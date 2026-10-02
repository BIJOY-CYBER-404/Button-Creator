<?php
/**
 * Root Router for cPanel
 * Only button pages (/p/{slug}) are publicly viewable.
 * Absolutely no hooks, links, or redirects to private admin pages for public visitors.
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

// Check if request is for /p/{slug}, legal pages, or ?p={slug} or ?slug={slug}
$slug = isset($_GET['slug']) ? trim($_GET['slug']) : (isset($_GET['p']) ? trim($_GET['p']) : '');
$path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
if (empty($slug)) {
    if (preg_match('#/p/([a-zA-Z0-9_-]+)#', $path, $m)) {
        $slug = trim($m[1]);
    } elseif (preg_match('#/(dmca|disclaimer|about-us|about|privacy-policy|privacy)/?$#i', $path, $lm)) {
        $slug = strtolower(trim($lm[1]));
        $_GET['slug'] = $slug;
    }
}

$maintenance = SLEA_Datastore::get_maintenance_settings();

if (!empty($slug) || (!empty($maintenance['enabled']) && !SLEA_Auth::is_logged_in())) {
    require __DIR__ . '/view.php';
    exit;
}

// If admin is already authenticated in session, redirect to admin dashboard
if (SLEA_Auth::is_logged_in()) {
    header('Location: admin.php');
    exit;
}

// For public visitors: Render error according to Debug Mode (Off = generic notice, On = actual error reason)
SLEA_Datastore::render_public_error(
    '404 - Route Not Found',
    "Actual Error [HTTP 404]: Direct request to '" . ($path ?: '/') . "' failed because no episode page slug (?slug= or /p/{slug}) was provided.",
    404,
    'We could not load this page right now. Please check your episode link and try again.'
);
