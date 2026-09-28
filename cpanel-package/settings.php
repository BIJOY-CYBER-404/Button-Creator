<?php
/**
 * Admin Panel: /settings
 * Matches React AdminSidebar + AppHeader + SettingsManager design
 * Manage Backup & Restore Center, Site Branding & Logo, Maintenance Mode, AdSense & Banner Ads, Public Navigation Menu, and Footer Copyright.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/class-db.php';
require_once __DIR__ . '/includes/class-auth.php';
require_once __DIR__ . '/includes/class-datastore.php';

SLEA_Auth::require_admin();
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0, post-check=0, pre-check=0');
header('Pragma: no-cache');
header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
if (function_exists('opcache_invalidate')) {
    @opcache_invalidate(__FILE__, true);
}

$current_user = SLEA_Auth::get_current_user();

$menu_items = SLEA_Datastore::get_menu_items();
$footer_copyright = SLEA_Datastore::get_footer_copyright();
$ad_settings = SLEA_Datastore::get_ad_settings();
$site_identity = SLEA_Datastore::get_site_identity();
$maintenance_settings = SLEA_Datastore::get_maintenance_settings();
$server_snapshots = method_exists('SLEA_Datastore', 'list_server_snapshots') ? SLEA_Datastore::list_server_snapshots() : [];
$site_name = !empty($site_identity['site_name']) ? $site_identity['site_name'] : APP_NAME;
$site_logo_url = !empty($site_identity['site_logo_url']) ? $site_identity['site_logo_url'] : '';

$words = preg_split('/\s+/', trim($site_name));
$initials = '';
foreach ($words as $w) {
    if ($w !== '') {
        $initials .= strtoupper(substr($w, 0, 1));
    }
}
$brand_initials = substr($initials ?: 'MHQ', 0, 3);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Settings (/settings) - <?= htmlspecialchars($site_name) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        .font-mono { font-family: 'JetBrains Mono', monospace; }
        .shadow-2xs { box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05); }
        .shadow-xs { box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05); }
    </style>
</head>
<body class="w-full min-h-screen bg-[#f0f4f9] text-[#1f1f1f] flex flex-col font-sans antialiased overflow-x-hidden selection:bg-[#d3e3fd] selection:text-[#041e49]">
    <!-- Left Icon Sidebar (Matches React AdminSidebar.tsx) -->
    <aside id="admin-sidebar" class="fixed inset-y-0 left-0 w-16 sm:w-20 bg-white border-r border-[#e1e7f0] z-40 flex flex-col items-center py-4 justify-between shadow-xs select-none transition-colors" aria-label="Admin Navigation Sidebar">
        <div class="flex flex-col items-center w-full gap-5">
            <!-- Brand Logo / Site Identity -->
            <a href="admin.php" class="w-10 h-10 sm:w-11 sm:h-11 rounded-2xl bg-[#c2e7ff] text-[#001d35] flex items-center justify-center font-black text-lg shadow-2xs hover:scale-105 transition-transform cursor-pointer overflow-hidden p-1" title="<?= htmlspecialchars($site_name) ?>">
                <?php if (!empty($site_logo_url)): ?>
                    <img src="<?= htmlspecialchars($site_logo_url) ?>" alt="<?= htmlspecialchars($site_name) ?>" class="w-full h-full object-contain rounded-xl" />
                <?php else: ?>
                    <span class="text-xs font-black tracking-tight"><?= htmlspecialchars($brand_initials) ?></span>
                <?php endif; ?>
            </a>

            <!-- Navigation Icon Buttons -->
            <nav class="flex flex-col items-center w-full gap-2 px-1 sm:px-2">
                <!-- Generate -->
                <a href="admin.php" class="w-12 h-12 rounded-2xl flex flex-col items-center justify-center text-center transition-all cursor-pointer group relative text-[#444746] hover:bg-[#f0f4f9] hover:text-[#0b57d0]" title="Generate (+ Generator)">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                        <circle cx="12" cy="12" r="10"/><path d="M8 12h8"/><path d="M12 8v8"/>
                    </svg>
                    <span class="text-[9px] font-bold mt-0.5 tracking-tight text-[#5f6368] group-hover:text-[#0b57d0]">Generate</span>
                </a>

                <!-- Pages -->
                <a href="pages.php" class="w-12 h-12 rounded-2xl flex flex-col items-center justify-center text-center transition-all cursor-pointer group relative text-[#444746] hover:bg-[#f0f4f9] hover:text-[#0b57d0]" title="Pages (/pages)">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                        <path d="m12.83 2.18a2 2 0 0 0-1.66 0L2.6 6.08a1 1 0 0 0 0 1.83l8.58 3.91a2 2 0 0 0 1.66 0l8.58-3.9a1 1 0 0 0 0-1.83Z"/><path d="m22 17.65-9.17 4.16a2 2 0 0 1-1.66 0L2 17.65"/><path d="m22 12.65-9.17 4.16a2 2 0 0 1-1.66 0L2 12.65"/>
                    </svg>
                    <span class="text-[9px] font-bold mt-0.5 tracking-tight text-[#5f6368] group-hover:text-[#0b57d0]">Pages</span>
                </a>

                <!-- Settings (Active) -->
                <a href="settings.php" aria-current="page" class="w-12 h-12 rounded-2xl flex flex-col items-center justify-center text-center transition-all cursor-pointer group relative bg-[#0b57d0] text-white shadow-xs" title="Settings (/settings)">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                        <path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/>
                    </svg>
                    <span class="text-[9px] font-bold mt-0.5 tracking-tight text-white">Settings</span>
                </a>

                <!-- Update -->
                <a href="update.php" class="w-12 h-12 rounded-2xl flex flex-col items-center justify-center text-center transition-all cursor-pointer group relative text-[#444746] hover:bg-[#f0f4f9] hover:text-[#0b57d0]" title="One-Click System Updater (/update.php)">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                        <path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"/><path d="M21 3v5h-5"/><path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16"/><path d="M8 16H3v5"/>
                    </svg>
                    <span class="text-[9px] font-bold mt-0.5 tracking-tight text-[#5f6368] group-hover:text-[#0b57d0]">Update</span>
                </a>

                <!-- Analytics -->
                <a href="analytics.php" class="w-12 h-12 rounded-2xl flex flex-col items-center justify-center text-center transition-all cursor-pointer group relative text-[#444746] hover:bg-[#f0f4f9] hover:text-[#0b57d0]" title="Monthly Analytics & Statistics (/analytics.php)">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                        <path d="M3 3v16a2 2 0 0 0 2 2h16"/><path d="M18 17V9"/><path d="M13 17V5"/><path d="M8 17v-3"/>
                    </svg>
                    <span class="text-[9px] font-bold mt-0.5 tracking-tight text-[#5f6368] group-hover:text-[#0b57d0]">Analytics</span>
                </a>
            </nav>
        </div>

        <!-- Bottom Area: Admin Status Badge & Logout & Version -->
        <div class="flex flex-col items-center w-full gap-2 px-1 sm:px-2 pb-3">
            <div class="w-8 h-8 rounded-full bg-[#e8f0fe] text-[#0b57d0] flex items-center justify-center border border-[#d3e3fd]" title="Admin Mode Active (<?= htmlspecialchars($current_user['username']) ?>)">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                    <path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/><path d="m9 12 2 2 4-4"/>
                </svg>
            </div>

            <a href="logout.php" class="w-10 h-10 rounded-xl flex items-center justify-center text-[#c5221f] hover:bg-[#fce8e6] transition-colors cursor-pointer" title="Sign Out" aria-label="Sign Out">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" x2="9" y1="12" y2="12"/>
                </svg>
            </a>

            <span class="text-[10px] font-bold font-mono text-[#5f6368] bg-[#f0f4f9] px-1.5 py-0.5 rounded border border-[#e1e7f0] select-none" title="Movie Hub HQ Drive Version <?= htmlspecialchars(APP_VERSION) ?>">
                <?= htmlspecialchars(APP_VERSION) ?>
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
                                <span class="font-semibold text-[#0b57d0] truncate">Admin Settings (/settings)</span>
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

        <!-- Main Workspace (Matches React SettingsManager.tsx) -->
        <main class="flex-1 w-full max-w-5xl mx-auto px-3.5 sm:px-6 py-5 sm:py-7">
            <div class="space-y-8" id="settings-manager">
                <!-- Settings Header Card -->
                <div class="bg-white rounded-3xl p-6 sm:p-7 border border-[#e0e4eb] shadow-xs flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div class="flex items-center gap-3.5">
                        <div class="w-12 h-12 rounded-2xl bg-[#c2e7ff] text-[#001d35] flex items-center justify-center font-bold shadow-2xs">
                            <svg class="w-6 h-6 text-[#0b57d0]" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                <path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/>
                            </svg>
                        </div>
                        <div>
                            <h2 class="text-lg font-bold text-[#1f1f1f] leading-tight flex items-center gap-2">
                                <span>Admin Configuration</span>
                                <span class="text-xs font-semibold px-2.5 py-0.5 rounded-full bg-[#e8f0fe] text-[#0b57d0] border border-[#d3e3fd]">
                                    Active
                                </span>
                            </h2>
                            <p class="text-xs text-[#5f6368] mt-0.5">
                                Manage AdSense &amp; custom banners, public navigation bar, and footer copyright text.
                            </p>
                        </div>
                    </div>

                    <div class="text-xs text-[#5f6368] bg-[#f0f4f9] px-3.5 py-2 rounded-xl border border-[#e1e7f0] flex items-center gap-2">
                        <svg class="w-4 h-4 text-[#0b57d0]" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                            <circle cx="12" cy="12" r="10"/><path d="M12 2a14.5 14.5 0 0 0 0 20 14.5 14.5 0 0 0 0-20"/><path d="M2 12h20"/>
                        </svg>
                        <span>Synced with cPanel <code>settings.php</code> specs</span>
                    </div>
                </div>

                <!-- TOP: Backup & Restore Center (Matches React SettingsManager.tsx) -->
                <div class="bg-white rounded-3xl p-6 sm:p-8 border-2 border-[#c2e7ff] shadow-sm space-y-6" id="backup-restore-section">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 pb-4 border-b border-[#f0f4f9]" id="backup-restore-center">
                        <div>
                            <h3 class="font-bold text-base text-[#1f1f1f] flex items-center gap-2 flex-wrap">
                                <span>💾 Backup &amp; Restore Center</span>
                                <span class="text-[11px] font-mono px-2.5 py-0.5 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200">
                                    Auto-Detect Restore • Zero Data Loss
                                </span>
                            </h3>
                            <p class="text-xs text-[#5f6368] mt-0.5">
                                Backup <strong>Website Settings</strong>, <strong>Generated Pages</strong>, and <strong>Others (Admin Accounts &amp; Analytics)</strong> all together or separately. Restore automatically detects any backup file format!
                            </p>
                        </div>
                        <span class="text-[11px] font-bold px-2.5 py-1 rounded-full bg-[#e8f0fe] text-[#0b57d0] border border-[#c2e7ff] self-start sm:self-auto">
                            Modular &amp; Update-Safe
                        </span>
                    </div>

                    <!-- 1. Download Portable JSON Backups -->
                    <div class="space-y-3">
                        <div class="flex items-center justify-between">
                            <h4 class="text-xs font-bold uppercase tracking-wider text-[#444746]">
                                1. Download Backup (.json) — All or Separately
                            </h4>
                            <span class="text-[11px] text-[#5f6368]">Instant JSON export</span>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                            <a href="api.php?action=export_backup&amp;scope=all&amp;download=1" class="p-4 rounded-2xl bg-[#f8fafd] hover:bg-[#e8f0fe] border border-[#d3e3fd] text-left transition-all flex flex-col justify-between gap-3 cursor-pointer">
                                <div class="space-y-1">
                                    <div class="flex items-center justify-between">
                                        <span class="text-xs font-extrabold text-[#0b57d0]">📦 Full Backup (All)</span>
                                        <span class="text-[10px] font-mono px-1.5 py-0.5 rounded bg-blue-100 text-blue-800 font-bold">ALL</span>
                                    </div>
                                    <p class="text-[11px] text-[#5f6368]">Settings + Generated Pages + Accounts &amp; Analytics.</p>
                                </div>
                                <span class="text-[11px] font-bold text-[#0b57d0]">⬇ Download Full Backup</span>
                            </a>

                            <a href="api.php?action=export_backup&amp;scope=settings&amp;download=1" class="p-4 rounded-2xl bg-[#f8fafd] hover:bg-purple-50 border border-purple-200 text-left transition-all flex flex-col justify-between gap-3 cursor-pointer">
                                <div class="space-y-1">
                                    <div class="flex items-center justify-between">
                                        <span class="text-xs font-extrabold text-purple-700">⚙️ Settings Only</span>
                                        <span class="text-[10px] font-mono px-1.5 py-0.5 rounded bg-purple-100 text-purple-800 font-bold">SETTINGS</span>
                                    </div>
                                    <p class="text-[11px] text-[#5f6368]">Branding, Logo, Menu, Footer HTML, Ads &amp; Maintenance.</p>
                                </div>
                                <span class="text-[11px] font-bold text-purple-700">⬇ Download Settings Only</span>
                            </a>

                            <a href="api.php?action=export_backup&amp;scope=pages&amp;download=1" class="p-4 rounded-2xl bg-[#f8fafd] hover:bg-emerald-50 border border-emerald-200 text-left transition-all flex flex-col justify-between gap-3 cursor-pointer">
                                <div class="space-y-1">
                                    <div class="flex items-center justify-between">
                                        <span class="text-xs font-extrabold text-emerald-700">📄 Pages Only</span>
                                        <span class="text-[10px] font-mono px-1.5 py-0.5 rounded bg-emerald-100 text-emerald-800 font-bold">PAGES</span>
                                    </div>
                                    <p class="text-[11px] text-[#5f6368]">All generated episode button pages, slugs, links &amp; views.</p>
                                </div>
                                <span class="text-[11px] font-bold text-emerald-700">⬇ Download Pages Only</span>
                            </a>

                            <a href="api.php?action=export_backup&amp;scope=others&amp;download=1" class="p-4 rounded-2xl bg-[#f8fafd] hover:bg-amber-50 border border-amber-200 text-left transition-all flex flex-col justify-between gap-3 cursor-pointer">
                                <div class="space-y-1">
                                    <div class="flex items-center justify-between">
                                        <span class="text-xs font-extrabold text-amber-800">👤 Others Only</span>
                                        <span class="text-[10px] font-mono px-1.5 py-0.5 rounded bg-amber-100 text-amber-800 font-bold">OTHERS</span>
                                    </div>
                                    <p class="text-[11px] text-[#5f6368]">Admin accounts, permissions &amp; monthly view statistics.</p>
                                </div>
                                <span class="text-[11px] font-bold text-amber-800">⬇ Download Others Only</span>
                            </a>
                        </div>
                    </div>

                    <!-- 2. Restore from Backup File — Automatic Detection -->
                    <div class="p-5 rounded-2xl bg-[#f8fafd] border border-[#d3e3fd] space-y-4">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                            <div>
                                <h4 class="text-xs font-bold uppercase tracking-wider text-[#111827] flex items-center gap-2 flex-wrap">
                                    <span>2. Restore from Backup File (.json)</span>
                                    <span class="text-[10px] font-mono px-2 py-0.5 rounded-full bg-blue-100 text-[#0b57d0] font-bold">
                                        ✨ Auto-Detects Backup Type Automatically
                                    </span>
                                </h4>
                                <p class="text-[11px] text-[#5f6368] mt-0.5">
                                    Simply upload any backup JSON file (Full Backup, Settings Only, Pages Only, or Others Only). The system automatically detects what is inside and restores it safely without losing any other data.
                                </p>
                            </div>
                        </div>

                        <div id="autoDetectBackupBanner" class="hidden p-3.5 rounded-xl bg-white border border-emerald-200 flex items-center justify-between gap-3">
                            <div class="flex items-center gap-2.5">
                                <span class="w-7 h-7 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center font-bold text-sm">
                                    ✓
                                </span>
                                <div>
                                    <div class="text-xs font-bold text-[#1f1f1f]">Auto-Detected Backup Content:</div>
                                    <div id="autoDetectBackupText" class="text-[11px] text-emerald-700 font-mono">Inspecting backup file...</div>
                                </div>
                            </div>
                            <span id="autoDetectBackupStatus" class="text-[10px] font-mono px-2.5 py-1 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200 font-bold">
                                ✓ Restored Automatically
                            </span>
                        </div>

                        <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3 bg-white p-3.5 rounded-xl border border-[#e0e4eb]">
                            <div class="text-xs text-[#5f6368]">
                                Select any <code class="font-mono text-[#0b57d0]">.json</code> backup file — automatic detection &amp; safe restore runs immediately upon selection.
                            </div>
                            <label id="restoreFileBtnLabel" class="px-5 py-2.5 rounded-xl bg-[#0b57d0] hover:bg-[#0842a0] text-white font-bold text-xs shadow-xs transition-all cursor-pointer flex items-center justify-center gap-2 shrink-0">
                                <span id="restoreFileBtnText">⬆ Select &amp; Auto-Restore Backup (.json)</span>
                                <input
                                    type="file"
                                    id="restoreBackupFileInput"
                                    accept=".json,application/json"
                                    onchange="onBackupFileSelected(this)"
                                    class="hidden"
                                />
                            </label>
                        </div>
                    </div>

                    <!-- 3. One-Click Server Snapshots -->
                    <div class="p-5 rounded-2xl bg-[#f8fafd] border border-[#e1e7f0] space-y-4">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                            <div>
                                <h4 class="text-xs font-bold uppercase tracking-wider text-[#111827]">
                                    3. Instant Snapshots (Auto-Detected One-Click Restore)
                                </h4>
                                <p class="text-[11px] text-[#5f6368]">
                                    Create instant restore points for Full Backup, Settings Only, Pages Only, or Others Only.
                                </p>
                            </div>
                            <div class="flex items-center gap-2 flex-wrap">
                                <select id="snapshotScopeSelect" class="px-3 py-2 rounded-xl bg-white border border-[#c4c7c5] text-xs font-semibold">
                                    <option value="all">Scope: Full Everything</option>
                                    <option value="settings">Scope: Settings Only</option>
                                    <option value="pages">Scope: Pages Only</option>
                                    <option value="others">Scope: Others Only</option>
                                </select>
                                <input
                                    type="text"
                                    id="snapshotLabelInput"
                                    placeholder="Optional snapshot note..."
                                    class="px-3 py-2 rounded-xl bg-white border border-[#c4c7c5] text-xs w-44"
                                />
                                <button
                                    type="button"
                                    id="createSnapshotBtn"
                                    onclick="createServerSnapshot()"
                                    class="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs cursor-pointer"
                                >
                                    + Create Snapshot
                                </button>
                            </div>
                        </div>

                        <div id="snapshotsListContainer" class="space-y-2 max-h-64 overflow-y-auto">
                            <?php if (empty($server_snapshots)): ?>
                                <div class="text-center py-5 text-xs text-[#5f6368] bg-white rounded-xl border border-dashed border-[#c4c7c5]">
                                    No snapshots created yet. Click <strong>+ Create Snapshot</strong> above to save an instant restore point.
                                </div>
                            <?php else: ?>
                                <?php foreach ($server_snapshots as $snap): ?>
                                    <div class="p-3 bg-white rounded-xl border border-[#e0e4eb] flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                                        <div>
                                            <div class="flex items-center gap-2 flex-wrap">
                                                <span class="text-xs font-bold text-[#1f1f1f]"><?= htmlspecialchars($snap['label']) ?></span>
                                                <span class="text-[10px] font-mono px-2 py-0.5 rounded bg-blue-50 text-blue-700 font-bold uppercase"><?= htmlspecialchars($snap['scope']) ?></span>
                                                <span class="text-[10px] font-mono text-[#747775]"><?= htmlspecialchars($snap['created_at']) ?></span>
                                            </div>
                                            <div class="text-[11px] font-mono text-[#5f6368]">
                                                Settings: <?= intval($snap['counts']['settings'] ?? 0) ?> • Pages: <?= intval($snap['counts']['pages'] ?? 0) ?> • Others: <?= intval($snap['counts']['users'] ?? 0) ?>
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-1.5 flex-wrap shrink-0">
                                            <button type="button" onclick="restoreSnapshot('<?= htmlspecialchars($snap['filename'], ENT_QUOTES) ?>', 'auto')" class="px-3 py-1.5 rounded-lg bg-[#0b57d0] hover:bg-[#0842a0] text-white text-[11px] font-bold cursor-pointer">
                                                ↻ Restore (Auto-Detect)
                                            </button>
                                            <a href="api.php?action=download_snapshot&amp;filename=<?= urlencode($snap['filename']) ?>" class="px-2.5 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 text-[11px] font-bold" title="Download Snapshot JSON">
                                                ⬇
                                            </a>
                                            <button type="button" onclick="deleteSnapshot('<?= htmlspecialchars($snap['filename'], ENT_QUOTES) ?>')" class="px-2.5 py-1.5 rounded-lg bg-rose-50 hover:bg-rose-100 text-rose-600 text-[11px] font-bold cursor-pointer">
                                                ✕
                                            </button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- 0. Site Branding & Logo Configuration Form -->
                <div class="bg-white rounded-3xl p-6 sm:p-8 border border-[#e0e4eb] shadow-xs space-y-6">
                    <div class="flex items-center justify-between pb-4 border-b border-[#f0f4f9]">
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded-xl bg-[#c2e7ff] text-[#001d35] flex items-center justify-center font-bold">
                                <svg class="w-5 h-5 text-[#0b57d0]" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                    <path d="M9.937 15.5A2 2 0 0 0 8.5 14.063l-6.135-1.582a.5.5 0 0 1 0-.962L8.5 9.936A2 2 0 0 0 9.937 8.5l1.582-6.135a.5.5 0 0 1 .963 0L14.063 8.5A2 2 0 0 0 15.5 9.937l6.135 1.581a.5.5 0 0 1 0 .964L15.5 14.063a2 2 0 0 0-1.437 1.437l-1.582 6.135a.5.5 0 0 1-.963 0z"/><path d="M20 3v4"/><path d="M22 5h-4"/><path d="M4 17v2"/><path d="M5 18H3"/>
                                </svg>
                            </div>
                            <div>
                                <h3 class="font-bold text-base text-[#1f1f1f]">Site Branding &amp; Logo</h3>
                                <p class="text-xs text-[#5f6368]">
                                    Configure public website name and logo image URL.
                                </p>
                            </div>
                        </div>
                        <span class="text-[11px] font-semibold px-2.5 py-1 rounded-full bg-[#e8f0fe] text-[#0b57d0]">
                            <?= htmlspecialchars($site_name) ?>
                        </span>
                    </div>

                    <div class="space-y-5">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                            <div class="space-y-1.5">
                                <label class="text-xs font-bold text-[#444746] block">
                                    Website Name
                                </label>
                                <input
                                    type="text"
                                    id="siteNameInput"
                                    value="<?= htmlspecialchars($site_identity['site_name'] ?? 'Movie Hub HQ Drive') ?>"
                                    oninput="updateLogoLivePreview()"
                                    placeholder="Movie Hub HQ Drive"
                                    class="w-full px-3.5 py-2.5 rounded-xl border border-[#c4c7c5] text-xs font-medium focus:border-[#0b57d0] focus:ring-1 focus:ring-[#0b57d0] outline-none"
                                />
                                <span class="text-[11px] text-[#5f6368] block">
                                    Public gateway brand title displayed on headers and sidebar.
                                </span>
                            </div>

                            <div class="space-y-1.5">
                                <label class="text-xs font-bold text-[#444746] block">
                                    Site Logo Image URL (Optional)
                                </label>
                                <div class="relative">
                                    <input
                                        type="url"
                                        id="siteLogoUrlInput"
                                        value="<?= htmlspecialchars($site_identity['site_logo_url'] ?? '') ?>"
                                        oninput="updateLogoLivePreview()"
                                        placeholder="https://example.com/logo.png"
                                        class="w-full pl-9 pr-3.5 py-2.5 rounded-xl border border-[#c4c7c5] text-xs font-mono focus:border-[#0b57d0] focus:ring-1 focus:ring-[#0b57d0] outline-none"
                                    />
                                    <svg class="w-4 h-4 text-[#747775] absolute left-3 top-3" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                        <rect width="18" height="18" x="3" y="3" rx="2" ry="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/>
                                    </svg>
                                </div>
                                <span class="text-[11px] text-[#5f6368] block">
                                    Provide a direct image URL (PNG, SVG, WebP). When provided, text fallback is suppressed.
                                </span>
                            </div>

                            <div class="space-y-1.5 md:col-span-2">
                                <label class="text-xs font-bold text-[#444746] block">
                                    Brand Live Preview
                                </label>
                                <div class="p-4 bg-[#f8fafd] border border-[#e0e4eb] rounded-xl flex items-center justify-between min-h-[64px]">
                                    <div class="flex items-center gap-2.5 min-w-0" id="liveBrandPreview">
                                        <?php if (!empty($site_identity['site_logo_url'])): ?>
                                            <img id="previewLogoImg" src="<?= htmlspecialchars($site_identity['site_logo_url']) ?>" alt="Logo Preview" class="h-8 max-w-[180px] object-contain rounded" />
                                            <span id="previewSiteName" class="hidden font-bold text-sm text-[#111827] truncate"><?= htmlspecialchars($site_identity['site_name'] ?? 'Movie Hub HQ Drive') ?></span>
                                        <?php else: ?>
                                            <img id="previewLogoImg" src="" alt="Logo Preview" class="hidden h-8 max-w-[180px] object-contain rounded" />
                                            <span id="previewSiteName" class="font-bold text-sm text-[#111827] truncate"><?= htmlspecialchars($site_identity['site_name'] ?? 'Movie Hub HQ Drive') ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <span class="px-2 py-0.5 rounded-md bg-[#e6f4ea] text-[#137333] text-[10px] font-bold shrink-0">
                                        M3 Light Preview
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="flex justify-end pt-2">
                            <button
                                type="button"
                                id="saveIdentityBtn"
                                onclick="saveSiteIdentity()"
                                class="px-5 py-2.5 rounded-full bg-[#0b57d0] hover:bg-[#0842a0] text-white font-bold text-xs flex items-center gap-2 shadow-xs transition-colors cursor-pointer"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                    <path d="M15.2 3a2 2 0 0 1 1.4.6l3.8 3.8a2 2 0 0 1 .6 1.4V19a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z"/><path d="M17 21v-7a1 1 0 0 0-1-1H8a1 1 0 0 0-1 1v7"/><path d="M7 3v4a1 1 0 0 0 1 1h7"/>
                                </svg>
                                <span>Save Site Branding &amp; Logo</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- 🛠️ Maintenance Mode Form -->
                <div class="bg-white rounded-3xl p-6 sm:p-8 border border-[#e0e4eb] shadow-xs space-y-6">
                    <div class="flex items-center justify-between pb-4 border-b border-[#f0f4f9]">
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded-xl bg-[#fef7e0] text-[#b06000] flex items-center justify-center font-bold">
                                <svg class="w-5 h-5 text-[#b06000]" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                    <path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/><path d="M12 8v4"/><path d="M12 16h.01"/>
                                </svg>
                            </div>
                            <div>
                                <h3 class="font-bold text-base text-[#1f1f1f]">Maintenance Mode</h3>
                                <p class="text-xs text-[#5f6368]">
                                    Temporarily restrict public access to the portal with an interactive creative screen.
                                </p>
                            </div>
                        </div>
                        <span id="maintenanceStatusBadge" class="text-[11px] font-semibold px-2.5 py-1 rounded-full bg-[#fef7e0] text-[#b06000] border border-[#feebc8]">
                            Status: <?= !empty($maintenance_settings['enabled']) ? 'Active' : 'Inactive' ?>
                        </span>
                    </div>

                    <div class="space-y-5">
                        <div class="grid grid-cols-1 md:grid-cols-12 gap-6">
                            <!-- Inputs Column -->
                            <div class="md:col-span-7 space-y-5">
                                <div class="flex items-center justify-between p-4 bg-[#f8fafd] rounded-2xl border border-[#e1e7f0]">
                                    <div>
                                        <span class="text-xs font-bold text-[#1f1f1f] block">Enable Maintenance Mode</span>
                                        <span class="text-[10px] text-[#5f6368]">Toggle to block or resume public site access instantly.</span>
                                    </div>
                                    <label class="relative inline-flex items-center cursor-pointer">
                                        <input
                                            type="checkbox"
                                            id="maintenanceEnabledInput"
                                            <?= !empty($maintenance_settings['enabled']) ? 'checked' : '' ?>
                                            onchange="updateMaintenanceLivePreview()"
                                            class="sr-only peer"
                                        />
                                        <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                                    </label>
                                </div>

                                <!-- End Time Countdown Configuration -->
                                <div class="space-y-2 p-4 bg-[#f8fafd] rounded-2xl border border-[#e1e7f0]">
                                    <div class="flex items-center justify-between">
                                        <label class="text-xs font-bold text-[#1f1f1f] flex items-center gap-1.5">
                                            <svg class="w-4 h-4 text-[#0b57d0]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
                                            <span>Maintenance End Time (Optional Countdown)</span>
                                        </label>
                                        <span class="text-[10px] text-[#0b57d0] font-semibold bg-[#e8f0fe] px-2 py-0.5 rounded-md">Live Countdown</span>
                                    </div>
                                    <input
                                        type="datetime-local"
                                        id="maintenanceEndTimeInput"
                                        value="<?= htmlspecialchars($maintenance_settings['end_time'] ?? '') ?>"
                                        onchange="updateMaintenanceLivePreview()"
                                        class="w-full px-3.5 py-2 rounded-xl border border-[#c4c7c5] focus:border-[#0b57d0] outline-none text-xs font-semibold bg-white"
                                    />
                                    <div class="flex items-center gap-1.5 flex-wrap pt-1">
                                        <span class="text-[10px] font-bold text-[#5f6368] uppercase mr-1">Quick Presets:</span>
                                        <button type="button" onclick="setMaintenancePreset(30)" class="px-2 py-1 rounded-lg bg-white border border-[#dadce0] hover:bg-[#e8f0fe] text-[11px] font-bold text-[#444746] cursor-pointer">+30m</button>
                                        <button type="button" onclick="setMaintenancePreset(60)" class="px-2 py-1 rounded-lg bg-white border border-[#dadce0] hover:bg-[#e8f0fe] text-[11px] font-bold text-[#444746] cursor-pointer">+1h</button>
                                        <button type="button" onclick="setMaintenancePreset(180)" class="px-2 py-1 rounded-lg bg-white border border-[#dadce0] hover:bg-[#e8f0fe] text-[11px] font-bold text-[#444746] cursor-pointer">+3h</button>
                                        <button type="button" onclick="setMaintenancePreset(1440)" class="px-2 py-1 rounded-lg bg-white border border-[#dadce0] hover:bg-[#e8f0fe] text-[11px] font-bold text-[#444746] cursor-pointer">+1d</button>
                                        <button type="button" onclick="clearMaintenancePreset()" class="px-2 py-1 rounded-lg bg-white border border-[#fce8e6] hover:bg-[#fce8e6] text-[11px] font-bold text-[#c5221f] cursor-pointer">Clear</button>
                                    </div>
                                </div>

                                <div class="space-y-1.5">
                                    <label class="text-xs font-bold text-[#444746] block">
                                        Custom Maintenance Message:
                                    </label>
                                    <textarea
                                        id="maintenanceMessageInput"
                                        rows="4"
                                        oninput="updateMaintenanceLivePreview()"
                                        placeholder="The website is currently undergoing scheduled maintenance. We will be back shortly!"
                                        class="w-full px-4 py-3 rounded-2xl border border-[#c4c7c5] text-xs font-medium focus:border-[#0b57d0] focus:ring-1 focus:ring-[#0b57d0] outline-none leading-relaxed"
                                    ><?= htmlspecialchars($maintenance_settings['message'] ?? '') ?></textarea>
                                    <span class="text-[10px] text-[#747775] block">Provide dynamic info about current system optimizations or upgrades.</span>
                                </div>
                            </div>

                            <!-- Preview Column (Matches React SettingsManager.tsx) -->
                            <div class="md:col-span-5 space-y-2">
                                <span class="text-[11px] font-bold uppercase text-[#5f6368] block">Public Preview Mockup:</span>
                                <div class="border border-[#e0e4eb] rounded-3xl p-4 bg-[#f8fafd] space-y-3 relative overflow-hidden">
                                    <div class="relative w-full aspect-[4/3] rounded-xl overflow-hidden bg-[#fafbfc] border border-[#f0f4f9] flex items-center justify-center">
                                        <div class="absolute inset-0 bg-[#0b57d0]/5 flex items-center justify-center text-[#0b57d0] font-mono text-[10px] text-center p-4">
                                            <div class="space-y-1">
                                                <div class="w-8 h-8 rounded-full border-2 border-[#0b57d0] border-t-transparent animate-spin mx-auto"></div>
                                                <span class="block font-bold">Optimization Engaged</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="text-center space-y-1">
                                        <div class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-[#fef7e0] border border-[#feebc8] text-[#b06000] text-[9px] font-bold font-mono">
                                            <span>SYSTEM MAINTENANCE</span>
                                        </div>
                                        <h4 class="text-xs font-black text-[#111827]">We'll Be Right Back</h4>
                                        <p id="maintenancePreviewMsg" class="text-[10px] text-[#5f6368] leading-normal line-clamp-2 px-2">
                                            <?= htmlspecialchars($maintenance_settings['message'] ?: 'The website is currently undergoing scheduled maintenance. We will be back shortly!') ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="flex justify-end pt-2">
                            <button
                                type="button"
                                id="saveMaintenanceBtn"
                                onclick="saveMaintenanceSettings()"
                                class="px-5 py-2.5 rounded-full bg-[#0b57d0] hover:bg-[#0842a0] text-white font-bold text-xs flex items-center gap-2 shadow-xs transition-colors cursor-pointer"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                    <path d="M15.2 3a2 2 0 0 1 1.4.6l3.8 3.8a2 2 0 0 1 .6 1.4V19a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z"/><path d="M17 21v-7a1 1 0 0 0-1-1H8a1 1 0 0 0-1 1v7"/><path d="M7 3v4a1 1 0 0 0 1 1h7"/>
                                </svg>
                                <span>Save Maintenance Settings</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- 1. Google AdSense & Banner Ads Form (Matches React SettingsManager.tsx) -->
                <div class="bg-white rounded-3xl p-6 sm:p-8 border border-[#e0e4eb] shadow-xs space-y-6">
                    <div class="flex items-center justify-between gap-3 pb-4 border-b border-[#f0f4f9]">
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center font-bold">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                    <line x1="4" x2="4" y1="21" y2="14"/><line x1="4" x2="4" y1="10" y2="3"/><line x1="12" x2="12" y1="21" y2="12"/><line x1="12" x2="12" y1="8" y2="3"/><line x1="20" x2="20" y1="21" y2="16"/><line x1="20" x2="20" y1="12" y2="3"/><line x1="2" x2="6" y1="14" y2="14"/><line x1="10" x2="14" y1="8" y2="8"/><line x1="18" x2="22" y1="16" y2="16"/>
                                </svg>
                            </div>
                            <div>
                                <h3 class="font-bold text-base text-[#1f1f1f]">AdSense &amp; Banner Ads Configuration</h3>
                                <p class="text-xs text-[#5f6368]">
                                    Insert Google AdSense Auto-Ads or custom responsive banner ad HTML/JavaScript code.
                                </p>
                            </div>
                        </div>
                        <label class="inline-flex items-center gap-2 text-xs font-bold text-[#1f1f1f] cursor-pointer">
                            <input type="checkbox" id="bannerAdsEnabled" class="w-4 h-4 text-[#0b57d0] rounded border-gray-300" <?= !isset($ad_settings['banner_ads_enabled']) || !empty($ad_settings['banner_ads_enabled']) ? 'checked' : '' ?> />
                            <span>Master Banner Switch</span>
                        </label>
                    </div>

                    <div class="space-y-5">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                            <div class="space-y-1.5">
                                <label class="text-xs font-bold text-[#444746] block">
                                    Google AdSense Publisher ID
                                </label>
                                <input
                                    type="text"
                                    id="adsenseClientId"
                                    value="<?= htmlspecialchars($ad_settings['adsense_client_id'] ?? '') ?>"
                                    placeholder="ca-pub-XXXXXXXXXXXXXXXX"
                                    class="w-full px-3.5 py-2.5 rounded-xl border border-[#c4c7c5] text-xs font-mono focus:border-[#0b57d0] focus:ring-1 focus:ring-[#0b57d0] outline-none"
                                />
                                <span class="text-[11px] text-[#5f6368] block">
                                    Optional: Auto-inserts <code>pagead2.googlesyndication.com</code> snippet in the page header.
                                </span>
                            </div>

                            <div class="space-y-2 pt-5">
                                <label class="flex items-center gap-2.5 cursor-pointer select-none">
                                    <input
                                        type="checkbox"
                                        id="adsenseAutoEnabled"
                                        <?= !empty($ad_settings['adsense_auto_enabled']) ? 'checked' : '' ?>
                                        class="w-4 h-4 text-[#0b57d0] rounded border-gray-300 focus:ring-[#0b57d0]"
                                    />
                                    <span class="text-xs font-bold text-[#1f1f1f]">Enable Google Auto-Ads</span>
                                </label>
                                <p class="text-[11px] text-[#5f6368] pl-6">
                                    Automatically places machine learning ads across optimal positions on the button page.
                                </p>
                            </div>
                        </div>

                        <div class="space-y-4 pt-2">
                            <!-- Top Banner -->
                            <div class="space-y-1.5">
                                <div class="flex items-center justify-between">
                                    <label class="text-xs font-bold text-[#444746] flex items-center gap-1.5">
                                        <svg class="w-3.5 h-3.5 text-[#0b57d0]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>
                                        <span>Top Banner Ad HTML (Placed under the navigation bar)</span>
                                    </label>
                                    <label class="inline-flex items-center gap-1.5 text-xs text-[#444746] cursor-pointer">
                                        <input type="checkbox" id="adTopEnabled" class="rounded text-[#0b57d0]" <?= !empty($ad_settings['ad_top_enabled']) ? 'checked' : '' ?> />
                                        <span class="font-semibold">Enabled</span>
                                    </label>
                                </div>
                                <textarea
                                    id="adTopCode"
                                    rows="3"
                                    class="w-full px-3.5 py-2.5 rounded-xl border border-[#c4c7c5] text-xs font-mono focus:border-[#0b57d0] outline-none"
                                    placeholder="Paste HTML/JS ad code here..."
                                ><?= htmlspecialchars($ad_settings['ad_top_code'] ?? '') ?></textarea>
                            </div>

                            <!-- Middle Banner -->
                            <div class="space-y-1.5">
                                <div class="flex items-center justify-between">
                                    <label class="text-xs font-bold text-[#444746] flex items-center gap-1.5">
                                        <svg class="w-3.5 h-3.5 text-[#0b57d0]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>
                                        <span>In-Between Episode Buttons Ad HTML (Placed between button rows)</span>
                                    </label>
                                    <label class="inline-flex items-center gap-1.5 text-xs text-[#444746] cursor-pointer">
                                        <input type="checkbox" id="adMiddleEnabled" class="rounded text-[#0b57d0]" <?= !empty($ad_settings['ad_middle_enabled']) ? 'checked' : '' ?> />
                                        <span class="font-semibold">Enabled</span>
                                    </label>
                                </div>
                                <textarea
                                    id="adMiddleCode"
                                    rows="3"
                                    class="w-full px-3.5 py-2.5 rounded-xl border border-[#c4c7c5] text-xs font-mono focus:border-[#0b57d0] outline-none"
                                    placeholder="Paste HTML/JS ad code here..."
                                ><?= htmlspecialchars($ad_settings['ad_middle_code'] ?? '') ?></textarea>
                            </div>

                            <!-- Bottom Banner -->
                            <div class="space-y-1.5">
                                <div class="flex items-center justify-between">
                                    <label class="text-xs font-bold text-[#444746] flex items-center gap-1.5">
                                        <svg class="w-3.5 h-3.5 text-[#0b57d0]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>
                                        <span>Bottom Banner Ad HTML (Placed above footer)</span>
                                    </label>
                                    <label class="inline-flex items-center gap-1.5 text-xs text-[#444746] cursor-pointer">
                                        <input type="checkbox" id="adBottomEnabled" class="rounded text-[#0b57d0]" <?= !empty($ad_settings['ad_bottom_enabled']) ? 'checked' : '' ?> />
                                        <span class="font-semibold">Enabled</span>
                                    </label>
                                </div>
                                <textarea
                                    id="adBottomCode"
                                    rows="3"
                                    class="w-full px-3.5 py-2.5 rounded-xl border border-[#c4c7c5] text-xs font-mono focus:border-[#0b57d0] outline-none"
                                    placeholder="Paste HTML/JS ad code here..."
                                ><?= htmlspecialchars($ad_settings['ad_bottom_code'] ?? '') ?></textarea>
                            </div>
                        </div>

                        <div class="flex justify-end pt-2">
                            <button
                                type="button"
                                id="saveAdsBtn"
                                onclick="saveAdSettings()"
                                class="px-5 py-2.5 rounded-xl bg-[#0b57d0] hover:bg-[#0842a0] text-white text-xs font-bold shadow-xs transition-all cursor-pointer flex items-center gap-1.5"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                    <path d="M15.2 3a2 2 0 0 1 1.4.6l3.8 3.8a2 2 0 0 1 .6 1.4V19a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z"/><path d="M17 21v-7a1 1 0 0 0-1-1H8a1 1 0 0 0-1 1v7"/><path d="M7 3v4a1 1 0 0 0 1 1h7"/>
                                </svg>
                                <span>Save Ad Settings</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- 2. Public Header Navigation Menu (Matches React SettingsManager.tsx) -->
                <div class="bg-white rounded-3xl p-6 sm:p-8 border border-[#e0e4eb] shadow-xs space-y-6">
                    <div class="flex items-center justify-between gap-3 pb-4 border-b border-[#f0f4f9]">
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center font-bold">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                    <line x1="4" x2="20" y1="12" y2="12"/><line x1="4" x2="20" y1="6" y2="6"/><line x1="4" x2="20" y1="18" y2="18"/>
                                </svg>
                            </div>
                            <div>
                                <h3 class="font-bold text-base text-[#1f1f1f]">Public Header Navigation Menu</h3>
                                <p class="text-xs text-[#5f6368]">
                                    Define links displayed across every generated button page header for visitor navigation.
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Existing Menu Items List -->
                    <div class="space-y-2" id="menuItemsContainer"></div>

                    <!-- Add New Menu Item Form (Matches React SettingsManager.tsx) -->
                    <form onsubmit="handleAddMenuItem(event)" class="pt-3 border-t border-[#f0f4f9] space-y-3">
                        <h4 class="text-xs font-bold text-[#1f1f1f]">+ Add New Navigation Link</h4>
                        <div class="grid grid-cols-1 sm:grid-cols-12 gap-3 items-end">
                            <div class="sm:col-span-4 space-y-1">
                                <label class="text-[11px] font-semibold text-[#5f6368]">Item Title</label>
                                <input
                                    type="text"
                                    id="newMenuTitle"
                                    placeholder="e.g. Movies"
                                    class="w-full px-3 py-2 rounded-xl border border-[#c4c7c5] text-xs focus:border-[#0b57d0] outline-none"
                                />
                            </div>
                            <div class="sm:col-span-5 space-y-1">
                                <label class="text-[11px] font-semibold text-[#5f6368]">Target URL</label>
                                <input
                                    type="text"
                                    id="newMenuUrl"
                                    placeholder="e.g. /movies or https://..."
                                    class="w-full px-3 py-2 rounded-xl border border-[#c4c7c5] text-xs font-mono focus:border-[#0b57d0] outline-none"
                                />
                            </div>
                            <div class="sm:col-span-3 flex items-center justify-between sm:justify-end gap-2">
                                <label class="flex items-center gap-1.5 text-xs text-[#5f6368] cursor-pointer">
                                    <input
                                        type="checkbox"
                                        id="newMenuBlank"
                                        class="rounded text-[#0b57d0]"
                                    />
                                    <span>New Tab</span>
                                </label>
                                <button
                                    type="submit"
                                    id="saveMenuBtn"
                                    class="px-4 py-2 rounded-xl bg-[#0b57d0] hover:bg-[#0842a0] text-white text-xs font-bold transition-all cursor-pointer flex items-center gap-1"
                                >
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
                                    <span>Add</span>
                                </button>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- 3. Footer Copyright Text (Matches React SettingsManager.tsx) -->
                <div class="bg-white rounded-3xl p-6 sm:p-8 border border-[#e0e4eb] shadow-xs space-y-5">
                    <div class="flex items-center gap-3 pb-4 border-b border-[#f0f4f9]">
                        <div class="w-9 h-9 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center font-bold">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                <circle cx="12" cy="12" r="10"/><path d="M12 2a14.5 14.5 0 0 0 0 20 14.5 14.5 0 0 0 0-20"/><path d="M2 12h20"/>
                            </svg>
                        </div>
                        <div>
                            <h3 class="font-bold text-base text-[#1f1f1f]">Footer Copyright &amp; Disclaimer</h3>
                            <p class="text-xs text-[#5f6368]">
                                Configures the copyright statement and legal disclaimer displayed at the bottom of public pages.
                            </p>
                        </div>
                    </div>

                    <div class="space-y-4">
                        <div class="space-y-1.5">
                            <label class="text-xs font-bold text-[#444746] block">Footer Content (HTML allowed)</label>
                            <div class="flex flex-wrap items-center gap-1.5 pb-2">
                                <span class="text-[11px] font-bold text-[#5f6368] self-center mr-1">Quick Emojis &amp; Symbols:</span>
                                <?php foreach (['🍿', '🎬', '❤️', '🚀', '⭐', '🎥', '📺', '🛡️', '💬', '📅', '✨', '🔥', '⚡', '🔒', '©️'] as $emoji): ?>
                                    <button type="button" onclick="insertPhpEmoji('<?= $emoji ?>')"
                                        class="w-7 h-7 rounded-lg bg-slate-50 hover:bg-slate-100 border border-[#e0e4eb] flex items-center justify-center text-sm transition-all cursor-pointer select-none active:scale-95"
                                        title="Insert <?= $emoji ?>">
                                        <?= $emoji ?>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                            <textarea
                                id="footerCopyrightInput"
                                rows="3"
                                oninput="updateFooterPreview()"
                                class="w-full px-3.5 py-2.5 rounded-xl border border-[#c4c7c5] text-xs font-mono focus:border-[#0b57d0] outline-none"
                                placeholder="&amp;copy; 2026 ... All rights reserved."
                            ><?= htmlspecialchars(html_entity_decode($footer_copyright, ENT_QUOTES | ENT_HTML5, 'UTF-8'), ENT_QUOTES, 'UTF-8') ?></textarea>
                        </div>

                        <div class="space-y-1.5">
                            <span class="text-[11px] font-bold uppercase text-[#747775]">Live Footer Preview:</span>
                            <div id="footerPreviewBox" class="p-4 rounded-xl bg-[#f8f9fa] border border-[#e0e4eb] text-xs text-center text-[#5f6368] overflow-hidden">
                                <?= $footer_copyright ?>
                            </div>
                        </div>

                        <div class="flex justify-end">
                            <button
                                type="button"
                                id="saveFooterBtn"
                                onclick="saveFooterCopyright()"
                                class="px-5 py-2.5 rounded-xl bg-[#0b57d0] hover:bg-[#0842a0] text-white text-xs font-bold shadow-xs transition-all cursor-pointer flex items-center gap-1.5"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                    <path d="M15.2 3a2 2 0 0 1 1.4.6l3.8 3.8a2 2 0 0 1 .6 1.4V19a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z"/><path d="M17 21v-7a1 1 0 0 0-1-1H8a1 1 0 0 0-1 1v7"/><path d="M7 3v4a1 1 0 0 0 1 1h7"/>
                                </svg>
                                <span>Save Footer Text</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Top Right Notification Toast Container (Matches React Toast.tsx) -->
    <aside id="toastContainer" aria-label="Notifications" class="fixed top-5 right-5 z-50 flex flex-col items-end gap-3 pointer-events-none max-w-sm w-[calc(100%-2.5rem)]"></aside>

    <script>
        let MENU_ITEMS = <?= json_encode(array_values($menu_items), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?> || [];

        function escapeToastHtml(str) {
            return String(str || '').replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/'/g, '&#039;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
        }

        function renderMenuItems() {
            const container = document.getElementById('menuItemsContainer');
            if (!container) return;
            if (MENU_ITEMS.length === 0) {
                container.innerHTML = `<div class="text-center py-4 text-xs text-[#747775] bg-[#f8fafd] rounded-2xl border border-dashed border-[#c4c7c5]">No navigation links configured yet. Add one below.</div>`;
                return;
            }
            container.innerHTML = MENU_ITEMS.map((item, idx) => {
                const isBlank = !!(item.new_tab || item.target_blank);
                return `
                    <div class="flex items-center justify-between gap-3 p-3 rounded-2xl bg-[#f8fafd] border border-[#e1e7f0]">
                        <div class="flex items-center gap-3 min-w-0">
                            <span class="w-6 h-6 rounded-full bg-white border border-[#c4c7c5] text-[10px] font-bold text-[#444746] flex items-center justify-center shrink-0">
                                ${idx + 1}
                            </span>
                            <div class="min-w-0">
                                <div class="text-xs font-bold text-[#1f1f1f] truncate">${escapeToastHtml(item.title)}</div>
                                <div class="text-[11px] font-mono text-[#5f6368] truncate">${escapeToastHtml(item.url)}</div>
                            </div>
                        </div>

                        <div class="flex items-center gap-2 shrink-0">
                            ${isBlank ? `<span class="text-[10px] font-semibold px-2 py-0.5 rounded-full bg-slate-100 text-slate-600">New Tab</span>` : ''}
                            <button
                                type="button"
                                onclick="removeMenuItem(${idx})"
                                class="p-1.5 rounded-lg text-red-600 hover:bg-red-50 transition-colors cursor-pointer"
                                title="Remove item"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                    <path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/><line x1="10" x2="10" y1="11" y2="17"/><line x1="14" x2="14" y1="11" y2="17"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                `;
            }).join('');
        }

        async function persistMenuItems(toastMsg) {
            try {
                const res = await fetch('api.php?action=save_menu_items', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ items: MENU_ITEMS })
                });
                const data = await res.json();
                if (data.success) {
                    showToast(toastMsg || 'Navigation menu updated successfully!', 'success');
                } else {
                    showToast('Error saving navigation menu: ' + (data.error || 'Unknown error'), 'error');
                }
            } catch (e) {
                showToast('Request failed: ' + e.message, 'error');
            }
        }

        async function handleAddMenuItem(e) {
            e.preventDefault();
            const titleEl = document.getElementById('newMenuTitle');
            const urlEl = document.getElementById('newMenuUrl');
            const blankEl = document.getElementById('newMenuBlank');

            const title = titleEl.value.trim();
            const url = urlEl.value.trim();
            const newTab = blankEl.checked;

            if (!title || !url) {
                showToast('Please provide both menu item title and destination URL.', 'error');
                return;
            }

            MENU_ITEMS.push({
                id: 'm_' + Math.random().toString(36).substring(2, 7),
                title: title,
                url: url,
                new_tab: newTab
            });

            titleEl.value = '';
            urlEl.value = '';
            blankEl.checked = false;
            renderMenuItems();
            await persistMenuItems(`Added navigation item "${title}"`);
        }

        async function removeMenuItem(idx) {
            MENU_ITEMS.splice(idx, 1);
            renderMenuItems();
            await persistMenuItems('Menu item removed.');
        }

        async function saveAdSettings() {
            const btn = document.getElementById('saveAdsBtn');
            btn.disabled = true;

            const payload = {
                adsense_auto_enabled: document.getElementById('adsenseAutoEnabled').checked,
                adsense_client_id: document.getElementById('adsenseClientId').value.trim(),
                banner_ads_enabled: document.getElementById('bannerAdsEnabled').checked,
                ad_top_enabled: document.getElementById('adTopEnabled').checked,
                ad_top_code: document.getElementById('adTopCode').value.trim(),
                ad_middle_enabled: document.getElementById('adMiddleEnabled').checked,
                ad_middle_code: document.getElementById('adMiddleCode').value.trim(),
                ad_bottom_enabled: document.getElementById('adBottomEnabled').checked,
                ad_bottom_code: document.getElementById('adBottomCode').value.trim()
            };

            try {
                const res = await fetch('api.php?action=save_ad_settings', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ ad_settings: payload })
                });
                const data = await res.json();
                if (data.success) {
                    showToast('AdSense & Banner Ad settings saved successfully!', 'success');
                } else {
                    showToast('Error saving ads: ' + (data.error || 'Unknown error'), 'error');
                }
            } catch (e) {
                showToast('Request failed: ' + e.message, 'error');
            } finally {
                btn.disabled = false;
            }
        }

        function updateFooterPreview() {
            const html = document.getElementById('footerCopyrightInput').value;
            document.getElementById('footerPreviewBox').innerHTML = html;
        }

        function insertPhpEmoji(emoji) {
            const input = document.getElementById('footerCopyrightInput');
            const start = input.selectionStart !== undefined ? input.selectionStart : input.value.length;
            const end = input.selectionEnd !== undefined ? input.selectionEnd : input.value.length;
            const val = input.value;
            input.value = val.substring(0, start) + ' ' + emoji + ' ' + val.substring(end);
            input.focus();
            updateFooterPreview();
        }

        async function saveFooterCopyright() {
            const btn = document.getElementById('saveFooterBtn');
            btn.disabled = true;

            const html = document.getElementById('footerCopyrightInput').value;

            try {
                const res = await fetch('api.php?action=save_footer_copyright', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json; charset=utf-8' },
                    body: JSON.stringify({ footer_html: html })
                });
                const data = await res.json();
                if (data.success) {
                    showToast('Footer copyright text updated successfully!', 'success');
                } else {
                    showToast('Error saving footer: ' + (data.error || 'Unknown error'), 'error');
                }
            } catch (e) {
                showToast('Request failed: ' + e.message, 'error');
            } finally {
                btn.disabled = false;
            }
        }

        function updateLogoLivePreview() {
            const name = document.getElementById('siteNameInput').value.trim() || 'Movie Hub HQ Drive';
            const logoUrl = document.getElementById('siteLogoUrlInput').value.trim();

            const previewName = document.getElementById('previewSiteName');
            const previewImg = document.getElementById('previewLogoImg');

            previewName.innerText = name;
            if (logoUrl) {
                previewImg.src = logoUrl;
                previewImg.classList.remove('hidden');
                previewName.classList.add('hidden');
            } else {
                previewImg.src = '';
                previewImg.classList.add('hidden');
                previewName.classList.remove('hidden');
            }
        }

        async function saveSiteIdentity() {
            const btn = document.getElementById('saveIdentityBtn');
            btn.disabled = true;

            const payload = {
                site_name: document.getElementById('siteNameInput').value.trim() || 'Movie Hub HQ Drive',
                site_logo_url: document.getElementById('siteLogoUrlInput').value.trim(),
            };

            try {
                const res = await fetch('api.php?action=save_site_identity', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ site_identity: payload })
                });
                const data = await res.json();
                if (data.success) {
                    showToast('Site branding & logo updated successfully!', 'success');
                } else {
                    showToast('Error saving site identity: ' + (data.error || 'Unknown error'), 'error');
                }
            } catch (e) {
                showToast('Request failed: ' + e.message, 'error');
            } finally {
                btn.disabled = false;
            }
        }

        function setMaintenancePreset(minutes) {
            const now = new Date();
            const future = new Date(now.getTime() + minutes * 60 * 1000);
            const year = future.getFullYear();
            const month = String(future.getMonth() + 1).padStart(2, '0');
            const day = String(future.getDate()).padStart(2, '0');
            const hours = String(future.getHours()).padStart(2, '0');
            const mins = String(future.getMinutes()).padStart(2, '0');

            document.getElementById('maintenanceEndTimeInput').value = `${year}-${month}-${day}T${hours}:${mins}`;
            updateMaintenanceLivePreview();
            showToast(`Maintenance countdown set to +${minutes >= 60 ? (minutes / 60) + ' hour(s)' : minutes + ' mins'}`, 'info');
        }

        function clearMaintenancePreset() {
            document.getElementById('maintenanceEndTimeInput').value = '';
            updateMaintenanceLivePreview();
            showToast('Maintenance end time countdown cleared.', 'info');
        }

        function updateMaintenanceLivePreview() {
            const msg = document.getElementById('maintenanceMessageInput').value.trim() || 'The website is currently undergoing scheduled maintenance. We will be back shortly!';
            const enabled = document.getElementById('maintenanceEnabledInput').checked;
            document.getElementById('maintenancePreviewMsg').innerText = msg;
            const statusBadge = document.getElementById('maintenanceStatusBadge');
            if (statusBadge) {
                statusBadge.innerText = 'Status: ' + (enabled ? 'Active' : 'Inactive');
            }
        }

        async function saveMaintenanceSettings() {
            const btn = document.getElementById('saveMaintenanceBtn');
            btn.disabled = true;

            const payload = {
                enabled: document.getElementById('maintenanceEnabledInput').checked,
                end_time: document.getElementById('maintenanceEndTimeInput').value.trim(),
                message: document.getElementById('maintenanceMessageInput').value.trim()
            };

            try {
                const res = await fetch('api.php?action=save_maintenance_settings', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ maintenance_settings: payload })
                });
                const data = await res.json();
                if (data.success) {
                    showToast('Maintenance settings saved successfully!', 'success');
                } else {
                    showToast('Error saving maintenance settings: ' + (data.error || 'Unknown error'), 'error');
                }
            } catch (e) {
                showToast('Request failed: ' + e.message, 'error');
            } finally {
                btn.disabled = false;
            }
        }

        function inspectBackupPayloadClient(parsed) {
            if (!parsed || typeof parsed !== 'object') return 'Invalid Backup File';
            const dataBlock = (parsed.data && typeof parsed.data === 'object') ? parsed.data : parsed;
            const parts = [];
            const hasSettings = (dataBlock.settings && typeof dataBlock.settings === 'object' && Object.keys(dataBlock.settings).length > 0)
                || ['site_identity', 'menu_items', 'footer_copyright', 'ad_settings', 'maintenance_settings'].some(k => k in parsed);
            const hasPages = Array.isArray(dataBlock.pages)
                || (Array.isArray(parsed) && parsed.length > 0 && parsed[0].slug);
            const hasOthers = (dataBlock.others && typeof dataBlock.others === 'object')
                || Array.isArray(parsed.users)
                || Array.isArray(parsed.page_views_monthly)
                || (Array.isArray(parsed) && parsed.length > 0 && (parsed[0].username || parsed[0].page_slug));

            if (hasSettings) {
                const sCount = dataBlock.settings ? Object.keys(dataBlock.settings).length : 1;
                parts.push(`Website Settings (${sCount})`);
            }
            if (hasPages) {
                const pCount = Array.isArray(dataBlock.pages) ? dataBlock.pages.length : (Array.isArray(parsed) ? parsed.length : 1);
                parts.push(`Generated Pages (${pCount})`);
            }
            if (hasOthers) {
                parts.push(`Others (Accounts & Analytics)`);
            }
            return parts.length > 0 ? parts.join(' • ') : 'Backup Data';
        }

        async function onBackupFileSelected(input) {
            if (!input.files || input.files.length === 0) return;
            const banner = document.getElementById('autoDetectBackupBanner');
            const textEl = document.getElementById('autoDetectBackupText');
            const statusEl = document.getElementById('autoDetectBackupStatus');
            const btnText = document.getElementById('restoreFileBtnText');

            try {
                const text = await input.files[0].text();
                const parsed = JSON.parse(text);
                const summary = inspectBackupPayloadClient(parsed);
                if (banner && textEl && statusEl) {
                    banner.classList.remove('hidden');
                    textEl.innerText = summary;
                    statusEl.innerText = 'Auto-Restoring...';
                }
                if (btnText) btnText.innerText = 'Auto-Detecting & Restoring...';

                const b64Payload = btoa(unescape(encodeURIComponent(text)));
                const res = await fetch('api.php?action=restore_backup', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        backup_b64: b64Payload,
                        backup: parsed,
                        scope: 'auto',
                        mode: 'merge'
                    })
                });
                const data = await res.json();
                if (data.success) {
                    if (textEl && data.detected_type) textEl.innerText = data.detected_type + ' (' + summary + ')';
                    if (statusEl) statusEl.innerText = '✓ Restored Automatically';
                    showToast(data.message || `Auto-Detected [${summary}] & Restored successfully!`, 'success');
                    setTimeout(() => window.location.reload(), 1200);
                } else {
                    if (statusEl) statusEl.innerText = 'Failed';
                    showToast('Restore failed: ' + (data.error || 'Unknown error'), 'error');
                }
            } catch (e) {
                if (banner && textEl && statusEl) {
                    banner.classList.remove('hidden');
                    textEl.innerText = 'Invalid JSON file selected';
                    statusEl.innerText = 'Error';
                }
                showToast('Restore failed: ' + (e.message || 'Invalid JSON backup file'), 'error');
            } finally {
                if (btnText) btnText.innerText = '⬆ Select & Auto-Restore Backup (.json)';
                input.value = '';
            }
        }

        async function createServerSnapshot() {
            const scope = document.getElementById('snapshotScopeSelect').value;
            const label = document.getElementById('snapshotLabelInput').value.trim();
            const btn = document.getElementById('createSnapshotBtn');

            btn.disabled = true;
            btn.innerText = 'Creating...';

            try {
                const res = await fetch('api.php?action=create_snapshot', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ scope, label })
                });
                const data = await res.json();
                if (data.success) {
                    showToast(data.message || `Created ${scope.toUpperCase()} snapshot successfully!`, 'success');
                    document.getElementById('snapshotLabelInput').value = '';
                    renderSnapshotsList(data.snapshots || []);
                } else {
                    showToast('Error creating snapshot: ' + (data.error || 'Unknown error'), 'error');
                }
            } catch (e) {
                showToast('Request failed: ' + e.message, 'error');
            } finally {
                btn.disabled = false;
                btn.innerText = '+ Create Snapshot';
            }
        }

        async function restoreSnapshot(filename, scope = 'auto') {
            try {
                showToast('Auto-detecting snapshot contents and restoring...', 'info');
                const res = await fetch('api.php?action=restore_snapshot', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ filename, scope: 'auto', mode: 'merge' })
                });
                const data = await res.json();
                if (data.success) {
                    showToast(data.message || 'Snapshot restored successfully!', 'success');
                    setTimeout(() => window.location.reload(), 1200);
                } else {
                    showToast('Restore failed: ' + (data.error || 'Unknown error'), 'error');
                }
            } catch (e) {
                showToast('Request failed: ' + e.message, 'error');
            }
        }

        async function deleteSnapshot(filename) {
            if (!confirm(`Delete server snapshot "${filename}"?`)) return;
            try {
                const res = await fetch('api.php?action=delete_snapshot', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ filename })
                });
                const data = await res.json();
                if (data.success) {
                    showToast('Snapshot deleted.', 'info');
                    renderSnapshotsList(data.snapshots || []);
                } else {
                    showToast('Delete failed: ' + (data.error || 'Unknown error'), 'error');
                }
            } catch (e) {
                showToast('Request failed: ' + e.message, 'error');
            }
        }

        function renderSnapshotsList(snapshots) {
            const container = document.getElementById('snapshotsListContainer');
            if (!container) return;
            if (!snapshots || snapshots.length === 0) {
                container.innerHTML = `<div class="text-center py-5 text-xs text-[#5f6368] bg-white rounded-xl border border-dashed border-[#c4c7c5]">No snapshots created yet. Click <strong>+ Create Snapshot</strong> above to save an instant restore point.</div>`;
                return;
            }
            container.innerHTML = snapshots.map(snap => `
                <div class="p-3 bg-white rounded-xl border border-[#e0e4eb] flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div>
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="text-xs font-bold text-[#1f1f1f]">${escapeToastHtml(snap.label)}</span>
                            <span class="text-[10px] font-mono px-2 py-0.5 rounded bg-blue-50 text-blue-700 font-bold uppercase">${escapeToastHtml(snap.scope)}</span>
                            <span class="text-[10px] font-mono text-[#747775]">${escapeToastHtml(snap.created_at)}</span>
                        </div>
                        <div class="text-[11px] font-mono text-[#5f6368]">
                            Settings: ${Number(snap.counts?.settings || 0)} • Pages: ${Number(snap.counts?.pages || 0)} • Others: ${Number(snap.counts?.users || 0)}
                        </div>
                    </div>
                    <div class="flex items-center gap-1.5 flex-wrap shrink-0">
                        <button type="button" onclick="restoreSnapshot('${escapeToastHtml(snap.filename)}', 'auto')" class="px-3 py-1.5 rounded-lg bg-[#0b57d0] hover:bg-[#0842a0] text-white text-[11px] font-bold cursor-pointer">↻ Restore (Auto-Detect)</button>
                        <a href="api.php?action=download_snapshot&filename=${encodeURIComponent(snap.filename)}" class="px-2.5 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 text-[11px] font-bold">⬇</a>
                        <button type="button" onclick="deleteSnapshot('${escapeToastHtml(snap.filename)}')" class="px-2.5 py-1.5 rounded-lg bg-rose-50 hover:bg-rose-100 text-rose-600 text-[11px] font-bold cursor-pointer">✕</button>
                    </div>
                </div>
            `).join('');
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
                ? `<svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/></svg>`
                : (type === 'info'
                    ? `<svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>`
                    : `<svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" x2="12" y1="8" y2="12"/><line x1="12" x2="12.01" y1="16" y2="16"/></svg>`);

            const title = type === 'success' ? 'Success' : (type === 'info' ? 'Information' : 'Notice / Error');

            toast.innerHTML = `
                <div class="p-2 rounded-xl shrink-0 mt-0.5 ${iconClass}">
                    ${iconSvg}
                </div>
                <div class="flex-1 min-w-0 pr-1">
                    <div class="text-[11px] font-bold uppercase tracking-wider text-slate-500">${title}</div>
                    <div class="text-xs font-medium text-slate-800 leading-snug mt-0.5 break-words">${escapeToastHtml(msg)}</div>
                </div>
                <button type="button" onclick="document.getElementById('${id}').remove()" class="p-1 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-100 shrink-0 transition-colors cursor-pointer" title="Dismiss">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
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
            }, 3500);
        }

        document.addEventListener('DOMContentLoaded', () => {
            renderMenuItems();
            updateMaintenanceLivePreview();
        });
    </script>
</body>
</html>
