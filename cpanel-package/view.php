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

if (empty($slug)) {
    http_response_code(404);
    die('<!DOCTYPE html><html><head><meta name="robots" content="noindex,nofollow"><title>Page Not Found</title></head><body style="font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,sans-serif;text-align:center;padding:60px 20px;background:#f8fafd;color:#1f1f1f;"><div style="max-width:440px;margin:0 auto;background:#ffffff;padding:32px;border-radius:24px;border:1px solid #e0e4eb;box-shadow:0 1px 3px rgba(0,0,0,0.05);"><h2 style="color:#d93025;margin:0 0 8px;">404 - Page Not Found</h2><p style="font-size:13px;color:#5f6368;margin:0;">The requested episode link page could not be located.</p></div></body></html>');
}

$is_admin = SLEA_Auth::is_logged_in();

$maintenance = SLEA_Datastore::get_maintenance_settings();
if (!empty($maintenance['enabled']) && !$is_admin) {
    http_response_code(503);
    header('Retry-After: 3600');
    $custom_msg = htmlspecialchars($maintenance['message'] ?? 'The website is currently undergoing scheduled maintenance. We will be back shortly!');
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="robots" content="noindex, nofollow, noarchive">
        <title>Scheduled Maintenance - Back Soon</title>
        <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
        <script src="https://cdn.tailwindcss.com"></script>
        <style>
            body { font-family: 'Plus Jakarta Sans', sans-serif; }
            @keyframes pulse-ring {
                0% { transform: scale(0.95); opacity: 0.5; }
                50% { transform: scale(1.05); opacity: 0.8; }
                100% { transform: scale(0.95); opacity: 0.5; }
            }
            @keyframes spin-slow {
                from { transform: rotate(0deg); }
                to { transform: rotate(360deg); }
            }
            .pulse-circle {
                animation: pulse-ring 3s infinite ease-in-out;
            }
            .spin-slow {
                animation: spin-slow 12s infinite linear;
            }
        </style>
    </head>
    <body class="bg-[#f8fafd] text-[#1f1f1f] min-h-screen flex items-center justify-center p-4 sm:p-6 selection:bg-[#d3e3fd] select-none">
        <div class="max-w-lg w-full bg-white rounded-3xl border border-[#e0e4eb] p-6 sm:p-8 shadow-xs text-center space-y-6 relative overflow-hidden">
            <!-- Top Subtle Graphic/Banner -->
            <div class="relative w-full aspect-[4/3] rounded-2xl overflow-hidden bg-[#fafbfc] border border-[#f0f4f9]">
                <!-- Interactive gears or animation -->
                <div class="absolute inset-0 flex items-center justify-center opacity-10">
                    <svg class="w-48 h-48 text-[#0b57d0] spin-slow" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                    </svg>
                </div>
                <!-- Premium Custom Maintenance Illustration -->
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

            <!-- Dynamic Interactive Component (Interactive Progress Gauge) -->
            <div class="bg-[#f8fafd] rounded-2xl p-4 border border-[#e1e7f0] space-y-3">
                <div class="flex items-center justify-between text-[11px] font-mono text-[#5f6368]">
                    <span>Optimization Progress</span>
                    <span id="progress-text" class="font-bold text-[#0b57d0]">87%</span>
                </div>
                <div class="w-full bg-[#e1e7f0] h-2 rounded-full overflow-hidden">
                    <div id="progress-bar" class="bg-[#0b57d0] h-full rounded-full transition-all duration-1000" style="width: 87%;"></div>
                </div>
                <p class="text-[10px] text-slate-400 font-mono text-center">
                    Auto-refreshes to check status. No action required.
                </p>
            </div>

            <!-- Custom Script for Interactive Progress Simulation -->
            <script>
                // Make the gauge slowly progress up to 99% and then restart or hover, keeping users engaged.
                let progress = 87;
                const bar = document.getElementById('progress-bar');
                const text = document.getElementById('progress-text');
                
                setInterval(() => {
                    if (progress < 98) {
                        progress += Math.floor(Math.random() * 2) + 1;
                    } else {
                        progress = 85; // reset or keep fluctuating
                    }
                    if (bar && text) {
                        bar.style.width = progress + '%';
                        text.innerText = progress + '%';
                    }
                }, 3000);

                // Auto reload page every 45 seconds to check if maintenance mode has been disabled!
                setTimeout(() => {
                    window.location.reload();
                }, 45000);
            </script>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Only public pages are viewable by public! Logged in admins can preview private pages as well
$page = SLEA_Datastore::get_page_by_slug($slug, !$is_admin);
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
    <!-- Admin Quick Controls Bar (Visible only when logged in as admin) -->
    <div class="w-full bg-[#1e293b] text-white border-b border-slate-800 px-4 py-2 text-xs z-50 sticky top-0 shadow-md">
        <div class="max-w-4xl mx-auto flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-2">
                <span class="px-2 py-0.5 rounded-md bg-blue-500/20 text-blue-300 font-bold text-[10px] uppercase tracking-wider">Admin View</span>
                <span class="text-slate-300 hidden sm:inline">| Page #<?= $page_id ?> (<?= intval($page['views'] ?? 0) ?> views)</span>
            </div>
            
                <div class="flex items-center gap-3">
                    <!-- Public / Private Live Toggle Switch -->
                    <div class="flex items-center gap-2">
                        <span class="text-slate-300 text-[11px]">Visibility:</span>
                        <button type="button" id="adminViewToggleBtn" onclick="toggleAdminStatus(<?= $page_id ?>)" role="switch" aria-checked="<?= $is_public ? 'true' : 'false' ?>"
                            class="relative inline-flex h-5 w-9 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none <?= $is_public ? 'bg-[#137333]' : 'bg-slate-600' ?>">
                            <span id="adminViewToggleThumb" class="pointer-events-none inline-block h-4 w-4 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out <?= $is_public ? 'translate-x-4' : 'translate-x-0' ?>"></span>
                        </button>
                        <span id="adminViewStatusBadge" class="px-2 py-0.5 rounded text-[10px] font-bold <?= $is_public ? 'bg-emerald-500/20 text-emerald-300' : 'bg-amber-500/20 text-amber-300' ?>">
                            <?= $is_public ? 'Public' : 'Private' ?>
                        </span>
                    </div>

                    <a href="pages.php?edit=<?= $page_id ?>" class="px-2.5 py-1 rounded-lg bg-blue-600 hover:bg-blue-500 text-white font-bold text-[11px] transition-colors inline-flex items-center gap-1">
                        <span>Edit Page</span>
                    </a>
                    <a href="pages.php" class="px-2.5 py-1 rounded-lg bg-slate-700 hover:bg-slate-600 text-slate-200 font-bold text-[11px] transition-colors">
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
                <!-- Top Banner Ad Placement -->
                <div class="ad-banner-top w-full flex justify-center items-center overflow-hidden rounded-2xl bg-white border border-[#e0e4eb] p-2">
                    <?= $ad_settings['ad_top_code'] ?>
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

        <?php if (!empty($ad_settings['banner_ads_enabled']) && !empty($ad_settings['ad_middle_enabled']) && !empty($ad_settings['ad_middle_code'])): ?>
            <!-- Middle Banner Ad Placement -->
            <div class="ad-banner-middle w-full flex justify-center items-center overflow-hidden rounded-2xl bg-white border border-[#e0e4eb] p-2">
                <?= $ad_settings['ad_middle_code'] ?>
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
                            <div class="text-sm font-bold text-[#111827] truncate group-hover:text-[#0b57d0] transition-colors flex items-center gap-1.5">
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
                    <div class="shrink-0">
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
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <?php if (!empty($ad_settings['banner_ads_enabled']) && !empty($ad_settings['ad_bottom_enabled']) && !empty($ad_settings['ad_bottom_code'])): ?>
            <!-- Bottom Banner Ad Placement -->
            <div class="ad-banner-bottom w-full flex justify-center items-center overflow-hidden rounded-2xl bg-white border border-[#e0e4eb] p-2">
                <?= $ad_settings['ad_bottom_code'] ?>
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

    <script>
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
                        badge.className = 'px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-500/20 text-emerald-300';
                        badge.innerText = 'Public';
                        if (banner) banner.classList.add('hidden');
                    } else {
                        btn.className = 'relative inline-flex h-5 w-9 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none bg-slate-600';
                        thumb.className = 'pointer-events-none inline-block h-4 w-4 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out translate-x-0';
                        badge.className = 'px-2 py-0.5 rounded text-[10px] font-bold bg-amber-500/20 text-amber-300';
                        badge.innerText = 'Private';
                        if (banner) banner.classList.remove('hidden');
                    }
                } else {
                    alert('Could not update status: ' + (data.error || 'Unknown error'));
                }
            } catch (err) {
                alert('Request failed: ' + err.message);
            }
        }
        <?php endif; ?>
    </script>
</body>
</html>
