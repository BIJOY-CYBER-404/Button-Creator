<?php
/**
 * Admin Panel: /settings
 * Manage public navigation menu items and edit footer copyright text (HTML allowed).
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/class-db.php';
require_once __DIR__ . '/includes/class-auth.php';
require_once __DIR__ . '/includes/class-datastore.php';

SLEA_Auth::require_admin();
$current_user = SLEA_Auth::get_current_user();

$menu_items = SLEA_Datastore::get_menu_items();
$footer_copyright = SLEA_Datastore::get_footer_copyright();
$ad_settings = SLEA_Datastore::get_ad_settings();
$site_identity = SLEA_Datastore::get_site_identity();
$maintenance_settings = SLEA_Datastore::get_maintenance_settings();
$site_name = !empty($site_identity['site_name']) ? $site_identity['site_name'] : APP_NAME;
$site_logo_url = !empty($site_identity['site_logo_url']) ? $site_identity['site_logo_url'] : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portal Settings (/settings) - <?= htmlspecialchars($site_name) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        .font-mono { font-family: 'JetBrains Mono', monospace; }
    </style>
</head>
<body class="bg-[#f0f4f9] text-[#1f1f1f] min-h-screen font-sans antialiased selection:bg-[#d3e3fd] selection:text-[#041e49]">
    <!-- Left Icon Sidebar (Generate, Pages, Settings) -->
    <aside class="fixed inset-y-0 left-0 w-16 sm:w-20 bg-white border-r border-[#e1e7f0] z-40 flex flex-col items-center py-4 justify-between shadow-xs select-none">
        <!-- Top: Logo & Main Navigation Icons -->
        <div class="flex flex-col items-center w-full gap-5">
            <!-- Brand Logo -->
            <a href="admin.php" class="w-10 h-10 sm:w-11 sm:h-11 rounded-2xl bg-[#c2e7ff] text-[#001d35] flex items-center justify-center font-black text-lg shadow-2xs hover:scale-105 transition-transform overflow-hidden p-1" title="<?= htmlspecialchars($site_name) ?>">
                <?php if (!empty($site_logo_url)): ?>
                    <img src="<?= htmlspecialchars($site_logo_url) ?>" alt="<?= htmlspecialchars($site_name) ?>" class="w-full h-full object-contain rounded-xl" />
                <?php else: ?>
                    <span class="font-bold text-[10px] text-center leading-tight truncate px-0.5"><?= htmlspecialchars($site_name) ?></span>
                <?php endif; ?>
            </a>

            <!-- Nav Icons (Generate, Pages, Settings) -->
            <nav class="flex flex-col items-center w-full gap-2 px-1 sm:px-2">
                <!-- 1. Generate Icon -->
                <a href="admin.php" class="w-12 h-12 rounded-2xl flex flex-col items-center justify-center text-center transition-all group text-[#444746] hover:bg-[#f0f4f9] hover:text-[#0b57d0]" title="Generate (+ Generator)">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    <span class="text-[9px] font-bold mt-0.5 tracking-tight text-[#5f6368] group-hover:text-[#0b57d0]">Generate</span>
                </a>

                <!-- 2. Pages Icon -->
                <a href="pages.php" class="w-12 h-12 rounded-2xl flex flex-col items-center justify-center text-center transition-all group text-[#444746] hover:bg-[#f0f4f9] hover:text-[#0b57d0]" title="Pages (/pages)">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                    </svg>
                    <span class="text-[9px] font-bold mt-0.5 tracking-tight text-[#5f6368] group-hover:text-[#0b57d0]">Pages</span>
                </a>

                <!-- 3. Settings Icon (Active) -->
                <a href="settings.php" class="w-12 h-12 rounded-2xl flex flex-col items-center justify-center text-center transition-all group bg-[#0b57d0] text-white shadow-xs" title="Settings (/settings)">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    <span class="text-[9px] font-bold mt-0.5 tracking-tight text-white">Settings</span>
                </a>

                <!-- 4. Update Icon -->
                <a href="update.php" class="w-12 h-12 rounded-2xl flex flex-col items-center justify-center text-center transition-all group text-[#444746] hover:bg-[#f0f4f9] hover:text-[#0b57d0]" title="One-Click System Updater (/update.php)">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                    <span class="text-[9px] font-bold mt-0.5 tracking-tight text-[#5f6368] group-hover:text-[#0b57d0]">Update</span>
                </a>
            </nav>
        </div>

        <!-- Bottom: User & Logout & Version -->
        <div class="flex flex-col items-center w-full gap-2 px-1 sm:px-2 pb-3">
            <div class="w-8 h-8 rounded-full bg-[#e8f0fe] text-[#0b57d0] font-black text-xs flex items-center justify-center border border-[#d3e3fd]" title="Logged in as <?= htmlspecialchars($current_user['username']) ?>">
                <?= strtoupper(substr($current_user['username'], 0, 1)) ?>
            </div>
            <a href="logout.php" class="w-10 h-10 rounded-xl flex items-center justify-center text-[#c5221f] hover:bg-[#fce8e6] transition-colors" title="Logout">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                </svg>
            </a>
            <span class="text-[10px] font-bold font-mono text-slate-400 bg-slate-100 px-1.5 py-0.5 rounded border border-slate-200" title="System Version <?= htmlspecialchars(APP_VERSION) ?>">
                <?= htmlspecialchars(APP_VERSION) ?>
            </span>
        </div>
    </aside>

    <!-- Main Wrapper (Offset for Left Sidebar) -->
    <div class="pl-16 sm:pl-20 min-h-screen flex flex-col">
        <!-- Top Header Bar -->
        <header class="bg-white border-b border-[#e1e7f0] sticky top-0 z-30 shadow-2xs">
            <div class="max-w-5xl mx-auto px-4 sm:px-6 py-3 sm:py-3.5 flex items-center justify-between gap-3 min-w-0">
                <div class="flex items-center gap-2.5 min-w-0 flex-1">
                    <div class="w-8 h-8 rounded-lg bg-blue-50 border border-blue-100 flex items-center justify-center text-blue-600 shrink-0">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                    </div>
                    <div class="min-w-0 flex-1">
                        <h1 class="font-bold text-sm sm:text-base text-[#1f1f1f] leading-tight truncate">
                            <?= htmlspecialchars(APP_NAME) ?>
                        </h1>
                        <span class="text-[11px] text-[#5f6368] block truncate">Admin Settings (/settings)</span>
                    </div>
                </div>

                <div class="flex items-center gap-2.5 shrink-0">
                    <span class="text-xs font-semibold px-2.5 py-1 rounded-full bg-[#e6f4ea] text-[#137333] border border-[#a8dab5] hidden sm:inline-flex items-center gap-1">
                        ● Admin Session
                    </span>
                    <span class="text-xs font-semibold text-slate-600">
                        <?= htmlspecialchars($current_user['username']) ?>
                    </span>
                </div>
            </div>
        </header>

        <!-- Main Content Container -->
        <main class="max-w-5xl mx-auto px-4 sm:px-6 py-6 sm:py-8 flex-1 w-full space-y-7">
        <!-- Notification Banner -->
        <div id="toast" class="hidden bg-[#e6f4ea] border border-[#a8dab5] text-[#137333] px-4 py-3 rounded-2xl text-xs font-bold shadow-xs flex items-center justify-between">
            <span id="toastMsg">Settings saved successfully!</span>
            <button onclick="document.getElementById('toast').classList.add('hidden')" class="text-sm font-bold">✕</button>
        </div>

        <!-- 0. Site Branding & Logo Configuration (Google Material M3) -->
        <div class="bg-white rounded-3xl p-6 sm:p-7 border border-[#e0e4eb] shadow-xs space-y-6">
            <div class="pb-3 border-b border-[#f0f4f9] flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                <div>
                    <h2 class="text-base sm:text-lg font-bold text-[#111827] flex items-center gap-2">
                        <span>🎨 Site Branding & Logo</span>
                    </h2>
                    <p class="text-xs text-[#5f6368]">
                        Customize the website title, logo image URL, or logo icon displayed in the public header, sidebar drawer, and page cards.
                    </p>
                </div>
                <span class="px-2.5 py-1 rounded-full bg-[#e8f0fe] text-[#0b57d0] text-[11px] font-bold self-start sm:self-auto shrink-0 border border-[#c2e7ff]">
                    Global Branding
                </span>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-12 gap-5">
                <!-- Site Name -->
                <div class="sm:col-span-6 space-y-1.5">
                    <label class="text-xs font-bold text-[#1f1f1f] block">
                        Website Name / Brand Title:
                    </label>
                    <input type="text" id="siteNameInput" value="<?= htmlspecialchars($site_identity['site_name'] ?? 'Movie Hub HQ Drive') ?>"
                        placeholder="Movie Hub HQ Drive" oninput="updateLogoLivePreview()"
                        class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 focus:border-blue-600 focus:ring-1 focus:ring-blue-600 outline-none text-xs font-semibold text-slate-800" />
                    <p class="text-[10px] text-[#747775]">Displayed in the browser title bar, top header, and sidebar navigation.</p>
                </div>

                <!-- Site Logo Image URL -->
                <div class="sm:col-span-6 space-y-1.5">
                    <label class="text-xs font-bold text-[#1f1f1f] block">
                        Custom Logo Image URL (Optional):
                    </label>
                    <input type="url" id="siteLogoUrlInput" value="<?= htmlspecialchars($site_identity['site_logo_url'] ?? '') ?>"
                        placeholder="https://example.com/logo.png" oninput="updateLogoLivePreview()"
                        class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 focus:border-blue-600 focus:ring-1 focus:ring-blue-600 outline-none text-xs font-mono text-slate-800" />
                    <p class="text-[10px] text-[#747775]">Provide a direct image URL (PNG, SVG, WebP). When provided, text fallback is suppressed.</p>
                </div>

                <!-- Live Preview in M3 Light Style -->
                <div class="sm:col-span-6 space-y-1.5">
                    <label class="text-[11px] font-bold uppercase text-[#5f6368] block">
                        Live Header & Sidebar Preview:
                    </label>
                    <div class="p-3 rounded-2xl bg-[#f8fafd] border border-[#d3e3fd] flex items-center justify-between gap-3">
                        <div class="flex items-center gap-2.5 min-w-0" id="liveBrandPreview">
                            <?php if (!empty($site_identity['site_logo_url'])): ?>
                                <img id="previewLogoImg" src="<?= htmlspecialchars($site_identity['site_logo_url']) ?>" alt="Logo" class="h-8 max-w-[130px] object-contain rounded" />
                                <span id="previewSiteName" class="hidden font-bold text-sm text-[#111827] truncate">
                                    <?= htmlspecialchars($site_identity['site_name'] ?? 'Movie Hub HQ Drive') ?>
                                </span>
                            <?php else: ?>
                                <img id="previewLogoImg" src="" alt="Logo" class="hidden h-8 max-w-[130px] object-contain rounded" />
                                <span id="previewSiteName" class="font-bold text-sm text-[#111827] truncate">
                                    <?= htmlspecialchars($site_identity['site_name'] ?? 'Movie Hub HQ Drive') ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <span class="px-2 py-0.5 rounded-md bg-[#e6f4ea] text-[#137333] text-[10px] font-bold shrink-0">
                            Light Theme M3
                        </span>
                    </div>
                </div>
            </div>

            <div class="pt-2 flex justify-end">
                <button type="button" onclick="saveSiteIdentity()" id="saveIdentityBtn" class="px-5 py-2.5 rounded-xl bg-[#0b57d0] hover:bg-[#0842a0] text-white font-bold text-xs shadow-xs transition-all cursor-pointer">
                    Save Site Branding & Logo
                </button>
            </div>
        </div>

        <!-- 🛠️ Maintenance Mode Configuration -->
        <div class="bg-white rounded-3xl p-6 sm:p-7 border border-[#e0e4eb] shadow-xs space-y-6">
            <div class="pb-3 border-b border-[#f0f4f9] flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                <div>
                    <h2 class="text-base sm:text-lg font-bold text-[#111827] flex items-center gap-2">
                        <span>🛠️ Maintenance Mode</span>
                    </h2>
                    <p class="text-xs text-[#5f6368]">
                        Temporarily restrict public access to your movie portal and show an interactive, creative under-maintenance screen. Logged-in administrators can still view pages.
                    </p>
                </div>
                <span class="px-2.5 py-1 rounded-full bg-[#fef7e0] text-[#b06000] text-[11px] font-bold self-start sm:self-auto shrink-0 border border-[#feebc8]">
                    Under Construction
                </span>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-12 gap-6">
                <!-- Inputs column -->
                <div class="md:col-span-7 space-y-5">
                    <!-- Status Toggle -->
                    <div class="flex items-center justify-between p-4 bg-[#f8fafd] rounded-2xl border border-[#e1e7f0]">
                        <div>
                            <label class="text-xs font-bold text-[#1f1f1f] block">Enable Maintenance Mode</label>
                            <span class="text-[10px] text-[#5f6368]">Toggle to turn public restrictions on or off instantly.</span>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" id="maintenanceEnabledInput" <?= !empty($maintenance_settings['enabled']) ? 'checked' : '' ?> class="sr-only peer" onchange="updateMaintenanceLivePreview()">
                            <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                        </label>
                    </div>

                    <!-- Message Textarea -->
                    <div class="space-y-1.5">
                        <label class="text-xs font-bold text-[#1f1f1f] block">
                            Custom Maintenance Message:
                        </label>
                        <textarea id="maintenanceMessageInput" rows="4" oninput="updateMaintenanceLivePreview()"
                            placeholder="The website is currently undergoing scheduled maintenance. We will be back shortly!"
                            class="w-full px-4 py-3 rounded-2xl border border-slate-300 focus:border-blue-600 focus:ring-1 focus:ring-blue-600 outline-none text-xs font-semibold text-slate-800 leading-relaxed"><?= htmlspecialchars($maintenance_settings['message'] ?? '') ?></textarea>
                        <p class="text-[10px] text-[#747775]">Provide information about the duration or reasons for the outage to your audience.</p>
                    </div>
                </div>

                <!-- Live Preview Mockup Column -->
                <div class="md:col-span-5 space-y-2">
                    <label class="text-[11px] font-bold uppercase text-[#5f6368] block">Public Screen Preview:</label>
                    <div class="border border-[#e0e4eb] rounded-3xl p-4 bg-[#f8fafd] shadow-2xs space-y-3 relative overflow-hidden">
                        <!-- Preview Thumbnail -->
                        <div class="relative w-full aspect-[4/3] rounded-xl overflow-hidden bg-[#fafbfc] border border-[#f0f4f9]">
                            <img src="assets/images/maintenance_illustration.jpg" alt="Illustration" class="w-full h-full object-cover" referrerPolicy="no-referrer" />
                        </div>
                        <!-- Preview Message content -->
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

            <div class="pt-2 flex justify-end">
                <button type="button" onclick="saveMaintenanceSettings()" id="saveMaintenanceBtn" class="px-5 py-2.5 rounded-xl bg-[#0b57d0] hover:bg-[#0842a0] text-white font-bold text-xs shadow-xs transition-all cursor-pointer">
                    Save Maintenance Settings
                </button>
            </div>
        </div>

        <!-- 1. Google AdSense & Manual Banner Ads Configuration -->
        <div class="bg-white rounded-3xl p-6 sm:p-7 border border-[#e0e4eb] shadow-xs space-y-6">
            <div class="pb-3 border-b border-[#f0f4f9] flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                <div>
                    <h2 class="text-base sm:text-lg font-bold text-[#111827] flex items-center gap-2">
                        <span>📢 Monetization & Ads Configuration</span>
                    </h2>
                    <p class="text-xs text-[#5f6368]">
                        Configure Google AdSense automatic ads and manual HTML/JS banner ad placements for all public episode button pages.
                    </p>
                </div>
                <span class="px-2.5 py-1 rounded-full bg-[#e8f0fe] text-[#0b57d0] text-[11px] font-bold self-start sm:self-auto shrink-0 border border-[#c2e7ff]">
                    Public Pages
                </span>
            </div>

            <!-- Subsection A: Google AdSense Automatic Ads -->
            <div class="p-5 rounded-2xl bg-[#f8fafd] border border-[#d3e3fd] space-y-4">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <div class="text-xs font-bold text-[#041e49] flex items-center gap-1.5">
                            <span>Google AdSense Automatic Ads</span>
                            <span class="px-2 py-0.5 rounded-md bg-blue-100 text-blue-800 font-mono text-[10px] font-bold">Auto Ads</span>
                        </div>
                        <p class="text-[11px] text-[#5f6368] mt-0.5">
                            Automatically injects Google's optimized auto-ad script into the <code>&lt;head&gt;</code> of all public button pages.
                        </p>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer shrink-0">
                        <input type="checkbox" id="adsenseAutoEnabled" class="sr-only peer" <?= !empty($ad_settings['adsense_auto_enabled']) ? 'checked' : '' ?>>
                        <div class="w-11 h-6 bg-slate-300 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-[#0b57d0]"></div>
                    </label>
                </div>

                <div class="space-y-1.5 pt-1">
                    <label class="text-[11px] font-bold uppercase text-[#5f6368] block">
                        AdSense Publisher / Client ID or Full Script Code:
                    </label>
                    <input type="text" id="adsenseClientId" value="<?= htmlspecialchars($ad_settings['adsense_client_id'] ?? '') ?>"
                        placeholder="e.g. ca-pub-1234567890123456 or &lt;script async src=&quot;https://pagead2...&quot;&gt;&lt;/script&gt;"
                        class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 focus:border-blue-600 focus:ring-1 focus:ring-blue-600 outline-none text-xs font-mono text-slate-800" />
                    <p class="text-[10px] text-[#747775]">
                        Tip: You can enter just your publisher ID (<code>ca-pub-XXXXXXXXXX</code>) or paste the full Google AdSense script tag.
                    </p>
                </div>
            </div>

            <!-- Subsection B: Manual Banner Ads -->
            <div class="p-5 rounded-2xl bg-[#fafafa] border border-[#e0e4eb] space-y-5">
                <div class="flex items-center justify-between gap-3 pb-3 border-b border-slate-200">
                    <div>
                        <div class="text-xs font-bold text-[#111827] flex items-center gap-1.5">
                            <span>Manual Banner Ads Placements</span>
                            <span class="px-2 py-0.5 rounded-md bg-slate-200 text-slate-700 font-mono text-[10px] font-bold">Custom Banners</span>
                        </div>
                        <p class="text-[11px] text-[#5f6368] mt-0.5">
                            Insert custom display banners (AdSense display units, Adsterra, PropellerAds, or custom HTML/image banners).
                        </p>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer shrink-0">
                        <input type="checkbox" id="bannerAdsEnabled" class="sr-only peer" <?= !empty($ad_settings['banner_ads_enabled']) ? 'checked' : '' ?>>
                        <div class="w-11 h-6 bg-slate-300 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-[#0b57d0]"></div>
                    </label>
                </div>

                <!-- Spot 1: Top Banner Ad -->
                <div class="space-y-2 p-4 rounded-xl bg-white border border-slate-200 shadow-2xs">
                    <div class="flex items-center justify-between">
                        <label class="text-xs font-bold text-[#1f1f1f] flex items-center gap-2">
                            <span>1. Top Banner Ad (Above Title & Content)</span>
                        </label>
                        <label class="inline-flex items-center gap-1.5 text-xs text-[#444746] cursor-pointer">
                            <input type="checkbox" id="adTopEnabled" class="rounded text-blue-600" <?= !empty($ad_settings['ad_top_enabled']) ? 'checked' : '' ?> />
                            <span class="font-semibold">Enable Top Spot</span>
                        </label>
                    </div>
                    <textarea id="adTopCode" rows="3" placeholder="Paste HTML/JavaScript banner code (e.g. 728x90 leaderboard or responsive banner)"
                        class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 focus:border-blue-600 focus:ring-1 focus:ring-blue-600 outline-none text-xs font-mono text-slate-800 leading-relaxed"><?= htmlspecialchars($ad_settings['ad_top_code'] ?? '') ?></textarea>
                </div>

                <!-- Spot 2: Middle Banner Ad -->
                <div class="space-y-2 p-4 rounded-xl bg-white border border-slate-200 shadow-2xs">
                    <div class="flex items-center justify-between">
                        <label class="text-xs font-bold text-[#1f1f1f] flex items-center gap-2">
                            <span>2. Middle Banner Ad (Between Title Card & Episode Buttons)</span>
                        </label>
                        <label class="inline-flex items-center gap-1.5 text-xs text-[#444746] cursor-pointer">
                            <input type="checkbox" id="adMiddleEnabled" class="rounded text-blue-600" <?= !empty($ad_settings['ad_middle_enabled']) ? 'checked' : '' ?> />
                            <span class="font-semibold">Enable Middle Spot</span>
                        </label>
                    </div>
                    <textarea id="adMiddleCode" rows="3" placeholder="Paste HTML/JavaScript banner code (e.g. 300x250 medium rectangle or responsive banner)"
                        class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 focus:border-blue-600 focus:ring-1 focus:ring-blue-600 outline-none text-xs font-mono text-slate-800 leading-relaxed"><?= htmlspecialchars($ad_settings['ad_middle_code'] ?? '') ?></textarea>
                </div>

                <!-- Spot 3: Bottom Banner Ad -->
                <div class="space-y-2 p-4 rounded-xl bg-white border border-slate-200 shadow-2xs">
                    <div class="flex items-center justify-between">
                        <label class="text-xs font-bold text-[#1f1f1f] flex items-center gap-2">
                            <span>3. Bottom Banner Ad (Below Episode Buttons / Above Footer)</span>
                        </label>
                        <label class="inline-flex items-center gap-1.5 text-xs text-[#444746] cursor-pointer">
                            <input type="checkbox" id="adBottomEnabled" class="rounded text-blue-600" <?= !empty($ad_settings['ad_bottom_enabled']) ? 'checked' : '' ?> />
                            <span class="font-semibold">Enable Bottom Spot</span>
                        </label>
                    </div>
                    <textarea id="adBottomCode" rows="3" placeholder="Paste HTML/JavaScript banner code (e.g. 728x90, 300x250, or responsive banner)"
                        class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 focus:border-blue-600 focus:ring-1 focus:ring-blue-600 outline-none text-xs font-mono text-slate-800 leading-relaxed"><?= htmlspecialchars($ad_settings['ad_bottom_code'] ?? '') ?></textarea>
                </div>
            </div>

            <div class="pt-2 flex justify-end">
                <button type="button" onclick="saveAdSettings()" id="saveAdsBtn" class="px-5 py-2.5 rounded-xl bg-[#0b57d0] hover:bg-[#0842a0] text-white font-bold text-xs shadow-md transition-all cursor-pointer">
                    Save Ad Configurations
                </button>
            </div>
        </div>

        <!-- 2. Public Navigation Menu Manager -->
        <div class="bg-white rounded-3xl p-6 sm:p-7 border border-[#e0e4eb] shadow-xs space-y-5">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 pb-3 border-b border-[#f0f4f9]">
                <div>
                    <h2 class="text-base sm:text-lg font-bold text-[#111827] flex items-center gap-2">
                        <span>Public Header Navigation Menu</span>
                    </h2>
                    <p class="text-xs text-[#5f6368]">
                        Configures the desktop navigation buttons and mobile hamburger menu on all public button pages.
                    </p>
                </div>

                <button type="button" onclick="addNewMenuItem()" class="px-3.5 py-2 rounded-xl bg-[#0b57d0] hover:bg-[#0842a0] text-white text-xs font-bold transition-all shadow-2xs self-start sm:self-auto cursor-pointer">
                    + Add Menu Item
                </button>
            </div>

            <!-- Menu Items List -->
            <div class="space-y-3" id="menuItemsContainer">
                <?php foreach ($menu_items as $index => $item): ?>
                    <div class="menu-item-card bg-[#f8fafd] border border-[#d3e3fd] rounded-2xl p-4 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3" data-id="<?= htmlspecialchars($item['id'] ?? ('m' . $index)) ?>">
                        <div class="grid grid-cols-1 sm:grid-cols-12 gap-3 flex-1 w-full">
                            <div class="sm:col-span-4">
                                <label class="text-[10px] font-bold uppercase text-[#5f6368] block mb-1">Button Name</label>
                                <input type="text" class="menu-title w-full px-3 py-1.5 rounded-lg border border-slate-300 text-xs font-bold text-slate-800 outline-none focus:border-blue-600"
                                    value="<?= htmlspecialchars($item['title'] ?? '') ?>" placeholder="e.g. Home" required />
                            </div>
                            <div class="sm:col-span-6">
                                <label class="text-[10px] font-bold uppercase text-[#5f6368] block mb-1">Link URL</label>
                                <input type="url" class="menu-url w-full px-3 py-1.5 rounded-lg border border-slate-300 text-xs font-mono text-slate-700 outline-none focus:border-blue-600"
                                    value="<?= htmlspecialchars($item['url'] ?? '') ?>" placeholder="https://..." required />
                            </div>
                            <div class="sm:col-span-2 flex items-center pt-2 sm:pt-4">
                                <label class="inline-flex items-center gap-1.5 text-xs text-[#444746] cursor-pointer">
                                    <input type="checkbox" class="menu-newtab rounded text-blue-600" <?= !empty($item['new_tab']) ? 'checked' : '' ?> />
                                    <span>New tab</span>
                                </label>
                            </div>
                        </div>

                        <div class="flex items-center gap-2 self-end sm:self-center">
                            <button type="button" onclick="this.closest('.menu-item-card').remove()" class="p-2 text-red-500 hover:text-red-700 hover:bg-red-50 rounded-lg transition-colors cursor-pointer text-xs font-bold" title="Delete menu item">
                                Delete
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="pt-2 flex justify-end">
                <button type="button" onclick="saveMenuItems()" id="saveMenuBtn" class="px-5 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs shadow-md transition-all cursor-pointer">
                    Save Navigation Menu
                </button>
            </div>
        </div>

        <!-- 2. Public Pages Footer Copyright Editor (HTML Allowed) -->
        <div class="bg-white rounded-3xl p-6 sm:p-7 border border-[#e0e4eb] shadow-xs space-y-5">
            <div class="pb-3 border-b border-[#f0f4f9]">
                <h2 class="text-base sm:text-lg font-bold text-[#111827]">
                    Public Pages Footer Copyright Text (HTML Allowed)
                </h2>
                <p class="text-xs text-[#5f6368]">
                    Rendered at the bottom of every public button page. You can include links, disclaimer text, or HTML styling.
                </p>
            </div>

            <div class="space-y-3">
                <label class="text-xs font-bold text-[#444746] block">
                    Footer Copyright HTML Content:
                </label>
                <textarea id="footerCopyrightInput" rows="4" oninput="updateFooterPreview()"
                    class="w-full px-4 py-3 rounded-2xl border border-slate-300 focus:border-blue-600 focus:ring-1 focus:ring-blue-600 outline-none text-xs font-mono text-slate-800 leading-relaxed"><?= htmlspecialchars($footer_copyright) ?></textarea>
            </div>

            <!-- Live HTML Preview -->
            <div class="space-y-1.5">
                <span class="text-[11px] font-bold uppercase text-[#747775]">Live Footer Preview:</span>
                <div id="footerPreviewBox" class="p-4 rounded-xl bg-[#f8f9fa] border border-[#e0e4eb] text-xs text-center text-[#5f6368] overflow-hidden">
                    <?= $footer_copyright ?>
                </div>
            </div>

            <div class="pt-2 flex justify-end">
                <button type="button" onclick="saveFooterCopyright()" id="saveFooterBtn" class="px-5 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs shadow-md transition-all cursor-pointer">
                    Save Footer Text
                </button>
            </div>
        </div>

        <!-- 3. One-Click Application Update System Card -->
        <div class="bg-white rounded-3xl p-6 sm:p-7 border border-[#e0e4eb] shadow-xs space-y-4">
            <div class="pb-3 border-b border-[#f0f4f9] flex items-center justify-between flex-wrap gap-2">
                <div>
                    <h2 class="text-base sm:text-lg font-bold text-[#111827] flex items-center gap-2">
                        <span>One-Click Application Update System</span>
                        <span class="text-xs font-mono px-2 py-0.5 rounded bg-blue-50 text-blue-700 border border-blue-200">update.php</span>
                    </h2>
                    <p class="text-xs text-[#5f6368] mt-0.5">
                        Check for application updates from remote repository, download release ZIP packages, perform automated file/database backups, and execute zero-downtime updates.
                    </p>
                </div>
                <a href="update.php" class="px-4 py-2 rounded-xl bg-[#0b57d0] hover:bg-[#0842a0] text-white font-bold text-xs flex items-center gap-2 shadow-xs transition-all">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                    <span>Launch One-Click Updater</span>
                </a>
            </div>

            <div class="p-3.5 bg-[#f8fafd] rounded-2xl border border-[#e0e4eb] flex items-center justify-between text-xs text-[#5f6368] flex-wrap gap-2">
                <div class="flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-[#137333]"></span>
                    <span>Target Destination Format:</span>
                    <code class="font-mono font-bold text-[#0b57d0]">https://mydverse02.blogspot.com/p/*.html</code>
                </div>
                <span class="text-[11px] text-[#747775]">Safe & Non-Destructive</span>
            </div>
        </div>
    </main>
    </div>

    <script>
        async function saveAdSettings() {
            const btn = document.getElementById('saveAdsBtn');
            btn.disabled = true;
            btn.innerText = 'Saving Ads...';

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
                    showToast('Ad configurations saved successfully!');
                } else {
                    alert('Error saving ads: ' + (data.error || 'Unknown error'));
                }
            } catch (e) {
                alert('Request failed: ' + e.message);
            } finally {
                btn.disabled = false;
                btn.innerText = 'Save Ad Configurations';
            }
        }

        function updateFooterPreview() {
            const html = document.getElementById('footerCopyrightInput').value;
            document.getElementById('footerPreviewBox').innerHTML = html;
        }

        function addNewMenuItem() {
            const container = document.getElementById('menuItemsContainer');
            const newId = 'm_' + Math.random().toString(36).substring(2, 8);
            const card = document.createElement('div');
            card.className = 'menu-item-card bg-[#f8fafd] border border-[#d3e3fd] rounded-2xl p-4 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3';
            card.setAttribute('data-id', newId);
            card.innerHTML = `
                <div class="grid grid-cols-1 sm:grid-cols-12 gap-3 flex-1 w-full">
                    <div class="sm:col-span-4">
                        <label class="text-[10px] font-bold uppercase text-[#5f6368] block mb-1">Button Name</label>
                        <input type="text" class="menu-title w-full px-3 py-1.5 rounded-lg border border-slate-300 text-xs font-bold text-slate-800 outline-none focus:border-blue-600"
                            placeholder="e.g. Action Movies" required />
                    </div>
                    <div class="sm:col-span-6">
                        <label class="text-[10px] font-bold uppercase text-[#5f6368] block mb-1">Link URL</label>
                        <input type="url" class="menu-url w-full px-3 py-1.5 rounded-lg border border-slate-300 text-xs font-mono text-slate-700 outline-none focus:border-blue-600"
                            placeholder="https://moviehubhq.com/..." required />
                    </div>
                    <div class="sm:col-span-2 flex items-center pt-2 sm:pt-4">
                        <label class="inline-flex items-center gap-1.5 text-xs text-[#444746] cursor-pointer">
                            <input type="checkbox" class="menu-newtab rounded text-blue-600" />
                            <span>New tab</span>
                        </label>
                    </div>
                </div>
                <div class="flex items-center gap-2 self-end sm:self-center">
                    <button type="button" onclick="this.closest('.menu-item-card').remove()" class="p-2 text-red-500 hover:text-red-700 hover:bg-red-50 rounded-lg transition-colors cursor-pointer text-xs font-bold" title="Delete menu item">
                        Delete
                    </button>
                </div>
            `;
            container.appendChild(card);
        }

        async function saveMenuItems() {
            const btn = document.getElementById('saveMenuBtn');
            btn.disabled = true;
            btn.innerText = 'Saving...';

            const cards = document.querySelectorAll('.menu-item-card');
            const items = [];
            cards.forEach(card => {
                const title = card.querySelector('.menu-title').value.trim();
                const url = card.querySelector('.menu-url').value.trim();
                const newTab = card.querySelector('.menu-newtab').checked;
                const id = card.getAttribute('data-id') || ('m_' + Math.random().toString(36).substring(2, 6));
                if (title && url) {
                    items.push({ id, title, url, new_tab: newTab });
                }
            });

            try {
                const res = await fetch('api.php?action=save_menu_items', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ items: items })
                });
                const data = await res.json();
                if (data.success) {
                    showToast('Navigation menu updated successfully!');
                } else {
                    alert('Error: ' + data.error);
                }
            } catch (e) {
                alert('Request failed: ' + e.message);
            } finally {
                btn.disabled = false;
                btn.innerText = 'Save Navigation Menu';
            }
        }

        async function saveFooterCopyright() {
            const btn = document.getElementById('saveFooterBtn');
            btn.disabled = true;
            btn.innerText = 'Saving...';

            const html = document.getElementById('footerCopyrightInput').value;

            try {
                const res = await fetch('api.php?action=save_footer_copyright', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ footer_html: html })
                });
                const data = await res.json();
                if (data.success) {
                    showToast('Footer copyright updated successfully!');
                } else {
                    alert('Error: ' + data.error);
                }
            } catch (e) {
                alert('Request failed: ' + e.message);
            } finally {
                btn.disabled = false;
                btn.innerText = 'Save Footer Text';
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
            btn.innerText = 'Saving Branding...';

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
                    showToast('Site branding & logo updated successfully!');
                } else {
                    alert('Error saving site identity: ' + (data.error || 'Unknown error'));
                }
            } catch (e) {
                alert('Request failed: ' + e.message);
            } finally {
                btn.disabled = false;
                btn.innerText = 'Save Site Branding & Logo';
            }
        }

        function updateMaintenanceLivePreview() {
            const msg = document.getElementById('maintenanceMessageInput').value.trim() || 'The website is currently undergoing scheduled maintenance. We will be back shortly!';
            document.getElementById('maintenancePreviewMsg').innerText = msg;
        }

        async function saveMaintenanceSettings() {
            const btn = document.getElementById('saveMaintenanceBtn');
            btn.disabled = true;
            btn.innerText = 'Saving...';

            const payload = {
                enabled: document.getElementById('maintenanceEnabledInput').checked,
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
                    showToast('Maintenance settings saved successfully!');
                } else {
                    alert('Error saving maintenance settings: ' + (data.error || 'Unknown error'));
                }
            } catch (e) {
                alert('Request failed: ' + e.message);
            } finally {
                btn.disabled = false;
                btn.innerText = 'Save Maintenance Settings';
            }
        }

        function showToast(msg) {
            const toast = document.getElementById('toast');
            document.getElementById('toastMsg').innerText = msg;
            toast.classList.remove('hidden');
            setTimeout(() => { toast.classList.add('hidden'); }, 3500);
        }
    </script>
</body>
</html>
