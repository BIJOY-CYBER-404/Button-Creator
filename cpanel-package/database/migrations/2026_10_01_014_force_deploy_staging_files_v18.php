<?php
/**
 * Migration v-18.0: Force Deploy Staging Application Files to APP_ROOT
 * Standardizes version format to v-x.x (v-18.0), hardens security across all endpoints and .htaccess,
 * and initializes the analytics_visits table for 100% real visitor telemetry (Device Breakdown, Traffic Channels, Country).
 */

(function($staging_param, $logger_obj, $pdo_obj) {
    $src_root = (!empty($staging_param) && is_dir($staging_param)) ? rtrim($staging_param, '/\\') : dirname(__DIR__, 2);
    $dst_root = defined('APP_ROOT') ? rtrim(APP_ROOT, '/\\') : dirname(__DIR__, 4);

    // 1. Ensure analytics_visits table exists for 100% real visitor telemetry
    if ($pdo_obj) {
        try {
            $driver = $pdo_obj->getAttribute(PDO::ATTR_DRIVER_NAME);
            $is_mysql = ($driver === 'mysql');
            $auto_inc = $is_mysql ? 'INT AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
            $table_engine = $is_mysql ? 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci' : '';
            $pdo_obj->exec("
                CREATE TABLE IF NOT EXISTS analytics_visits (
                    id {$auto_inc},
                    session_id VARCHAR(64) NOT NULL,
                    page_slug VARCHAR(120) DEFAULT '',
                    device_type VARCHAR(30) NOT NULL,
                    channel VARCHAR(40) NOT NULL,
                    referrer_host VARCHAR(190) DEFAULT '',
                    country VARCHAR(100) NOT NULL,
                    country_code VARCHAR(10) DEFAULT '',
                    duration_sec INT DEFAULT 0,
                    visit_date VARCHAR(10) NOT NULL,
                    year_month VARCHAR(7) NOT NULL,
                    created_at DATETIME,
                    updated_at DATETIME
                ) {$table_engine}
            ");
        } catch (Throwable $e) {
            // Non-blocking
        }
    }

    if (!is_dir($src_root) || !is_dir($dst_root)) {
        return;
    }

    if (@realpath($src_root) === @realpath($dst_root)) {
        return;
    }

    if (!file_exists($src_root . '/settings.php') || !file_exists($src_root . '/index.php')) {
        return;
    }

    $deployed_count = 0;

    $deploy_tree = null;
    $deploy_tree = function($src, $dst) use (&$deploy_tree, &$deployed_count, $dst_root) {
        $dir = @opendir($src);
        if (!$dir) {
            return;
        }

        if (!is_dir($dst)) {
            @mkdir($dst, 0755, true);
        }
        @chmod($dst, 0755);

        while (($file = readdir($dir)) !== false) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            if ($file === 'data' || $file === 'backups' || $file === 'temp' || $file === 'config.local.php' || $file === '.maintenance') {
                continue;
            }

            $src_path = $src . '/' . $file;
            $dst_path = $dst . '/' . $file;

            if ($file === 'config.php' && file_exists($dst_path)) {
                continue;
            }

            if (
                strpos($dst_path, $dst_root . '/backups') === 0 ||
                strpos($dst_path, $dst_root . '/temp') === 0 ||
                strpos($dst_path, $dst_root . '/data') === 0
            ) {
                continue;
            }

            if (is_dir($src_path)) {
                $deploy_tree($src_path, $dst_path);
            } else {
                if (file_exists($dst_path)) {
                    @chmod($dst_path, 0644);
                    @unlink($dst_path);
                }
                $copied = @copy($src_path, $dst_path);
                if (!$copied || !file_exists($dst_path)) {
                    $raw = @file_get_contents($src_path);
                    if ($raw !== false) {
                        @file_put_contents($dst_path, $raw, LOCK_EX);
                    }
                }
                @chmod($dst_path, 0644);
                if (function_exists('opcache_invalidate')) {
                    @opcache_invalidate($dst_path, true);
                }
                $deployed_count++;
            }
        }
        closedir($dir);
    };

    try {
        $deploy_tree($src_root, $dst_root);

        // Update APP_VERSION in existing config.php to v-18.0
        $cfg_path = $dst_root . '/config.php';
        if (file_exists($cfg_path)) {
            $cfg_content = @file_get_contents($cfg_path);
            if ($cfg_content !== false) {
                $updated_cfg = preg_replace("/define\(\s*'APP_VERSION'\s*,\s*'[^']+'\s*\)\s*;/", "define('APP_VERSION', 'v-18.0');", $cfg_content);
                if ($updated_cfg && $updated_cfg !== $cfg_content) {
                    @file_put_contents($cfg_path, $updated_cfg);
                    if (function_exists('opcache_invalidate')) {
                        @opcache_invalidate($cfg_path, true);
                    }
                }
            }
        }

        @clearstatcache(true);
        if (function_exists('opcache_reset')) {
            @opcache_reset();
        }
        if ($logger_obj && method_exists($logger_obj, 'log')) {
            $logger_obj->log("Running database migrations", "[OK] Synchronized {$deployed_count} application files (v-18.0) from staging to production root.");
        }
    } catch (Throwable $e) {
        error_log("Force deploy v-18.0 migration notice: " . $e->getMessage());
    }
})(
    isset($staging_dir) ? $staging_dir : '',
    isset($this) && isset($this->logger) ? $this->logger : null,
    isset($pdo) ? $pdo : null
);
