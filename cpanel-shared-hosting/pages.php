<?php
/**
 * Admin Panel: /pages
 * Matches React AdminSidebar + AppHeader + PagesManager design
 * View, edit, delete, and toggle public/private visibility for all button pages.
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

$pages = SLEA_Datastore::get_all_pages();
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$base_url = rtrim($protocol . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']), '/\\');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Pages (/pages) - <?= htmlspecialchars($site_name) ?></title>
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

                <!-- Pages (Active) -->
                <a href="pages.php" aria-current="page" class="w-12 h-12 rounded-2xl flex flex-col items-center justify-center text-center transition-all cursor-pointer group relative bg-[#0b57d0] text-white shadow-xs" title="Pages (/pages)">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                        <path d="m12.83 2.18a2 2 0 0 0-1.66 0L2.6 6.08a1 1 0 0 0 0 1.83l8.58 3.91a2 2 0 0 0 1.66 0l8.58-3.9a1 1 0 0 0 0-1.83Z"/><path d="m22 17.65-9.17 4.16a2 2 0 0 1-1.66 0L2 17.65"/><path d="m22 12.65-9.17 4.16a2 2 0 0 1-1.66 0L2 12.65"/>
                    </svg>
                    <span class="text-[9px] font-bold mt-0.5 tracking-tight text-white">Pages</span>
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
                                <span class="font-semibold text-[#0b57d0] truncate">Manage Pages (/pages)</span>
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

        <!-- Main Workspace (Matches React PagesManager.tsx) -->
        <main class="flex-1 w-full max-w-5xl mx-auto px-3.5 sm:px-6 py-5 sm:py-7">
            <div class="w-full space-y-4">
                <div class="bg-white rounded-2xl p-5 sm:p-6 border border-[#e0e4eb] shadow-xs space-y-4">
                    <!-- Header Row -->
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-[#f0f4f9]">
                        <div class="space-y-0.5">
                            <h2 class="text-base font-bold text-[#111827] flex items-center gap-2">
                                <svg class="w-4 h-4 text-[#0b57d0]" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                    <path d="m12.83 2.18a2 2 0 0 0-1.66 0L2.6 6.08a1 1 0 0 0 0 1.83l8.58 3.91a2 2 0 0 0 1.66 0l8.58-3.9a1 1 0 0 0 0-1.83Z"/><path d="m22 17.65-9.17 4.16a2 2 0 0 1-1.66 0L2 17.65"/><path d="m22 12.65-9.17 4.16a2 2 0 0 1-1.66 0L2 12.65"/>
                                </svg>
                                <span>Active Button Pages (<span id="totalPagesCount"><?= count($pages) ?></span>)</span>
                            </h2>
                            <p class="text-xs text-[#5f6368]">
                                Manage, preview, and bulk delete generated episode button pages.
                            </p>
                        </div>

                        <div class="flex items-center gap-2 flex-wrap">
                            <button
                                type="button"
                                id="bulkDeleteBtn"
                                onclick="deleteSelectedPages()"
                                class="hidden px-3 py-1.5 rounded-lg bg-[#ba1a1a] hover:bg-[#93000a] text-white text-xs font-semibold items-center gap-1.5 cursor-pointer transition-all shadow-2xs"
                                title="Delete all selected pages"
                            >
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                    <path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/><line x1="10" x2="10" y1="11" y2="17"/><line x1="14" x2="14" y1="11" y2="17"/>
                                </svg>
                                <span>Delete Selected (<span id="selectedCountBadge">0</span>)</span>
                            </button>

                            <input
                                type="text"
                                id="filterInput"
                                oninput="filterPages()"
                                placeholder="Filter pages..."
                                class="px-3 py-1.5 rounded-lg border border-[#c4c7c5] text-xs outline-none focus:border-[#0b57d0]"
                            />
                            <button
                                type="button"
                                onclick="window.location.reload()"
                                class="p-2 rounded-lg text-[#5f6368] hover:bg-[#f0f4f9] border border-[#e1e7f0] cursor-pointer transition-colors"
                                title="Refresh list"
                            >
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                    <path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"/><path d="M21 3v5h-5"/><path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16"/><path d="M8 16H3v5"/>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <!-- Selection bar if pages exist -->
                    <div id="selectionBar" class="<?= empty($pages) ? 'hidden' : 'flex' ?> items-center justify-between px-3 py-2 bg-[#f8fafd] rounded-xl border border-[#e0e4eb] text-xs text-[#444746]">
                        <label class="flex items-center gap-2 cursor-pointer select-none">
                            <input
                                type="checkbox"
                                id="selectAllCheckbox"
                                onchange="toggleSelectAll(this.checked)"
                                class="w-4 h-4 text-[#0b57d0] rounded border-[#c4c7c5] focus:ring-[#0b57d0] cursor-pointer"
                            />
                            <span class="font-semibold text-[11px]" id="selectAllLabel">
                                Select All (<span id="visiblePagesCount"><?= count($pages) ?></span> visible)
                            </span>
                        </label>

                        <div id="selectionActionInfo" class="hidden items-center gap-2">
                            <span class="text-[11px] font-bold text-[#0b57d0]" id="selectedPagesText">
                                0 pages selected
                            </span>
                            <button
                                type="button"
                                onclick="clearSelection()"
                                class="text-[11px] text-[#5f6368] hover:text-[#111827] underline cursor-pointer"
                            >
                                Clear
                            </button>
                        </div>
                    </div>

                    <!-- Pages Card List (Matches React PagesManager.tsx) -->
                    <div id="pagesListContainer" class="space-y-3">
                        <?php if (empty($pages)): ?>
                            <div id="no-pages-row" class="py-12 text-center text-xs text-[#747775] space-y-2">
                                <svg class="w-8 h-8 mx-auto text-[#c4c7c5]" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                    <path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M10 9H8"/><path d="M16 13H8"/><path d="M16 17H8"/>
                                </svg>
                                <p>No button pages match your filter or none created yet.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($pages as $p):
                                $clean_url = $base_url . '/p/' . urlencode($p['slug']);
                                $is_public = !empty($p['is_public']);
                                $btn_count = count($p['buttons'] ?? []);
                                $views_count = intval($p['views'] ?? 0);
                                $created_date = !empty($p['created_at']) ? date('n/j/Y', strtotime($p['created_at'])) : '';
                            ?>
                            <div
                                id="page-row-<?= $p['id'] ?>"
                                data-search="<?= htmlspecialchars(strtolower(($p['title'] ?? '') . ' ' . ($p['slug'] ?? ''))) ?>"
                                class="page-item-card bg-white hover:bg-[#fdfdff] rounded-xl p-4 border transition-all shadow-2xs flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-[#e3e7ee] hover:border-[#c2e7ff]"
                            >
                                <div class="flex items-start sm:items-center gap-3 min-w-0 flex-1">
                                    <input
                                        type="checkbox"
                                        value="<?= $p['id'] ?>"
                                        onchange="updateSelectionState()"
                                        class="page-checkbox mt-1 sm:mt-0 w-4 h-4 text-[#0b57d0] rounded border-[#c4c7c5] focus:ring-[#0b57d0] cursor-pointer shrink-0"
                                    />

                                    <div class="space-y-1 min-w-0 flex-1">
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <span class="font-bold text-sm text-[#111827] break-words" id="row-title-<?= $p['id'] ?>">
                                                <?= htmlspecialchars($p['title']) ?>
                                            </span>
                                            <span class="px-2 py-0.5 rounded-full bg-[#e6f4ea] text-[#137333] text-[10px] font-bold shrink-0">
                                                <?= $views_count ?> views
                                            </span>
                                            <span class="px-2 py-0.5 rounded-full bg-[#f0f4f9] text-[#444746] text-[10px] font-mono shrink-0" id="row-btns-<?= $p['id'] ?>">
                                                <?= $btn_count ?> buttons
                                            </span>

                                            <!-- Interactive Public / Private Toggle Pill -->
                                            <button
                                                type="button"
                                                onclick="toggleStatus(<?= $p['id'] ?>)"
                                                id="status-badge-<?= $p['id'] ?>"
                                                title="Click to toggle Public / Private visibility"
                                                class="px-2 py-0.5 rounded-full text-[10px] font-bold cursor-pointer transition-colors shrink-0 <?= $is_public ? 'bg-[#e6f4ea] text-[#137333] border border-[#a8dab5]' : 'bg-[#fff0d4] text-[#b06000] border border-[#feebc8]' ?>"
                                            >
                                                <?= $is_public ? '● Public' : '○ Private' ?>
                                            </button>
                                        </div>

                                        <div class="flex items-center gap-2 text-[11px] text-[#747775] flex-wrap">
                                            <a href="<?= htmlspecialchars($clean_url) ?>" target="_blank" class="font-mono text-[#0b57d0] hover:underline shrink-0" id="row-link-<?= $p['id'] ?>">
                                                /p/<?= htmlspecialchars($p['slug']) ?>
                                            </a>
                                            <span>•</span>
                                            <span class="shrink-0"><?= htmlspecialchars($created_date) ?></span>
                                            <?php if (!empty($p['resolved_url'])): ?>
                                                <span>•</span>
                                                <span class="break-all max-w-[280px] font-mono text-[10px] truncate">
                                                    <?= htmlspecialchars($p['resolved_url']) ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>

                                <div class="flex items-center gap-1.5 shrink-0 self-end sm:self-auto pl-7 sm:pl-0">
                                    <button
                                        type="button"
                                        onclick="copyText('<?= htmlspecialchars($clean_url, ENT_QUOTES) ?>')"
                                        class="px-2.5 py-1.5 rounded-lg bg-[#f0f4f9] hover:bg-[#d3e3fd] text-[#041e49] text-xs font-semibold flex items-center gap-1.5 cursor-pointer transition-colors"
                                        title="Copy Public URL"
                                    >
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                            <rect width="14" height="14" x="8" y="8" rx="2" ry="2"/><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/>
                                        </svg>
                                        <span>Copy Link</span>
                                    </button>

                                    <a
                                        href="<?= htmlspecialchars($clean_url) ?>"
                                        target="_blank"
                                        id="row-preview-<?= $p['id'] ?>"
                                        class="px-2.5 py-1.5 rounded-lg bg-[#e8f0fe] hover:bg-[#c2e7ff] text-[#0b57d0] text-xs font-semibold flex items-center gap-1.5 cursor-pointer transition-colors"
                                    >
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                            <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/>
                                        </svg>
                                        <span>Preview</span>
                                    </a>

                                    <button
                                        type="button"
                                        onclick="openEditModal(<?= $p['id'] ?>)"
                                        class="px-2.5 py-1.5 rounded-lg bg-[#f0f4f9] hover:bg-[#e1e7f0] text-[#444746] text-xs font-semibold flex items-center gap-1 cursor-pointer transition-colors"
                                        title="Edit Page & Episode Buttons"
                                    >
                                        <span>Edit</span>
                                    </button>

                                    <button
                                        type="button"
                                        onclick="deletePage(<?= $p['id'] ?>, '<?= htmlspecialchars(addslashes($p['title'])) ?>')"
                                        class="p-1.5 rounded-lg text-[#c5221f] hover:bg-[#fce8e6] cursor-pointer transition-colors"
                                        title="Delete Page"
                                    >
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                            <path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/><line x1="10" x2="10" y1="11" y2="17"/><line x1="14" x2="14" y1="11" y2="17"/>
                                        </svg>
                                    </button>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Edit Page Modal Backdrop -->
    <div id="editModal" class="fixed inset-0 bg-black/60 z-50 hidden items-center justify-center p-4 backdrop-blur-xs">
        <div class="bg-white rounded-3xl max-w-2xl w-full max-h-[90vh] overflow-y-auto p-6 sm:p-7 space-y-5 shadow-2xl border border-slate-100">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-xl bg-[#e8f0fe] text-[#0b57d0] flex items-center justify-center font-bold">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-slate-900" id="editModalTitle">Edit Button Page</h3>
                        <p class="text-[11px] text-slate-500">Update title, direct slug URL, public visibility, or individual episode links</p>
                    </div>
                </div>
                <button type="button" onclick="closeEditModal()" class="w-8 h-8 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-600 font-bold flex items-center justify-center transition-colors cursor-pointer">✕</button>
            </div>

            <form id="editForm" onsubmit="savePageEdit(event)" class="space-y-4">
                <input type="hidden" id="editPageId" />

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="space-y-1">
                        <label class="text-xs font-bold text-slate-700 block">Page Title</label>
                        <input type="text" id="editTitle" required class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 text-xs font-semibold focus:border-blue-600 outline-none" />
                    </div>

                    <div class="space-y-1">
                        <label class="text-xs font-bold text-slate-700 block">Slug (URL Path)</label>
                        <div class="flex items-center rounded-xl border border-slate-300 focus-within:border-blue-600 overflow-hidden bg-slate-50">
                            <span class="px-2.5 text-xs text-slate-400 font-mono select-none">/p/</span>
                            <input type="text" id="editSlug" required class="w-full py-2.5 pr-3 text-xs font-mono font-bold text-blue-700 bg-transparent outline-none" />
                        </div>
                    </div>
                </div>

                <div class="space-y-1">
                    <label class="text-xs font-bold text-slate-700 block">Description / Notice (Optional)</label>
                    <textarea id="editDescription" rows="2" class="w-full px-3.5 py-2 rounded-xl border border-slate-300 text-xs focus:border-blue-600 outline-none" placeholder="Optional notes for visitors..."></textarea>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 p-4 bg-slate-50 rounded-2xl border border-slate-200">
                    <!-- Public / Private Toggle Switch inside Modal -->
                    <div class="space-y-1.5">
                        <label class="text-xs font-bold text-slate-800 block">Visibility Status</label>
                        <div class="flex items-center gap-3">
                            <button type="button" id="modalVisibilityToggle" onclick="toggleModalVisibility()" role="switch" aria-checked="true"
                                class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out bg-[#137333]">
                                <span id="modalVisibilityThumb" class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow-md ring-0 transition duration-200 ease-in-out translate-x-5"></span>
                            </button>
                            <div>
                                <div id="modalVisibilityLabel" class="text-xs font-bold text-[#137333]">Public</div>
                                <div id="modalVisibilitySub" class="text-[10px] text-slate-500">Accessible by link</div>
                            </div>
                        </div>
                        <input type="hidden" id="editIsPublic" value="1" />
                    </div>

                    <div class="space-y-1">
                        <label class="text-xs font-bold text-slate-800 block">Theme Color</label>
                        <select id="editTheme" class="w-full px-3.5 py-2 rounded-xl border border-slate-300 text-xs font-semibold bg-white focus:border-blue-600 outline-none">
                            <option value="indigo">Indigo Blue (Material 3)</option>
                            <option value="emerald">Emerald Green</option>
                            <option value="crimson">Crimson Red</option>
                            <option value="slate">Slate Minimal</option>
                            <option value="dark">Dark Cinema</option>
                        </select>
                    </div>
                </div>

                <!-- Buttons Editor -->
                <div class="space-y-2 pt-2 border-t border-slate-100">
                    <div class="flex items-center justify-between">
                        <div>
                            <label class="text-xs font-bold text-slate-800 block">Episode Buttons</label>
                            <p class="text-[10px] text-slate-500">Direct download or server links</p>
                        </div>
                        <button type="button" onclick="addNewButtonRow()" class="px-3 py-1 rounded-lg bg-blue-50 text-blue-600 hover:bg-blue-100 text-xs font-bold transition-colors cursor-pointer">
                            + Add Button
                        </button>
                    </div>
                    <div id="buttonsContainer" class="space-y-2.5 max-h-56 overflow-y-auto pr-1"></div>
                </div>

                <div class="flex items-center justify-end gap-2 pt-4 border-t border-slate-100">
                    <button type="button" onclick="closeEditModal()" class="px-4 py-2.5 rounded-xl border border-slate-300 text-xs font-semibold text-slate-600 hover:bg-slate-50 cursor-pointer">
                        Cancel
                    </button>
                    <button type="submit" id="saveEditBtn" class="px-6 py-2.5 rounded-xl bg-[#0b57d0] hover:bg-[#0842a0] text-white text-xs font-bold shadow-md cursor-pointer transition-all">
                        Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Top Right Notification Toast Container (Matches React Toast.tsx) -->
    <aside id="toastContainer" aria-label="Notifications" class="fixed top-5 right-5 z-50 flex flex-col items-end gap-3 pointer-events-none max-w-sm w-[calc(100%-2.5rem)]"></aside>

    <script>
        const baseUrl = '<?= $base_url ?>';
        let ALL_PAGES = <?= json_encode($pages, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?> || [];

        function filterPages() {
            const query = document.getElementById('filterInput').value.toLowerCase();
            const cards = document.querySelectorAll('.page-item-card');
            let visible = 0;
            cards.forEach(c => {
                const text = (c.getAttribute('data-search') || c.innerText).toLowerCase();
                const show = text.includes(query);
                c.style.display = show ? '' : 'none';
                if (show) visible++;
            });
            const visEl = document.getElementById('visiblePagesCount');
            if (visEl) visEl.innerText = visible;
            updateSelectionState();
        }

        function copyText(txt) {
            navigator.clipboard.writeText(txt);
            showToast('Button page link copied to clipboard!', 'success');
        }

        async function toggleStatus(id) {
            const badge = document.getElementById('status-badge-' + id);

            try {
                const res = await fetch('api.php?action=toggle_page_status', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: id })
                });
                const data = await res.json();
                if (data.success && data.page) {
                    const isPub = Number(data.page.is_public) === 1;
                    const pageObj = ALL_PAGES.find(p => p.id == id);
                    if (pageObj) pageObj.is_public = isPub ? 1 : 0;

                    if (badge) {
                        if (isPub) {
                            badge.className = 'px-2 py-0.5 rounded-full text-[10px] font-bold cursor-pointer transition-colors shrink-0 bg-[#e6f4ea] text-[#137333] border border-[#a8dab5]';
                            badge.innerText = '● Public';
                            showToast('Page status changed to Public', 'success');
                        } else {
                            badge.className = 'px-2 py-0.5 rounded-full text-[10px] font-bold cursor-pointer transition-colors shrink-0 bg-[#fff0d4] text-[#b06000] border border-[#feebc8]';
                            badge.innerText = '○ Private';
                            showToast('Page status changed to Private (Hidden)', 'info');
                        }
                    }
                } else {
                    showToast('Failed to update status: ' + (data.error || 'Unknown error'), 'error');
                }
            } catch (e) {
                showToast('Request failed: ' + e.message, 'error');
            }
        }

        function toggleModalVisibility() {
            const hiddenInput = document.getElementById('editIsPublic');
            const current = Number(hiddenInput.value) === 1;
            setModalVisibilityState(!current);
        }

        function setModalVisibilityState(isPublic) {
            const hiddenInput = document.getElementById('editIsPublic');
            const toggleBtn = document.getElementById('modalVisibilityToggle');
            const thumb = document.getElementById('modalVisibilityThumb');
            const label = document.getElementById('modalVisibilityLabel');
            const sub = document.getElementById('modalVisibilitySub');

            hiddenInput.value = isPublic ? '1' : '0';
            toggleBtn.setAttribute('aria-checked', isPublic ? 'true' : 'false');

            if (isPublic) {
                toggleBtn.className = 'relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out bg-[#137333]';
                thumb.className = 'pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow-md ring-0 transition duration-200 ease-in-out translate-x-5';
                label.className = 'text-xs font-bold text-[#137333]';
                label.innerText = 'Public';
                sub.innerText = 'Accessible by visitors with link';
            } else {
                toggleBtn.className = 'relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out bg-slate-300';
                thumb.className = 'pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow-md ring-0 transition duration-200 ease-in-out translate-x-0';
                label.className = 'text-xs font-bold text-[#b06000]';
                label.innerText = 'Private';
                sub.innerText = 'Hidden & returns 404 to public';
            }
        }

        async function deletePage(id, title) {
            if (!confirm(`Are you sure you want to delete "${title}"?`)) return;
            try {
                const res = await fetch('api.php?action=delete_page', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: id })
                });
                const data = await res.json();
                if (data.success) {
                    const row = document.getElementById('page-row-' + id);
                    if (row) row.remove();
                    ALL_PAGES = ALL_PAGES.filter(p => p.id != id);
                    const totalEl = document.getElementById('totalPagesCount');
                    if (totalEl) totalEl.innerText = ALL_PAGES.length;
                    showToast('Page deleted successfully.', 'info');
                    filterPages();
                } else {
                    showToast('Delete failed: ' + data.error, 'error');
                }
            } catch (e) {
                showToast('Error: ' + e.message, 'error');
            }
        }

        function toggleSelectAll(checked) {
            const checkboxes = document.querySelectorAll('.page-checkbox');
            checkboxes.forEach(cb => {
                const card = cb.closest('.page-item-card');
                if (card && card.style.display !== 'none') {
                    cb.checked = checked;
                }
            });
            updateSelectionState();
        }

        function clearSelection() {
            document.querySelectorAll('.page-checkbox').forEach(cb => { cb.checked = false; });
            updateSelectionState();
        }

        function updateSelectionState() {
            const checkedBoxes = document.querySelectorAll('.page-checkbox:checked');
            const allCards = document.querySelectorAll('.page-item-card');
            const bulkBtn = document.getElementById('bulkDeleteBtn');
            const badge = document.getElementById('selectedCountBadge');
            const selectAll = document.getElementById('selectAllCheckbox');
            const selectionActionInfo = document.getElementById('selectionActionInfo');
            const selectedPagesText = document.getElementById('selectedPagesText');

            allCards.forEach(card => {
                const cb = card.querySelector('.page-checkbox');
                if (cb && cb.checked) {
                    card.classList.add('border-[#0b57d0]', 'bg-[#f0f4f9]/50');
                    card.classList.remove('border-[#e3e7ee]');
                } else {
                    card.classList.remove('border-[#0b57d0]', 'bg-[#f0f4f9]/50');
                    card.classList.add('border-[#e3e7ee]');
                }
            });

            const count = checkedBoxes.length;
            if (badge) badge.innerText = count;
            if (selectedPagesText) selectedPagesText.innerText = `${count} page${count === 1 ? '' : 's'} selected`;

            if (count > 0) {
                if (bulkBtn) {
                    bulkBtn.classList.remove('hidden');
                    bulkBtn.classList.add('flex');
                }
                if (selectionActionInfo) {
                    selectionActionInfo.classList.remove('hidden');
                    selectionActionInfo.classList.add('flex');
                }
            } else {
                if (bulkBtn) {
                    bulkBtn.classList.remove('flex');
                    bulkBtn.classList.add('hidden');
                }
                if (selectionActionInfo) {
                    selectionActionInfo.classList.remove('flex');
                    selectionActionInfo.classList.add('hidden');
                }
            }

            const visibleCheckboxes = Array.from(document.querySelectorAll('.page-checkbox')).filter(cb => {
                const c = cb.closest('.page-item-card');
                return c && c.style.display !== 'none';
            });

            if (selectAll && visibleCheckboxes.length > 0) {
                const allVisChecked = visibleCheckboxes.every(cb => cb.checked);
                selectAll.checked = allVisChecked;
                selectAll.indeterminate = count > 0 && !allVisChecked;
            }
        }

        async function deleteSelectedPages() {
            const checkedBoxes = document.querySelectorAll('.page-checkbox:checked');
            const ids = Array.from(checkedBoxes).map(cb => cb.value);
            if (ids.length === 0) return;

            if (!confirm(`Are you sure you want to permanently delete ${ids.length} selected button page${ids.length === 1 ? '' : 's'}?`)) {
                return;
            }

            const bulkBtn = document.getElementById('bulkDeleteBtn');
            if (bulkBtn) {
                bulkBtn.disabled = true;
            }

            try {
                const res = await fetch('api.php?action=bulk_delete_pages', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ ids: ids })
                });
                const data = await res.json();
                if (data.success) {
                    ids.forEach(id => {
                        const row = document.getElementById('page-row-' + id);
                        if (row) row.remove();
                        ALL_PAGES = ALL_PAGES.filter(p => p.id != id);
                    });
                    const totalEl = document.getElementById('totalPagesCount');
                    if (totalEl) totalEl.innerText = ALL_PAGES.length;
                    showToast(`Successfully deleted ${ids.length} page${ids.length === 1 ? '' : 's'}.`, 'success');
                    filterPages();
                } else {
                    showToast('Bulk delete failed: ' + (data.error || 'Unknown error'), 'error');
                }
            } catch (err) {
                showToast('Bulk delete request error: ' + err.message, 'error');
            } finally {
                if (bulkBtn) {
                    bulkBtn.disabled = false;
                    updateSelectionState();
                }
            }
        }

        function openEditModal(pageId) {
            const page = ALL_PAGES.find(p => p.id == pageId);
            if (!page) {
                showToast('Page data not found for ID: ' + pageId, 'error');
                return;
            }

            document.getElementById('editPageId').value = page.id;
            document.getElementById('editTitle').value = page.title || '';
            document.getElementById('editSlug').value = page.slug || '';
            document.getElementById('editDescription').value = page.description || '';
            document.getElementById('editTheme').value = page.theme || 'indigo';

            setModalVisibilityState(Number(page.is_public) === 1);

            const container = document.getElementById('buttonsContainer');
            container.innerHTML = '';
            const btns = Array.isArray(page.buttons) ? page.buttons : [];
            if (btns.length === 0) {
                addNewButtonRow();
            } else {
                btns.forEach(btn => {
                    addButtonRow(btn.text || '', btn.url || '', btn.quality || '', btn.episode || '');
                });
            }

            const modal = document.getElementById('editModal');
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }

        function closeEditModal() {
            const modal = document.getElementById('editModal');
            modal.classList.remove('flex');
            modal.classList.add('hidden');
        }

        function addButtonRow(text = '', url = '', quality = '', episode = '') {
            const container = document.getElementById('buttonsContainer');
            const row = document.createElement('div');
            row.className = 'grid grid-cols-12 gap-2 bg-slate-50 p-2.5 rounded-2xl border border-slate-200 button-item-row items-center';
            row.innerHTML = `
                <div class="col-span-5">
                    <input type="text" placeholder="Button Label (e.g. Episode 1)" value="${escapeHtml(text)}" class="btn-text w-full px-2.5 py-1.5 rounded-lg border border-slate-300 text-xs font-medium outline-none focus:border-blue-600" required />
                </div>
                <div class="col-span-5">
                    <input type="url" placeholder="Destination URL" value="${escapeHtml(url)}" class="btn-url w-full px-2.5 py-1.5 rounded-lg border border-slate-300 text-xs font-mono outline-none focus:border-blue-600" required />
                </div>
                <div class="col-span-1">
                    <input type="text" placeholder="Quality (720p)" value="${escapeHtml(quality)}" class="btn-quality w-full px-2 py-1.5 rounded-lg border border-slate-300 text-xs text-center outline-none focus:border-blue-600" />
                </div>
                <div class="col-span-1 text-center">
                    <button type="button" onclick="this.closest('.button-item-row').remove()" class="text-red-500 hover:text-red-700 hover:bg-red-50 p-1.5 rounded-lg text-sm font-bold transition-colors cursor-pointer" title="Delete Button">✕</button>
                </div>
            `;
            container.appendChild(row);
        }

        function addNewButtonRow() {
            addButtonRow('', '', '720p', '');
        }

        async function savePageEdit(e) {
            e.preventDefault();
            const saveBtn = document.getElementById('saveEditBtn');
            saveBtn.disabled = true;
            saveBtn.innerText = 'Saving Changes...';

            const id = document.getElementById('editPageId').value;
            const title = document.getElementById('editTitle').value.trim();
            const slug = document.getElementById('editSlug').value.trim();
            const description = document.getElementById('editDescription').value.trim();
            const isPublic = parseInt(document.getElementById('editIsPublic').value, 10);
            const theme = document.getElementById('editTheme').value;

            const buttonRows = document.querySelectorAll('.button-item-row');
            const buttons = [];
            buttonRows.forEach(r => {
                const t = r.querySelector('.btn-text').value.trim();
                const u = r.querySelector('.btn-url').value.trim();
                const q = r.querySelector('.btn-quality').value.trim();
                if (t && u) {
                    buttons.push({
                        text: t,
                        url: u,
                        quality: q || null
                    });
                }
            });

            try {
                const res = await fetch('api.php?action=update_page', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        id: id,
                        title: title,
                        slug: slug,
                        description: description,
                        is_public: isPublic,
                        theme: theme,
                        buttons: buttons
                    })
                });
                const data = await res.json();
                if (data.success && data.page) {
                    const idx = ALL_PAGES.findIndex(p => p.id == id);
                    if (idx !== -1) {
                        ALL_PAGES[idx] = data.page;
                    }

                    const rowTitle = document.getElementById('row-title-' + id);
                    const rowLink = document.getElementById('row-link-' + id);
                    const rowPreview = document.getElementById('row-preview-' + id);
                    const rowBtns = document.getElementById('row-btns-' + id);
                    const badge = document.getElementById('status-badge-' + id);

                    if (rowTitle) rowTitle.innerText = data.page.title;
                    if (rowLink) {
                        rowLink.innerText = '/p/' + data.page.slug;
                        rowLink.href = baseUrl + '/p/' + encodeURIComponent(data.page.slug);
                    }
                    if (rowPreview) {
                        rowPreview.href = baseUrl + '/p/' + encodeURIComponent(data.page.slug);
                    }
                    if (rowBtns) {
                        rowBtns.innerText = (data.page.buttons ? data.page.buttons.length : 0) + ' buttons';
                    }
                    if (badge) {
                        const isPub = Number(data.page.is_public) === 1;
                        badge.className = 'px-2 py-0.5 rounded-full text-[10px] font-bold cursor-pointer transition-colors shrink-0 ' +
                            (isPub ? 'bg-[#e6f4ea] text-[#137333] border border-[#a8dab5]' : 'bg-[#fff0d4] text-[#b06000] border border-[#feebc8]');
                        badge.innerText = isPub ? '● Public' : '○ Private';
                    }

                    closeEditModal();
                    showToast('Page updated successfully!', 'success');
                } else {
                    showToast('Error updating page: ' + (data.error || 'Unknown error'), 'error');
                }
            } catch (err) {
                showToast('Request failed: ' + err.message, 'error');
            } finally {
                saveBtn.disabled = false;
                saveBtn.innerText = 'Save Changes';
            }
        }

        function escapeToastHtml(str) {
            return String(str || '').replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/'/g, '&#039;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
        }

        function escapeHtml(str) {
            return escapeToastHtml(str);
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

        window.addEventListener('DOMContentLoaded', () => {
            const urlParams = new URLSearchParams(window.location.search);
            const editId = urlParams.get('edit') || urlParams.get('id');
            if (editId) {
                openEditModal(editId);
            }
        });
    </script>
</body>
</html>
