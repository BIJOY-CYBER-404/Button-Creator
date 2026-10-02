<?php
/**
 * Root Router for cPanel (Hardened)
 * Only public button pages (/p/{slug}) and legal pages are publicly viewable.
 * Custom login slug routes cleanly to login.php (or redirects to admin.php if already logged in).
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/class-db.php';
require_once __DIR__ . '/includes/class-auth.php';
require_once __DIR__ . '/includes/class-datastore.php';

SLEA_Datastore::register_public_error_handler();

if (isset($_GET['maintenance_asset']) && $_GET['maintenance_asset'] === '1') {
    $img_file = __DIR__ . '/assets/images/maintenance_illustration.jpg';
    if (file_exists($img_file)) {
        header('Content-Type: image/jpeg');
        header('Cache-Control: public, max-age=86400');
        readfile($img_file);
        exit;
    }
}

$slug = isset($_GET['slug']) ? trim((string)$_GET['slug']) : (isset($_GET['p']) ? trim((string)$_GET['p']) : '');
$path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
$configured_login_slug = SLEA_Datastore::get_login_slug();
$is_explicit_episode_route = (bool)preg_match('#/(?:p|page)/([a-zA-Z0-9_-]+)#i', $path);

// 1. Check Custom Login Slug or Default Login Route FIRST (before treating root rewrite ?slug= as an episode page)
if (!$is_explicit_episode_route) {
    $path_base = strtolower((string)preg_replace('/\.php$/i', '', trim(basename($path), '/')));
    $slug_clean = strtolower((string)preg_replace('/\.php$/i', '', trim($slug, '/')));

    $matches_configured_login = ($configured_login_slug !== '')
        && ($path_base === strtolower($configured_login_slug) || $slug_clean === strtolower($configured_login_slug));

    if ($matches_configured_login) {
        if (SLEA_Auth::is_logged_in()) {
            header('Location: admin.php');
            exit;
        }
        define('SLEA_LOGIN_ROUTED', true);
        require __DIR__ . '/login.php';
        exit;
    }

    // If custom login slug is set (not 'login') and visitor requests /login, return stealth 404 (or redirect to admin.php if already logged in)
    if ($path_base === 'login' || $slug_clean === 'login') {
        if (SLEA_Auth::is_logged_in()) {
            header('Location: admin.php');
            exit;
        }
        if ($configured_login_slug !== 'login') {
            SLEA_Auth::render_404();
        }
        define('SLEA_LOGIN_ROUTED', true);
        require __DIR__ . '/login.php';
        exit;
    }
}

// 2. Check /p/{slug} or legal pages if $slug was not populated by query string
if (empty($slug)) {
    if (preg_match('#/(?:p|page)/([a-zA-Z0-9_-]+)#', $path, $m)) {
        $slug = trim($m[1]);
    } elseif (preg_match('#/(dmca|disclaimer|about-us|about|privacy-policy|privacy)/?$#i', $path, $lm)) {
        $slug = strtolower(trim($lm[1]));
        $_GET['slug'] = $slug;
    }
}

// Route clean admin paths (/admin, /pages, /settings, /analytics, /update, /logout) using cookie login state
$admin_route_map = [
    'admin'     => 'admin.php',
    'pages'     => 'pages.php',
    'settings'  => 'settings.php',
    'analytics' => 'analytics.php',
    'update'    => 'update.php',
    'updater'   => 'update.php',
    'logout'    => 'logout.php'
];
if (!empty($slug) && isset($admin_route_map[strtolower($slug)]) && !$is_explicit_episode_route) {
    $target_php = $admin_route_map[strtolower($slug)];
    if ($target_php === 'logout.php') {
        require __DIR__ . '/logout.php';
        exit;
    }
    if (SLEA_Auth::is_logged_in()) {
        header('Location: ' . $target_php);
        exit;
    } else {
        SLEA_Auth::redirect_to_login('/' . $target_php);
    }
}

// Block reserved system names from being resolved as episode slugs unless they are legal pages
$legal_slugs = ['dmca', 'disclaimer', 'about-us', 'about', 'privacy-policy', 'privacy'];
$reserved_system_slugs = ['api', 'setup', 'view', 'index', 'config', 'data', 'includes', 'backups', 'temp', 'database'];
if (!empty($slug) && !in_array(strtolower($slug), $legal_slugs, true)) {
    $lower_slug = strtolower($slug);
    if (in_array($lower_slug, $reserved_system_slugs, true) || ($configured_login_slug !== '' && $lower_slug === strtolower($configured_login_slug))) {
        SLEA_Auth::render_404();
    }
}

$maintenance = SLEA_Datastore::get_maintenance_settings();

if (!empty($slug) || (!empty($maintenance['enabled']) && !SLEA_Auth::is_logged_in())) {
    require __DIR__ . '/view.php';
    exit;
}

// Render error according to Debug Mode (Off = generic notice, On = actual error reason)
SLEA_Datastore::render_public_error(
    '404 - Route Not Found (Missing Episode Slug)',
    "Actual Error [HTTP 404]: Direct request to '" . ($path ?: '/') . "' failed because no episode page slug (?slug= or /p/{slug}) was provided in the request URL.",
    404,
    'We could not load this page right now. Please check your episode link and try again.'
);
