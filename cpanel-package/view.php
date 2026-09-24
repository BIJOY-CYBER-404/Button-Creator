<?php
/**
 * Public Non-Indexable Episode Button Page Viewer
 * Strictly non-indexable by search engines (RFC 6305).
 * Google Material M3 Light Theme Design.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/class-db.php';
require_once __DIR__ . '/includes/class-auth.php';
require_once __DIR__ . '/includes/class-datastore.php';

// Force Search Engine Non-Indexation Headers (RFC 6305)
header('Cache-Control: public, max-age=60');

$slug = isset($_GET['slug']) ? trim($_GET['slug']) : (isset($_GET['p']) ? trim($_GET['p']) : '');

// Fallback extraction from /p/{slug} path if empty
if (empty($slug)) {
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
    if (preg_match('#/p/([a-zA-Z0-9_-]+)#', $path, $m)) {
        $slug = trim($m[1]);
    }
}

if (empty($slug)) {
    http_response_code(404);
    die('<!DOCTYPE html><html><head><meta name="robots" content="noindex,nofollow"><title>Page Not Found</title></head><body style="font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,sans-serif;text-align:center;padding:60px 20px;background:#f8fafd;color:#1f1f1f;"><div style="max-width:440px;margin:0 auto;background:#ffffff;padding:32px;border-radius:24px;border:1px solid #e0e4eb;box-shadow:0 1px 3px rgba(0,0,0,0.05);"><h2 style="color:#d93025;margin:0 0 8px;">404 - Page Not Found</h2><p style="font-size:13px;color:#5f6368;margin:0;">The requested episode link page could not be located.</p></div></body></html>');
}

// Seamless 301 redirect if accessed via direct view.php?slug=... to modern /p/{slug} clean structure
$request_uri = $_SERVER['REQUEST_URI'] ?? '';
if (strpos($request_uri, 'view.php') !== false && !empty($slug)) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $base_dir = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
    // Normalize slug (strip legacy ep- prefix for public clean URLs)
    $clean_slug_redirect = (strpos($slug, 'ep-') === 0) ? substr($slug, 3) : $slug;
    $target_clean_url = ($base_dir === '' || $base_dir === '/') ? "/p/{$clean_slug_redirect}" : "{$base_dir}/p/{$clean_slug_redirect}";
    header("Location: {$protocol}{$host}{$target_clean_url}", true, 301);
    exit;
}

$is_admin = SLEA_Auth::is_logged_in();

$maintenance = SLEA_Datastore::get_maintenance_settings();
if (!empty($maintenance['enabled']) && !$is_admin) {
    http_response_code(503);
    header('Retry-After: 3600');
    $custom_msg = htmlspecialchars($maintenance['message'] ?? 'The website is currently undergoing scheduled maintenance. We will be back shortly!');
    $end_time = !empty($maintenance['end_time']) ? trim($maintenance['end_time']) : '';
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="robots" content="noindex, nofollow, noarchive">
        <title>Scheduled Maintenance - Back Soon</title>
        <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
        <script src="https://cdn.tailwindcss.com"></script>
        <style>
            body { font-family: 'Plus Jakarta Sans', sans-serif; }
            .font-mono { font-family: 'JetBrains Mono', monospace; }
            @keyframes spin-slow {
                from { transform: rotate(0deg); }
                to { transform: rotate(360deg); }
            }
            .spin-slow {
                animation: spin-slow 16s infinite linear;
            }
        </style>
    </head>
    <body class="bg-[#f8fafd] text-[#1f1f1f] min-h-screen flex items-center justify-center p-4 sm:p-6 selection:bg-[#d3e3fd] select-none">
        <div class="max-w-lg w-full bg-white rounded-3xl border border-[#e0e4eb] p-6 sm:p-8 shadow-xs text-center space-y-6 relative overflow-hidden">
            <!-- Top Illustration Banner -->
            <div class="relative w-full aspect-[4/3] rounded-2xl overflow-hidden bg-[#fafbfc] border border-[#f0f4f9]">
                <div class="absolute inset-0 flex items-center justify-center opacity-10">
                    <svg class="w-48 h-48 text-[#0b57d0] spin-slow" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                    </svg>
                </div>
                <img src="assets/images/maintenance_illustration.jpg" alt="Under Maintenance" class="w-full h-full object-cover transition-transform hover:scale-105 duration-700" referrerPolicy="no-referrer" />
            </div>

            <!-- Header Badge -->
            <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-[#fef7e0] border border-[#feebc8] text-[#b06000] text-xs font-bold font-mono">
                <span class="w-2 h-2 rounded-full bg-[#b06000] animate-ping"></span>
                <span>SYSTEM MAINTENANCE</span>
            </div>

            <!-- Page Titles -->
            <div class="space-y-2">
                <h1 class="text-xl sm:text-2xl font-extrabold tracking-tight text-[#111827]">
                    We'll Be Right Back
                </h1>
                <p class="text-xs sm:text-sm text-[#444746] leading-relaxed max-w-md mx-auto">
                    <?= $custom_msg ?>
                </p>
            </div>

            <!-- Maintenance End Time Countdown Component (Days, Hours, Minutes, Seconds) -->
            <?php if (!empty($end_time)): ?>
            <div id="countdownWrapper" class="bg-[#f8fafd] rounded-2xl p-4 sm:p-5 border border-[#e1e7f0] space-y-3 shadow-2xs">
                <div class="flex items-center justify-between text-xs font-semibold text-[#5f6368]">
                    <span class="flex items-center gap-1.5 text-[#0b57d0] font-bold">
                        <svg class="w-4 h-4 text-[#0b57d0]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke-width="2"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6l4 2"/></svg>
                        <span>Estimated Time Remaining</span>
                    </span>
                    <span id="countdownStatusBadge" class="text-[10px] font-mono font-bold px-2 py-0.5 rounded-full bg-[#e8f0fe] text-[#0b57d0]">COUNTDOWN</span>
                </div>

                <!-- 4 Real-Time Countdown Badges -->
                <div class="grid grid-cols-4 gap-2 text-center font-mono">
                    <div class="bg-white rounded-xl p-2 sm:p-3 border border-[#dadce0] shadow-2xs">
                        <div id="cDays" class="text-lg sm:text-2xl font-black text-[#0b57d0]">00</div>
                        <div class="text-[9px] sm:text-[10px] text-[#5f6368] font-sans font-semibold uppercase mt-0.5">Days</div>
                    </div>
                    <div class="bg-white rounded-xl p-2 sm:p-3 border border-[#dadce0] shadow-2xs">
                        <div id="cHours" class="text-lg sm:text-2xl font-black text-[#0b57d0]">00</div>
                        <div class="text-[9px] sm:text-[10px] text-[#5f6368] font-sans font-semibold uppercase mt-0.5">Hours</div>
                    </div>
                    <div class="bg-white rounded-xl p-2 sm:p-3 border border-[#dadce0] shadow-2xs">
                        <div id="cMins" class="text-lg sm:text-2xl font-black text-[#0b57d0]">00</div>
                        <div class="text-[9px] sm:text-[10px] text-[#5f6368] font-sans font-semibold uppercase mt-0.5">Minutes</div>
                    </div>
                    <div class="bg-white rounded-xl p-2 sm:p-3 border border-[#dadce0] shadow-2xs">
                        <div id="cSecs" class="text-lg sm:text-2xl font-black text-[#0b57d0]">00</div>
                        <div class="text-[9px] sm:text-[10px] text-[#5f6368] font-sans font-semibold uppercase mt-0.5">Seconds</div>
                    </div>
                </div>

                <div id="countdownNoticeBox" class="text-[11px] text-[#5f6368] font-medium text-center">
                    Target End Time: <span id="countdownFormattedTime" class="font-bold text-[#1f1f1f]"></span>
                </div>
            </div>
            <?php else: ?>
            <div class="bg-[#f8fafd] rounded-2xl p-4 border border-[#e1e7f0] text-center space-y-1">
                <div class="inline-flex items-center gap-1.5 text-xs font-bold text-[#0b57d0]">
                    <svg class="w-3.5 h-3.5 text-[#0b57d0]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke-width="2"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6l4 2"/></svg>
                    <span>System Upgrades Underway</span>
                </div>
                <p class="text-[11px] text-[#5f6368]">
                    We expect to be back online shortly. Page will refresh automatically.
                </p>
            </div>
            <?php endif; ?>

            <script>
                const targetIsoStr = <?= json_encode($end_time) ?>;
                if (targetIsoStr) {
                    const targetDate = new Date(targetIsoStr);
                    const formattedEl = document.getElementById('countdownFormattedTime');
                    if (formattedEl && !isNaN(targetDate.getTime())) {
                        formattedEl.innerText = targetDate.toLocaleString([], { dateStyle: 'medium', timeStyle: 'short' });
                    }

                    function tickCountdown() {
                        const now = new Date();
                        const diff = targetDate.getTime() - now.getTime();
                        if (diff <= 0) {
                            document.getElementById('cDays').innerText = '00';
                            document.getElementById('cHours').innerText = '00';
                            document.getElementById('cMins').innerText = '00';
                            document.getElementById('cSecs').innerText = '00';
                            const badge = document.getElementById('countdownStatusBadge');
                            if (badge) {
                                badge.innerText = 'WRAPPING UP';
                                badge.className = 'text-[10px] font-mono font-bold px-2 py-0.5 rounded-full bg-[#e6f4ea] text-[#137333]';
                            }
                            const notice = document.getElementById('countdownNoticeBox');
                            if (notice) {
                                notice.innerHTML = '<span class="text-[#137333] font-bold">Finalizing maintenance checks... Reloading website.</span>';
                            }
                            setTimeout(() => window.location.reload(), 6000);
                            return;
                        }

                        const totalSecs = Math.floor(diff / 1000);
                        const days = Math.floor(totalSecs / 86400);
                        const hours = Math.floor((totalSecs % 86400) / 3600);
                        const mins = Math.floor((totalSecs % 3600) / 60);
                        const secs = totalSecs % 60;

                        document.getElementById('cDays').innerText = String(days).padStart(2, '0');
                        document.getElementById('cHours').innerText = String(hours).padStart(2, '0');
                        document.getElementById('cMins').innerText = String(mins).padStart(2, '0');
                        document.getElementById('cSecs').innerText = String(secs).padStart(2, '0');
                    }

                    tickCountdown();
                    setInterval(tickCountdown, 1000);
                }

                // Auto reload periodically to detect when maintenance is finished
                setTimeout(() => {
                    window.location.reload();
                }, 30000);
            </script>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Lookup page by slug with bi-directional fallback (supports both clean slug and legacy ep- prefix)
$page = SLEA_Datastore::get_page_by_slug($slug, !$is_admin);
if (!$page) {
    if (strpos($slug, 'ep-') === 0) {
        $alt_slug = substr($slug, 3);
        $page = SLEA_Datastore::get_page_by_slug($alt_slug, !$is_admin);
    } else {
        $alt_slug = 'ep-' . $slug;
        $page = SLEA_Datastore::get_page_by_slug($alt_slug, !$is_admin);
    }
}

if (!$page) {
    http_response_code(404);
    die('<!DOCTYPE html><html><head><meta name="robots" content="noindex,nofollow"><title>Page Not Available</title></head><body style="font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,sans-serif;text-align:center;padding:60px 20px;background:#f8fafd;color:#1f1f1f;"><div style="max-width:440px;margin:0 auto;background:#ffffff;padding:32px;border-radius:24px;border:1px solid #e0e4eb;box-shadow:0 1px 3px rgba(0,0,0,0.05);"><h2 style="color:#d93025;margin:0 0 8px;">404 - Page Not Available</h2><p style="font-size:13px;color:#5f6368;margin:0;">This episode page is either private or does not exist.</p></div></body></html>');
}

// Increment view counter
if (defined('AUTO_INCREMENT_VIEWS') && AUTO_INCREMENT_VIEWS) {
    SLEA_Datastore::increment_views($slug);
}

$title = htmlspecialchars($page['title'] ?? 'Episode Downloads');
$description = htmlspecialchars($page['description'] ?? '');
$buttons = $page['buttons'] ?? [];
$theme = $page['theme'] ?? 'indigo';
$resolved_url = htmlspecialchars($page['resolved_url'] ?? '');
$is_public = !empty($page['is_public']);
$page_id = intval($page['id'] ?? 0);

// Load site branding, navigation menu, footer copyright, and ad settings
$site_identity = SLEA_Datastore::get_site_identity();
$site_name = !empty($site_identity['site_name']) ? $site_identity['site_name'] : (defined('APP_NAME') ? APP_NAME : 'Movie Hub HQ Drive');
$site_logo_url = !empty($site_identity['site_logo_url']) ? $site_identity['site_logo_url'] : '';

$menu_items = SLEA_Datastore::get_menu_items();
$footer_copyright = SLEA_Datastore::get_footer_copyright();
$ad_settings = SLEA_Datastore::get_ad_settings();
$share_settings = SLEA_Datastore::get_share_settings();

// Dynamic canonical share URL strictly adhering to clean /p/{slug} structure everywhere
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$base_dir = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
// Clean display slug (strip ep- prefix for public clean URLs if present)
$clean_share_slug = !empty($page['slug']) ? $page['slug'] : $slug;
if (strpos($clean_share_slug, 'ep-') === 0) {
    $clean_share_slug = substr($clean_share_slug, 3);
}
$canonical_share_url = ($base_dir === '' || $base_dir === '/')
    ? "{$protocol}{$host}/p/" . rawurlencode($clean_share_slug)
    : "{$protocol}{$host}{$base_dir}/p/" . rawurlencode($clean_share_slug);

$share_encoded_url = urlencode($canonical_share_url);
$share_encoded_title = urlencode($page['title'] ?? 'Watch & Download Episodes');
$share_encoded_msg = urlencode(($page['title'] ?? 'Watch & Download') . ' - Fast Episode Links: ' . $canonical_share_url);

// Google Material M3 Light Themes
$themes = [
    'indigo'  => ['bg' => '#f8fafd', 'card' => '#ffffff', 'border' => '#e0e4eb', 'primary' => '#0b57d0', 'hover' => '#0842a0', 'badge_bg' => '#e8f0fe', 'badge_text' => '#041e49', 'accent' => '#0b57d0'],
    'emerald' => ['bg' => '#f6fbf7', 'card' => '#ffffff', 'border' => '#cce8d5', 'primary' => '#0f9d58', 'hover' => '#0b8043', 'badge_bg' => '#e6f4ea', 'badge_text' => '#137333', 'accent' => '#0f9d58'],
    'crimson' => ['bg' => '#fdf7f7', 'card' => '#ffffff', 'border' => '#f7d0cd', 'primary' => '#d93025', 'hover' => '#c5221f', 'badge_bg' => '#fce8e6', 'badge_text' => '#c5221f', 'accent' => '#d93025'],
    'slate'   => ['bg' => '#f8f9fa', 'card' => '#ffffff', 'border' => '#dadce0', 'primary' => '#3c4043', 'hover' => '#202124', 'badge_bg' => '#f1f3f4', 'badge_text' => '#202124', 'accent' => '#5f6368'],
    'dark'    => ['bg' => '#f8fafd', 'card' => '#ffffff', 'border' => '#e0e4eb', 'primary' => '#0b57d0', 'hover' => '#0842a0', 'badge_bg' => '#e8f0fe', 'badge_text' => '#041e49', 'accent' => '#0b57d0']
];
$t = $themes[$theme] ?? $themes['indigo'];

/**
 * Helper: Detect server provider name and return clean provider name + server SVG icon
 */
function resolve_server_info($provider, $url, $btn_text) {
    $haystack = strtolower($provider . ' ' . $url . ' ' . $btn_text);
    
    // 1. Google Drive / GDrive
    if (strpos($haystack, 'gdrive') !== false || strpos($haystack, 'google drive') !== false || strpos($haystack, 'drive.google') !== false || strpos($haystack, 'gd ') !== false) {
        $name = !empty($provider) && strtolower($provider) !== 'fastdl' ? $provider : 'GDrive Fast Server';
        $icon = '<svg class="w-4 h-4 shrink-0" viewBox="0 0 87.3 78" fill="none"><path d="m6.6 66.85 3.85 6.65c.8 1.4 1.95 2.5 3.3 3.3l13.75-23.8H0c0 1.55.4 3.1 1.2 4.5z" fill="#0066da"/><path d="M43.65 25 29.9 1.2c-1.35.8-2.5 1.9-3.3 3.3l-25.4 44C.4 50 0 51.55 0 53.1h27.5z" fill="#00ac47"/><path d="M73.55 76.8c1.35-.8 2.5-1.9 3.3-3.3l1.6-2.75 7.65-13.25c.8-1.4 1.2-2.95 1.2-4.5H59.8l5.85 10.1z" fill="#ea4335"/><path d="M43.65 25 57.4 1.2C56.05.4 54.5 0 52.95 0H34.35c-1.55 0-3.1.4-4.45 1.2z" fill="#00832d"/><path d="m59.8 53.1-16.15-28-16.15 28z" fill="#2684fc"/><path d="M73.55 76.8c1.35-.8 2.5-1.9 3.3-3.3l-13.75-23.8H35.65l13.75 23.8c1.35.8 2.9 1.2 4.45 1.2h15.25c1.55 0 3.1-.4 4.45-1.1z" fill="#ffba00"/></svg>';
        return ['name' => $name, 'icon' => $icon, 'badge' => 'GDrive'];
    }

    // 2. xcloud / Cloud / OneDrive
    if (strpos($haystack, 'xcloud') !== false || strpos($haystack, 'cloud') !== false || strpos($haystack, 'onedrive') !== false || strpos($haystack, 'azure') !== false) {
        $name = !empty($provider) ? $provider : 'xcloud Mirror';
        $icon = '<svg class="w-4 h-4 text-[#0b57d0] shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 15a4 4 0 004 4h9a5 5 0 10-.1-9.999 5.002 5.002 0 00-9.78 2.096A4.001 4.001 0 003 15z"/></svg>';
        return ['name' => $name, 'icon' => $icon, 'badge' => 'xcloud'];
    }

    // 3. Mega / Mediafire / Fast Server
    if (strpos($haystack, 'mega') !== false || strpos($haystack, 'mediafire') !== false) {
        $name = !empty($provider) ? $provider : 'High Speed Mirror';
        $icon = '<svg class="w-4 h-4 text-[#d93025] shrink-0" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 14h-2v-2h2v2zm0-4h-2V7h2v5z"/></svg>';
        return ['name' => $name, 'icon' => $icon, 'badge' => 'Mirror'];
    }

    // Default Fast Server
    $name = !empty($provider) ? $provider : 'Fast Server';
    $icon = '<svg class="w-4 h-4 text-[#0b57d0] shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M12 5l7 7-7 7"/></svg>';
    return ['name' => $name, 'icon' => $icon, 'badge' => 'Server'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    
    <title><?= $title ?> - <?= htmlspecialchars($site_name) ?></title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&family=JetBrains+Mono:wght@400;500;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>

    <?php if (!empty($ad_settings['adsense_auto_enabled']) && !empty($ad_settings['adsense_client_id'])): 
        $ad_client = trim($ad_settings['adsense_client_id']);
        if (strpos($ad_client, '<script') !== false): ?>
            <?= $ad_client ?>
        <?php else: ?>
            <script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=<?= htmlspecialchars($ad_client) ?>" crossorigin="anonymous"></script>
        <?php endif; ?>
    <?php endif; ?>
    
    <style>
        * { -webkit-tap-highlight-color: transparent; }
        body {
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: <?= $t['bg'] ?>;
            color: #1f1f1f;
        }
        .font-mono { font-family: 'JetBrains Mono', monospace; }
        .theme-card {
            background-color: <?= $t['card'] ?>;
            border-color: <?= $t['border'] ?>;
        }
    </style>
</head>
<body class="min-h-screen flex flex-col antialiased selection:bg-[#d3e3fd] selection:text-[#041e49]">
    <?php if ($is_admin): ?>
    <!-- Admin Quick Controls Bar (Visible only when logged in as admin - Light Theme) -->
    <div class="w-full bg-white text-[#1f1f1f] border-b border-[#e1e7f0] px-4 py-2 text-xs z-50 sticky top-0 shadow-2xs">
        <div class="max-w-4xl mx-auto flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-2">
                <span class="px-2.5 py-0.5 rounded-full bg-[#e8f0fe] text-[#0b57d0] border border-[#c2e7ff] font-bold text-[10px] uppercase tracking-wider">Admin View</span>
                <span class="text-[#5f6368] hidden sm:inline">| Page #<?= $page_id ?> (<?= intval($page['views'] ?? 0) ?> views)</span>
            </div>
            
                <div class="flex items-center gap-3">
                    <!-- Public / Private Live Toggle Switch -->
                    <div class="flex items-center gap-2">
                        <span class="text-[#444746] text-[11px] font-medium">Visibility:</span>
                        <button type="button" id="adminViewToggleBtn" onclick="toggleAdminStatus(<?= $page_id ?>)" role="switch" aria-checked="<?= $is_public ? 'true' : 'false' ?>"
                            class="relative inline-flex h-5 w-9 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none <?= $is_public ? 'bg-[#137333]' : 'bg-slate-300' ?>">
                            <span id="adminViewToggleThumb" class="pointer-events-none inline-block h-4 w-4 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out <?= $is_public ? 'translate-x-4' : 'translate-x-0' ?>"></span>
                        </button>
                        <span id="adminViewStatusBadge" class="px-2 py-0.5 rounded text-[10px] font-bold <?= $is_public ? 'bg-[#e6f4ea] text-[#137333] border border-[#a8dab5]' : 'bg-[#fff0d4] text-[#b06000] border border-[#ffd599]' ?>">
                            <?= $is_public ? 'Public' : 'Private' ?>
                        </span>
                    </div>

                    <a href="pages.php?edit=<?= $page_id ?>" class="px-2.5 py-1 rounded-lg bg-[#0b57d0] hover:bg-[#0842a0] text-white font-bold text-[11px] transition-colors inline-flex items-center gap-1 shadow-2xs">
                        <span>Edit Page</span>
                    </a>
                    <a href="pages.php" class="px-2.5 py-1 rounded-lg bg-[#f0f4f9] hover:bg-[#e1e7f0] text-[#1f1f1f] border border-[#dadce0] font-bold text-[11px] transition-colors">
                        Pages Manager
                    </a>
                </div>
            </div>
        </div>
        <?php if (!$is_public): ?>
        <!-- Warning Banner for Private Page -->
        <div id="privateNoticeBanner" class="w-full bg-[#fff4e5] border-b border-[#ffe0b2] text-[#663c00] px-4 py-2.5 text-xs text-center font-medium">
            <strong>Notice:</strong> This page is currently set to <strong>PRIVATE</strong>. Regular visitors cannot access this page and will see a 404 error. You are viewing it because you are logged in as Admin.
        </div>
        <?php endif; ?>
        <?php endif; ?>

        <!-- Public Header Navigation Bar (Google Material M3 Light Theme) -->
        <header class="w-full bg-white/95 border-b border-[#e1e7f0] sticky <?= $is_admin ? 'top-10' : 'top-0' ?> z-40 backdrop-blur-md shadow-2xs">
            <div class="max-w-4xl mx-auto px-4 sm:px-6 py-3 sm:py-3.5 flex items-center justify-between gap-3 min-w-0">
                <!-- Left Header: Mobile 3-Line Hamburger Button + Site Logo/Name -->
                <div class="flex items-center gap-3">
                    <!-- Mobile 3-Line Hamburger Button -->
                    <button type="button" id="hamburgerBtn" onclick="toggleMobileMenu()" aria-label="Toggle navigation menu"
                        class="md:hidden p-2 rounded-xl bg-[#f0f4f9] border border-[#e1e7f0] text-[#1f1f1f] hover:bg-[#e8f0fe] hover:text-[#0b57d0] focus:outline-none cursor-pointer transition-colors">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>

                    <!-- Logo (Image if available, fallback to Text Logo) -->
                    <a href="javascript:void(0)" class="flex items-center group text-decoration-none">
                        <?php if (!empty($site_logo_url)): ?>
                            <img src="<?= htmlspecialchars($site_logo_url) ?>" alt="<?= htmlspecialchars($site_name) ?>" class="h-8 max-w-[180px] object-contain">
                        <?php else: ?>
                            <span class="font-bold text-base sm:text-lg tracking-tight text-[#111827] group-hover:text-[#0b57d0] transition-colors">
                                <?= htmlspecialchars($site_name) ?>
                            </span>
                        <?php endif; ?>
                    </a>
                </div>

                <!-- Right Header: Desktop Navigation Menu Buttons (Google Material M3 Chips) -->
                <nav class="hidden md:flex flex-row items-center gap-2">
                    <?php foreach ($menu_items as $item): 
                        $m_title = htmlspecialchars($item['title'] ?? '');
                        $m_url = htmlspecialchars($item['url'] ?? '#');
                        $m_target = !empty($item['new_tab']) ? 'target="_blank" rel="noopener"' : '';
                    ?>
                        <a href="<?= $m_url ?>" <?= $m_target ?>
                            class="px-3.5 py-1.5 rounded-full text-xs font-semibold text-[#444746] hover:text-[#0b57d0] bg-[#f0f4f9] hover:bg-[#e8f0fe] border border-[#e1e7f0] hover:border-[#c2e7ff] shadow-2xs transition-all whitespace-nowrap">
                            <?= $m_title ?>
                        </a>
                    <?php endforeach; ?>
                </nav>
            </div>
        </header>

        <!-- Mobile Sidebar Drawer (Opens from Left Side, Occupies Half of the Page, Items Placed Vertically) -->
        <div id="mobileSidebarBackdrop" onclick="closeMobileMenu()" class="fixed inset-0 bg-black/40 backdrop-blur-xs z-50 hidden opacity-0 transition-opacity duration-300 md:hidden" aria-hidden="true"></div>

        <aside id="mobileSidebar" class="fixed inset-y-0 left-0 z-50 w-1/2 min-w-[250px] max-w-[340px] h-full bg-white border-r border-[#e1e7f0] shadow-2xl flex flex-col transform -translate-x-full transition-transform duration-300 ease-in-out md:hidden" aria-label="Mobile Navigation Drawer">
            <!-- Sidebar Header: Image logo if available, fallback to Text Logo -->
            <div class="p-4 border-b border-[#f0f4f9] flex items-center justify-between shrink-0">
                <div class="flex items-center min-w-0">
                    <?php if (!empty($site_logo_url)): ?>
                        <img src="<?= htmlspecialchars($site_logo_url) ?>" alt="<?= htmlspecialchars($site_name) ?>" class="h-7 max-w-[140px] object-contain">
                    <?php else: ?>
                        <span class="font-bold text-sm tracking-tight text-[#111827] truncate">
                            <?= htmlspecialchars($site_name) ?>
                        </span>
                    <?php endif; ?>
                </div>
                <button type="button" onclick="closeMobileMenu()" class="p-1.5 rounded-xl text-[#5f6368] hover:text-[#111827] hover:bg-[#f0f4f9] transition-colors cursor-pointer" aria-label="Close menu">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <!-- Vertical Menu Items (Google Material M3 Light Styling) -->
            <nav class="flex-1 overflow-y-auto p-4 flex flex-col space-y-2">
                <?php foreach ($menu_items as $item): 
                    $m_title = htmlspecialchars($item['title'] ?? '');
                    $m_url = htmlspecialchars($item['url'] ?? '#');
                    $m_target = !empty($item['new_tab']) ? 'target="_blank" rel="noopener"' : '';
                ?>
                    <a href="<?= $m_url ?>" <?= $m_target ?>
                        class="flex items-center justify-between px-4 py-3 rounded-2xl text-xs sm:text-sm font-semibold text-[#1f1f1f] hover:text-[#0b57d0] bg-[#f8fafd] hover:bg-[#e8f0fe] border border-[#e0e4eb] hover:border-[#c2e7ff] shadow-2xs transition-all">
                        <span><?= $m_title ?></span>
                        <svg class="w-4 h-4 text-[#5f6368] opacity-60" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                    </a>
                <?php endforeach; ?>
            </nav>

            <!-- Sidebar Footer -->
            <div class="p-4 border-t border-[#f0f4f9] text-[11px] text-[#747775] text-center shrink-0">
                <?= htmlspecialchars($site_name) ?>
            </div>
        </aside>

        <!-- Main Episode Content Area (Google Material M3 Light Theme) -->
        <main class="flex-1 w-full max-w-2xl mx-auto px-4 sm:px-6 py-6 sm:py-8 space-y-5">
            <?php if (!empty($ad_settings['banner_ads_enabled']) && !empty($ad_settings['ad_top_enabled']) && !empty($ad_settings['ad_top_code'])): ?>
                <!-- Top Banner Ad Placement (Hidden if not configured; shows - Advertisement - header when configured) -->
                <div class="ad-slot-container w-full text-center space-y-1 my-2">
                    <div class="ad-label text-[10px] font-semibold tracking-wider text-[#747775] uppercase select-none">- Advertisement -</div>
                    <div class="ad-content-box w-full flex justify-center items-center overflow-hidden rounded-2xl bg-white border border-[#e0e4eb] p-2">
                        <?= $ad_settings['ad_top_code'] ?>
                    </div>
                    <div class="adblock-fallback hidden w-full rounded-2xl bg-[#fff8e6] border border-[#ffe082] p-4 text-center select-none shadow-2xs">
                        <div class="flex flex-col items-center justify-center gap-1">
                            <span class="text-xs font-bold text-[#b78103] flex items-center gap-1.5">
                                ⚠️ Ad Blocker / DNS Filter Detected
                            </span>
                            <p class="text-xs text-[#7c5e10] max-w-md">
                                Please turn off your ad blocker or private DNS to keep our site free and support fast streaming links.
                            </p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Title & Information Card (Google Material M3 Card) -->
            <div class="bg-white border border-[#e0e4eb] rounded-3xl p-6 sm:p-7 text-center space-y-3 shadow-xs">
                <h1 class="text-xl sm:text-2xl font-extrabold text-[#111827] leading-snug tracking-tight">
                    <?= $title ?>
                </h1>

                <p class="text-xs sm:text-sm text-[#5f6368] max-w-lg mx-auto leading-relaxed">
                    <?= !empty($description) ? $description : 'Select your episode below to stream or download via high-speed server mirrors.' ?>
                </p>
            </div>

            <?php if (!empty($share_settings['enabled']) && !empty($share_settings['show_in_page'])): ?>
            <!-- Social Media & Mobile Display Share Card (Google Material M3 Light Theme) -->
            <div class="bg-white border border-[#e0e4eb] rounded-3xl p-4 sm:p-5 shadow-xs space-y-3">
                <div class="flex items-center justify-between gap-2 flex-wrap pb-0.5">
                    <div class="flex items-center gap-2">
                        <span class="w-7 h-7 rounded-xl bg-[#e8f0fe] text-[#0b57d0] flex items-center justify-center">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/>
                            </svg>
                        </span>
                        <span class="text-xs font-bold text-[#111827]">Share Episode Page</span>
                    </div>
                    <span class="text-[11px] text-[#747775]">Copy link or share to social apps</span>
                </div>

                <!-- Uniform Action Chips: Copy Link, WhatsApp, Telegram, Facebook, X, and QR Code -->
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-2">
                    <!-- 1. Copy Link Button (Instant Feedback) -->
                    <button type="button" onclick="copyPageShareUrl(this)" id="copyShareBtn"
                        class="flex items-center justify-center gap-2 px-3 py-2.5 rounded-2xl bg-[#f0f4f9] hover:bg-[#e8f0fe] text-[#0b57d0] border border-[#d3e3fd] hover:border-[#0b57d0] text-xs font-bold transition-all shadow-2xs cursor-pointer select-none active:scale-95 group">
                        <svg class="w-4 h-4 shrink-0 transition-transform group-hover:scale-110" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                        </svg>
                        <span class="share-btn-text truncate">Copy Link</span>
                    </button>

                    <!-- 2. WhatsApp Share -->
                    <a href="https://api.whatsapp.com/send?text=<?= $share_encoded_msg ?>" target="_blank" rel="noopener noreferrer"
                        class="flex items-center justify-center gap-2 px-3 py-2.5 rounded-2xl bg-[#e6f4ea] hover:bg-[#ceead6] text-[#137333] border border-[#a8dab5] text-xs font-bold transition-all shadow-2xs cursor-pointer select-none active:scale-95 group">
                        <svg class="w-4 h-4 shrink-0 fill-current" viewBox="0 0 24 24">
                            <path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/>
                        </svg>
                        <span class="truncate">WhatsApp</span>
                    </a>

                    <!-- 3. Telegram Share -->
                    <a href="https://t.me/share/url?url=<?= $share_encoded_url ?>&text=<?= $share_encoded_title ?>" target="_blank" rel="noopener noreferrer"
                        class="flex items-center justify-center gap-2 px-3 py-2.5 rounded-2xl bg-[#e8f4fd] hover:bg-[#d0ebfc] text-[#0088cc] border border-[#b8e1fa] text-xs font-bold transition-all shadow-2xs cursor-pointer select-none active:scale-95 group">
                        <svg class="w-4 h-4 shrink-0 fill-current" viewBox="0 0 24 24">
                            <path d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z"/>
                        </svg>
                        <span class="truncate">Telegram</span>
                    </a>

                    <!-- 4. Facebook Share -->
                    <a href="https://www.facebook.com/sharer/sharer.php?u=<?= $share_encoded_url ?>" target="_blank" rel="noopener noreferrer"
                        class="flex items-center justify-center gap-2 px-3 py-2.5 rounded-2xl bg-[#ebf3ff] hover:bg-[#dbeafe] text-[#1877f2] border border-[#bfdbfe] text-xs font-bold transition-all shadow-2xs cursor-pointer select-none active:scale-95 group">
                        <svg class="w-4 h-4 shrink-0 fill-current" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                        <span class="truncate">Facebook</span>
                    </a>

                    <!-- 5. X / Twitter Share -->
                    <a href="https://twitter.com/intent/tweet?text=<?= $share_encoded_title ?>&url=<?= $share_encoded_url ?>" target="_blank" rel="noopener noreferrer"
                        class="flex items-center justify-center gap-2 px-3 py-2.5 rounded-2xl bg-[#f3f4f6] hover:bg-[#e5e7eb] text-[#111827] border border-[#d1d5db] text-xs font-bold transition-all shadow-2xs cursor-pointer select-none active:scale-95 group">
                        <svg class="w-3.5 h-3.5 shrink-0 fill-current" viewBox="0 0 24 24"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
                        <span class="truncate">X / Tweet</span>
                    </a>

                    <!-- 6. QR Code Button -->
                    <button type="button" onclick="openQrModal()"
                        class="flex items-center justify-center gap-2 px-3 py-2.5 rounded-2xl bg-[#fdf4ff] hover:bg-[#fae8ff] text-[#9333ea] border border-[#f0abfc] text-xs font-bold transition-all shadow-2xs cursor-pointer select-none active:scale-95 group">
                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/></svg>
                        <span class="truncate">QR Code</span>
                    </button>
                </div>
            </div>
            <?php endif; ?>

        <?php if (!empty($ad_settings['banner_ads_enabled']) && !empty($ad_settings['ad_middle_enabled']) && !empty($ad_settings['ad_middle_code'])): ?>
            <!-- Middle Banner Ad Placement (Hidden if not configured; shows - Advertisement - header when configured) -->
            <div class="ad-slot-container w-full text-center space-y-1">
                <div class="ad-label text-[10px] font-semibold tracking-wider text-[#747775] uppercase select-none">- Advertisement -</div>
                <div class="ad-content-box w-full flex justify-center items-center overflow-hidden rounded-2xl bg-white border border-[#e0e4eb] p-2">
                    <?= $ad_settings['ad_middle_code'] ?>
                </div>
                <div class="adblock-fallback hidden w-full rounded-2xl bg-[#fff8e6] border border-[#ffe082] p-4 text-center select-none shadow-2xs">
                    <div class="flex flex-col items-center justify-center gap-1">
                        <span class="text-xs font-bold text-[#b78103] flex items-center gap-1.5">
                            ⚠️ Ad Blocker / DNS Filter Detected
                        </span>
                        <p class="text-xs text-[#7c5e10] max-w-md">
                            Please turn off your ad blocker or private DNS to keep our site free and support fast streaming links.
                        </p>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Episode Buttons List (Server Icon, Episode 01/02.., Watch Now Button) -->
        <div class="space-y-2.5">
            <?php if (empty($buttons)): ?>
                <div class="bg-white border border-[#e0e4eb] rounded-2xl p-8 text-center text-xs text-[#747775]">
                    No episode download buttons currently available for this title.
                </div>
            <?php else: ?>
                <?php foreach ($buttons as $idx => $btn): 
                    $btn_text = htmlspecialchars($btn['text'] ?? ('Episode ' . ($idx + 1)));
                    $btn_url = htmlspecialchars($btn['url'] ?? '#');
                    $btn_quality = htmlspecialchars($btn['quality'] ?? 'HD');
                    $raw_provider = $btn['provider'] ?? '';
                    $raw_ep = $btn['episode'] ?? ($idx + 1);
                    $click_count = intval($btn['clicks'] ?? 0);

                    // Episode 01, Episode 02... formatting (padded to 2 digits)
                    $ep_num_padded = is_numeric($raw_ep) ? str_pad(intval($raw_ep), 2, '0', STR_PAD_LEFT) : $raw_ep;
                    $ep_label = 'Episode ' . $ep_num_padded;

                    // Server icon and provider name resolution
                    $server_info = resolve_server_info($raw_provider, $btn_url, $btn_text);
                ?>
                <div class="bg-white hover:bg-[#fafcff] border border-[#e0e4eb] hover:border-[#0b57d0] rounded-2xl p-3.5 sm:p-4 flex items-center justify-between gap-3 transition-all shadow-2xs group">
                    
                    <!-- Left Column: Server Icon, Episode 01/02.., Provider & Quality Tags -->
                    <div class="flex items-center gap-3 min-w-0 flex-1">
                        <!-- Episode Pill -->
                        <div class="w-10 h-10 rounded-xl bg-[#e8f0fe] text-[#0b57d0] flex items-center justify-center font-extrabold text-xs shrink-0 group-hover:bg-[#0b57d0] group-hover:text-white transition-colors shadow-2xs">
                            E<?= $ep_num_padded ?>
                        </div>

                        <!-- Info Column -->
                        <div class="min-w-0 flex-1">
                            <div class="text-sm font-bold text-[#111827] truncate group-hover:text-[#0b57d0] transition-colors flex items-center gap-1.5 flex-wrap">
                                <span><?= $ep_label ?></span>
                                <?php if (!empty($btn['text']) && stripos($btn['text'], 'Episode') === false): ?>
                                    <span class="text-xs text-[#747775] font-normal truncate hidden sm:inline">(<?= $btn_text ?>)</span>
                                <?php endif; ?>


                            </div>

                            <!-- Server Provider & Quality Info with Server Icon -->
                            <div class="flex items-center gap-2 text-[11px] text-[#5f6368] mt-0.5">
                                <div class="inline-flex items-center gap-1 font-medium">
                                    <?= $server_info['icon'] ?>
                                    <span><?= htmlspecialchars($server_info['name']) ?></span>
                                </div>
                                <span class="px-2 py-0.5 rounded-md bg-[#f1f3f4] text-[#3c4043] font-mono font-bold text-[10px] border border-[#e0e4eb]">
                                    <?= $btn_quality ?>
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column: "Watch Now" Button (Google Material M3 Rounded Pill) -->
                    <div class="shrink-0 flex items-center gap-2">
                        <a href="<?= $btn_url ?>" target="_blank" rel="noopener noreferrer nofollow"
                            class="px-4 py-2 rounded-full bg-[#0b57d0] hover:bg-[#0842a0] text-white font-bold text-xs flex items-center gap-1.5 shadow-xs hover:shadow-md transition-all cursor-pointer">
                            <!-- Play Triangle Icon for Watch Now -->
                            <svg class="w-3.5 h-3.5 fill-current" viewBox="0 0 24 24">
                                <path d="M8 5v14l11-7z" />
                            </svg>
                            <span>Watch Now</span>
                        </a>
                    </div>
                </div>

                <?php 
                $has_middle_ad = (!empty($ad_settings['banner_ads_enabled']) && !empty($ad_settings['ad_middle_enabled']) && !empty($ad_settings['ad_middle_code'])) || (!empty($ad_settings['adsense_auto_enabled']) && !empty($ad_settings['adsense_client_id']));
                if (($idx + 1) % 4 === 0 && $idx < count($buttons) - 1 && $has_middle_ad): 
                ?>
                    <!-- In-Feed Responsive Banner Ad space after every 4 buttons (Hidden if not configured; shows - Advertisement - header when configured) -->
                    <div class="ad-slot-container my-3 w-full text-center space-y-1">
                        <div class="ad-label text-[10px] font-semibold tracking-wider text-[#747775] uppercase select-none">- Advertisement -</div>
                        <div class="ad-content-box overflow-hidden rounded-2xl bg-white border border-[#e0e4eb] p-2 text-center">
                            <?php if (!empty($ad_settings['banner_ads_enabled']) && !empty($ad_settings['ad_middle_enabled']) && !empty($ad_settings['ad_middle_code'])): ?>
                                <div class="w-full flex justify-center items-center">
                                    <?= $ad_settings['ad_middle_code'] ?>
                                </div>
                            <?php elseif (!empty($ad_settings['adsense_auto_enabled']) && !empty($ad_settings['adsense_client_id'])): ?>
                                <div class="py-3 text-center space-y-1">
                                    <ins class="adsbygoogle"
                                         style="display:block; width:100%;"
                                         data-ad-client="<?= htmlspecialchars($ad_settings['adsense_client_id']) ?>"
                                         data-ad-format="auto"
                                         data-full-width-responsive="true"></ins>
                                    <script>(adsbygoogle = window.adsbygoogle || []).push({});</script>
                                    <span class="text-[10px] font-bold tracking-wider text-[#0b57d0] uppercase bg-[#e8f0fe] px-2 py-0.5 rounded">
                                        AdSense Auto Ad
                                    </span>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="adblock-fallback hidden w-full rounded-2xl bg-[#fff8e6] border border-[#ffe082] p-4 text-center select-none shadow-2xs">
                            <div class="flex flex-col items-center justify-center gap-1">
                                <span class="text-xs font-bold text-[#b78103] flex items-center gap-1.5">
                                    ⚠️ Ad Blocker / DNS Filter Detected
                                </span>
                                <p class="text-xs text-[#7c5e10] max-w-md">
                                    Please turn off your ad blocker or private DNS to keep our site free and support fast streaming links.
                                </p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <?php if (!empty($ad_settings['banner_ads_enabled']) && !empty($ad_settings['ad_bottom_enabled']) && !empty($ad_settings['ad_bottom_code'])): ?>
            <!-- Bottom Banner Ad Placement (Hidden if not configured; shows - Advertisement - header when configured) -->
            <div class="ad-slot-container w-full text-center space-y-1 my-2">
                <div class="ad-label text-[10px] font-semibold tracking-wider text-[#747775] uppercase select-none">- Advertisement -</div>
                <div class="ad-content-box w-full flex justify-center items-center overflow-hidden rounded-2xl bg-white border border-[#e0e4eb] p-2">
                    <?= $ad_settings['ad_bottom_code'] ?>
                </div>
                <div class="adblock-fallback hidden w-full rounded-2xl bg-[#fff8e6] border border-[#ffe082] p-4 text-center select-none shadow-2xs">
                    <div class="flex flex-col items-center justify-center gap-1">
                        <span class="text-xs font-bold text-[#b78103] flex items-center gap-1.5">
                            ⚠️ Ad Blocker / DNS Filter Detected
                        </span>
                        <p class="text-xs text-[#7c5e10] max-w-md">
                            Please turn off your ad blocker or private DNS to keep our site free and support fast streaming links.
                        </p>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </main>

    <!-- Public Footer (Google Material M3 Light Theme) with Admin-Configurable Copyright -->
    <footer class="w-full bg-white border-t border-[#e1e7f0] mt-auto py-6 px-4">
        <div class="max-w-4xl mx-auto text-center text-xs text-[#5f6368]">
            <!-- Custom HTML Footer rendered directly as configured by admin in /settings -->
            <div class="leading-relaxed">
                <?= $footer_copyright ?>
            </div>
        </div>
    </footer>

    <!-- Top Right Notification Toast Container -->
    <div id="toastContainer" class="fixed top-5 right-5 z-50 flex flex-col items-end gap-3 pointer-events-none max-w-sm w-full"></div>

    <?php if (!empty($share_settings['enabled']) && !empty($share_settings['show_mobile_fab'])): ?>
    <!-- Floating Mobile Share Button (Decreased FAB icon size for optimal ergonomics) -->
    <div class="fixed bottom-5 right-4 z-40 sm:hidden">
        <button type="button" onclick="handleMobileFabShare()" aria-label="Share page"
            class="w-13 h-13 rounded-full bg-[#0b57d0] hover:bg-[#0842a0] text-white flex items-center justify-center shadow-xl transition-all active:scale-95 border-2 border-white ring-4 ring-[#0b57d0]/20 cursor-pointer">
            <svg class="w-5.5 h-5.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/>
            </svg>
        </button>
    </div>
    <?php endif; ?>

    <!-- QR Code Display Modal -->
    <div id="qrModal" class="fixed inset-0 bg-black/60 backdrop-blur-xs z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-3xl p-6 max-w-xs w-full text-center space-y-4 shadow-2xl border border-[#e0e4eb] transform transition-all scale-95 opacity-0 duration-200" id="qrModalCard">
            <div class="flex items-center justify-between pb-2 border-b border-[#f0f4f9]">
                <div class="flex items-center gap-2">
                    <span class="w-6 h-6 rounded-lg bg-[#e8f0fe] text-[#0b57d0] flex items-center justify-center">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/></svg>
                    </span>
                    <span class="text-xs font-bold text-[#111827]">Scan to Open</span>
                </div>
                <button type="button" onclick="closeQrModal()" class="p-1 rounded-lg text-[#5f6368] hover:text-[#111827] hover:bg-[#f0f4f9] transition-colors cursor-pointer" aria-label="Close">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <!-- Dynamic QR Code Container -->
            <div class="flex justify-center p-3 bg-[#f8fafd] rounded-2xl border border-[#e0e4eb]">
                <img id="qrCodeImg" src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=<?= $share_encoded_url ?>" alt="QR Code" class="w-48 h-48 rounded-lg">
            </div>

            <p class="text-[11px] text-[#5f6368] leading-tight">
                Scan with any mobile camera or scanner app to open this page instantly.
            </p>

            <button type="button" onclick="copyPageShareUrl(this)" class="w-full py-2.5 rounded-full bg-[#0b57d0] hover:bg-[#0842a0] text-white font-bold text-xs shadow-xs transition-all cursor-pointer">
                Copy Link to Clipboard
            </button>
        </div>
    </div>

    <!-- Mobile Share Sheet Bottom Drawer -->
    <div id="mobileShareSheetBackdrop" onclick="closeMobileShareSheet()" class="fixed inset-0 bg-black/50 backdrop-blur-xs z-50 hidden opacity-0 transition-opacity duration-300 md:hidden"></div>
    <div id="mobileShareSheet" class="fixed inset-x-0 bottom-0 z-50 bg-white rounded-t-3xl border-t border-[#e0e4eb] p-5 shadow-2xl space-y-4 transform translate-y-full transition-transform duration-300 ease-in-out md:hidden">
        <div class="flex items-center justify-between pb-2 border-b border-[#f0f4f9]">
            <div class="flex items-center gap-2">
                <span class="w-7 h-7 rounded-xl bg-[#e8f0fe] text-[#0b57d0] flex items-center justify-center">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/></svg>
                </span>
                <span class="text-sm font-bold text-[#111827]">Share Episode Link</span>
            </div>
            <button type="button" onclick="closeMobileShareSheet()" class="p-1.5 rounded-xl text-[#5f6368] hover:bg-[#f0f4f9] transition-colors cursor-pointer" aria-label="Close">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <div class="grid grid-cols-4 gap-2 text-center text-xs">
            <!-- Native Share / System Share -->
            <button type="button" onclick="triggerNativeShare()" class="flex flex-col items-center gap-1.5 p-2 rounded-2xl bg-[#f0f4f9] hover:bg-[#e8f0fe] text-[#0b57d0] transition-colors cursor-pointer">
                <div class="w-10 h-10 rounded-full bg-[#0b57d0] text-white flex items-center justify-center shadow-xs">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/></svg>
                </div>
                <span class="text-[11px] font-semibold text-[#1f1f1f]">More Apps</span>
            </button>

            <!-- WhatsApp -->
            <a href="https://api.whatsapp.com/send?text=<?= $share_encoded_msg ?>" target="_blank" rel="noopener noreferrer"
                class="flex flex-col items-center gap-1.5 p-2 rounded-2xl bg-[#f0f4f9] hover:bg-[#e6f4ea] text-[#137333] transition-colors cursor-pointer">
                <div class="w-10 h-10 rounded-full bg-[#25d366] text-white flex items-center justify-center shadow-xs">
                    <svg class="w-5 h-5 fill-current" viewBox="0 0 24 24"><path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/></svg>
                </div>
                <span class="text-[11px] font-semibold text-[#1f1f1f]">WhatsApp</span>
            </a>

            <!-- Telegram -->
            <a href="https://t.me/share/url?url=<?= $share_encoded_url ?>&text=<?= $share_encoded_title ?>" target="_blank" rel="noopener noreferrer"
                class="flex flex-col items-center gap-1.5 p-2 rounded-2xl bg-[#f0f4f9] hover:bg-[#e8f4fd] text-[#0088cc] transition-colors cursor-pointer">
                <div class="w-10 h-10 rounded-full bg-[#0088cc] text-white flex items-center justify-center shadow-xs">
                    <svg class="w-5 h-5 fill-current" viewBox="0 0 24 24"><path d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z"/></svg>
                </div>
                <span class="text-[11px] font-semibold text-[#1f1f1f]">Telegram</span>
            </a>

            <!-- Facebook -->
            <a href="https://www.facebook.com/sharer/sharer.php?u=<?= $share_encoded_url ?>" target="_blank" rel="noopener noreferrer"
                class="flex flex-col items-center gap-1.5 p-2 rounded-2xl bg-[#f0f4f9] hover:bg-[#e4eaf2] text-[#1877f2] transition-colors cursor-pointer">
                <div class="w-10 h-10 rounded-full bg-[#1877f2] text-white flex items-center justify-center shadow-xs">
                    <svg class="w-5 h-5 fill-current" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                </div>
                <span class="text-[11px] font-semibold text-[#1f1f1f]">Facebook</span>
            </a>
        </div>

        <button type="button" onclick="copyPageShareUrl(this)" class="w-full py-3 rounded-2xl bg-[#f0f4f9] hover:bg-[#e8f0fe] text-[#0b57d0] font-bold text-xs flex items-center justify-center gap-2 border border-[#d3e3fd] transition-all cursor-pointer">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
            <span class="share-btn-text">Copy Link</span>
        </button>
    </div>

    <script>
        const PAGE_SHARE_URL = <?= json_encode($canonical_share_url) ?>;
        const PAGE_SHARE_TITLE = <?= json_encode($page['title'] ?? 'Watch & Download Episodes') ?>;

        function copyPageShareUrl(btn) {
            const url = PAGE_SHARE_URL || window.location.href;
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(url).then(() => {
                    handleCopySuccess(btn);
                }).catch(() => {
                    fallbackCopy(url, btn);
                });
            } else {
                fallbackCopy(url, btn);
            }
        }

        function fallbackCopy(text, btn) {
            const temp = document.createElement('input');
            temp.value = text;
            document.body.appendChild(temp);
            temp.select();
            try {
                document.execCommand('copy');
                handleCopySuccess(btn);
            } catch (err) {
                showToast('Please copy URL manually from browser address bar', 'info');
            }
            document.body.removeChild(temp);
        }

        function handleCopySuccess(btn) {
            showToast('Episode link copied to clipboard!', 'success');
            if (btn) {
                const textSpan = btn.querySelector('.share-btn-text');
                if (textSpan) {
                    const original = textSpan.innerText;
                    textSpan.innerText = '✓ Copied!';
                    setTimeout(() => {
                        textSpan.innerText = original;
                    }, 2500);
                }
            }
        }

        function triggerNativeShare() {
            const url = PAGE_SHARE_URL || window.location.href;
            const title = PAGE_SHARE_TITLE || document.title;
            if (navigator.share) {
                navigator.share({
                    title: title,
                    text: 'Watch & Download: ' + title,
                    url: url
                }).catch((err) => {
                    if (err.name !== 'AbortError') {
                        copyPageShareUrl(null);
                    }
                });
            } else {
                openMobileShareSheet();
            }
        }

        function handleMobileFabShare() {
            if (navigator.share) {
                triggerNativeShare();
            } else {
                openMobileShareSheet();
            }
        }

        function openQrModal() {
            const modal = document.getElementById('qrModal');
            const card = document.getElementById('qrModalCard');
            if (!modal || !card) return;
            modal.classList.remove('hidden');
            setTimeout(() => {
                card.classList.remove('scale-95', 'opacity-0');
                card.classList.add('scale-100', 'opacity-100');
            }, 10);
        }

        function closeQrModal() {
            const modal = document.getElementById('qrModal');
            const card = document.getElementById('qrModalCard');
            if (!modal || !card) return;
            card.classList.remove('scale-100', 'opacity-100');
            card.classList.add('scale-95', 'opacity-0');
            setTimeout(() => modal.classList.add('hidden'), 200);
        }

        function openMobileShareSheet() {
            const sheet = document.getElementById('mobileShareSheet');
            const backdrop = document.getElementById('mobileShareSheetBackdrop');
            if (!sheet || !backdrop) return;
            backdrop.classList.remove('hidden');
            void backdrop.offsetWidth;
            backdrop.classList.remove('opacity-0');
            backdrop.classList.add('opacity-100');

            sheet.classList.remove('translate-y-full');
            sheet.classList.add('translate-y-0');
        }

        function closeMobileShareSheet() {
            const sheet = document.getElementById('mobileShareSheet');
            const backdrop = document.getElementById('mobileShareSheetBackdrop');
            if (!sheet || !backdrop) return;
            sheet.classList.remove('translate-y-0');
            sheet.classList.add('translate-y-full');

            backdrop.classList.remove('opacity-100');
            backdrop.classList.add('opacity-0');
            setTimeout(() => backdrop.classList.add('hidden'), 300);
        }

        function openMobileMenu() {
            const sidebar = document.getElementById('mobileSidebar');
            const backdrop = document.getElementById('mobileSidebarBackdrop');
            if (!sidebar || !backdrop) return;

            backdrop.classList.remove('hidden');
            void backdrop.offsetWidth;
            backdrop.classList.remove('opacity-0');
            backdrop.classList.add('opacity-100');

            sidebar.classList.remove('-translate-x-full');
            sidebar.classList.add('translate-x-0');
            document.body.style.overflow = 'hidden';
        }

        function closeMobileMenu() {
            const sidebar = document.getElementById('mobileSidebar');
            const backdrop = document.getElementById('mobileSidebarBackdrop');
            if (!sidebar || !backdrop) return;

            sidebar.classList.remove('translate-x-0');
            sidebar.classList.add('-translate-x-full');

            backdrop.classList.remove('opacity-100');
            backdrop.classList.add('opacity-0');

            setTimeout(() => {
                backdrop.classList.add('hidden');
                document.body.style.overflow = '';
            }, 300);
        }

        function toggleMobileMenu() {
            const sidebar = document.getElementById('mobileSidebar');
            if (sidebar && sidebar.classList.contains('translate-x-0')) {
                closeMobileMenu();
            } else {
                openMobileMenu();
            }
        }

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeMobileMenu();
            }
        });

        function escapeToastHtml(str) {
            return String(str || '').replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/'/g, '&#039;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
        }

        function showToast(msg, type = 'success') {
            const container = document.getElementById('toastContainer');
            if (!container) return;

            const id = 'toast_' + Math.random().toString(36).substring(2, 9);
            const toast = document.createElement('div');
            toast.id = id;
            toast.className = `pointer-events-auto bg-white rounded-2xl shadow-xl border p-3.5 flex items-start gap-3 w-full ring-1 transform transition-all duration-300 translate-x-8 opacity-0 scale-95 ${
                type === 'success' 
                    ? 'border-emerald-200 ring-emerald-500/10' 
                    : (type === 'info' ? 'border-blue-200 ring-blue-500/10' : 'border-rose-200 ring-rose-500/10')
            }`;

            const iconClass = type === 'success'
                ? 'bg-emerald-50 text-emerald-600'
                : (type === 'info' ? 'bg-blue-50 text-blue-600' : 'bg-rose-50 text-rose-600');

            const iconSvg = type === 'success'
                ? `<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>`
                : (type === 'info'
                    ? `<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke-width="2"></circle><line x1="12" y1="8" x2="12" y2="12" stroke-width="2"></line><line x1="12" y1="16" x2="12.01" y2="16" stroke-width="2"></line></svg>`
                    : `<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>`);

            const title = type === 'success' ? 'Success' : (type === 'info' ? 'Information' : 'Notice');

            toast.innerHTML = `
                <div class="p-2 rounded-xl shrink-0 mt-0.5 ${iconClass}">
                    ${iconSvg}
                </div>
                <div class="flex-1 min-w-0 pr-1">
                    <div class="text-[11px] font-bold uppercase tracking-wider text-slate-500">${title}</div>
                    <div class="text-xs font-medium text-slate-800 leading-snug mt-0.5 break-words">${escapeToastHtml(msg)}</div>
                </div>
                <button type="button" onclick="document.getElementById('${id}').remove()" class="p-1 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-100 shrink-0 transition-colors cursor-pointer" title="Dismiss">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            `;

            container.appendChild(toast);
            requestAnimationFrame(() => {
                toast.classList.remove('translate-x-8', 'opacity-0', 'scale-95');
                toast.classList.add('translate-x-0', 'opacity-100', 'scale-100');
            });

            setTimeout(() => {
                const el = document.getElementById(id);
                if (el) {
                    el.classList.remove('translate-x-0', 'opacity-100', 'scale-100');
                    el.classList.add('translate-x-8', 'opacity-0', 'scale-95');
                    setTimeout(() => el.remove(), 250);
                }
            }, 4500);
        }

        <?php if ($is_admin): ?>
        async function toggleAdminStatus(id) {
            const btn = document.getElementById('adminViewToggleBtn');
            const thumb = document.getElementById('adminViewToggleThumb');
            const badge = document.getElementById('adminViewStatusBadge');
            const banner = document.getElementById('privateNoticeBanner');

            try {
                const res = await fetch('api.php?action=toggle_page_status', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: id })
                });
                const data = await res.json();
                if (data.success && data.page) {
                    const isPub = Number(data.page.is_public) === 1;
                    btn.setAttribute('aria-checked', isPub ? 'true' : 'false');
                    if (isPub) {
                        btn.className = 'relative inline-flex h-5 w-9 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none bg-[#137333]';
                        thumb.className = 'pointer-events-none inline-block h-4 w-4 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out translate-x-4';
                        badge.className = 'px-2 py-0.5 rounded text-[10px] font-bold bg-[#e6f4ea] text-[#137333] border border-[#a8dab5]';
                        badge.innerText = 'Public';
                        if (banner) banner.classList.add('hidden');
                        showToast('Page visibility changed to Public', 'success');
                    } else {
                        btn.className = 'relative inline-flex h-5 w-9 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none bg-slate-300';
                        thumb.className = 'pointer-events-none inline-block h-4 w-4 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out translate-x-0';
                        badge.className = 'px-2 py-0.5 rounded text-[10px] font-bold bg-[#fff0d4] text-[#b06000] border border-[#ffd599]';
                        badge.innerText = 'Private';
                        if (banner) banner.classList.remove('hidden');
                        showToast('Page visibility changed to Private', 'info');
                    }
                } else {
                    showToast('Could not update status: ' + (data.error || 'Unknown error'), 'error');
                }
            } catch (err) {
                showToast('Request failed: ' + err.message, 'error');
            }
        }
        <?php endif; ?>

        // -------------------------------------------------------------
        // Universal AdBlocker & Private DNS Detection
        // -------------------------------------------------------------
        (function initAdBlockerDetection() {
            function handleAdBlockDetected() {
                const slots = document.querySelectorAll('.ad-slot-container');
                slots.forEach(function(slot) {
                    const contentBox = slot.querySelector('.ad-content-box');
                    const fallback = slot.querySelector('.adblock-fallback');
                    if (contentBox) contentBox.style.display = 'none';
                    if (fallback) fallback.classList.remove('hidden');
                });
            }

            // 1. Bait element test
            const bait = document.createElement('div');
            bait.className = 'adsbox pub_300x250 pub_728x90 text-ad textAd text_ad text-ads ad-banner adsbygoogle';
            bait.style.cssText = 'position:absolute;top:-9999px;left:-9999px;width:1px;height:1px;pointer-events:none;';
            document.body.appendChild(bait);

            setTimeout(function() {
                let blocked = false;
                if (!bait || bait.offsetParent === null || bait.offsetHeight === 0 || bait.offsetLeft === 0) {
                    blocked = true;
                } else {
                    const style = window.getComputedStyle(bait);
                    if (style.display === 'none' || style.visibility === 'hidden') {
                        blocked = true;
                    }
                }

                if (bait && bait.parentNode) {
                    bait.parentNode.removeChild(bait);
                }

                if (blocked) {
                    handleAdBlockDetected();
                    return;
                }

                // 2. Network / DNS test to standard ad script endpoint
                fetch('https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js', {
                    method: 'HEAD',
                    mode: 'no-cors',
                    cache: 'no-store'
                }).catch(function() {
                    handleAdBlockDetected();
                });
            }, 100);
        })();
    </script>
</body>
</html>
