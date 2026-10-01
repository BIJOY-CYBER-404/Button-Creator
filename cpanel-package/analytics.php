<?php
/**
 * Monthly Analytics & Statistics Dashboard (cPanel)
 * Matches React AdminSidebar + AppHeader + AnalyticsDashboard design
 * Google Analytics style metrics: Top 10 most viewed pages, total website visits,
 * average page visit time, device breakdown, traffic channels, top traffic country, and last month vs current month comparison.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/class-db.php';
require_once __DIR__ . '/includes/class-auth.php';
require_once __DIR__ . '/includes/class-datastore.php';

SLEA_Auth::require_admin();
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
$current_user = SLEA_Auth::get_current_user();
$site_identity = SLEA_Datastore::get_site_identity();
$site_name = !empty($site_identity['site_name']) ? $site_identity['site_name'] : (defined('APP_NAME') ? APP_NAME : 'Movie Hub HQ Drive');
$site_logo_url = !empty($site_identity['site_logo_url']) ? $site_identity['site_logo_url'] : '';
$words = preg_split('/\s+/', trim($site_name));
$initials = '';
foreach ($words as $w) {
    if ($w !== '') $initials .= mb_substr($w, 0, 1);
}
$initials = strtoupper(mb_substr($initials, 0, 3)) ?: 'MHQ';

$pages = SLEA_Datastore::get_all_pages();

// Dynamic Monthly Analytics
$current_ym = date('Y-m');
$prev_ym = date('Y-m', strtotime('first day of last month'));
$current_year = date('Y');
$current_month_name = date('F Y');
$prev_month_name = date('F Y', strtotime('first day of last month'));
$current_month_short = date('M');
$prev_month_short = date('M', strtotime('first day of last month'));

$range = $_GET['range'] ?? 'current_month';
if (!in_array($range, ['current_month', 'prev_month', 'ytd', 'all'])) {
    $range = 'current_month';
}

$monthly_stats = SLEA_Datastore::get_monthly_views_stats();

$total_views = 0;
$current_month_views = 0;
$prev_month_views = 0;
$has_current_month = false;
$has_prev_month = false;

if (!empty($monthly_stats)) {
    foreach ($monthly_stats as $ym => $cnt) {
        $total_views += $cnt;
        if ($ym === $current_ym) {
            $current_month_views = $cnt;
            $has_current_month = true;
        } elseif ($ym === $prev_ym) {
            $prev_month_views = $cnt;
            $has_prev_month = true;
        }
    }
}

// Derive from pages created timestamp if monthly breakdown is not yet populated
if ($total_views === 0) {
    foreach ($pages as $p) {
        $views = intval($p['views'] ?? 0);
        $total_views += $views;
        $p_ym = date('Y-m', strtotime($p['created_at'] ?? 'now'));
        if ($p_ym === $current_ym) {
            $current_month_views += $views;
            $has_current_month = true;
        } elseif ($p_ym === $prev_ym) {
            $prev_month_views += $views;
            $has_prev_month = true;
        }
    }
}

// Determine displayed views based on range
$displayed_views = $total_views;
$displayed_label = 'Total Website Visits';
if ($range === 'current_month') {
    $displayed_views = $has_current_month ? $current_month_views : ($total_views > 0 ? $total_views : 0);
    $displayed_label = $current_month_name . ' Visits';
} elseif ($range === 'prev_month') {
    $displayed_views = $has_prev_month ? $prev_month_views : 0;
    $displayed_label = $prev_month_name . ' Visits';
} elseif ($range === 'ytd') {
    $displayed_views = $total_views;
    $displayed_label = $current_year . ' YTD Visits';
}

// Calculate growth rate only if real historical data exists
$has_historical_comparison = ($has_prev_month && $prev_month_views > 0);
$growth_rate = $has_historical_comparison ? round((($current_month_views - $prev_month_views) / $prev_month_views) * 100) : null;
$active_pages_count = count($pages);
$visit_telemetry = SLEA_Datastore::get_visit_telemetry_stats();
$total_tracked_visits = intval($visit_telemetry['total_tracked'] ?? 0);
$dev_mobile = $visit_telemetry['devices']['mobile'] ?? ['count' => 0, 'percent' => 0];
$dev_desktop = $visit_telemetry['devices']['desktop'] ?? ['count' => 0, 'percent' => 0];
$dev_tablet = $visit_telemetry['devices']['tablet'] ?? ['count' => 0, 'percent' => 0];
$chan_direct = $visit_telemetry['channels']['direct'] ?? ['count' => 0, 'percent' => 0];
$chan_social = $visit_telemetry['channels']['social'] ?? ['count' => 0, 'percent' => 0];
$chan_organic = $visit_telemetry['channels']['organic'] ?? ['count' => 0, 'percent' => 0];
$top_countries = $visit_telemetry['countries'] ?? [];

// Sort pages by views descending for top 10
usort($pages, function($a, $b) {
    return intval($b['views'] ?? 0) - intval($a['views'] ?? 0);
});
$top_pages = array_slice($pages, 0, 10);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics &amp; Statistics - <?= htmlspecialchars($site_name) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        .font-mono { font-family: 'JetBrains Mono', monospace; }
        .shadow-2xs { box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05); }
        .shadow-xs { box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05); }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(4px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-in {
            animation: fadeIn 0.25s ease-out forwards;
        }
    </style>
</head>
<body class="min-h-screen bg-[#f0f4f9] text-[#1f1f1f] flex flex-col font-sans selection:bg-[#d3e3fd] selection:text-[#041e49]">

    <!-- Left Icon Sidebar (Matches React AdminSidebar.tsx) -->
    <aside id="admin-sidebar" class="fixed inset-y-0 left-0 w-16 sm:w-20 bg-white border-r border-[#e1e7f0] z-40 flex flex-col items-center py-4 justify-between shadow-xs select-none transition-colors" aria-label="Admin Navigation Sidebar">
        <div class="flex flex-col items-center w-full gap-5">
            <a href="admin.php" class="w-10 h-10 sm:w-11 sm:h-11 rounded-2xl bg-[#c2e7ff] text-[#001d35] flex items-center justify-center font-black text-lg shadow-2xs hover:scale-105 transition-transform cursor-pointer overflow-hidden p-1" title="<?= htmlspecialchars($site_name) ?>">
                <?php if (!empty($site_logo_url)): ?>
                    <img src="<?= htmlspecialchars($site_logo_url) ?>" alt="<?= htmlspecialchars($site_name) ?>" class="w-full h-full object-contain rounded-xl" />
                <?php else: ?>
                    <span class="text-xs font-black tracking-tight"><?= htmlspecialchars($initials) ?></span>
                <?php endif; ?>
            </a>

            <nav class="flex flex-col items-center w-full gap-2 px-1 sm:px-2">
                <!-- 1. Generate -->
                <a href="admin.php" title="Generate (+ Generator)" class="w-12 h-12 rounded-2xl flex flex-col items-center justify-center text-center transition-all cursor-pointer group relative text-[#444746] hover:bg-[#f0f4f9] hover:text-[#0b57d0]">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                        <circle cx="12" cy="12" r="10"/><path d="M8 12h8"/><path d="M12 8v8"/>
                    </svg>
                    <span class="text-[9px] font-bold mt-0.5 tracking-tight text-[#5f6368] group-hover:text-[#0b57d0]">Generate</span>
                </a>

                <!-- 2. Pages -->
                <a href="pages.php" title="Pages (/pages)" class="w-12 h-12 rounded-2xl flex flex-col items-center justify-center text-center transition-all cursor-pointer group relative text-[#444746] hover:bg-[#f0f4f9] hover:text-[#0b57d0]">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                        <path d="m12.83 2.18a2 2 0 0 0-1.66 0L2.6 6.08a1 1 0 0 0 0 1.83l8.58 3.91a2 2 0 0 0 1.66 0l8.58-3.9a1 1 0 0 0 0-1.83Z"/><path d="m22 17.65-9.17 4.16a2 2 0 0 1-1.66 0L2 17.65"/><path d="m22 12.65-9.17 4.16a2 2 0 0 1-1.66 0L2 12.65"/>
                    </svg>
                    <span class="text-[9px] font-bold mt-0.5 tracking-tight text-[#5f6368] group-hover:text-[#0b57d0]">Pages</span>
                </a>

                <!-- 3. Settings -->
                <a href="settings.php" title="Settings (/settings)" class="w-12 h-12 rounded-2xl flex flex-col items-center justify-center text-center transition-all cursor-pointer group relative text-[#444746] hover:bg-[#f0f4f9] hover:text-[#0b57d0]">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                        <path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/>
                    </svg>
                    <span class="text-[9px] font-bold mt-0.5 tracking-tight text-[#5f6368] group-hover:text-[#0b57d0]">Settings</span>
                </a>

                <!-- 4. Update -->
                <a href="update.php" title="One-Click System Updater (/update.php)" class="w-12 h-12 rounded-2xl flex flex-col items-center justify-center text-center transition-all cursor-pointer group relative text-[#444746] hover:bg-[#f0f4f9] hover:text-[#0b57d0]">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                        <path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"/><path d="M21 3v5h-5"/><path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16"/><path d="M8 16H3v5"/>
                    </svg>
                    <span class="text-[9px] font-bold mt-0.5 tracking-tight text-[#5f6368] group-hover:text-[#0b57d0]">Update</span>
                </a>

                <!-- 5. Analytics (Active) -->
                <a href="analytics.php" title="Monthly Analytics &amp; Statistics (/analytics.php)" aria-current="page" class="w-12 h-12 rounded-2xl flex flex-col items-center justify-center text-center transition-all cursor-pointer group relative bg-[#0b57d0] text-white shadow-xs">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                        <path d="M3 3v16a2 2 0 0 0 2 2h16"/><path d="M18 17V9"/><path d="M13 17V5"/><path d="M8 17v-3"/>
                    </svg>
                    <span class="text-[9px] font-bold mt-0.5 tracking-tight text-white">Analytics</span>
                </a>
            </nav>
        </div>

        <div class="flex flex-col items-center w-full gap-2 px-1 sm:px-2 pb-3">
            <div class="w-8 h-8 rounded-full bg-[#e8f0fe] text-[#0b57d0] flex items-center justify-center border border-[#d3e3fd]" title="Admin Mode Active (<?= htmlspecialchars($current_user['username']) ?>)">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                    <path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/><path d="m9 12 2 2 4-4"/>
                </svg>
            </div>

            <a href="logout.php" class="w-10 h-10 rounded-xl flex items-center justify-center text-[#c5221f] hover:bg-[#fce8e6] transition-colors cursor-pointer" title="Sign Out">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" x2="9" y1="12" y2="12"/>
                </svg>
            </a>

            <span class="text-[10px] font-bold font-mono text-[#5f6368] bg-[#f0f4f9] px-1.5 py-0.5 rounded border border-[#e1e7f0] select-none" title="Movie Hub HQ Drive Version v-<?= htmlspecialchars(preg_replace('/^(\d+\.\d+)\.\d+$/', '$1', ltrim(APP_VERSION, 'vV-'))) ?>">
                v-<?= htmlspecialchars(preg_replace('/^(\d+\.\d+)\.\d+$/', '$1', ltrim(APP_VERSION, 'vV-'))) ?>
            </span>
        </div>
    </aside>

    <!-- Main App Content Wrapper with Left Margin for Sidebar -->
    <div class="pl-16 sm:pl-20 min-h-screen flex flex-col flex-1">
        <!-- Navigation Bar (Matches React AppHeader.tsx) -->
        <header id="app-header" class="w-full bg-[#fdfcff] text-[#1f1f1f] border-b border-[#e1e7f0] shadow-2xs select-none sticky top-0 z-30 transition-colors">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 py-3 sm:py-3.5 flex items-center justify-between gap-3 min-w-0">
                <!-- Text Logo & Breadcrumb -->
                <div class="flex items-center gap-2.5 min-w-0 flex-1">
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2 flex-wrap sm:flex-nowrap">
                            <h1 class="font-bold text-sm sm:text-base leading-snug text-[#1f1f1f] tracking-tight truncate">
                                <?= htmlspecialchars($site_name) ?>
                            </h1>
                            <div class="flex items-center gap-1.5 text-xs text-[#5f6368] min-w-0">
                                <svg class="w-3.5 h-3.5 text-[#8e918f] shrink-0" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                    <path d="m9 18 6-6-6-6"/>
                                </svg>
                                <span class="font-semibold text-[#0b57d0] truncate">Monthly Analytics &amp; Statistics (/analytics.php)</span>
                            </div>
                        </div>
                        <p class="text-[11px] text-[#5f6368] truncate hidden sm:block">
                            Shortlink Bypass • Episode Button Pages
                        </p>
                    </div>
                </div>

                <!-- Status / Quick Action -->
                <div class="flex items-center gap-3 shrink-0">
                    <div class="flex items-center gap-2.5">
                        <span class="text-[11px] font-bold px-2.5 py-1 rounded-full bg-[#e6f4ea] text-[#137333] border border-[#a8dab5] tracking-wide shrink-0 flex items-center gap-1">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                <path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/><path d="m9 12 2 2 4-4"/>
                            </svg>
                            <span class="hidden sm:inline">Admin Active</span>
                        </span>
                    </div>
                </div>
            </div>
        </header>

        <!-- Main Workspace (Matches React AnalyticsDashboard.tsx) -->
        <main class="flex-1 w-full max-w-5xl mx-auto px-3.5 sm:px-6 py-5 sm:py-7">
            <div class="space-y-6 animate-fade-in pb-12">

                <!-- Top Header & Range Selector -->
                <div class="bg-white rounded-3xl p-6 sm:p-8 border border-[#e0e4eb] shadow-xs flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-2">
                            <div class="w-9 h-9 rounded-xl bg-[#e8f0fe] text-[#0b57d0] flex items-center justify-center font-bold shrink-0">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M3 3v16a2 2 0 0 0 2 2h16"/><path d="M18 17V9"/><path d="M13 17V5"/><path d="M8 17v-3"/></svg>
                            </div>
                            <div>
                                <h2 class="text-lg sm:text-xl font-extrabold text-[#111827]">Monthly Analytics &amp; Statistics</h2>
                                <p class="text-xs text-[#5f6368]">Real-time visitor traffic, top performing episode pages, engagement metrics &amp; audience insights.</p>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center gap-2.5 flex-wrap">
                        <div class="inline-flex rounded-xl bg-[#f1f3f4] p-1 border border-[#e0e4eb]">
                            <a
                                href="?range=current_month"
                                class="px-3 py-1.5 rounded-lg text-xs font-bold transition-all cursor-pointer <?= $range === 'current_month' ? 'bg-white text-[#0b57d0] shadow-2xs' : 'text-[#5f6368] hover:text-[#1f1f1f]' ?>"
                            >
                                <?= htmlspecialchars($current_month_short) ?> (Current)
                            </a>
                            <a
                                href="?range=prev_month"
                                class="px-3 py-1.5 rounded-lg text-xs font-bold transition-all cursor-pointer <?= $range === 'prev_month' ? 'bg-white text-[#0b57d0] shadow-2xs' : 'text-[#5f6368] hover:text-[#1f1f1f]' ?>"
                            >
                                <?= htmlspecialchars($prev_month_short) ?> (Last)
                            </a>
                            <a
                                href="?range=ytd"
                                class="px-3 py-1.5 rounded-lg text-xs font-bold transition-all cursor-pointer <?= $range === 'ytd' ? 'bg-white text-[#0b57d0] shadow-2xs' : 'text-[#5f6368] hover:text-[#1f1f1f]' ?>"
                            >
                                YTD
                            </a>
                            <a
                                href="?range=all"
                                class="px-3 py-1.5 rounded-lg text-xs font-bold transition-all cursor-pointer <?= $range === 'all' ? 'bg-white text-[#0b57d0] shadow-2xs' : 'text-[#5f6368] hover:text-[#1f1f1f]' ?>"
                            >
                                All-Time
                            </a>
                        </div>

                        <button
                            type="button"
                            onclick="handleExportCSV()"
                            class="px-3.5 py-2 rounded-xl bg-[#f1f3f4] hover:bg-[#e2e7ec] text-[#3c4043] font-bold text-xs flex items-center gap-1.5 transition-colors cursor-pointer border border-[#d9dbde]"
                            title="Export CSV Report"
                        >
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" x2="12" y1="15" y2="3"/></svg>
                            <span class="hidden sm:inline">Export CSV</span>
                        </button>

                        <button
                            type="button"
                            onclick="window.location.reload()"
                            class="p-2 rounded-xl bg-[#e8f0fe] hover:bg-[#d3e3fd] text-[#0b57d0] transition-colors cursor-pointer border border-[#c2e7ff]"
                            title="Refresh Analytics"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"/><path d="M21 3v5h-5"/><path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16"/><path d="M8 16H3v5"/></svg>
                        </button>
                    </div>
                </div>

                <!-- 4 Key Metrics Cards -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <!-- Card 1: Displayed Range Visits -->
                    <div class="bg-white rounded-3xl p-5 border border-[#e0e4eb] shadow-xs space-y-3 relative overflow-hidden group hover:border-[#0b57d0] transition-all">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold text-[#5f6368] uppercase tracking-wider"><?= htmlspecialchars($displayed_label) ?></span>
                            <div class="w-8 h-8 rounded-xl bg-[#e6f4ea] text-[#137333] flex items-center justify-center font-bold">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 2a14.5 14.5 0 0 0 0 20 14.5 14.5 0 0 0 0-20"/><path d="M2 12h20"/></svg>
                            </div>
                        </div>
                        <div class="flex items-baseline gap-2">
                            <span class="text-2xl sm:text-3xl font-extrabold text-[#111827] font-mono"><?= number_format($displayed_views) ?></span>
                            <?php if ($growth_rate !== null): ?>
                                <span class="text-xs font-bold inline-flex items-center px-1.5 py-0.5 rounded-md <?= $growth_rate >= 0 ? 'bg-[#e6f4ea] text-[#137333]' : 'bg-red-50 text-red-600' ?>">
                                    <svg class="w-3 h-3 mr-0.5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><polyline points="22 7 13.5 15.5 8.5 10.5 2 17"/><polyline points="16 7 22 7 22 13"/></svg>
                                    <?= $growth_rate >= 0 ? '+' : '' ?><?= $growth_rate ?>% vs <?= htmlspecialchars($prev_month_short) ?>
                                </span>
                            <?php else: ?>
                                <span class="text-xs font-bold text-[#5f6368] bg-[#f1f3f4] px-1.5 py-0.5 rounded-md">
                                    Growth: N/A
                                </span>
                            <?php endif; ?>
                        </div>
                        <p class="text-[11px] text-[#747775]">
                            <?= $growth_rate !== null ? "Compared to {$prev_month_name} recorded pages" : "Insufficient {$prev_month_name} data" ?>
                        </p>
                    </div>

                    <!-- Card 2: Active Sessions -->
                    <div class="bg-white rounded-3xl p-5 border border-[#e0e4eb] shadow-xs space-y-3 relative overflow-hidden group hover:border-[#0b57d0] transition-all">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold text-[#5f6368] uppercase tracking-wider">Active Sessions</span>
                            <div class="w-8 h-8 rounded-xl bg-[#e8f0fe] text-[#0b57d0] flex items-center justify-center font-bold">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                            </div>
                        </div>
                        <div class="flex items-baseline gap-2">
                            <span class="text-2xl sm:text-3xl font-extrabold text-[#111827] font-mono">—</span>
                            <span class="text-xs font-bold text-[#5f6368] inline-flex items-center bg-[#f1f3f4] px-1.5 py-0.5 rounded-md">
                                N/A
                            </span>
                        </div>
                        <p class="text-[11px] text-[#747775]">Session telemetry not logged in DB</p>
                    </div>

                    <!-- Card 3: Avg Visit Duration -->
                    <div class="bg-white rounded-3xl p-5 border border-[#e0e4eb] shadow-xs space-y-3 relative overflow-hidden group hover:border-[#0b57d0] transition-all">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold text-[#5f6368] uppercase tracking-wider">Avg Visit Duration</span>
                            <div class="w-8 h-8 rounded-xl bg-[#fef7e0] text-[#b06000] flex items-center justify-center font-bold">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                            </div>
                        </div>
                        <div class="flex items-baseline gap-2">
                            <span class="text-2xl sm:text-3xl font-extrabold text-[#111827] font-mono">—</span>
                            <span class="text-xs font-bold text-[#5f6368] inline-flex items-center bg-[#f1f3f4] px-1.5 py-0.5 rounded-md">
                                N/A
                            </span>
                        </div>
                        <p class="text-[11px] text-[#747775]">Duration telemetry not logged in DB</p>
                    </div>

                    <!-- Card 4: Published Pages -->
                    <div class="bg-white rounded-3xl p-5 border border-[#e0e4eb] shadow-xs space-y-3 relative overflow-hidden group hover:border-[#0b57d0] transition-all">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold text-[#5f6368] uppercase tracking-wider">Published Pages</span>
                            <div class="w-8 h-8 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center font-bold">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/><path d="m9 12 2 2 4-4"/></svg>
                            </div>
                        </div>
                        <div class="flex items-baseline gap-2">
                            <span class="text-2xl sm:text-3xl font-extrabold text-[#111827] font-mono"><?= $active_pages_count ?></span>
                            <span class="text-xs font-bold text-purple-700 inline-flex items-center bg-purple-50 px-1.5 py-0.5 rounded-md">
                                <?= $active_pages_count > 0 ? '100% Online' : '0 Published' ?>
                            </span>
                        </div>
                        <p class="text-[11px] text-[#747775]">Active verified button pages</p>
                    </div>
                </div>

                <!-- Traffic Trend & Last Month vs Current Month Chart Section -->
                <div class="bg-white rounded-3xl p-6 sm:p-8 border border-[#e0e4eb] shadow-xs space-y-6">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-[#f1f3f4] pb-4">
                        <div>
                            <h3 id="chartTitle" class="text-base font-bold text-[#1f1f1f]">
                                Daily Page Views (<?= htmlspecialchars($current_month_name) ?>)
                            </h3>
                            <p id="chartSub" class="text-xs text-[#5f6368]">
                                Time-series view overview.
                            </p>
                        </div>
                        <div class="flex items-center gap-2">
                            <div class="inline-flex rounded-xl bg-[#f1f3f4] p-1 border border-[#e0e4eb]">
                                <button
                                    type="button"
                                    id="btnChartDaily"
                                    onclick="switchChartMode('daily')"
                                    class="px-3 py-1 rounded-lg text-xs font-bold transition-all cursor-pointer bg-white text-[#0b57d0] shadow-2xs"
                                >
                                    Daily Trend
                                </button>
                                <button
                                    type="button"
                                    id="btnChartCompare"
                                    onclick="switchChartMode('monthly_compare')"
                                    class="px-3 py-1 rounded-lg text-xs font-bold transition-all cursor-pointer text-[#5f6368]"
                                >
                                    Last Month vs Current Month
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Daily Mode View -->
                    <div id="chartViewDaily">
                        <?php if ($total_views === 0): ?>
                            <div class="py-12 text-center text-xs text-[#747775]">
                                No page visits recorded yet. Visits will automatically increment in real-time as visitors open button pages.
                            </div>
                        <?php else: ?>
                            <div class="py-8 text-center space-y-2">
                                <div class="text-3xl font-extrabold text-[#0b57d0] font-mono"><?= number_format($total_views) ?></div>
                                <p class="text-xs font-medium text-[#5f6368]">Total Real Verified Page Views across all active button pages.</p>
                                <span class="inline-block px-3 py-1 rounded-full bg-[#f1f3f4] text-[#5f6368] text-[11px] font-medium border border-[#e0e4eb]">
                                    Hourly/daily timeseries telemetry: N/A (Aggregate database views shown)
                                </span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Monthly Compare View -->
                    <div id="chartViewCompare" class="hidden py-6 px-4 grid grid-cols-1 sm:grid-cols-2 gap-8 items-center">
                        <div class="space-y-6">
                            <div class="space-y-2">
                                <div class="flex justify-between text-xs font-bold">
                                    <span class="text-[#5f6368]">Last Month Views (<?= htmlspecialchars($prev_month_name) ?>)</span>
                                    <span class="font-mono text-[#5f6368]">
                                        <?= $has_prev_month ? number_format($prev_month_views) . ' views' : '— (N/A)' ?>
                                    </span>
                                </div>
                                <div class="w-full bg-[#f1f3f4] h-4 rounded-full overflow-hidden">
                                    <div
                                        class="bg-slate-400 h-full rounded-full transition-all"
                                        style="width: <?= $has_historical_comparison ? min(100, max(10, ($prev_month_views / max(1, $current_month_views + $prev_month_views)) * 200)) : 0 ?>%"
                                    ></div>
                                </div>
                            </div>
                            <div class="space-y-2">
                                <div class="flex justify-between text-xs font-bold">
                                    <span class="text-[#0b57d0]">Current Month Views (<?= htmlspecialchars($current_month_name) ?>)</span>
                                    <span class="font-mono text-[#0b57d0]">
                                        <?= $has_current_month ? number_format($current_month_views) . ' views' : ($total_views > 0 ? number_format($total_views) . ' views' : '— (0 views)') ?>
                                    </span>
                                </div>
                                <div class="w-full bg-[#f1f3f4] h-4 rounded-full overflow-hidden">
                                    <div
                                        class="bg-gradient-to-r from-[#0b57d0] to-[#4285f4] h-full rounded-full transition-all"
                                        style="width: <?= $total_views > 0 ? ($has_historical_comparison ? min(100, max(15, ($current_month_views / max(1, $current_month_views + $prev_month_views)) * 200)) : 100) : 0 ?>%"
                                    ></div>
                                </div>
                            </div>
                        </div>
                        <div class="bg-[#f8f9fa] rounded-2xl p-6 border border-[#e0e4eb] space-y-3 text-center sm:text-left">
                            <h4 class="font-bold text-sm text-[#111827]">Monthly Growth Summary</h4>
                            <?php if ($growth_rate !== null): ?>
                                <p class="text-xs text-[#5f6368] leading-relaxed">
                                    Traffic in <span class="font-semibold text-slate-800"><?= htmlspecialchars($current_month_name) ?></span> changed by <span class="font-bold text-[#137333]"><?= $growth_rate >= 0 ? '+' : '' ?><?= $growth_rate ?>%</span> compared to <span class="font-semibold text-slate-800"><?= htmlspecialchars($prev_month_name) ?></span> based on actual database records.
                                </p>
                            <?php else: ?>
                                <p class="text-xs text-[#5f6368] leading-relaxed">
                                    No historical traffic logged for <?= htmlspecialchars($prev_month_name) ?>. Growth percentage calculation: <span class="font-bold text-[#444746]">N/A</span>.
                                </p>
                            <?php endif; ?>
                            <div class="pt-2 flex items-center justify-center sm:justify-start gap-3">
                                <span class="px-3 py-1 bg-[#e8f0fe] text-[#0b57d0] rounded-xl text-xs font-mono font-bold">
                                    <?= htmlspecialchars($current_month_short) ?>: <?= number_format($current_month_views ?: $total_views) ?>
                                </span>
                                <span class="px-3 py-1 bg-slate-100 text-slate-600 rounded-xl text-xs font-mono font-bold">
                                    <?= htmlspecialchars($prev_month_short) ?>: <?= $has_prev_month ? number_format($prev_month_views) : 'N/A' ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Top 10 Most Viewed Pages Table -->
                <div class="bg-white rounded-3xl p-6 sm:p-8 border border-[#e0e4eb] shadow-xs space-y-6">
                    <div class="flex items-center justify-between border-b border-[#f1f3f4] pb-4">
                        <div>
                            <h3 class="text-base font-bold text-[#1f1f1f]">Top 10 Most Viewed Episode Pages</h3>
                            <p class="text-xs text-[#5f6368]">Ranked strictly by real database view counts.</p>
                        </div>
                        <span class="px-3 py-1 rounded-full bg-[#e8f0fe] text-[#0b57d0] text-xs font-bold border border-[#c2e7ff]">
                            <?= count($top_pages) ?> Pages
                        </span>
                    </div>

                    <?php if (empty($top_pages)): ?>
                        <div class="text-center py-12 text-[#747775] text-xs">
                            No pages generated yet. Create your first page in the Generate tab.
                        </div>
                    <?php else: ?>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse">
                                <thead>
                                    <tr class="border-b border-[#e1e7f0] text-[11px] font-bold text-[#5f6368] uppercase tracking-wider">
                                        <th class="py-3 px-4">Rank</th>
                                        <th class="py-3 px-4">Page Title &amp; Slug</th>
                                        <th class="py-3 px-4 text-center">Views</th>
                                        <th class="py-3 px-4 text-center">Traffic Share</th>
                                        <th class="py-3 px-4 text-right">Created</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-[#f1f3f4] text-xs">
                                    <?php foreach ($top_pages as $index => $p):
                                        $views = intval($p['views'] ?? 0);
                                        $share = $total_views > 0 ? round(($views / $total_views) * 100) : 0;
                                    ?>
                                        <tr class="hover:bg-[#fafcff] transition-colors">
                                            <td class="py-3.5 px-4 font-mono font-extrabold text-[#0b57d0]">
                                                #<?= $index + 1 ?>
                                            </td>
                                            <td class="py-3.5 px-4 min-w-[240px]">
                                                <div class="font-bold text-[#111827] truncate max-w-sm" title="<?= htmlspecialchars($p['title']) ?>"><?= htmlspecialchars($p['title']) ?></div>
                                                <div class="text-[11px] font-mono text-[#5f6368]">/p/<?= htmlspecialchars($p['slug']) ?></div>
                                            </td>
                                            <td class="py-3.5 px-4 text-center font-mono font-bold text-[#137333]">
                                                <?= number_format($views) ?>
                                            </td>
                                            <td class="py-3.5 px-4 text-center">
                                                <div class="flex items-center justify-center gap-2">
                                                    <div class="w-20 bg-slate-100 rounded-full h-2 overflow-hidden border border-slate-200">
                                                        <div class="bg-[#0b57d0] h-full rounded-full" style="width: <?= max(5, $share) ?>%"></div>
                                                    </div>
                                                    <span class="font-mono text-[11px] font-bold text-[#444746]"><?= $share ?>%</span>
                                                </div>
                                            </td>
                                            <td class="py-3.5 px-4 text-right font-mono text-[#747775]">
                                                <?= htmlspecialchars(date('n/j/Y', strtotime($p['created_at'] ?? 'now'))) ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Device Breakdown, Top Traffic Channels & Top Traffic Country -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <!-- Device Breakdown -->
                    <div class="bg-white rounded-3xl p-6 border border-[#e0e4eb] shadow-xs space-y-4">
                        <div class="flex items-center justify-between">
                            <h4 class="font-bold text-sm text-[#111827] flex items-center gap-2">
                                <svg class="w-4 h-4 text-[#0b57d0]" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><rect width="14" height="20" x="5" y="2" rx="2" ry="2"/><path d="M12 18h.01"/></svg>
                                <span>Device Breakdown</span>
                            </h4>
                            <span class="text-[10px] font-bold px-2 py-0.5 rounded bg-[#e8f0fe] text-[#0b57d0] border border-[#c2e7ff]">100% Real</span>
                        </div>
                        <div class="space-y-3 text-xs">
                            <div>
                                <div class="flex justify-between font-medium mb-1 text-[#5f6368]">
                                    <span class="flex items-center gap-1.5">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><rect width="14" height="20" x="5" y="2" rx="2" ry="2"/><path d="M12 18h.01"/></svg>
                                        Mobile Smartphone
                                    </span>
                                    <span class="font-mono font-bold text-[#111827]"><?= intval($dev_mobile['count']) ?> (<?= intval($dev_mobile['percent']) ?>%)</span>
                                </div>
                                <div class="w-full bg-[#f1f3f4] h-2 rounded-full overflow-hidden">
                                    <div class="bg-[#0b57d0] h-full rounded-full transition-all" style="width: <?= max(0, min(100, intval($dev_mobile['percent']))) ?>%"></div>
                                </div>
                            </div>
                            <div>
                                <div class="flex justify-between font-medium mb-1 text-[#5f6368]">
                                    <span class="flex items-center gap-1.5">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><rect width="20" height="14" x="2" y="3" rx="2"/><line x1="8" x2="16" y1="21" y2="21"/><line x1="12" x2="12" y1="17" y2="21"/></svg>
                                        Desktop PC / Mac
                                    </span>
                                    <span class="font-mono font-bold text-[#111827]"><?= intval($dev_desktop['count']) ?> (<?= intval($dev_desktop['percent']) ?>%)</span>
                                </div>
                                <div class="w-full bg-[#f1f3f4] h-2 rounded-full overflow-hidden">
                                    <div class="bg-[#0b57d0] h-full rounded-full transition-all" style="width: <?= max(0, min(100, intval($dev_desktop['percent']))) ?>%"></div>
                                </div>
                            </div>
                            <div>
                                <div class="flex justify-between font-medium mb-1 text-[#5f6368]">
                                    <span>Tablet / iPad</span>
                                    <span class="font-mono font-bold text-[#111827]"><?= intval($dev_tablet['count']) ?> (<?= intval($dev_tablet['percent']) ?>%)</span>
                                </div>
                                <div class="w-full bg-[#f1f3f4] h-2 rounded-full overflow-hidden">
                                    <div class="bg-[#0b57d0] h-full rounded-full transition-all" style="width: <?= max(0, min(100, intval($dev_tablet['percent']))) ?>%"></div>
                                </div>
                            </div>
                        </div>
                        <p class="text-[10px] text-[#747775] leading-relaxed pt-1">
                            Aggregated from <?= number_format($total_tracked_visits) ?> real logged HTTP User-Agent request<?= $total_tracked_visits === 1 ? '' : 's' ?>.
                        </p>
                    </div>

                    <!-- Top Traffic Channels -->
                    <div class="bg-white rounded-3xl p-6 border border-[#e0e4eb] shadow-xs space-y-4">
                        <div class="flex items-center justify-between">
                            <h4 class="font-bold text-sm text-[#111827] flex items-center gap-2">
                                <svg class="w-4 h-4 text-[#137333]" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 2a14.5 14.5 0 0 0 0 20 14.5 14.5 0 0 0 0-20"/><path d="M2 12h20"/></svg>
                                <span>Top Traffic Channels</span>
                            </h4>
                            <span class="text-[10px] font-bold px-2 py-0.5 rounded bg-[#e6f4ea] text-[#137333] border border-[#ceead6]">100% Real</span>
                        </div>
                        <div class="space-y-3 text-xs">
                            <div>
                                <div class="flex justify-between font-medium mb-1 text-[#5f6368]">
                                    <span>Direct &amp; Bookmark Links</span>
                                    <span class="font-mono font-bold text-[#111827]"><?= intval($chan_direct['count']) ?> (<?= intval($chan_direct['percent']) ?>%)</span>
                                </div>
                                <div class="w-full bg-[#f1f3f4] h-2 rounded-full overflow-hidden">
                                    <div class="bg-[#137333] h-full rounded-full transition-all" style="width: <?= max(0, min(100, intval($chan_direct['percent']))) ?>%"></div>
                                </div>
                            </div>
                            <div>
                                <div class="flex justify-between font-medium mb-1 text-[#5f6368]">
                                    <span>Social Media (Telegram / WhatsApp)</span>
                                    <span class="font-mono font-bold text-[#111827]"><?= intval($chan_social['count']) ?> (<?= intval($chan_social['percent']) ?>%)</span>
                                </div>
                                <div class="w-full bg-[#f1f3f4] h-2 rounded-full overflow-hidden">
                                    <div class="bg-[#137333] h-full rounded-full transition-all" style="width: <?= max(0, min(100, intval($chan_social['percent']))) ?>%"></div>
                                </div>
                            </div>
                            <div>
                                <div class="flex justify-between font-medium mb-1 text-[#5f6368]">
                                    <span>Organic Search &amp; Referrals</span>
                                    <span class="font-mono font-bold text-[#111827]"><?= intval($chan_organic['count']) ?> (<?= intval($chan_organic['percent']) ?>%)</span>
                                </div>
                                <div class="w-full bg-[#f1f3f4] h-2 rounded-full overflow-hidden">
                                    <div class="bg-[#137333] h-full rounded-full transition-all" style="width: <?= max(0, min(100, intval($chan_organic['percent']))) ?>%"></div>
                                </div>
                            </div>
                        </div>
                        <p class="text-[10px] text-[#747775] leading-relaxed pt-1">
                            Classified from <?= number_format($total_tracked_visits) ?> real HTTP Referer header<?= $total_tracked_visits === 1 ? '' : 's' ?>.
                        </p>
                    </div>

                    <!-- Top Traffic Country -->
                    <div class="bg-white rounded-3xl p-6 border border-[#e0e4eb] shadow-xs space-y-4">
                        <div class="flex items-center justify-between">
                            <h4 class="font-bold text-sm text-[#111827] flex items-center gap-2">
                                <svg class="w-4 h-4 text-[#b06000]" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M20 10c0 4.993-5.539 10.193-7.399 11.799a1 1 0 0 1-1.202 0C9.539 20.193 4 14.993 4 10a8 8 0 0 1 16 0"/><circle cx="12" cy="10" r="3"/></svg>
                                <span>Top Traffic Country</span>
                            </h4>
                            <span class="text-[10px] font-bold px-2 py-0.5 rounded bg-[#fef7e0] text-[#b06000] border border-[#fde293]">100% Real</span>
                        </div>
                        <div class="space-y-3 text-xs">
                            <?php if (empty($top_countries)): ?>
                                <div class="py-6 text-center text-[#747775]">
                                    No country data recorded yet.
                                </div>
                            <?php else: ?>
                                <?php foreach (array_slice($top_countries, 0, 3) as $c_item): ?>
                                    <div>
                                        <div class="flex justify-between font-medium mb-1 text-[#5f6368]">
                                            <span><?= htmlspecialchars($c_item['name'] ?? 'Unknown') ?> <span class="text-[10px] font-mono text-slate-400">(<?= htmlspecialchars($c_item['code'] ?? 'UN') ?>)</span></span>
                                            <span class="font-mono font-bold text-[#111827]"><?= intval($c_item['count'] ?? 0) ?> (<?= intval($c_item['percent'] ?? 0) ?>%)</span>
                                        </div>
                                        <div class="w-full bg-[#f1f3f4] h-2 rounded-full overflow-hidden">
                                            <div class="bg-[#b06000] h-full rounded-full transition-all" style="width: <?= max(0, min(100, intval($c_item['percent'] ?? 0))) ?>%"></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <p class="text-[10px] text-[#747775] leading-relaxed pt-1">
                            Resolved from real visitor GeoIP &amp; locale request headers.
                        </p>
                    </div>
                </div>

            </div>
        </main>
    </div>

    <script>
        const topPagesData = <?= json_encode(array_map(function($p, $idx) {
            return [
                'rank' => $idx + 1,
                'title' => $p['title'] ?? '',
                'slug' => $p['slug'] ?? '',
                'views' => intval($p['views'] ?? 0),
                'created_at' => $p['created_at'] ?? ''
            ];
        }, $top_pages, array_keys($top_pages))) ?>;

        function switchChartMode(mode) {
            const btnDaily = document.getElementById('btnChartDaily');
            const btnCompare = document.getElementById('btnChartCompare');
            const viewDaily = document.getElementById('chartViewDaily');
            const viewCompare = document.getElementById('chartViewCompare');
            const titleEl = document.getElementById('chartTitle');
            const subEl = document.getElementById('chartSub');

            if (mode === 'daily') {
                btnDaily.className = 'px-3 py-1 rounded-lg text-xs font-bold transition-all cursor-pointer bg-white text-[#0b57d0] shadow-2xs';
                btnCompare.className = 'px-3 py-1 rounded-lg text-xs font-bold transition-all cursor-pointer text-[#5f6368]';
                viewDaily.classList.remove('hidden');
                viewCompare.classList.add('hidden');
                titleEl.innerText = 'Daily Page Views (<?= addslashes($current_month_name) ?>)';
                subEl.innerText = 'Time-series view overview.';
            } else {
                btnCompare.className = 'px-3 py-1 rounded-lg text-xs font-bold transition-all cursor-pointer bg-white text-[#0b57d0] shadow-2xs';
                btnDaily.className = 'px-3 py-1 rounded-lg text-xs font-bold transition-all cursor-pointer text-[#5f6368]';
                viewCompare.classList.remove('hidden');
                viewDaily.classList.add('hidden');
                titleEl.innerText = 'Last Month (<?= addslashes($prev_month_name) ?>) vs Current Month (<?= addslashes($current_month_name) ?>)';
                subEl.innerText = 'Real comparative view analysis between previous and current months.';
            }
        }

        function handleExportCSV() {
            const headers = ["Rank", "Title", "Slug", "Views", "Created At"];
            const rows = topPagesData.map(p => [
                p.rank,
                '"' + String(p.title).replace(/"/g, '""') + '"',
                p.slug,
                p.views,
                p.created_at
            ]);
            const csvContent = "data:text/csv;charset=utf-8," + [headers.join(","), ...rows.map(e => e.join(","))].join("\n");
            const encodedUri = encodeURI(csvContent);
            const link = document.createElement("a");
            link.setAttribute("href", encodedUri);
            link.setAttribute("download", "movie_hub_analytics_<?= htmlspecialchars($range) ?>.csv");
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
    </script>
</body>
</html>
