<?php
/**
 * Migration 016: Force-Deploy Staged Application Files, About Us Page, Humanized Legal Pages & Debug Mode Error Handler (v-20.0)
 *
 * Ensures that even when updating from an older version of class-updater.php,
 * all newly extracted staging files are copied directly into the live root directory,
 * default debug_settings are initialized if not present, and config.php APP_VERSION is updated to v-20.0.
 */

return function($pdo) {
    $staging_dir = dirname(dirname(__DIR__));
    $root_dir    = dirname(dirname($staging_dir));

    if (strpos(basename($staging_dir), 'staging') !== false && is_dir($root_dir)) {
        $protected = ['config.php', 'data', 'storage', 'backups', 'temp', '.env'];

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
                    '${1}v-20.0${3}',
                    $cfg
                );
                if ($cfg_updated && $cfg_updated !== $cfg) {
                    @file_put_contents($cfg_file, $cfg_updated);
                }
            }
        }
    }

    // Ensure default debug_settings (OFF by default) exists in settings table if not set
    if ($pdo instanceof PDO) {
        try {
            $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'debug_settings' LIMIT 1");
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                $ins = $pdo->prepare("INSERT INTO settings (setting_key, setting_value, updated_at) VALUES ('debug_settings', :val, :now)");
                $ins->execute([
                    ':val' => json_encode(['enabled' => false]),
                    ':now' => date('Y-m-d H:i:s')
                ]);
            }
        } catch (Exception $e) {
            // Non-blocking
        }
    }

    if (function_exists('opcache_reset')) {
        @opcache_reset();
    }
};
