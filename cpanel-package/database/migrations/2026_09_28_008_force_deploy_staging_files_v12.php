<?php
/**
 * Migration v-12.0.0: Force Deploy Staging Application Files to APP_ROOT
 * Guarantees all updated PHP files (including view.php, class-datastore.php, class-auth.php, .htaccess)
 * and assets are deployed with proper permissions and OPcache invalidation during Step 7.
 */

return function($pdo, $driver) {
    $staging_dir = dirname(__DIR__, 2);
    $app_root    = defined('APP_ROOT') ? APP_ROOT : dirname($staging_dir, 3);

    if (realpath($staging_dir) === realpath($app_root) || !is_dir($staging_dir) || !is_dir($app_root)) {
        return;
    }

    $skip_Protected = [
        'data',
        'config.local.php',
        'database.sqlite',
        '.git',
        '.env',
        'error_log'
    ];

    $copy_recursive = function($src, $dst, $rel_prefix = '') use (&$copy_recursive, $skip_Protected) {
        if (!is_dir($dst)) {
            @mkdir($dst, 0755, true);
        }
        $items = @scandir($src);
        if ($items === false) return;

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            $rel_path = $rel_prefix === '' ? $item : ($rel_prefix . '/' . $item);

            $is_protected = false;
            foreach ($skip_Protected as $prot) {
                if ($rel_path === $prot || strpos($rel_path, $prot . '/') === 0) {
                    $is_protected = true;
                    break;
                }
            }
            if ($is_protected) continue;

            if ($rel_path === 'config.php' && file_exists($dst . '/config.php')) {
                $existing_cfg = @file_get_contents($dst . '/config.php');
                $new_cfg      = @file_get_contents($src . '/config.php');
                if ($existing_cfg !== false && $new_cfg !== false) {
                    if (preg_match("/define\(\s*'APP_VERSION'\s*,\s*'([^']+)'\s*\)/", $new_cfg, $m)) {
                        $updated_cfg = preg_replace(
                            "/define\(\s*'APP_VERSION'\s*,\s*'[^']+'\s*\)/",
                            "define('APP_VERSION', '" . $m[1] . "')",
                            $existing_cfg
                        );
                        @chmod($dst . '/config.php', 0644);
                        @file_put_contents($dst . '/config.php', $updated_cfg);
                        if (function_exists('opcache_invalidate')) {
                            @opcache_invalidate($dst . '/config.php', true);
                        }
                        continue;
                    }
                }
            }

            $s_path = $src . '/' . $item;
            $d_path = $dst . '/' . $item;

            if (is_dir($s_path)) {
                $copy_recursive($s_path, $d_path, $rel_path);
            } else {
                $parent_dir = dirname($d_path);
                if (!is_dir($parent_dir)) {
                    @mkdir($parent_dir, 0755, true);
                }
                if (file_exists($d_path)) {
                    @chmod($d_path, 0644);
                    @unlink($d_path);
                }
                $bytes = @file_get_contents($s_path);
                if ($bytes !== false) {
                    @file_put_contents($d_path, $bytes);
                } else {
                    @copy($s_path, $d_path);
                }
                @chmod($d_path, 0644);
                if (function_exists('opcache_invalidate') && substr($d_path, -4) === '.php') {
                    @opcache_invalidate($d_path, true);
                }
            }
        }
    };

    $copy_recursive($staging_dir, $app_root, '');

    if (function_exists('opcache_reset')) {
        @opcache_reset();
    }
    clearstatcache(true);
};
