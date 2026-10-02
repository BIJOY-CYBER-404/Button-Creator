<?php
/**
 * Migration 024: Season End Message Support & Remote Update Release v-28.0
 *
 * Extracts and displays (<div style="text-align:center;color:red;font-weight:bold;margin-top:10px;">Season End</div>)
 * messages in the last of all button links.
 * Adds end_message_html column to pages table and deploys updated files to root.
 */

if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__)) {
    http_response_code(403);
    exit('Forbidden');
}

return function($pdo) {
    // 1. Ensure end_message_html column exists in pages table
    try {
        $pdo->exec("ALTER TABLE pages ADD COLUMN end_message_html TEXT");
    } catch (Exception $e) {
        // Column already exists or table updated
    }

    // 2. Synchronize staging files to root during remote update
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

        // Update APP_VERSION in root config.php to v-28.0
        $cfg_file = $root_dir . '/config.php';
        if (file_exists($cfg_file) && is_writable($cfg_file)) {
            $cfg = @file_get_contents($cfg_file);
            if ($cfg !== false) {
                $cfg_updated = preg_replace(
                    "/(define\s*\(\s*['\"]APP_VERSION['\"]\s*,\s*['\"])([^'\"]+)(['\"]\s*\)\s*;)/i",
                    '${1}v-28.0${3}',
                    $cfg
                );
                if (strpos($cfg_updated, 'AUTH_COOKIE_LIFETIME') === false) {
                    $cfg_updated .= "\nif (!defined('AUTH_COOKIE_LIFETIME')) {\n    define('AUTH_COOKIE_LIFETIME', 7200);\n}\n";
                }
                if ($cfg_updated && $cfg_updated !== $cfg) {
                    @file_put_contents($cfg_file, $cfg_updated, LOCK_EX);
                }
            }
        }
    }

    return [
        'success' => true,
        'message' => 'Migration 024: Season End message support and files synchronized to v-28.0'
    ];
};
