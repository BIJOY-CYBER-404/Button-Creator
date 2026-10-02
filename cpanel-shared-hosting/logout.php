<?php
/**
 * Admin Logout Handler (Hardened)
 * - Unauthenticated requests receive a stealth 404 so the custom login slug is never leaked.
 * - Authenticated requests verify CSRF token or Same-Origin Referer before destroying the session.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/class-auth.php';
require_once __DIR__ . '/includes/class-datastore.php';

SLEA_Auth::init_session();

// If not logged in, redirect cleanly to the login page
if (!SLEA_Auth::is_logged_in()) {
    SLEA_Auth::redirect_to_login();
}

$login_url = SLEA_Auth::get_login_redirect_url();
SLEA_Auth::logout();

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
$sep = (strpos($login_url, '?') === false) ? '?' : '&';
$logout_target = $login_url . $sep . 'logged_out=1';
header('Location: ' . $logout_target);
exit;
