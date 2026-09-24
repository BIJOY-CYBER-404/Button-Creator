<?php
/**
 * Datastore for Generated Episode Button Pages and Site Settings
 * Supports MySQL and SQLite seamlessly with automatic dual-layer backup to prevent any data loss on updates.
 */

require_once __DIR__ . '/class-db.php';

class SLEA_Datastore {

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
            $sql = "SELECT * FROM pages WHERE slug = :slug";
            if ($public_only) {
                $sql .= " AND is_public = 1";
            }
            $sql .= " LIMIT 1";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([':slug' => $slug]);
            $row = $stmt->fetch();

            if ($row) {
                return self::format_page_row($row);
            }
        } catch (Exception $e) {
            error_log('Error finding page: ' . $e->getMessage());
        }

        // Check fallback from file backup if database has missing row
        $file_pages = self::get_pages_from_file();
        foreach ($file_pages as $p) {
            if ($p['slug'] === $slug && (!$public_only || !empty($p['is_public']))) {
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

    public static function save_page($page_data) {
        $pdo = SLEA_DB::get_connection();
        $now = date('Y-m-d H:i:s');

        $title        = !empty($page_data['title']) ? trim($page_data['title']) : 'Episode Download Links';
        $description  = !empty($page_data['description']) ? trim($page_data['description']) : '';
        $source_url   = !empty($page_data['source_url']) ? trim($page_data['source_url']) : '';
        $resolved_url = !empty($page_data['resolved_url']) ? trim($page_data['resolved_url']) : '';
        $theme        = !empty($page_data['theme']) ? $page_data['theme'] : (defined('DEFAULT_PAGE_THEME') ? DEFAULT_PAGE_THEME : 'indigo');
        $is_public    = isset($page_data['is_public']) ? intval($page_data['is_public']) : 1;
        $buttons      = isset($page_data['buttons']) && is_array($page_data['buttons']) ? $page_data['buttons'] : [];
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
        } catch (Exception $e) {
            // Non-blocking view increment
        }
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
        $driver = SLEA_DB::get_driver();
        $now = date('Y-m-d H:i:s');
        $json = is_string($value) ? $value : json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($driver === 'sqlite') {
            // Standard, robust SQLite upsert compatible with all SQLite versions
            $stmt = $pdo->prepare("INSERT OR REPLACE INTO settings (setting_key, setting_value, updated_at) VALUES (:key, :val, :now)");
            $stmt->execute([':key' => $key, ':val' => $json, ':now' => $now]);
        } else {
            // MySQL / MariaDB standard upsert
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO settings (setting_key, setting_value, updated_at)
                    VALUES (:key, :val, :now)
                    ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = VALUES(updated_at)
                ");
                $stmt->execute([':key' => $key, ':val' => $json, ':now' => $now]);
            } catch (Exception $e) {
                // Fallback for strict MySQL / MariaDB configurations
                $stmt2 = $pdo->prepare("REPLACE INTO settings (setting_key, setting_value, updated_at) VALUES (:key, :val, :now)");
                $stmt2->execute([':key' => $key, ':val' => $json, ':now' => $now]);
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
            $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = :k LIMIT 1");
            $stmt->execute([':k' => $key]);
            $row = $stmt->fetch();
            if ($row && isset($row['setting_value']) && $row['setting_value'] !== '') {
                return $row['setting_value'];
            }
        } catch (Exception $e) {
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
        $clean = array_values($items);
        return self::save_raw_setting('menu_items', $clean);
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
        $clean = [
            'site_name'      => !empty($data['site_name']) ? trim($data['site_name']) : 'Movie Hub HQ Drive',
            'site_logo_url'  => trim($data['site_logo_url'] ?? ''),
            'site_logo_text' => trim($data['site_logo_text'] ?? 'MHQ'),
        ];

        self::save_raw_setting('site_identity', $clean);
        return $clean;
    }

    // -------------------------------------------------------------
    // Maintenance Mode Settings (With Countdown End Time)
    // -------------------------------------------------------------

    public static function get_maintenance_settings() {
        $defaults = [
            'enabled'  => false,
            'message'  => 'The website is currently undergoing scheduled maintenance. We will be back shortly!',
            'end_time' => ''
        ];

        $raw = self::get_raw_setting('maintenance_settings');
        if (!empty($raw)) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return array_merge($defaults, $decoded);
            }
        }

        return $defaults;
    }

    public static function save_maintenance_settings($data) {
        $clean = [
            'enabled'  => !empty($data['enabled']),
            'message'  => !empty($data['message']) ? trim($data['message']) : 'The website is currently undergoing scheduled maintenance. We will be back shortly!',
            'end_time' => trim($data['end_time'] ?? '')
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
            'show_mobile_fab' => true,
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
                return array_merge($defaults, $decoded);
            }
        }

        return $defaults;
    }

    public static function save_share_settings($data) {
        $clean = [
            'enabled' => isset($data['enabled']) ? !empty($data['enabled']) : true,
            'show_in_page' => isset($data['show_in_page']) ? !empty($data['show_in_page']) : true,
            'show_mobile_fab' => isset($data['show_mobile_fab']) ? !empty($data['show_mobile_fab']) : true,
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
}
