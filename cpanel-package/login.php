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
<body class="bg-[#0f172a] text-slate-200 min-h-screen flex items-center justify-center p-4 font-sans antialiased">
    <div class="max-w-md w-full bg-slate-900 border border-slate-800 rounded-3xl p-7 sm:p-8 space-y-6 shadow-2xl">
        <div class="text-center space-y-2">
            <div class="w-13 h-13 bg-indigo-500/10 border border-indigo-500/30 text-indigo-400 rounded-2xl flex items-center justify-center mx-auto text-xl font-bold shadow-inner">
                🔐
            </div>
            <h1 class="text-xl font-bold text-white tracking-tight">Private Administrator Sign In</h1>
            <p class="text-xs text-slate-400 max-w-xs mx-auto">
                Restricted portal for link extraction, URL resolution, and episode page publishing.
            </p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="bg-red-500/10 border border-red-500/30 text-red-300 text-xs p-3.5 rounded-xl font-medium flex items-center gap-2">
                <span>⚠️</span>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" action="login.php" class="space-y-4">
            <div class="space-y-1">
                <label class="text-xs font-semibold text-slate-300 block">Username or Email</label>
                <input type="text" name="username" required value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                    autocomplete="username"
                    class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950 border border-slate-800 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 text-sm text-white outline-none transition-all" />
            </div>

            <div class="space-y-1">
                <label class="text-xs font-semibold text-slate-300 block">Password</label>
                <input type="password" name="password" required
                    autocomplete="current-password"
                    class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950 border border-slate-800 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 text-sm text-white outline-none transition-all font-mono" />
            </div>

            <button type="submit" class="w-full py-3 px-4 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs tracking-wider uppercase flex items-center justify-center gap-2 cursor-pointer shadow-lg shadow-indigo-600/30 transition-all">
                Sign In to Dashboard
            </button>
        </form>

        <div class="text-center text-[10px] text-slate-500">
            Private Access Only • MySQL Authenticated
        </div>
    </div>
</body>
</html>
