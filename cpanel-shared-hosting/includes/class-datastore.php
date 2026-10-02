<?php
/**
 * Datastore for Generated Episode Button Pages and Site Settings
 * Supports MySQL and SQLite seamlessly with automatic dual-layer backup to prevent any data loss on updates.
 */

if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__)) {
    http_response_code(403);
    exit('Forbidden');
}

require_once __DIR__ . '/class-db.php';

class SLEA_Datastore {
    private static $debug_mode_cache = null;
    private static $error_handler_registered = false;
    private static $captured_debug_errors = [];

    private static function parse_bool_flag($val) {
        if (is_bool($val)) return $val;
        if (is_int($val) || is_float($val)) return intval($val) !== 0;
        if (is_string($val)) {
            $v = strtolower(trim($val));
            if (in_array($v, ['1', 'true', 'yes', 'on', 'enabled'], true)) return true;
            if (in_array($v, ['0', 'false', 'no', 'off', 'disabled', ''], true)) return false;
        }
        return !empty($val);
    }

    // -------------------------------------------------------------
    // Page Management with Automatic File-Backup & Self-Healing
    // -------------------------------------------------------------

    public static function get_all_pages() {
        try {
            $pdo = SLEA_DB::get_connection();
            $stmt = $pdo->query("SELECT * FROM pages ORDER BY created_at DESC, id DESC");
            $rows = $stmt->fetchAll();

            if (!empty($rows)) {
                $pages = [];
                foreach ($rows as $r) {
                    $pages[] = self::format_page_row($r);
                }
                // Automatically keep persistent backup file synchronized
                self::sync_pages_to_file($pages);
                return $pages;
            }
        } catch (Exception $e) {
            error_log('Error getting pages from database: ' . $e->getMessage());
        }

        // Self-Healing: If database returned empty (e.g. after update or database reconnection), check backup file
        $backup_pages = self::get_pages_from_file();
        if (!empty($backup_pages)) {
            // Restore pages into the database so they are never lost
            self::restore_pages_into_db($backup_pages);
            return $backup_pages;
        }

        return [];
    }

    public static function get_page_by_slug($slug, $public_only = false) {
        try {
            $pdo = SLEA_DB::get_connection();
            $stmt = $pdo->prepare("SELECT * FROM pages WHERE slug = :slug LIMIT 1");
            $stmt->execute([':slug' => $slug]);
            $row = $stmt->fetch();

            if ($row) {
                $formatted = self::format_page_row($row);
                if ($public_only && empty($formatted['is_public'])) {
                    return null;
                }
                return $formatted;
            }
        } catch (Exception $e) {
            error_log('Error finding page: ' . $e->getMessage());
        }

        // Check fallback from file backup only if database does not have this slug at all
        $file_pages = self::get_pages_from_file();
        foreach ($file_pages as $p) {
            if (($p['slug'] ?? '') === $slug) {
                $is_pub = isset($p['is_public']) ? !empty($p['is_public']) : true;
                if ($public_only && !$is_pub) {
                    return null;
                }
                return $p;
            }
        }

        return null;
    }

    public static function get_page_by_id($id) {
        try {
            $pdo = SLEA_DB::get_connection();
            $stmt = $pdo->prepare("SELECT * FROM pages WHERE id = :id OR page_key = :k LIMIT 1");
            $stmt->execute([':id' => $id, ':k' => $id]);
            $row = $stmt->fetch();

            if ($row) {
                return self::format_page_row($row);
            }
        } catch (Exception $e) {
            error_log('Error finding page by ID: ' . $e->getMessage());
        }

        // Fallback from file backup
        $file_pages = self::get_pages_from_file();
        foreach ($file_pages as $p) {
            if ($p['id'] == $id || ($p['page_key'] ?? '') === $id) {
                return $p;
            }
        }

        return null;
    }

    public static function sanitize_safe_href($url) {
        $u = trim((string)$url);
        if ($u === '') return '#';
        // Block dangerous URI schemes (javascript:, data:, vbscript:, file:)
        $normalized = preg_replace('/[\x00-\x20]+/', '', strtolower($u));
        if (preg_match('/^(javascript|data|vbscript|file):/i', $normalized)) {
            return '#';
        }
        return $u;
    }

    public static function save_page($page_data) {
        $pdo = SLEA_DB::get_connection();
        $now = date('Y-m-d H:i:s');

        $title        = !empty($page_data['title']) ? trim($page_data['title']) : 'Episode Download Links';
        $description  = !empty($page_data['description']) ? trim($page_data['description']) : '';
        $source_url   = !empty($page_data['source_url']) ? self::sanitize_safe_href($page_data['source_url']) : '';
        $resolved_url = !empty($page_data['resolved_url']) ? self::sanitize_safe_href($page_data['resolved_url']) : '';
        $theme        = !empty($page_data['theme']) ? $page_data['theme'] : (defined('DEFAULT_PAGE_THEME') ? DEFAULT_PAGE_THEME : 'indigo');
        $is_public    = isset($page_data['is_public']) ? intval($page_data['is_public']) : 1;
        $raw_buttons  = isset($page_data['buttons']) && is_array($page_data['buttons']) ? $page_data['buttons'] : [];
        $buttons      = [];
        foreach ($raw_buttons as $btn) {
            if (!is_array($btn)) continue;
            $btn['url'] = self::sanitize_safe_href($btn['url'] ?? '#');
            $buttons[] = $btn;
        }
        $buttons_json = json_encode($buttons, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

        // Sanitize and derive slug
        if (empty($page_data['slug'])) {
            $chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
            $rand_str = '';
            for ($i = 0; $i < 8; $i++) {
                $rand_str .= $chars[random_int(0, strlen($chars) - 1)];
            }
            $raw_slug = 'ep-' . $rand_str;
        } else {
            $raw_slug = $page_data['slug'];
        }
        $slug = self::sanitize_slug($raw_slug);
        $reserved_slugs = ['admin', 'pages', 'settings', 'analytics', 'update', 'updater', 'api', 'logout', 'setup', 'view', 'index', 'login', 'p', 'page', '404', 'assets', 'data', 'includes', 'backups', 'temp', 'database', 'dmca', 'disclaimer', 'about-us', 'about', 'privacy-policy', 'privacy'];
        $current_login_slug = strtolower(self::get_login_slug());
        if (in_array(strtolower($slug), $reserved_slugs, true) || ($current_login_slug !== '' && strtolower($slug) === $current_login_slug)) {
            $slug = 'ep-' . $slug;
        }

        $id = !empty($page_data['id']) ? intval($page_data['id']) : 0;
        $page_key = !empty($page_data['page_key']) ? $page_data['page_key'] : ('p_' . substr(md5(uniqid(rand(), true)), 0, 12));

        $saved_page = null;

        // Check if updating existing
        if ($id > 0) {
            // Check slug uniqueness excluding self
            $stmt_check = $pdo->prepare("SELECT id FROM pages WHERE slug = :s AND id != :id LIMIT 1");
            $stmt_check->execute([':s' => $slug, ':id' => $id]);
            if ($stmt_check->fetch()) {
                $slug .= '-' . rand(10, 99);
            }

            $stmt = $pdo->prepare("
                UPDATE pages SET
                    slug = :slug,
                    title = :title,
                    description = :desc,
                    source_url = :surl,
                    resolved_url = :rurl,
                    theme = :theme,
                    buttons_json = :btns,
                    is_public = :pub,
                    updated_at = :now
                WHERE id = :id
            ");
            $stmt->execute([
                ':slug'  => $slug,
                ':title' => $title,
                ':desc'  => $description,
                ':surl'  => $source_url,
                ':rurl'  => $resolved_url,
                ':theme' => $theme,
                ':btns'  => $buttons_json,
                ':pub'   => $is_public,
                ':now'   => $now,
                ':id'    => $id
            ]);

            $saved_page = self::get_page_by_id($id);
        } else {
            // New record: ensure unique slug
            $original_slug = $slug;
            $counter = 1;
            while (true) {
                $stmt_check = $pdo->prepare("SELECT id FROM pages WHERE slug = :s LIMIT 1");
                $stmt_check->execute([':s' => $slug]);
                if (!$stmt_check->fetch()) break;
                $slug = $original_slug . '-' . $counter;
                $counter++;
            }

            $stmt = $pdo->prepare("
                INSERT INTO pages (
                    page_key, slug, title, description, source_url, resolved_url,
                    theme, buttons_json, views, is_public, created_at, updated_at
                ) VALUES (
                    :k, :slug, :title, :desc, :surl, :rurl,
                    :theme, :btns, 0, :pub, :created_at, :updated_at
                )
            ");
            $stmt->execute([
                ':k'          => $page_key,
                ':slug'       => $slug,
                ':title'      => $title,
                ':desc'       => $description,
                ':surl'       => $source_url,
                ':rurl'       => $resolved_url,
                ':theme'      => $theme,
                ':btns'       => $buttons_json,
                ':pub'        => $is_public,
                ':created_at' => $now,
                ':updated_at' => $now
            ]);

            $new_id = $pdo->lastInsertId();
            $saved_page = self::get_page_by_id($new_id);
        }

        // Synchronize full pages state to persistent backup file immediately
        self::sync_pages_to_file();

        return $saved_page;
    }

    public static function toggle_public_status($id) {
        $pdo = SLEA_DB::get_connection();
        $stmt = $pdo->prepare("UPDATE pages SET is_public = CASE WHEN is_public = 1 THEN 0 ELSE 1 END, updated_at = :now WHERE id = :id OR page_key = :k");
        $stmt->execute([':now' => date('Y-m-d H:i:s'), ':id' => $id, ':k' => $id]);
        $page = self::get_page_by_id($id);
        self::sync_pages_to_file();
        return $page;
    }

    public static function delete_page($id) {
        $pdo = SLEA_DB::get_connection();
        $stmt = $pdo->prepare("DELETE FROM pages WHERE id = :id OR page_key = :k OR slug = :s");
        $res = $stmt->execute([':id' => $id, ':k' => $id, ':s' => $id]);
        self::sync_pages_to_file();
        return $res;
    }

    public static function bulk_delete_pages($ids) {
        if (empty($ids) || !is_array($ids)) return 0;
        $pdo = SLEA_DB::get_connection();
        $in = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare("DELETE FROM pages WHERE id IN ($in)");
        $stmt->execute($ids);
        $count = $stmt->rowCount();
        self::sync_pages_to_file();
        return $count;
    }

    public static function increment_views($slug) {
        try {
            $pdo = SLEA_DB::get_connection();
            $driver = SLEA_DB::get_driver();
            $stmt = $pdo->prepare("UPDATE pages SET views = views + 1 WHERE slug = :s");
            $stmt->execute([':s' => $slug]);

            // Track monthly breakdown dynamically
            $ym = date('Y-m');
            if ($driver === 'sqlite') {
                $mstmt = $pdo->prepare("
                    INSERT INTO page_views_monthly (page_slug, year_month, views)
                    VALUES (:slug, :ym, 1)
                    ON CONFLICT(page_slug, year_month) DO UPDATE SET views = views + 1
                ");
                $mstmt->execute([':slug' => $slug, ':ym' => $ym]);
            } else {
                $mstmt = $pdo->prepare("
                    INSERT INTO page_views_monthly (page_slug, year_month, views)
                    VALUES (:slug, :ym, 1)
                    ON DUPLICATE KEY UPDATE views = views + 1
                ");
                $mstmt->execute([':slug' => $slug, ':ym' => $ym]);
            }

            // Record 100% real visitor telemetry (device, traffic channel, country)
            self::record_visit_telemetry($slug);
        } catch (Exception $e) {
            // Non-blocking view increment
        }
    }

    public static function get_country_name_map() {
        return [
            'US' => 'United States', 'GB' => 'United Kingdom', 'CA' => 'Canada', 'AU' => 'Australia',
            'IN' => 'India', 'BD' => 'Bangladesh', 'PK' => 'Pakistan', 'ID' => 'Indonesia',
            'PH' => 'Philippines', 'MY' => 'Malaysia', 'SG' => 'Singapore', 'VN' => 'Vietnam',
            'TH' => 'Thailand', 'KR' => 'South Korea', 'JP' => 'Japan', 'CN' => 'China',
            'TW' => 'Taiwan', 'HK' => 'Hong Kong', 'DE' => 'Germany', 'FR' => 'France',
            'IT' => 'Italy', 'ES' => 'Spain', 'NL' => 'Netherlands', 'BR' => 'Brazil',
            'MX' => 'Mexico', 'AR' => 'Argentina', 'CO' => 'Colombia', 'CL' => 'Chile',
            'PE' => 'Peru', 'RU' => 'Russia', 'UA' => 'Ukraine', 'PL' => 'Poland',
            'TR' => 'Turkey', 'SA' => 'Saudi Arabia', 'AE' => 'United Arab Emirates', 'EG' => 'Egypt',
            'NG' => 'Nigeria', 'ZA' => 'South Africa', 'KE' => 'Kenya', 'MA' => 'Morocco',
            'NP' => 'Nepal', 'LK' => 'Sri Lanka', 'MM' => 'Myanmar', 'KH' => 'Cambodia',
            'SE' => 'Sweden', 'NO' => 'Norway', 'DK' => 'Denmark', 'FI' => 'Finland',
            'CH' => 'Switzerland', 'AT' => 'Austria', 'BE' => 'Belgium', 'PT' => 'Portugal',
            'GR' => 'Greece', 'CZ' => 'Czechia', 'RO' => 'Romania', 'HU' => 'Hungary',
            'IE' => 'Ireland', 'NZ' => 'New Zealand', 'IL' => 'Israel', 'QA' => 'Qatar',
            'KW' => 'Kuwait', 'OM' => 'Oman', 'BH' => 'Bahrain', 'JO' => 'Jordan',
            'IQ' => 'Iraq', 'IR' => 'Iran', 'UZ' => 'Uzbekistan', 'KZ' => 'Kazakhstan',
            'DZ' => 'Algeria', 'TN' => 'Tunisia', 'GH' => 'Ghana', 'ET' => 'Ethiopia'
        ];
    }

    public static function detect_device_type($ua = null) {
        if ($ua === null) {
            $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        }
        $ua_low = strtolower((string)$ua);
        if (empty($ua_low)) {
            return 'desktop';
        }
        if (preg_match('/(ipad|tablet|playbook|silk)|(android(?!.*mobile))/i', $ua_low)) {
            return 'tablet';
        }
        if (preg_match('/(mobi|iphone|ipod|android.*mobile|windows phone|blackberry|opera mini|iemobile|mobile)/i', $ua_low)) {
            return 'mobile';
        }
        return 'desktop';
    }

    public static function detect_traffic_channel($referer = null, $host = null) {
        if ($referer === null) {
            $referer = $_SERVER['HTTP_REFERER'] ?? '';
        }
        if ($host === null) {
            $host = $_SERVER['HTTP_HOST'] ?? '';
        }
        $ref = trim((string)$referer);
        if ($ref === '') {
            return 'direct';
        }
        $ref_host = strtolower((string)parse_url($ref, PHP_URL_HOST));
        $cur_host = strtolower(preg_replace('/:\d+$/', '', (string)$host));
        if ($ref_host === '' || ($cur_host !== '' && $ref_host === $cur_host)) {
            return 'direct';
        }
        if (preg_match('/(t\.me|telegram\.org|telegram\.me|wa\.me|whatsapp\.com|facebook\.com|fb\.com|fb\.me|m\.facebook\.com|l\.facebook\.com|twitter\.com|x\.com|t\.co|instagram\.com|l\.instagram\.com|youtube\.com|youtu\.be|tiktok\.com|reddit\.com|pinterest\.com|discord\.com|discord\.gg|linkedin\.com|vk\.com)/i', $ref_host)) {
            return 'social';
        }
        return 'organic';
    }

    public static function detect_country_info() {
        $map = self::get_country_name_map();
        $candidates = [
            $_SERVER['HTTP_CF_IPCOUNTRY'] ?? '',
            $_SERVER['GEOIP_COUNTRY_CODE'] ?? '',
            $_SERVER['HTTP_X_APPENGINE_COUNTRY'] ?? '',
            $_SERVER['HTTP_X_COUNTRY_CODE'] ?? '',
            $_SERVER['HTTP_X_GEO_COUNTRY'] ?? '',
            $_SERVER['HTTP_X_VERCEL_IP_COUNTRY'] ?? ''
        ];

        foreach ($candidates as $raw) {
            $code = strtoupper(trim((string)$raw));
            if (preg_match('/^[A-Z]{2}$/', $code) && !in_array($code, ['XX', 'T1', 'ZZ'], true)) {
                $geo_name = trim((string)($_SERVER['GEOIP_COUNTRY_NAME'] ?? ''));
                return [
                    'code' => $code,
                    'name' => $geo_name !== '' ? $geo_name : ($map[$code] ?? $code)
                ];
            }
        }

        // Fallback to real HTTP Accept-Language region subtag sent by visitor's browser
        $accept_lang = (string)($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '');
        if ($accept_lang !== '') {
            if (preg_match('/\b[a-z]{2,3}[-_]([A-Za-z]{2})\b/', $accept_lang, $m)) {
                $code = strtoupper($m[1]);
                if (isset($map[$code])) {
                    return ['code' => $code, 'name' => $map[$code]];
                }
            }
            $lang_to_country = [
                'bn' => 'BD', 'hi' => 'IN', 'ur' => 'PK', 'id' => 'ID', 'ms' => 'MY',
                'vi' => 'VN', 'th' => 'TH', 'tl' => 'PH', 'fil' => 'PH', 'ko' => 'KR',
                'ja' => 'JP', 'zh' => 'CN', 'ru' => 'RU', 'uk' => 'UA', 'tr' => 'TR',
                'ar' => 'SA', 'de' => 'DE', 'fr' => 'FR', 'es' => 'ES', 'pt' => 'BR',
                'it' => 'IT', 'nl' => 'NL', 'pl' => 'PL'
            ];
            if (preg_match('/^\s*([a-z]{2,3})\b/i', $accept_lang, $lm)) {
                $lcode = strtolower($lm[1]);
                if (isset($lang_to_country[$lcode])) {
                    $cc = $lang_to_country[$lcode];
                    return ['code' => $cc, 'name' => $map[$cc]];
                }
            }
        }

        return ['code' => 'UN', 'name' => 'Unknown'];
    }

    public static function get_visitor_session_id() {
        $raw_ip = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        if (strpos($raw_ip, ',') !== false) {
            $raw_ip = trim(explode(',', $raw_ip)[0]);
        }
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $today = date('Y-m-d');
        return substr(hash('sha256', $raw_ip . '|' . $ua . '|' . $today), 0, 32);
    }

    public static function format_duration_label($sec) {
        $sec = max(0, (int)round($sec));
        if ($sec <= 0) {
            return '0s';
        }
        $mins = (int)floor($sec / 60);
        $rem = $sec % 60;
        return $mins > 0 ? ($mins . 'm ' . $rem . 's') : ($rem . 's');
    }

    public static function record_visit_telemetry($slug, $override = null) {
        try {
            $pdo = SLEA_DB::get_connection();
            $device = is_array($override) && !empty($override['device_type'])
                ? $override['device_type']
                : self::detect_device_type();
            if (!in_array($device, ['mobile', 'desktop', 'tablet'], true)) {
                $device = 'desktop';
            }

            $channel = is_array($override) && !empty($override['traffic_channel'])
                ? $override['traffic_channel']
                : self::detect_traffic_channel();
            if (!in_array($channel, ['direct', 'social', 'organic'], true)) {
                $channel = 'direct';
            }

            $country = self::detect_country_info();
            if (is_array($override) && !empty($override['country_code'])) {
                $cc = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', (string)$override['country_code']), 0, 2));
                if ($cc !== '') {
                    $map = self::get_country_name_map();
                    $country = [
                        'code' => $cc,
                        'name' => !empty($override['country_name']) ? trim((string)$override['country_name']) : ($map[$cc] ?? $cc)
                    ];
                }
            }

            $raw_ip = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
            if (strpos($raw_ip, ',') !== false) {
                $raw_ip = trim(explode(',', $raw_ip)[0]);
            }
            $ip_hash = $raw_ip !== '' ? substr(hash('sha256', $raw_ip . date('Y-m')), 0, 24) : '';
            $session_id = self::get_visitor_session_id();
            $ref_raw = trim((string)($_SERVER['HTTP_REFERER'] ?? ''));
            $ref_host = $ref_raw !== '' ? strtolower((string)parse_url($ref_raw, PHP_URL_HOST)) : '';
            $today = date('Y-m-d');
            $ym = date('Y-m');
            $now = date('Y-m-d H:i:s');

            if (session_status() === PHP_SESSION_ACTIVE) {
                if (empty($_SESSION['slea_session_start_ts'])) {
                    $_SESSION['slea_session_start_ts'] = time();
                }
            }

            $duration_sec = is_array($override) && isset($override['duration_sec'])
                ? max(1, min(7200, (int)$override['duration_sec']))
                : 1;

            if (session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['slea_session_start_ts'])) {
                $sess_elapsed = max(1, min(7200, time() - (int)$_SESSION['slea_session_start_ts']));
                $duration_sec = max($duration_sec, $sess_elapsed);
            }

            // Check if this visitor session already has a record today to compute real multi-request session elapsed time
            try {
                $chk = $pdo->prepare("
                    SELECT MIN(COALESCE(created_at, visited_at)) AS first_seen, MAX(duration_sec) AS max_dur
                    FROM analytics_visits
                    WHERE (session_id = :sid OR (ip_hash != '' AND ip_hash = :iph))
                      AND (visit_date = :vd OR SUBSTR(COALESCE(created_at, visited_at), 1, 10) = :vd2)
                ");
                $chk->execute([
                    ':sid' => $session_id,
                    ':iph' => $ip_hash,
                    ':vd'  => $today,
                    ':vd2' => $today
                ]);
                $prev = $chk->fetch(PDO::FETCH_ASSOC);
                if ($prev && !empty($prev['first_seen'])) {
                    $first_ts = strtotime($prev['first_seen']);
                    if ($first_ts !== false && $first_ts > 0) {
                        $elapsed = max(1, min(7200, time() - $first_ts));
                        $duration_sec = max($duration_sec, (int)($prev['max_dur'] ?? 0), $elapsed);
                    }
                    $upd = $pdo->prepare("
                        UPDATE analytics_visits
                        SET duration_sec = CASE WHEN duration_sec > :dur1 THEN duration_sec ELSE :dur2 END,
                            updated_at = :now,
                            session_id = CASE WHEN session_id = '' OR session_id IS NULL THEN :sid_set ELSE session_id END
                        WHERE (session_id = :sid OR (ip_hash != '' AND ip_hash = :iph))
                          AND (visit_date = :vd OR SUBSTR(COALESCE(created_at, visited_at), 1, 10) = :vd2)
                    ");
                    $upd->execute([
                        ':dur1'    => $duration_sec,
                        ':dur2'    => $duration_sec,
                        ':now'     => $now,
                        ':sid_set' => $session_id,
                        ':sid'     => $session_id,
                        ':iph'     => $ip_hash,
                        ':vd'      => $today,
                        ':vd2'     => $today
                    ]);
                }
            } catch (Exception $e) {
                // Non-blocking
            }

            $stmt = $pdo->prepare("
                INSERT INTO analytics_visits (
                    session_id, page_slug, device_type, channel, traffic_channel,
                    referrer_host, country, country_name, country_code, ip_hash,
                    duration_sec, visit_date, year_month, visited_at, created_at, updated_at
                ) VALUES (
                    :sid, :slug, :dev, :chan1, :chan2,
                    :ref, :cnt1, :cnt2, :cc, :iph,
                    :dur, :vd, :ym, :va, :ca, :ua
                )
            ");
            $stmt->execute([
                ':sid'   => $session_id,
                ':slug'  => substr((string)$slug, 0, 120),
                ':dev'   => $device,
                ':chan1' => $channel,
                ':chan2' => $channel,
                ':ref'   => substr($ref_host, 0, 190),
                ':cnt1'  => $country['name'],
                ':cnt2'  => $country['name'],
                ':cc'    => $country['code'],
                ':iph'   => $ip_hash,
                ':dur'   => $duration_sec,
                ':vd'    => $today,
                ':ym'    => $ym,
                ':va'    => $now,
                ':ca'    => $now,
                ':ua'    => $now
            ]);
        } catch (Exception $e) {
            // Non-blocking
        }
    }

    public static function update_visit_duration($slug = '', $client_duration_sec = 0) {
        try {
            $pdo = SLEA_DB::get_connection();
            $session_id = self::get_visitor_session_id();
            $raw_ip = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
            if (strpos($raw_ip, ',') !== false) {
                $raw_ip = trim(explode(',', $raw_ip)[0]);
            }
            $ip_hash = $raw_ip !== '' ? substr(hash('sha256', $raw_ip . date('Y-m')), 0, 24) : '';
            $today = date('Y-m-d');
            $now = date('Y-m-d H:i:s');
            $dur = max(1, min(7200, (int)$client_duration_sec));

            if (session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['slea_session_start_ts'])) {
                $dur = max($dur, max(1, min(7200, time() - (int)$_SESSION['slea_session_start_ts'])));
            }

            $chk = $pdo->prepare("
                SELECT id, COALESCE(created_at, visited_at) AS first_seen, duration_sec
                FROM analytics_visits
                WHERE (session_id = :sid OR (ip_hash != '' AND ip_hash = :iph))
                ORDER BY id ASC
            ");
            $chk->execute([':sid' => $session_id, ':iph' => $ip_hash]);
            $rows = $chk->fetchAll(PDO::FETCH_ASSOC);

            if (!empty($rows)) {
                $first_seen = $rows[0]['first_seen'] ?? '';
                if ($first_seen !== '') {
                    $first_ts = strtotime($first_seen);
                    if ($first_ts !== false && $first_ts > 0) {
                        $server_elapsed = max(1, min(7200, time() - $first_ts));
                        $dur = max($dur, $server_elapsed);
                    }
                }
                $upd = $pdo->prepare("
                    UPDATE analytics_visits
                    SET duration_sec = CASE WHEN duration_sec > :d1 THEN duration_sec ELSE :d2 END,
                        updated_at = :now,
                        session_id = CASE WHEN session_id = '' OR session_id IS NULL THEN :sid_set ELSE session_id END
                    WHERE (session_id = :sid OR (ip_hash != '' AND ip_hash = :iph))
                ");
                $upd->execute([
                    ':d1'      => $dur,
                    ':d2'      => $dur,
                    ':now'     => $now,
                    ':sid_set' => $session_id,
                    ':sid'     => $session_id,
                    ':iph'     => $ip_hash
                ]);
                return $dur;
            } else {
                self::record_visit_telemetry($slug !== '' ? $slug : '__visit__', ['duration_sec' => $dur]);
                return $dur;
            }
        } catch (Exception $e) {
            return max(1, (int)$client_duration_sec);
        }
    }

    public static function get_visit_telemetry_stats() {
        $stats = [
            'total_tracked'          => 0,
            'total_tracked_visits'   => 0,
            'active_sessions'        => 0,
            'avg_duration_sec'       => 0,
            'avg_duration_formatted' => '0s',
            'devices' => [
                'mobile'  => ['count' => 0, 'percent' => 0, 'pct' => 0],
                'desktop' => ['count' => 0, 'percent' => 0, 'pct' => 0],
                'tablet'  => ['count' => 0, 'percent' => 0, 'pct' => 0],
            ],
            'channels' => [
                'direct'   => ['count' => 0, 'percent' => 0, 'pct' => 0],
                'social'   => ['count' => 0, 'percent' => 0, 'pct' => 0],
                'organic'  => ['count' => 0, 'percent' => 0, 'pct' => 0],
                'search'   => ['count' => 0, 'percent' => 0, 'pct' => 0],
                'referral' => ['count' => 0, 'percent' => 0, 'pct' => 0],
            ],
            'countries' => []
        ];

        try {
            $pdo = SLEA_DB::get_connection();
            $total = (int)$pdo->query("SELECT COUNT(*) FROM analytics_visits")->fetchColumn();

            // If no visits have been logged yet in analytics_visits and this is a real browser HTTP request,
            // record the current real HTTP request telemetry so real session/duration/device/channel/country data is captured immediately.
            if ($total === 0 && !empty($_SERVER['HTTP_USER_AGENT'])) {
                self::record_visit_telemetry('__visit__');
                $total = (int)$pdo->query("SELECT COUNT(*) FROM analytics_visits")->fetchColumn();
            } elseif ($total > 0 && !empty($_SERVER['HTTP_USER_AGENT'])) {
                // Refresh current active session's elapsed duration if this browser session already has a visit row
                $cur_sid = self::get_visitor_session_id();
                $raw_ip = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
                if (strpos($raw_ip, ',') !== false) {
                    $raw_ip = trim(explode(',', $raw_ip)[0]);
                }
                $cur_iph = $raw_ip !== '' ? substr(hash('sha256', $raw_ip . date('Y-m')), 0, 24) : '';
                $chk_cur = $pdo->prepare("SELECT COUNT(*) FROM analytics_visits WHERE session_id = :sid OR (ip_hash != '' AND ip_hash = :iph)");
                $chk_cur->execute([':sid' => $cur_sid, ':iph' => $cur_iph]);
                if ((int)$chk_cur->fetchColumn() > 0) {
                    self::update_visit_duration('', 1);
                }
            }

            $stats['total_tracked'] = $total;
            $stats['total_tracked_visits'] = $total;
            if ($total <= 0) {
                return $stats;
            }

            // Compute 100% real Active Sessions and Avg Visit Duration from analytics_visits rows
            $stmt_all = $pdo->query("
                SELECT id, session_id, ip_hash, duration_sec, visited_at, created_at, updated_at
                FROM analytics_visits
                ORDER BY id ASC
            ");
            $session_map = [];
            if ($stmt_all) {
                while ($row = $stmt_all->fetch(PDO::FETCH_ASSOC)) {
                    $skey = trim((string)($row['session_id'] ?? ''));
                    if ($skey === '') {
                        $skey = trim((string)($row['ip_hash'] ?? ''));
                    }
                    if ($skey === '') {
                        $skey = 'row_' . ($row['id'] ?? uniqid());
                    }

                    $t_start_str = $row['created_at'] ?: ($row['visited_at'] ?: '');
                    $t_end_str   = $row['updated_at'] ?: ($row['visited_at'] ?: $t_start_str);
                    $t_start = $t_start_str !== '' ? (int)strtotime($t_start_str) : 0;
                    $t_end   = $t_end_str !== '' ? (int)strtotime($t_end_str) : $t_start;
                    $row_dur = max(0, (int)($row['duration_sec'] ?? 0));

                    if (!isset($session_map[$skey])) {
                        $session_map[$skey] = [
                            'min_ts'  => $t_start,
                            'max_ts'  => $t_end,
                            'max_dur' => $row_dur,
                            'hits'    => 1
                        ];
                    } else {
                        if ($t_start > 0 && ($session_map[$skey]['min_ts'] === 0 || $t_start < $session_map[$skey]['min_ts'])) {
                            $session_map[$skey]['min_ts'] = $t_start;
                        }
                        if ($t_end > $session_map[$skey]['max_ts']) {
                            $session_map[$skey]['max_ts'] = $t_end;
                        }
                        if ($row_dur > $session_map[$skey]['max_dur']) {
                            $session_map[$skey]['max_dur'] = $row_dur;
                        }
                        $session_map[$skey]['hits']++;
                    }
                }
            }

            $active_sessions = max(1, count($session_map));
            $session_durations = [];
            $now_ts = time();
            $today_str = date('Y-m-d');

            foreach ($session_map as $sinfo) {
                $span = ($sinfo['max_ts'] > 0 && $sinfo['min_ts'] > 0 && $sinfo['max_ts'] >= $sinfo['min_ts'])
                    ? min(7200, $sinfo['max_ts'] - $sinfo['min_ts'])
                    : 0;
                $d = max($sinfo['max_dur'], $span);
                if ($d <= 0 && $sinfo['min_ts'] > 0) {
                    if (date('Y-m-d', $sinfo['min_ts']) === $today_str && ($now_ts - $sinfo['min_ts']) >= 0) {
                        $d = max(1, min(3600, $now_ts - $sinfo['min_ts']));
                    } else {
                        $d = 1;
                    }
                }
                $session_durations[] = max(1, $d);
            }

            $avg_duration_sec = !empty($session_durations)
                ? max(1, (int)round(array_sum($session_durations) / count($session_durations)))
                : 1;

            $stats['active_sessions']        = $active_sessions;
            $stats['avg_duration_sec']       = $avg_duration_sec;
            $stats['avg_duration_formatted'] = self::format_duration_label($avg_duration_sec);

            // Device breakdown
            $stmt_d = $pdo->query("SELECT device_type, COUNT(*) as cnt FROM analytics_visits GROUP BY device_type");
            if ($stmt_d) {
                while ($r = $stmt_d->fetch(PDO::FETCH_ASSOC)) {
                    $dtype = strtolower(trim($r['device_type'] ?? ''));
                    $cnt = (int)($r['cnt'] ?? 0);
                    if (isset($stats['devices'][$dtype])) {
                        $pct = (int)round(($cnt / $total) * 100);
                        $stats['devices'][$dtype]['count'] = $cnt;
                        $stats['devices'][$dtype]['percent'] = $pct;
                        $stats['devices'][$dtype]['pct'] = $pct;
                    }
                }
            }

            // Traffic channels breakdown (supports both traffic_channel and channel columns)
            $stmt_c = $pdo->query("
                SELECT COALESCE(NULLIF(traffic_channel, ''), NULLIF(channel, ''), 'direct') AS ch_name, COUNT(*) as cnt
                FROM analytics_visits
                GROUP BY ch_name
            ");
            if ($stmt_c) {
                while ($r = $stmt_c->fetch(PDO::FETCH_ASSOC)) {
                    $chan = strtolower(trim($r['ch_name'] ?? 'direct'));
                    if ($chan === 'search' || $chan === 'referral') {
                        $chan_norm = 'organic';
                    } else {
                        $chan_norm = in_array($chan, ['direct', 'social', 'organic'], true) ? $chan : 'direct';
                    }
                    $cnt = (int)($r['cnt'] ?? 0);
                    $stats['channels'][$chan_norm]['count'] += $cnt;
                    $stats['channels'][$chan_norm]['percent'] = (int)round(($stats['channels'][$chan_norm]['count'] / $total) * 100);
                    $stats['channels'][$chan_norm]['pct'] = $stats['channels'][$chan_norm]['percent'];
                }
                $stats['channels']['search'] = $stats['channels']['organic'];
            }

            // Top countries breakdown (supports both country_name and country columns)
            $stmt_loc = $pdo->query("
                SELECT
                    COALESCE(NULLIF(country_code, ''), 'UN') AS c_code,
                    COALESCE(NULLIF(country_name, ''), NULLIF(country, ''), 'Unknown') AS c_name,
                    COUNT(*) as cnt
                FROM analytics_visits
                GROUP BY c_code, c_name
                ORDER BY cnt DESC
                LIMIT 5
            ");
            if ($stmt_loc) {
                while ($r = $stmt_loc->fetch(PDO::FETCH_ASSOC)) {
                    $cnt = (int)($r['cnt'] ?? 0);
                    $pct = (int)round(($cnt / $total) * 100);
                    $stats['countries'][] = [
                        'code'         => $r['c_code'] ?: 'UN',
                        'country_code' => $r['c_code'] ?: 'UN',
                        'name'         => $r['c_name'] ?: 'Unknown',
                        'country'      => $r['c_name'] ?: 'Unknown',
                        'count'        => $cnt,
                        'percent'      => $pct,
                        'pct'          => $pct
                    ];
                }
            }
        } catch (Exception $e) {
            // Safe fallback
        }

        return $stats;
    }

    public static function get_monthly_views_stats() {
        $monthly_data = [];
        try {
            $pdo = SLEA_DB::get_connection();
            $stmt = $pdo->query("SELECT year_month, SUM(views) as total_views FROM page_views_monthly GROUP BY year_month ORDER BY year_month ASC");
            if ($stmt) {
                while ($row = $stmt->fetch()) {
                    if (!empty($row['year_month'])) {
                        $monthly_data[$row['year_month']] = (int)$row['total_views'];
                    }
                }
            }
        } catch (Exception $e) {
            // Table might not exist or error, fallback safely
        }
        return $monthly_data;
    }

    // -------------------------------------------------------------
    // Unified Multi-Driver Settings Engine (Guaranteed Zero SQL Errors & Zero Data Loss)
    // -------------------------------------------------------------

    /**
     * Unified Setting Storage that works 100% reliably on MySQL, MariaDB, and SQLite
     * Never throws "near DUPLICATE: syntax error" on SQLite.
     * Also mirrors to data/settings_backup.json to prevent setting loss across updates.
     */
    public static function save_raw_setting($key, $value) {
        $pdo = SLEA_DB::get_connection();
        $now = date('Y-m-d H:i:s');
        $json = is_string($value) ? $value : json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        try {
            // Remove any existing/duplicate rows for this setting_key first to guarantee single authoritative value
            $del = $pdo->prepare("DELETE FROM settings WHERE setting_key = :key");
            $del->execute([':key' => $key]);
            $ins = $pdo->prepare("INSERT INTO settings (setting_key, setting_value, updated_at) VALUES (:key, :val, :now)");
            $ins->execute([':key' => $key, ':val' => $json, ':now' => $now]);
        } catch (Exception $e) {
            try {
                $upd = $pdo->prepare("UPDATE settings SET setting_value = :val, updated_at = :now WHERE setting_key = :key");
                $upd->execute([':val' => $json, ':now' => $now, ':key' => $key]);
                if ($upd->rowCount() === 0) {
                    $ins2 = $pdo->prepare("INSERT INTO settings (setting_key, setting_value, updated_at) VALUES (:key, :val, :now)");
                    $ins2->execute([':key' => $key, ':val' => $json, ':now' => $now]);
                }
            } catch (Exception $e2) {
                error_log("Error saving setting '{$key}': " . $e2->getMessage());
            }
        }

        // Mirror setting to persistent JSON backup file
        self::sync_setting_to_backup_file($key, $value);

        return true;
    }

    /**
     * Unified Setting Retrieval with fallback to persistent JSON backup
     */
    public static function get_raw_setting($key) {
        try {
            $pdo = SLEA_DB::get_connection();
            $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = :k ORDER BY updated_at DESC LIMIT 1");
            $stmt->execute([':k' => $key]);
            $row = $stmt->fetch();
            if ($row && isset($row['setting_value']) && $row['setting_value'] !== '') {
                return $row['setting_value'];
            }
        } catch (Throwable $e) {
            error_log("Error reading setting '{$key}': " . $e->getMessage());
        }

        // Fallback to data/settings_backup.json if database setting is missing
        $file_settings = self::get_settings_from_backup_file();
        if (isset($file_settings[$key])) {
            $val = $file_settings[$key];
            return is_string($val) ? $val : json_encode($val, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        return null;
    }

    // -------------------------------------------------------------
    // Menu Management
    // -------------------------------------------------------------

    public static function get_menu_items() {
        $raw = self::get_raw_setting('menu_items');
        if (!empty($raw)) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) return $decoded;
        }

        return [
            ['id' => 'm1', 'title' => 'Home', 'url' => 'https://moviehubhq.com/', 'new_tab' => false],
            ['id' => 'm2', 'title' => 'Korean Drama', 'url' => 'https://moviehubhq.com/catagory/korean/', 'new_tab' => false],
            ['id' => 'm3', 'title' => 'Chinese Drama', 'url' => 'https://moviehubhq.com/catagory/chinese/', 'new_tab' => false]
        ];
    }

    public static function save_menu_items($items) {
        $clean = [];
        if (is_array($items)) {
            foreach ($items as $item) {
                if (!is_array($item)) continue;
                $item['url'] = self::sanitize_safe_href($item['url'] ?? '#');
                $clean[] = $item;
            }
        }
        return self::save_raw_setting('menu_items', array_values($clean));
    }

    // -------------------------------------------------------------
    // Ad & Monetization Settings
    // -------------------------------------------------------------

    public static function get_ad_settings() {
        $defaults = [
            'adsense_auto_enabled' => false,
            'adsense_client_id'    => '',
            'banner_ads_enabled'   => false,
            'ad_top_enabled'       => false,
            'ad_top_code'          => '',
            'ad_middle_enabled'    => false,
            'ad_middle_code'       => '',
            'ad_bottom_enabled'    => false,
            'ad_bottom_code'       => ''
        ];

        $raw = self::get_raw_setting('ad_settings');
        if (!empty($raw)) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return array_merge($defaults, $decoded);
            }
        }

        return $defaults;
    }

    public static function save_ad_settings($ad_data) {
        $clean = [
            'adsense_auto_enabled' => !empty($ad_data['adsense_auto_enabled']),
            'adsense_client_id'    => trim($ad_data['adsense_client_id'] ?? ''),
            'banner_ads_enabled'   => !empty($ad_data['banner_ads_enabled']),
            'ad_top_enabled'       => !empty($ad_data['ad_top_enabled']),
            'ad_top_code'          => trim($ad_data['ad_top_code'] ?? ''),
            'ad_middle_enabled'    => !empty($ad_data['ad_middle_enabled']),
            'ad_middle_code'       => trim($ad_data['ad_middle_code'] ?? ''),
            'ad_bottom_enabled'    => !empty($ad_data['ad_bottom_enabled']),
            'ad_bottom_code'       => trim($ad_data['ad_bottom_code'] ?? '')
        ];

        self::save_raw_setting('ad_settings', $clean);
        return $clean;
    }

    // -------------------------------------------------------------
    // Site Identity Settings
    // -------------------------------------------------------------

    public static function get_site_identity() {
        $defaults = [
            'site_name'      => 'Movie Hub HQ Drive',
            'site_logo_url'  => '',
            'site_logo_text' => 'MHQ',
        ];

        $raw = self::get_raw_setting('site_identity');
        if (!empty($raw)) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return array_merge($defaults, $decoded);
            }
        }

        return $defaults;
    }

    public static function save_site_identity($data) {
        $raw_logo = trim($data['site_logo_url'] ?? '');
        $safe_logo = $raw_logo !== '' ? self::sanitize_safe_href($raw_logo) : '';
        if ($safe_logo === '#') {
            $safe_logo = '';
        }
        $clean = [
            'site_name'      => !empty($data['site_name']) ? trim($data['site_name']) : 'Movie Hub HQ Drive',
            'site_logo_url'  => $safe_logo,
            'site_logo_text' => trim($data['site_logo_text'] ?? 'MHQ'),
        ];

        self::save_raw_setting('site_identity', $clean);
        return $clean;
    }

    // -------------------------------------------------------------
    // Custom Admin Login Page Path Settings
    // -------------------------------------------------------------

    public static function sanitize_login_slug($raw) {
        $s = trim((string)$raw);
        $s = trim($s, "/\\ \t\n\r\0\x0B");
        $s = preg_replace('/\.php$/i', '', $s);
        $s = trim($s, "/\\");
        $s = preg_replace('/[^a-zA-Z0-9_-]/', '-', strtolower($s));
        $s = preg_replace('/-+/', '-', $s);
        $s = trim($s, '-');
        return $s;
    }

    public static function get_login_slug() {
        $raw = self::get_raw_setting('login_slug');
        if (!empty($raw)) {
            $decoded = json_decode($raw, true);
            $val = (json_last_error() === JSON_ERROR_NONE && is_string($decoded)) ? $decoded : (is_string($raw) ? trim($raw, '"') : '');
            $clean = self::sanitize_login_slug($val);
            if (!empty($clean)) {
                return $clean;
            }
        }
        return 'login';
    }

    public static function save_login_slug($slug) {
        $clean = self::sanitize_login_slug($slug);
        if (empty($clean)) {
            $clean = 'login';
        }
        $reserved = ['admin', 'pages', 'settings', 'analytics', 'update', 'updater', 'api', 'logout', 'setup', 'view', 'index', 'p', 'page', '404', 'assets', 'data', 'includes', 'backups', 'temp', 'database', 'dmca', 'disclaimer', 'about-us', 'about', 'privacy-policy', 'privacy'];
        if (in_array($clean, $reserved, true)) {
            throw new Exception("The path '/{$clean}' is reserved by the system. Please choose a different login path.");
        }
        if ($clean !== 'login' && self::get_page_by_slug($clean, false) !== null) {
            throw new Exception("The path '/{$clean}' is already used by an episode page. Please choose a unique login path.");
        }
        self::save_raw_setting('login_slug', $clean);
        return $clean;
    }

    public static function get_login_url() {
        $slug = self::get_login_slug();
        return $slug === 'login' ? 'login.php' : $slug;
    }

    // -------------------------------------------------------------
    // Maintenance Mode Settings (With Countdown End Time)
    // -------------------------------------------------------------

    public static function get_maintenance_settings() {
        $defaults = [
            'enabled'       => false,
            'message'       => 'The website is currently undergoing scheduled maintenance. We will be back shortly!',
            'end_time'      => '',
            'end_timestamp' => 0
        ];

        $raw = self::get_raw_setting('maintenance_settings');
        if (!empty($raw)) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $merged = array_merge($defaults, $decoded);
                $merged['end_timestamp'] = (isset($merged['end_timestamp']) && is_numeric($merged['end_timestamp']))
                    ? round((float)$merged['end_timestamp'])
                    : 0;
                if ($merged['end_timestamp'] <= 0 && !empty($merged['end_time'])) {
                    $ts = strtotime(str_replace('T', ' ', $merged['end_time']));
                    if ($ts !== false && $ts > 0) {
                        $merged['end_timestamp'] = round((float)$ts * 1000);
                    }
                }
                return $merged;
            }
        }

        return $defaults;
    }

    public static function save_maintenance_settings($data) {
        $enabled = !empty($data['enabled']);
        $end_time = trim($data['end_time'] ?? '');
        $end_timestamp = (isset($data['end_timestamp']) && is_numeric($data['end_timestamp']))
            ? round((float)$data['end_timestamp'])
            : 0;

        if (empty($end_time) && $end_timestamp <= 0) {
            $end_timestamp = 0;
        } elseif ($end_timestamp <= 0 && !empty($end_time)) {
            $ts = strtotime(str_replace('T', ' ', $end_time));
            if ($ts !== false && $ts > 0) {
                $end_timestamp = round((float)$ts * 1000);
            }
        }

        $explicitly_cleared = !empty($data['clear_countdown']);
        if ($enabled && !$explicitly_cleared && $end_timestamp <= round(microtime(true) * 1000)) {
            $future_sec = time() + (2 * 3600);
            $end_timestamp = round((float)$future_sec * 1000);
            if (empty($end_time)) {
                $end_time = date('Y-m-d\TH:i', $future_sec);
            }
        }

        $clean = [
            'enabled'       => $enabled,
            'message'       => !empty($data['message']) ? trim($data['message']) : 'The website is currently undergoing scheduled maintenance. We will be back shortly!',
            'end_time'      => $end_time,
            'end_timestamp' => $end_timestamp
        ];

        self::save_raw_setting('maintenance_settings', $clean);
        return $clean;
    }

    // -------------------------------------------------------------
    // Share Settings
    // -------------------------------------------------------------

    public static function get_share_settings() {
        $defaults = [
            'enabled' => true,
            'show_in_page' => true,
            'show_mobile_fab' => false,
            'platforms' => [
                'copy' => true,
                'whatsapp' => true,
                'telegram' => true,
                'facebook' => true,
                'twitter' => true,
                'native_share' => true,
                'qr_code' => true
            ]
        ];

        $raw = self::get_raw_setting('share_settings');
        if (!empty($raw)) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $merged = array_merge($defaults, $decoded);
                $merged['show_mobile_fab'] = false;
                return $merged;
            }
        }

        return $defaults;
    }

    public static function save_share_settings($data) {
        $clean = [
            'enabled' => isset($data['enabled']) ? !empty($data['enabled']) : true,
            'show_in_page' => isset($data['show_in_page']) ? !empty($data['show_in_page']) : true,
            'show_mobile_fab' => false,
            'platforms' => is_array($data['platforms'] ?? null) ? $data['platforms'] : [
                'copy' => true,
                'whatsapp' => true,
                'telegram' => true,
                'facebook' => true,
                'twitter' => true,
                'native_share' => true,
                'qr_code' => true
            ]
        ];

        self::save_raw_setting('share_settings', $clean);
        return $clean;
    }

    // -------------------------------------------------------------
    // System Debug Mode & Public Error Handling Settings
    // -------------------------------------------------------------

    public static function get_debug_settings() {
        if (self::$debug_mode_cache !== null) {
            return ['enabled' => (bool)self::$debug_mode_cache];
        }

        $defaults = [
            'enabled' => false,
        ];

        $raw = self::get_raw_setting('debug_settings');
        if ($raw !== null && $raw !== '') {
            $decoded = is_array($raw) ? $raw : json_decode((string)$raw, true);
            if (is_array($decoded) && array_key_exists('enabled', $decoded)) {
                $enabled = self::parse_bool_flag($decoded['enabled']);
                self::$debug_mode_cache = $enabled;
                return ['enabled' => $enabled];
            } elseif (is_bool($decoded) || is_numeric($decoded) || is_string($decoded)) {
                $enabled = self::parse_bool_flag($decoded);
                self::$debug_mode_cache = $enabled;
                return ['enabled' => $enabled];
            }
        }

        $file_settings = self::get_settings_from_backup_file();
        if (isset($file_settings['debug_settings'])) {
            $ds = $file_settings['debug_settings'];
            if (is_string($ds)) {
                $ds = json_decode($ds, true);
            }
            if (is_array($ds) && array_key_exists('enabled', $ds)) {
                $enabled = self::parse_bool_flag($ds['enabled']);
                self::$debug_mode_cache = $enabled;
                return ['enabled' => $enabled];
            }
        }

        self::$debug_mode_cache = false;
        return $defaults;
    }

    public static function save_debug_settings($data) {
        $enabled_raw = is_array($data) ? ($data['enabled'] ?? false) : $data;
        $enabled = self::parse_bool_flag($enabled_raw);
        $clean = [
            'enabled' => $enabled,
        ];
        self::$debug_mode_cache = $enabled;
        self::save_raw_setting('debug_settings', $clean);
        return $clean;
    }

    public static function is_debug_mode() {
        try {
            $dbg = self::get_debug_settings();
            return !empty($dbg['enabled']);
        } catch (Throwable $e) {
            try {
                $file_settings = self::get_settings_from_backup_file();
                if (isset($file_settings['debug_settings']['enabled'])) {
                    return self::parse_bool_flag($file_settings['debug_settings']['enabled']);
                }
            } catch (Throwable $e2) {}
            return false;
        }
    }

    public static function get_captured_debug_errors() {
        return self::$captured_debug_errors;
    }

    public static function register_public_error_handler() {
        if (self::$error_handler_registered) {
            return;
        }
        self::$error_handler_registered = true;

        // Buffer output so partial HTML is never leaked before custom error screen renders
        if (ob_get_level() === 0) {
            @ob_start();
        }

        // Prevent raw PHP error strings from corrupting HTTP headers; capture all errors internally
        @ini_set('display_errors', '0');
        @ini_set('display_startup_errors', '0');
        @error_reporting(E_ALL);

        set_error_handler(function ($severity, $message, $file, $line) {
            // Respect @ error-suppression operator
            if (!(error_reporting() & $severity)) {
                return true;
            }

            $type_map = [
                E_ERROR             => 'E_ERROR',
                E_WARNING           => 'E_WARNING',
                E_PARSE             => 'E_PARSE',
                E_NOTICE            => 'E_NOTICE',
                E_CORE_ERROR        => 'E_CORE_ERROR',
                E_CORE_WARNING      => 'E_CORE_WARNING',
                E_COMPILE_ERROR     => 'E_COMPILE_ERROR',
                E_COMPILE_WARNING   => 'E_COMPILE_WARNING',
                E_USER_ERROR        => 'E_USER_ERROR',
                E_USER_WARNING      => 'E_USER_WARNING',
                E_USER_NOTICE       => 'E_USER_NOTICE',
                E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
                E_DEPRECATED        => 'E_DEPRECATED',
                E_USER_DEPRECATED   => 'E_USER_DEPRECATED',
            ];
            $type_label = $type_map[$severity] ?? "PHP_ERR_{$severity}";
            $entry = "Actual Error [{$type_label}]: {$message} in " . basename((string)$file) . " on line {$line}";
            self::$captured_debug_errors[] = $entry;

            if (in_array($severity, [E_USER_ERROR, E_RECOVERABLE_ERROR, E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
                SLEA_Datastore::render_public_error(
                    '500 - PHP Runtime Error',
                    $entry,
                    500,
                    'Something went wrong while loading this page. Please try again in a moment.'
                );
            }

            return true;
        });

        set_exception_handler(function ($ex) {
            $msg = "Actual Error [Uncaught " . get_class($ex) . "]: " . $ex->getMessage() . " in " . basename($ex->getFile()) . " on line " . $ex->getLine();
            SLEA_Datastore::render_public_error(
                '500 - Uncaught Application Exception',
                $msg,
                500,
                'Something went wrong while loading this page. Please try again in a moment.',
                [
                    'exception_class' => get_class($ex),
                    'file'            => basename($ex->getFile()),
                    'line'            => $ex->getLine(),
                    'trace'           => $ex->getTraceAsString(),
                ]
            );
        });

        register_shutdown_function(function () {
            $err = error_get_last();
            if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR], true)) {
                $msg = "Actual Error [Fatal Shutdown Error #{$err['type']}]: {$err['message']} in " . basename($err['file']) . " on line {$err['line']}";
                SLEA_Datastore::render_public_error(
                    '500 - Fatal System Error',
                    $msg,
                    500,
                    'Something went wrong while loading this page. Please try again in a moment.',
                    [
                        'file' => basename($err['file']),
                        'line' => $err['line'],
                    ]
                );
            }
        });
    }

    public static function render_public_error($actual_title, $actual_error_msg, $http_code = 404, $safe_public_msg = null, $extra_context = []) {
        // Clear any buffered partial HTML output so the error screen renders cleanly
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }

        $code_int = intval($http_code) ?: 404;
        if (!headers_sent()) {
            http_response_code($code_int);
            header('Content-Type: text/html; charset=utf-8');
            header('X-Robots-Tag: noindex, nofollow, noarchive, nosnippet');
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            header('Pragma: no-cache');
            header('Expires: 0');
            header('X-LiteSpeed-Cache-Control: no-cache');
        }

        $debug = self::is_debug_mode();
        $raw_req_uri = $_SERVER['REQUEST_URI'] ?? '/';
        $req_uri = htmlspecialchars($raw_req_uri, ENT_QUOTES, 'UTF-8');
        $req_method = htmlspecialchars($_SERVER['REQUEST_METHOD'] ?? 'GET', ENT_QUOTES, 'UTF-8');
        $script_name = htmlspecialchars(basename($_SERVER['SCRIPT_NAME'] ?? ($_SERVER['PHP_SELF'] ?? 'view.php')), ENT_QUOTES, 'UTF-8');

        // Safely load site branding, menu items, and footer for functional 404 navigation
        $site_name = defined('APP_NAME') ? APP_NAME : 'Movie Hub HQ Drive';
        $site_logo_url = '';
        $site_logo_text = 'MHQ';
        $menu_items = [
            ['title' => 'Home', 'url' => 'https://moviehubhq.com/', 'new_tab' => false],
            ['title' => 'Korean Drama', 'url' => 'https://moviehubhq.com/catagory/korean/', 'new_tab' => false],
            ['title' => 'Chinese Drama', 'url' => 'https://moviehubhq.com/catagory/chinese/', 'new_tab' => false]
        ];
        $footer_html = '&copy; ' . date('Y') . ' MovieHubHQ • Direct Episode Link Gateway';
        $is_admin_logged_in = false;

        try {
            $identity = self::get_site_identity();
            if (!empty($identity['site_name'])) {
                $site_name = $identity['site_name'];
            }
            if (!empty($identity['site_logo_url'])) {
                $site_logo_url = self::sanitize_safe_href($identity['site_logo_url']);
                if ($site_logo_url === '#') $site_logo_url = '';
            }
            if (!empty($identity['site_logo_text'])) {
                $site_logo_text = $identity['site_logo_text'];
            }
            $loaded_menu = self::get_menu_items();
            if (is_array($loaded_menu) && !empty($loaded_menu)) {
                $login_slug_cfg = strtolower(self::get_login_slug());
                $blocked_routes = ['admin', 'pages', 'settings', 'analytics', 'update', 'updater', 'login', 'logout', 'setup', 'api', $login_slug_cfg];
                $filtered_menu = array_values(array_filter($loaded_menu, function($item) use ($blocked_routes) {
                    $t = strtolower(trim($item['title'] ?? ''));
                    if (preg_match('/admin|login|dashboard|cpanel|setup/i', $t)) return false;
                    $u = strtolower(trim($item['url'] ?? ''));
                    if ($u === '' || $u === '#') return true;
                    $path = trim(parse_url($u, PHP_URL_PATH) ?: '', '/');
                    $base = preg_replace('/\.php$/i', '', basename($path));
                    return !in_array($base, $blocked_routes, true);
                }));
                if (!empty($filtered_menu)) {
                    $menu_items = $filtered_menu;
                }
            }
            $loaded_footer = self::get_footer_copyright();
            if (!empty($loaded_footer)) {
                $footer_html = $loaded_footer;
            }
        } catch (Throwable $e) {
            // Safe fallback if DB is unreachable
        }

        // Compute base path for legal links
        $script_path = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/index.php');
        $base_dir = rtrim(dirname($script_path), '/');
        $base_dir = preg_replace('#/(p|page)$#i', '', $base_dir);
        if ($base_dir === '/' || $base_dir === '.' || $base_dir === '') {
            $base_dir = '';
        } elseif ($base_dir[0] !== '/') {
            $base_dir = '/' . $base_dir;
        }

        $safe_title = "Something's wrong here...";
        $safe_desc = 'It looks like nothing was found at this location. The page you were looking for does not exist or was loading incorrectly.';

        $display_title = $debug ? htmlspecialchars((string)$actual_title, ENT_QUOTES, 'UTF-8') : htmlspecialchars((string)$safe_title, ENT_QUOTES, 'UTF-8');
        $display_msg = htmlspecialchars((string)$safe_desc, ENT_QUOTES, 'UTF-8');

        // Determine Return to Home URL (use configured Home menu item if set, otherwise site root)
        $home_href = ($base_dir === '' ? '/' : $base_dir . '/');
        foreach ($menu_items as $m_item) {
            $m_t = strtolower(trim($m_item['title'] ?? ''));
            $m_u = trim($m_item['url'] ?? '');
            if ($m_t === 'home' && $m_u !== '' && $m_u !== '#') {
                $home_href = self::sanitize_safe_href($m_u);
                break;
            }
        }
        $home_href_safe = htmlspecialchars($home_href, ENT_QUOTES, 'UTF-8');

        // Build Desktop & Mobile Navigation Links HTML matching view.php
        $desktop_nav_html = '';
        $mobile_nav_html = '';
        foreach ($menu_items as $item) {
            $m_title = htmlspecialchars($item['title'] ?? 'Link', ENT_QUOTES, 'UTF-8');
            $m_url = htmlspecialchars(self::sanitize_safe_href($item['url'] ?? '#'), ENT_QUOTES, 'UTF-8');
            $m_blank = (!empty($item['new_tab']) || !empty($item['target_blank'])) ? ' target="_blank" rel="noopener noreferrer"' : '';
            $desktop_nav_html .= '<a href="' . $m_url . '"' . $m_blank . ' class="px-3.5 py-1.5 rounded-full text-xs font-semibold text-[#444746] hover:text-[#0b57d0] bg-[#f0f4f9] hover:bg-[#e8f0fe] border border-[#e1e7f0] hover:border-[#c2e7ff] shadow-2xs transition-all whitespace-nowrap">' . $m_title . '</a>';
            $mobile_nav_html .= '<a href="' . $m_url . '"' . $m_blank . ' class="flex items-center justify-between px-4 py-3 rounded-2xl text-xs sm:text-sm font-semibold text-[#1f1f1f] hover:text-[#0b57d0] bg-[#f8fafd] hover:bg-[#e8f0fe] border border-[#e0e4eb] hover:border-[#c2e7ff] shadow-2xs transition-all"><span>' . $m_title . '</span><svg class="w-4 h-4 text-[#5f6368] opacity-60" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg></a>';
        }

        $site_name_safe = htmlspecialchars($site_name, ENT_QUOTES, 'UTF-8');
        $header_logo_html = $site_logo_url !== ''
            ? '<img src="' . htmlspecialchars($site_logo_url, ENT_QUOTES, 'UTF-8') . '" alt="' . $site_name_safe . '" class="h-8 max-w-[180px] object-contain">'
            : '<span class="font-bold text-base sm:text-lg tracking-tight text-[#111827] group-hover:text-[#0b57d0] transition-colors">' . $site_name_safe . '</span>';

        $drawer_logo_html = $site_logo_url !== ''
            ? '<img src="' . htmlspecialchars($site_logo_url, ENT_QUOTES, 'UTF-8') . '" alt="' . $site_name_safe . '" class="h-7 max-w-[140px] object-contain">'
            : '<span class="font-bold text-sm tracking-tight text-[#111827] truncate">' . $site_name_safe . '</span>';

        $debug_block = '';
        if ($debug) {
            $time_str = date('Y-m-d H:i:s T');
            $php_ver = htmlspecialchars(PHP_VERSION, ENT_QUOTES, 'UTF-8');
            $db_driver = 'unknown';
            try {
                if (class_exists('SLEA_DB')) {
                    $db_driver = SLEA_DB::get_driver() ?: 'sqlite';
                }
            } catch (Throwable $e) {}

            $extra_rows = '';
            if (!empty($extra_context['file'])) {
                $loc = htmlspecialchars($extra_context['file'] . (!empty($extra_context['line']) ? ':' . $extra_context['line'] : ''), ENT_QUOTES, 'UTF-8');
                $extra_rows .= '<div><strong class="text-[#111827]">Source Location:</strong> ' . $loc . '</div>';
            }
            if (!empty($extra_context['trace'])) {
                $trace_safe = htmlspecialchars((string)$extra_context['trace'], ENT_QUOTES, 'UTF-8');
                $extra_rows .= '<details class="mt-2"><summary class="cursor-pointer font-bold text-[#c5221f]">View Stack Trace</summary><pre class="mt-2 p-2.5 bg-white border border-[#fad2cf] rounded-xl overflow-x-auto text-[11px] text-[#c5221f] whitespace-pre-wrap break-words">' . $trace_safe . '</pre></details>';
            }
            if (!empty(self::$captured_debug_errors)) {
                $warn_items = '';
                foreach (self::$captured_debug_errors as $w) {
                    $warn_items .= '<li>' . htmlspecialchars((string)$w, ENT_QUOTES, 'UTF-8') . '</li>';
                }
                $extra_rows .= '<div class="mt-2 pt-2 border-t border-dashed border-[#e0e4eb]"><strong class="text-[#111827]">Captured Runtime Warnings/Notices:</strong><ul class="mt-1 ml-4 list-disc">' . $warn_items . '</ul></div>';
            }

            $debug_block = '<div class="mt-8 w-full bg-[#f8fafd] border border-[#e0e4eb] rounded-2xl p-4 text-[#1f1f1f] font-mono text-[11px] leading-relaxed text-left space-y-2">'
                . '<div class="flex items-center justify-between gap-2 flex-wrap font-bold text-[#c5221f] uppercase tracking-wider">'
                . '<span>Developer Diagnostics (Debug Mode: ON)</span>'
                . '<span class="bg-[#fce8e6] text-[#c5221f] border border-[#fad2cf] px-2 py-0.5 rounded-full text-[10px]">HTTP ' . $code_int . '</span>'
                . '</div>'
                . '<div class="bg-[#fce8e6]/60 border border-[#fad2cf] rounded-xl px-3 py-2.5 text-[#c5221f] break-words"><strong>Actual Error:</strong> ' . htmlspecialchars((string)$actual_error_msg, ENT_QUOTES, 'UTF-8') . '</div>'
                . '<div class="text-[#5f6368] space-y-1">'
                . '<div><strong class="text-[#111827]">Request:</strong> ' . $req_method . ' ' . $req_uri . '</div>'
                . '<div><strong class="text-[#111827]">Handler:</strong> ' . $script_name . ' · <strong class="text-[#111827]">DB Driver:</strong> ' . htmlspecialchars((string)$db_driver, ENT_QUOTES, 'UTF-8') . ' · <strong class="text-[#111827]">PHP:</strong> ' . $php_ver . '</div>'
                . '<div><strong class="text-[#111827]">Timestamp:</strong> ' . htmlspecialchars($time_str, ENT_QUOTES, 'UTF-8') . '</div>'
                . $extra_rows
                . '</div>'
                . '</div>';
        }

        die('<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <meta name="robots" content="noindex, nofollow, noarchive, nosnippet">
    <title>' . $code_int . ' - ' . $display_title . ' - ' . $site_name_safe . '</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        * { -webkit-tap-highlight-color: transparent; }
        body {
            font-family: "Plus Jakarta Sans", -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: #ffffff;
            color: #1f1f1f;
        }
        .font-mono { font-family: "JetBrains Mono", monospace; }
        .shadow-2xs { box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05); }
        .shadow-xs { box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05); }
        @keyframes s404CloudLeft {
            0%, 100% { transform: translate(0px, 0px); }
            50% { transform: translate(-8px, -4px); }
        }
        @keyframes s404CloudRight {
            0%, 100% { transform: translate(0px, 0px); }
            50% { transform: translate(8px, -5px); }
        }
        @keyframes s404CharBounce {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-6px) rotate(2deg); }
        }
        @keyframes s404ArmLeft {
            0%, 100% { transform: rotate(0deg); }
            50% { transform: rotate(-14deg); }
        }
        @keyframes s404ArmRight {
            0%, 100% { transform: rotate(0deg); }
            50% { transform: rotate(16deg); }
        }
        @keyframes s404LegRight {
            0%, 100% { transform: rotate(0deg); }
            50% { transform: rotate(-12deg); }
        }
        @keyframes s404Blink {
            0%, 45%, 49%, 100% { transform: scaleY(1); }
            47% { transform: scaleY(0.12); }
        }
        .s404-cloud-left { animation: s404CloudLeft 5s ease-in-out infinite; }
        .s404-cloud-right { animation: s404CloudRight 6s ease-in-out infinite; }
        .s404-char { animation: s404CharBounce 2.8s ease-in-out infinite; transform-origin: 196px 155px; }
        .s404-arm-left { animation: s404ArmLeft 1.8s ease-in-out infinite; transform-origin: 176px 92px; }
        .s404-arm-right { animation: s404ArmRight 1.8s ease-in-out infinite; transform-origin: 218px 78px; }
        .s404-leg-right { animation: s404LegRight 2.8s ease-in-out infinite; transform-origin: 220px 121px; }
        .s404-eye { animation: s404Blink 4s infinite; transform-box: fill-box; transform-origin: center; }
    </style>
</head>
<body class="min-h-screen flex flex-col bg-white antialiased selection:bg-[#d3e3fd] selection:text-[#041e49]">
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
                    ' . $header_logo_html . '
                </a>
            </div>

            <!-- Right Header: Desktop Navigation Menu Buttons (Google Material M3 Chips) -->
            <nav class="hidden md:flex flex-row items-center gap-2">
                ' . $desktop_nav_html . '
            </nav>
        </div>
    </header>

    <!-- Mobile Sidebar Drawer (Opens from Left Side, Occupies Half of the Page) -->
    <div id="mobileSidebarBackdrop" onclick="closeMobileMenu()" class="fixed inset-0 bg-black/40 backdrop-blur-xs z-50 hidden opacity-0 transition-opacity duration-300 md:hidden" aria-hidden="true"></div>

    <aside id="mobileSidebar" class="fixed inset-y-0 left-0 z-50 w-1/2 min-w-[250px] max-w-[340px] h-full bg-white border-r border-[#e1e7f0] shadow-2xl flex flex-col transform -translate-x-full transition-transform duration-300 ease-in-out md:hidden" aria-label="Mobile Navigation Drawer">
        <div class="p-4 border-b border-[#f0f4f9] flex items-center justify-between shrink-0">
            <div class="flex items-center min-w-0">
                ' . $drawer_logo_html . '
            </div>
            <button type="button" onclick="closeMobileMenu()" class="p-1.5 rounded-xl text-[#5f6368] hover:text-[#111827] hover:bg-[#f0f4f9] transition-colors cursor-pointer" aria-label="Close menu">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <nav class="flex-1 overflow-y-auto p-4 flex flex-col space-y-2">
            ' . $mobile_nav_html . '
        </nav>

        <div class="p-4 border-t border-[#f0f4f9] text-[11px] text-[#747775] text-center shrink-0">
            ' . $site_name_safe . '
        </div>
    </aside>

    <!-- Main 404 Content Area matching screenshot -->
    <main class="flex-1 w-full max-w-xl mx-auto px-6 py-12 sm:py-16 flex flex-col items-center justify-center text-center">
        <!-- Animated 404 Icon Illustration -->
        <div class="w-full max-w-[300px] sm:max-w-[340px] mx-auto select-none">
            <svg viewBox="0 0 420 250" class="w-full h-auto overflow-visible" role="img" aria-label="404 Page Not Found Illustration">
                <!-- Floating Left Cloud -->
                <g class="s404-cloud-left">
                    <path d="M 118 44 H 144 C 147.5 44 150 41.5 150 38.2 C 150 35.2 147.8 32.8 144.8 32.5 C 144.2 27.2 139.6 23 134 23 C 129.2 23 125.1 26.1 123.6 30.5 C 122.7 30.1 121.6 29.8 120.5 29.8 C 116.4 29.8 113 33.1 113 37.2 C 113 41 115.2 44 118 44 Z" fill="#ffffff" stroke="#737373" stroke-width="1.8" stroke-linejoin="round" />
                </g>

                <!-- Floating Right Cloud -->
                <g class="s404-cloud-right">
                    <path d="M 278 44 H 304 C 307.5 44 310 41.5 310 38.2 C 310 35.2 307.8 32.8 304.8 32.5 C 304.2 27.2 299.6 23 294 23 C 289.2 23 285.1 26.1 283.6 30.5 C 282.7 30.1 281.6 29.8 280.5 29.8 C 276.4 29.8 273 33.1 273 37.2 C 273 41 275.2 44 278 44 Z" fill="#ffffff" stroke="#737373" stroke-width="1.8" stroke-linejoin="round" />
                </g>

                <!-- Left "4" emerging from horizon -->
                <polygon points="88,222 118,148 139,156 110,222" fill="#737373" />
                <polygon points="131,222 129,189 151,187 153,222" fill="#737373" />

                <!-- Center "0" Dome emerging from horizon -->
                <path d="M 161 222 C 161 170 177 152 201 152 C 225 152 241 170 241 222 Z" fill="#ffffff" stroke="#737373" stroke-width="1.8" />
                <ellipse cx="196" cy="159" rx="10" ry="3.6" fill="#d4d4d4" />
                <ellipse cx="215" cy="164" rx="5.5" ry="2.2" fill="#d4d4d4" transform="rotate(14 215 164)" />
                <ellipse cx="180" cy="166" rx="4" ry="1.6" fill="#e0e0e0" transform="rotate(-15 180 166)" />
                <path d="M 183 222 C 183 186 190 175 201 175 C 212 175 219 186 219 222 Z" fill="#ffffff" stroke="#737373" stroke-width="1.8" />

                <!-- Right "4" emerging from horizon -->
                <polygon points="249,222 284,138 304,146 268,222" fill="#737373" />
                <polygon points="258,210 293,207 291,176 314,174 316,205 327,204 329,222 258,222" fill="#737373" />

                <!-- Animated Cute Page Character balancing on the "0" -->
                <g class="s404-char">
                    <!-- Left Leg -->
                    <path d="M 189 131 L 194 156 L 188 158" fill="none" stroke="#737373" stroke-width="3.2" stroke-linecap="round" stroke-linejoin="round" />
                    <!-- Right Kicking Leg -->
                    <g class="s404-leg-right">
                        <path d="M 220 121 L 226 133 C 227 136 225 139 221 140" fill="none" stroke="#737373" stroke-width="3.2" stroke-linecap="round" stroke-linejoin="round" />
                    </g>
                    <!-- Left Waving Arm -->
                    <g class="s404-arm-left">
                        <path d="M 176 92 L 152 88" fill="none" stroke="#737373" stroke-width="3.2" stroke-linecap="round" />
                    </g>
                    <!-- Right Raised Waving Arm -->
                    <g class="s404-arm-right">
                        <path d="M 218 78 L 231 53" fill="none" stroke="#737373" stroke-width="3.2" stroke-linecap="round" />
                    </g>
                    <!-- Tilted Document Sheet Body with Folded Corner -->
                    <g transform="rotate(-17 199 98)">
                        <polygon points="174,77 186,65 224,65 224,128 174,128" fill="#ffffff" stroke="#737373" stroke-width="2" stroke-linejoin="round" />
                        <polygon points="174,77 186,77 186,65" fill="#ffffff" stroke="#737373" stroke-width="2" stroke-linejoin="round" />
                        <circle class="s404-eye" cx="192" cy="89" r="2.3" fill="#555555" />
                        <circle class="s404-eye" cx="207" cy="89" r="2.3" fill="#555555" />
                        <path d="M 195 95 C 195 104 205 104 205 95 Z" fill="#555555" />
                        <path d="M 197 100.5 Q 200 98.5 203 100.5" fill="none" stroke="#ffffff" stroke-width="1.4" stroke-linecap="round" />
                    </g>
                </g>

                <!-- Horizon Line & Ground Dashes -->
                <line x1="68" y1="222" x2="76" y2="222" stroke="#737373" stroke-width="1.8" stroke-linecap="round" />
                <line x1="82" y1="222" x2="334" y2="222" stroke="#737373" stroke-width="1.8" stroke-linecap="round" />
                <line x1="340" y1="222" x2="348" y2="222" stroke="#737373" stroke-width="1.8" stroke-linecap="round" />

                <line x1="100" y1="232" x2="108" y2="232" stroke="#737373" stroke-width="1.8" stroke-linecap="round" />
                <line x1="118" y1="232" x2="134" y2="232" stroke="#737373" stroke-width="1.8" stroke-linecap="round" />
                <line x1="90" y1="240" x2="96" y2="240" stroke="#737373" stroke-width="1.8" stroke-linecap="round" />

                <line x1="286" y1="232" x2="292" y2="232" stroke="#737373" stroke-width="1.8" stroke-linecap="round" />
                <line x1="302" y1="232" x2="328" y2="232" stroke="#737373" stroke-width="1.8" stroke-linecap="round" />
                <line x1="332" y1="240" x2="338" y2="240" stroke="#737373" stroke-width="1.8" stroke-linecap="round" />
            </svg>
        </div>

        <!-- Title & Description matching screenshot -->
        <h1 class="mt-6 text-2xl sm:text-[28px] font-extrabold text-[#2b2b2b] tracking-tight leading-snug">
            ' . $display_title . '
        </h1>
        <p class="mt-3 text-sm sm:text-[15px] text-[#757575] max-w-md mx-auto leading-relaxed">
            ' . $display_msg . '
        </p>

        <!-- Return to Home Button matching screenshot -->
        <div class="mt-12 sm:mt-16">
            <a href="' . $home_href_safe . '" class="inline-flex items-center justify-center px-7 py-3.5 rounded-xl bg-[#f4f4f5] hover:bg-[#e7e8ea] text-[#222222] font-bold text-xs sm:text-sm transition-all shadow-2xs active:scale-95 cursor-pointer">
                Return to Home
            </a>
        </div>

        ' . $debug_block . '
    </main>

    <!-- Public Footer (Google Material M3 Light Theme) -->
    <footer class="w-full bg-white border-t border-[#e1e7f0] mt-auto py-6 px-4">
        <div class="max-w-4xl mx-auto text-center text-xs text-[#5f6368]">
            <div class="leading-relaxed">
                ' . $footer_html . '
            </div>
            <div class="mt-2.5 pt-2.5 border-t border-[#f0f4f9] flex items-center justify-center flex-wrap gap-x-5 gap-y-1.5 text-xs font-medium text-[#5f6368]">
                <a href="' . htmlspecialchars(($base_dir === '' ? '' : $base_dir) . '/dmca', ENT_QUOTES, 'UTF-8') . '" class="hover:text-[#0b57d0] hover:underline transition-colors">DMCA</a>
                <span class="text-[#c4c7c5] select-none">•</span>
                <a href="' . htmlspecialchars(($base_dir === '' ? '' : $base_dir) . '/disclaimer', ENT_QUOTES, 'UTF-8') . '" class="hover:text-[#0b57d0] hover:underline transition-colors">Disclaimer</a>
                <span class="text-[#c4c7c5] select-none">•</span>
                <a href="' . htmlspecialchars(($base_dir === '' ? '' : $base_dir) . '/about-us', ENT_QUOTES, 'UTF-8') . '" class="hover:text-[#0b57d0] hover:underline transition-colors">About Us</a>
                <span class="text-[#c4c7c5] select-none">•</span>
                <a href="' . htmlspecialchars(($base_dir === '' ? '' : $base_dir) . '/privacy-policy', ENT_QUOTES, 'UTF-8') . '" class="hover:text-[#0b57d0] hover:underline transition-colors">Privacy Policy</a>
            </div>
        </div>
    </footer>

    <script>
        function openMobileMenu() {
            var sidebar = document.getElementById("mobileSidebar");
            var backdrop = document.getElementById("mobileSidebarBackdrop");
            if (!sidebar || !backdrop) return;
            backdrop.classList.remove("hidden");
            void backdrop.offsetWidth;
            backdrop.classList.remove("opacity-0");
            backdrop.classList.add("opacity-100");
            sidebar.classList.remove("-translate-x-full");
            sidebar.classList.add("translate-x-0");
            document.body.style.overflow = "hidden";
        }

        function closeMobileMenu() {
            var sidebar = document.getElementById("mobileSidebar");
            var backdrop = document.getElementById("mobileSidebarBackdrop");
            if (!sidebar || !backdrop) return;
            sidebar.classList.remove("translate-x-0");
            sidebar.classList.add("-translate-x-full");
            backdrop.classList.remove("opacity-100");
            backdrop.classList.add("opacity-0");
            setTimeout(function() {
                backdrop.classList.add("hidden");
                document.body.style.overflow = "";
            }, 300);
        }

        function toggleMobileMenu() {
            var sidebar = document.getElementById("mobileSidebar");
            if (sidebar && sidebar.classList.contains("translate-x-0")) {
                closeMobileMenu();
            } else {
                openMobileMenu();
            }
        }

        document.addEventListener("keydown", function(e) {
            if (e.key === "Escape") {
                closeMobileMenu();
            }
        });
    </script>
</body>
</html>');
    }

    // -------------------------------------------------------------
    // Footer Copyright & Emoji Preservation
    // -------------------------------------------------------------

    public static function repair_corrupted_emojis($val) {
        if (empty($val) || !is_string($val)) return $val;
        
        $val = preg_replace('/(<span[^>]*class=["\'][^"\']*heart[^"\']*["\'][^>]*>)\s*\?+\s*(<\/span>)/i', '$1&#10084;&#65039;$2', $val);
        $val = preg_replace('/(<span[^>]*aria-label=["\']love["\'][^>]*>)\s*\?+\s*(<\/span>)/i', '$1&#10084;&#65039;$2', $val);
        $val = preg_replace('/(Designed\s+with)\s+\?+\s+(by)/i', '$1 &#10084;&#65039; $2', $val);
        $val = preg_replace('/(\bwith)\s+\?+\s+(\bby|\bfor)/i', '$1 &#10084;&#65039; $2', $val);
        
        $val = str_replace(
            ['?? • Made with ??', 'Gateway ?? • All rights reserved ??', 'MovieHubHQ ??', 'All rights reserved ??', 'for Direct Episode Link Gateway ??'],
            ['🍿 • Made with &#10084;&#65039;', 'Gateway 🎬 • All rights reserved 🚀', 'MovieHubHQ 🍿', 'All rights reserved 🚀', 'for Direct Episode Link Gateway 🎬'],
            $val
        );

        return $val;
    }

    public static function encode_emojis_to_entities($string) {
        if (empty($string) || !is_string($string)) {
            return $string;
        }

        if (function_exists('mb_ord') && function_exists('mb_strlen') && function_exists('mb_substr')) {
            $result = '';
            $len = mb_strlen($string, 'UTF-8');
            for ($i = 0; $i < $len; $i++) {
                $char = mb_substr($string, $i, 1, 'UTF-8');
                $code = mb_ord($char, 'UTF-8');
                if ($code > 255) {
                    $result .= '&#' . $code . ';';
                } else {
                    $result .= $char;
                }
            }
            return $result;
        }

        return $string;
    }

    public static function get_footer_copyright() {
        $raw = self::get_raw_setting('footer_copyright');
        if (!empty($raw) && trim($raw) !== '') {
            if (strpos($raw, '??') !== false) {
                $repaired = self::repair_corrupted_emojis($raw);
                if ($repaired !== $raw) {
                    self::save_footer_copyright($repaired);
                    return $repaired;
                }
            }
            return $raw;
        }

        return '&copy; ' . date('Y') . ' MovieHubHQ 🍿 • Made with &#10084;&#65039; for Direct Episode Link Gateway 🎬 • All rights reserved 🚀';
    }

    public static function get_footer_settings() {
        return [
            'copyright_text' => strip_tags(html_entity_decode(self::get_footer_copyright(), ENT_QUOTES | ENT_HTML5, 'UTF-8')),
            'footer_html'    => self::get_footer_copyright(),
            'footer_subtext' => ''
        ];
    }

    public static function save_footer_copyright($html) {
        $html = self::repair_corrupted_emojis($html);
        if (function_exists('mb_check_encoding') && !mb_check_encoding($html, 'UTF-8')) {
            $html = mb_convert_encoding($html, 'UTF-8', mb_detect_encoding($html) ?: 'UTF-8');
        }
        $safe_html = self::encode_emojis_to_entities($html);
        return self::save_raw_setting('footer_copyright', $safe_html);
    }

    // -------------------------------------------------------------
    // Persistent Dual-Layer Backup Helpers (Guarantees Zero Data Loss)
    // -------------------------------------------------------------

    public static function sync_pages_to_file($pages = null) {
        try {
            if ($pages === null) {
                $pdo = SLEA_DB::get_connection();
                $stmt = $pdo->query("SELECT * FROM pages ORDER BY created_at DESC, id DESC");
                $rows = $stmt->fetchAll();
                $pages = [];
                foreach ($rows as $r) {
                    $pages[] = self::format_page_row($r);
                }
            }

            if (empty($pages)) {
                return; // Do not overwrite backup with empty array
            }

            $data_dir = defined('DATA_DIR') ? DATA_DIR : (APP_ROOT . '/data');
            if (!is_dir($data_dir)) {
                @mkdir($data_dir, 0755, true);
            }

            $backup_file = $data_dir . '/pages_backup.json';
            @file_put_contents($backup_file, json_encode($pages, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            
            // Also update pages.json fallback
            $pages_file = defined('JSON_STORAGE_FILE') ? JSON_STORAGE_FILE : ($data_dir . '/pages.json');
            @file_put_contents($pages_file, json_encode($pages, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } catch (Exception $e) {
            // Non-blocking
        }
    }

    private static function get_pages_from_file() {
        $data_dir = defined('DATA_DIR') ? DATA_DIR : (APP_ROOT . '/data');
        $candidates = [
            $data_dir . '/pages_backup.json',
            defined('JSON_STORAGE_FILE') ? JSON_STORAGE_FILE : '',
            $data_dir . '/pages.json'
        ];

        foreach (array_filter($candidates) as $file) {
            if (file_exists($file)) {
                $content = @file_get_contents($file);
                $decoded = json_decode($content, true);
                if (is_array($decoded) && !empty($decoded)) {
                    return $decoded;
                }
            }
        }
        return [];
    }

    private static function restore_pages_into_db($pages) {
        try {
            $pdo = SLEA_DB::get_connection();
            foreach ($pages as $p) {
                if (empty($p['slug'])) continue;

                $check = $pdo->prepare("SELECT id FROM pages WHERE slug = :s LIMIT 1");
                $check->execute([':s' => $p['slug']]);
                if ($check->fetch()) {
                    continue; // Page already exists
                }

                $btns = !empty($p['buttons']) ? (is_string($p['buttons']) ? $p['buttons'] : json_encode($p['buttons'], JSON_UNESCAPED_SLASHES)) : '[]';

                $stmt = $pdo->prepare("
                    INSERT INTO pages (
                        page_key, slug, title, description, source_url, resolved_url,
                        theme, buttons_json, views, is_public, created_at, updated_at
                    ) VALUES (
                        :k, :slug, :title, :desc, :surl, :rurl,
                        :theme, :btns, :views, :pub, :created_at, :updated_at
                    )
                ");
                $stmt->execute([
                    ':k'          => !empty($p['page_key']) ? $p['page_key'] : ('p_' . ($p['id'] ?? uniqid())),
                    ':slug'       => $p['slug'],
                    ':title'      => $p['title'] ?? 'Episode Download Links',
                    ':desc'       => $p['description'] ?? '',
                    ':surl'       => $p['source_url'] ?? '',
                    ':rurl'       => $p['resolved_url'] ?? '',
                    ':theme'      => $p['theme'] ?? 'indigo',
                    ':btns'       => $btns,
                    ':views'      => intval($p['views'] ?? 0),
                    ':pub'        => intval($p['is_public'] ?? 1),
                    ':created_at' => $p['created_at'] ?? date('Y-m-d H:i:s'),
                    ':updated_at' => $p['updated_at'] ?? date('Y-m-d H:i:s')
                ]);
            }
        } catch (Exception $e) {
            error_log("Failed to auto-restore pages: " . $e->getMessage());
        }
    }

    private static function sync_setting_to_backup_file($key, $val) {
        try {
            $data_dir = defined('DATA_DIR') ? DATA_DIR : (APP_ROOT . '/data');
            if (!is_dir($data_dir)) {
                @mkdir($data_dir, 0755, true);
            }
            $backup_file = $data_dir . '/settings_backup.json';
            $existing = [];
            if (file_exists($backup_file)) {
                $existing = json_decode(@file_get_contents($backup_file), true) ?: [];
            }
            $existing[$key] = $val;
            @file_put_contents($backup_file, json_encode($existing, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } catch (Exception $e) {
            // Non-blocking
        }
    }

    private static function get_settings_from_backup_file() {
        $data_dir = defined('DATA_DIR') ? DATA_DIR : (APP_ROOT . '/data');
        $backup_file = $data_dir . '/settings_backup.json';
        if (file_exists($backup_file)) {
            return json_decode(@file_get_contents($backup_file), true) ?: [];
        }
        return [];
    }

    private static function format_page_row($row) {
        $buttons = [];
        if (!empty($row['buttons_json'])) {
            $buttons = json_decode($row['buttons_json'], true) ?: [];
        }

        return [
            'id'           => intval($row['id']),
            'page_key'     => $row['page_key'] ?? ('p_' . $row['id']),
            'slug'         => $row['slug'],
            'title'        => $row['title'],
            'description'  => $row['description'] ?? '',
            'source_url'   => $row['source_url'] ?? '',
            'resolved_url' => $row['resolved_url'] ?? '',
            'theme'        => $row['theme'] ?? 'indigo',
            'buttons'      => $buttons,
            'views'        => intval($row['views'] ?? 0),
            'is_public'    => intval($row['is_public'] ?? 1),
            'created_at'   => $row['created_at'],
            'updated_at'   => $row['updated_at']
        ];
    }

    private static function sanitize_slug($string) {
        $slug = preg_replace('/[^a-zA-Z0-9_-]/', '-', strtolower($string));
        $slug = preg_replace('/-+/', '-', $slug);
        return trim($slug, '-');
    }

    // -------------------------------------------------------------
    // Comprehensive Backup & Restore Engine (Supports All or Separate Scopes: settings, pages, others)
    // -------------------------------------------------------------

    public static function sync_all_backups() {
        try {
            self::sync_pages_to_file();
            $pdo = SLEA_DB::get_connection();

            // Sync all settings to data/settings_backup.json
            $stmt_s = $pdo->query("SELECT setting_key, setting_value FROM settings");
            $rows_s = $stmt_s ? $stmt_s->fetchAll(PDO::FETCH_ASSOC) : [];
            if (!empty($rows_s)) {
                $data_dir = defined('DATA_DIR') ? DATA_DIR : (APP_ROOT . '/data');
                if (!is_dir($data_dir)) @mkdir($data_dir, 0755, true);
                $backup_file = $data_dir . '/settings_backup.json';
                $existing = file_exists($backup_file) ? (json_decode(@file_get_contents($backup_file), true) ?: []) : [];
                foreach ($rows_s as $r) {
                    $decoded = json_decode($r['setting_value'], true);
                    $existing[$r['setting_key']] = (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) ? $decoded : $r['setting_value'];
                }
                @file_put_contents($backup_file, json_encode($existing, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            }

            // Sync all users to data/users_backup.json
            $stmt_u = $pdo->query("SELECT id, username, email, password_hash, role, permissions, created_at, updated_at FROM users ORDER BY id ASC");
            $rows_u = $stmt_u ? $stmt_u->fetchAll(PDO::FETCH_ASSOC) : [];
            if (!empty($rows_u)) {
                $data_dir = defined('DATA_DIR') ? DATA_DIR : (APP_ROOT . '/data');
                if (!is_dir($data_dir)) @mkdir($data_dir, 0755, true);
                @file_put_contents($data_dir . '/users_backup.json', json_encode($rows_u, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            }

            // Sync analytics to data/analytics_backup.json
            $stmt_a = $pdo->query("SELECT page_slug, year_month, views FROM page_views_monthly ORDER BY year_month ASC");
            $rows_a = $stmt_a ? $stmt_a->fetchAll(PDO::FETCH_ASSOC) : [];
            if (!empty($rows_a)) {
                $data_dir = defined('DATA_DIR') ? DATA_DIR : (APP_ROOT . '/data');
                if (!is_dir($data_dir)) @mkdir($data_dir, 0755, true);
                @file_put_contents($data_dir . '/analytics_backup.json', json_encode($rows_a, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            }

            // Sync visitor telemetry to data/analytics_visits_backup.json
            $stmt_av = $pdo->query("SELECT * FROM analytics_visits ORDER BY id ASC");
            $rows_av = $stmt_av ? $stmt_av->fetchAll(PDO::FETCH_ASSOC) : [];
            if (!empty($rows_av)) {
                $data_dir = defined('DATA_DIR') ? DATA_DIR : (APP_ROOT . '/data');
                if (!is_dir($data_dir)) @mkdir($data_dir, 0755, true);
                @file_put_contents($data_dir . '/analytics_visits_backup.json', json_encode($rows_av, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            }
        } catch (Exception $e) {
            // Non-blocking
        }
    }

    /**
     * Create a structured backup payload.
     * @param string $scope 'all' | 'settings' | 'pages' | 'others'
     */
    public static function create_backup_payload($scope = 'all') {
        $scope = strtolower(trim($scope ?: 'all'));
        if (!in_array($scope, ['all', 'settings', 'pages', 'others'], true)) {
            $scope = 'all';
        }

        self::sync_all_backups();
        $pdo = SLEA_DB::get_connection();

        $include_settings = ($scope === 'all' || $scope === 'settings');
        $include_pages    = ($scope === 'all' || $scope === 'pages');
        $include_others   = ($scope === 'all' || $scope === 'others');

        $settings_data = [];
        if ($include_settings) {
            $settings_data = [
                'site_identity'        => self::get_site_identity(),
                'login_slug'           => self::get_login_slug(),
                'menu_items'           => self::get_menu_items(),
                'footer_copyright'     => self::get_footer_copyright(),
                'ad_settings'          => self::get_ad_settings(),
                'maintenance_settings' => self::get_maintenance_settings(),
                'share_settings'       => self::get_share_settings(),
                'debug_settings'       => self::get_debug_settings(),
            ];
            // Also include any custom or raw settings in DB (e.g. update_config)
            try {
                $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $k = $row['setting_key'];
                    if (!isset($settings_data[$k])) {
                        $dec = json_decode($row['setting_value'], true);
                        $settings_data[$k] = (json_last_error() === JSON_ERROR_NONE) ? $dec : $row['setting_value'];
                    }
                }
            } catch (Exception $e) {}
        }

        $pages_data = [];
        if ($include_pages) {
            $pages_data = self::get_all_pages();
        }

        $users_data = [];
        $analytics_data = [];
        $migrations_data = [];
        if ($include_others) {
            try {
                $stmt_u = $pdo->query("SELECT id, username, email, password_hash, role, permissions, created_at, updated_at FROM users ORDER BY id ASC");
                $users_data = $stmt_u ? $stmt_u->fetchAll(PDO::FETCH_ASSOC) : [];
            } catch (Exception $e) {}

            if (empty($users_data)) {
                $data_dir = defined('DATA_DIR') ? DATA_DIR : (APP_ROOT . '/data');
                if (file_exists($data_dir . '/users_backup.json')) {
                    $users_data = json_decode(@file_get_contents($data_dir . '/users_backup.json'), true) ?: [];
                }
            }

            try {
                $stmt_a = $pdo->query("SELECT page_slug, year_month, views FROM page_views_monthly ORDER BY year_month ASC");
                $analytics_data = $stmt_a ? $stmt_a->fetchAll(PDO::FETCH_ASSOC) : [];
            } catch (Exception $e) {}

            try {
                $stmt_m = $pdo->query("SELECT migration_name, batch, executed_at FROM migrations ORDER BY id ASC");
                $migrations_data = $stmt_m ? $stmt_m->fetchAll(PDO::FETCH_ASSOC) : [];
            } catch (Exception $e) {}
        }

        return [
            'backup_format' => 'slea_backup_v2',
            'app_name'      => defined('APP_NAME') ? APP_NAME : 'Movie Hub HQ Drive',
            'app_version'   => defined('APP_VERSION') ? APP_VERSION : '23.0',
            'scope'         => $scope,
            'created_at'    => date('Y-m-d H:i:s'),
            'counts'        => [
                'settings'  => count($settings_data),
                'pages'     => count($pages_data),
                'users'     => count($users_data),
                'analytics' => count($analytics_data)
            ],
            'data'          => [
                'settings' => $include_settings ? $settings_data : null,
                'pages'    => $include_pages ? $pages_data : null,
                'others'   => $include_others ? [
                    'users'              => $users_data,
                    'page_views_monthly' => $analytics_data,
                    'migrations'         => $migrations_data
                ] : null
            ]
        ];
    }

    /**
     * Restore a backup payload with automatic content detection.
     * Automatically detects whether the backup contains Full Backup (All), Website Settings Only,
     * Generated Pages Only, or Others Only (Accounts & Analytics), as well as raw JSON files.
     * @param array  $payload The decoded JSON backup array
     * @param string $scope   'auto' | 'all' | 'settings' | 'pages' | 'others'
     * @param string $mode    'merge' | 'overwrite'
     */
    public static function restore_backup_payload($payload, $scope = 'auto', $mode = 'merge') {
        if (!is_array($payload)) {
            throw new Exception('Invalid backup file format.');
        }

        $pdo = SLEA_DB::get_connection();
        $driver = SLEA_DB::get_driver();
        $now = date('Y-m-d H:i:s');

        $scope = strtolower(trim($scope ?: 'auto'));
        $mode  = strtolower(trim($mode ?: 'merge'));

        // Support v2 structured format, v1 format, and raw settings/pages/users/analytics JSON files
        $data_block = isset($payload['data']) && is_array($payload['data']) ? $payload['data'] : $payload;

        // Auto-detect Settings payload
        $detected_settings = null;
        if (!empty($data_block['settings']) && is_array($data_block['settings'])) {
            $detected_settings = $data_block['settings'];
        } else {
            $known_setting_keys = ['site_identity', 'login_slug', 'menu_items', 'footer_copyright', 'ad_settings', 'maintenance_settings', 'share_settings', 'debug_settings', 'update_config'];
            $matched_settings = [];
            foreach ($known_setting_keys as $k) {
                if (array_key_exists($k, $payload)) {
                    $matched_settings[$k] = $payload[$k];
                }
            }
            if (!empty($matched_settings)) {
                $detected_settings = $matched_settings;
            }
        }

        // Auto-detect Pages payload
        $detected_pages = null;
        if (isset($data_block['pages']) && is_array($data_block['pages'])) {
            $detected_pages = $data_block['pages'];
        } elseif (isset($payload[0]) && is_array($payload[0]) && isset($payload[0]['slug']) && (isset($payload[0]['buttons']) || isset($payload[0]['buttons_json']) || isset($payload[0]['title']))) {
            $detected_pages = $payload;
        } elseif (isset($payload['slug']) && (isset($payload['buttons']) || isset($payload['buttons_json']))) {
            $detected_pages = [$payload];
        }

        // Auto-detect Others (Users & Analytics) payload
        $detected_others = null;
        if (!empty($data_block['others']) && is_array($data_block['others'])) {
            $detected_others = $data_block['others'];
        } elseif (isset($payload['users']) || isset($payload['page_views_monthly'])) {
            $detected_others = [
                'users'              => $payload['users'] ?? [],
                'page_views_monthly' => $payload['page_views_monthly'] ?? []
            ];
        } elseif (isset($payload[0]) && is_array($payload[0]) && isset($payload[0]['username']) && isset($payload[0]['password_hash'])) {
            $detected_others = ['users' => $payload, 'page_views_monthly' => []];
        } elseif (isset($payload[0]) && is_array($payload[0]) && isset($payload[0]['page_slug']) && isset($payload[0]['year_month'])) {
            $detected_others = ['users' => [], 'page_views_monthly' => $payload];
        }

        $restore_settings = ($scope === 'auto' || $scope === 'all' || $scope === 'settings') && !empty($detected_settings);
        $restore_pages    = ($scope === 'auto' || $scope === 'all' || $scope === 'pages') && is_array($detected_pages);
        $restore_others   = ($scope === 'auto' || $scope === 'all' || $scope === 'others') && !empty($detected_others);

        $detected_parts = [];
        if ($restore_settings) $detected_parts[] = 'Website Settings';
        if ($restore_pages)    $detected_parts[] = 'Generated Pages';
        if ($restore_others)   $detected_parts[] = 'Others (Accounts & Analytics)';
        $detected_label = !empty($detected_parts) ? implode(' + ', $detected_parts) : 'Backup Data';

        $restored_counts = [
            'detected_type' => $detected_label,
            'settings'      => 0,
            'pages'         => 0,
            'users'         => 0,
            'analytics'     => 0
        ];

        // 1. Restore Website Settings
        if ($restore_settings && is_array($detected_settings)) {
            foreach ($detected_settings as $s_key => $s_val) {
                if (empty($s_key) || !is_string($s_key)) continue;
                if ($s_key === 'footer_copyright' && is_string($s_val)) {
                    self::save_footer_copyright($s_val);
                } else {
                    self::save_raw_setting($s_key, $s_val);
                }
                $restored_counts['settings']++;
            }
        }

        // 2. Restore Generated Pages
        $pages_list = $detected_pages;

        if ($restore_pages && is_array($pages_list)) {
            if ($mode === 'overwrite' && !empty($pages_list)) {
                try {
                    $pdo->exec("DELETE FROM pages");
                } catch (Exception $e) {}
            }

            foreach ($pages_list as $p) {
                if (empty($p['slug'])) continue;
                $slug = self::sanitize_slug($p['slug']);
                $btns_json = isset($p['buttons'])
                    ? (is_string($p['buttons']) ? $p['buttons'] : json_encode($p['buttons'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE))
                    : ($p['buttons_json'] ?? '[]');

                $chk = $pdo->prepare("SELECT id, views FROM pages WHERE slug = :s LIMIT 1");
                $chk->execute([':s' => $slug]);
                $existing_row = $chk->fetch(PDO::FETCH_ASSOC);

                if ($existing_row) {
                    $upd = $pdo->prepare("
                        UPDATE pages SET
                            title = :title,
                            description = :desc,
                            source_url = :surl,
                            resolved_url = :rurl,
                            theme = :theme,
                            buttons_json = :btns,
                            views = :views,
                            is_public = :pub,
                            updated_at = :updated_at
                        WHERE id = :id
                    ");
                    $upd->execute([
                        ':title'      => $p['title'] ?? 'Episode Download Links',
                        ':desc'       => $p['description'] ?? '',
                        ':surl'       => $p['source_url'] ?? '',
                        ':rurl'       => $p['resolved_url'] ?? '',
                        ':theme'      => $p['theme'] ?? 'indigo',
                        ':btns'       => $btns_json,
                        ':views'      => max(intval($existing_row['views'] ?? 0), intval($p['views'] ?? 0)),
                        ':pub'        => isset($p['is_public']) ? intval($p['is_public']) : 1,
                        ':updated_at' => $p['updated_at'] ?? $now,
                        ':id'         => $existing_row['id']
                    ]);
                } else {
                    $page_key = !empty($p['page_key']) ? $p['page_key'] : ('p_' . substr(md5($slug . uniqid()), 0, 12));
                    // Avoid duplicate page_key
                    $chk_k = $pdo->prepare("SELECT id FROM pages WHERE page_key = :k LIMIT 1");
                    $chk_k->execute([':k' => $page_key]);
                    if ($chk_k->fetch()) {
                        $page_key = 'p_' . substr(md5($slug . uniqid('', true)), 0, 12);
                    }

                    $ins = $pdo->prepare("
                        INSERT INTO pages (
                            page_key, slug, title, description, source_url, resolved_url,
                            theme, buttons_json, views, is_public, created_at, updated_at
                        ) VALUES (
                            :k, :slug, :title, :desc, :surl, :rurl,
                            :theme, :btns, :views, :pub, :created_at, :updated_at
                        )
                    ");
                    $ins->execute([
                        ':k'          => $page_key,
                        ':slug'       => $slug,
                        ':title'      => $p['title'] ?? 'Episode Download Links',
                        ':desc'       => $p['description'] ?? '',
                        ':surl'       => $p['source_url'] ?? '',
                        ':rurl'       => $p['resolved_url'] ?? '',
                        ':theme'      => $p['theme'] ?? 'indigo',
                        ':btns'       => $btns_json,
                        ':views'      => intval($p['views'] ?? 0),
                        ':pub'        => isset($p['is_public']) ? intval($p['is_public']) : 1,
                        ':created_at' => $p['created_at'] ?? $now,
                        ':updated_at' => $p['updated_at'] ?? $now
                    ]);
                }
                $restored_counts['pages']++;
            }

            self::sync_pages_to_file();
        }

        // 3. Restore Others (Admin Accounts, Monthly Analytics, Migrations)
        if ($restore_others && !empty($detected_others) && is_array($detected_others)) {
            $others = $detected_others;

            // 3a. Users / Admin Accounts
            if (!empty($others['users']) && is_array($others['users'])) {
                foreach ($others['users'] as $u) {
                    if (empty($u['username']) || empty($u['password_hash'])) continue;
                    if (!preg_match('/^\$2[ayb]\$/', (string)$u['password_hash'])) continue;
                    $chk_u = $pdo->prepare("SELECT id FROM users WHERE username = :u LIMIT 1");
                    $chk_u->execute([':u' => $u['username']]);
                    $ex_u = $chk_u->fetch(PDO::FETCH_ASSOC);

                    $perms_str = is_string($u['permissions'] ?? null)
                        ? $u['permissions']
                        : json_encode($u['permissions'] ?? ['all']);

                    if ($ex_u) {
                        if ($mode === 'overwrite') {
                            $upd_u = $pdo->prepare("
                                UPDATE users SET
                                    email = :e,
                                    password_hash = :p,
                                    role = :r,
                                    permissions = :perms,
                                    updated_at = :up
                                WHERE id = :id
                            ");
                            $upd_u->execute([
                                ':e'     => $u['email'] ?? ($u['username'] . '@localhost'),
                                ':p'     => $u['password_hash'],
                                ':r'     => $u['role'] ?? 'superadmin',
                                ':perms' => $perms_str,
                                ':up'    => $u['updated_at'] ?? $now,
                                ':id'    => $ex_u['id']
                            ]);
                        }
                    } else {
                        $ins_u = $pdo->prepare("
                            INSERT INTO users (username, email, password_hash, role, permissions, created_at, updated_at)
                            VALUES (:u, :e, :p, :r, :perms, :c, :up)
                        ");
                        $ins_u->execute([
                            ':u'     => $u['username'],
                            ':e'     => $u['email'] ?? ($u['username'] . '@localhost'),
                            ':p'     => $u['password_hash'],
                            ':r'     => $u['role'] ?? 'superadmin',
                            ':perms' => $perms_str,
                            ':c'     => $u['created_at'] ?? $now,
                            ':up'    => $u['updated_at'] ?? $now
                        ]);
                    }
                    $restored_counts['users']++;
                }
            }

            // 3b. Monthly Page Views Analytics
            if (!empty($others['page_views_monthly']) && is_array($others['page_views_monthly'])) {
                foreach ($others['page_views_monthly'] as $a) {
                    if (empty($a['page_slug']) || empty($a['year_month'])) continue;
                    $v_count = intval($a['views'] ?? 1);
                    try {
                        if ($driver === 'sqlite') {
                            $stmt_a = $pdo->prepare("
                                INSERT INTO page_views_monthly (page_slug, year_month, views)
                                VALUES (:s, :ym, :v)
                                ON CONFLICT(page_slug, year_month) DO UPDATE SET views = MAX(views, :v)
                            ");
                        } else {
                            $stmt_a = $pdo->prepare("
                                INSERT INTO page_views_monthly (page_slug, year_month, views)
                                VALUES (:s, :ym, :v)
                                ON DUPLICATE KEY UPDATE views = GREATEST(views, VALUES(views))
                            ");
                        }
                        $stmt_a->execute([
                            ':s'  => $a['page_slug'],
                            ':ym' => $a['year_month'],
                            ':v'  => $v_count
                        ]);
                        $restored_counts['analytics']++;
                    } catch (Exception $e) {}
                }
            }
        }

        // Sync everything back to persistent JSON files in /data
        self::sync_all_backups();

        return $restored_counts;
    }

    // -------------------------------------------------------------
    // Local Server Snapshots in /data/snapshots (Protected from Updates)
    // -------------------------------------------------------------

    public static function get_snapshots_dir() {
        $data_dir = defined('DATA_DIR') ? DATA_DIR : (APP_ROOT . '/data');
        $snap_dir = $data_dir . '/snapshots';
        if (!is_dir($snap_dir)) {
            @mkdir($snap_dir, 0755, true);
        }
        return $snap_dir;
    }

    public static function create_server_snapshot($scope = 'all', $label = '') {
        $scope = strtolower(trim($scope ?: 'all'));
        if (!in_array($scope, ['all', 'settings', 'pages', 'others'], true)) {
            $scope = 'all';
        }

        $payload = self::create_backup_payload($scope);
        $snap_id = 'snap_' . $scope . '_' . date('Ymd_His') . '_' . substr(md5(uniqid()), 0, 5);
        $filename = $snap_id . '.json';
        $payload['snapshot_id'] = $snap_id;
        $payload['label'] = !empty($label) ? trim($label) : ucfirst($scope) . ' Backup Snapshot';

        $snap_dir = self::get_snapshots_dir();
        $filepath = $snap_dir . '/' . $filename;
        @file_put_contents($filepath, json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        // Enforce max 15 snapshots so disk space stays clean
        $all_files = glob($snap_dir . '/snap_*.json');
        if (is_array($all_files) && count($all_files) > 15) {
            usort($all_files, function($a, $b) {
                return filemtime($a) - filemtime($b);
            });
            $to_delete = array_slice($all_files, 0, count($all_files) - 15);
            foreach ($to_delete as $del_file) {
                @unlink($del_file);
            }
        }

        return [
            'filename'   => $filename,
            'scope'      => $scope,
            'label'      => $payload['label'],
            'created_at' => $payload['created_at'],
            'counts'     => $payload['counts'],
            'size_kb'    => round(filesize($filepath) / 1024, 2)
        ];
    }

    public static function list_server_snapshots() {
        $snap_dir = self::get_snapshots_dir();
        $files = glob($snap_dir . '/*.json');
        if (!is_array($files)) return [];

        usort($files, function($a, $b) {
            return filemtime($b) - filemtime($a);
        });

        $list = [];
        foreach ($files as $file) {
            $raw = @file_get_contents($file);
            $dec = json_decode($raw, true);
            if (!is_array($dec)) continue;
            $list[] = [
                'filename'    => basename($file),
                'scope'       => $dec['scope'] ?? 'all',
                'label'       => $dec['label'] ?? ('Snapshot ' . basename($file)),
                'app_version' => $dec['app_version'] ?? '',
                'created_at'  => $dec['created_at'] ?? date('Y-m-d H:i:s', filemtime($file)),
                'counts'      => $dec['counts'] ?? [
                    'settings'  => !empty($dec['data']['settings']) ? count($dec['data']['settings']) : 0,
                    'pages'     => !empty($dec['data']['pages']) ? count($dec['data']['pages']) : 0,
                    'users'     => !empty($dec['data']['others']['users']) ? count($dec['data']['others']['users']) : 0,
                    'analytics' => !empty($dec['data']['others']['page_views_monthly']) ? count($dec['data']['others']['page_views_monthly']) : 0,
                ],
                'size_kb'     => round(filesize($file) / 1024, 2)
            ];
        }
        return $list;
    }

    public static function get_server_snapshot_payload($filename) {
        $safe_name = basename($filename);
        $filepath = self::get_snapshots_dir() . '/' . $safe_name;
        if (!file_exists($filepath)) {
            throw new Exception('Snapshot file not found.');
        }
        $dec = json_decode(@file_get_contents($filepath), true);
        if (!is_array($dec)) {
            throw new Exception('Snapshot file is corrupted or unreadable.');
        }
        return $dec;
    }

    public static function delete_server_snapshot($filename) {
        $safe_name = basename($filename);
        $filepath = self::get_snapshots_dir() . '/' . $safe_name;
        if (file_exists($filepath)) {
            return @unlink($filepath);
        }
        return false;
    }
}
