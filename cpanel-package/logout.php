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

// Never reveal logout endpoint or custom login URL to unauthenticated visitors
if (!SLEA_Auth::is_logged_in()) {
    SLEA_Auth::render_404();
}

// Verify CSRF token or strict Same-Origin Referer to prevent cross-site forced logout
$csrf_token = $_GET['csrf_token'] ?? $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
$valid_csrf = ($csrf_token !== '' && SLEA_Auth::verify_csrf_token($csrf_token));

if (!$valid_csrf) {
    $host = strtolower(preg_replace('/:\d+$/', '', (string)($_SERVER['HTTP_HOST'] ?? '')));
    $referer = (string)($_SERVER['HTTP_REFERER'] ?? '');
    $referer_host = $referer !== '' ? strtolower((string)parse_url($referer, PHP_URL_HOST)) : '';
    if ($host === '' || $referer_host === '' || $referer_host !== $host) {
        SLEA_Auth::render_404();
    }
}

$login_url = SLEA_Datastore::get_login_url();
SLEA_Auth::logout();

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
$safe_login_url = preg_replace('/[^a-zA-Z0-9_.-]/', '', $login_url) ?: 'login.php';
header('Location: ' . $safe_login_url);
exit;
