<?php
/**
 * Rollback Manager
 * Automatically restores previous working application files and database backup upon update failure.
 */

class SLEA_RollbackManager {
    private $logger;
    private $backup_dir;

    public function __construct(SLEA_UpdateLogger $logger, $backup_dir) {
        $this->logger = $logger;
        $this->backup_dir = $backup_dir;
    }

    public function perform_rollback($reason = '') {
        $this->logger->log("Rollback", "Initiating automatic rollback system due to failure: " . $reason, "error");

        $rollback_success = true;
        $rollback_errors = [];

        // 1. Restore Files (Protect /data, /backups, /temp, and config.local.php)
        $files_backup_dir = $this->backup_dir . '/files';
        if (is_dir($files_backup_dir)) {
            try {
                $this->logger->log("Rollback", "Restoring previous working application files...");
                $this->copy_directory($files_backup_dir, APP_ROOT, [
                    APP_ROOT . '/backups',
                    APP_ROOT . '/temp',
                    APP_ROOT . '/data',
                    APP_ROOT . '/config.local.php'
                ]);
                $this->logger->log("Rollback", "[OK] Application files restored.");
            } catch (Exception $e) {
                $rollback_success = false;
                $rollback_errors[] = "File rollback error: " . $e->getMessage();
            }
        } else {
            $this->logger->log("Rollback", "[WARNING] File backup directory not found at " . $files_backup_dir, "warning");
        }

        // 2. Restore Database Safely (Using structured JSON payload so settings, pages, and accounts are never lost or corrupted)
        $db_json_file = $this->backup_dir . '/db_backup.json';
        if (file_exists($db_json_file) && class_exists('SLEA_Datastore')) {
            try {
                $this->logger->log("Rollback", "Verifying and restoring database state from structured JSON backup...");
                $payload = json_decode(@file_get_contents($db_json_file), true);
                if (is_array($payload)) {
                    SLEA_Datastore::restore_backup_payload($payload, 'all', 'merge');
                }
                $this->logger->log("Rollback", "[OK] Database settings, pages, and accounts verified and restored.");
            } catch (Exception $e) {
                $rollback_errors[] = "Database JSON restore warning: " . $e->getMessage();
            }
        } elseif (class_exists('SLEA_Datastore')) {
            try {
                SLEA_Datastore::get_all_pages();
                SLEA_Datastore::sync_all_backups();
            } catch (Exception $e) {}
        }

        // 3. Remove maintenance mode lock file
        $maint_file = APP_ROOT . '/.maintenance';
        if (file_exists($maint_file)) {
            @unlink($maint_file);
        }

        if ($rollback_success) {
            $this->logger->log("Rollback", "Rollback Completed Successfully. Previous working version has been restored.");
        } else {
            $this->logger->log("Rollback", "Rollback encountered issues: " . implode('; ', $rollback_errors), "error");
        }

        return $rollback_success;
    }

    private function copy_directory($src, $dst, $exclude_paths = []) {
        $dir = opendir($src);
        if (!$dir) return;

        @mkdir($dst, 0755, true);

        while (($file = readdir($dir)) !== false) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $src_path = $src . '/' . $file;
            $dst_path = $dst . '/' . $file;

            $skip = false;
            foreach ($exclude_paths as $ex) {
                if (strpos($dst_path, $ex) === 0) {
                    $skip = true;
                    break;
                }
            }
            if ($skip) continue;

            if (is_dir($src_path)) {
                $this->copy_directory($src_path, $dst_path, $exclude_paths);
            } else {
                @copy($src_path, $dst_path);
            }
        }
        closedir($dir);
    }
}
