<?php
/**
 * Database abstraction layer supporting MySQL (and graceful SQLite fallback)
 */

class SLEA_DB {
    private static $pdo = null;
    private static $driver = null;

    public static function get_connection() {
        if (self::$pdo !== null) {
            return self::$pdo;
        }

        if (!defined('APP_ROOT')) {
            define('APP_ROOT', dirname(__DIR__));
        }
        if (!defined('DATA_DIR')) {
            define('DATA_DIR', APP_ROOT . '/data');
        }

        // Try MySQL first if configured
        if (defined('DB_TYPE') && DB_TYPE === 'mysql') {
            try {
                $dsn = sprintf(
                    'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                    defined('DB_HOST') ? DB_HOST : 'localhost',
                    defined('DB_PORT') ? DB_PORT : '3306',
                    defined('DB_NAME') ? DB_NAME : 'moviehub_buttons',
                    defined('DB_CHARSET') ? DB_CHARSET : 'utf8mb4'
                );

                $options = [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => true,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
                ];

                self::$pdo = new PDO($dsn, defined('DB_USER') ? DB_USER : '', defined('DB_PASS') ? DB_PASS : '', $options);
                self::$driver = 'mysql';
                self::ensure_schema();
                return self::$pdo;
            } catch (PDOException $e) {
                // If MySQL connection fails, fall back to SQLite so system remains resilient
                error_log('MySQL connection failed: ' . $e->getMessage() . '. Falling back to SQLite.');
            }
        }

        // SQLite fallback
        try {
            if (!is_dir(DATA_DIR)) {
                @mkdir(DATA_DIR, 0755, true);
            }
            $sqlite_file = defined('SQLITE_STORAGE_FILE') ? SQLITE_STORAGE_FILE : DATA_DIR . '/database.sqlite';
            self::$pdo = new PDO('sqlite:' . $sqlite_file);
            self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            self::$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            self::$driver = 'sqlite';
            self::ensure_schema();
            return self::$pdo;
        } catch (PDOException $ex) {
            die('Fatal Database Error: ' . htmlspecialchars($ex->getMessage()));
        }
    }

    public static function get_driver() {
        if (self::$driver === null) {
            self::get_connection();
        }
        return self::$driver;
    }

    private static function ensure_schema() {
        if (!self::$pdo) return;

        $is_mysql = (self::$driver === 'mysql');
        $auto_inc = $is_mysql ? 'INT AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
        $text_type = $is_mysql ? 'LONGTEXT' : 'TEXT';

        // 1. Users Table
        self::$pdo->exec("
            CREATE TABLE IF NOT EXISTS users (
                id {$auto_inc},
                username VARCHAR(60) NOT NULL UNIQUE,
                email VARCHAR(120) NOT NULL,
                password_hash VARCHAR(255) NOT NULL,
                role VARCHAR(30) DEFAULT 'admin',
                permissions TEXT,
                created_at DATETIME,
                updated_at DATETIME
            )
        ");

        // 2. Button Pages Table
        self::$pdo->exec("
            CREATE TABLE IF NOT EXISTS pages (
                id {$auto_inc},
                page_key VARCHAR(64) UNIQUE,
                slug VARCHAR(120) NOT NULL UNIQUE,
                title VARCHAR(255) NOT NULL,
                description TEXT,
                source_url TEXT,
                resolved_url TEXT,
                theme VARCHAR(30) DEFAULT 'indigo',
                buttons_json {$text_type},
                views INT DEFAULT 0,
                is_public INT DEFAULT 1,
                created_at DATETIME,
                updated_at DATETIME
            )
        ");

        // 3. Settings Table
        self::$pdo->exec("
            CREATE TABLE IF NOT EXISTS settings (
                setting_key VARCHAR(100) PRIMARY KEY,
                setting_value {$text_type},
                updated_at DATETIME
            )
        ");

        // 4. Migrations History Table
        self::$pdo->exec("
            CREATE TABLE IF NOT EXISTS migrations (
                id {$auto_inc},
                migration_name VARCHAR(255) NOT NULL UNIQUE,
                batch INT DEFAULT 1,
                executed_at DATETIME
            )
        ");

        // 5. Update History Table
        self::$pdo->exec("
            CREATE TABLE IF NOT EXISTS update_history (
                id {$auto_inc},
                update_id VARCHAR(64) NOT NULL UNIQUE,
                old_version VARCHAR(30) NOT NULL,
                new_version VARCHAR(30) NOT NULL,
                status VARCHAR(30) NOT NULL,
                step VARCHAR(100) DEFAULT '',
                error_message {$text_type},
                rollback_status VARCHAR(30) DEFAULT '',
                migration_status VARCHAR(30) DEFAULT '',
                started_at DATETIME,
                completed_at DATETIME,
                details_json {$text_type}
            )
        ");

        // Seed initial default menu items if not set
        $stmt = self::$pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'menu_items'");
        $stmt->execute();
        if (!$stmt->fetch()) {
            $default_menu = [
                [
                    'id'      => 'm1',
                    'title'   => 'Home',
                    'url'     => 'https://moviehubhq.com/',
                    'new_tab' => false
                ],
                [
                    'id'      => 'm2',
                    'title'   => 'Korean Drama',
                    'url'     => 'https://moviehubhq.com/catagory/korean/',
                    'new_tab' => false
                ],
                [
                    'id'      => 'm3',
                    'title'   => 'Chinese Drama',
                    'url'     => 'https://moviehubhq.com/catagory/chinese/',
                    'new_tab' => false
                ]
            ];
            $insert_menu = self::$pdo->prepare("INSERT INTO settings (setting_key, setting_value, updated_at) VALUES ('menu_items', :val, :now)");
            $insert_menu->execute([
                ':val' => json_encode($default_menu, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT),
                ':now' => date('Y-m-d H:i:s')
            ]);
        }

        // Seed initial footer copyright text if not set
        $stmt_footer = self::$pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'footer_copyright'");
        $stmt_footer->execute();
        if (!$stmt_footer->fetch()) {
            $default_footer = '&copy; ' . date('Y') . ' MovieHubHQ 🍿 • Made with ❤️ for Direct Episode Link Gateway 🎬 • All rights reserved 🚀';
            $insert_footer = self::$pdo->prepare("INSERT INTO settings (setting_key, setting_value, updated_at) VALUES ('footer_copyright', :val, :now)");
            $insert_footer->execute([
                ':val' => $default_footer,
                ':now' => date('Y-m-d H:i:s')
            ]);
        }
    }
}
