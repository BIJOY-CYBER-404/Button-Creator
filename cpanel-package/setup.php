<?php
/**
 * Private One-Time Account Setup Page
 * Matches React App.tsx (logged-out) + AppHeader.tsx + AdminLoginCard.tsx design
 * Only accessible ONCE when the database has zero admin accounts.
 * Permanently locked immediately after first administrator creation.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/class-db.php';
require_once __DIR__ . '/includes/class-auth.php';
require_once __DIR__ . '/includes/class-datastore.php';

$site_identity = SLEA_Datastore::get_site_identity();
$site_name = !empty($site_identity['site_name']) ? $site_identity['site_name'] : (defined('APP_NAME') ? APP_NAME : 'Movie Hub');
$footer_settings = SLEA_Datastore::get_footer_settings();

$has_users = SLEA_Auth::has_users();
$error = '';

// If admin already exists, PERMANENTLY LOCK this page!
if ($has_users) {
    http_response_code(403);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Setup Locked - <?= htmlspecialchars($site_name) ?></title>
        <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
        <script src="https://cdn.tailwindcss.com"></script>
        <style>
            body { font-family: 'Plus Jakarta Sans', sans-serif; }
            .font-mono { font-family: 'JetBrains Mono', monospace; }
        </style>
    </head>
    <body class="min-h-screen bg-[#f0f4f9] text-[#1f1f1f] flex flex-col font-sans selection:bg-[#d3e3fd] selection:text-[#041e49]">
        <div class="flex-1 flex flex-col">
            <header class="bg-white border-b border-[#e1e7f0] sticky top-0 z-30">
                <div class="max-w-5xl mx-auto px-4 sm:px-6 h-16 flex items-center justify-between gap-3">
                    <div class="flex items-center gap-3 min-w-0">
                        <span class="text-sm sm:text-base font-extrabold text-[#1f1f1f] tracking-tight truncate">
                            <?= htmlspecialchars($site_name) ?>
                        </span>
                    </div>
                    <div class="flex items-center gap-2 text-xs text-[#444746]">
                        <span class="inline-block w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                        <span class="hidden sm:inline font-medium">System Online</span>
                    </div>
                </div>
            </header>

            <main class="max-w-4xl w-full mx-auto px-4 sm:px-6 py-6 sm:py-8 flex-1 flex items-center justify-center">
                <div class="w-full max-w-md mx-auto py-8 px-4">
                    <div class="bg-white rounded-2xl p-7 border border-[#d3e3fd] shadow-md space-y-6 text-center">
                        <div class="w-12 h-12 rounded-2xl bg-[#fce8e6] text-[#c5221f] flex items-center justify-center mx-auto shadow-2xs border border-[#fad2cf]">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                        </div>
                        <div class="space-y-2">
                            <h2 class="text-lg font-bold text-[#1f1f1f]">Setup Permanently Closed</h2>
                            <p class="text-xs text-[#5f6368] leading-relaxed">
                                The administrator account has already been provisioned in the MySQL database. Account creation is permanently locked for security.
                            </p>
                        </div>
                        <div class="pt-1">
                            <a href="login.php" class="w-full py-3 px-4 rounded-xl bg-[#0b57d0] hover:bg-[#0842a0] text-white font-bold text-xs flex items-center justify-center gap-2 cursor-pointer shadow-xs transition-all">
                                <span>Proceed to Admin Login</span>
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                            </a>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Handle Form Submission for first admin
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    if (empty($username) || empty($email) || empty($password)) {
        $error = 'All fields are required.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } else {
        $result = SLEA_Auth::create_first_admin($username, $email, $password);
        if ($result['success']) {
            header('Location: admin.php?installed=1');
            exit;
        } else {
            $error = $result['error'] ?? 'Failed to initialize administrator account.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>One-Time Administrator Setup - <?= htmlspecialchars($site_name) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        .font-mono { font-family: 'JetBrains Mono', monospace; }
    </style>
</head>
<body class="min-h-screen bg-[#f0f4f9] text-[#1f1f1f] flex flex-col font-sans selection:bg-[#d3e3fd] selection:text-[#041e49]">

    <div class="flex-1 flex flex-col">
        <!-- Top Header (Matches React AppHeader.tsx) -->
        <header class="bg-white border-b border-[#e1e7f0] sticky top-0 z-30">
            <div class="max-w-5xl mx-auto px-4 sm:px-6 h-16 flex items-center justify-between gap-3">
                <div class="flex items-center gap-3 min-w-0">
                    <span class="text-sm sm:text-base font-extrabold text-[#1f1f1f] tracking-tight truncate">
                        <?= htmlspecialchars($site_name) ?>
                    </span>
                </div>
                <div class="flex items-center gap-2 text-xs text-[#444746]">
                    <span class="inline-block w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                    <span class="hidden sm:inline font-medium">System Online</span>
                </div>
            </div>
        </header>

        <!-- Main Workspace -->
        <main class="max-w-4xl w-full mx-auto px-4 sm:px-6 py-6 sm:py-8 flex-1">
            <div class="w-full max-w-md mx-auto py-8 px-4">
                <div class="bg-white rounded-2xl p-7 border border-[#d3e3fd] shadow-md space-y-6">
                    <!-- Header -->
                    <div class="text-center space-y-2">
                        <div class="w-12 h-12 rounded-2xl bg-[#c2e7ff] text-[#001d35] flex items-center justify-center mx-auto shadow-2xs">
                            <svg class="w-6 h-6 text-[#0b57d0]" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/><path d="m9 12 2 2 4-4"/></svg>
                        </div>
                        <h2 class="text-lg font-bold text-[#1f1f1f]">One-Time Administrator Setup</h2>
                        <p class="text-xs text-[#5f6368] max-w-xs mx-auto">
                            No administrator account exists in the database. Create the primary superadmin account to unlock private tools.
                        </p>
                    </div>

                    <div class="bg-[#fef7e0] border border-[#feebc8] text-[#b06000] text-xs p-3 rounded-xl flex items-start gap-2">
                        <svg class="w-4 h-4 shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg>
                        <span>This registration page will be <strong>permanently locked</strong> immediately after you submit this form.</span>
                    </div>

                    <?php if (!empty($error)): ?>
                        <div class="bg-[#fce8e6] text-[#c5221f] text-xs p-3 rounded-xl flex items-center gap-2 border border-[#fad2cf]">
                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/><path d="M12 8v4"/><path d="M12 16h.01"/></svg>
                            <span><?= htmlspecialchars($error) ?></span>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="setup.php" class="space-y-4">
                        <div class="space-y-1">
                            <label class="text-xs font-semibold text-[#444746] block">Admin Username</label>
                            <input type="text" name="username" required value="<?= htmlspecialchars($_POST['username'] ?? 'admin') ?>"
                                placeholder="e.g. admin"
                                class="w-full px-3.5 py-2.5 rounded-xl border border-[#c4c7c5] focus:border-[#0b57d0] focus:ring-2 focus:ring-[#0b57d0]/15 outline-none text-sm font-medium transition-all" />
                        </div>

                        <div class="space-y-1">
                            <label class="text-xs font-semibold text-[#444746] block">Admin Email</label>
                            <input type="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" placeholder="admin@example.com"
                                class="w-full px-3.5 py-2.5 rounded-xl border border-[#c4c7c5] focus:border-[#0b57d0] focus:ring-2 focus:ring-[#0b57d0]/15 outline-none text-sm font-medium transition-all" />
                        </div>

                        <div class="space-y-1">
                            <label class="text-xs font-semibold text-[#444746] block">Password (min 6 characters)</label>
                            <input type="password" name="password" required
                                placeholder="••••••••"
                                class="w-full px-3.5 py-2.5 rounded-xl border border-[#c4c7c5] focus:border-[#0b57d0] focus:ring-2 focus:ring-[#0b57d0]/15 outline-none text-sm font-medium transition-all font-mono" />
                        </div>

                        <div class="space-y-1">
                            <label class="text-xs font-semibold text-[#444746] block">Confirm Password</label>
                            <input type="password" name="confirm_password" required
                                placeholder="••••••••"
                                class="w-full px-3.5 py-2.5 rounded-xl border border-[#c4c7c5] focus:border-[#0b57d0] focus:ring-2 focus:ring-[#0b57d0]/15 outline-none text-sm font-medium transition-all font-mono" />
                        </div>

                        <button type="submit" class="w-full py-3 px-4 rounded-xl bg-[#0b57d0] hover:bg-[#0842a0] text-white font-bold text-xs flex items-center justify-center gap-2 cursor-pointer shadow-xs transition-all">
                            <span>Create Admin Account &amp; Lock Setup</span>
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                        </button>
                    </form>

                    <div class="bg-[#f8fafd] border border-[#e1e7f0] rounded-xl p-3 text-[11px] text-[#444746] flex items-center justify-between">
                        <div class="flex items-center gap-1.5 font-medium">
                            <svg class="w-3.5 h-3.5 text-[#0b57d0]" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><rect width="20" height="8" x="2" y="2" rx="2" ry="2"/><rect width="20" height="8" x="2" y="14" rx="2" ry="2"/><line x1="6" x2="6.01" y1="6" y2="6"/><line x1="6" x2="6.01" y1="18" y2="18"/></svg>
                            <span>Security Engine:</span>
                        </div>
                        <span class="font-mono bg-[#e8f0fe] text-[#0b57d0] px-2 py-0.5 rounded font-bold">
                            BCRYPT Protected
                        </span>
                    </div>
                </div>
            </div>
        </main>
    </div>

</body>
</html>
