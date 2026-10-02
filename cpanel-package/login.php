<?php
/**
 * Admin Login Page (/login.php or custom slug)
 * Matches React AppHeader + AdminLoginCard UI
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/class-db.php';
require_once __DIR__ . '/includes/class-auth.php';
require_once __DIR__ . '/includes/class-datastore.php';

SLEA_Datastore::register_public_error_handler();
SLEA_Auth::init_session();

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header('X-Robots-Tag: noindex, nofollow, noarchive');

// If no users exist yet, redirect to initial setup wizard
if (!SLEA_Auth::has_users()) {
    header('Location: setup.php');
    exit;
}

// If already logged in as Admin, login page cannot be viewed — redirect immediately to admin.php
if (SLEA_Auth::is_logged_in()) {
    header('Location: admin.php');
    exit;
}

// Enforce custom login slug if configured (hide default /login.php with stealth 404)
$configured_login_slug = SLEA_Datastore::get_login_slug();
if ($configured_login_slug !== 'login') {
    $req_path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
    $req_base = trim(basename($req_path), '/');
    $req_clean = strtolower(preg_replace('/\.php$/i', '', $req_base));
    $routed_via_custom_slug = (defined('SLEA_LOGIN_ROUTED') && SLEA_LOGIN_ROUTED === true)
        || ($req_clean === strtolower($configured_login_slug));

    $valid_custom_post = ($_SERVER['REQUEST_METHOD'] === 'POST')
        && !empty($_SESSION['slea_login_form_token'])
        && !empty($_POST['_login_token'])
        && hash_equals((string)$_SESSION['slea_login_form_token'], (string)$_POST['_login_token']);

    if (!$routed_via_custom_slug && !$valid_custom_post) {
        SLEA_Auth::render_404();
    }
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $posted_token = (string)($_POST['_login_token'] ?? '');
    $session_token = (string)($_SESSION['slea_login_form_token'] ?? '');

    if ($session_token === '' || $posted_token === '' || !hash_equals($session_token, $posted_token)) {
        $error = 'Invalid or expired login session token. Please try again.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        try {
            if (SLEA_Auth::login($username, $password)) {
                unset($_SESSION['slea_login_form_token']);
                header('Location: admin.php');
                exit;
            } else {
                $error = 'Invalid username or password. Please try again.';
            }
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}

// Generate fresh CSRF token for login form
if (empty($_SESSION['slea_login_form_token'])) {
    $_SESSION['slea_login_form_token'] = bin2hex(random_bytes(32));
}
$login_form_token = $_SESSION['slea_login_form_token'];
$form_action = ($configured_login_slug !== 'login') ? $configured_login_slug : 'login.php';

$site_identity = SLEA_Datastore::get_site_identity();
$site_name = !empty($site_identity['site_name']) ? $site_identity['site_name'] : (defined('APP_NAME') ? APP_NAME : 'Movie Hub HQ Drive');
$site_logo_url = !empty($site_identity['site_logo_url']) ? $site_identity['site_logo_url'] : '';
$words = preg_split('/\s+/', trim($site_name));
$initials = '';
foreach ($words as $w) {
    if ($w !== '') $initials .= mb_substr($w, 0, 1);
}
$initials = strtoupper(mb_substr($initials, 0, 3)) ?: 'MHQ';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Admin Login - <?= htmlspecialchars($site_name) ?></title>
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
    <div class="min-h-screen flex flex-col flex-1">
        <!-- Navigation Bar (Matches React AppHeader.tsx) -->
        <header id="app-header" class="sticky top-0 z-30 w-full bg-[#f0f4f9]/90 backdrop-blur-md border-b border-[#e1e7f0] transition-colors">
            <div class="max-w-5xl mx-auto px-4 sm:px-6 h-16 flex items-center justify-between gap-3">
                <!-- Brand Identity -->
                <div class="flex items-center gap-3 min-w-0">
                    <div class="w-9 h-9 rounded-xl bg-[#c2e7ff] text-[#001d35] flex items-center justify-center font-bold text-base shadow-2xs shrink-0 overflow-hidden">
                        <?php if (!empty($site_logo_url)): ?>
                            <img src="<?= htmlspecialchars($site_logo_url) ?>" alt="<?= htmlspecialchars($site_name) ?>" class="w-full h-full object-contain" onerror="this.style.display='none';this.parentElement.innerText='<?= htmlspecialchars($initials) ?>';" />
                        <?php else: ?>
                            <span class="text-xs font-black tracking-tighter"><?= htmlspecialchars($initials) ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="truncate">
                        <div class="flex items-center gap-2">
                            <h1 class="font-bold text-sm sm:text-base tracking-tight text-[#1f1f1f] truncate">
                                <?= htmlspecialchars($site_name) ?>
                            </h1>
                            <span class="hidden sm:inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold bg-[#e8f0fe] text-[#0b57d0]">
                                Admin Login
                            </span>
                        </div>
                        <p class="text-xs text-[#5f6368] truncate hidden sm:block">
                            Protected Link Resolution &amp; Button Page Generator
                        </p>
                    </div>
                </div>

                <!-- Right Action Controls -->
                <div class="flex items-center gap-2 shrink-0">
                    <span class="flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-medium bg-[#0b57d0] text-white shadow-2xs">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                            <rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                        </svg>
                        <span>Admin Login</span>
                    </span>
                </div>
            </div>
        </header>

        <!-- Main Login Workspace (Matches React AdminLoginCard.tsx) -->
        <main class="flex-1 w-full max-w-5xl mx-auto px-3.5 sm:px-6 py-5 sm:py-7">
            <div class="max-w-md mx-auto my-12">
                <div class="bg-white rounded-3xl p-6 sm:p-8 border border-[#e0e4eb] shadow-sm space-y-6">
                    <!-- Icon + Header -->
                    <div class="text-center space-y-2">
                        <div class="w-12 h-12 rounded-2xl bg-[#e8f0fe] text-[#0b57d0] flex items-center justify-center mx-auto shadow-2xs">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                <rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                            </svg>
                        </div>
                        <h2 class="text-xl font-bold text-[#1f1f1f]">Admin Dashboard Login</h2>
                        <p class="text-xs text-[#5f6368] leading-relaxed">
                            Only authenticated administrators can access the Link Resolver, HTML Extractor, and Page Generator.
                        </p>
                    </div>

                    <?php if ($error): ?>
                    <div class="p-3 rounded-xl bg-[#fce8e6] text-[#c5221f] text-xs font-medium flex items-center gap-2 border border-[#fad2cf]">
                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                            <circle cx="12" cy="12" r="10"/><line x1="12" x2="12" y1="8" y2="12"/><line x1="12" x2="12.01" y1="16" y2="16"/>
                        </svg>
                        <span><?= htmlspecialchars($error) ?></span>
                    </div>
                    <?php endif; ?>

                    <form method="POST" action="<?= htmlspecialchars($form_action, ENT_QUOTES, 'UTF-8') ?>" class="space-y-4">
                        <input type="hidden" name="_login_token" value="<?= htmlspecialchars($login_form_token, ENT_QUOTES, 'UTF-8') ?>">
                        <div class="space-y-1.5">
                            <label class="text-xs font-semibold text-[#444746] block">
                                Admin Username
                            </label>
                            <div class="relative">
                                <input
                                    type="text"
                                    name="username"
                                    required
                                    autofocus
                                    value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                                    placeholder="Enter admin username"
                                    class="w-full pl-9 pr-4 py-2.5 rounded-xl border border-[#c4c7c5] focus:border-[#0b57d0] focus:ring-2 focus:ring-[#0b57d0]/15 outline-none text-sm text-[#1f1f1f]"
                                />
                                <svg class="w-4 h-4 text-[#747775] absolute left-3 top-3" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                    <path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
                                </svg>
                            </div>
                        </div>

                        <div class="space-y-1.5">
                            <label class="text-xs font-semibold text-[#444746] block">
                                Admin Password
                            </label>
                            <div class="relative">
                                <input
                                    type="password"
                                    name="password"
                                    required
                                    placeholder="Enter admin password"
                                    class="w-full pl-9 pr-4 py-2.5 rounded-xl border border-[#c4c7c5] focus:border-[#0b57d0] focus:ring-2 focus:ring-[#0b57d0]/15 outline-none text-sm text-[#1f1f1f]"
                                />
                                <svg class="w-4 h-4 text-[#747775] absolute left-3 top-3" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                    <path d="m15.5 7.5 2.3 2.3a1 1 0 0 0 1.4 0l2.1-2.1a1 1 0 0 0 0-1.4L19 4"/><path d="m21 2-9.6 9.6"/><circle cx="7.5" cy="15.5" r="5.5"/>
                                </svg>
                            </div>
                        </div>

                        <button
                            type="submit"
                            class="w-full py-3 rounded-xl bg-[#0b57d0] hover:bg-[#0842a0] text-white font-semibold text-sm flex items-center justify-center gap-2 cursor-pointer transition-colors shadow-xs"
                        >
                            <span>Sign In to Admin Panel</span>
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                <path d="M5 12h14"/><path d="m12 5 7 7-7 7"/>
                            </svg>
                        </button>
                    </form>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
