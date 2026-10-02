<?php
/**
 * Database abstraction layer supporting MySQL (and graceful SQLite fallback)
 */

if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__)) {
    http_response_code(403);
    exit('Forbidden');
}

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
            self::protect_data_directory(DATA_DIR);
            $sqlite_file = defined('SQLITE_STORAGE_FILE') ? SQLITE_STORAGE_FILE : DATA_DIR . '/database.sqlite';
            self::$pdo = new PDO('sqlite:' . $sqlite_file);
            self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            self::$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            self::$driver = 'sqlite';
            self::ensure_schema();
            return self::$pdo;
        } catch (PDOException $ex) {
            error_log('Database connection error: ' . $ex->getMessage());
            if (class_exists('SLEA_Datastore') && method_exists('SLEA_Datastore', 'render_public_error')) {
                SLEA_Datastore::render_public_error(
                    '503 - Database Connection Error',
                    'Actual Error [Database PDOException]: ' . $ex->getMessage(),
                    503,
                    'Service temporarily unavailable. Please try again in a moment.'
                );
            }
            http_response_code(503);
            die('Service temporarily unavailable.');
        }
    }

    public static function protect_data_directory($dir) {
        if (!is_dir($dir)) return;
        $ht = $dir . '/.htaccess';
        if (!file_exists($ht)) {
            @file_put_contents($ht, "Order deny,allow\nDeny from all\n<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n");
        }
        $idx = $dir . '/index.php';
        if (!file_exists($idx)) {
            @file_put_contents($idx, "<?php http_response_code(404); exit;\n");
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

        // 7. Real Visitor Telemetry Table (Device Breakdown, Traffic Channels, Country, Sessions, Duration)
        self::$pdo->exec("
            CREATE TABLE IF NOT EXISTS analytics_visits (
                id {$auto_inc},
                session_id VARCHAR(64) DEFAULT '',
                page_slug VARCHAR(120) DEFAULT '',
                device_type VARCHAR(30) DEFAULT 'desktop',
                channel VARCHAR(40) DEFAULT 'direct',
                traffic_channel VARCHAR(40) DEFAULT 'direct',
                referrer_host VARCHAR(190) DEFAULT '',
                country VARCHAR(100) DEFAULT 'Unknown',
                country_name VARCHAR(100) DEFAULT 'Unknown',
                country_code VARCHAR(10) DEFAULT 'UN',
                ip_hash VARCHAR(64) DEFAULT '',
                duration_sec INT DEFAULT 0,
                visit_date VARCHAR(10) DEFAULT '',
                year_month VARCHAR(7) DEFAULT '',
                visited_at DATETIME,
                created_at DATETIME,
                updated_at DATETIME
            ) {$table_engine}
        ");

        // Ensure all telemetry columns exist on existing analytics_visits tables across upgrades
        $visit_alter_cols = [
            "session_id VARCHAR(64) DEFAULT ''",
            "page_slug VARCHAR(120) DEFAULT ''",
            "device_type VARCHAR(30) DEFAULT 'desktop'",
            "channel VARCHAR(40) DEFAULT 'direct'",
            "traffic_channel VARCHAR(40) DEFAULT 'direct'",
            "referrer_host VARCHAR(190) DEFAULT ''",
            "country VARCHAR(100) DEFAULT 'Unknown'",
            "country_name VARCHAR(100) DEFAULT 'Unknown'",
            "country_code VARCHAR(10) DEFAULT 'UN'",
            "ip_hash VARCHAR(64) DEFAULT ''",
            "duration_sec INT DEFAULT 0",
            "visit_date VARCHAR(10) DEFAULT ''",
            "year_month VARCHAR(7) DEFAULT ''",
            "visited_at DATETIME",
            "created_at DATETIME",
            "updated_at DATETIME"
        ];
        foreach ($visit_alter_cols as $col_def) {
            try {
                self::$pdo->exec("ALTER TABLE analytics_visits ADD COLUMN {$col_def}");
            } catch (Exception $e) {
                // Column already exists
            }
        }

        // Self-Heal Settings, Users, Pages, and Analytics from persistent JSON backups in /data before seeding defaults
        self::auto_heal_from_data_backups();

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

    private static function auto_heal_from_data_backups() {
        if (!self::$pdo) return;
        $data_dir = defined('DATA_DIR') ? DATA_DIR : (APP_ROOT . '/data');
        if (!is_dir($data_dir)) return;

        $now = date('Y-m-d H:i:s');

        // 1. Auto-heal settings from data/settings_backup.json
        $settings_file = $data_dir . '/settings_backup.json';
        if (file_exists($settings_file)) {
            try {
                $saved_settings = json_decode(@file_get_contents($settings_file), true);
                if (is_array($saved_settings) && !empty($saved_settings)) {
                    foreach ($saved_settings as $s_key => $s_val) {
                        if (empty($s_key)) continue;
                        $chk = self::$pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = :k LIMIT 1");
                        $chk->execute([':k' => $s_key]);
                        if (!$chk->fetch()) {
                            $json_val = is_string($s_val) ? $s_val : json_encode($s_val, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                            $ins = self::$pdo->prepare("INSERT INTO settings (setting_key, setting_value, updated_at) VALUES (:k, :v, :u)");
                            $ins->execute([':k' => $s_key, ':v' => $json_val, ':u' => $now]);
                        }
                    }
                }
            } catch (Exception $e) { /* non-blocking */ }
        }

        // 2. Auto-heal users from data/users_backup.json if users table is empty
        $users_file = $data_dir . '/users_backup.json';
        if (file_exists($users_file)) {
            try {
                $cnt_stmt = self::$pdo->query("SELECT COUNT(*) AS cnt FROM users");
                $cnt_row = $cnt_stmt ? $cnt_stmt->fetch() : null;
                if (empty($cnt_row) || intval($cnt_row['cnt']) === 0) {
                    $saved_users = json_decode(@file_get_contents($users_file), true);
                    if (is_array($saved_users) && !empty($saved_users)) {
                        foreach ($saved_users as $u) {
                            if (empty($u['username']) || empty($u['password_hash'])) continue;
                            if (!preg_match('/^\$2[ayb]\$/', (string)$u['password_hash'])) continue;
                            $ins = self::$pdo->prepare("
                                INSERT INTO users (username, email, password_hash, role, permissions, created_at, updated_at)
                                VALUES (:u, :e, :p, :r, :perms, :c, :up)
                            ");
                            $ins->execute([
                                ':u'     => $u['username'],
                                ':e'     => $u['email'] ?? ($u['username'] . '@localhost'),
                                ':p'     => $u['password_hash'],
                                ':r'     => $u['role'] ?? 'superadmin',
                                ':perms' => is_string($u['permissions'] ?? null) ? $u['permissions'] : json_encode($u['permissions'] ?? ['all']),
                                ':c'     => $u['created_at'] ?? $now,
                                ':up'    => $u['updated_at'] ?? $now
                            ]);
                        }
                    }
                }
            } catch (Exception $e) { /* non-blocking */ }
        }

        // 3. Auto-heal analytics from data/analytics_backup.json if page_views_monthly is empty
        $analytics_file = $data_dir . '/analytics_backup.json';
        if (file_exists($analytics_file)) {
            try {
                $cnt_stmt = self::$pdo->query("SELECT COUNT(*) AS cnt FROM page_views_monthly");
                $cnt_row = $cnt_stmt ? $cnt_stmt->fetch() : null;
                if (empty($cnt_row) || intval($cnt_row['cnt']) === 0) {
                    $saved_analytics = json_decode(@file_get_contents($analytics_file), true);
                    if (is_array($saved_analytics) && !empty($saved_analytics)) {
                        foreach ($saved_analytics as $a) {
                            if (empty($a['page_slug']) || empty($a['year_month'])) continue;
                            $ins = self::$pdo->prepare("
                                INSERT INTO page_views_monthly (page_slug, year_month, views)
                                VALUES (:s, :ym, :v)
                            ");
                            $ins->execute([
                                ':s'  => $a['page_slug'],
                                ':ym' => $a['year_month'],
                                ':v'  => intval($a['views'] ?? 1)
                            ]);
                        }
                    }
                }
            } catch (Exception $e) { /* non-blocking */ }
        }

        // 4. Auto-heal visitor telemetry from data/analytics_visits_backup.json if analytics_visits is empty
        $visits_file = $data_dir . '/analytics_visits_backup.json';
        if (file_exists($visits_file)) {
            try {
                $cnt_stmt = self::$pdo->query("SELECT COUNT(*) AS cnt FROM analytics_visits");
                $cnt_row = $cnt_stmt ? $cnt_stmt->fetch() : null;
                if (empty($cnt_row) || intval($cnt_row['cnt']) === 0) {
                    $saved_visits = json_decode(@file_get_contents($visits_file), true);
                    if (is_array($saved_visits) && !empty($saved_visits)) {
                        foreach ($saved_visits as $v) {
                            if (empty($v['session_id']) || empty($v['device_type'])) continue;
                            $ins = self::$pdo->prepare("
                                INSERT INTO analytics_visits (session_id, page_slug, device_type, channel, referrer_host, country, country_code, duration_sec, visit_date, year_month, created_at, updated_at)
                                VALUES (:sid, :slug, :dev, :ch, :ref, :cnt, :cc, :dur, :vd, :ym, :ca, :ua)
                            ");
                            $ins->execute([
                                ':sid'  => $v['session_id'],
                                ':slug' => $v['page_slug'] ?? '',
                                ':dev'  => $v['device_type'],
                                ':ch'   => $v['channel'] ?? 'direct',
                                ':ref'  => $v['referrer_host'] ?? '',
                                ':cnt'  => $v['country'] ?? 'Local Network',
                                ':cc'   => $v['country_code'] ?? '',
                                ':dur'  => intval($v['duration_sec'] ?? 0),
                                ':vd'   => $v['visit_date'] ?? date('Y-m-d'),
                                ':ym'   => $v['year_month'] ?? date('Y-m'),
                                ':ca'   => $v['created_at'] ?? $now,
                                ':ua'   => $v['updated_at'] ?? $now
                            ]);
                        }
                    }
                }
            } catch (Exception $e) { /* non-blocking */ }
        }
    }

    public static function persist_verified_credentials() {
        try {
            $data_dir = defined('DATA_DIR') ? DATA_DIR : (APP_ROOT . '/data');
            if (!is_dir($data_dir)) {
                @mkdir($data_dir, 0755, true);
            }
            self::protect_data_directory($data_dir);
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
