<?php
/**
 * Authentication and Admin Session Protection with MySQL Database
 */

require_once __DIR__ . '/class-db.php';

class SLEA_Auth {
    public static function start_session() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public static function has_users() {
        try {
            $pdo = SLEA_DB::get_connection();
            $stmt = $pdo->query("SELECT COUNT(*) AS total FROM users");
            $row = $stmt->fetch();
            return !empty($row) && intval($row['total']) > 0;
        } catch (Exception $e) {
            return false;
        }
    }

    public static function is_logged_in() {
        self::start_session();
        return !empty($_SESSION['slea_admin_logged_in']) && $_SESSION['slea_admin_logged_in'] === true;
    }

    public static function get_current_user() {
        self::start_session();
        if (!self::is_logged_in()) {
            return null;
        }
        return [
            'id'          => $_SESSION['slea_admin_id'] ?? 0,
            'username'    => $_SESSION['slea_admin_user'] ?? 'admin',
            'email'       => $_SESSION['slea_admin_email'] ?? '',
            'role'        => $_SESSION['slea_admin_role'] ?? 'admin',
            'permissions' => $_SESSION['slea_admin_perms'] ?? ['all']
        ];
    }

    /**
     * One-time account creation for the initial administrator.
     * Permanently locked once an admin account exists!
     */
    public static function create_first_admin($username, $email, $password) {
        if (self::has_users()) {
            return [
                'success' => false,
                'error'   => 'Setup has already been completed. Account creation is permanently closed.'
            ];
        }

        $username = trim($username);
        $email    = trim($email);
        $password = trim($password);

        if (strlen($username) < 3) {
            return ['success' => false, 'error' => 'Username must be at least 3 characters.'];
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'error' => 'Please provide a valid email address.'];
        }
        if (strlen($password) < 6) {
            return ['success' => false, 'error' => 'Password must be at least 6 characters long.'];
        }

        try {
            $pdo = SLEA_DB::get_connection();
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $now  = date('Y-m-d H:i:s');
            $perms = json_encode(['all', 'pages', 'settings', 'generator', 'users']);

            $stmt = $pdo->prepare("
                INSERT INTO users (username, email, password_hash, role, permissions, created_at, updated_at)
                VALUES (:u, :e, :p, 'superadmin', :perms, :created_at, :updated_at)
            ");
            $stmt->execute([
                ':u'          => $username,
                ':e'          => $email,
                ':p'          => $hash,
                ':perms'      => $perms,
                ':created_at' => $now,
                ':updated_at' => $now
            ]);

            $id = $pdo->lastInsertId();

            // Automatically log in
            self::start_session();
            $_SESSION['slea_admin_logged_in'] = true;
            $_SESSION['slea_admin_id']        = $id;
            $_SESSION['slea_admin_user']      = $username;
            $_SESSION['slea_admin_email']     = $email;
            $_SESSION['slea_admin_role']      = 'superadmin';
            $_SESSION['slea_admin_perms']     = ['all'];

            return ['success' => true];
        } catch (Exception $e) {
            return ['success' => false, 'error' => 'Database error: ' . $e->getMessage()];
        }
    }

    public static function login($username_or_email, $password) {
        self::start_session();
        $input = trim($username_or_email);

        try {
            $pdo = SLEA_DB::get_connection();
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = :uname OR email = :uemail LIMIT 1");
            $stmt->execute([
                ':uname'  => $input,
                ':uemail' => $input
            ]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                $_SESSION['slea_admin_logged_in'] = true;
                $_SESSION['slea_admin_id']        = $user['id'];
                $_SESSION['slea_admin_user']      = $user['username'];
                $_SESSION['slea_admin_email']     = $user['email'];
                $_SESSION['slea_admin_role']      = $user['role'];
                $_SESSION['slea_admin_perms']     = !empty($user['permissions']) ? json_decode($user['permissions'], true) : ['all'];
                $_SESSION['slea_login_time']      = time();
                return true;
            }
        } catch (Exception $e) {
            error_log('Login error: ' . $e->getMessage());
        }

        return false;
    }

    public static function logout() {
        self::start_session();
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        session_destroy();
    }

    public static function require_admin() {
        self::start_session();
        if (!self::is_logged_in()) {
            if (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) {
                header('Content-Type: application/json; charset=utf-8');
                http_response_code(401);
                echo json_encode([
                    'success' => false,
                    'error'   => 'Unauthorized: Admin authentication required.'
                ]);
                exit;
            }
            // Redirect to private login page
            header('Location: login.php');
            exit;
        }
    }
}
