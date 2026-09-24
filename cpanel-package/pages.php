<?php
/**
 * Admin Panel: /pages
 * View, edit, delete, and toggle public/private visibility for all button pages.
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
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$base_url = rtrim($protocol . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']), '/\\');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Button Pages (/pages) - <?= htmlspecialchars($site_name) ?></title>
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

                <!-- 2. Pages Icon (Active) -->
                <a href="pages.php" class="w-12 h-12 rounded-2xl flex flex-col items-center justify-center text-center transition-all group bg-[#0b57d0] text-white shadow-xs" title="Pages (/pages)">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                    </svg>
                    <span class="text-[9px] font-bold mt-0.5 tracking-tight text-white">Pages</span>
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
            <div class="max-w-6xl mx-auto px-4 sm:px-6 py-3 sm:py-3.5 flex items-center justify-between gap-3 min-w-0">
                <div class="flex items-center gap-2.5 min-w-0 flex-1">
                    <div class="w-8 h-8 rounded-lg bg-blue-50 border border-blue-100 flex items-center justify-center text-blue-600 shrink-0">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                        </svg>
                    </div>
                    <div class="min-w-0 flex-1">
                        <h1 class="font-bold text-sm sm:text-base text-[#1f1f1f] leading-tight truncate">
                            <?= htmlspecialchars(APP_NAME) ?>
                        </h1>
                        <span class="text-[11px] text-[#5f6368] block truncate">Pages Manager (/pages)</span>
                    </div>
                </div>

                <div class="flex items-center gap-2.5 shrink-0">
                    <a href="admin.php" class="px-3 py-1.5 rounded-lg text-xs font-bold bg-[#0b57d0] text-white hover:bg-[#0842a0] transition-colors shadow-2xs inline-flex items-center gap-1 shrink-0">
                        + New Page
                    </a>
                    <span class="text-xs font-semibold text-slate-600 hidden sm:inline shrink-0">
                        <?= htmlspecialchars($current_user['username']) ?>
                    </span>
                </div>
            </div>
        </header>

        <!-- Main Content Container -->
        <main class="max-w-6xl mx-auto px-4 sm:px-6 py-6 sm:py-8 flex-1 w-full space-y-6">
        <!-- Title & Filter Header -->
        <div class="bg-white rounded-3xl p-5 sm:p-6 border border-[#e0e4eb] shadow-xs flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div class="space-y-1">
                <h2 class="text-lg font-bold text-[#111827] flex items-center gap-2">
                    <span>Manage Episode Button Pages</span>
                    <span class="text-xs font-semibold px-2.5 py-0.5 rounded-full bg-[#e8f0fe] text-[#0b57d0]">
                        <?= count($pages) ?> Total
                    </span>
                </h2>
                <p class="text-xs text-[#5f6368]">
                    Control public vs private visibility switches, edit episode buttons, and inspect traffic analytics.
                </p>
            </div>

            <div class="flex items-center gap-2 flex-wrap sm:flex-nowrap">
                <button type="button" id="bulkDeleteBtn" onclick="deleteSelectedPages()"
                    class="hidden px-3.5 py-2 rounded-xl bg-red-600 hover:bg-red-700 text-white text-xs font-bold transition-all shadow-xs items-center gap-1.5 cursor-pointer">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                    <span>Delete Selected (<span id="selectedCountBadge">0</span>)</span>
                </button>
                <input type="text" id="filterInput" onkeyup="filterPages()" placeholder="Search title or slug..."
                    class="px-3.5 py-2 rounded-xl border border-[#c4c7c5] focus:border-[#0b57d0] outline-none text-xs w-44 sm:w-60" />
                <a href="admin.php" class="px-4 py-2 rounded-xl bg-[#0b57d0] hover:bg-[#0842a0] text-white text-xs font-bold transition-all shadow-2xs whitespace-nowrap flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    <span>New Page</span>
                </a>
            </div>
        </div>

        <!-- Pages Table -->
        <div class="bg-white rounded-3xl border border-[#e0e4eb] shadow-xs overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs" id="pagesTable">
                    <thead class="bg-[#f8fafd] border-b border-[#e0e4eb] text-[#5f6368] font-bold uppercase tracking-wider text-[11px]">
                        <tr>
                            <th class="py-4 px-4 sm:px-5 w-10 text-center">
                                <input type="checkbox" id="selectAllCheckbox" onchange="toggleSelectAll(this.checked)"
                                    class="w-4 h-4 rounded border-slate-300 text-[#0b57d0] focus:ring-[#0b57d0] cursor-pointer" title="Select All Pages" />
                            </th>
                            <th class="py-4 px-4 sm:px-6">Page Title & Slug</th>
                            <th class="py-4 px-4">Visibility Switch</th>
                            <th class="py-4 px-4 text-center">Buttons</th>
                            <th class="py-4 px-4 text-center">Views</th>
                            <th class="py-4 px-4">Created</th>
                            <th class="py-4 px-4 sm:px-6 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#f0f4f9]">
                        <?php if (empty($pages)): ?>
                            <tr id="no-pages-row">
                                <td colspan="7" class="py-12 text-center text-slate-400">
                                    No button pages created yet. Click "+ New Page" to generate your first page.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($pages as $p):
                                $clean_url = $base_url . '/p/' . urlencode($p['slug']);
                                $view_url = $clean_url;
                                $is_public = !empty($p['is_public']);
                                $btn_count = count($p['buttons'] ?? []);
                            ?>
                            <tr id="page-row-<?= $p['id'] ?>" class="hover:bg-[#fafcff] transition-colors">
                                <td class="py-4 px-4 sm:px-5 w-10 text-center">
                                    <input type="checkbox" class="page-checkbox w-4 h-4 rounded border-slate-300 text-[#0b57d0] focus:ring-[#0b57d0] cursor-pointer"
                                        value="<?= $p['id'] ?>" onchange="updateSelectionState()" />
                                </td>
                                <td class="py-4 px-4 sm:px-6 min-w-[240px]">
                                    <div class="font-bold text-sm text-[#111827] truncate max-w-xs sm:max-w-sm" id="row-title-<?= $p['id'] ?>">
                                        <?= htmlspecialchars($p['title']) ?>
                                    </div>
                                    <div class="flex items-center gap-1.5 text-[11px] text-[#0b57d0] font-mono mt-0.5">
                                        <a href="<?= htmlspecialchars($clean_url) ?>" target="_blank" class="hover:underline" id="row-link-<?= $p['id'] ?>">
                                            /p/<?= htmlspecialchars($p['slug']) ?>
                                        </a>
                                        <button type="button" onclick="copyText('<?= htmlspecialchars($clean_url) ?>')" title="Copy URL" class="text-slate-400 hover:text-slate-700 cursor-pointer">
                                            📋
                                        </button>
                                    </div>
                                </td>

                                <!-- Interactive Public / Private Switch Toggle -->
                                <td class="py-4 px-4 whitespace-nowrap">
                                    <div class="flex items-center gap-2.5">
                                        <button type="button" role="switch" aria-checked="<?= $is_public ? 'true' : 'false' ?>"
                                            onclick="toggleStatus(<?= $p['id'] ?>)" id="switch-btn-<?= $p['id'] ?>"
                                            title="Click to toggle Public / Private"
                                            class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none <?= $is_public ? 'bg-[#137333]' : 'bg-slate-300' ?>">
                                            <span id="switch-thumb-<?= $p['id'] ?>" class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow-md ring-0 transition duration-200 ease-in-out <?= $is_public ? 'translate-x-5' : 'translate-x-0' ?>"></span>
                                        </button>
                                        <span id="status-badge-<?= $p['id'] ?>" class="px-2 py-0.5 rounded-md text-[11px] font-bold <?= $is_public ? 'bg-[#e6f4ea] text-[#137333]' : 'bg-[#fff0d4] text-[#b06000]' ?>">
                                            <?= $is_public ? 'Public' : 'Private' ?>
                                        </span>
                                    </div>
                                </td>

                                <td class="py-4 px-4 text-center whitespace-nowrap">
                                    <span class="px-2.5 py-1 rounded-lg bg-[#f0f4f9] text-[#444746] font-mono font-bold text-[11px]" id="row-btns-<?= $p['id'] ?>">
                                        <?= $btn_count ?> btns
                                    </span>
                                </td>

                                <td class="py-4 px-4 text-center whitespace-nowrap">
                                    <span class="font-mono font-bold text-[#137333] bg-[#e6f4ea] px-2.5 py-0.5 rounded-full">
                                        <?= intval($p['views'] ?? 0) ?>
                                    </span>
                                </td>

                                <td class="py-4 px-4 whitespace-nowrap text-[#747775] text-[11px]">
                                    <?= htmlspecialchars(substr($p['created_at'] ?? '', 0, 10)) ?>
                                </td>

                                <td class="py-4 px-4 sm:px-6 text-right whitespace-nowrap space-x-1.5">
                                    <button type="button" onclick="openEditModal(<?= $p['id'] ?>)"
                                        class="px-3 py-1.5 rounded-xl bg-[#0b57d0] hover:bg-[#0842a0] text-white font-bold text-xs transition-all shadow-2xs cursor-pointer inline-flex items-center gap-1">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                        <span>Edit</span>
                                    </button>
                                    <a href="<?= htmlspecialchars($view_url) ?>" target="_blank"
                                        class="px-2.5 py-1.5 rounded-xl bg-[#e8f0fe] hover:bg-[#d3e3fd] text-[#0b57d0] font-bold text-xs transition-all inline-flex items-center gap-1">
                                        <span>View</span>
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                    </a>
                                    <button type="button" onclick="deletePage(<?= $p['id'] ?>, '<?= htmlspecialchars(addslashes($p['title'])) ?>')"
                                        class="px-2.5 py-1.5 rounded-xl text-[#c5221f] hover:bg-[#fce8e6] font-bold text-xs transition-colors cursor-pointer">
                                        Delete
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
    </div>

    <!-- Edit Page Modal Backdrop -->
    <div id="editModal" class="fixed inset-0 bg-black/60 z-50 hidden items-center justify-center p-4 backdrop-blur-xs">
        <div class="bg-white rounded-3xl max-w-2xl w-full max-h-[90vh] overflow-y-auto p-6 sm:p-7 space-y-5 shadow-2xl border border-slate-100">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center font-bold">
                        ✏️
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-slate-900" id="editModalTitle">Edit Button Page</h3>
                        <p class="text-[11px] text-slate-500">Update title, direct slug URL, public visibility, or individual episode links</p>
                    </div>
                </div>
                <button type="button" onclick="closeEditModal()" class="w-8 h-8 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-600 font-bold flex items-center justify-center transition-colors">✕</button>
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
                        <button type="button" onclick="addNewButtonRow()" class="px-3 py-1 rounded-lg bg-blue-50 text-blue-600 hover:bg-blue-100 text-xs font-bold transition-colors">
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

    <!-- Floating Global Toast Notification Container -->
    <div id="toastContainer" class="fixed top-5 right-5 z-50 flex flex-col gap-2.5 pointer-events-none max-w-sm w-full"></div>

    <script>
        const baseUrl = '<?= $base_url ?>';
        let ALL_PAGES = <?= json_encode($pages, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?> || [];

        function filterPages() {
            const query = document.getElementById('filterInput').value.toLowerCase();
            const rows = document.querySelectorAll('#pagesTable tbody tr:not(#no-pages-row)');
            rows.forEach(r => {
                const text = r.innerText.toLowerCase();
                r.style.display = text.includes(query) ? '' : 'none';
            });
        }

        function copyText(txt) {
            navigator.clipboard.writeText(txt);
            showToast('Copied link to clipboard: ' + txt);
        }

        async function toggleStatus(id) {
            const btn = document.getElementById('switch-btn-' + id);
            const thumb = document.getElementById('switch-thumb-' + id);
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
                    
                    // Update in local memory
                    const pageObj = ALL_PAGES.find(p => p.id == id);
                    if (pageObj) pageObj.is_public = isPub ? 1 : 0;

                    // Update Switch UI
                    if (isPub) {
                        btn.className = 'relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none bg-[#137333]';
                        thumb.className = 'pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow-md ring-0 transition duration-200 ease-in-out translate-x-5';
                        badge.className = 'px-2 py-0.5 rounded-md text-[11px] font-bold bg-[#e6f4ea] text-[#137333]';
                        badge.innerText = 'Public';
                        showToast('Page status changed to Public');
                    } else {
                        btn.className = 'relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none bg-slate-300';
                        thumb.className = 'pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow-md ring-0 transition duration-200 ease-in-out translate-x-0';
                        badge.className = 'px-2 py-0.5 rounded-md text-[11px] font-bold bg-[#fff0d4] text-[#b06000]';
                        badge.innerText = 'Private';
                        showToast('Page status changed to Private (Hidden)', 'info');
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
            const toggleBtn = document.getElementById('modalVisibilityToggle');
            const thumb = document.getElementById('modalVisibilityThumb');
            const label = document.getElementById('modalVisibilityLabel');
            const sub = document.getElementById('modalVisibilitySub');

            const current = Number(hiddenInput.value) === 1;
            const next = !current;

            hiddenInput.value = next ? '1' : '0';
            toggleBtn.setAttribute('aria-checked', next ? 'true' : 'false');

            if (next) {
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
            if (!confirm(`Are you sure you want to permanently delete "${title}"?`)) return;
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
                    showToast('Page deleted successfully', 'success');
                    updateSelectionState();
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
                const row = cb.closest('tr');
                if (row && row.style.display !== 'none') {
                    cb.checked = checked;
                }
            });
            updateSelectionState();
        }

        function updateSelectionState() {
            const checkedBoxes = document.querySelectorAll('.page-checkbox:checked');
            const totalBoxes = document.querySelectorAll('.page-checkbox');
            const bulkBtn = document.getElementById('bulkDeleteBtn');
            const badge = document.getElementById('selectedCountBadge');
            const selectAll = document.getElementById('selectAllCheckbox');

            const count = checkedBoxes.length;
            if (badge) badge.innerText = count;

            if (count > 0) {
                if (bulkBtn) {
                    bulkBtn.classList.remove('hidden');
                    bulkBtn.classList.add('inline-flex');
                }
            } else {
                if (bulkBtn) {
                    bulkBtn.classList.remove('inline-flex');
                    bulkBtn.classList.add('hidden');
                }
            }

            if (selectAll && totalBoxes.length > 0) {
                selectAll.checked = count === totalBoxes.length;
                selectAll.indeterminate = count > 0 && count < totalBoxes.length;
            }
        }

        async function deleteSelectedPages() {
            const checkedBoxes = document.querySelectorAll('.page-checkbox:checked');
            const ids = Array.from(checkedBoxes).map(cb => cb.value);
            if (ids.length === 0) return;

            if (!confirm(`Are you sure you want to permanently delete ${ids.length} selected page(s)? This action cannot be undone.`)) {
                return;
            }

            const bulkBtn = document.getElementById('bulkDeleteBtn');
            if (bulkBtn) {
                bulkBtn.disabled = true;
                bulkBtn.innerText = `Deleting ${ids.length}...`;
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
                    showToast(`Successfully deleted ${ids.length} page(s).`, 'success');
                    updateSelectionState();

                    const remainingRows = document.querySelectorAll('.page-checkbox');
                    if (remainingRows.length === 0) {
                        const tbody = document.querySelector('#pagesTable tbody');
                        if (tbody) {
                            tbody.innerHTML = `
                                <tr id="no-pages-row">
                                    <td colspan="7" class="py-12 text-center text-slate-400">
                                        No button pages created yet. Click "+ New Page" to generate your first page.
                                    </td>
                                </tr>
                            `;
                        }
                    }
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
                    // Update in local array
                    const idx = ALL_PAGES.findIndex(p => p.id == id);
                    if (idx !== -1) {
                        ALL_PAGES[idx] = data.page;
                    }

                    // Update Table Row directly
                    const rowTitle = document.getElementById('row-title-' + id);
                    const rowLink = document.getElementById('row-link-' + id);
                    const rowBtns = document.getElementById('row-btns-' + id);
                    if (rowTitle) rowTitle.innerText = data.page.title;
                    if (rowLink) {
                        rowLink.innerText = '/p/' + data.page.slug;
                        rowLink.href = baseUrl + '/p/' + encodeURIComponent(data.page.slug);
                    }
                    if (rowBtns) {
                        rowBtns.innerText = (data.page.buttons ? data.page.buttons.length : 0) + ' btns';
                    }

                    // Update Switch in row
                    const isPub = Number(data.page.is_public) === 1;
                    const btn = document.getElementById('switch-btn-' + id);
                    const thumb = document.getElementById('switch-thumb-' + id);
                    const badge = document.getElementById('status-badge-' + id);
                    if (btn && thumb && badge) {
                        if (isPub) {
                            btn.className = 'relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none bg-[#137333]';
                            thumb.className = 'pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow-md ring-0 transition duration-200 ease-in-out translate-x-5';
                            badge.className = 'px-2 py-0.5 rounded-md text-[11px] font-bold bg-[#e6f4ea] text-[#137333]';
                            badge.innerText = 'Public';
                        } else {
                            btn.className = 'relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none bg-slate-300';
                            thumb.className = 'pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow-md ring-0 transition duration-200 ease-in-out translate-x-0';
                            badge.className = 'px-2 py-0.5 rounded-md text-[11px] font-bold bg-[#fff0d4] text-[#b06000]';
                            badge.innerText = 'Private';
                        }
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

        function escapeHtml(str) {
            return String(str || '').replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/'/g, '&#039;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
        }

        // Auto open modal if URL has ?edit=ID
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
