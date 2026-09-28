<?php
/**
 * One-Click Application Update System Web Interface (cPanel)
 * Matches React AdminSidebar + AppHeader + OneClickUpdaterHub design
 * Automated, atomic update system with checksum verification, Zip Slip protection,
 * automated file/database backup, versioned migrations, and zero-downtime rollback.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/class-db.php';
require_once __DIR__ . '/includes/class-auth.php';
require_once __DIR__ . '/includes/class-datastore.php';
require_once __DIR__ . '/includes/class-updater.php';

// Protect with Admin Auth
SLEA_Auth::require_admin();
$current_user = SLEA_Auth::get_current_user();

// Check and abort any interrupted updates if page was refreshed / navigated away
SLEA_Updater::check_and_abort_interrupted_updates();

$current_version = defined('APP_VERSION') ? ltrim(APP_VERSION, 'v-') : '5.8.0';
$updater_config = SLEA_Updater::get_config();
$update_check = SLEA_Updater::check_for_updates();
$update_history = SLEA_Updater::get_update_history();

$site_identity = SLEA_Datastore::get_site_identity();
$site_name = !empty($site_identity['site_name']) ? $site_identity['site_name'] : (defined('APP_NAME') ? APP_NAME : 'Movie Hub');
$site_logo_url = !empty($site_identity['site_logo_url']) ? $site_identity['site_logo_url'] : '';

// Process POST requests for AJAX or Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (function_exists('ob_clean')) @ob_clean();
    header('Content-Type: application/json; charset=utf-8');
    @set_time_limit(300);
    @ini_set('memory_limit', '512M');
    @ini_set('display_errors', '0');
    error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);

    $action = $_POST['action'] ?? ($_GET['action'] ?? '');
    
    try {
        if ($action === 'check') {
            $res = SLEA_Updater::check_for_updates(true);
            echo json_encode($res);
            exit;
        } elseif ($action === 'update') {
            $res = SLEA_Updater::run_update();
            echo json_encode($res);
            exit;
        } elseif ($action === 'save_config') {
            $saved = SLEA_Updater::save_config([
                'manifest_url'     => trim($_POST['manifest_url'] ?? ''),
                'backup_retention' => intval($_POST['backup_retention'] ?? 3),
                'verify_checksum'  => isset($_POST['verify_checksum']) ? true : false,
                'maintenance_mode' => isset($_POST['maintenance_mode']) ? true : false
            ]);
            echo json_encode(['success' => true, 'config' => $saved]);
            exit;
        }
    } catch (Throwable $ex) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => $ex->getMessage()]);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>One-Click Application Updater - <?= htmlspecialchars($site_name) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['"Plus Jakarta Sans"', 'sans-serif'],
                        mono: ['"JetBrains Mono"', 'monospace']
                    }
                }
            }
        }
    </script>
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        .font-mono { font-family: 'JetBrains Mono', monospace; }
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
    <aside class="fixed inset-y-0 left-0 w-16 sm:w-20 bg-white border-r border-[#e1e7f0] z-40 flex flex-col items-center py-4 justify-between shadow-xs select-none">
        <!-- Top Brand Logo -->
        <div class="flex flex-col items-center w-full gap-5">
            <a href="admin.php" class="w-10 h-10 sm:w-11 sm:h-11 rounded-2xl bg-[#c2e7ff] text-[#001d35] flex items-center justify-center font-black text-lg shadow-2xs hover:scale-105 transition-transform cursor-pointer overflow-hidden p-1" title="<?= htmlspecialchars($site_name) ?> Dashboard">
                <?php if (!empty($site_logo_url)): ?>
                    <img src="<?= htmlspecialchars($site_logo_url) ?>" alt="<?= htmlspecialchars($site_name) ?>" class="w-full h-full object-contain rounded-xl" />
                <?php else: ?>
                    <span class="font-bold text-[10px] text-center leading-tight truncate px-0.5"><?= htmlspecialchars($site_name) ?></span>
                <?php endif; ?>
            </a>

            <!-- Navigation Icons -->
            <nav class="flex flex-col items-center w-full gap-2 px-1 sm:px-2">
                <!-- Generate -->
                <a href="admin.php" title="Generate" class="w-12 h-12 sm:w-14 sm:h-14 rounded-2xl flex flex-col items-center justify-center gap-1 transition-all cursor-pointer group relative text-[#5f6368] hover:bg-[#f0f4f9] hover:text-[#1f1f1f]">
                    <svg class="w-5 h-5 transition-transform group-hover:scale-105 text-[#5f6368] group-hover:text-[#1f1f1f]" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="m15 4-1 1 4 4 1-1a2.83 2.83 0 1 0-4-4Z"/><path d="m13 6-8.5 8.5a2.12 2.12 0 1 0 3 3L16 9"/><path d="m18 13 1.5-1.5"/><path d="m15 2-1.5 1.5"/></svg>
                    <span class="text-[9px] font-bold tracking-tight text-[#5f6368]">Generate</span>
                </a>

                <!-- Pages -->
                <a href="pages.php" title="Pages" class="w-12 h-12 sm:w-14 sm:h-14 rounded-2xl flex flex-col items-center justify-center gap-1 transition-all cursor-pointer group relative text-[#5f6368] hover:bg-[#f0f4f9] hover:text-[#1f1f1f]">
                    <svg class="w-5 h-5 transition-transform group-hover:scale-105 text-[#5f6368] group-hover:text-[#1f1f1f]" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><polygon points="12 2 2 7 12 12 22 7 12 2"/><polyline points="2 17 12 22 22 17"/><polyline points="2 12 12 17 22 12"/></svg>
                    <span class="text-[9px] font-bold tracking-tight text-[#5f6368]">Pages</span>
                </a>

                <!-- Settings -->
                <a href="settings.php" title="Settings" class="w-12 h-12 sm:w-14 sm:h-14 rounded-2xl flex flex-col items-center justify-center gap-1 transition-all cursor-pointer group relative text-[#5f6368] hover:bg-[#f0f4f9] hover:text-[#1f1f1f]">
                    <svg class="w-5 h-5 transition-transform group-hover:scale-105 text-[#5f6368] group-hover:text-[#1f1f1f]" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/></svg>
                    <span class="text-[9px] font-bold tracking-tight text-[#5f6368]">Settings</span>
                </a>

                <!-- Update (Active) -->
                <a href="update.php" title="Update" class="w-12 h-12 sm:w-14 sm:h-14 rounded-2xl flex flex-col items-center justify-center gap-1 transition-all cursor-pointer group relative bg-[#e8f0fe] text-[#0b57d0] shadow-2xs">
                    <svg class="w-5 h-5 transition-transform group-hover:scale-105 text-[#0b57d0]" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"/><path d="M21 3v5h-5"/><path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16"/><path d="M8 16H3v5"/></svg>
                    <span class="text-[9px] font-bold tracking-tight text-[#0b57d0]">Update</span>
                    <span class="absolute left-0 top-3 bottom-3 w-1 bg-[#0b57d0] rounded-r-full"></span>
                </a>

                <!-- Analytics -->
                <a href="analytics.php" title="Analytics" class="w-12 h-12 sm:w-14 sm:h-14 rounded-2xl flex flex-col items-center justify-center gap-1 transition-all cursor-pointer group relative text-[#5f6368] hover:bg-[#f0f4f9] hover:text-[#1f1f1f]">
                    <svg class="w-5 h-5 transition-transform group-hover:scale-105 text-[#5f6368] group-hover:text-[#1f1f1f]" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M3 3v16a2 2 0 0 0 2 2h16"/><path d="M18 17V9"/><path d="M13 17V5"/><path d="M8 17v-3"/></svg>
                    <span class="text-[9px] font-bold tracking-tight text-[#5f6368]">Analytics</span>
                </a>
            </nav>
        </div>

        <!-- Bottom Admin Profile & Logout -->
        <div class="flex flex-col items-center gap-3 w-full px-1 sm:px-2">
            <!-- Admin Status Pill -->
            <div class="w-10 h-10 rounded-full bg-[#e6f4ea] border border-[#a8dab5] text-[#137333] flex items-center justify-center font-bold text-xs shadow-2xs" title="Logged in as <?= htmlspecialchars($current_user['username']) ?>">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/><path d="m9 12 2 2 4-4"/></svg>
            </div>

            <!-- Logout Icon Button -->
            <a href="logout.php" title="Sign Out (<?= htmlspecialchars($current_user['username']) ?>)" class="w-12 h-12 rounded-2xl flex flex-col items-center justify-center gap-0.5 text-[#5f6368] hover:bg-[#fce8e6] hover:text-[#c5221f] transition-colors cursor-pointer">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" x2="9" y1="12" y2="12"/></svg>
                <span class="text-[8px] font-bold">Exit</span>
            </a>

            <!-- Version Tag -->
            <span class="text-[10px] font-bold font-mono text-[#444746] bg-slate-100 px-2 py-0.5 rounded-md border border-slate-200">
                <?= htmlspecialchars(APP_VERSION) ?>
            </span>
        </div>
    </aside>

    <!-- Main Content Area -->
    <div class="flex-1 flex flex-col pl-16 sm:pl-20">
        <!-- Top Header (Matches React AppHeader.tsx) -->
        <header class="bg-white border-b border-[#e1e7f0] sticky top-0 z-30">
            <div class="max-w-5xl mx-auto px-4 sm:px-6 h-16 flex items-center justify-between gap-3">
                <div class="flex items-center gap-3 min-w-0">
                    <span class="text-sm sm:text-base font-extrabold text-[#1f1f1f] tracking-tight truncate">
                        One-Click Updater
                    </span>
                    <span class="hidden md:inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-[#e8f0fe] text-[#0b57d0]">
                        Admin Mode (<?= htmlspecialchars($current_user['username']) ?>)
                    </span>
                </div>

                <!-- Right Status Pill -->
                <div class="flex items-center gap-2 text-xs text-[#444746]">
                    <span class="inline-block w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                    <span class="hidden sm:inline font-medium">System Online</span>
                </div>
            </div>
        </header>

        <!-- Main Workspace (Matches React OneClickUpdaterHub.tsx) -->
        <main class="max-w-4xl w-full mx-auto px-4 sm:px-6 py-6 sm:py-8 flex-1">
            <div class="space-y-8 animate-fade-in">

                <!-- Top Banner Header -->
                <div class="bg-white rounded-2xl border border-[#e0e4eb] p-5 sm:p-6 shadow-xs">
                    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-5 sm:gap-6">
                        <div class="space-y-2 min-w-0 flex-1">
                            <div class="flex items-start sm:items-center gap-3.5 flex-wrap sm:flex-nowrap">
                                <div class="w-10 h-10 rounded-xl bg-[#0b57d0] text-white flex items-center justify-center font-bold shrink-0 shadow-xs">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"/><path d="M21 3v5h-5"/><path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16"/><path d="M8 16H3v5"/></svg>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <h2 class="text-lg sm:text-xl font-extrabold text-[#1f1f1f] break-words">
                                            One-Click Application Update System
                                        </h2>
                                        <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold font-mono bg-[#e8f0fe] text-[#0b57d0] border border-[#c2e7ff]">
                                            v<?= htmlspecialchars($current_version) ?>
                                        </span>
                                    </div>
                                    <p class="text-xs text-[#5f6368] mt-0.5 leading-relaxed break-words">
                                        Detects, verifies, backs up, and deploys new application releases on cPanel/shared hosting with zero-downtime rollback.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center gap-2.5 flex-wrap sm:flex-nowrap shrink-0">
                            <button
                                onclick="checkUpdateNow()"
                                id="btnCheck"
                                class="px-4 py-2.5 rounded-xl border border-[#dadce0] text-xs font-semibold text-[#3c4043] bg-white hover:bg-[#f8fafd] transition flex items-center space-x-2 disabled:opacity-50 cursor-pointer shadow-2xs"
                            >
                                <svg id="iconCheckSpin" class="w-4 h-4 text-[#5f6368]" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"/><path d="M21 3v5h-5"/><path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16"/><path d="M8 16H3v5"/></svg>
                                <span>Check for Updates</span>
                            </button>

                            <?php if (!empty($update_check['update_available'])): ?>
                                <button
                                    onclick="startOneClickUpdate()"
                                    id="btnStartUpdate"
                                    class="px-5 py-2.5 rounded-xl text-xs font-bold text-white bg-[#0b57d0] hover:bg-[#0842a0] transition shadow-xs flex items-center space-x-2 cursor-pointer disabled:opacity-50"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M4 14.899A7 7 0 1 1 15.71 8h1.79a4.5 4.5 0 0 1 2.5 8.242"/><path d="M12 12v9"/><path d="m8 17 4 4 4-4"/></svg>
                                    <span>Install Update (v<?= htmlspecialchars($update_check['remote_version']) ?>)</span>
                                </button>
                            <?php else: ?>
                                <span class="px-4 py-2.5 rounded-xl text-xs font-bold text-[#137333] bg-[#e6f4ea] border border-[#ceedd5] flex items-center space-x-1.5 select-none">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="m9 11 3 3L22 4"/></svg>
                                    <span>Up to Date (v<?= htmlspecialchars($current_version) ?>)</span>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Status Metrics -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mt-6 pt-6 border-t border-[#f1f3f4]">
                        <div class="bg-[#f8fafd] p-4 rounded-xl border border-[#e0e4eb]">
                            <span class="text-[11px] font-bold text-[#5f6368] uppercase tracking-wider block mb-1">Installed Version</span>
                            <span class="text-lg font-extrabold text-[#1f1f1f]">v<?= htmlspecialchars($current_version) ?></span>
                        </div>
                        <div class="bg-[#f8fafd] p-4 rounded-xl border border-[#e0e4eb]">
                            <span class="text-[11px] font-bold text-[#5f6368] uppercase tracking-wider block mb-1">Latest Version</span>
                            <span class="text-lg font-extrabold text-[#0052cc]">v<?= htmlspecialchars($update_check['remote_version'] ?? $current_version) ?></span>
                        </div>
                        <div class="bg-[#f8fafd] p-4 rounded-xl border border-[#e0e4eb]">
                            <span class="text-[11px] font-bold text-[#5f6368] uppercase tracking-wider block mb-1">Release Date</span>
                            <span class="text-sm font-semibold text-[#3c4043]"><?= htmlspecialchars($update_check['release_date'] ?? date('Y-m-d')) ?></span>
                        </div>
                        <div class="bg-[#f8fafd] p-4 rounded-xl border border-[#e0e4eb]">
                            <span class="text-[11px] font-bold text-[#5f6368] uppercase tracking-wider block mb-1">Security Guard</span>
                            <span class="text-sm font-semibold text-[#137333] flex items-center space-x-1">
                                <svg class="w-4 h-4 text-[#137333]" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/><path d="m9 12 2 2 4-4"/></svg>
                                <span>Zip Slip &amp; SHA-256 Protected</span>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Release Notes -->
                <?php if (!empty($update_check['release_notes'])): ?>
                    <div class="bg-white rounded-2xl border border-[#e0e4eb] p-6 shadow-sm space-y-4">
                        <h3 class="text-base font-bold text-[#1f1f1f] flex items-center space-x-2">
                            <svg class="w-5 h-5 text-[#0052cc]" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M9.937 15.5A2 2 0 0 0 8.5 14.063l-6.135-1.582a.5.5 0 0 1 0-.962L8.5 9.936A2 2 0 0 0 9.937 8.5l1.582-6.135a.5.5 0 0 1 .963 0L14.063 8.5A2 2 0 0 0 15.5 9.937l6.135 1.581a.5.5 0 0 1 0 .964L15.5 14.063a2 2 0 0 0-1.437 1.437l-1.582 6.135a.5.5 0 0 1-.963 0z"/><path d="M20 3v4"/><path d="M22 5h-4"/><path d="M4 17v2"/><path d="M5 18H3"/></svg>
                            <span>Release Notes &amp; Version Highlights (v<?= htmlspecialchars($update_check['remote_version'] ?? $current_version) ?>)</span>
                        </h3>
                        <ul class="space-y-2 text-xs text-[#3c4043] list-disc list-inside bg-[#f8fafd] p-4 rounded-xl border border-[#e0e4eb] font-medium leading-relaxed">
                            <?php foreach ($update_check['release_notes'] as $note): ?>
                                <li><?= htmlspecialchars($note) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <!-- Updater Configuration -->
                <div class="bg-white rounded-2xl border border-[#e0e4eb] p-6 shadow-sm space-y-6">
                    <div class="flex items-center space-x-3 border-b border-[#f1f3f4] pb-4">
                        <svg class="w-5 h-5 text-[#5f6368]" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/></svg>
                        <div>
                            <h3 class="text-base font-bold text-[#1f1f1f]">Update System Configuration</h3>
                            <p class="text-xs text-[#5f6368]">Configure remote update URLs, checksum security, and retention limits.</p>
                        </div>
                    </div>

                    <form id="formConfig" onsubmit="saveConfig(event)" class="grid grid-cols-1 md:grid-cols-2 gap-6 text-xs">
                        <div class="space-y-1 md:col-span-2">
                            <label class="font-bold text-[#3c4043] uppercase tracking-wider block">Remote Release Manifest URL</label>
                            <input
                                type="text"
                                name="manifest_url"
                                value="<?= htmlspecialchars($updater_config['manifest_url']) ?>"
                                required
                                class="w-full px-3.5 py-2 text-xs rounded-xl border border-[#dadce0] focus:ring-2 focus:ring-[#0052cc] focus:outline-none bg-[#f8fafd]"
                            />
                        </div>

                        <div class="space-y-1">
                            <label class="font-bold text-[#3c4043] uppercase tracking-wider block">Backup Retention Count</label>
                            <input
                                type="number"
                                name="backup_retention"
                                min="1"
                                max="10"
                                value="<?= intval($updater_config['backup_retention']) ?>"
                                class="w-full px-3.5 py-2 text-xs rounded-xl border border-[#dadce0] focus:ring-2 focus:ring-[#0052cc] focus:outline-none bg-[#f8fafd]"
                            />
                        </div>

                        <div class="space-y-3 pt-2">
                            <label class="flex items-center space-x-3 cursor-pointer">
                                <input
                                    type="checkbox"
                                    name="verify_checksum"
                                    <?= !empty($updater_config['verify_checksum']) ? 'checked' : '' ?>
                                    class="w-4 h-4 rounded text-[#0052cc] focus:ring-[#0052cc]"
                                />
                                <span class="font-bold text-[#1f1f1f]">Enable SHA-256 Checksum Verification</span>
                            </label>

                            <label class="flex items-center space-x-3 cursor-pointer">
                                <input
                                    type="checkbox"
                                    name="maintenance_mode"
                                    <?= !empty($updater_config['maintenance_mode']) ? 'checked' : '' ?>
                                    class="w-4 h-4 rounded text-[#0052cc] focus:ring-[#0052cc]"
                                />
                                <span class="font-bold text-[#1f1f1f]">Enable Maintenance Mode During Installation</span>
                            </label>
                        </div>

                        <div class="pt-3 border-t border-[#f1f3f4] flex justify-end md:col-span-2">
                            <button
                                type="submit"
                                class="px-5 py-2.5 rounded-xl bg-[#0b57d0] hover:bg-[#0842a0] text-white font-bold text-xs shadow-xs transition-colors cursor-pointer"
                            >
                                Save Updater Settings
                            </button>
                        </div>
                    </form>
                </div>

                <!-- History & Audit Log -->
                <div class="bg-white rounded-2xl border border-[#e0e4eb] p-6 shadow-sm space-y-4">
                    <h3 class="text-base font-bold text-[#1f1f1f] flex items-center space-x-2">
                        <svg class="w-5 h-5 text-[#5f6368]" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/><path d="M12 7v5l4 2"/></svg>
                        <span>Update Audit History &amp; Rollback Logs</span>
                    </h3>

                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs border-collapse">
                            <thead>
                                <tr class="bg-[#f8fafd] border-b border-[#e0e4eb] text-[#5f6368] font-bold uppercase tracking-wider">
                                    <th class="p-3">Update ID</th>
                                    <th class="p-3">Old Version</th>
                                    <th class="p-3">New Version</th>
                                    <th class="p-3">Status</th>
                                    <th class="p-3">Started At</th>
                                    <th class="p-3">Completed At</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-[#f1f3f4]">
                                <?php if (empty($update_history)): ?>
                                    <tr>
                                        <td colspan="6" class="p-4 text-center text-[#80868b]">No updates have been performed yet.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($update_history as $item): ?>
                                        <tr class="hover:bg-[#f8fafd]">
                                            <td class="p-3 font-mono font-medium text-[#1f1f1f]"><?= htmlspecialchars($item['update_id']) ?></td>
                                            <td class="p-3">v<?= htmlspecialchars($item['old_version']) ?></td>
                                            <td class="p-3 font-bold text-[#0052cc]">v<?= htmlspecialchars($item['new_version']) ?></td>
                                            <td class="p-3">
                                                <?php if ($item['status'] === 'success'): ?>
                                                    <span class="px-2 py-0.5 rounded-full text-[11px] font-bold bg-[#e6f4ea] text-[#137333]">Success</span>
                                                <?php elseif ($item['status'] === 'failed'): ?>
                                                    <span class="px-2 py-0.5 rounded-full text-[11px] font-bold bg-[#fce8e6] text-[#c5221f]">Failed (Rolled Back)</span>
                                                <?php else: ?>
                                                    <span class="px-2 py-0.5 rounded-full text-[11px] font-bold bg-[#e8f0fe] text-[#0052cc]"><?= htmlspecialchars($item['status']) ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="p-3 text-[#5f6368]"><?= htmlspecialchars($item['started_at'] ?? 'N/A') ?></td>
                                            <td class="p-3 text-[#5f6368]"><?= htmlspecialchars($item['completed_at'] ?? 'N/A') ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Diagnostics and Check logs -->
                <?php if (!empty($update_check['diagnostics'])): ?>
                    <div class="bg-white rounded-2xl border border-[#e0e4eb] p-6 shadow-sm space-y-4">
                        <h3 class="text-base font-bold text-[#1f1f1f] flex items-center space-x-2">
                            <svg class="w-5 h-5 text-[#5f6368]" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><polyline points="4 17 10 11 4 5"/><line x1="12" x2="20" y1="19" y2="19"/></svg>
                            <span>Remote Update Diagnostics Log</span>
                        </h3>
                        <p class="text-xs text-[#5f6368] leading-normal">
                            Results of querying configured manifest URLs during the last update check.
                        </p>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-xs border-collapse">
                                <thead>
                                    <tr class="bg-[#f8fafd] border-b border-[#e0e4eb] text-[#5f6368] font-bold uppercase tracking-wider">
                                        <th class="p-3">Tested Candidate URL</th>
                                        <th class="p-3">Status</th>
                                        <th class="p-3">Detected Version</th>
                                        <th class="p-3">Fetch Details / Error</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-[#f1f3f4] font-mono text-[11px] text-[#3c4043]">
                                    <?php foreach ($update_check['diagnostics'] as $url => $diag): ?>
                                        <tr>
                                            <td class="p-3 truncate max-w-xs sm:max-w-md" title="<?= htmlspecialchars($url) ?>"><?= htmlspecialchars($url) ?></td>
                                            <td class="p-3">
                                                <?php if ($diag['status'] === 'success'): ?>
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-[#e6f4ea] text-[#137333]">SUCCESS</span>
                                                <?php else: ?>
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-[#fce8e6] text-[#c5221f]">FAILED</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="p-3"><?= htmlspecialchars($diag['version'] ?? 'N/A') ?></td>
                                            <td class="p-3 <?= $diag['status'] === 'success' ? 'text-[#137333]' : 'text-[#c5221f]' ?>"><?= htmlspecialchars($diag['message']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>

            </div>
        </main>
    </div>

    <!-- Full-Screen Animated Remote Update Modal Overlay with Popup Logs (Matches React OneClickUpdaterHub.tsx) -->
    <div id="updateModalOverlay" class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-md flex items-center justify-center p-4 hidden opacity-0 transition-opacity duration-300">
        <div class="relative w-full max-w-lg bg-white rounded-3xl shadow-2xl border border-slate-200 overflow-hidden transform scale-95 transition-all duration-300" id="updateModalCard">
            <!-- Header Glow Background with Radar effect -->
            <div class="h-32 bg-gradient-to-br from-[#0052cc] via-[#0066ff] to-[#00c6ff] relative flex items-center justify-center overflow-hidden">
                <div class="absolute w-40 h-40 rounded-full border border-white/20 animate-ping opacity-25"></div>
                <div class="absolute w-28 h-28 rounded-full border border-white/30 animate-pulse opacity-40"></div>

                <div class="relative z-10 w-20 h-20 rounded-2xl bg-white/15 backdrop-blur-md border border-white/30 flex items-center justify-center text-white shadow-xl">
                    <svg class="w-10 h-10 animate-bounce" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M4 14.899A7 7 0 1 1 15.71 8h1.79a4.5 4.5 0 0 1 2.5 8.242"/><path d="M12 12v9"/><path d="m8 17 4 4 4-4"/></svg>
                </div>

                <div class="absolute top-3 right-3">
                    <span id="modalVersionBadge" class="px-3 py-1 rounded-full text-[11px] font-mono font-bold bg-white/20 text-white backdrop-blur-sm border border-white/30">
                        v<?= htmlspecialchars($update_check['remote_version'] ?? $current_version) ?>
                    </span>
                </div>
            </div>

            <!-- Body Content -->
            <div class="p-6 sm:p-8 space-y-6">
                <div class="text-center space-y-1.5">
                    <h3 id="modalStatusTitle" class="text-xl font-black text-slate-900 tracking-tight">
                        Installing Remote Update...
                    </h3>
                    <p id="modalStatusSub" class="text-xs sm:text-sm text-slate-500 font-medium">
                        Downloading release package, verifying integrity &amp; deploying files.
                    </p>
                </div>

                <!-- Progress & Percentage -->
                <div class="space-y-2">
                    <div class="flex items-center justify-between text-xs font-bold">
                        <span id="modalCurrentStepLabel" class="text-[#0052cc] flex items-center gap-1.5">
                            <span class="w-2 h-2 rounded-full bg-[#0052cc] animate-ping"></span>
                            <span>Step 1: Checking update manifest...</span>
                        </span>
                        <span id="modalPercentDisplay" class="font-mono text-sm font-black text-[#0052cc]">
                            10%
                        </span>
                    </div>

                    <div class="w-full h-3.5 bg-slate-100 rounded-full overflow-hidden p-0.5 border border-slate-200">
                        <div
                            id="modalProgressBar"
                            class="h-full bg-gradient-to-r from-[#0052cc] via-[#0066ff] to-[#00c6ff] rounded-full transition-all duration-400 ease-out"
                            style="width: 10%"
                        ></div>
                    </div>
                </div>

                <!-- Live Mini Step Dots -->
                <div class="grid grid-cols-5 gap-1.5 pt-1" id="miniStepDots">
                    <div class="h-1.5 rounded-full bg-[#0052cc] shadow-xs transition-all" id="dot-1"></div>
                    <div class="h-1.5 rounded-full bg-slate-200 transition-all" id="dot-2"></div>
                    <div class="h-1.5 rounded-full bg-slate-200 transition-all" id="dot-3"></div>
                    <div class="h-1.5 rounded-full bg-slate-200 transition-all" id="dot-4"></div>
                    <div class="h-1.5 rounded-full bg-slate-200 transition-all" id="dot-5"></div>
                </div>

                <!-- Terminal Mini Line -->
                <div class="bg-slate-900 rounded-xl p-3 text-slate-200 font-mono text-[11px] flex items-center gap-2 overflow-hidden border border-slate-800 shadow-inner">
                    <span class="text-emerald-400 font-bold shrink-0">$</span>
                    <span id="modalLiveLog" class="truncate text-slate-300">
                        [Step 1/10] Checking update manifest...
                    </span>
                </div>

                <p class="text-[11px] text-center text-slate-400 font-medium">
                    ⚡ Please do not close or refresh this tab until completion.
                </p>
            </div>
        </div>
    </div>

    <!-- Top Right Notification Toast Container -->
    <div id="toastContainer" class="fixed top-5 right-5 z-50 flex flex-col items-end gap-3 pointer-events-none max-w-sm w-full"></div>

    <script>
        function escapeToastHtml(str) {
            return String(str || '').replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/'/g, '&#039;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
        }

        function triggerConfettiBlast() {
            if (typeof confetti === 'function') {
                confetti({
                    particleCount: 120,
                    spread: 80,
                    origin: { y: 0.6 },
                    colors: ['#0052cc', '#00c6ff', '#137333', '#fbbc04', '#ea4335']
                });
                setTimeout(() => {
                    confetti({
                        particleCount: 80,
                        angle: 60,
                        spread: 55,
                        origin: { x: 0 }
                    });
                    confetti({
                        particleCount: 80,
                        angle: 120,
                        spread: 55,
                        origin: { x: 1 }
                    });
                }, 300);
            }
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

        function checkUpdateNow() {
            const btn = document.getElementById('btnCheck');
            const spin = document.getElementById('iconCheckSpin');
            spin.classList.add('animate-spin');
            btn.disabled = true;

            fetch('update.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=check'
            })
            .then(res => res.json())
            .then(data => {
                spin.classList.remove('animate-spin');
                btn.disabled = false;
                if (data.success) {
                    showToast('Update check complete! Remote: v' + data.remote_version + (data.update_available ? ' (Update Available)' : ' (Up to date)'), data.update_available ? 'info' : 'success');
                    setTimeout(() => window.location.reload(), 1500);
                } else {
                    showToast('Check failed: ' + (data.error || 'Unknown error'), 'error');
                }
            })
            .catch(err => {
                spin.classList.remove('animate-spin');
                btn.disabled = false;
                showToast('Network error during check: ' + err.message, 'error');
            });
        }

        function saveConfig(e) {
            e.preventDefault();
            const form = document.getElementById('formConfig');
            const body = new URLSearchParams(new FormData(form));
            body.append('action', 'save_config');

            fetch('update.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body.toString()
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    showToast('Updater configuration saved successfully!', 'success');
                } else {
                    showToast('Failed to save settings: ' + data.error, 'error');
                }
            });
        }

        function startOneClickUpdate() {
            if (!confirm('Are you sure you want to install this update? Full backup will be created automatically.')) {
                return;
            }

            const btnUpdate = document.getElementById('btnStartUpdate');
            const modalOverlay = document.getElementById('updateModalOverlay');
            const modalCard = document.getElementById('updateModalCard');
            
            if (btnUpdate) btnUpdate.disabled = true;

            // Show Animated Modal Overlay Popup with Live Logs
            if (modalOverlay) {
                modalOverlay.classList.remove('hidden');
                requestAnimationFrame(() => {
                    modalOverlay.classList.remove('opacity-0');
                    if (modalCard) {
                        modalCard.classList.remove('scale-95');
                        modalCard.classList.add('scale-100');
                    }
                });
            }

            const steps = [
                'Checking update manifest...',
                'Downloading release ZIP package...',
                'Verifying package & Zip Slip security...',
                'Creating file & database backup...',
                'Extracting files to staging workspace...',
                'Checking environment compatibility...',
                'Running versioned database migrations...',
                'Deploying application files...',
                'Running post-deployment health checks...',
                'Finalizing update...'
            ];

            let stepIdx = 1;

            const interval = setInterval(() => {
                if (stepIdx <= 10) {
                    const pct = Math.round((stepIdx / 10) * 100);

                    const modalProgressBar = document.getElementById('modalProgressBar');
                    if (modalProgressBar) modalProgressBar.style.width = pct + '%';

                    const modalPercentDisplay = document.getElementById('modalPercentDisplay');
                    if (modalPercentDisplay) modalPercentDisplay.innerText = pct + '%';

                    const modalCurrentStepLabel = document.getElementById('modalCurrentStepLabel');
                    if (modalCurrentStepLabel) {
                        modalCurrentStepLabel.innerHTML = `<span class="w-2 h-2 rounded-full bg-[#0052cc] animate-ping"></span><span>Step ${stepIdx}: ${steps[stepIdx - 1]}</span>`;
                    }

                    const modalLiveLog = document.getElementById('modalLiveLog');
                    if (modalLiveLog) modalLiveLog.innerText = `[Step ${stepIdx}/10] ${steps[stepIdx - 1]}`;

                    const dotIndex = Math.ceil(stepIdx / 2);
                    for (let d = 1; d <= 5; d++) {
                        const dot = document.getElementById('dot-' + d);
                        if (dot) {
                            if (d <= dotIndex) {
                                dot.className = 'h-1.5 rounded-full bg-[#0052cc] shadow-xs transition-all';
                            } else {
                                dot.className = 'h-1.5 rounded-full bg-slate-200 transition-all';
                            }
                        }
                    }

                    stepIdx++;
                } else {
                    clearInterval(interval);
                }
            }, 600);

            fetch('update.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=update'
            })
            .then(res => res.json())
            .then(data => {
                clearInterval(interval);

                const modalProgressBar = document.getElementById('modalProgressBar');
                if (modalProgressBar) modalProgressBar.style.width = '100%';
                const modalPercentDisplay = document.getElementById('modalPercentDisplay');
                if (modalPercentDisplay) modalPercentDisplay.innerText = '100%';

                if (data.success) {
                    showToast(data.message, 'success');
                    triggerConfettiBlast();

                    const modalStatusTitle = document.getElementById('modalStatusTitle');
                    if (modalStatusTitle) {
                        modalStatusTitle.className = 'text-xl font-black text-emerald-600 tracking-tight flex items-center justify-center gap-2';
                        modalStatusTitle.innerHTML = '<span>Update Installed Successfully!</span> 🎉';
                    }
                    const modalStatusSub = document.getElementById('modalStatusSub');
                    if (modalStatusSub) {
                        modalStatusSub.innerText = 'System is ready. Reloading dashboard in 2 seconds...';
                    }
                    const modalCurrentStepLabel = document.getElementById('modalCurrentStepLabel');
                    if (modalCurrentStepLabel) {
                        modalCurrentStepLabel.innerHTML = '<span class="text-emerald-600 font-bold">✓ All 10 verification steps passed</span>';
                    }
                    const modalLiveLog = document.getElementById('modalLiveLog');
                    if (modalLiveLog) {
                        modalLiveLog.innerHTML = '<span class="text-emerald-400 font-bold">[READY] Version updated successfully.</span>';
                    }

                    setTimeout(() => window.location.reload(), 2400);
                } else {
                    showToast(data.error, 'error');

                    const modalStatusTitle = document.getElementById('modalStatusTitle');
                    if (modalStatusTitle) {
                        modalStatusTitle.className = 'text-xl font-black text-rose-600 tracking-tight';
                        modalStatusTitle.innerText = 'Update Failed';
                    }
                    const modalStatusSub = document.getElementById('modalStatusSub');
                    if (modalStatusSub) {
                        modalStatusSub.innerText = data.error || 'The system was safely rolled back.';
                    }
                }
            })
            .catch(err => {
                clearInterval(interval);
                showToast('Update error: ' + err.message, 'error');
            });
        }
    </script>
</body>
</html>
