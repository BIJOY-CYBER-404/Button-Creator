<?php
/**
 * Private Admin Login Page
 * Notice: This page is completely isolated. No links to this page exist anywhere on public pages.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/class-db.php';
require_once __DIR__ . '/includes/class-auth.php';

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
            $error = 'Invalid credentials. Access denied.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Private Portal Login</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-[#f0f4f9] text-[#1f1f1f] min-h-screen flex items-center justify-center p-4 font-sans antialiased selection:bg-[#d3e3fd] selection:text-[#041e49]">
    <div class="max-w-md w-full bg-white border border-[#e0e4eb] rounded-3xl p-7 sm:p-8 space-y-6 shadow-xs">
        <div class="text-center space-y-2">
            <div class="w-14 h-14 bg-[#e8f0fe] border border-[#d3e3fd] text-[#0b57d0] rounded-2xl flex items-center justify-center mx-auto text-2xl font-bold shadow-2xs">
                🔐
            </div>
            <h1 class="text-xl font-bold text-[#1f1f1f] tracking-tight">Private Administrator Sign In</h1>
            <p class="text-xs text-[#5f6368] max-w-xs mx-auto">
                Restricted portal for link extraction, URL resolution, and episode page publishing.
            </p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="bg-[#fce8e6] border border-[#f7d0cd] text-[#c5221f] text-xs p-3.5 rounded-2xl font-medium flex items-center gap-2">
                <span>⚠️</span>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" action="login.php" class="space-y-4">
            <div class="space-y-1.5">
                <label class="text-xs font-bold text-[#444746] block">Username or Email</label>
                <input type="text" name="username" required value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                    autocomplete="username"
                    placeholder="e.g. admin"
                    class="w-full px-4 py-3 rounded-2xl bg-white border border-[#dadce0] focus:border-[#0b57d0] focus:ring-2 focus:ring-[#0b57d0]/15 text-sm text-[#1f1f1f] outline-none transition-all" />
            </div>

            <div class="space-y-1.5">
                <label class="text-xs font-bold text-[#444746] block">Password</label>
                <input type="password" name="password" required
                    autocomplete="current-password"
                    placeholder="••••••••"
                    class="w-full px-4 py-3 rounded-2xl bg-white border border-[#dadce0] focus:border-[#0b57d0] focus:ring-2 focus:ring-[#0b57d0]/15 text-sm text-[#1f1f1f] outline-none transition-all font-mono" />
            </div>

            <button type="submit" class="w-full py-3.5 px-4 rounded-2xl bg-[#0b57d0] hover:bg-[#0842a0] text-white font-bold text-xs tracking-wider uppercase flex items-center justify-center gap-2 cursor-pointer shadow-sm hover:shadow-md transition-all">
                Sign In to Dashboard
            </button>
        </form>

        <div class="text-center text-[11px] text-[#747775]">
            Private Access Only • MySQL Authenticated
        </div>
    </div>
</body>
</html>
