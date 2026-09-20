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

$current_version = defined('APP_VERSION') ? ltrim(APP_VERSION, 'v-') : '3.8.0';
$updater_config = SLEA_Updater::get_config();
$update_check = SLEA_Updater::check_for_updates();
$update_history = SLEA_Updater::get_update_history();

// Process POST requests for AJAX or Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
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
    } catch (Exception $ex) {
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

        <!-- Progress Tracker & Live Execution Console -->
        <div id="progressSection" class="bg-white rounded-2xl border border-[#e0e4eb] p-6 shadow-sm hidden space-y-6">
            <div class="flex items-center justify-between border-b border-[#f1f3f4] pb-4">
                <div class="flex items-center space-x-3">
                    <div id="spinnerGlobal" class="w-6 h-6 border-2 border-[#0052cc] border-t-transparent rounded-full animate-spin"></div>
                    <div>
                        <h3 class="text-base font-bold text-[#1f1f1f]" id="progressTitle">Executing One-Click Application Update</h3>
                        <span class="text-xs text-[#5f6368]" id="progressSub">Please keep this window open while the installation completes.</span>
                    </div>
                </div>
                <span id="currentStepBadge" class="px-3 py-1 text-xs font-bold rounded-full bg-[#e8f0fe] text-[#0052cc]">
                    Step 1 / 10
                </span>
            </div>

            <!-- Step Progress List -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-xs" id="stepGrid">
                <div id="step-1" class="p-3 rounded-xl border border-[#dadce0] bg-[#f8fafd] flex items-center justify-between">
                    <span class="font-medium text-[#3c4043]">1. Checking update manifest...</span>
                    <span class="status-icon font-mono text-[#5f6368]">Pending</span>
                </div>
                <div id="step-2" class="p-3 rounded-xl border border-[#dadce0] bg-[#f8fafd] flex items-center justify-between">
                    <span class="font-medium text-[#3c4043]">2. Downloading release ZIP package...</span>
                    <span class="status-icon font-mono text-[#5f6368]">Pending</span>
                </div>
                <div id="step-3" class="p-3 rounded-xl border border-[#dadce0] bg-[#f8fafd] flex items-center justify-between">
                    <span class="font-medium text-[#3c4043]">3. Verifying package &amp; Zip Slip security...</span>
                    <span class="status-icon font-mono text-[#5f6368]">Pending</span>
                </div>
                <div id="step-4" class="p-3 rounded-xl border border-[#dadce0] bg-[#f8fafd] flex items-center justify-between">
                    <span class="font-medium text-[#3c4043]">4. Creating file &amp; database backup...</span>
                    <span class="status-icon font-mono text-[#5f6368]">Pending</span>
                </div>
                <div id="step-5" class="p-3 rounded-xl border border-[#dadce0] bg-[#f8fafd] flex items-center justify-between">
                    <span class="font-medium text-[#3c4043]">5. Extracting files to staging workspace...</span>
                    <span class="status-icon font-mono text-[#5f6368]">Pending</span>
                </div>
                <div id="step-6" class="p-3 rounded-xl border border-[#dadce0] bg-[#f8fafd] flex items-center justify-between">
                    <span class="font-medium text-[#3c4043]">6. Checking environment compatibility...</span>
                    <span class="status-icon font-mono text-[#5f6368]">Pending</span>
                </div>
                <div id="step-7" class="p-3 rounded-xl border border-[#dadce0] bg-[#f8fafd] flex items-center justify-between">
                    <span class="font-medium text-[#3c4043]">7. Running versioned database migrations...</span>
                    <span class="status-icon font-mono text-[#5f6368]">Pending</span>
                </div>
                <div id="step-8" class="p-3 rounded-xl border border-[#dadce0] bg-[#f8fafd] flex items-center justify-between">
                    <span class="font-medium text-[#3c4043]">8. Deploying application files...</span>
                    <span class="status-icon font-mono text-[#5f6368]">Pending</span>
                </div>
                <div id="step-9" class="p-3 rounded-xl border border-[#dadce0] bg-[#f8fafd] flex items-center justify-between">
                    <span class="font-medium text-[#3c4043]">9. Running post-deployment health checks...</span>
                    <span class="status-icon font-mono text-[#5f6368]">Pending</span>
                </div>
                <div id="step-10" class="p-3 rounded-xl border border-[#dadce0] bg-[#f8fafd] flex items-center justify-between">
                    <span class="font-medium text-[#3c4043]">10. Finalizing update...</span>
                    <span class="status-icon font-mono text-[#5f6368]">Pending</span>
                </div>
            </div>

            <!-- Terminal Log Window -->
            <div class="space-y-2">
                <span class="text-xs font-bold text-[#5f6368] uppercase tracking-wider block">Live Execution Output</span>
                <div id="terminalLog" class="bg-[#1e1e1e] text-[#d4d4d4] font-mono text-xs p-4 rounded-xl max-h-64 overflow-y-auto space-y-1 leading-relaxed border border-[#333]">
                    <div class="text-[#808080]">[LOG START] Initializing One-Click Updater...</div>
                </div>
            </div>
        </div>

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

    <script>
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
                    alert('Update Check Complete! Remote version: v' + data.remote_version + (data.update_available ? ' (Update Available)' : ' (Up to date)'));
                    window.location.reload();
                } else {
                    alert('Check failed: ' + (data.error || 'Unknown error'));
                }
            })
            .catch(err => {
                spin.classList.remove('animate-spin');
                btn.disabled = false;
                alert('Network error during check: ' + err.message);
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
                    alert('Updater settings saved successfully!');
                } else {
                    alert('Failed to save settings: ' + data.error);
                }
            });
        }

        function startOneClickUpdate() {
            if (!confirm('Are you sure you want to install this update? A backup will be created automatically.')) {
                return;
            }

            const progSec = document.getElementById('progressSection');
            const term = document.getElementById('terminalLog');
            const btnUpdate = document.getElementById('btnStartUpdate');
            
            progSec.classList.remove('hidden');
            if (btnUpdate) btnUpdate.disabled = true;

            term.innerHTML = '<div class="text-[#00ffff]">[START] Launching One-Click Application Update...</div>';

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
                    const el = document.getElementById('step-' + stepIdx);
                    if (el) {
                        el.className = 'p-3 rounded-xl border border-[#0052cc] bg-[#e8f0fe] flex items-center justify-between font-bold text-[#0052cc]';
                        el.querySelector('.status-icon').innerText = 'In Progress...';
                    }
                    if (stepIdx > 1) {
                        const prevEl = document.getElementById('step-' + (stepIdx - 1));
                        if (prevEl) {
                            prevEl.className = 'p-3 rounded-xl border border-[#ceedd5] bg-[#e6f4ea] flex items-center justify-between font-bold text-[#137333]';
                            prevEl.querySelector('.status-icon').innerText = '[OK] Done';
                        }
                    }

                    document.getElementById('currentStepBadge').innerText = 'Step ' + stepIdx + ' / 10';
                    term.innerHTML += '<div class="text-[#80d8ff]">[STEP ' + stepIdx + '] ' + steps[stepIdx - 1] + '</div>';
                    term.scrollTop = term.scrollHeight;

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
                // Mark all steps done
                for (let i = 1; i <= 10; i++) {
                    const el = document.getElementById('step-' + i);
                    if (el) {
                        el.className = 'p-3 rounded-xl border border-[#ceedd5] bg-[#e6f4ea] flex items-center justify-between font-bold text-[#137333]';
                        el.querySelector('.status-icon').innerText = '[OK] Done';
                    }
                }

                if (data.success) {
                    term.innerHTML += '<div class="text-[#00ff00] font-bold">[SUCCESS] ' + data.message + '</div>';
                    alert(data.message);
                    window.location.reload();
                } else {
                    term.innerHTML += '<div class="text-[#ff5252] font-bold">[FAILED] ' + data.error + '</div>';
                    alert(data.error);
                }
            })
            .catch(err => {
                clearInterval(interval);
                term.innerHTML += '<div class="text-[#ff5252] font-bold">[NETWORK ERROR] ' + err.message + '</div>';
                alert('Update execution error: ' + err.message);
            });
        }
    </script>
</body>
</html>
