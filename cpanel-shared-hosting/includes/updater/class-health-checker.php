<?php
/**
 * Health Checker
 * Performs post-installation integrity and health verification.
 */

class SLEA_HealthChecker {
    private $logger;

    public function __construct(SLEA_UpdateLogger $logger) {
        $this->logger = $logger;
    }

    public function verify_health() {
        $this->logger->log("Running health checks", "Running post-update health and integrity verification...");

        // 1. Verify critical application files exist
        $required_files = [
            APP_ROOT . '/index.php',
            APP_ROOT . '/config.php',
            APP_ROOT . '/api.php',
            APP_ROOT . '/view.php',
            APP_ROOT . '/admin.php',
            APP_ROOT . '/includes/class-db.php',
            APP_ROOT . '/includes/class-datastore.php',
            APP_ROOT . '/includes/class-auth.php'
        ];

        $missing_files = [];
        foreach ($required_files as $f) {
            if (!file_exists($f)) {
                $missing_files[] = basename($f);
            }
        }

        if (!empty($missing_files)) {
            throw new Exception("Health Check Failed: Missing critical core application files after deployment: " . implode(', ', $missing_files));
        }

        // 2. Verify Database Connection and Tables
        try {
            $pdo = SLEA_DB::get_connection();
            $tables = ['users', 'pages', 'settings', 'migrations'];
            foreach ($tables as $tbl) {
                $stmt = $pdo->query("SELECT 1 FROM {$tbl} LIMIT 1");
                $stmt->fetch();
            }
        } catch (Exception $e) {
            throw new Exception("Health Check Failed: Database connectivity/schema verification error: " . $e->getMessage());
        }

        // 3. Verify Configuration load
        if (!defined('APP_NAME') || !defined('APP_VERSION')) {
            throw new Exception("Health Check Failed: Application constants (APP_NAME, APP_VERSION) could not be verified.");
        }

        $this->logger->log("Running health checks", "Post-update health check PASSED. Application is fully operational.");
        return true;
    }
}
