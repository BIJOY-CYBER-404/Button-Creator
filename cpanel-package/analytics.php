<?php
/**
 * Monthly Analytics & Statistics Dashboard (cPanel)
 * Google Analytics style metrics: Top 10 most viewed pages, total website visits,
 * average page visit time, device breakdown, traffic channels, top traffic country, and last month vs current month comparison.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/class-db.php';
require_once __DIR__ . '/includes/class-auth.php';
require_once __DIR__ . '/includes/class-datastore.php';

SLEA_Auth::require_admin();
$current_user = SLEA_Auth::get_current_user();
$site_identity = SLEA_Datastore::get_site_identity();
$site_name = !empty($site_identity['site_name']) ? $site_identity['site_name'] : APP_NAME;
$site_logo_url = !empty($site_identity['site_logo_url']) ? $site_identity['site_logo_url'] : '';

$pages = SLEA_Datastore::get_all_pages();

// Dynamic Monthly Analytics
$current_ym = date('Y-m');
$prev_ym = date('Y-m', strtotime('first day of last month'));
$current_month_name = date('F Y');
$prev_month_name = date('F Y', strtotime('first day of last month'));
$current_month_short = date('M');
$prev_month_short = date('M', strtotime('first day of last month'));

$range = $_GET['range'] ?? 'current_month';
if (!in_array($range, ['current_month', 'prev_month', 'all'])) {
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
$displayed_label = 'All-Time Website Visits';
if ($range === 'current_month') {
    $displayed_views = $has_current_month ? $current_month_views : ($total_views > 0 ? $total_views : 0);
    $displayed_label = $current_month_name . ' Visits';
} elseif ($range === 'prev_month') {
    $displayed_views = $has_prev_month ? $prev_month_views : 0;
    $displayed_label = $prev_month_name . ' Visits';
}

// Calculate growth rate only if real historical data exists
$has_historical_comparison = ($has_prev_month && $prev_month_views > 0);
$growth_rate = $has_historical_comparison ? round((($current_month_views - $prev_month_views) / $prev_month_views) * 100) : null;
$active_pages_count = count($pages);

// Sort pages by views descending for top 10
usort($pages, function($a, $b) {
    return intval($b['views'] ?? 0) - intval($a['views'] ?? 0);
});
$top_pages = array_slice($pages, 0, 10);

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$base_url = rtrim($protocol . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']), '/\\');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics & Statistics - <?= htmlspecialchars($site_name) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        .font-mono { font-family: 'JetBrains Mono', monospace; }
    </style>
</head>
<body class="bg-[#f0f4f9] text-[#1f1f1f] min-h-screen font-sans antialiased selection:bg-[#d3e3fd] selection:text-[#041e49]">
    <!-- Left Icon Sidebar -->
    <aside class="fixed inset-y-0 left-0 w-16 sm:w-20 bg-white border-r border-[#e1e7f0] z-40 flex flex-col items-center py-4 justify-between shadow-xs select-none">
        <div class="flex flex-col items-center w-full gap-5">
            <a href="admin.php" class="w-10 h-10 sm:w-11 sm:h-11 rounded-2xl bg-[#c2e7ff] text-[#001d35] flex items-center justify-center font-black text-lg shadow-2xs hover:scale-105 transition-transform overflow-hidden p-1" title="<?= htmlspecialchars($site_name) ?>">
                <?php if (!empty($site_logo_url)): ?>
                    <img src="<?= htmlspecialchars($site_logo_url) ?>" alt="<?= htmlspecialchars($site_name) ?>" class="w-full h-full object-contain rounded-xl" />
                <?php else: ?>
                    <span class="font-bold text-[10px] text-center leading-tight truncate px-0.5"><?= htmlspecialchars($site_name) ?></span>
                <?php endif; ?>
            </a>

            <nav class="flex flex-col items-center w-full gap-2 px-1 sm:px-2">
                <a href="admin.php" class="w-12 h-12 rounded-2xl flex flex-col items-center justify-center text-center transition-all group text-[#444746] hover:bg-[#f0f4f9] hover:text-[#0b57d0]" title="Generate">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    <span class="text-[9px] font-bold mt-0.5">Generate</span>
                </a>
                <a href="pages.php" class="w-12 h-12 rounded-2xl flex flex-col items-center justify-center text-center transition-all group text-[#444746] hover:bg-[#f0f4f9] hover:text-[#0b57d0]" title="Pages">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                    <span class="text-[9px] font-bold mt-0.5">Pages</span>
                </a>
                <a href="settings.php" class="w-12 h-12 rounded-2xl flex flex-col items-center justify-center text-center transition-all group text-[#444746] hover:bg-[#f0f4f9] hover:text-[#0b57d0]" title="Settings">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    <span class="text-[9px] font-bold mt-0.5">Settings</span>
                </a>
                <a href="update.php" class="w-12 h-12 rounded-2xl flex flex-col items-center justify-center text-center transition-all group text-[#444746] hover:bg-[#f0f4f9] hover:text-[#0b57d0]" title="Update">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    <span class="text-[9px] font-bold mt-0.5">Update</span>
                </a>
                <a href="analytics.php" class="w-12 h-12 rounded-2xl flex flex-col items-center justify-center text-center transition-all group bg-[#0b57d0] text-white shadow-xs" title="Analytics">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                    <span class="text-[9px] font-bold mt-0.5 text-white">Analytics</span>
                </a>
            </nav>
        </div>

        <div class="flex flex-col items-center w-full gap-2 px-1 sm:px-2 pb-3">
            <div class="w-8 h-8 rounded-full bg-[#e8f0fe] text-[#0b57d0] font-black text-xs flex items-center justify-center border border-[#d3e3fd]">
                <?= strtoupper(substr($current_user['username'], 0, 1)) ?>
            </div>
            <a href="logout.php" class="w-10 h-10 rounded-xl flex items-center justify-center text-[#c5221f] hover:bg-[#fce8e6] transition-colors" title="Logout">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
            </a>
            <span class="text-[10px] font-bold font-mono text-slate-400 bg-slate-100 px-1.5 py-0.5 rounded border border-slate-200"><?= htmlspecialchars(APP_VERSION) ?></span>
        </div>
    </aside>

    <div class="pl-16 sm:pl-20 min-h-screen flex flex-col">
        <header class="bg-white border-b border-[#e1e7f0] sticky top-0 z-30 shadow-2xs">
            <div class="max-w-5xl mx-auto px-4 sm:px-6 py-3 sm:py-3.5 flex items-center justify-between gap-3">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-lg bg-blue-50 border border-blue-100 flex items-center justify-center text-blue-600">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                    </div>
                    <div>
                        <h1 class="font-bold text-sm sm:text-base text-[#1f1f1f] leading-tight">Monthly Analytics & Statistics</h1>
                        <span class="text-[11px] text-[#5f6368]">Google Analytics style traffic overview &amp; insights</span>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <div class="inline-flex rounded-xl bg-[#f1f3f4] p-1 border border-[#e0e4eb] text-xs font-bold">
                        <a href="?range=current_month" class="px-3 py-1 rounded-lg transition-all <?= $range === 'current_month' ? 'bg-white text-[#0b57d0] shadow-2xs' : 'text-[#5f6368] hover:text-[#1f1f1f]' ?>">
                            <?= $current_month_short ?> (Current)
                        </a>
                        <a href="?range=prev_month" class="px-3 py-1 rounded-lg transition-all <?= $range === 'prev_month' ? 'bg-white text-[#0b57d0] shadow-2xs' : 'text-[#5f6368] hover:text-[#1f1f1f]' ?>">
                            <?= $prev_month_short ?> (Last)
                        </a>
                        <a href="?range=all" class="px-3 py-1 rounded-lg transition-all <?= $range === 'all' ? 'bg-white text-[#0b57d0] shadow-2xs' : 'text-[#5f6368] hover:text-[#1f1f1f]' ?>">
                            All-Time
                        </a>
                    </div>
                    <span class="text-xs font-semibold px-2.5 py-1 rounded-full bg-[#e6f4ea] text-[#137333] border border-[#a8dab5] hidden sm:inline-block">● Admin Live</span>
                </div>
            </div>
        </header>

        <main class="max-w-5xl mx-auto px-4 sm:px-6 py-6 sm:py-8 flex-1 w-full space-y-6">
            <!-- 4 Metric Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="bg-white rounded-3xl p-5 border border-[#e0e4eb] shadow-xs space-y-3">
                    <span class="text-xs font-bold text-[#5f6368] uppercase tracking-wider block"><?= htmlspecialchars($displayed_label) ?></span>
                    <div class="text-3xl font-extrabold text-[#111827] font-mono"><?= number_format($displayed_views) ?></div>
                    <?php if ($growth_rate !== null): ?>
                        <span class="text-xs font-bold <?= $growth_rate >= 0 ? 'text-[#137333] bg-[#e6f4ea]' : 'text-red-600 bg-red-50' ?> px-1.5 py-0.5 rounded">
                            <?= $growth_rate >= 0 ? '+' : '' ?><?= $growth_rate ?>% vs <?= htmlspecialchars($prev_month_short) ?>
                        </span>
                    <?php else: ?>
                        <span class="text-xs font-bold text-[#5f6368] bg-[#f1f3f4] px-1.5 py-0.5 rounded">
                            Growth: N/A
                        </span>
                    <?php endif; ?>
                </div>
                <div class="bg-white rounded-3xl p-5 border border-[#e0e4eb] shadow-xs space-y-3">
                    <span class="text-xs font-bold text-[#5f6368] uppercase tracking-wider block">Active Sessions</span>
                    <div class="text-3xl font-extrabold text-[#111827] font-mono">—</div>
                    <span class="text-xs font-bold text-[#5f6368] bg-[#f1f3f4] px-1.5 py-0.5 rounded">Telemetry: N/A</span>
                </div>
                <div class="bg-white rounded-3xl p-5 border border-[#e0e4eb] shadow-xs space-y-3">
                    <span class="text-xs font-bold text-[#5f6368] uppercase tracking-wider block">Avg Visit Duration</span>
                    <div class="text-3xl font-extrabold text-[#111827] font-mono">—</div>
                    <span class="text-xs font-bold text-[#5f6368] bg-[#f1f3f4] px-1.5 py-0.5 rounded">Telemetry: N/A</span>
                </div>
                <div class="bg-white rounded-3xl p-5 border border-[#e0e4eb] shadow-xs space-y-3">
                    <span class="text-xs font-bold text-[#5f6368] uppercase tracking-wider block">Published Pages</span>
                    <div class="text-3xl font-extrabold text-[#111827] font-mono"><?= $active_pages_count ?></div>
                    <span class="text-xs font-bold text-purple-700 bg-purple-50 px-1.5 py-0.5 rounded"><?= $active_pages_count > 0 ? '100% Online' : '0 Published' ?></span>
                </div>
            </div>

            <!-- Last Month vs Current Month Comparison Card -->
            <div class="bg-white rounded-3xl p-6 sm:p-8 border border-[#e0e4eb] shadow-xs space-y-6">
                <div class="border-b border-[#f1f3f4] pb-4 flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                    <div>
                        <h3 class="text-base font-bold text-[#1f1f1f]">Last Month (<?= htmlspecialchars($prev_month_name) ?>) vs Current Month (<?= htmlspecialchars($current_month_name) ?>)</h3>
                        <p class="text-xs text-[#5f6368]">Monthly dynamic comparative performance based on actual recorded views.</p>
                    </div>
                    <span class="text-xs font-bold font-mono px-2.5 py-1 rounded-lg bg-blue-50 text-blue-700 border border-blue-200 self-start sm:self-auto">
                        <?= htmlspecialchars($current_ym) ?> vs <?= htmlspecialchars($prev_ym) ?>
                    </span>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-8 items-center py-2">
                    <div class="space-y-5">
                        <div class="space-y-2">
                            <div class="flex justify-between text-xs font-bold">
                                <span class="text-[#5f6368]">Last Month Views (<?= htmlspecialchars($prev_month_name) ?>)</span>
                                <span class="font-mono text-[#5f6368]"><?= $has_prev_month ? number_format($prev_month_views) . ' views' : '— (N/A)' ?></span>
                            </div>
                            <div class="w-full bg-[#f1f3f4] h-3.5 rounded-full overflow-hidden">
                                <div class="bg-slate-400 h-full rounded-full" style="width: <?= $has_historical_comparison ? min(100, max(15, ($prev_month_views / max(1, $current_month_views + $prev_month_views)) * 200)) : 0 ?>%"></div>
                            </div>
                        </div>
                        <div class="space-y-2">
                            <div class="flex justify-between text-xs font-bold">
                                <span class="text-[#0b57d0]">Current Month Views (<?= htmlspecialchars($current_month_name) ?>)</span>
                                <span class="font-mono text-[#0b57d0]"><?= $has_current_month ? number_format($current_month_views) . ' views' : ($total_views > 0 ? number_format($total_views) . ' views' : '0 views') ?></span>
                            </div>
                            <div class="w-full bg-[#f1f3f4] h-3.5 rounded-full overflow-hidden">
                                <div class="bg-blue-600 h-full rounded-full" style="width: <?= $total_views > 0 ? ($has_historical_comparison ? min(100, max(20, ($current_month_views / max(1, $current_month_views + $prev_month_views)) * 200)) : 100) : 0 ?>%"></div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-[#f8f9fa] rounded-2xl p-5 border border-[#e0e4eb] space-y-3">
                        <h4 class="font-bold text-sm text-[#111827]">Traffic Growth Insight</h4>
                        <?php if ($growth_rate !== null): ?>
                            <p class="text-xs text-[#5f6368] leading-relaxed">
                                Your active button pages generated <span class="font-bold text-[#137333]"><?= number_format($current_month_views) ?></span> views in <span class="font-semibold text-slate-800"><?= htmlspecialchars($current_month_name) ?></span>, representing a <span class="font-bold text-[#0b57d0]"><?= $growth_rate >= 0 ? '+' : '' ?><?= $growth_rate ?>%</span> change compared to <span class="font-semibold text-slate-800"><?= htmlspecialchars($prev_month_name) ?></span>.
                            </p>
                        <?php else: ?>
                            <p class="text-xs text-[#5f6368] leading-relaxed">
                                No previous month history recorded for <?= htmlspecialchars($prev_month_name) ?>. Growth rate calculation: <span class="font-bold text-[#444746]">N/A</span>.
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Top 10 Most Viewed Pages Table -->
            <div class="bg-white rounded-3xl p-6 sm:p-8 border border-[#e0e4eb] shadow-xs space-y-6">
                <div class="flex items-center justify-between border-b border-[#f1f3f4] pb-4">
                    <div>
                        <h3 class="text-base font-bold text-[#1f1f1f]">Top 10 Most Viewed Episode Pages</h3>
                        <p class="text-xs text-[#5f6368]">Ranked by total visitor views and traffic volume.</p>
                    </div>
                </div>

                <?php if (empty($top_pages)): ?>
                    <div class="text-center py-12 text-[#747775] text-xs">No pages generated yet.</div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="border-b border-[#e1e7f0] text-[11px] font-bold text-[#5f6368] uppercase tracking-wider">
                                    <th class="py-3 px-4">Rank</th>
                                    <th class="py-3 px-4">Page Title &amp; Slug</th>
                                    <th class="py-3 px-4 text-center">Views</th>
                                    <th class="py-3 px-4 text-right">Created</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[#f1f3f4] text-xs">
                                <?php foreach ($top_pages as $idx => $p): ?>
                                    <tr class="hover:bg-[#fafcff] transition-colors">
                                        <td class="py-3.5 px-4 font-mono font-extrabold text-[#0b57d0]">#<?= $idx + 1 ?></td>
                                        <td class="py-3.5 px-4">
                                            <div class="font-bold text-[#111827] truncate max-w-sm"><?= htmlspecialchars($p['title']) ?></div>
                                            <div class="text-[11px] font-mono text-[#5f6368]">/p/<?= htmlspecialchars($p['slug']) ?></div>
                                        </td>
                                        <td class="py-3.5 px-4 text-center font-mono font-bold text-[#137333]"><?= number_format(intval($p['views'] ?? 0)) ?></td>
                                        <td class="py-3.5 px-4 text-right font-mono text-[#747775]"><?= htmlspecialchars($p['created_at']) ?></td>
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
                            <span>📱 Device Breakdown</span>
                        </h4>
                        <span class="text-[10px] font-bold px-2 py-0.5 rounded bg-slate-100 text-slate-600 border border-slate-200">N/A</span>
                    </div>
                    <div class="space-y-3 text-xs">
                        <div>
                            <div class="flex justify-between font-medium mb-1 text-[#5f6368]">
                                <span>Mobile Smartphone</span>
                                <span class="font-mono font-bold text-slate-400">—</span>
                            </div>
                            <div class="w-full bg-[#f1f3f4] h-2 rounded-full overflow-hidden">
                                <div class="bg-slate-300 h-full rounded-full" style="width: 0%"></div>
                            </div>
                        </div>
                        <div>
                            <div class="flex justify-between font-medium mb-1 text-[#5f6368]">
                                <span>Desktop PC / Mac</span>
                                <span class="font-mono font-bold text-slate-400">—</span>
                            </div>
                            <div class="w-full bg-[#f1f3f4] h-2 rounded-full overflow-hidden">
                                <div class="bg-slate-300 h-full rounded-full" style="width: 0%"></div>
                            </div>
                        </div>
                        <div>
                            <div class="flex justify-between font-medium mb-1 text-[#5f6368]">
                                <span>Tablet iPad</span>
                                <span class="font-mono font-bold text-slate-400">—</span>
                            </div>
                            <div class="w-full bg-[#f1f3f4] h-2 rounded-full overflow-hidden">
                                <div class="bg-slate-300 h-full rounded-full" style="width: 0%"></div>
                            </div>
                        </div>
                    </div>
                    <p class="text-[10px] text-[#747775]">User-agent device telemetry not logged. Only real data displayed: N/A.</p>
                </div>

                <!-- Top Traffic Channels -->
                <div class="bg-white rounded-3xl p-6 border border-[#e0e4eb] shadow-xs space-y-4">
                    <div class="flex items-center justify-between">
                        <h4 class="font-bold text-sm text-[#111827] flex items-center gap-2">
                            <span>🌐 Top Traffic Channels</span>
                        </h4>
                        <span class="text-[10px] font-bold px-2 py-0.5 rounded bg-slate-100 text-slate-600 border border-slate-200">N/A</span>
                    </div>
                    <div class="space-y-3 text-xs">
                        <div>
                            <div class="flex justify-between font-medium mb-1 text-[#5f6368]">
                                <span>Direct &amp; Bookmarks</span>
                                <span class="font-mono font-bold text-slate-400">—</span>
                            </div>
                            <div class="w-full bg-[#f1f3f4] h-2 rounded-full overflow-hidden">
                                <div class="bg-slate-300 h-full rounded-full" style="width: 0%"></div>
                            </div>
                        </div>
                        <div>
                            <div class="flex justify-between font-medium mb-1 text-[#5f6368]">
                                <span>Social Media (Telegram/WhatsApp)</span>
                                <span class="font-mono font-bold text-slate-400">—</span>
                            </div>
                            <div class="w-full bg-[#f1f3f4] h-2 rounded-full overflow-hidden">
                                <div class="bg-slate-300 h-full rounded-full" style="width: 0%"></div>
                            </div>
                        </div>
                        <div>
                            <div class="flex justify-between font-medium mb-1 text-[#5f6368]">
                                <span>Organic Search &amp; Referrals</span>
                                <span class="font-mono font-bold text-slate-400">—</span>
                            </div>
                            <div class="w-full bg-[#f1f3f4] h-2 rounded-full overflow-hidden">
                                <div class="bg-slate-300 h-full rounded-full" style="width: 0%"></div>
                            </div>
                        </div>
                    </div>
                    <p class="text-[10px] text-[#747775]">HTTP Referrer channels not logged. Only real data displayed: N/A.</p>
                </div>

                <!-- Top Traffic Country -->
                <div class="bg-white rounded-3xl p-6 border border-[#e0e4eb] shadow-xs space-y-4">
                    <div class="flex items-center justify-between">
                        <h4 class="font-bold text-sm text-[#111827] flex items-center gap-2">
                            <span>📍 Top Traffic Country</span>
                        </h4>
                        <span class="text-[10px] font-bold px-2 py-0.5 rounded bg-slate-100 text-slate-600 border border-slate-200">N/A</span>
                    </div>
                    <div class="space-y-3 text-xs">
                        <div>
                            <div class="flex justify-between font-medium mb-1 text-[#5f6368]">
                                <span>Primary Region</span>
                                <span class="font-mono font-bold text-slate-400">—</span>
                            </div>
                            <div class="w-full bg-[#f1f3f4] h-2 rounded-full overflow-hidden">
                                <div class="bg-slate-300 h-full rounded-full" style="width: 0%"></div>
                            </div>
                        </div>
                        <div>
                            <div class="flex justify-between font-medium mb-1 text-[#5f6368]">
                                <span>Secondary Region</span>
                                <span class="font-mono font-bold text-slate-400">—</span>
                            </div>
                            <div class="w-full bg-[#f1f3f4] h-2 rounded-full overflow-hidden">
                                <div class="bg-slate-300 h-full rounded-full" style="width: 0%"></div>
                            </div>
                        </div>
                        <div>
                            <div class="flex justify-between font-medium mb-1 text-[#5f6368]">
                                <span>Other Regions</span>
                                <span class="font-mono font-bold text-slate-400">—</span>
                            </div>
                            <div class="w-full bg-[#f1f3f4] h-2 rounded-full overflow-hidden">
                                <div class="bg-slate-300 h-full rounded-full" style="width: 0%"></div>
                            </div>
                        </div>
                    </div>
                    <p class="text-[10px] text-[#747775]">GeoIP visitor country tracking not enabled. Only real data displayed: N/A.</p>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
