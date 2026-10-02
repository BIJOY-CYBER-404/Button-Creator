<?php
/**
 * Migration 015: Force-Deploy Staged Application Files & Real Session/Duration Telemetry Schema (v-19.0)
 *
 * Ensures that even when updating from an older version of class-updater.php,
 * all newly extracted staging files are copied directly into the live root directory,
 * analytics_visits has all session & duration columns, and config.php APP_VERSION is updated to v-19.0.
 */

return function($pdo) {
    $staging_dir = dirname(dirname(__DIR__));
    $root_dir    = dirname(dirname($staging_dir));

    if (basename($staging_dir) === 'staging' && is_dir($root_dir)) {
        $protected = ['config.php', 'data', 'storage', '.env'];

        $copy_recursive = function($src, $dst, $is_root = false) use (&$copy_recursive, $protected) {
            if (!is_dir($src)) return;
            @mkdir($dst, 0755, true);
            $items = @scandir($src);
            if (!is_array($items)) return;

            foreach ($items as $item) {
                if ($item === '.' || $item === '..') continue;
                if ($is_root && in_array($item, $protected, true)) {
                    continue;
                }
                $s = $src . '/' . $item;
                $d = $dst . '/' . $item;
                if (is_dir($s)) {
                    $copy_recursive($s, $d, false);
                } else {
                    @copy($s, $d);
                }
            }
        };

        $copy_recursive($staging_dir, $root_dir, true);

        $cfg_file = $root_dir . '/config.php';
        if (file_exists($cfg_file) && is_writable($cfg_file)) {
            $cfg = @file_get_contents($cfg_file);
            if ($cfg !== false) {
                $cfg_updated = preg_replace(
                    "/(define\s*\(\s*['\"]APP_VERSION['\"]\s*,\s*['\"])([^'\"]+)(['\"]\s*\)\s*;)/i",
                    '${1}v-19.0${3}',
                    $cfg
                );
                if ($cfg_updated && $cfg_updated !== $cfg) {
                    @file_put_contents($cfg_file, $cfg_updated);
                }
            }
        }
    }

    // Ensure analytics_visits table has session_id, duration_sec, and telemetry columns
    if ($pdo instanceof PDO) {
        $cols = [
            'session_id'      => 'VARCHAR(64) DEFAULT NULL',
            'channel'         => "VARCHAR(32) DEFAULT 'direct'",
            'traffic_channel' => "VARCHAR(32) DEFAULT 'direct'",
            'referrer_host'   => 'VARCHAR(191) DEFAULT NULL',
            'country'         => "VARCHAR(64) DEFAULT 'Direct / Private Network'",
            'country_code'    => "VARCHAR(12) DEFAULT 'GLB'",
            'duration_sec'    => 'INT DEFAULT 1',
            'ip_hash'         => 'VARCHAR(64) DEFAULT NULL',
            'user_agent'      => 'VARCHAR(255) DEFAULT NULL',
        ];
        foreach ($cols as $col => $def) {
            try {
                $pdo->exec("ALTER TABLE analytics_visits ADD COLUMN {$col} {$def}");
            } catch (Exception $e) {
                // Column already exists
            }
        }
    }

    if (function_exists('opcache_reset')) {
        @opcache_reset();
    }
};
