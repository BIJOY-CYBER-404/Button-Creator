<?php
/**
 * Sign In Page (/login.php or custom slug)
 * Google Material M3 Light Theme Design matching all public pages
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

// Sanitize optional redirect target (e.g., private page /p/{slug} or admin page)
$raw_redirect = trim((string)($_POST['redirect'] ?? $_GET['redirect'] ?? ''));
$safe_redirect_target = 'admin.php';
if ($raw_redirect !== '' && $raw_redirect[0] === '/' && strpos($raw_redirect, '//') !== 0) {
    $redir_path = strtolower((string)parse_url($raw_redirect, PHP_URL_PATH));
    if (strpos($redir_path, 'login') === false && strpos($redir_path, 'logout') === false) {
        $safe_redirect_target = $raw_redirect;
    }
}

// If already logged in with a valid 2-hour cookie, redirect immediately to target or admin.php
if (SLEA_Auth::is_logged_in()) {
    header('Location: ' . $safe_redirect_target);
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
        && !empty($_POST['_login_token'])
        && SLEA_Auth::verify_login_form_token((string)$_POST['_login_token']);

    if (!$routed_via_custom_slug && !$valid_custom_post) {
        SLEA_Auth::render_404();
    }
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $posted_token = (string)($_POST['_login_token'] ?? '');

    if (!SLEA_Auth::verify_login_form_token($posted_token)) {
        $error = 'Invalid or expired session token. Please try again.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        try {
            if (SLEA_Auth::login($username, $password)) {
                unset($_SESSION['slea_login_form_token']);
                $new_cookie_token = (string)($_SESSION['slea_auth_token'] ?? '');
                $new_exp_sec = (int)($_SESSION['slea_cookie_expires_at'] ?? (time() + SLEA_Auth::AUTH_COOKIE_LIFETIME));
                $new_exp_ms = $new_exp_sec * 1000;
                $max_age = max(1, $new_exp_sec - time());
                $target_js = json_encode($safe_redirect_target);
                $target_attr = htmlspecialchars($safe_redirect_target, ENT_QUOTES, 'UTF-8');
                $tok_js = json_encode($new_cookie_token);
                $exp_js = json_encode($new_exp_ms);
                echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Signing In...</title><script>'
                    . '(function(){'
                    . 'var tok=' . $tok_js . ';'
                    . 'var expMs=' . $exp_js . ';'
                    . 'var maxAge=' . (int)$max_age . ';'
                    . 'var expUtc=new Date(expMs).toUTCString();'
                    . 'var sec=window.location.protocol==="https:"?"; Secure":"";'
                    . 'document.cookie="slea_auth_cookie="+encodeURIComponent(tok)+"; Max-Age="+maxAge+"; Expires="+expUtc+"; Path=/; SameSite=Lax"+sec;'
                    . 'document.cookie="slea_admin_token="+encodeURIComponent(tok)+"; Max-Age="+maxAge+"; Expires="+expUtc+"; Path=/; SameSite=Lax"+sec;'
                    . 'document.cookie="slea_login_state=1; Max-Age="+maxAge+"; Expires="+expUtc+"; Path=/; SameSite=Lax"+sec;'
                    . 'document.cookie="slea_admin_exp="+expMs+"; Max-Age="+maxAge+"; Expires="+expUtc+"; Path=/; SameSite=Lax"+sec;'
                    . 'try{localStorage.setItem("slea_browser_cookie_state",JSON.stringify({token:tok,expMs:expMs}));}catch(e){}'
                    . 'window.location.replace(' . $target_js . ');'
                    . '})();'
                    . '</script><meta http-equiv="refresh" content="0;url=' . $target_attr . '"></head><body style="background:#f8fafd"></body></html>';
                exit;
            } else {
                $error = 'Invalid username or password. Please try again.';
            }
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}

// Generate fresh HMAC-backed token for login form
$login_form_token = SLEA_Auth::create_login_form_token();
$form_action = ($configured_login_slug !== 'login') ? $configured_login_slug : 'login.php';

$site_identity = SLEA_Datastore::get_site_identity();
$site_name = !empty($site_identity['site_name']) ? $site_identity['site_name'] : (defined('APP_NAME') ? APP_NAME : 'Movie Hub HQ Drive');
$site_logo_url = !empty($site_identity['site_logo_url']) ? trim((string)$site_identity['site_logo_url']) : '';
if ($site_logo_url !== '' && preg_match('/^\s*(?:javascript|data|vbscript):/i', $site_logo_url)) {
    $site_logo_url = '';
}

$menu_items = array_values(array_filter(SLEA_Datastore::get_menu_items(), function($item) use ($configured_login_slug) {
    $t = strtolower(trim($item['title'] ?? ''));
    if (preg_match('/admin|login|dashboard|cpanel|setup/i', $t)) return false;
    $u = strtolower(trim($item['url'] ?? ''));
    if (preg_match('/^\s*(?:javascript|data|vbscript):/i', $u)) return false;
    if ($u === '' || $u === '#') return true;
    $path = trim(parse_url($u, PHP_URL_PATH) ?: '', '/');
    $base = preg_replace('/\.php$/i', '', basename($path));
    $blocked = ['admin', 'pages', 'settings', 'analytics', 'update', 'updater', 'login', 'logout', 'setup', 'api', strtolower($configured_login_slug)];
    return !in_array($base, $blocked, true);
}));

$footer_copyright = SLEA_Datastore::get_footer_copyright();

$script_name_clean = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? ($_SERVER['PHP_SELF'] ?? '/login.php'));
$base_dir = rtrim(dirname($script_name_clean), '/');
$base_dir = preg_replace('#/(p|page)$#i', '', $base_dir);
if ($base_dir === '/' || $base_dir === '.' || $base_dir === '') {
    $base_dir = '';
} elseif ($base_dir[0] !== '/') {
    $base_dir = '/' . $base_dir;
}
$app_base_path = $base_dir;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow, noarchive">
    <title>Sign In - <?= htmlspecialchars($site_name) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        * { -webkit-tap-highlight-color: transparent; }
        body { font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif; }
        .font-mono { font-family: 'JetBrains Mono', monospace; }
        .shadow-2xs { box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05); }
        .shadow-xs { box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05); }
    </style>
</head>
<body class="w-full min-h-screen bg-[#f8fafd] text-[#1f1f1f] flex flex-col font-sans antialiased overflow-x-hidden selection:bg-[#d3e3fd] selection:text-[#041e49]">
    <!-- Public Header Navigation Bar (Google Material M3 Light Theme) -->
    <header class="w-full bg-white/95 border-b border-[#e1e7f0] sticky top-0 z-40 backdrop-blur-md shadow-2xs">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 py-3 sm:py-3.5 flex items-center justify-between gap-3 min-w-0">
            <!-- Left Header: Mobile 3-Line Hamburger Button + Site Logo/Name -->
            <div class="flex items-center gap-3">
                <button type="button" id="hamburgerBtn" onclick="toggleMobileMenu()" aria-label="Toggle navigation menu"
                    class="md:hidden p-2 rounded-xl bg-[#f0f4f9] border border-[#e1e7f0] text-[#1f1f1f] hover:bg-[#e8f0fe] hover:text-[#0b57d0] focus:outline-none cursor-pointer transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                </button>

                <a href="javascript:void(0)" class="flex items-center group text-decoration-none">
                    <?php if (!empty($site_logo_url)): ?>
                        <img src="<?= htmlspecialchars($site_logo_url) ?>" alt="<?= htmlspecialchars($site_name) ?>" class="h-8 max-w-[180px] object-contain">
                    <?php else: ?>
                        <span class="font-bold text-base sm:text-lg tracking-tight text-[#111827] group-hover:text-[#0b57d0] transition-colors">
                            <?= htmlspecialchars($site_name) ?>
                        </span>
                    <?php endif; ?>
                </a>
            </div>

            <!-- Right Header: Desktop Navigation Menu Buttons (Google Material M3 Chips) -->
            <nav class="hidden md:flex flex-row items-center gap-2">
                <?php foreach ($menu_items as $item): 
                    $m_title = htmlspecialchars($item['title'] ?? '');
                    $m_url = htmlspecialchars($item['url'] ?? '#');
                    $m_target = !empty($item['new_tab']) ? 'target="_blank" rel="noopener"' : '';
                ?>
                    <a href="<?= $m_url ?>" <?= $m_target ?>
                        class="px-3.5 py-1.5 rounded-full text-xs font-semibold text-[#444746] hover:text-[#0b57d0] bg-[#f0f4f9] hover:bg-[#e8f0fe] border border-[#e1e7f0] hover:border-[#c2e7ff] shadow-2xs transition-all whitespace-nowrap">
                        <?= $m_title ?>
                    </a>
                <?php endforeach; ?>
            </nav>
        </div>
    </header>

    <!-- Mobile Sidebar Drawer (Opens from Left Side, Occupies Half of the Page) -->
    <div id="mobileSidebarBackdrop" onclick="closeMobileMenu()" class="fixed inset-0 bg-black/40 backdrop-blur-xs z-50 hidden opacity-0 transition-opacity duration-300 md:hidden" aria-hidden="true"></div>

    <aside id="mobileSidebar" class="fixed inset-y-0 left-0 z-50 w-1/2 min-w-[250px] max-w-[340px] h-full bg-white border-r border-[#e1e7f0] shadow-2xl flex flex-col transform -translate-x-full transition-transform duration-300 ease-in-out md:hidden" aria-label="Mobile Navigation Drawer">
        <div class="p-4 border-b border-[#f0f4f9] flex items-center justify-between shrink-0">
            <div class="flex items-center min-w-0">
                <?php if (!empty($site_logo_url)): ?>
                    <img src="<?= htmlspecialchars($site_logo_url) ?>" alt="<?= htmlspecialchars($site_name) ?>" class="h-7 max-w-[140px] object-contain">
                <?php else: ?>
                    <span class="font-bold text-sm tracking-tight text-[#111827] truncate">
                        <?= htmlspecialchars($site_name) ?>
                    </span>
                <?php endif; ?>
            </div>
            <button type="button" onclick="closeMobileMenu()" class="p-1.5 rounded-xl text-[#5f6368] hover:text-[#111827] hover:bg-[#f0f4f9] transition-colors cursor-pointer" aria-label="Close menu">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <nav class="flex-1 overflow-y-auto p-4 flex flex-col space-y-2">
            <?php foreach ($menu_items as $item): 
                $m_title = htmlspecialchars($item['title'] ?? '');
                $m_url = htmlspecialchars($item['url'] ?? '#');
                $m_target = !empty($item['new_tab']) ? 'target="_blank" rel="noopener"' : '';
            ?>
                <a href="<?= $m_url ?>" <?= $m_target ?>
                    class="flex items-center justify-between px-4 py-3 rounded-2xl text-xs sm:text-sm font-semibold text-[#1f1f1f] hover:text-[#0b57d0] bg-[#f8fafd] hover:bg-[#e8f0fe] border border-[#e0e4eb] hover:border-[#c2e7ff] shadow-2xs transition-all">
                    <span><?= $m_title ?></span>
                    <svg class="w-4 h-4 text-[#5f6368] opacity-60" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                </a>
            <?php endforeach; ?>
        </nav>

        <div class="p-4 border-t border-[#f0f4f9] text-[11px] text-[#747775] text-center shrink-0">
            <?= htmlspecialchars($site_name) ?>
        </div>
    </aside>

    <!-- Main Sign In Workspace -->
    <main class="flex-1 w-full max-w-2xl mx-auto px-4 sm:px-6 py-8 flex items-center justify-center">
        <div class="w-full max-w-md mx-auto">
            <div class="bg-white rounded-3xl p-6 sm:p-8 border border-[#e0e4eb] shadow-xs space-y-6">
                <!-- Icon + Header -->
                <div class="text-center space-y-2">
                    <div class="w-12 h-12 rounded-2xl bg-[#e8f0fe] border border-[#c2e7ff] text-[#0b57d0] flex items-center justify-center mx-auto shadow-2xs">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                            <rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                        </svg>
                    </div>
                    <h2 class="text-xl font-extrabold text-[#111827] tracking-tight">Sign In</h2>
                    <p class="text-xs text-[#5f6368] leading-relaxed">
                        Enter your credentials to continue.
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
                    <input type="hidden" name="redirect" value="<?= htmlspecialchars($raw_redirect, ENT_QUOTES, 'UTF-8') ?>">
                    <div class="space-y-1.5">
                        <label class="text-xs font-semibold text-[#444746] block">
                            Username
                        </label>
                        <div class="relative">
                            <input
                                type="text"
                                name="username"
                                required
                                autofocus
                                value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                                placeholder="Enter username"
                                class="w-full pl-9 pr-4 py-2.5 rounded-xl border border-[#c4c7c5] focus:border-[#0b57d0] focus:ring-2 focus:ring-[#0b57d0]/15 outline-none text-sm text-[#1f1f1f]"
                            />
                            <svg class="w-4 h-4 text-[#747775] absolute left-3 top-3" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                <path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
                            </svg>
                        </div>
                    </div>

                    <div class="space-y-1.5">
                        <label class="text-xs font-semibold text-[#444746] block">
                            Password
                        </label>
                        <div class="relative">
                            <input
                                type="password"
                                name="password"
                                required
                                placeholder="••••••••"
                                class="w-full pl-9 pr-4 py-2.5 rounded-xl border border-[#c4c7c5] focus:border-[#0b57d0] focus:ring-2 focus:ring-[#0b57d0]/15 outline-none text-sm text-[#1f1f1f] font-mono"
                            />
                            <svg class="w-4 h-4 text-[#747775] absolute left-3 top-3" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                <path d="m15.5 7.5 2.3 2.3a1 1 0 0 0 1.4 0l2.1-2.1a1 1 0 0 0 0-1.4L19 4"/><path d="m21 2-9.6 9.6"/><circle cx="7.5" cy="15.5" r="5.5"/>
                            </svg>
                        </div>
                    </div>

                    <button
                        type="submit"
                        class="w-full py-3 rounded-xl bg-[#0b57d0] hover:bg-[#0842a0] text-white font-bold text-xs flex items-center justify-center gap-2 cursor-pointer transition-colors shadow-xs"
                    >
                        <span>Sign In</span>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                            <path d="M5 12h14"/><path d="m12 5 7 7-7 7"/>
                        </svg>
                    </button>
                </form>
            </div>
        </div>
    </main>

    <!-- Public Footer (Google Material M3 Light Theme) -->
    <footer class="w-full bg-white border-t border-[#e1e7f0] mt-auto py-6 px-4">
        <div class="max-w-4xl mx-auto text-center text-xs text-[#5f6368]">
            <div class="leading-relaxed">
                <?= $footer_copyright ?>
            </div>
            <div class="mt-2.5 pt-2.5 border-t border-[#f0f4f9] flex items-center justify-center flex-wrap gap-x-5 gap-y-1.5 text-xs font-medium text-[#5f6368]">
                <a href="<?= htmlspecialchars(($app_base_path === '' ? '' : $app_base_path) . '/dmca') ?>" class="hover:text-[#0b57d0] hover:underline transition-colors">DMCA</a>
                <span class="text-[#c4c7c5] select-none">•</span>
                <a href="<?= htmlspecialchars(($app_base_path === '' ? '' : $app_base_path) . '/disclaimer') ?>" class="hover:text-[#0b57d0] hover:underline transition-colors">Disclaimer</a>
                <span class="text-[#c4c7c5] select-none">•</span>
                <a href="<?= htmlspecialchars(($app_base_path === '' ? '' : $app_base_path) . '/about-us') ?>" class="hover:text-[#0b57d0] hover:underline transition-colors">About Us</a>
                <span class="text-[#c4c7c5] select-none">•</span>
                <a href="<?= htmlspecialchars(($app_base_path === '' ? '' : $app_base_path) . '/privacy-policy') ?>" class="hover:text-[#0b57d0] hover:underline transition-colors">Privacy Policy</a>
            </div>
        </div>
    </footer>

    <script>
        (function() {
            var isLoggedOut = window.location.search.indexOf('logged_out=1') !== -1;
            var isCookieSync = window.location.search.indexOf('cookie_sync=1') !== -1;
            if (isLoggedOut || isCookieSync) {
                var past = 'Thu, 01 Jan 1970 00:00:00 GMT';
                document.cookie = 'slea_auth_cookie=; Max-Age=0; Expires=' + past + '; Path=/';
                document.cookie = 'slea_admin_token=; Max-Age=0; Expires=' + past + '; Path=/';
                document.cookie = 'slea_login_state=; Max-Age=0; Expires=' + past + '; Path=/';
                document.cookie = 'slea_admin_exp=; Max-Age=0; Expires=' + past + '; Path=/';
                try { localStorage.removeItem('slea_browser_cookie_state'); } catch (e) {}
                return;
            }
            // If a valid unexpired (< 2h) local browser cookie exists, restore document.cookie automatically
            try {
                var raw = localStorage.getItem('slea_browser_cookie_state');
                if (raw) {
                    var parsed = JSON.parse(raw);
                    if (parsed && parsed.token && parsed.expMs && Number(parsed.expMs) > Date.now() + 5000) {
                        var maxAge = Math.max(1, Math.floor((Number(parsed.expMs) - Date.now()) / 1000));
                        var expUtc = new Date(Number(parsed.expMs)).toUTCString();
                        var sec = window.location.protocol === 'https:' ? '; Secure' : '';
                        document.cookie = 'slea_auth_cookie=' + encodeURIComponent(parsed.token) + '; Max-Age=' + maxAge + '; Expires=' + expUtc + '; Path=/; SameSite=Lax' + sec;
                        document.cookie = 'slea_admin_token=' + encodeURIComponent(parsed.token) + '; Max-Age=' + maxAge + '; Expires=' + expUtc + '; Path=/; SameSite=Lax' + sec;
                        document.cookie = 'slea_login_state=1; Max-Age=' + maxAge + '; Expires=' + expUtc + '; Path=/; SameSite=Lax' + sec;
                        document.cookie = 'slea_admin_exp=' + Number(parsed.expMs) + '; Max-Age=' + maxAge + '; Expires=' + expUtc + '; Path=/; SameSite=Lax' + sec;
                        var dest = <?= json_encode($safe_redirect_target) ?>;
                        var sep = dest.indexOf('?') === -1 ? '?' : '&';
                        window.location.replace(dest + sep + 'cookie_sync=1&_auth_cookie=' + encodeURIComponent(parsed.token));
                        return;
                    } else {
                        localStorage.removeItem('slea_browser_cookie_state');
                    }
                }
            } catch (e) {}
        })();

        function openMobileMenu() {
            var sidebar = document.getElementById('mobileSidebar');
            var backdrop = document.getElementById('mobileSidebarBackdrop');
            if (!sidebar || !backdrop) return;
            backdrop.classList.remove('hidden');
            void backdrop.offsetWidth;
            backdrop.classList.remove('opacity-0');
            backdrop.classList.add('opacity-100');
            sidebar.classList.remove('-translate-x-full');
            sidebar.classList.add('translate-x-0');
            document.body.style.overflow = 'hidden';
        }

        function closeMobileMenu() {
            var sidebar = document.getElementById('mobileSidebar');
            var backdrop = document.getElementById('mobileSidebarBackdrop');
            if (!sidebar || !backdrop) return;
            sidebar.classList.remove('translate-x-0');
            sidebar.classList.add('-translate-x-full');
            backdrop.classList.remove('opacity-100');
            backdrop.classList.add('opacity-0');
            setTimeout(function() {
                backdrop.classList.add('hidden');
                document.body.style.overflow = '';
            }, 300);
        }

        function toggleMobileMenu() {
            var sidebar = document.getElementById('mobileSidebar');
            if (sidebar && sidebar.classList.contains('translate-x-0')) {
                closeMobileMenu();
            } else {
                openMobileMenu();
            }
        }

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeMobileMenu();
            }
        });
    </script>
</body>
</html>
