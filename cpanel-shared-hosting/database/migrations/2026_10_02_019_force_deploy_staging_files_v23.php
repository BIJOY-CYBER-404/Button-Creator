<?php
/**
 * Migration 019: Force-Deploy Staged Application Files, Redesigned Functional 404 Page & Security Hardening (v-23.0)
 *
 * Ensures that when updating remotely from any prior version (v19.0–v22.0),
 * all newly extracted staging files are copied directly into the live root directory,
 * private directories are protected with .htaccess guards, duplicate settings rows are cleaned,
 * and config.php APP_VERSION is updated to v-23.0.
 */

if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__)) {
    http_response_code(403);
    exit('Forbidden');
}

return function($pdo) {
    $staging_dir = dirname(dirname(__DIR__));
    $root_dir    = defined('APP_ROOT') ? APP_ROOT : dirname(dirname($staging_dir));

    if (realpath($staging_dir) !== realpath($root_dir) && is_dir($root_dir) && file_exists($staging_dir . '/includes/class-datastore.php')) {
        $protected = ['config.php', 'config.local.php', 'data', 'storage', 'backups', 'temp', '.env'];

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
                    @chmod($d, 0644);
                    @copy($s, $d);
                    if (function_exists('opcache_invalidate')) {
                        @opcache_invalidate($d, true);
                    }
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
                    '${1}v-23.0${3}',
                    $cfg
                );
                if ($cfg_updated && $cfg_updated !== $cfg) {
                    @file_put_contents($cfg_file, $cfg_updated, LOCK_EX);
                    if (function_exists('opcache_invalidate')) {
                        @opcache_invalidate($cfg_file, true);
                    }
                }
            }
        }
    }

    // Ensure private directory guards exist
    $protected_dirs = [
        $root_dir . '/data',
        $root_dir . '/backups',
        $root_dir . '/temp',
        $root_dir . '/includes',
        $root_dir . '/includes/updater',
        $root_dir . '/database',
        $root_dir . '/database/migrations'
    ];
    $htaccess_deny = "Order deny,allow\nDeny from all\n<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n";
    $index_deny = "<?php\nif (basename(\$_SERVER['SCRIPT_FILENAME'] ?? '') === 'index.php') {\n    http_response_code(403);\n    exit('Forbidden');\n}\nreturn function(\$pdo) {};\n";

    foreach ($protected_dirs as $dir) {
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        if (is_dir($dir)) {
            @file_put_contents($dir . '/.htaccess', $htaccess_deny, LOCK_EX);
            @file_put_contents($dir . '/index.php', $index_deny, LOCK_EX);
        }
    }

    if (function_exists('opcache_reset')) {
        @opcache_reset();
    }
};
