<?php
/**
 * Private One-Time Account Setup Page
 * Only accessible ONCE when the database has zero admin accounts.
 * Permanently locked immediately after first administrator creation.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/class-db.php';
require_once __DIR__ . '/includes/class-auth.php';

$has_users = SLEA_Auth::has_users();
$error = '';
$success = false;

// If admin already exists, PERMANENTLY LOCK this page!
if ($has_users) {
    http_response_code(403);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Setup Locked</title>
        <script src="https://cdn.tailwindcss.com"></script>
    </head>
    <body class="bg-[#0f172a] text-slate-200 min-h-screen flex items-center justify-center p-4 font-sans">
        <div class="max-w-md w-full bg-slate-900 border border-slate-800 rounded-2xl p-7 text-center space-y-4 shadow-2xl">
            <div class="w-14 h-14 bg-red-500/10 border border-red-500/20 text-red-400 rounded-2xl flex items-center justify-center mx-auto text-2xl font-bold">
                🔒
            </div>
            <h1 class="text-xl font-bold text-white tracking-tight">Setup Permanently Closed</h1>
            <p class="text-xs text-slate-400 leading-relaxed">
                The administrator account has already been provisioned in the MySQL database. Account creation is permanently locked for security.
            </p>
            <div class="pt-2">
                <a href="login.php" class="inline-block px-5 py-2.5 bg-blue-600 hover:bg-blue-500 text-white text-xs font-semibold rounded-xl transition-all shadow-md">
                    Proceed to Private Login
                </a>
            </div>
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
    $password = trim($_POST['password'] ?? '');
    $confirm  = trim($_POST['confirm_password'] ?? '');

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
    <title>Private Administrator Initialization</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-[#0f172a] text-slate-200 min-h-screen flex items-center justify-center p-4 font-sans antialiased">
    <div class="max-w-md w-full bg-slate-900 border border-slate-800 rounded-3xl p-7 sm:p-8 space-y-6 shadow-2xl">
        <div class="text-center space-y-2">
            <div class="w-14 h-14 bg-blue-500/10 border border-blue-500/30 text-blue-400 rounded-2xl flex items-center justify-center mx-auto text-2xl font-bold shadow-inner">
                ⚡
            </div>
            <h1 class="text-xl font-black text-white tracking-tight">One-Time Administrator Setup</h1>
            <p class="text-xs text-slate-400 max-w-xs mx-auto">
                No administrator account exists in the database. Create the primary superadmin account to unlock private tools.
            </p>
        </div>

        <div class="bg-amber-500/10 border border-amber-500/20 text-amber-300 text-[11px] p-3 rounded-xl flex items-start gap-2">
            <span class="text-base shrink-0">⚠️</span>
            <span>This registration page will be <strong>permanently locked</strong> immediately after you submit this form.</span>
        </div>

        <?php if (!empty($error)): ?>
            <div class="bg-red-500/10 border border-red-500/30 text-red-300 text-xs p-3.5 rounded-xl font-medium">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="setup.php" class="space-y-4">
            <div class="space-y-1">
                <label class="text-xs font-semibold text-slate-300 block">Admin Username</label>
                <input type="text" name="username" required value="<?= htmlspecialchars($_POST['username'] ?? 'admin') ?>"
                    class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950 border border-slate-800 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 text-sm text-white outline-none transition-all" />
            </div>

            <div class="space-y-1">
                <label class="text-xs font-semibold text-slate-300 block">Admin Email</label>
                <input type="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" placeholder="admin@example.com"
                    class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950 border border-slate-800 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 text-sm text-white outline-none transition-all" />
            </div>

            <div class="space-y-1">
                <label class="text-xs font-semibold text-slate-300 block">Password (min 6 characters)</label>
                <input type="password" name="password" required
                    class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950 border border-slate-800 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 text-sm text-white outline-none transition-all font-mono" />
            </div>

            <div class="space-y-1">
                <label class="text-xs font-semibold text-slate-300 block">Confirm Password</label>
                <input type="password" name="confirm_password" required
                    class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950 border border-slate-800 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 text-sm text-white outline-none transition-all font-mono" />
            </div>

            <button type="submit" class="w-full py-3 px-4 rounded-xl bg-blue-600 hover:bg-blue-500 text-white font-bold text-xs tracking-wider uppercase flex items-center justify-center gap-2 cursor-pointer shadow-lg shadow-blue-600/30 transition-all">
                Create Admin Account & Lock Setup
            </button>
        </form>

        <div class="text-center text-[10px] text-slate-500 font-mono">
            MySQL Database Engine • Protected with BCRYPT Hashing
        </div>
    </div>
</body>
</html>
