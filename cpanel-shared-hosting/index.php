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

if (isset($_GET['maintenance_asset']) && $_GET['maintenance_asset'] === '1') {
    $img_file = __DIR__ . '/assets/images/maintenance_illustration.jpg';
    if (file_exists($img_file)) {
        header('Content-Type: image/jpeg');
        header('Cache-Control: public, max-age=86400');
        readfile($img_file);
        exit;
    }
}

// Check if request is for /p/{slug} or ?p={slug} or ?slug={slug}
$slug = isset($_GET['slug']) ? trim($_GET['slug']) : (isset($_GET['p']) ? trim($_GET['p']) : '');
if (empty($slug)) {
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
    if (preg_match('#/p/([a-zA-Z0-9_-]+)#', $path, $m)) {
        $slug = trim($m[1]);
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

// For public visitors: No admin hooks, links, or clues exist.
http_response_code(404);
die('<!DOCTYPE html><html><head><meta name="robots" content="noindex, nofollow, noarchive"><title>404 Not Found</title></head><body style="font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,sans-serif;text-align:center;padding:60px 20px;background:#f8fafd;color:#1f1f1f;"><div style="max-width:440px;margin:0 auto;background:#ffffff;padding:32px;border-radius:24px;border:1px solid #e0e4eb;box-shadow:0 1px 3px rgba(0,0,0,0.05);"><h1 style="color:#d93025;font-size:20px;font-weight:700;margin:0 0 8px;">404 Not Found</h1><p style="font-size:13px;color:#5f6368;margin:0;">The requested page does not exist or has been removed.</p></div></body></html>');
