<?php
/**
 * One-Click Application Update System Web Interface (cPanel)
 * Automated, atomic update system with checksum verification, Zip Slip protection,
 * automated file/database backup, versioned migrations, and zero-downtime rollback.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/class-db.php';
require_once __DIR__ . '/includes/class-auth.php';
require_once __DIR__ . '/includes/class-updater.php';

// Protect with Admin Auth
SLEA_Auth::require_admin();

// Check and abort any interrupted updates if page was refreshed / navigated away
SLEA_Updater::check_and_abort_interrupted_updates();

$current_version = defined('APP_VERSION') ? ltrim(APP_VERSION, 'v-') : '3.8.0';
$updater_config = SLEA_Updater::get_config();
$update_check = SLEA_Updater::check_for_updates();
$update_history = SLEA_Updater::get_update_history();

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
    <title>One-Click Application Updater - <?php echo htmlspecialchars(APP_NAME); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['"Plus Jakarta Sans"', 'sans-serif'],
                        mono: ['"JetBrains Mono"', 'monospace']
                    },
                    animation: {
                        'pulse-glow': 'pulseGlow 2s cubic-bezier(0.4, 0, 0.6, 1) infinite',
                        'spin-slow': 'spin 4s linear infinite',
                        'bounce-slow': 'bounceSlow 2s ease-in-out infinite',
                        'shimmer': 'shimmer 2s infinite linear'
                    },
                    keyframes: {
                        pulseGlow: {
                            '0%, 100%': { transform: 'scale(1)', opacity: '0.8', filter: 'drop-shadow(0 0 10px rgba(11, 87, 208, 0.4))' },
                            '50%': { transform: 'scale(1.08)', opacity: '1', filter: 'drop-shadow(0 0 25px rgba(11, 87, 208, 0.8))' }
                        },
                        bounceSlow: {
                            '0%, 100%': { transform: 'translateY(0)' },
                            '50%': { transform: 'translateY(-6px)' }
                        },
                        shimmer: {
                            '0%': { backgroundPosition: '-200% 0' },
                            '100%': { backgroundPosition: '200% 0' }
                        }
                    }
                }
            }
        }
    </script>
    <style>
        .progress-striped {
            background-image: linear-gradient(
                45deg,
                rgba(255, 255, 255, 0.2) 25%,
                transparent 25%,
                transparent 50%,
                rgba(255, 255, 255, 0.2) 50%,
                rgba(255, 255, 255, 0.2) 75%,
                transparent 75%,
                transparent
            );
            background-size: 1.5rem 1.5rem;
            animation: moveStripes 1s linear infinite;
        }
        @keyframes moveStripes {
            from { background-position: 0 0; }
            to { background-position: 1.5rem 0; }
        }
        .radar-wave {
            position: absolute;
            border-radius: 50%;
            border: 2px solid rgba(11, 87, 208, 0.4);
            animation: radarPulse 2.5s cubic-bezier(0, 0.2, 0.8, 1) infinite;
        }
        .radar-wave:nth-child(2) {
            animation-delay: 0.8s;
        }
        .radar-wave:nth-child(3) {
            animation-delay: 1.6s;
        }
        @keyframes radarPulse {
            0% { width: 40px; height: 40px; opacity: 1; transform: scale(0.6); }
            100% { width: 220px; height: 220px; opacity: 0; transform: scale(1.6); }
        }
    </style>
</head>
<body class="bg-[#f0f4f9] text-[#1f1f1f] font-sans min-h-screen pb-16 antialiased">
    <!-- Header Navigation -->
    <header class="bg-white border-b border-[#e0e4eb] sticky top-0 z-30 shadow-xs">
        <div class="max-w-6xl mx-auto px-3 sm:px-6 py-2.5 sm:py-3.5 flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-2.5 sm:gap-4">
            <div class="flex items-center space-x-2.5 min-w-0">
                <a href="admin.php" class="p-2 text-[#5f6368] hover:text-[#1a73e8] hover:bg-[#f1f3f4] rounded-xl transition shrink-0" title="Back to Admin">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                </a>
                <div class="h-6 w-px bg-[#dadce0] shrink-0"></div>
                <div class="flex items-center space-x-2.5 min-w-0 flex-1">
                    <div class="w-8 h-8 rounded-lg bg-[#0052cc] flex items-center justify-center text-white font-bold text-sm shrink-0 shadow-2xs">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    </div>
                    <div class="min-w-0 flex-1">
                        <h1 class="text-sm sm:text-base font-bold text-[#1f1f1f] leading-snug truncate">One-Click Application Updater</h1>
                        <p class="text-[11px] sm:text-xs text-[#5f6368] font-medium leading-tight truncate">Production Release Engine &amp; Rollback Manager</p>
                    </div>
                </div>
            </div>
            <div class="flex items-center justify-between sm:justify-end space-x-2.5 w-full sm:w-auto shrink-0 border-t sm:border-t-0 pt-2 sm:pt-0 border-[#f0f4f9]">
                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] sm:text-xs font-semibold bg-[#e8f0fe] text-[#0052cc] border border-[#d3e3fd] shrink-0">
                    v<?php echo htmlspecialchars($current_version); ?> Installed
                </span>
                <a href="admin.php" class="px-3 py-1.5 text-xs font-medium text-[#0052cc] hover:bg-[#f1f3f4] rounded-lg transition border border-[#dadce0] shrink-0 flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    <span>Back to Admin</span>
                </a>
            </div>
        </div>
    </header>

    <main class="max-w-6xl mx-auto px-4 sm:px-6 pt-8 space-y-8">

        <!-- Top Overview Card -->
        <div class="bg-white rounded-2xl border border-[#e0e4eb] p-6 shadow-sm">
            <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-6">
                <div class="space-y-2 min-w-0">
                    <div class="flex items-center gap-3 flex-wrap">
                        <h2 class="text-lg sm:text-xl font-extrabold text-[#1f1f1f] break-words">System Release Status</h2>
                        <?php if ($update_check['update_available']): ?>
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-[#fef7e0] text-[#b06000] border border-[#fde293] animate-pulse">
                                Update Available: v<?php echo htmlspecialchars($update_check['remote_version']); ?>
                            </span>
                        <?php else: ?>
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-[#e6f4ea] text-[#137333] border border-[#ceedd5]">
                                Up to Date (v<?php echo htmlspecialchars($current_version); ?>)
                            </span>
                        <?php endif; ?>
                    </div>
                    <p class="text-xs sm:text-sm text-[#5f6368] leading-relaxed break-words">
                        Automated update system verifies ZIP integrity, creates backups, executes safe application file deployment, and supports instant rollback upon failure.
                    </p>
                </div>

                <div class="flex items-center gap-3 flex-wrap sm:flex-nowrap shrink-0">
                    <button onclick="checkUpdateNow()" id="btnCheck" class="px-4 py-2.5 rounded-xl border border-[#dadce0] text-sm font-semibold text-[#3c4043] bg-white hover:bg-[#f8fafd] transition flex items-center space-x-2">
                        <svg id="iconCheckSpin" class="w-4 h-4 text-[#5f6368]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                        <span>Check for Updates</span>
                    </button>

                    <?php if ($update_check['update_available']): ?>
                        <button onclick="startOneClickUpdate()" id="btnStartUpdate" class="px-5 py-2.5 rounded-xl text-sm font-bold text-white bg-[#0052cc] hover:bg-[#0043a8] transition shadow-md flex items-center space-x-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                            <span>Install Update (v<?php echo htmlspecialchars($update_check['remote_version']); ?>)</span>
                        </button>
                    <?php else: ?>
                        <button disabled class="px-5 py-2.5 rounded-xl text-sm font-semibold text-[#80868b] bg-[#f1f3f4] cursor-not-allowed">
                            Latest Version Installed
                        </button>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Version Details Cards Grid -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mt-6 pt-6 border-t border-[#f1f3f4]">
                <div class="bg-[#f8fafd] p-4 rounded-xl border border-[#e0e4eb]">
                    <span class="text-xs font-semibold text-[#5f6368] uppercase tracking-wider block mb-1">Installed Version</span>
                    <span class="text-lg font-bold text-[#1f1f1f]">v<?php echo htmlspecialchars($current_version); ?></span>
                </div>
                <div class="bg-[#f8fafd] p-4 rounded-xl border border-[#e0e4eb]">
                    <span class="text-xs font-semibold text-[#5f6368] uppercase tracking-wider block mb-1">Latest Version</span>
                    <span class="text-lg font-bold text-[#0052cc]">v<?php echo htmlspecialchars($update_check['remote_version'] ?? $current_version); ?></span>
                </div>
                <div class="bg-[#f8fafd] p-4 rounded-xl border border-[#e0e4eb]">
                    <span class="text-xs font-semibold text-[#5f6368] uppercase tracking-wider block mb-1">Release Date</span>
                    <span class="text-sm font-medium text-[#3c4043]"><?php echo htmlspecialchars($update_check['release_date'] ?? 'N/A'); ?></span>
                </div>
                <div class="bg-[#f8fafd] p-4 rounded-xl border border-[#e0e4eb]">
                    <span class="text-xs font-semibold text-[#5f6368] uppercase tracking-wider block mb-1">Backup Protection</span>
                    <span class="text-sm font-medium text-[#137333] flex items-center space-x-1">
                        <svg class="w-4 h-4 text-[#137333]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        <span>Auto Backup &amp; Rollback</span>
                    </span>
                </div>
            </div>
        </div>

        <!-- Diagnostics and Check logs -->
        <?php if (!empty($update_check['diagnostics'])): ?>
            <div class="bg-white rounded-2xl border border-[#e0e4eb] p-6 shadow-sm space-y-4">
                <h3 class="text-base font-bold text-[#1f1f1f] flex items-center space-x-2">
                    <svg class="w-5 h-5 text-[#5f6368]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                    <span>Remote Update Diagnostics Log</span>
                </h3>
                <p class="text-xs text-[#5f6368] leading-normal">
                    This log details the results of querying all configured manifest check URLs during the last update scan. It shows whether a candidate was successfully reached or if it failed (e.g. redirected or blocked).
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
                                    <td class="p-3 truncate max-w-xs sm:max-w-md" title="<?php echo htmlspecialchars($url); ?>"><?php echo htmlspecialchars($url); ?></td>
                                    <td class="p-3">
                                        <?php if ($diag['status'] === 'success'): ?>
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-[#e6f4ea] text-[#137333]">SUCCESS</span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-[#fce8e6] text-[#c5221f]">FAILED</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="p-3"><?php echo htmlspecialchars($diag['version'] ?? 'N/A'); ?></td>
                                    <td class="p-3 <?php echo $diag['status'] === 'success' ? 'text-[#137333]' : 'text-[#c5221f]'; ?>"><?php echo htmlspecialchars($diag['message']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <!-- Release Notes Section -->
        <?php if (!empty($update_check['release_notes'])): ?>
            <div class="bg-white rounded-2xl border border-[#e0e4eb] p-6 shadow-sm space-y-4">
                <h3 class="text-base font-bold text-[#1f1f1f] flex items-center space-x-2">
                    <svg class="w-5 h-5 text-[#0052cc]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    <span>Release Notes &amp; Features (v<?php echo htmlspecialchars($update_check['remote_version']); ?>)</span>
                </h3>
                <ul class="space-y-2 text-sm text-[#3c4043] list-disc list-inside bg-[#f8fafd] p-4 rounded-xl border border-[#e0e4eb]">
                    <?php foreach ($update_check['release_notes'] as $note): ?>
                        <li class="leading-relaxed"><?php echo htmlspecialchars($note); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <!-- Configuration Settings Section -->
        <div class="bg-white rounded-2xl border border-[#e0e4eb] p-6 shadow-sm space-y-6">
            <div class="flex items-center justify-between border-b border-[#f1f3f4] pb-4">
                <h3 class="text-base font-bold text-[#1f1f1f] flex items-center space-x-2">
                    <svg class="w-5 h-5 text-[#5f6368]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    <span>Update Engine Settings</span>
                </h3>
            </div>

            <form id="formConfig" onsubmit="saveConfig(event)" class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="space-y-1 md:col-span-2">
                    <label class="text-xs font-bold text-[#3c4043] uppercase tracking-wider">Remote Release Manifest URL</label>
                    <input type="text" name="manifest_url" value="<?php echo htmlspecialchars($updater_config['manifest_url']); ?>" required class="w-full px-3.5 py-2 text-sm rounded-xl border border-[#dadce0] focus:ring-2 focus:ring-[#0052cc] focus:outline-none bg-[#f8fafd]">
                    <p class="text-xs text-[#5f6368]">The remote server URL serving release update.json manifest files.</p>
                </div>

                <div class="space-y-1">
                    <label class="text-xs font-bold text-[#3c4043] uppercase tracking-wider">Backup Retention Limit</label>
                    <input type="number" name="backup_retention" min="1" max="10" value="<?php echo intval($updater_config['backup_retention']); ?>" class="w-full px-3.5 py-2 text-sm rounded-xl border border-[#dadce0] focus:ring-2 focus:ring-[#0052cc] focus:outline-none bg-[#f8fafd]">
                    <p class="text-xs text-[#5f6368]">Number of historical release backups to preserve on disk.</p>
                </div>

                <div class="space-y-4 pt-2">
                    <label class="flex items-center space-x-3 cursor-pointer">
                        <input type="checkbox" name="verify_checksum" <?php echo !empty($updater_config['verify_checksum']) ? 'checked' : ''; ?> class="w-4 h-4 rounded text-[#0052cc] focus:ring-[#0052cc]">
                        <span class="text-sm font-semibold text-[#1f1f1f]">Enable SHA-256 Checksum Verification</span>
                    </label>

                    <label class="flex items-center space-x-3 cursor-pointer">
                        <input type="checkbox" name="maintenance_mode" <?php echo !empty($updater_config['maintenance_mode']) ? 'checked' : ''; ?> class="w-4 h-4 rounded text-[#0052cc] focus:ring-[#0052cc]">
                        <span class="text-sm font-semibold text-[#1f1f1f]">Enable Maintenance Mode During Installation</span>
                    </label>
                </div>

                <div class="md:col-span-2 flex justify-end">
                    <button type="submit" class="px-5 py-2 text-sm font-bold text-white bg-[#0052cc] hover:bg-[#0043a8] rounded-xl transition">
                        Save Configuration
                    </button>
                </div>
            </form>
        </div>

        <!-- Update History Section -->
        <div class="bg-white rounded-2xl border border-[#e0e4eb] p-6 shadow-sm space-y-4">
            <h3 class="text-base font-bold text-[#1f1f1f] flex items-center space-x-2">
                <svg class="w-5 h-5 text-[#5f6368]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span>Update Execution History &amp; Audit Log</span>
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
                                    <td class="p-3 font-mono font-medium text-[#1f1f1f]"><?php echo htmlspecialchars($item['update_id']); ?></td>
                                    <td class="p-3">v<?php echo htmlspecialchars($item['old_version']); ?></td>
                                    <td class="p-3 font-semibold text-[#0052cc]">v<?php echo htmlspecialchars($item['new_version']); ?></td>
                                    <td class="p-3">
                                        <?php if ($item['status'] === 'success'): ?>
                                            <span class="px-2 py-0.5 rounded-full font-bold bg-[#e6f4ea] text-[#137333]">Success</span>
                                        <?php elseif ($item['status'] === 'failed'): ?>
                                            <span class="px-2 py-0.5 rounded-full font-bold bg-[#fce8e6] text-[#c5221f]">Failed (Rolled Back)</span>
                                        <?php else: ?>
                                            <span class="px-2 py-0.5 rounded-full font-bold bg-[#e8f0fe] text-[#0052cc]"><?php echo htmlspecialchars($item['status']); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="p-3 text-[#5f6368]"><?php echo htmlspecialchars($item['started_at'] ?? 'N/A'); ?></td>
                                    <td class="p-3 text-[#5f6368]"><?php echo htmlspecialchars($item['completed_at'] ?? 'N/A'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>

    <!-- Interactive Animated Remote Update Modal Overlay -->
    <div id="updateModalOverlay" class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-md flex items-center justify-center p-4 hidden opacity-0 transition-opacity duration-300">
        <div class="relative w-full max-w-lg bg-white rounded-3xl shadow-2xl border border-slate-200 overflow-hidden transform scale-95 transition-transform duration-300" id="updateModalCard">
            <!-- Header Glow Background -->
            <div class="h-32 bg-gradient-to-br from-[#0052cc] via-[#0066ff] to-[#00c6ff] relative flex items-center justify-center overflow-hidden">
                <!-- Radar Waves -->
                <div class="radar-wave"></div>
                <div class="radar-wave"></div>
                <div class="radar-wave"></div>

                <div class="relative z-10 w-20 h-20 rounded-2xl bg-white/15 backdrop-blur-md border border-white/30 flex items-center justify-center text-white shadow-xl animate-pulse-glow">
                    <svg id="modalIconSpin" class="w-10 h-10 animate-bounce-slow" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                    </svg>
                </div>

                <div class="absolute top-3 right-3">
                    <span id="modalVersionBadge" class="px-3 py-1 rounded-full text-[11px] font-mono font-bold bg-white/20 text-white backdrop-blur-sm border border-white/30">
                        v<?php echo htmlspecialchars($update_check['remote_version'] ?? $current_version); ?>
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
                        Downloading package, verifying integrity &amp; applying migrations.
                    </p>
                </div>

                <!-- Animated Progress & Percentage -->
                <div class="space-y-2">
                    <div class="flex items-center justify-between text-xs font-bold">
                        <span id="modalCurrentStepLabel" class="text-[#0052cc] flex items-center gap-1.5">
                            <span class="w-2 h-2 rounded-full bg-[#0052cc] animate-ping"></span>
                            <span>Step 1: Checking manifest...</span>
                        </span>
                        <span id="modalPercentDisplay" class="font-mono text-sm font-black text-[#0052cc]">
                            0%
                        </span>
                    </div>

                    <div class="w-full h-3.5 bg-slate-100 rounded-full overflow-hidden p-0.5 border border-slate-200">
                        <div id="modalProgressBar" class="h-full bg-gradient-to-r from-[#0052cc] via-[#0066ff] to-[#00c6ff] rounded-full progress-striped transition-all duration-400 ease-out" style="width: 0%;"></div>
                    </div>
                </div>

                <!-- Live Mini Step Indicators -->
                <div class="grid grid-cols-5 gap-1.5 pt-1" id="miniStepDots">
                    <div class="h-1.5 rounded-full bg-[#0052cc] transition-all" id="dot-1"></div>
                    <div class="h-1.5 rounded-full bg-slate-200 transition-all" id="dot-2"></div>
                    <div class="h-1.5 rounded-full bg-slate-200 transition-all" id="dot-3"></div>
                    <div class="h-1.5 rounded-full bg-slate-200 transition-all" id="dot-4"></div>
                    <div class="h-1.5 rounded-full bg-slate-200 transition-all" id="dot-5"></div>
                </div>

                <!-- Terminal Mini Line -->
                <div class="bg-slate-900 rounded-xl p-3 text-slate-200 font-mono text-[11px] flex items-center gap-2 overflow-hidden border border-slate-800 shadow-inner">
                    <span class="text-emerald-400 font-bold shrink-0">$</span>
                    <span id="modalLiveLog" class="truncate text-slate-300">Initializing zero-downtime update engine...</span>
                </div>

                <!-- Caution text -->
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
                    showToast('Updater settings saved successfully!', 'success');
                } else {
                    showToast('Failed to save settings: ' + data.error, 'error');
                }
            });
        }

        function startOneClickUpdate() {
            if (!confirm('Are you sure you want to install this update? A backup will be created automatically.')) {
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

                    // Update Modal Overlay Elements
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

                    // Update mini dots in modal
                    const dotIndex = Math.ceil(stepIdx / 2);
                    for (let d = 1; d <= 5; d++) {
                        const dot = document.getElementById('dot-' + d);
                        if (dot) {
                            if (d <= dotIndex) {
                                dot.className = 'h-1.5 rounded-full bg-[#0052cc] shadow-xs';
                            } else {
                                dot.className = 'h-1.5 rounded-full bg-slate-200';
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

                    // Trigger Confetti Blast Animation!
                    triggerConfettiBlast();

                    // Update modal UI with celebration state
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
                showToast('Update execution error: ' + err.message, 'error');
            });
        }
    </script>
</body>
</html>
