<?php
/**
 * Migration: Force Deploy Staging Application Files to APP_ROOT
 * Bypasses legacy copy_staging_files temp-path exclusion bug on older installations (< v-8.0.0)
 * and ensures all updated PHP files (settings.php, api.php, includes/*, etc.) are atomically written
 * and OPcache-invalidated during Step 7 of One-Click Update.
 */

(function($staging_param, $logger_obj) {
    $src_root = (!empty($staging_param) && is_dir($staging_param)) ? rtrim($staging_param, '/\\') : dirname(__DIR__, 2);
    $dst_root = defined('APP_ROOT') ? rtrim(APP_ROOT, '/\\') : dirname(__DIR__, 4);

    if (!is_dir($src_root) || !is_dir($dst_root)) {
        return;
    }

    // Only run when executing from a staging directory outside APP_ROOT (i.e. during an active update)
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

        while (($file = readdir($dir)) !== false) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            // Never touch live user data, backups, temp workspace, local config, or maintenance lock
            if ($file === 'data' || $file === 'backups' || $file === 'temp' || $file === 'config.local.php' || $file === '.maintenance') {
                continue;
            }

            $src_path = $src . '/' . $file;
            $dst_path = $dst . '/' . $file;

            // Preserve existing config.php (version constant is updated separately by updater)
            if ($file === 'config.php' && file_exists($dst_path)) {
                continue;
            }

            // Protect top-level excluded directories in destination
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
                    @chmod($dst_path, 0777);
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
        @clearstatcache(true);
        if (function_exists('opcache_reset')) {
            @opcache_reset();
        }
        if ($logger_obj && method_exists($logger_obj, 'log')) {
            $logger_obj->log("Running database migrations", "[OK] Synchronized {$deployed_count} updated application files from staging workspace to production root.");
        }
    } catch (Throwable $e) {
        error_log("Force deploy staging migration notice: " . $e->getMessage());
    }
})(isset($staging_dir) ? $staging_dir : '', isset($this) && isset($this->logger) ? $this->logger : null);
