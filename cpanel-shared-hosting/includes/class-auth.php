<?php
/**
 * Admin Authentication & Session Security Manager (Hardened)
 * Enforces:
 * - Persistent IP + Session Brute-Force Rate Limiting
 * - Session User-Agent Fingerprinting, Idle Timeout & Live Account Verification
 * - Cryptographic CSRF Token Generation, Auto-Injection & Strict Verification
 * - Stealth 404 for Unauthenticated Access to Admin & Private Routes
 */

if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__)) {
    http_response_code(403);
    exit('Forbidden');
}

require_once __DIR__ . '/class-db.php';

class SLEA_Auth {
    private static $verified_user_this_request = null;
    private static $csrf_ob_registered = false;

    public static function init_session() {
        if (session_status() === PHP_SESSION_NONE) {
            $is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https')
                || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);

            @ini_set('session.use_only_cookies', '1');
            @ini_set('session.use_strict_mode', '1');
            @ini_set('session.cookie_httponly', '1');
            if ($is_https) {
                @ini_set('session.cookie_secure', '1');
            }

            if (PHP_VERSION_ID >= 70300) {
                session_set_cookie_params([
                    'lifetime' => 0,
                    'path'     => '/',
                    'domain'   => '',
                    'secure'   => $is_https,
                    'httponly' => true,
                    'samesite' => 'Lax'
                ]);
            } else {
                session_set_cookie_params(0, '/; samesite=Lax', '', $is_https, true);
            }

            session_name(defined('SESSION_NAME') ? SESSION_NAME : 'slea_admin_session');
            @session_start();
        }
    }

    /**
     * Get client IP address safely for rate limiting
     */
    public static function get_client_ip() {
        $remote = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $cf = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? '';
        if ($cf !== '' && filter_var($cf, FILTER_VALIDATE_IP)) {
            return $cf . '|' . $remote;
        }
        return $remote;
    }

    /**
     * Persistent file-backed + session-backed brute-force rate limiter
     */
    private static function get_rate_limit_file() {
        $dir = dirname(__DIR__) . '/data';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $ht = $dir . '/.htaccess';
        if (!file_exists($ht)) {
            @file_put_contents($ht, "Order deny,allow\nDeny from all\n<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n");
        }
        return $dir . '/rate_limits.json';
    }

    private static function load_rate_limits() {
        $file = self::get_rate_limit_file();
        if (!file_exists($file)) {
            return [];
        }
        $raw = @file_get_contents($file);
        $data = is_string($raw) ? json_decode($raw, true) : null;
        if (!is_array($data)) {
            return [];
        }
        $now = time();
        $cleaned = [];
        foreach ($data as $k => $entry) {
            if (is_array($entry) && ($now - (int)($entry['last_attempt'] ?? 0)) < 900) {
                $cleaned[$k] = $entry;
            }
        }
        return $cleaned;
    }

    private static function save_rate_limits($data) {
        $file = self::get_rate_limit_file();
        @file_put_contents($file, json_encode($data, JSON_UNESCAPED_SLASHES), LOCK_EX);
    }

    public static function is_rate_limited(&$remaining_seconds = 0) {
        self::init_session();
        $now = time();
        $lockout_window = 900; // 15 minutes
        $max_attempts = 5;

        // 1. Check Session Rate Limit
        $sess_attempts = (int)($_SESSION['slea_login_attempts'] ?? 0);
        $sess_last = (int)($_SESSION['slea_last_attempt_time'] ?? 0);
        if ($sess_attempts >= $max_attempts && ($now - $sess_last) < $lockout_window) {
            $remaining_seconds = max(1, $lockout_window - ($now - $sess_last));
            return true;
        }

        // 2. Check Persistent IP Rate Limit
        $ip_key = hash('sha256', self::get_client_ip());
        $limits = self::load_rate_limits();
        if (isset($limits[$ip_key])) {
            $ip_attempts = (int)($limits[$ip_key]['attempts'] ?? 0);
            $ip_last = (int)($limits[$ip_key]['last_attempt'] ?? 0);
            if ($ip_attempts >= $max_attempts && ($now - $ip_last) < $lockout_window) {
                $remaining_seconds = max(1, $lockout_window - ($now - $ip_last));
                return true;
            }
        }

        return false;
    }

    private static function record_failed_login_attempt() {
        self::init_session();
        $now = time();
        if (($now - (int)($_SESSION['slea_last_attempt_time'] ?? 0)) >= 900) {
            $_SESSION['slea_login_attempts'] = 0;
        }
        $_SESSION['slea_login_attempts'] = (int)($_SESSION['slea_login_attempts'] ?? 0) + 1;
        $_SESSION['slea_last_attempt_time'] = $now;

        $ip_key = hash('sha256', self::get_client_ip());
        $limits = self::load_rate_limits();
        $prev = isset($limits[$ip_key]) ? (int)($limits[$ip_key]['attempts'] ?? 0) : 0;
        $limits[$ip_key] = [
            'attempts'     => $prev + 1,
            'last_attempt' => $now
        ];
        self::save_rate_limits($limits);

        // Constant-time mitigation delay (250ms) against high-speed automated brute-forcing
        @usleep(250000);
    }

    private static function clear_failed_login_attempts() {
        self::init_session();
        $_SESSION['slea_login_attempts'] = 0;
        $_SESSION['slea_last_attempt_time'] = 0;

        $ip_key = hash('sha256', self::get_client_ip());
        $limits = self::load_rate_limits();
        if (isset($limits[$ip_key])) {
            unset($limits[$ip_key]);
            self::save_rate_limits($limits);
        }
    }

    /**
     * Check if any user exists in the system (for initial setup redirect & lock)
     */
    public static function has_users() {
        $lock_file = dirname(__DIR__) . '/data/setup.lock';
        try {
            $pdo = SLEA_DB::get_connection();
            $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM users");
            $row = $stmt->fetch();
            $count = $row ? (int)$row['cnt'] : 0;
            if ($count > 0) {
                if (!file_exists($lock_file)) {
                    @file_put_contents($lock_file, date('Y-m-d H:i:s'), LOCK_EX);
                }
                return true;
            }

            // If DB table is empty, check if users_backup.json has preserved admin accounts
            $backup_users_file = dirname(__DIR__) . '/data/users_backup.json';
            if (file_exists($backup_users_file)) {
                $raw = @file_get_contents($backup_users_file);
                $users = is_string($raw) ? json_decode($raw, true) : null;
                if (is_array($users) && count($users) > 0) {
                    // Auto-restore admin accounts into DB so setup.php can never be abused
                    foreach ($users as $u) {
                        if (!empty($u['username']) && !empty($u['password_hash'])) {
                            $ins = $pdo->prepare("INSERT INTO users (username, password_hash, role, created_at) VALUES (:u, :p, :r, :c)");
                            $ins->execute([
                                ':u' => $u['username'],
                                ':p' => $u['password_hash'],
                                ':r' => $u['role'] ?? 'admin',
                                ':c' => $u['created_at'] ?? date('Y-m-d H:i:s')
                            ]);
                        }
                    }
                    @file_put_contents($lock_file, date('Y-m-d H:i:s'), LOCK_EX);
                    return true;
                }
            }
            return false;
        } catch (Exception $e) {
            return file_exists($lock_file);
        }
    }

    /**
     * Authenticate user with username & password
     */
    public static function login($username, $password) {
        self::init_session();

        $remaining_sec = 0;
        if (self::is_rate_limited($remaining_sec)) {
            $wait_min = (int)ceil($remaining_sec / 60);
            throw new Exception("Too many failed login attempts. Please wait {$wait_min} minute(s) before trying again.");
        }

        $username = trim((string)$username);
        $password = (string)$password;
        if ($username === '' || $password === '') {
            self::record_failed_login_attempt();
            return false;
        }

        try {
            $pdo = SLEA_DB::get_connection();
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = :u LIMIT 1");
            $stmt->execute([':u' => $username]);
            $user = $stmt->fetch();

            if ($user && !empty($user['password_hash']) && password_verify($password, $user['password_hash'])) {
                self::clear_failed_login_attempts();
                @session_regenerate_id(true);

                $_SESSION['slea_admin_logged_in'] = true;
                $_SESSION['slea_user_id']         = (int)$user['id'];
                $_SESSION['slea_username']        = (string)$user['username'];
                $_SESSION['slea_role']            = (string)($user['role'] ?? 'admin');
                $_SESSION['slea_ua_hash']         = hash('sha256', (string)($_SERVER['HTTP_USER_AGENT'] ?? ''));
                $_SESSION['slea_last_activity']   = time();
                $_SESSION['slea_created_at']      = time();
                $_SESSION['slea_csrf_token']      = bin2hex(random_bytes(32));
                self::$verified_user_this_request = true;

                // Update last login timestamp
                $upd = $pdo->prepare("UPDATE users SET last_login = :now WHERE id = :id");
                $upd->execute([
                    ':now' => date('Y-m-d H:i:s'),
                    ':id'  => $user['id']
                ]);

                return true;
            }
        } catch (Exception $e) {
            error_log('Login DB error: ' . $e->getMessage());
        }

        self::record_failed_login_attempt();
        return false;
    }

    public static function is_logged_in() {
        self::init_session();
        if (empty($_SESSION['slea_admin_logged_in']) || $_SESSION['slea_admin_logged_in'] !== true) {
            return false;
        }
        $uid = (int)($_SESSION['slea_user_id'] ?? 0);
        if ($uid <= 0) {
            return false;
        }

        // Verify User-Agent session binding to prevent session hijacking
        $current_ua_hash = hash('sha256', (string)($_SERVER['HTTP_USER_AGENT'] ?? ''));
        if (!empty($_SESSION['slea_ua_hash']) && !hash_equals((string)$_SESSION['slea_ua_hash'], $current_ua_hash)) {
            self::logout();
            return false;
        }
        if (empty($_SESSION['slea_ua_hash'])) {
            $_SESSION['slea_ua_hash'] = $current_ua_hash;
        }

        // Verify idle timeout (4 hours max inactivity)
        $now = time();
        $last_act = (int)($_SESSION['slea_last_activity'] ?? $now);
        if (($now - $last_act) > 14400) {
            self::logout();
            return false;
        }
        $_SESSION['slea_last_activity'] = $now;

        // Periodically rotate session ID every 30 minutes
        $created = (int)($_SESSION['slea_created_at'] ?? $now);
        if (($now - $created) > 1800) {
            @session_regenerate_id(true);
            $_SESSION['slea_created_at'] = $now;
        }

        // Verify user still exists in DB once per request
        if (self::$verified_user_this_request === null) {
            try {
                $pdo = SLEA_DB::get_connection();
                $stmt = $pdo->prepare("SELECT id, username, role FROM users WHERE id = :id LIMIT 1");
                $stmt->execute([':id' => $uid]);
                $row = $stmt->fetch();
                if (!$row) {
                    self::$verified_user_this_request = false;
                    self::logout();
                    return false;
                }
                $_SESSION['slea_username'] = (string)$row['username'];
                $_SESSION['slea_role'] = (string)($row['role'] ?? 'admin');
                self::$verified_user_this_request = true;
            } catch (Exception $e) {
                self::$verified_user_this_request = true;
            }
        } elseif (self::$verified_user_this_request === false) {
            return false;
        }

        return true;
    }

    public static function get_current_user() {
        self::init_session();
        if (!self::is_logged_in()) return null;
        return [
            'id'       => (int)($_SESSION['slea_user_id'] ?? 0),
            'username' => (string)($_SESSION['slea_username'] ?? 'admin'),
            'role'     => (string)($_SESSION['slea_role'] ?? 'admin')
        ];
    }

    /**
     * Get or generate cryptographic CSRF token for the current session
     */
    public static function get_csrf_token() {
        self::init_session();
        if (empty($_SESSION['slea_csrf_token']) || !is_string($_SESSION['slea_csrf_token']) || strlen($_SESSION['slea_csrf_token']) < 32) {
            $_SESSION['slea_csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['slea_csrf_token'];
    }

    /**
     * Constant-time CSRF token validation
     */
    public static function verify_csrf_token($token) {
        if (!is_string($token) || $token === '') {
            return false;
        }
        $expected = self::get_csrf_token();
        return hash_equals($expected, trim($token));
    }

    /**
     * Validate CSRF token + Same-Origin headers on state-changing admin API / form requests
     */
    public static function verify_request_csrf($json_body = null) {
        self::init_session();

        // 1. Validate Origin / Referer if provided by browser
        $host = strtolower(preg_replace('/:\d+$/', '', (string)($_SERVER['HTTP_HOST'] ?? '')));
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        $referer = $_SERVER['HTTP_REFERER'] ?? '';

        if ($origin !== '' && $origin !== 'null') {
            $origin_host = strtolower((string)parse_url($origin, PHP_URL_HOST));
            if ($host !== '' && $origin_host !== '' && $origin_host !== $host) {
                return false;
            }
        }
        if ($referer !== '') {
            $referer_host = strtolower((string)parse_url($referer, PHP_URL_HOST));
            if ($host !== '' && $referer_host !== '' && $referer_host !== $host) {
                return false;
            }
        }

        // 2. Check cryptographic CSRF token from Header, POST, GET, or JSON payload
        $token = $_SERVER['HTTP_X_CSRF_TOKEN']
            ?? $_POST['csrf_token']
            ?? $_GET['csrf_token']
            ?? (is_array($json_body) ? ($json_body['csrf_token'] ?? '') : '');

        if (is_string($token) && $token !== '') {
            return self::verify_csrf_token($token);
        }

        return false;
    }

    /**
     * Output buffer callback that automatically injects CSRF meta/JS interceptor into Admin HTML pages
     */
    public static function inject_csrf_protection_into_html($html) {
        if (!is_string($html) || stripos($html, '<html') === false) {
            return $html;
        }
        $csrf = self::get_csrf_token();
        $csrf_attr = htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8');
        $csrf_json = json_encode($csrf);

        $script = "\n    <meta name=\"csrf-token\" content=\"{$csrf_attr}\">\n"
            . "    <script>\n"
            . "    (function(){\n"
            . "        window.SLEA_CSRF_TOKEN = {$csrf_json};\n"
            . "        var origFetch = window.fetch;\n"
            . "        if (origFetch) {\n"
            . "            window.fetch = function(input, init) {\n"
            . "                init = init ? Object.assign({}, init) : {};\n"
            . "                var urlStr = (typeof input === 'string') ? input : (input && input.url ? input.url : '');\n"
            . "                if (urlStr.indexOf('api.php') !== -1 || urlStr.indexOf('/api/') !== -1 || urlStr.indexOf('logout.php') !== -1) {\n"
            . "                    var headers = new Headers(init.headers || {});\n"
            . "                    if (!headers.has('X-CSRF-Token')) {\n"
            . "                        headers.set('X-CSRF-Token', window.SLEA_CSRF_TOKEN);\n"
            . "                    }\n"
            . "                    init.headers = headers;\n"
            . "                }\n"
            . "                return origFetch.call(this, input, init);\n"
            . "            };\n"
            . "        }\n"
            . "    })();\n"
            . "    </script>\n";

        if (stripos($html, '</head>') !== false) {
            $html = preg_replace('/<\/head>/i', $script . '</head>', $html, 1);
        } else {
            $html = $script . $html;
        }

        // Rewrite logout.php links to include csrf_token query parameter
        $html = str_replace(
            ['href="logout.php"', "href='logout.php'"],
            ['href="logout.php?csrf_token=' . urlencode($csrf) . '"', "href='logout.php?csrf_token=" . urlencode($csrf) . "'"],
            $html
        );

        return $html;
    }

    public static function logout() {
        self::init_session();
        self::$verified_user_this_request = false;
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        @session_destroy();
    }

    // -------------------------------------------------------------
    // User Management Methods (Create, List, Change Password, Delete)
    // -------------------------------------------------------------

    public static function get_all_users() {
        $pdo = SLEA_DB::get_connection();
        $stmt = $pdo->query("SELECT id, username, role, last_login, created_at FROM users ORDER BY id ASC");
        return $stmt->fetchAll();
    }

    private static function sync_users_backup() {
        if (class_exists('SLEA_Datastore')) {
            SLEA_Datastore::sync_users_to_backup_file();
        }
    }

    public static function create_first_admin($username, $email, $password) {
        self::init_session();
        if (self::has_users()) {
            return ['success' => false, 'error' => 'Setup is permanently locked. An administrator account already exists.'];
        }

        $username = trim((string)$username);
        $email    = trim((string)$email);
        $password = (string)$password;

        if ($username === '' || strlen($username) < 3 || strlen($username) > 50) {
            return ['success' => false, 'error' => 'Username must be between 3 and 50 characters.'];
        }
        if (!preg_match('/^[a-zA-Z0-9_.-]+$/', $username)) {
            return ['success' => false, 'error' => 'Username may only contain letters, numbers, dots, underscores, and hyphens.'];
        }
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'error' => 'Please enter a valid email address.'];
        }
        if (strlen($password) < 6) {
            return ['success' => false, 'error' => 'Password must be at least 6 characters.'];
        }

        try {
            $pdo = SLEA_DB::get_connection();
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $now = date('Y-m-d H:i:s');

            try {
                $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, role, created_at) VALUES (:u, :e, :p, 'superadmin', :now)");
                $stmt->execute([
                    ':u'   => $username,
                    ':e'   => $email,
                    ':p'   => $hash,
                    ':now' => $now
                ]);
            } catch (Exception $colEx) {
                $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, role, created_at) VALUES (:u, :p, 'superadmin', :now)");
                $stmt->execute([
                    ':u'   => $username,
                    ':p'   => $hash,
                    ':now' => $now
                ]);
            }

            $new_id = (int)$pdo->lastInsertId();
            $lock_file = dirname(__DIR__) . '/data/setup.lock';
            @file_put_contents($lock_file, $now, LOCK_EX);
            self::sync_users_backup();

            @session_regenerate_id(true);
            $_SESSION['slea_admin_logged_in'] = true;
            $_SESSION['slea_user_id']         = $new_id;
            $_SESSION['slea_username']        = $username;
            $_SESSION['slea_role']            = 'superadmin';
            $_SESSION['slea_ua_hash']         = hash('sha256', (string)($_SERVER['HTTP_USER_AGENT'] ?? ''));
            $_SESSION['slea_last_activity']   = time();
            $_SESSION['slea_created_at']      = time();
            $_SESSION['slea_csrf_token']      = bin2hex(random_bytes(32));
            self::$verified_user_this_request = true;

            return ['success' => true, 'user_id' => $new_id];
        } catch (Exception $e) {
            return ['success' => false, 'error' => 'Database error while creating administrator account.'];
        }
    }

    public static function create_user($username, $password, $role = 'admin') {
        $username = trim((string)$username);
        if (empty($username) || strlen($username) < 3 || strlen($username) > 50) {
            throw new Exception('Username must be between 3 and 50 characters.');
        }
        if (!preg_match('/^[a-zA-Z0-9_.-]+$/', $username)) {
            throw new Exception('Username may only contain letters, numbers, dots, underscores, and hyphens.');
        }
        if (empty($password) || strlen($password) < 6) {
            throw new Exception('Password must be at least 6 characters.');
        }
        $allowed_roles = ['admin', 'superadmin'];
        if (!in_array($role, $allowed_roles, true)) {
            $role = 'admin';
        }

        $pdo = SLEA_DB::get_connection();
        $stmt_check = $pdo->prepare("SELECT id FROM users WHERE username = :u LIMIT 1");
        $stmt_check->execute([':u' => $username]);
        if ($stmt_check->fetch()) {
            throw new Exception('Username already exists.');
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, role, created_at) VALUES (:u, :p, :r, :now)");
        $stmt->execute([
            ':u'   => $username,
            ':p'   => $hash,
            ':r'   => $role,
            ':now' => date('Y-m-d H:i:s')
        ]);

        $lock_file = dirname(__DIR__) . '/data/setup.lock';
        @file_put_contents($lock_file, date('Y-m-d H:i:s'), LOCK_EX);

        self::sync_users_backup();
        return $pdo->lastInsertId();
    }

    public static function change_password($user_id, $current_password, $new_password) {
        if (empty($new_password) || strlen($new_password) < 6) {
            throw new Exception('New password must be at least 6 characters.');
        }

        $pdo = SLEA_DB::get_connection();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $user_id]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($current_password, $user['password_hash'])) {
            throw new Exception('Current password is incorrect.');
        }

        $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
        $upd = $pdo->prepare("UPDATE users SET password_hash = :p WHERE id = :id");
        $res = $upd->execute([
            ':p'  => $new_hash,
            ':id' => $user_id
        ]);
        self::sync_users_backup();
        return $res;
    }

    public static function admin_reset_user_password($target_user_id, $new_password) {
        if (empty($new_password) || strlen($new_password) < 6) {
            throw new Exception('New password must be at least 6 characters.');
        }
        $pdo = SLEA_DB::get_connection();
        $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
        $upd = $pdo->prepare("UPDATE users SET password_hash = :p WHERE id = :id");
        $res = $upd->execute([
            ':p'  => $new_hash,
            ':id' => $target_user_id
        ]);
        self::sync_users_backup();
        return $res;
    }

    public static function delete_user($target_user_id, $current_user_id) {
        if (intval($target_user_id) === intval($current_user_id)) {
            throw new Exception('You cannot delete your own active account.');
        }

        $pdo = SLEA_DB::get_connection();
        $stmt_cnt = $pdo->query("SELECT COUNT(*) as cnt FROM users");
        $cnt = $stmt_cnt->fetch()['cnt'] ?? 1;
        if ($cnt <= 1) {
            throw new Exception('Cannot delete the last remaining admin user.');
        }

        $stmt = $pdo->prepare("DELETE FROM users WHERE id = :id");
        $res = $stmt->execute([':id' => $target_user_id]);
        self::sync_users_backup();
        return $res;
    }

    /**
     * Enforce admin authentication on a page or API endpoint.
     * Unauthenticated visitors receive a stealth 404 (hiding admin routes completely).
     */
    public static function require_admin($is_api = false) {
        if (!self::is_logged_in()) {
            if ($is_api) {
                http_response_code(404);
                header('Content-Type: application/json; charset=utf-8');
                header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
                header('X-Content-Type-Options: nosniff');
                echo json_encode(['success' => false, 'error' => 'Not Found']);
                exit;
            } else {
                self::render_404();
            }
        }

        if (!headers_sent()) {
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            header('Pragma: no-cache');
            header('Expires: 0');
            header('X-Frame-Options: SAMEORIGIN');
            header('X-Content-Type-Options: nosniff');
            header('Referrer-Policy: same-origin');
            header('X-Robots-Tag: noindex, nofollow, noarchive');
        }

        if (!$is_api && !self::$csrf_ob_registered) {
            self::$csrf_ob_registered = true;
            @ob_start([self::class, 'inject_csrf_protection_into_html']);
        }
    }

    /**
     * Render a stealth 404 page that honors Debug Mode while never leaking sensitive admin routes
     */
    public static function render_404($title_or_reason = '', $explicit_reason = '') {
        if (!class_exists('SLEA_Datastore') && file_exists(__DIR__ . '/class-datastore.php')) {
            require_once __DIR__ . '/class-datastore.php';
        }
        if (class_exists('SLEA_Datastore') && method_exists('SLEA_Datastore', 'render_public_error')) {
            $uri = $_SERVER['REQUEST_URI'] ?? '';
            if ($explicit_reason !== '') {
                $title = $title_or_reason !== '' ? $title_or_reason : '404 - Page Not Found';
                $reason = $explicit_reason;
            } else {
                $title = '404 - Page Not Found';
                $reason = $title_or_reason !== ''
                    ? $title_or_reason
                    : "Actual Error [HTTP 404]: Requested resource '" . $uri . "' is not available or requires authentication.";
            }
            SLEA_Datastore::render_public_error(
                $title,
                $reason,
                404
            );
        }

        http_response_code(404);
        header('X-Robots-Tag: noindex, nofollow');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Unable to Open Link</title><style>body{margin:0;padding:24px;min-height:100vh;display:flex;align-items:center;justify-content:center;background:#f8fafd;color:#1f1f1f;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;box-sizing:border-box}.card{max-width:420px;width:100%;background:#fff;border:1px solid #e1e7f0;border-radius:20px;padding:32px 24px;text-align:center;box-shadow:0 4px 20px rgba(0,0,0,0.04)}h1{font-size:19px;font-weight:700;margin:0 0 8px;color:#1f1f1f}p{font-size:13px;line-height:1.6;color:#5f6368;margin:0 0 20px}button{background:#0b57d0;color:#fff;border:0;border-radius:12px;padding:10px 20px;font-size:13px;font-weight:600;cursor:pointer}</style></head><body><div class="card"><h1>Unable to Open Link</h1><p>We could not load this page right now. The link you followed may be unavailable, moved, or expired.</p><button onclick="window.location.reload()">Try Again</button></div></body></html>';
        exit;
    }
}
