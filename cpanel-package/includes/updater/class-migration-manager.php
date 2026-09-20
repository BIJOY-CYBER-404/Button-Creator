<?php
/**
 * Migration Manager
 * Runs versioned database migrations once, maintaining execution history.
 */

class SLEA_MigrationManager {
    private $logger;

    public function __construct(SLEA_UpdateLogger $logger) {
        $this->logger = $logger;
    }

    public function run_migrations($staging_dir = '') {
        $this->logger->log("Running database migrations", "Checking for pending database migrations...");

        $pdo = SLEA_DB::get_connection();
        
        // Ensure migrations table exists
        $driver = SLEA_DB::get_driver();
        $auto_inc = ($driver === 'mysql') ? 'INT AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
        
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS migrations (
                id {$auto_inc},
                migration_name VARCHAR(255) NOT NULL UNIQUE,
                batch INT DEFAULT 1,
                executed_at DATETIME
            )
        ");

        // Fetch executed migrations
        $stmt = $pdo->query("SELECT migration_name FROM migrations");
        $executed_rows = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $executed_map = array_flip($executed_rows ?: []);

        // Get current max batch
        $batch_stmt = $pdo->query("SELECT MAX(batch) as max_batch FROM migrations");
        $max_batch_row = $batch_stmt->fetch();
        $current_batch = ($max_batch_row && isset($max_batch_row['max_batch'])) ? intval($max_batch_row['max_batch']) + 1 : 1;

        // Collect migration files from both current project and staging directory
        $migration_dirs = [
            APP_ROOT . '/database/migrations'
        ];
        if (!empty($staging_dir) && is_dir($staging_dir . '/database/migrations')) {
            $migration_dirs[] = $staging_dir . '/database/migrations';
        }

        $migration_files = [];
        foreach ($migration_dirs as $mdir) {
            if (is_dir($mdir)) {
                $files = glob($mdir . '/*.{sql,php}', GLOB_BRACE);
                foreach ($files as $f) {
                    $mname = basename($f);
                    $migration_files[$mname] = $f;
                }
            }
        }

        // Sort migrations by filename order (e.g. 2026_09_20_001_...)
        ksort($migration_files);

        $executed_count = 0;

        foreach ($migration_files as $mname => $mpath) {
            if (isset($executed_map[$mname])) {
                continue; // Already executed
            }

            $this->logger->log("Running database migrations", "Executing migration: " . $mname);

            try {
                $ext = pathinfo($mpath, PATHINFO_EXTENSION);
                if ($ext === 'sql') {
                    $sql_content = file_get_contents($mpath);
                    if (!empty(trim($sql_content))) {
                        $statements = array_filter(array_map('trim', explode(';', $sql_content)));
                        foreach ($statements as $query) {
                            if (!empty($query)) {
                                $pdo->exec($query);
                            }
                        }
                    }
                } elseif ($ext === 'php') {
                    require_once $mpath;
                }

                // Record migration execution
                $ins = $pdo->prepare("INSERT INTO migrations (migration_name, batch, executed_at) VALUES (:mname, :batch, :now)");
                $ins->execute([
                    ':mname' => $mname,
                    ':batch' => $current_batch,
                    ':now'   => date('Y-m-d H:i:s')
                ]);

                $executed_count++;
                $this->logger->log("Running database migrations", "[OK] Migration completed: " . $mname);
            } catch (Exception $e) {
                $err_msg = "Database migration " . $mname . " failed: " . $e->getMessage();
                $this->logger->log("Running database migrations", "[FAILED] " . $err_msg, "error");
                throw new Exception($err_msg);
            }
        }

        if ($executed_count === 0) {
            $this->logger->log("Running database migrations", "Database schema is already up to date. No new migrations executed.");
        } else {
            $this->logger->log("Running database migrations", "Successfully executed " . $executed_count . " new database migration(s).");
        }

        return true;
    }
}
