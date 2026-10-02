<?php
/**
 * Migration 017: Force-Deploy Staging Files for v21.0
 *
 * Ensures that even if a previous version of class-updater.php is executing the update,
 * all updated application files (including fixed Debug Mode & Public Error Handling in
 * class-datastore.php, view.php, index.php, login.php, settings.php, api.php, and .htaccess)
 * are copied from the active staging directory into APP_ROOT, and any duplicate settings rows
 * are deduplicated so the latest debug_settings value is always read.
 */

return function (PDO $pdo, string $driver = '') {
    $root = defined('APP_ROOT') ? APP_ROOT : dirname(__DIR__, 2);
    $stagingBase = (defined('DATA_DIR') ? DATA_DIR : $root . '/data') . '/updates/staging';

    if (is_dir($stagingBase)) {
        $dirs = glob($stagingBase . '/update_*', GLOB_ONLYDIR);
        if (!empty($dirs)) {
            usort($dirs, function ($a, $b) {
                return filemtime($b) - filemtime($a);
            });
            $latestStaging = $dirs[0];

            $sourceDir = $latestStaging;
            $subDirs = glob($latestStaging . '/*', GLOB_ONLYDIR);
            if (count($subDirs) === 1 && file_exists($subDirs[0] . '/config.php')) {
                $sourceDir = $subDirs[0];
            }

            $filesToDeploy = [
                'index.php',
                'admin.php',
                'pages.php',
                'settings.php',
                'analytics.php',
                'update.php',
                'view.php',
                'api.php',
                'login.php',
                'logout.php',
                'setup.php',
                'config.php',
                '.htaccess',
                'includes/class-auth.php',
                'includes/class-db.php',
                'includes/class-datastore.php',
                'includes/class-extractor.php',
                'includes/class-updater.php',
                'assets/images/maintenance_illustration.jpg',
            ];

            foreach ($filesToDeploy as $relPath) {
                $src = $sourceDir . '/' . $relPath;
                $dst = $root . '/' . $relPath;
                if (file_exists($src)) {
                    $dstDir = dirname($dst);
                    if (!is_dir($dstDir)) {
                        @mkdir($dstDir, 0755, true);
                    }
                    @chmod($dst, 0644);
                    @copy($src, $dst);
                }
            }
        }
    }

    // Deduplicate settings table rows if any duplicate setting_key rows exist (keep latest id)
    try {
        $stmt = $pdo->query("SELECT id, setting_key FROM settings ORDER BY id DESC");
        if ($stmt) {
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $seen = [];
            $deleteIds = [];
            foreach ($rows as $row) {
                $k = $row['setting_key'] ?? '';
                $id = (int) ($row['id'] ?? 0);
                if ($k === '' || $id <= 0) continue;
                if (isset($seen[$k])) {
                    $deleteIds[] = $id;
                } else {
                    $seen[$k] = true;
                }
            }
            if (!empty($deleteIds)) {
                $delStmt = $pdo->prepare("DELETE FROM settings WHERE id = :id");
                foreach ($deleteIds as $delId) {
                    $delStmt->execute([':id' => $delId]);
                }
            }
        }
    } catch (Exception $e) {
        // Ignore if settings table structure differs
    }

    if (function_exists('opcache_reset')) {
        @opcache_reset();
    }
};
