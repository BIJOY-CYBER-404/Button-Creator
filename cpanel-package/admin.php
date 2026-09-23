<?php
/**
 * Private Admin Dashboard for cPanel Episode Button Page Generator
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/class-db.php';
require_once __DIR__ . '/includes/class-auth.php';
require_once __DIR__ . '/includes/class-datastore.php';
require_once __DIR__ . '/includes/class-resolver.php';
require_once __DIR__ . '/includes/class-extractor.php';

// Protect this admin page - redirects to private login.php if not authenticated
SLEA_Auth::require_admin();
$current_user = SLEA_Auth::get_current_user();
$site_identity = SLEA_Datastore::get_site_identity();
$site_name = !empty($site_identity['site_name']) ? $site_identity['site_name'] : APP_NAME;
$site_logo_url = !empty($site_identity['site_logo_url']) ? $site_identity['site_logo_url'] : '';

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$base_url = rtrim($protocol . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']), '/\\');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - <?= htmlspecialchars($site_name) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
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
                <a href="admin.php" class="w-12 h-12 rounded-2xl flex flex-col items-center justify-center text-center transition-all group bg-[#0b57d0] text-white shadow-xs" title="Generate (+ Generator)">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    <span class="text-[9px] font-bold mt-0.5 tracking-tight text-white">Generate</span>
                </a>

                <!-- 2. Pages Icon -->
                <a href="pages.php" class="w-12 h-12 rounded-2xl flex flex-col items-center justify-center text-center transition-all group text-[#444746] hover:bg-[#f0f4f9] hover:text-[#0b57d0]" title="Pages (/pages)">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                    </svg>
                    <span class="text-[9px] font-bold mt-0.5 tracking-tight text-[#5f6368] group-hover:text-[#0b57d0]">Pages</span>
                </a>

                <!-- 3. Settings Icon -->
                <a href="settings.php" class="w-12 h-12 rounded-2xl flex flex-col items-center justify-center text-center transition-all group text-[#444746] hover:bg-[#f0f4f9] hover:text-[#0b57d0]" title="Settings (/settings)">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    <span class="text-[9px] font-bold mt-0.5 tracking-tight text-[#5f6368] group-hover:text-[#0b57d0]">Settings</span>
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
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                    </div>
                    <div class="min-w-0 flex-1">
                        <h1 class="font-bold text-sm sm:text-base text-[#1f1f1f] leading-tight truncate">
                            <?= htmlspecialchars(APP_NAME) ?>
                        </h1>
                        <span class="text-[11px] text-[#5f6368] block truncate">Generate Button Page</span>
                    </div>
                </div>

                <div class="flex items-center gap-2.5 shrink-0">
                    <span class="text-xs font-semibold px-2.5 py-1 rounded-full bg-[#e6f4ea] text-[#137333] border border-[#a8dab5] hidden sm:inline-flex items-center gap-1">
                        ● Admin Session
                    </span>
                    <span class="text-xs font-semibold text-slate-600 truncate max-w-[120px] sm:max-w-none">
                        <?= htmlspecialchars($current_user['username']) ?>
                    </span>
                </div>
            </div>
        </header>

        <!-- Main Generator Container -->
        <main class="max-w-5xl mx-auto px-4 sm:px-6 py-6 sm:py-8 flex-1 w-full space-y-6">
        <!-- Main Automated Flow Card -->
        <div class="bg-white rounded-3xl p-6 sm:p-8 border border-[#e0e4eb] shadow-xs space-y-6">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 pb-4 border-b border-[#f0f4f9]">
                <div>
                    <h2 class="text-base sm:text-lg font-bold text-[#111827] flex items-center gap-2">
                        <span>Shorten URL → Resolve → Extract → Create Page Flow</span>
                    </h2>
                    <p class="text-xs text-[#5f6368] mt-0.5">
                        Resolves Sohojgyan / AdLinkFly gateway hops until destination matches target Blogspot episode post, parses download buttons, and publishes a non-indexable button page.
                    </p>
                </div>
                <span class="px-2.5 py-1 rounded-full bg-[#e8f0fe] text-[#0b57d0] text-[11px] font-bold self-start sm:self-auto shrink-0 border border-[#c2e7ff]">
                    MySQL Engine
                </span>
            </div>

            <form id="generateForm" onsubmit="handleGenerate(event)" class="space-y-4">
                <div class="space-y-1.5">
                    <label class="text-xs font-bold text-[#444746] block">
                        Paste Shortened URL or Source Link:
                    </label>
                    <div class="flex flex-col sm:flex-row items-stretch gap-2">
                        <input type="url" id="shortenUrl" required placeholder="e.g. https://shrt.sohojgyan.com/Ij03ndJ or https://shrt.sohojgyan.com/AAhg"
                            class="flex-1 px-4 py-3 rounded-2xl border border-slate-300 focus:border-blue-600 focus:ring-2 focus:ring-blue-600/10 outline-none text-xs sm:text-sm font-mono text-slate-900 transition-all" />
                        <button type="submit" id="submitBtn"
                            class="px-6 py-3 rounded-2xl bg-[#0b57d0] hover:bg-[#0842a0] disabled:bg-blue-300 text-white font-bold text-xs sm:text-sm shadow-md transition-all cursor-pointer shrink-0">
                            Resolve & Create Page
                        </button>
                    </div>
                </div>

                <!-- Quick Samples -->
                <div class="flex items-center gap-2 flex-wrap text-xs text-[#747775]">
                    <span class="font-medium">Quick Samples:</span>
                    <button type="button" onclick="setSample('https://shrt.sohojgyan.com/Ij03ndJ')" class="px-2.5 py-1 rounded-lg bg-[#f0f4f9] hover:bg-[#d3e3fd] text-[#041e49] text-[11px]">
                        Shortlink 1 (Ij03ndJ)
                    </button>
                    <button type="button" onclick="setSample('https://shrt.sohojgyan.com/AAhg')" class="px-2.5 py-1 rounded-lg bg-[#f0f4f9] hover:bg-[#d3e3fd] text-[#041e49] text-[11px]">
                        Shortlink 2 (AAhg)
                    </button>
                    <button type="button" onclick="setSample('https://mydverse02.blogspot.com/p/flp-120926.html')" class="px-2.5 py-1 rounded-lg bg-[#f0f4f9] hover:bg-[#d3e3fd] text-[#041e49] text-[11px]">
                        Target 1 (flp-120926)
                    </button>
                    <button type="button" onclick="setSample('https://mydverse02.blogspot.com/p/mbmb-030826.html')" class="px-2.5 py-1 rounded-lg bg-[#f0f4f9] hover:bg-[#d3e3fd] text-[#041e49] text-[11px]">
                        Target 2 (mbmb-030826)
                    </button>
                </div>
            </form>

            <!-- Real-time Progress & Results Box -->
            <div id="resultBox" class="hidden rounded-2xl p-5 border transition-all space-y-4">
                <div class="flex items-center gap-2">
                    <span id="statusBadge" class="px-3 py-1 rounded-full text-xs font-bold inline-flex items-center gap-1.5">
                        Processing...
                    </span>
                </div>
                <div id="progressText" class="text-xs text-[#5f6368] font-mono leading-relaxed"></div>

                <!-- Output Box with Link -->
                <div id="outputSection" class="hidden space-y-3 pt-2 border-t border-slate-200">
                    <div class="text-xs font-bold text-[#137333]">
                        Button Page Created Successfully.
                    </div>

                    <div class="space-y-1.5">
                        <label class="text-[11px] font-bold text-slate-700 block">Button Page Link (Public):</label>
                        <div class="flex items-center gap-2 bg-white rounded-xl p-2 border border-slate-300 shadow-2xs">
                            <input type="text" id="pageUrlOutput" readonly class="flex-1 px-2 py-1 font-mono text-xs sm:text-sm font-bold text-blue-700 bg-transparent outline-none select-all" />
                            <button type="button" onclick="copyResultLink()" class="px-3 py-1.5 rounded-lg bg-[#0b57d0] hover:bg-[#0842a0] text-white text-xs font-bold cursor-pointer">
                                Copy Link
                            </button>
                            <a href="#" id="pageOpenLink" target="_blank" class="px-3 py-1.5 rounded-lg bg-[#e8f0fe] hover:bg-[#d3e3fd] text-[#0b57d0] text-xs font-bold inline-flex items-center gap-1">
                                <span>Open Page ↗</span>
                            </a>
                            <a href="#" id="pageEditLink" class="px-3 py-1.5 rounded-lg bg-[#f0f4f9] hover:bg-[#e1e7f0] text-[#1f1f1f] text-xs font-bold inline-flex items-center gap-1">
                                <span>Edit</span>
                            </a>
                        </div>
                    </div>

                    <!-- Final / Target Destination URL (Admin Only) -->
                    <div class="space-y-1.5">
                        <label class="text-[11px] font-bold text-slate-700 block">Target / Final Destination URL:</label>
                        <div class="flex items-center gap-2 bg-white rounded-xl p-2 border border-slate-300 shadow-2xs">
                            <input type="text" id="targetUrlOutput" readonly class="flex-1 px-2 py-1 font-mono text-xs text-slate-700 bg-transparent outline-none select-all truncate" />
                            <button type="button" onclick="copyTargetLink()" class="px-3 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold cursor-pointer">
                                Copy Target
                            </button>
                            <a href="#" id="targetUrlOpenLink" target="_blank" class="px-3 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold inline-flex items-center gap-1">
                                <span>Visit Target ↗</span>
                            </a>
                        </div>
                    </div>

                    <div id="extractedSummary" class="text-xs text-[#444746] bg-white/70 p-3 rounded-xl border border-slate-200 space-y-1"></div>
                </div>
            </div>
        </div>
    </main>
    </div>

    <!-- Top Right Notification Toast Container -->
    <div id="toastContainer" class="fixed top-5 right-5 z-50 flex flex-col items-end gap-3 pointer-events-none max-w-sm w-full"></div>

    <script>
        const baseUrl = '<?= $base_url ?>';

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
                    <div class="text-xs font-medium text-slate-800 leading-snug mt-0.5 break-words">${message}</div>
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

        function setSample(url) {
            document.getElementById('shortenUrl').value = url;
            showNotification('Sample shortlink loaded', 'success');
        }

        async function handleGenerate(e) {
            e.preventDefault();
            const input = document.getElementById('shortenUrl');
            const submitBtn = document.getElementById('submitBtn');
            const resultBox = document.getElementById('resultBox');
            const statusBadge = document.getElementById('statusBadge');
            const progressText = document.getElementById('progressText');
            const outputSection = document.getElementById('outputSection');

            const url = input.value.trim();
            if (!url) {
                showNotification('Please enter a shortened URL or link first.', 'error');
                return;
            }

            submitBtn.disabled = true;
            submitBtn.innerHTML = `
                <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white inline-block" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                Resolving & Generating...
            `;
            resultBox.className = 'rounded-2xl p-5 border border-blue-200 bg-blue-50/50 space-y-4';
            resultBox.classList.remove('hidden');
            outputSection.classList.add('hidden');

            statusBadge.className = 'px-3 py-1 rounded-full text-xs font-bold bg-blue-100 text-blue-700';
            statusBadge.innerText = 'Step 1/3: Resolving Destination...';
            progressText.innerText = 'Contacting shortlink gateway and verifying target destination...';

            // Progressive step updates
            const stepTimers = [];
            stepTimers.push(setTimeout(() => {
                if (submitBtn.disabled) {
                    statusBadge.innerText = 'Step 2/3: Bypassing Intermediate Gateway...';
                    progressText.innerText = 'Bypassing gateway hops to reach target Blogspot destination...';
                }
            }, 3000));
            stepTimers.push(setTimeout(() => {
                if (submitBtn.disabled) {
                    statusBadge.innerText = 'Step 3/3: Extracting Episode Buttons...';
                    progressText.innerText = 'Target reached! Parsing and extracting verified download links...';
                }
            }, 8000));

            // Setup AbortController for 45s timeout to allow gateway counter wait and redirects
            const controller = new AbortController();
            const timeoutDuration = 45000;
            const timeoutId = setTimeout(() => {
                controller.abort();
            }, timeoutDuration);

            try {
                const res = await fetch('api.php?action=create_page_from_url', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ url: url }),
                    signal: controller.signal
                });

                clearTimeout(timeoutId);
                stepTimers.forEach(t => clearTimeout(t));

                let data;
                const rawText = await res.text();
                try {
                    data = JSON.parse(rawText);
                } catch (jsonErr) {
                    if (res.status === 401) {
                        throw new Error('Unauthorized: Admin session expired. Please refresh the page and log in again.');
                    } else if (res.status >= 500) {
                        throw new Error('Server error (' + res.status + '): The cPanel host took too long or encountered a PHP execution error.');
                    } else {
                        throw new Error('Unexpected server response: ' + rawText.substring(0, 150));
                    }
                }

                if (!res.ok || !data.success) {
                    throw new Error(data.error || 'Failed to generate page.');
                }

                resultBox.className = 'rounded-2xl p-5 border border-emerald-200 bg-emerald-50/50 space-y-4';
                statusBadge.className = 'px-3 py-1 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800';
                statusBadge.innerText = 'Page Published Successfully';
                progressText.innerText = 'Gateway successfully resolved and page published.';

                const pageUrl = data.data.clean_url || (baseUrl + '/view.php?slug=' + data.data.slug);
                document.getElementById('pageUrlOutput').value = pageUrl;
                document.getElementById('pageOpenLink').href = data.data.view_url || pageUrl;
                document.getElementById('pageEditLink').href = 'pages.php?edit=' + data.data.id;

                const targetUrl = data.data.resolved_url || data.data.original_url || '';
                document.getElementById('targetUrlOutput').value = targetUrl;
                document.getElementById('targetUrlOpenLink').href = targetUrl || '#';

                const summary = document.getElementById('extractedSummary');
                summary.innerHTML = `
                    <div><strong>Page Title:</strong> ${data.data.title}</div>
                    <div><strong>Extracted Buttons:</strong> ${data.data.button_count} episode buttons</div>
                    <div><strong>Final Destination:</strong> <a href="${targetUrl}" target="_blank" class="text-blue-600 hover:underline font-mono">${targetUrl}</a></div>
                    <div><strong>Access:</strong> Public (Non-Indexable)</div>
                `;

                outputSection.classList.remove('hidden');
                showNotification('✓ Page generated and published successfully!', 'success');

            } catch (err) {
                clearTimeout(timeoutId);
                stepTimers.forEach(t => clearTimeout(t));

                let friendlyMsg = err.message || 'An unexpected error occurred.';
                if (err.name === 'AbortError' || err.message.includes('aborted')) {
                    friendlyMsg = 'Request timed out after 30 seconds. The target shortlink host or Blogspot destination did not respond in time. Please check your network connection or verify the link.';
                }

                resultBox.className = 'rounded-2xl p-5 border border-rose-200 bg-rose-50/60 space-y-4';
                statusBadge.className = 'px-3 py-1 rounded-full text-xs font-bold bg-rose-100 text-rose-800';
                statusBadge.innerText = err.name === 'AbortError' ? 'Gateway Timeout' : 'Generation Error';
                progressText.innerText = friendlyMsg;

                showNotification(friendlyMsg, 'error');
            } finally {
                submitBtn.disabled = false;
                submitBtn.innerHTML = `
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                    </svg>
                    Resolve & Create Page
                `;
            }
        }

        function copyResultLink() {
            const el = document.getElementById('pageUrlOutput');
            el.select();
            navigator.clipboard.writeText(el.value);
            showNotification('✓ Button Page Link copied to clipboard!', 'success');
        }

        function copyTargetLink() {
            const el = document.getElementById('targetUrlOutput');
            el.select();
            navigator.clipboard.writeText(el.value);
            showNotification('✓ Target Destination URL copied to clipboard!', 'success');
        }
    </script>
</body>
</html>
