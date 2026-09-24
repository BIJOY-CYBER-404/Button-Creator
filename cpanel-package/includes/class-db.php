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
                    PDO::ATTR_EMULATE_PREPARES   => false, // Native prepared statements preserve 4-byte UTF-8 emojis
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
                ];

                self::$pdo = new PDO($dsn, defined('DB_USER') ? DB_USER : '', defined('DB_PASS') ? DB_PASS : '', $options);
                // Enforce 4-byte UTF-8 collation on connection to prevent emoji conversion to ??
                self::$pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
                self::$pdo->exec("SET CHARACTER SET utf8mb4");
                self::$pdo->exec("SET character_set_client = utf8mb4, character_set_connection = utf8mb4, character_set_results = utf8mb4");
                self::$driver = 'mysql';

                // Automatically persist verified working MySQL credentials so they are never lost on updates
                self::persist_verified_credentials();

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
        $table_engine = $is_mysql ? 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci' : '';

        // For MySQL, upgrade existing tables to utf8mb4_unicode_ci so emojis are fully preserved
        if ($is_mysql) {
            try {
                self::$pdo->exec("ALTER TABLE settings CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                self::$pdo->exec("ALTER TABLE settings MODIFY setting_value LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            } catch (Exception $e) { /* ignore if not exists */ }

            try {
                self::$pdo->exec("ALTER TABLE pages CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                self::$pdo->exec("ALTER TABLE pages MODIFY buttons_json LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                self::$pdo->exec("ALTER TABLE pages MODIFY title VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                self::$pdo->exec("ALTER TABLE pages MODIFY description TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            } catch (Exception $e) { /* ignore if not exists */ }

            try {
                self::$pdo->exec("ALTER TABLE users CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            } catch (Exception $e) { /* ignore if not exists */ }
        }

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
            ) {$table_engine}
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
            ) {$table_engine}
        ");

        // 3. Settings Table
        self::$pdo->exec("
            CREATE TABLE IF NOT EXISTS settings (
                setting_key VARCHAR(100) PRIMARY KEY,
                setting_value {$text_type},
                updated_at DATETIME
            ) {$table_engine}
        ");

        // 4. Migrations History Table
        self::$pdo->exec("
            CREATE TABLE IF NOT EXISTS migrations (
                id {$auto_inc},
                migration_name VARCHAR(255) NOT NULL UNIQUE,
                batch INT DEFAULT 1,
                executed_at DATETIME
            ) {$table_engine}
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
            ) {$table_engine}
        ");

        // 6. Monthly Page Views Table
        $uq_constraint = $is_mysql ? 'UNIQUE KEY uq_page_month (page_slug, year_month)' : 'UNIQUE(page_slug, year_month)';
        self::$pdo->exec("
            CREATE TABLE IF NOT EXISTS page_views_monthly (
                id {$auto_inc},
                page_slug VARCHAR(120) NOT NULL,
                year_month VARCHAR(7) NOT NULL,
                views INT DEFAULT 1,
                {$uq_constraint}
            ) {$table_engine}
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

        // Seed initial footer copyright text if not set or if previous corruption had '??'
        $stmt_footer = self::$pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'footer_copyright'");
        $stmt_footer->execute();
        $row_footer = $stmt_footer->fetch();
        if (!$row_footer) {
            $default_footer = '&copy; ' . date('Y') . ' MovieHubHQ 🍿 • Made with ❤️ for Direct Episode Link Gateway 🎬 • All rights reserved 🚀';
            $insert_footer = self::$pdo->prepare("INSERT INTO settings (setting_key, setting_value, updated_at) VALUES ('footer_copyright', :val, :now)");
            $insert_footer->execute([
                ':val' => $default_footer,
                ':now' => date('Y-m-d H:i:s')
            ]);
        } elseif (isset($row_footer['setting_value']) && strpos($row_footer['setting_value'], '??') !== false) {
            // Repair previously corrupted '??' question marks from legacy 3-byte utf8
            $repaired = str_replace(
                ['?? • Made with ??', 'Gateway ?? • All rights reserved ??', 'MovieHubHQ ??'],
                ['🍿 • Made with ❤️', 'Gateway 🎬 • All rights reserved 🚀', 'MovieHubHQ 🍿'],
                $row_footer['setting_value']
            );
            if ($repaired !== $row_footer['setting_value']) {
                $repair_stmt = self::$pdo->prepare("UPDATE settings SET setting_value = :val, updated_at = :now WHERE setting_key = 'footer_copyright'");
                $repair_stmt->execute([':val' => $repaired, ':now' => date('Y-m-d H:i:s')]);
            }
        }
    }

    public static function persist_verified_credentials() {
        try {
            $data_dir = defined('DATA_DIR') ? DATA_DIR : (APP_ROOT . '/data');
            if (!is_dir($data_dir)) {
                @mkdir($data_dir, 0755, true);
            }
            $creds_file = $data_dir . '/db_credentials.json';
            $creds = [
                'db_type'    => defined('DB_TYPE') ? DB_TYPE : 'mysql',
                'db_host'    => defined('DB_HOST') ? DB_HOST : 'localhost',
                'db_port'    => defined('DB_PORT') ? DB_PORT : '3306',
                'db_name'    => defined('DB_NAME') ? DB_NAME : '',
                'db_user'    => defined('DB_USER') ? DB_USER : '',
                'db_pass'    => defined('DB_PASS') ? DB_PASS : '',
                'db_charset' => defined('DB_CHARSET') ? DB_CHARSET : 'utf8mb4',
                'saved_at'   => date('Y-m-d H:i:s')
            ];
            // Only write if valid database name and credentials configured
            if (!empty($creds['db_name']) && !empty($creds['db_user']) && $creds['db_pass'] !== 'SecretDB_Pass2026!') {
                @file_put_contents($creds_file, json_encode($creds, JSON_PRETTY_PRINT));
                // Also write config.local.php if not exists
                $local_cfg = defined('APP_ROOT') ? (APP_ROOT . '/config.local.php') : '';
                if (!empty($local_cfg) && !file_exists($local_cfg)) {
                    $php_content = "<?php\n" .
                        "// Persistent Database Credentials (Auto-generated to prevent data loss on updates)\n" .
                        "define('DB_TYPE', " . var_export($creds['db_type'], true) . ");\n" .
                        "define('DB_HOST', " . var_export($creds['db_host'], true) . ");\n" .
                        "define('DB_PORT', " . var_export($creds['db_port'], true) . ");\n" .
                        "define('DB_NAME', " . var_export($creds['db_name'], true) . ");\n" .
                        "define('DB_USER', " . var_export($creds['db_user'], true) . ");\n" .
                        "define('DB_PASS', " . var_export($creds['db_pass'], true) . ");\n" .
                        "define('DB_CHARSET', " . var_export($creds['db_charset'], true) . ");\n";
                    @file_put_contents($local_cfg, $php_content);
                }
            }
        } catch (Exception $e) {
            // Non-blocking
        }
    }
}
