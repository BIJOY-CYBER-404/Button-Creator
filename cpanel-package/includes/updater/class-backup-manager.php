<?php
/**
 * Backup Manager
 * Creates full file and database backups before file deployment.
 */

class SLEA_BackupManager {
    private $logger;
    private $update_id;
    private $backup_dir;

    public function __construct(SLEA_UpdateLogger $logger, $update_id) {
        $this->logger = $logger;
        $this->update_id = $update_id;
        $this->backup_dir = APP_ROOT . '/backups/' . $update_id;
    }

    public function create_backup() {
        $this->logger->log("Creating backup", "Creating full application file and database backup...");

        if (!is_dir($this->backup_dir)) {
            @mkdir($this->backup_dir, 0755, true);
        }

        $files_backup_dir = $this->backup_dir . '/files';
        @mkdir($files_backup_dir, 0755, true);

        // 1. Back up files
        $this->copy_directory(APP_ROOT, $files_backup_dir, [
            APP_ROOT . '/backups',
            APP_ROOT . '/temp',
            APP_ROOT . '/.git',
            APP_ROOT . '/node_modules'
        ]);

        // 2. Back up Database
        $db_backup_file = $this->backup_dir . '/db_backup.sql';
        $this->dump_database($db_backup_file);

        $this->logger->log("Creating backup", "Backup completed successfully at " . str_replace(APP_ROOT, '', $this->backup_dir));
        return $this->backup_dir;
    }

    public function get_backup_dir() {
        return $this->backup_dir;
    }

    public function enforce_retention_limit($limit = 3) {
        $backups_parent = APP_ROOT . '/backups';
        if (!is_dir($backups_parent)) {
            return;
        }

        $dirs = glob($backups_parent . '/update_*', GLOB_ONLYDIR);
        if (count($dirs) > $limit) {
            // Sort by creation time oldest first
            usort($dirs, function($a, $b) {
                return filemtime($a) - filemtime($b);
            });

            $to_remove = array_slice($dirs, 0, count($dirs) - $limit);
            foreach ($to_remove as $dir) {
                $this->delete_directory($dir);
            }
        }
    }

    private function copy_directory($src, $dst, $exclude_paths = []) {
        $dir = opendir($src);
        if (!$dir) return;

        @mkdir($dst, 0755, true);

        while (($file = readdir($dir)) !== false) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $src_path = $src . '/' . $file;
            $dst_path = $dst . '/' . $file;

            $skip = false;
            foreach ($exclude_paths as $ex) {
                if (strpos($src_path, $ex) === 0) {
                    $skip = true;
                    break;
                }
            }
            if ($skip) continue;

            if (is_dir($src_path)) {
                $this->copy_directory($src_path, $dst_path, $exclude_paths);
            } else {
                @copy($src_path, $dst_path);
            }
        }
        closedir($dir);
    }

    private function dump_database($target_file) {
        $pdo = SLEA_DB::get_connection();
        $driver = SLEA_DB::get_driver();

        $sql_dump = "-- Movie Hub HQ Database Backup\n";
        $sql_dump .= "-- Date: " . date('Y-m-d H:i:s') . "\n";
        $sql_dump .= "-- Driver: " . $driver . "\n\n";

        $tables = ['users', 'pages', 'settings', 'migrations', 'update_history'];

        foreach ($tables as $table) {
            try {
                $stmt = $pdo->query("SELECT * FROM {$table}");
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

                $sql_dump .= "-- Table: {$table}\n";
                $sql_dump .= "DELETE FROM {$table};\n";

                if (!empty($rows)) {
                    foreach ($rows as $row) {
                        $keys = array_keys($row);
                        $escaped_vals = array_map(function($val) use ($pdo) {
                            if ($val === null) return 'NULL';
                            return $pdo->quote($val);
                        }, array_values($row));

                        $sql_dump .= sprintf(
                            "INSERT INTO %s (%s) VALUES (%s);\n",
                            $table,
                            implode(', ', $keys),
                            implode(', ', $escaped_vals)
                        );
                    }
                }
                $sql_dump .= "\n";
            } catch (Exception $e) {
                // Table might not exist yet, skip
            }
        }

        file_put_contents($target_file, $sql_dump);
    }

    public function delete_backup($dir = null) {
        $target = $dir ?: $this->backup_dir;
        if (is_dir($target)) {
            $this->delete_directory($target);
            $this->logger->log("Backup Cleanup", "[OK] Pre-update backup removed: " . str_replace(APP_ROOT, '', $target));
            return true;
        }
        return false;
    }

    public function delete_directory($dir) {
        if (!is_dir($dir)) return;
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->delete_directory($path) : @unlink($path);
        }
        @rmdir($dir);
    }
}
