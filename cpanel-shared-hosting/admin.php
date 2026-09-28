<?php
/**
 * Private Admin Dashboard for cPanel Episode Button Page Generator
 * Matches React AdminSidebar + AppHeader + AdminFlowGenerator design
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/class-db.php';
require_once __DIR__ . '/includes/class-auth.php';
require_once __DIR__ . '/includes/class-datastore.php';
require_once __DIR__ . '/includes/class-resolver.php';
require_once __DIR__ . '/includes/class-extractor.php';
require_once __DIR__ . '/includes/class-updater.php';

// Protect this admin page - redirects to private login.php if not authenticated
SLEA_Auth::require_admin();
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

// Check and abort any interrupted updates if page was refreshed / navigated away
SLEA_Updater::check_and_abort_interrupted_updates();
$current_user = SLEA_Auth::get_current_user();
$site_identity = SLEA_Datastore::get_site_identity();
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

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$base_url = rtrim($protocol . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']), '/\\');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generate Button Page - <?= htmlspecialchars($site_name) ?></title>
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
                <!-- Generate (Active) -->
                <a href="admin.php" aria-current="page" class="w-12 h-12 rounded-2xl flex flex-col items-center justify-center text-center transition-all cursor-pointer group relative bg-[#0b57d0] text-white shadow-xs" title="Generate (+ Generator)">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                        <circle cx="12" cy="12" r="10"/><path d="M8 12h8"/><path d="M12 8v8"/>
                    </svg>
                    <span class="text-[9px] font-bold mt-0.5 tracking-tight text-white">Generate</span>
                </a>

                <!-- Pages -->
                <a href="pages.php" class="w-12 h-12 rounded-2xl flex flex-col items-center justify-center text-center transition-all cursor-pointer group relative text-[#444746] hover:bg-[#f0f4f9] hover:text-[#0b57d0]" title="Pages (/pages)">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                        <path d="m12.83 2.18a2 2 0 0 0-1.66 0L2.6 6.08a1 1 0 0 0 0 1.83l8.58 3.91a2 2 0 0 0 1.66 0l8.58-3.9a1 1 0 0 0 0-1.83Z"/><path d="m22 17.65-9.17 4.16a2 2 0 0 1-1.66 0L2 17.65"/><path d="m22 12.65-9.17 4.16a2 2 0 0 1-1.66 0L2 12.65"/>
                    </svg>
                    <span class="text-[9px] font-bold mt-0.5 tracking-tight text-[#5f6368] group-hover:text-[#0b57d0]">Pages</span>
                </a>

                <!-- Settings -->
                <a href="settings.php" class="w-12 h-12 rounded-2xl flex flex-col items-center justify-center text-center transition-all cursor-pointer group relative text-[#444746] hover:bg-[#f0f4f9] hover:text-[#0b57d0]" title="Settings (/settings)">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                        <path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/>
                    </svg>
                    <span class="text-[9px] font-bold mt-0.5 tracking-tight text-[#5f6368] group-hover:text-[#0b57d0]">Settings</span>
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
                                <span class="font-semibold text-[#0b57d0] truncate">Generate Button Page</span>
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

        <!-- Main Admin Workspace (Matches React AdminFlowGenerator.tsx) -->
        <main class="flex-1 w-full max-w-5xl mx-auto px-3.5 sm:px-6 py-5 sm:py-7">
            <div class="w-full space-y-6">
                <!-- Main Generator Card -->
                <div class="bg-white rounded-2xl p-5 sm:p-7 border border-[#e0e4eb] shadow-xs space-y-5">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 pb-3 border-b border-[#f0f4f9]">
                        <div class="space-y-1">
                            <div class="flex items-center gap-2">
                                <span class="w-2.5 h-2.5 rounded-full bg-[#0b57d0] animate-pulse"></span>
                                <h2 class="text-base sm:text-lg font-bold text-[#111827]">
                                    Shorten URL → Resolve → Extract → Create Page Flow
                                </h2>
                            </div>
                            <p class="text-xs text-[#5f6368]">
                                Automated 4-step pipeline to resolve shortlinks into Blogspot episode destinations, extract download buttons, and publish non-indexable public pages.
                            </p>
                        </div>
                        <span class="text-[11px] font-semibold text-[#0b57d0] bg-[#e8f0fe] px-2.5 py-1 rounded-full self-start sm:self-auto shrink-0 border border-[#c2e7ff]">
                            cPanel Admin Workflow
                        </span>
                    </div>

                    <!-- Input Form -->
                    <form id="generateForm" onsubmit="handleGenerate(event)" class="space-y-4">
                        <div class="space-y-1.5">
                            <label class="text-xs font-semibold text-[#444746] flex items-center justify-between">
                                <span>Paste Shortened URL or Source Link:</span>
                                <span class="text-[11px] text-[#747775] font-normal">
                                    AdLinkFly, Sohojgyan, Drama posts, Blogspot
                                </span>
                            </label>
                            <div class="flex items-center gap-2">
                                <div class="relative flex-1">
                                    <input
                                        type="url"
                                        id="shortenUrl"
                                        placeholder="e.g. https://shrt.sohojgyan.com/Ij03ndJ or https://shrt.sohojgyan.com/AAhg"
                                        required
                                        class="w-full pl-9 pr-4 py-3 rounded-xl border border-[#c4c7c5] focus:border-[#0b57d0] focus:ring-2 focus:ring-[#0b57d0]/15 outline-none text-xs sm:text-sm font-medium font-mono text-[#1f1f1f] transition-all"
                                    />
                                    <svg class="w-4 h-4 text-[#747775] absolute left-3 top-3.5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                        <path d="M9 17H7A5 5 0 0 1 7 7h2"/><path d="M15 7h2a5 5 0 1 1 0 10h-2"/><line x1="8" x2="16" y1="12" y2="12"/>
                                    </svg>
                                </div>

                                <button
                                    type="submit"
                                    id="submitBtn"
                                    class="px-5 py-3 rounded-xl bg-[#0b57d0] hover:bg-[#0842a0] disabled:bg-[#a8c7fa] text-white font-bold text-xs sm:text-sm flex items-center gap-2 cursor-pointer shrink-0 shadow-xs transition-all"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                        <path d="M9.937 15.5A2 2 0 0 0 8.5 14.063l-6.135-1.582a.5.5 0 0 1 0-.962L8.5 9.936A2 2 0 0 0 9.937 8.5l1.582-6.135a.5.5 0 0 1 .963 0L14.063 8.5A2 2 0 0 0 15.5 9.937l6.135 1.581a.5.5 0 0 1 0 .964L15.5 14.063a2 2 0 0 0-1.437 1.437l-1.582 6.135a.5.5 0 0 1-.963 0z"/><path d="M20 3v4"/><path d="M22 5h-4"/><path d="M4 17v2"/><path d="M5 18H3"/>
                                    </svg>
                                    <span class="hidden sm:inline">Resolve &amp; Create Page</span>
                                    <span class="sm:hidden">Generate</span>
                                </button>
                            </div>
                        </div>

                        <!-- Quick Samples -->
                        <div class="flex items-center gap-1.5 flex-wrap pt-0.5 text-xs">
                            <span class="text-[#747775] font-medium text-[11px]">Quick Samples:</span>
                            <button type="button" onclick="setSample('https://shrt.sohojgyan.com/Ij03ndJ')" class="px-2.5 py-1 rounded-full bg-[#f0f4f9] hover:bg-[#d3e3fd] text-[#041e49] border border-[#e1e7f0] cursor-pointer transition-colors text-[11px]">
                                Shortlink 1 (Ij03ndJ)
                            </button>
                            <button type="button" onclick="setSample('https://shrt.sohojgyan.com/AAhg')" class="px-2.5 py-1 rounded-full bg-[#f0f4f9] hover:bg-[#d3e3fd] text-[#041e49] border border-[#e1e7f0] cursor-pointer transition-colors text-[11px]">
                                Shortlink 2 (AAhg)
                            </button>
                            <button type="button" onclick="setSample('https://mydverse02.blogspot.com/p/flp-120926.html')" class="px-2.5 py-1 rounded-full bg-[#f0f4f9] hover:bg-[#d3e3fd] text-[#041e49] border border-[#e1e7f0] cursor-pointer transition-colors text-[11px]">
                                Target 1 (flp-120926)
                            </button>
                            <button type="button" onclick="setSample('https://mydverse02.blogspot.com/p/mbmb-030826.html')" class="px-2.5 py-1 rounded-full bg-[#f0f4f9] hover:bg-[#d3e3fd] text-[#041e49] border border-[#e1e7f0] cursor-pointer transition-colors text-[11px]">
                                Target 2 (mbmb-030826)
                            </button>
                            <button type="button" onclick="setSample('https://mydverse.com/2026/09/fanletter-please-korean-drama-in-hindi/')" class="px-2.5 py-1 rounded-full bg-[#f0f4f9] hover:bg-[#d3e3fd] text-[#041e49] border border-[#e1e7f0] cursor-pointer transition-colors text-[11px]">
                                Drama Post
                            </button>
                        </div>

                        <!-- Optional Page Customization Accordion -->
                        <div class="pt-2">
                            <details class="text-xs group">
                                <summary class="font-semibold text-[#5f6368] hover:text-[#0b57d0] cursor-pointer list-none flex items-center gap-1.5 select-none">
                                    <span class="transition-transform group-open:rotate-90">▸</span>
                                    <span>Optional Page Customization (Title, Theme)</span>
                                </summary>
                                <div class="pt-3 pl-4 grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <div class="space-y-1">
                                        <label class="text-[11px] font-semibold text-[#444746] block">
                                            Custom Page Title (Optional)
                                        </label>
                                        <input
                                            type="text"
                                            id="customTitleInput"
                                            placeholder="Auto-detected from Blogspot title if left blank"
                                            class="w-full px-3 py-2 rounded-lg border border-[#c4c7c5] text-xs outline-none focus:border-[#0b57d0]"
                                        />
                                    </div>
                                    <div class="space-y-1">
                                        <label class="text-[11px] font-semibold text-[#444746] block">
                                            Button Theme Color
                                        </label>
                                        <select
                                            id="themeSelect"
                                            class="w-full px-3 py-2 rounded-lg border border-[#c4c7c5] text-xs outline-none focus:border-[#0b57d0] bg-white"
                                        >
                                            <option value="indigo">Indigo Blue (Standard)</option>
                                            <option value="emerald">Emerald Green</option>
                                            <option value="crimson">Crimson Red</option>
                                            <option value="slate">Slate Minimal</option>
                                            <option value="dark">Dark Cinema</option>
                                        </select>
                                    </div>
                                </div>
                            </details>
                        </div>
                    </form>

                    <!-- Live Processing Indicator -->
                    <div id="loadingBox" class="hidden bg-[#f8fafd] border border-[#c2e7ff] rounded-xl p-4 space-y-3 animate-pulse">
                        <div class="flex items-center gap-2 text-xs font-bold text-[#0b57d0]">
                            <svg class="w-3.5 h-3.5 animate-spin" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                <path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"/><path d="M21 3v5h-5"/><path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16"/><path d="M8 16H3v5"/>
                            </svg>
                            <span id="statusStepTitle">Step 1/3: Resolving Shortened URL &amp; Bypassing Gateway...</span>
                        </div>
                        <p id="statusStepDesc" class="text-xs text-[#5f6368]">
                            Following redirects, resolving AdLinkFly tokens, and verifying Blogspot destination structure...
                        </p>
                    </div>

                    <!-- Error Notification -->
                    <div id="errorBox" class="hidden bg-[#fce8e6] text-[#c5221f] text-xs p-3.5 rounded-xl flex items-start gap-2.5 border border-[#fad2cf]">
                        <svg class="w-4 h-4 shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                            <circle cx="12" cy="12" r="10"/><line x1="12" x2="12" y1="8" y2="12"/><line x1="12" x2="12.01" y1="16" y2="16"/>
                        </svg>
                        <div class="space-y-1">
                            <span class="font-bold">Error Processing URL:</span>
                            <p id="errorText" class="text-[11px]"></p>
                        </div>
                    </div>

                    <!-- SUCCESS RESULT (Matches React AdminFlowGenerator.tsx) -->
                    <div id="successBox" class="hidden bg-[#f6faf7] border border-[#a8dab5] rounded-2xl p-5 space-y-4">
                        <div class="flex items-center justify-between flex-wrap gap-2">
                            <div class="flex items-center gap-2 text-xs font-bold text-[#137333]">
                                <svg class="w-4 h-4 text-[#137333]" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                    <circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/>
                                </svg>
                                <span>Page Created &amp; Ready to Share!</span>
                            </div>
                        </div>

                        <!-- Public Link Box -->
                        <div class="space-y-1.5 min-w-0">
                            <label class="text-xs font-bold text-[#111827]">
                                Public Button Page Link:
                            </label>
                            <div class="flex items-center gap-2 bg-white rounded-xl p-2 border border-[#c4e3cb] shadow-2xs flex-wrap sm:flex-nowrap min-w-0">
                                <input
                                    type="text"
                                    id="pageUrlOutput"
                                    readonly
                                    class="flex-1 min-w-0 px-2 py-1 font-mono text-xs sm:text-sm font-bold text-[#0b57d0] bg-transparent outline-none truncate select-all"
                                />
                                <div class="flex items-center gap-2 shrink-0">
                                    <button
                                        type="button"
                                        onclick="copyResultLink()"
                                        class="px-3 py-1.5 rounded-lg bg-[#0b57d0] hover:bg-[#0842a0] text-white font-bold text-xs flex items-center gap-1.5 cursor-pointer shadow-2xs transition-colors shrink-0"
                                    >
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                            <rect width="14" height="14" x="8" y="8" rx="2" ry="2"/><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/>
                                        </svg>
                                        <span>Copy Link</span>
                                    </button>
                                    <a
                                        href="#"
                                        id="pageOpenLink"
                                        target="_blank"
                                        class="px-3 py-1.5 rounded-lg bg-[#e8f0fe] hover:bg-[#d3e3fd] text-[#0b57d0] border border-[#c2e7ff] font-bold text-xs flex items-center gap-1.5 cursor-pointer transition-colors shrink-0"
                                    >
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                            <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/>
                                        </svg>
                                        <span>View Page</span>
                                    </a>
                                    <a
                                        href="#"
                                        id="pageEditLink"
                                        class="px-3 py-1.5 rounded-lg bg-[#f0f4f9] hover:bg-[#e1e7f0] text-[#1f1f1f] border border-[#e0e4eb] font-bold text-xs flex items-center gap-1.5 cursor-pointer transition-colors shrink-0"
                                    >
                                        <span>Edit</span>
                                    </a>
                                </div>
                            </div>
                        </div>

                        <!-- Extraction Metadata Info -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs text-[#444746] bg-white/70 rounded-xl p-3 border border-[#e0e4eb]">
                            <div class="min-w-0">
                                <span class="text-[#747775]">Title:</span>
                                <span id="resTitleText" class="font-bold text-[#1f1f1f] truncate block"></span>
                            </div>
                            <div class="min-w-0">
                                <span class="text-[#747775]">Extracted Buttons:</span>
                                <span id="resBtnCountText" class="font-bold text-[#137333] block"></span>
                            </div>
                        </div>

                        <!-- Button Preview List -->
                        <div class="space-y-2 pt-1">
                            <div class="text-xs font-bold text-[#444746] flex items-center justify-between">
                                <span id="previewListHeader">Extracted Buttons Preview:</span>
                                <a
                                    href="#"
                                    id="pageFullOpenLink"
                                    target="_blank"
                                    class="text-[11px] font-semibold text-[#0b57d0] hover:underline flex items-center gap-1"
                                >
                                    <span>Open Full Public Page</span>
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                        <path d="M15 3h6v6"/><path d="M10 14 21 3"/><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>
                                    </svg>
                                </a>
                            </div>

                            <div id="buttonsPreviewGrid" class="grid grid-cols-1 sm:grid-cols-2 gap-2"></div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Top Right Notification Toast Container (Matches React Toast.tsx) -->
    <aside id="toastContainer" aria-label="Notifications" class="fixed top-5 right-5 z-50 flex flex-col items-end gap-3 pointer-events-none max-w-sm w-[calc(100%-2.5rem)]"></aside>

    <script>
        const baseUrl = '<?= $base_url ?>';

        function escapeHtml(str) {
            return String(str || '').replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/'/g, '&#039;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
        }

        function showNotification(message, type = 'success') {
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
                    <div class="text-xs font-medium text-slate-800 leading-snug mt-0.5 break-words">${escapeHtml(message)}</div>
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

        function setSample(url) {
            document.getElementById('shortenUrl').value = url;
            handleGenerate(null, url);
        }

        async function handleGenerate(e, overrideUrl) {
            if (e) e.preventDefault();
            const input = document.getElementById('shortenUrl');
            const customTitle = document.getElementById('customTitleInput').value.trim();
            const theme = document.getElementById('themeSelect').value;
            const submitBtn = document.getElementById('submitBtn');
            const loadingBox = document.getElementById('loadingBox');
            const errorBox = document.getElementById('errorBox');
            const errorText = document.getElementById('errorText');
            const successBox = document.getElementById('successBox');
            const statusStepTitle = document.getElementById('statusStepTitle');
            const statusStepDesc = document.getElementById('statusStepDesc');

            const url = (overrideUrl || input.value).trim();
            if (!url) {
                showNotification('Please paste a shortened URL first.', 'error');
                return;
            }

            submitBtn.disabled = true;
            submitBtn.innerHTML = `
                <svg class="w-4 h-4 animate-spin" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                    <path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"/><path d="M21 3v5h-5"/><path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16"/><path d="M8 16H3v5"/>
                </svg>
                <span>Processing...</span>
            `;

            loadingBox.classList.remove('hidden');
            errorBox.classList.add('hidden');
            successBox.classList.add('hidden');

            statusStepTitle.innerText = 'Step 1/3: Resolving Shortened URL & Bypassing Gateway...';
            statusStepDesc.innerText = 'Following redirects, resolving AdLinkFly tokens, and verifying Blogspot destination structure...';

            const stepTimers = [];
            stepTimers.push(setTimeout(() => {
                if (submitBtn.disabled) {
                    statusStepTitle.innerText = 'Step 2/3: Bypassing Intermediate Gateway...';
                    statusStepDesc.innerText = 'Bypassing gateway hops to reach target Blogspot destination...';
                }
            }, 3000));
            stepTimers.push(setTimeout(() => {
                if (submitBtn.disabled) {
                    statusStepTitle.innerText = 'Step 3/3: Extracting Episode Buttons...';
                    statusStepDesc.innerText = 'Target reached! Parsing and extracting verified download links...';
                }
            }, 8000));

            const controller = new AbortController();
            const timeoutId = setTimeout(() => {
                controller.abort();
            }, 45000);

            try {
                const res = await fetch('api.php?action=create_page_from_url', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        url: url,
                        title_override: customTitle || undefined,
                        theme: theme
                    }),
                    signal: controller.signal
                });

                clearTimeout(timeoutId);
                stepTimers.forEach(t => clearTimeout(t));

                const rawText = await res.text();
                let data;
                try {
                    data = JSON.parse(rawText);
                } catch (jsonErr) {
                    if (res.status === 401) {
                        throw new Error('Admin session expired. Please refresh and log in again.');
                    } else if (res.status >= 500) {
                        throw new Error('Server error (' + res.status + '): Engine timed out or encountered an internal error.');
                    } else {
                        throw new Error('Unexpected server response: ' + rawText.substring(0, 120));
                    }
                }

                if (!res.ok || !data.success || !data.data) {
                    throw new Error(data.error || 'Failed to resolve and generate button page.');
                }

                const pageUrl = data.data.clean_url || data.data.view_url || (baseUrl + '/p/' + data.data.slug);
                document.getElementById('pageUrlOutput').value = pageUrl;
                document.getElementById('pageOpenLink').href = pageUrl;
                document.getElementById('pageFullOpenLink').href = pageUrl;
                document.getElementById('pageEditLink').href = 'pages.php?edit=' + data.data.id;
                document.getElementById('resTitleText').innerText = data.data.title || 'Episode Page';
                document.getElementById('resBtnCountText').innerText = (data.data.button_count || 0) + ' episodes';

                const buttons = Array.isArray(data.data.buttons) ? data.data.buttons : [];
                document.getElementById('previewListHeader').innerText = `Extracted Buttons Preview (${buttons.length}):`;
                const previewGrid = document.getElementById('buttonsPreviewGrid');
                previewGrid.innerHTML = buttons.slice(0, 6).map((btn, idx) => `
                    <div class="bg-white p-2.5 rounded-xl border border-[#e0e4eb] flex items-center justify-between gap-2 text-xs">
                        <div class="flex items-center gap-2 min-w-0">
                            <span class="w-6 h-6 rounded-md bg-[#e8f0fe] text-[#0b57d0] flex items-center justify-center font-bold text-[10px] shrink-0">
                                E${escapeHtml(btn.episode || (idx + 1))}
                            </span>
                            <span class="font-semibold text-[#1f1f1f] truncate text-[11px]">
                                ${escapeHtml(btn.text)}
                            </span>
                        </div>
                        ${btn.quality ? `<span class="px-1.5 py-0.5 rounded bg-[#f0f4f9] text-[#444746] text-[10px] font-mono font-bold shrink-0">${escapeHtml(btn.quality)}</span>` : ''}
                    </div>
                `).join('');

                loadingBox.classList.add('hidden');
                successBox.classList.remove('hidden');
                showNotification('✓ Episode Button Page created successfully!', 'success');
            } catch (err) {
                clearTimeout(timeoutId);
                stepTimers.forEach(t => clearTimeout(t));

                let friendlyMsg = err.message || 'An unexpected error occurred during processing.';
                if (err.name === 'AbortError' || (err.message && err.message.includes('aborted'))) {
                    friendlyMsg = 'Request timed out after 45 seconds. The target shortlink host or destination did not respond in time. Please verify the URL or try again.';
                }

                loadingBox.classList.add('hidden');
                errorText.innerText = friendlyMsg;
                errorBox.classList.remove('hidden');
                showNotification(friendlyMsg, 'error');
            } finally {
                submitBtn.disabled = false;
                submitBtn.innerHTML = `
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                        <path d="M9.937 15.5A2 2 0 0 0 8.5 14.063l-6.135-1.582a.5.5 0 0 1 0-.962L8.5 9.936A2 2 0 0 0 9.937 8.5l1.582-6.135a.5.5 0 0 1 .963 0L14.063 8.5A2 2 0 0 0 15.5 9.937l6.135 1.581a.5.5 0 0 1 0 .964L15.5 14.063a2 2 0 0 0-1.437 1.437l-1.582 6.135a.5.5 0 0 1-.963 0z"/><path d="M20 3v4"/><path d="M22 5h-4"/><path d="M4 17v2"/><path d="M5 18H3"/>
                    </svg>
                    <span class="hidden sm:inline">Resolve &amp; Create Page</span>
                    <span class="sm:hidden">Generate</span>
                `;
            }
        }

        function copyResultLink() {
            const el = document.getElementById('pageUrlOutput');
            el.select();
            navigator.clipboard.writeText(el.value);
            showNotification('Link copied to clipboard!', 'success');
        }
    </script>
</body>
</html>
