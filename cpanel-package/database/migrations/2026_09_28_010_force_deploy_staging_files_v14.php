<?php
/**
 * Migration v-14.0.0: Force Deploy Staging Application Files to APP_ROOT
 * Removes floating mobile FAB share icon and ensures Admin View toolbar
 * is displayed only when an active admin account logged-in state is found.
 */

return function($pdo) {
    $app_root = defined('APP_ROOT') ? APP_ROOT : dirname(__DIR__, 2);
    $staging_root = dirname(__DIR__, 2);

    if (realpath($staging_root) && realpath($app_root) && realpath($staging_root) !== realpath($app_root)) {
        $protected_prefixes = ['data/', 'storage/', 'uploads/', 'logs/'];
        $protected_exact = ['config.php', '.env'];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($staging_root, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $rel_path = ltrim(str_replace('\\', '/', substr($item->getPathname(), strlen($staging_root))), '/');
            if ($rel_path === '') continue;

            $skip = false;
            foreach ($protected_exact as $pe) {
                if ($rel_path === $pe) { $skip = true; break; }
            }
            foreach ($protected_prefixes as $pp) {
                if (strpos($rel_path, $pp) === 0) { $skip = true; break; }
            }
            if ($skip) continue;

            $target = $app_root . '/' . $rel_path;
            if ($item->isDir()) {
                if (!is_dir($target)) {
                    @mkdir($target, 0755, true);
                }
            } else {
                $parent_dir = dirname($target);
                if (!is_dir($parent_dir)) {
                    @mkdir($parent_dir, 0755, true);
                }
                if (file_exists($target)) {
                    @chmod($target, 0644);
                    @unlink($target);
                }
                @copy($item->getPathname(), $target);
                @chmod($target, 0644);
                if (function_exists('opcache_invalidate') && substr($target, -4) === '.php') {
                    @opcache_invalidate($target, true);
                }
            }
        }
    }

    if (function_exists('opcache_reset')) {
        @opcache_reset();
    }
    clearstatcache(true);
};
