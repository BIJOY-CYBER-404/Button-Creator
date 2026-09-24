<?php
/**
 * Configuration file for cPanel Episode Button Page Generator
 * Place this in public_html or your deployment directory.
 */

if (!defined('APP_ROOT')) {
    define('APP_ROOT', __DIR__);
}

// -------------------------------------------------------------
// Data & Storage Directories
// -------------------------------------------------------------
if (!defined('DATA_DIR')) {
    define('DATA_DIR', APP_ROOT . '/data');
}
if (!defined('SQLITE_STORAGE_FILE')) {
    define('SQLITE_STORAGE_FILE', DATA_DIR . '/database.sqlite');
}
if (!defined('JSON_STORAGE_FILE')) {
    define('JSON_STORAGE_FILE', DATA_DIR . '/pages.json');
}

// -------------------------------------------------------------
// Persistent Configuration Loader
// Protects custom database credentials from being lost during remote updates or manual ZIP extractions
// -------------------------------------------------------------
if (file_exists(APP_ROOT . '/config.local.php')) {
    require_once APP_ROOT . '/config.local.php';
}

$cached_creds = [];
$credentials_cache_file = DATA_DIR . '/db_credentials.json';
if (file_exists($credentials_cache_file)) {
    $cached_creds = json_decode(@file_get_contents($credentials_cache_file), true) ?: [];
}

// -------------------------------------------------------------
// MySQL Database Configuration (Required for cPanel shared hosting)
// -------------------------------------------------------------
if (!defined('DB_TYPE')) {
    define('DB_TYPE', !empty($cached_creds['db_type']) ? $cached_creds['db_type'] : 'mysql');
}
if (!defined('DB_HOST')) {
    define('DB_HOST', !empty($cached_creds['db_host']) ? $cached_creds['db_host'] : 'localhost');
}
if (!defined('DB_PORT')) {
    define('DB_PORT', !empty($cached_creds['db_port']) ? $cached_creds['db_port'] : '3306');
}
if (!defined('DB_NAME')) {
    define('DB_NAME', !empty($cached_creds['db_name']) ? $cached_creds['db_name'] : 'moviehub_buttons');
}
if (!defined('DB_USER')) {
    define('DB_USER', !empty($cached_creds['db_user']) ? $cached_creds['db_user'] : 'moviehub_user');
}
if (!defined('DB_PASS')) {
    define('DB_PASS', isset($cached_creds['db_pass']) ? $cached_creds['db_pass'] : 'SecretDB_Pass2026!');
}
if (!defined('DB_CHARSET')) {
    define('DB_CHARSET', !empty($cached_creds['db_charset']) ? $cached_creds['db_charset'] : 'utf8mb4');
}

// -------------------------------------------------------------
// Application & Security Configuration
// -------------------------------------------------------------
define('APP_NAME', 'Movie Hub HQ Drive');
define('APP_VERSION', 'v-5.4.0');
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

