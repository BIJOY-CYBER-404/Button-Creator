<?php
/**
 * Configuration file for cPanel Episode Button Page Generator
 * Place this in public_html or your deployment directory.
 */

if (!defined('APP_ROOT')) {
    define('APP_ROOT', __DIR__);
}

// -------------------------------------------------------------
// MySQL Database Configuration (Required for cPanel shared hosting)
// -------------------------------------------------------------
define('DB_TYPE', 'mysql'); // 'mysql' (recommended for cPanel) or 'sqlite' / 'json' fallback
define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_NAME', 'moviehub_buttons'); // Enter your cPanel MySQL Database Name
define('DB_USER', 'moviehub_user');     // Enter your cPanel MySQL Database Username
define('DB_PASS', 'SecretDB_Pass2026!'); // Enter your cPanel MySQL Database Password
define('DB_CHARSET', 'utf8mb4');

// Fallback SQLite / JSON data directory if MySQL connection fails or during testing
define('DATA_DIR', APP_ROOT . '/data');
define('SQLITE_STORAGE_FILE', DATA_DIR . '/database.sqlite');
define('JSON_STORAGE_FILE', DATA_DIR . '/pages.json');

// -------------------------------------------------------------
// Application & Security Configuration
// -------------------------------------------------------------
define('APP_NAME', 'Movie Hub HQ Drive');
define('APP_VERSION', 'v-4.3.0');
define('DEFAULT_PAGE_THEME', 'indigo');
define('AUTO_INCREMENT_VIEWS', true);
define('ROBOTS_NOINDEX', true); // Enforce noindex, nofollow on all button pages

// Session Security Configuration
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        ini_set('session.cookie_secure', 1);
    }
}

// Enforce UTF-8 default charset and internal multibyte encoding
ini_set('default_charset', 'UTF-8');
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}
if (function_exists('mb_http_output')) {
    mb_http_output('UTF-8');
}

