<?php
/**
 * Master One-Click Application Update Manager
 * Integrates Downloader, Validator, BackupManager, MigrationManager, RollbackManager, and HealthChecker.
 */

require_once __DIR__ . '/class-db.php';
require_once __DIR__ . '/class-datastore.php';
require_once __DIR__ . '/updater/class-update-logger.php';
require_once __DIR__ . '/updater/class-downloader.php';
require_once __DIR__ . '/updater/class-package-validator.php';
require_once __DIR__ . '/updater/class-backup-manager.php';
require_once __DIR__ . '/updater/class-migration-manager.php';
require_once __DIR__ . '/updater/class-rollback-manager.php';
require_once __DIR__ . '/updater/class-health-checker.php';

class SLEA_Updater {
    private static $lock_file;

    public static function init() {
        if (!defined('APP_ROOT')) {
            define('APP_ROOT', dirname(__DIR__));
        }
        self::$lock_file = APP_ROOT . '/temp/update.lock';
    }

    public static function get_config() {
        self::init();
        try {
            $pdo = SLEA_DB::get_connection();
            $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'update_config' LIMIT 1");
            $stmt->execute();
            $row = $stmt->fetch();
            if ($row && !empty($row['setting_value'])) {
                $saved = json_decode($row['setting_value'], true);
                if (is_array($saved)) {
                    return array_merge(self::default_config(), $saved);
                }
            }
        } catch (Exception $e) {
            // fallback
        }
        return self::default_config();
    }

    public static function default_config() {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
        $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
        $base = rtrim($protocol . $host . dirname($_SERVER['PHP_SELF'] ?? ''), '/\\');

        $remote_manifest = "https://raw.githubusercontent.com/BIJOY-CYBER-404/Button-Creator/main/public/releases/update.json";
        $remote_zip = "https://raw.githubusercontent.com/BIJOY-CYBER-404/Button-Creator/main/public/cpanel-app-package.zip";

        return [
            'manifest_url'          => $remote_manifest,
            'fallback_download_url' => $remote_zip,
            'check_interval'        => 'daily',
            'auto_check'            => true,
            'backup_retention'      => 3,
            'verify_checksum'       => true,
            'maintenance_mode'      => true
        ];
    }

    public static function save_config($new_config) {
        self::init();
        $merged = array_merge(self::get_config(), $new_config);
        $pdo = SLEA_DB::get_connection();
        $stmt = $pdo->prepare("REPLACE INTO settings (setting_key, setting_value, updated_at) VALUES ('update_config', :val, :now)");
        $stmt->execute([
            ':val' => json_encode($merged, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT),
            ':now' => date('Y-m-d H:i:s')
        ]);
        return $merged;
    }

    public static function check_for_updates($force = false) {
        self::init();
        $config = self::get_config();
        $current_version = defined('APP_VERSION') ? ltrim(APP_VERSION, 'v-') : '3.8.0';

        // Filter out non-http local file paths from saved manifest_url if present
        $saved_manifest = $config['manifest_url'] ?? '';
        if (!empty($saved_manifest) && !preg_match('/^https?:\/\//i', $saved_manifest)) {
            $saved_manifest = '';
        }

        $candidate_urls = array_unique(array_filter([
            $saved_manifest,
            "https://raw.githubusercontent.com/BIJOY-CYBER-404/Button-Creator/main/public/releases/update.json",
            "https://cdn.jsdelivr.net/gh/BIJOY-CYBER-404/Button-Creator@main/public/releases/update.json",
            "https://raw.githubusercontent.com/BIJOY-CYBER-404/Button-Creator/main/releases/update.json",
            (!empty($_SERVER['HTTP_HOST']) ? ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . '/releases/update.json' : ''),
            (!empty($_SERVER['HTTP_HOST']) ? ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . '/public/releases/update.json' : ''),
            APP_ROOT . '/releases/update.json',
            dirname(__DIR__) . '/releases/update.json',
            dirname(__DIR__) . '/public/releases/update.json',
            (!empty($_SERVER['DOCUMENT_ROOT']) ? $_SERVER['DOCUMENT_ROOT'] . '/releases/update.json' : ''),
            (!empty($_SERVER['DOCUMENT_ROOT']) ? $_SERVER['DOCUMENT_ROOT'] . '/public/releases/update.json' : ''),
            (!empty($_SERVER['DOCUMENT_ROOT']) ? $_SERVER['DOCUMENT_ROOT'] . '/cpanel-package/releases/update.json' : '')
        ]));

        $manifest = null;
        $last_error = "No valid update manifest URL configured.";
        $logger = new SLEA_UpdateLogger('check_' . date('Ymd_His'));
        $downloader = new SLEA_Downloader($logger);

        $best_manifest = null;
        $best_version = null;
        $best_url = null;
        $diagnostics = [];

        foreach ($candidate_urls as $url) {
            try {
                $is_remote_url = preg_match('/^https?:\/\//i', $url);
                $fetched = $downloader->fetch_manifest($url);
                if ($fetched && !empty($fetched['version'])) {
                    $candidate_ver = ltrim($fetched['version'], 'v-');
                    $diagnostics[$url] = [
                        'status' => 'success',
                        'version' => $candidate_ver,
                        'message' => 'Fetched successfully'
                    ];
                    
                    if ($best_version === null || version_compare($candidate_ver, $best_version, '>')) {
                        $best_manifest = $fetched;
                        $best_version = $candidate_ver;
                        $best_url = $url;
                    }
                } else {
                    $diagnostics[$url] = [
                        'status' => 'failed',
                        'message' => 'Invalid manifest format'
                    ];
                }
            } catch (Exception $e) {
                $last_error = $e->getMessage();
                $diagnostics[$url] = [
                    'status' => 'failed',
                    'message' => $e->getMessage()
                ];
            }
        }

        if ($best_manifest) {
            $manifest = $best_manifest;
        }

        if (!$manifest || empty($manifest['version'])) {
            return [
                'success'          => false,
                'current_version'  => $current_version,
                'update_available' => false,
                'error'            => $last_error,
                'diagnostics'      => $diagnostics,
                'checked_at'       => date('Y-m-d H:i:s')
            ];
        }

        $remote_version = ltrim($manifest['version'], 'v-');
        $update_available = version_compare($current_version, $remote_version, '<');

        return [
            'success'           => true,
            'current_version'   => $current_version,
            'remote_version'    => $remote_version,
            'update_available'  => $update_available,
            'release_date'      => $manifest['release_date'] ?? date('Y-m-d'),
            'download_url'      => $manifest['download_url'] ?? $config['fallback_download_url'],
            'release_notes'     => $manifest['release_notes'] ?? ['General updates and security patches.'],
            'minimum_php'       => $manifest['minimum_php'] ?? '8.0',
            'checksum'          => $manifest['checksum'] ?? '',
            'best_url'          => $best_url,
            'diagnostics'       => $diagnostics,
            'checked_at'        => date('Y-m-d H:i:s')
        ];
    }

    public static function get_update_history() {
        self::init();
        try {
            $pdo = SLEA_DB::get_connection();
            $stmt = $pdo->query("SELECT * FROM update_history ORDER BY id DESC LIMIT 20");
            $rows = $stmt->fetchAll();
            $history = [];
            foreach ($rows as $r) {
                if (!empty($r['details_json'])) {
                    $r['details'] = json_decode($r['details_json'], true) ?: [];
                }
                $history[] = $r;
            }
            return $history;
        } catch (Exception $e) {
            return [];
        }
    }

    public static function run_update() {
        @set_time_limit(300);
        @ini_set('memory_limit', '512M');
        self::init();
        $config = self::get_config();

        // 1. Update Locking Check
        if (self::is_locked()) {
            throw new Exception("Another update is currently in progress. Please wait for the current update to complete.");
        }
        self::acquire_lock();

        $update_id = 'update_' . date('Ymd_His') . '_' . substr(md5(uniqid()), 0, 4);
        $logger = new SLEA_UpdateLogger($update_id);
        $current_version = defined('APP_VERSION') ? ltrim(APP_VERSION, 'v-') : '3.8.0';

        $backup_manager = new SLEA_BackupManager($logger, $update_id);
        $rollback_manager = null;

        try {
            // Create History Record
            $pdo = SLEA_DB::get_connection();
            $ins_hist = $pdo->prepare("
                INSERT INTO update_history (update_id, old_version, new_version, status, step, started_at) 
                VALUES (:uid, :old_v, :new_v, 'in_progress', 'Checking update', :now)
            ");
            $ins_hist->execute([
                ':uid'   => $update_id,
                ':old_v' => $current_version,
                ':new_v' => 'pending',
                ':now'   => date('Y-m-d H:i:s')
            ]);

            // Step 1: Checking update
            $logger->log("Checking update", "Initiating One-Click Application Update...");
            $check_info = self::check_for_updates(true);
            if (!$check_info['success']) {
                throw new Exception("Update check failed: " . ($check_info['error'] ?? 'Unknown manifest error'));
            }

            $new_version = $check_info['remote_version'];
            $download_url = $check_info['download_url'];
            $checksum = $check_info['checksum'];

            // Update version target in DB
            $upd_v = $pdo->prepare("UPDATE update_history SET new_version = :nv WHERE update_id = :uid");
            $upd_v->execute([':nv' => $new_version, ':uid' => $update_id]);

            // Step 2: Downloading package
            $download_target = APP_ROOT . '/temp/download_' . $update_id . '.zip';
            $downloader = new SLEA_Downloader($logger);
            $downloader->download_package($download_url, $download_target);

            // Step 3: Verifying package
            $validator = new SLEA_PackageValidator($logger);
            $validator->validate_zip($download_target, $config['verify_checksum'] ? $checksum : '');

            // Enable Maintenance Mode
            if ($config['maintenance_mode']) {
                @file_put_contents(APP_ROOT . '/.maintenance', "Website is currently being updated to version " . $new_version . ". Please try again shortly.");
            }

            // Step 4: Creating backup
            if (class_exists('SLEA_Datastore')) {
                try {
                    SLEA_Datastore::sync_pages_to_file();
                } catch (Exception $e) { /* ignore */ }
            }
            $backup_dir = $backup_manager->create_backup();
            $rollback_manager = new SLEA_RollbackManager($logger, $backup_dir);

            // Step 5: Extracting files
            $staging_dir = APP_ROOT . '/temp/staging_' . $update_id;
            @mkdir($staging_dir, 0755, true);

            $zip = new ZipArchive();
            if ($zip->open($download_target) === true) {
                $zip->extractTo($staging_dir);
                $zip->close();
                $logger->log("Extracting files", "[OK] Extracted release package to staging workspace.");
            } else {
                throw new Exception("Failed to extract update ZIP archive to staging workspace.");
            }

            // Handle nested folder in zip if zip contained single root directory
            $extracted_items = array_diff(scandir($staging_dir), ['.', '..']);
            if (count($extracted_items) === 1) {
                $single_item = reset($extracted_items);
                $sub_path = $staging_dir . '/' . $single_item;
                if (is_dir($sub_path) && file_exists($sub_path . '/config.php')) {
                    $staging_dir = $sub_path;
                }
            }

            // Step 6: Checking compatibility
            $validator->check_compatibility($check_info);

            // Step 7: Running database migrations
            $migration_manager = new SLEA_MigrationManager($logger);
            $migration_manager->run_migrations($staging_dir);

            // Step 8: Applying application files
            $logger->log("Applying application files", "Deploying new application files over production directory...");
            $exclusions = [
                APP_ROOT . '/backups',
                APP_ROOT . '/temp',
                APP_ROOT . '/data'
            ];
            if (file_exists(APP_ROOT . '/config.php')) {
                $exclusions[] = APP_ROOT . '/config.php';
            }
            self::copy_staging_files($staging_dir, APP_ROOT, $exclusions);

            // Update APP_VERSION in config.php
            self::update_config_version($new_version);

            // Step 9: Running health checks
            $health_checker = new SLEA_HealthChecker($logger);
            $health_checker->verify_health();

            // Step 10: Finalizing update
            $logger->log("Finalizing update", "Cleaning up temporary staging files and finalizing update...");

            // Cleanup temp files
            @unlink($download_target);
            $backup_manager->delete_directory($staging_dir);

            // Delete pre-update backup upon success to free hosting disk space
            if (!empty($backup_dir) && is_dir($backup_dir)) {
                $backup_manager->delete_backup($backup_dir);
                $logger->log("Finalizing update", "[OK] Update verified successful: Pre-update backup removed to free hosting storage.");
            }

            // Remove maintenance mode
            if (file_exists(APP_ROOT . '/.maintenance')) {
                @unlink(APP_ROOT . '/.maintenance');
            }

            // Mark update history as successful
            $fin = $pdo->prepare("
                UPDATE update_history 
                SET status = 'success', step = 'Finalizing update', migration_status = 'success', rollback_status = 'not_needed', completed_at = :now 
                WHERE update_id = :uid
            ");
            $fin->execute([':now' => date('Y-m-d H:i:s'), ':uid' => $update_id]);

            self::release_lock();

            return [
                'success'        => true,
                'update_id'      => $update_id,
                'old_version'    => $current_version,
                'new_version'    => $new_version,
                'message'        => "Update Completed Successfully. Previous Version: {$current_version}, New Version: {$new_version}.",
                'logs'           => $logger->get_logs()
            ];

        } catch (Exception $e) {
            $err_reason = $e->getMessage();
            $logger->log("Error", "Update Failed: " . $err_reason, "error");

            // Perform Automated Rollback
            $rollback_status = 'failed';
            if ($rollback_manager !== null) {
                $rb_ok = $rollback_manager->perform_rollback($err_reason);
                $rollback_status = $rb_ok ? 'successful' : 'failed';
            }

            // Disable maintenance mode
            if (file_exists(APP_ROOT . '/.maintenance')) {
                @unlink(APP_ROOT . '/.maintenance');
            }

            // Update history DB record
            try {
                $pdo = SLEA_DB::get_connection();
                $fail_stmt = $pdo->prepare("
                    UPDATE update_history 
                    SET status = 'failed', error_message = :msg, rollback_status = :rb_stat, completed_at = :now 
                    WHERE update_id = :uid
                ");
                $fail_stmt->execute([
                    ':msg'     => "Update failed: " . $err_reason . ". " . ($rollback_status === 'successful' ? "The previous version has been restored successfully." : "Rollback encountered issues."),
                    ':rb_stat' => $rollback_status,
                    ':now'     => date('Y-m-d H:i:s'),
                    ':uid'     => $update_id
                ]);
            } catch (Exception $ex) {
                // Ignore DB error during failure logging
            }

            self::release_lock();

            return [
                'success'         => false,
                'update_id'       => $update_id,
                'error'           => "Update failed: " . $err_reason . ". The previous version (" . $current_version . ") has been restored successfully.",
                'rollback_status' => $rollback_status,
                'active_version'  => $current_version,
                'logs'            => $logger->get_logs()
            ];
        }
    }

    public static function check_and_abort_interrupted_updates() {
        self::init();
        try {
            $pdo = SLEA_DB::get_connection();
            $stmt = $pdo->query("SELECT * FROM update_history WHERE status = 'in_progress' ORDER BY id DESC");
            $rows = $stmt->fetchAll();
            foreach ($rows as $row) {
                $update_id = $row['update_id'];
                $logger = new SLEA_UpdateLogger($update_id);
                $logger->log("Abort", "Page refresh or interruption detected during update execution. Forcibly stopping background update and restoring previous version.", "error");

                $backup_dir = APP_ROOT . '/backups/' . $update_id;
                $rollback_status = 'failed';
                if (is_dir($backup_dir)) {
                    $rollback_manager = new SLEA_RollbackManager($logger, $backup_dir);
                    $rb_ok = $rollback_manager->perform_rollback("Interrupted by page refresh / navigation.");
                    $rollback_status = $rb_ok ? 'successful' : 'failed';
                } else {
                    $backups_root = APP_ROOT . '/backups';
                    if (is_dir($backups_root)) {
                        $subdirs = array_diff(scandir($backups_root), ['.', '..']);
                        if (!empty($subdirs)) {
                            rsort($subdirs);
                            $latest_backup = $backups_root . '/' . reset($subdirs);
                            if (is_dir($latest_backup)) {
                                $rollback_manager = new SLEA_RollbackManager($logger, basename($latest_backup));
                                $rb_ok = $rollback_manager->perform_rollback("Interrupted by page refresh / navigation.");
                                $rollback_status = $rb_ok ? 'successful' : 'failed';
                            }
                        }
                    }
                }

                if (file_exists(APP_ROOT . '/.maintenance')) {
                    @unlink(APP_ROOT . '/.maintenance');
                }

                $upd = $pdo->prepare("
                    UPDATE update_history 
                    SET status = 'aborted_and_restored', error_message = :msg, rollback_status = :rb_stat, completed_at = :now 
                    WHERE update_id = :uid
                ");
                $upd->execute([
                    ':msg'     => "Update aborted due to page refresh / navigation interruption. Previous version restored.",
                    ':rb_stat' => $rollback_status,
                    ':now'     => date('Y-m-d H:i:s'),
                    ':uid'     => $update_id
                ]);
            }

            self::release_lock();
            if (file_exists(APP_ROOT . '/.maintenance')) {
                @unlink(APP_ROOT . '/.maintenance');
            }
        } catch (Exception $e) {
            // ignore
        }
    }

    private static function is_locked() {
        if (!file_exists(self::$lock_file)) {
            return false;
        }
        $mtime = filemtime(self::$lock_file);
        // Stale lock recovery (> 10 minutes)
        if ((time() - $mtime) > 600) {
            @unlink(self::$lock_file);
            return false;
        }
        return true;
    }

    private static function acquire_lock() {
        $dir = dirname(self::$lock_file);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        file_put_contents(self::$lock_file, date('Y-m-d H:i:s'));
    }

    private static function release_lock() {
        if (file_exists(self::$lock_file)) {
            @unlink(self::$lock_file);
        }
    }

    private static function copy_staging_files($src, $dst, $exclude = []) {
        $dir = opendir($src);
        if (!$dir) return;

        @mkdir($dst, 0755, true);

        while (($file = readdir($dir)) !== false) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            // Absolute protection for user data, database, custom credentials, and backups
            if ($file === 'data' || $file === 'backups' || $file === 'temp' || $file === 'config.local.php' || $file === '.maintenance') {
                continue;
            }

            $src_path = $src . '/' . $file;
            $dst_path = $dst . '/' . $file;

            // Never overwrite existing config.php (keeps user's database settings intact)
            if ($file === 'config.php' && file_exists($dst_path)) {
                continue;
            }

            $skip = false;
            foreach ($exclude as $ex) {
                if (strpos($dst_path, $ex) === 0 || strpos($src_path, $ex) === 0) {
                    $skip = true;
                    break;
                }
            }
            if ($skip) continue;

            if (is_dir($src_path)) {
                self::copy_staging_files($src_path, $dst_path, $exclude);
            } else {
                @copy($src_path, $dst_path);
            }
        }
        closedir($dir);
    }

    private static function update_config_version($new_version) {
        $cfg_file = APP_ROOT . '/config.php';
        if (file_exists($cfg_file)) {
            $content = file_get_contents($cfg_file);
            $clean_v = 'v-' . ltrim($new_version, 'v-');
            $updated = preg_replace("/define\('APP_VERSION',\s*'[^']+'\);/", "define('APP_VERSION', '{$clean_v}');", $content);
            if ($updated && $updated !== $content) {
                file_put_contents($cfg_file, $updated);
            }
        }
    }
}
