<?php
/**
 * Migration 018: Security Hardening & Directory Access Protection (v22.0)
 *
 * Ensures all private directories (data, backups, temp, includes, database) have
 * strict .htaccess and index.php guards, creates setup.lock if admin accounts exist,
 * and force-deploys all hardened v22.0 application files from staging.
 */

if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__)) {
    http_response_code(403);
    exit('Forbidden');
}

return function($pdo) {
    $app_root = defined('APP_ROOT') ? APP_ROOT : dirname(__DIR__, 2);

    // 1. Protect all internal directories against direct HTTP access or directory indexing
    $protected_dirs = [
        $app_root . '/data',
        $app_root . '/backups',
        $app_root . '/temp',
        $app_root . '/includes',
        $app_root . '/includes/updater',
        $app_root . '/database',
        $app_root . '/database/migrations'
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

    // 2. Ensure setup.lock exists if any user account is present
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM users");
        if ($stmt && (int)$stmt->fetchColumn() > 0) {
            @file_put_contents($app_root . '/data/setup.lock', date('Y-m-d H:i:s'), LOCK_EX);
        }
    } catch (Exception $e) {
        // ignore
    }

    // 3. Force-deploy updated files from staging directory if running inside updater
    $staging_dir = dirname(__DIR__, 2);
    if (realpath($staging_dir) !== realpath($app_root) && file_exists($staging_dir . '/includes/class-auth.php')) {
        $critical_files = [
            'index.php',
            'login.php',
            'logout.php',
            'setup.php',
            'admin.php',
            'pages.php',
            'settings.php',
            'update.php',
            'analytics.php',
            'view.php',
            'api.php',
            '.htaccess',
            'includes/class-auth.php',
            'includes/class-datastore.php',
            'includes/class-db.php',
            'includes/class-extractor.php',
            'includes/class-resolver.php',
            'includes/class-updater.php',
            'includes/updater/class-downloader.php'
        ];
        foreach ($critical_files as $rel) {
            $src = $staging_dir . '/' . $rel;
            $dst = $app_root . '/' . $rel;
            if (file_exists($src)) {
                @chmod($dst, 0644);
                $bytes = @file_get_contents($src);
                if ($bytes !== false) {
                    @file_put_contents($dst, $bytes, LOCK_EX);
                }
                if (function_exists('opcache_invalidate')) {
                    @opcache_invalidate($dst, true);
                }
            }
        }
    }
};
