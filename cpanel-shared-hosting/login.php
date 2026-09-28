<?php
/**
 * Private Admin Login Page
 * Matches React App.tsx (logged-out) + AppHeader.tsx + AdminLoginCard.tsx design
 * Notice: This page is completely isolated. No links to this page exist anywhere on public pages.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/class-db.php';
require_once __DIR__ . '/includes/class-auth.php';
require_once __DIR__ . '/includes/class-datastore.php';

// If no users exist, redirect to one-time setup page
if (!SLEA_Auth::has_users()) {
    header('Location: setup.php');
    exit;
}

// If already logged in, redirect to admin panel
if (SLEA_Auth::is_logged_in()) {
    header('Location: admin.php');
    exit;
}

// Enforce Custom Login Page Path configured in Admin Settings
SLEA_Auth::start_session();
$configured_login_slug = SLEA_Datastore::get_login_slug();
$req_uri_path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
$req_basename = trim(basename($req_uri_path), '/');
$req_slug_clean = strtolower(preg_replace('/\.php$/i', '', $req_basename));
$query_login_slug = isset($_GET['slug']) ? SLEA_Datastore::sanitize_login_slug($_GET['slug']) : '';

$is_valid_login_path = defined('SLEA_LOGIN_ROUTED')
    || ($req_slug_clean === $configured_login_slug)
    || ($query_login_slug !== '' && $query_login_slug === $configured_login_slug)
    || ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_SESSION['slea_login_form_token']));

if (!$is_valid_login_path) {
    SLEA_Auth::render_404();
}

$_SESSION['slea_login_form_token'] = true;

$site_identity = SLEA_Datastore::get_site_identity();
$site_name = !empty($site_identity['site_name']) ? $site_identity['site_name'] : (defined('APP_NAME') ? APP_NAME : 'Movie Hub HQ Drive');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($username) || empty($password)) {
        $error = 'Please enter your username and password.';
    } else {
        if (SLEA_Auth::login($username, $password)) {
            header('Location: admin.php');
            exit;
        } else {
            $error = 'Invalid administrator credentials.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Access Required - <?= htmlspecialchars($site_name) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        .font-mono { font-family: 'JetBrains Mono', monospace; }
        .shadow-2xs { box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05); }
        .shadow-xs { box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05); }
    </style>
</head>
<body class="w-full min-h-screen bg-[#f0f4f9] text-[#1f1f1f] flex flex-col font-sans antialiased overflow-x-hidden selection:bg-[#d3e3fd] selection:text-[#041e49]">

    <div class="min-h-screen flex flex-col flex-1">
        <!-- Navigation Bar (Matches React AppHeader.tsx when logged out) -->
        <header id="app-header" class="w-full bg-[#fdfcff] text-[#1f1f1f] border-b border-[#e1e7f0] shadow-2xs select-none sticky top-0 z-30 transition-colors">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 py-3 sm:py-3.5 flex items-center justify-between gap-3 min-w-0">
                <div class="flex items-center gap-2.5 min-w-0 flex-1">
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2 flex-wrap sm:flex-nowrap">
                            <h1 class="font-bold text-sm sm:text-base leading-snug text-[#1f1f1f] tracking-tight truncate">
                                <?= htmlspecialchars($site_name) ?>
                            </h1>
                        </div>
                        <p class="text-[11px] text-[#5f6368] truncate hidden sm:block">
                            Shortlink Bypass • Episode Button Pages
                        </p>
                    </div>
                </div>

                <!-- Right Status Pill -->
                <div class="flex items-center gap-3 shrink-0">
                    <div class="flex items-center gap-2 text-xs text-[#444746]">
                        <span class="inline-block w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                        <span class="hidden sm:inline font-medium">System Online</span>
                    </div>
                </div>
            </div>
        </header>

        <!-- Main Workspace (Matches React AdminLoginCard.tsx) -->
        <main class="flex-1 w-full max-w-5xl mx-auto px-3.5 sm:px-6 py-5 sm:py-7">
            <div class="w-full max-w-md mx-auto py-8 px-4">
                <div class="bg-white rounded-2xl p-7 border border-[#d3e3fd] shadow-md space-y-6">
                    <!-- Header -->
                    <div class="text-center space-y-2">
                        <div class="w-12 h-12 rounded-2xl bg-[#c2e7ff] text-[#001d35] flex items-center justify-center mx-auto shadow-2xs">
                            <svg class="w-6 h-6 text-[#0b57d0]" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                        </div>
                        <h2 class="text-lg font-bold text-[#1f1f1f]">Admin Access Required</h2>
                        <p class="text-xs text-[#5f6368] max-w-xs mx-auto">
                            The link extractor, resolver, and page generator are private. Sign in as administrator to proceed.
                        </p>
                    </div>

                    <!-- Error Alert -->
                    <?php if (!empty($error)): ?>
                        <div class="bg-[#fce8e6] text-[#c5221f] text-xs p-3 rounded-xl flex items-center gap-2 border border-[#fad2cf]">
                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/><path d="M12 8v4"/><path d="M12 16h.01"/></svg>
                            <span><?= htmlspecialchars($error) ?></span>
                        </div>
                    <?php endif; ?>

                    <!-- Form -->
                    <form method="POST" action="" class="space-y-4">
                        <div class="space-y-1">
                            <label class="text-xs font-semibold text-[#444746] block">
                                Username
                            </label>
                            <input
                                type="text"
                                name="username"
                                value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                                required
                                autocomplete="username"
                                placeholder="admin"
                                class="w-full px-3.5 py-2.5 rounded-xl border border-[#c4c7c5] focus:border-[#0b57d0] focus:ring-2 focus:ring-[#0b57d0]/15 outline-none text-sm font-medium transition-all"
                            />
                        </div>

                        <div class="space-y-1">
                            <label class="text-xs font-semibold text-[#444746] block">
                                Password
                            </label>
                            <input
                                type="password"
                                name="password"
                                required
                                autocomplete="current-password"
                                placeholder="••••••••"
                                class="w-full px-3.5 py-2.5 rounded-xl border border-[#c4c7c5] focus:border-[#0b57d0] focus:ring-2 focus:ring-[#0b57d0]/15 outline-none text-sm font-medium transition-all font-mono"
                            />
                        </div>

                        <button
                            type="submit"
                            class="w-full py-3 px-4 rounded-xl bg-[#0b57d0] hover:bg-[#0842a0] text-white font-bold text-xs flex items-center justify-center gap-2 cursor-pointer shadow-xs transition-all"
                        >
                            <span>Sign In to Admin Dashboard</span>
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                        </button>
                    </form>

                    <!-- cPanel Notice -->
                    <div class="bg-[#f8fafd] border border-[#e1e7f0] rounded-xl p-3 text-[11px] text-[#444746] flex items-center justify-between">
                        <div class="flex items-center gap-1.5 font-medium">
                            <svg class="w-3.5 h-3.5 text-[#0b57d0]" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><rect width="20" height="8" x="2" y="2" rx="2" ry="2"/><rect width="20" height="8" x="2" y="14" rx="2" ry="2"/><line x1="6" x2="6.01" y1="6" y2="6"/><line x1="6" x2="6.01" y1="18" y2="18"/></svg>
                            <span>Authentication Mode:</span>
                        </div>
                        <span class="font-mono bg-[#e8f0fe] text-[#0b57d0] px-2 py-0.5 rounded font-bold">
                            MySQL Protected
                        </span>
                    </div>
                </div>
            </div>
        </main>
    </div>

</body>
</html>
