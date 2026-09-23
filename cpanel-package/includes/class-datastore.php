<?php
/**
 * Datastore for Generated Episode Button Pages and Site Settings (MySQL Powered)
 */

require_once __DIR__ . '/class-db.php';

class SLEA_Datastore {
    public static function get_all_pages() {
        try {
            $pdo = SLEA_DB::get_connection();
            $stmt = $pdo->query("SELECT * FROM pages ORDER BY id DESC");
            $rows = $stmt->fetchAll();

            $pages = [];
            foreach ($rows as $r) {
                $pages[] = self::format_page_row($r);
            }
            return $pages;
        } catch (Exception $e) {
            error_log('Error getting pages: ' . $e->getMessage());
            return [];
        }
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

            return self::get_page_by_id($id);
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
            return self::get_page_by_id($new_id);
        }
    }

    public static function toggle_public_status($id) {
        $pdo = SLEA_DB::get_connection();
        $stmt = $pdo->prepare("UPDATE pages SET is_public = CASE WHEN is_public = 1 THEN 0 ELSE 1 END, updated_at = :now WHERE id = :id OR page_key = :k");
        $stmt->execute([':now' => date('Y-m-d H:i:s'), ':id' => $id, ':k' => $id]);
        return self::get_page_by_id($id);
    }

    public static function delete_page($id) {
        $pdo = SLEA_DB::get_connection();
        $stmt = $pdo->prepare("DELETE FROM pages WHERE id = :id OR page_key = :k OR slug = :s");
        return $stmt->execute([':id' => $id, ':k' => $id, ':s' => $id]);
    }

    public static function increment_views($slug) {
        try {
            $pdo = SLEA_DB::get_connection();
            $stmt = $pdo->prepare("UPDATE pages SET views = views + 1 WHERE slug = :s");
            $stmt->execute([':s' => $slug]);
        } catch (Exception $e) {
            // Non-blocking view increment
        }
    }

    // -------------------------------------------------------------
    // Menu & Settings Management
    // -------------------------------------------------------------
    public static function get_menu_items() {
        try {
            $pdo = SLEA_DB::get_connection();
            $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'menu_items' LIMIT 1");
            $stmt->execute();
            $row = $stmt->fetch();
            if ($row && !empty($row['setting_value'])) {
                $decoded = json_decode($row['setting_value'], true);
                if (is_array($decoded)) return $decoded;
            }
        } catch (Exception $e) {
            error_log('Error loading menu items: ' . $e->getMessage());
        }

        return [
            ['id' => 'm1', 'title' => 'Home', 'url' => 'https://moviehubhq.com/', 'new_tab' => false],
            ['id' => 'm2', 'title' => 'Korean Drama', 'url' => 'https://moviehubhq.com/catagory/korean/', 'new_tab' => false],
            ['id' => 'm3', 'title' => 'Chinese Drama', 'url' => 'https://moviehubhq.com/catagory/chinese/', 'new_tab' => false]
        ];
    }

    public static function save_menu_items($items) {
        $pdo = SLEA_DB::get_connection();
        $json = json_encode(array_values($items), JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        $now = date('Y-m-d H:i:s');

        $stmt = $pdo->prepare("
            INSERT INTO settings (setting_key, setting_value, updated_at)
            VALUES ('menu_items', :val, :now)
            ON DUPLICATE KEY UPDATE setting_value = :val2, updated_at = :now2
        ");
        try {
            $stmt->execute([':val' => $json, ':now' => $now, ':val2' => $json, ':now2' => $now]);
        } catch (Exception $e) {
            // For SQLite fallback syntax
            $stmt2 = $pdo->prepare("INSERT OR REPLACE INTO settings (setting_key, setting_value, updated_at) VALUES ('menu_items', :val, :now)");
            $stmt2->execute([':val' => $json, ':now' => $now]);
        }
        return true;
    }

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

        try {
            $pdo = SLEA_DB::get_connection();
            $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'ad_settings' LIMIT 1");
            $stmt->execute();
            $row = $stmt->fetch();
            if ($row && !empty($row['setting_value'])) {
                $decoded = json_decode($row['setting_value'], true);
                if (is_array($decoded)) {
                    return array_merge($defaults, $decoded);
                }
            }
        } catch (Exception $e) {
            error_log('Error loading ad settings: ' . $e->getMessage());
        }
        return $defaults;
    }

    public static function save_ad_settings($ad_data) {
        $pdo = SLEA_DB::get_connection();
        $now = date('Y-m-d H:i:s');

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

        $json = json_encode($clean, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

        $stmt = $pdo->prepare("
            INSERT INTO settings (setting_key, setting_value, updated_at)
            VALUES ('ad_settings', :val, :now)
            ON DUPLICATE KEY UPDATE setting_value = :val2, updated_at = :now2
        ");
        try {
            $stmt->execute([':val' => $json, ':now' => $now, ':val2' => $json, ':now2' => $now]);
        } catch (Exception $e) {
            $stmt2 = $pdo->prepare("INSERT OR REPLACE INTO settings (setting_key, setting_value, updated_at) VALUES ('ad_settings', :val, :now)");
            $stmt2->execute([':val' => $json, ':now' => $now]);
        }
        return $clean;
    }

    public static function get_site_identity() {
        $defaults = [
            'site_name'      => 'Movie Hub HQ Drive',
            'site_logo_url'  => '',
            'site_logo_text' => 'MHQ',
        ];

        try {
            $pdo = SLEA_DB::get_connection();
            $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'site_identity' LIMIT 1");
            $stmt->execute();
            $row = $stmt->fetch();
            if ($row && !empty($row['setting_value'])) {
                $decoded = json_decode($row['setting_value'], true);
                if (is_array($decoded)) {
                    return array_merge($defaults, $decoded);
                }
            }
        } catch (Exception $e) {
            error_log('Error loading site identity: ' . $e->getMessage());
        }
        return $defaults;
    }

    public static function save_site_identity($data) {
        $pdo = SLEA_DB::get_connection();
        $now = date('Y-m-d H:i:s');

        $clean = [
            'site_name'      => !empty($data['site_name']) ? trim($data['site_name']) : 'Movie Hub HQ Drive',
            'site_logo_url'  => trim($data['site_logo_url'] ?? ''),
            'site_logo_text' => trim($data['site_logo_text'] ?? 'MHQ'),
        ];

        $json = json_encode($clean, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

        $stmt = $pdo->prepare("
            INSERT INTO settings (setting_key, setting_value, updated_at)
            VALUES ('site_identity', :val, :now)
            ON DUPLICATE KEY UPDATE setting_value = :val2, updated_at = :now2
        ");
        try {
            $stmt->execute([':val' => $json, ':now' => $now, ':val2' => $json, ':now2' => $now]);
        } catch (Exception $e) {
            $stmt2 = $pdo->prepare("INSERT OR REPLACE INTO settings (setting_key, setting_value, updated_at) VALUES ('site_identity', :val, :now)");
            $stmt2->execute([':val' => $json, ':now' => $now]);
        }
        return $clean;
    }

    public static function get_maintenance_settings() {
        $defaults = [
            'enabled' => false,
            'message' => 'The website is currently undergoing scheduled maintenance. We will be back shortly!'
        ];

        try {
            $pdo = SLEA_DB::get_connection();
            $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'maintenance_settings' LIMIT 1");
            $stmt->execute();
            $row = $stmt->fetch();
            if ($row && !empty($row['setting_value'])) {
                $decoded = json_decode($row['setting_value'], true);
                if (is_array($decoded)) {
                    return array_merge($defaults, $decoded);
                }
            }
        } catch (Exception $e) {
            error_log('Error loading maintenance settings: ' . $e->getMessage());
        }
        return $defaults;
    }

    public static function save_maintenance_settings($data) {
        $pdo = SLEA_DB::get_connection();
        $now = date('Y-m-d H:i:s');

        $clean = [
            'enabled' => !empty($data['enabled']),
            'message' => !empty($data['message']) ? trim($data['message']) : 'The website is currently undergoing scheduled maintenance. We will be back shortly!'
        ];

        $json = json_encode($clean, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

        $stmt = $pdo->prepare("
            INSERT INTO settings (setting_key, setting_value, updated_at)
            VALUES ('maintenance_settings', :val, :now)
            ON DUPLICATE KEY UPDATE setting_value = :val2, updated_at = :now2
        ");
        try {
            $stmt->execute([':val' => $json, ':now' => $now, ':val2' => $json, ':now2' => $now]);
        } catch (Exception $e) {
            $stmt2 = $pdo->prepare("INSERT OR REPLACE INTO settings (setting_key, setting_value, updated_at) VALUES ('maintenance_settings', :val, :now)");
            $stmt2->execute([':val' => $json, ':now' => $now]);
        }
        return $clean;
    }

    public static function get_footer_copyright() {
        try {
            $pdo = SLEA_DB::get_connection();
            $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'footer_copyright' LIMIT 1");
            $stmt->execute();
            $row = $stmt->fetch();
            if ($row && isset($row['setting_value']) && trim($row['setting_value']) !== '') {
                $val = $row['setting_value'];
                // If legacy MySQL corruption converted emojis to '??', auto-repair to clean UTF-8 emojis
                if (strpos($val, '??') !== false) {
                    $repaired = str_replace(
                        ['?? • Made with ??', 'Gateway ?? • All rights reserved ??', 'MovieHubHQ ??'],
                        ['🍿 • Made with ❤️', 'Gateway 🎬 • All rights reserved 🚀', 'MovieHubHQ 🍿'],
                        $val
                    );
                    return $repaired;
                }
                return $val;
            }
        } catch (Exception $e) {
            error_log('Error loading footer copyright: ' . $e->getMessage());
        }
        return '&copy; ' . date('Y') . ' MovieHubHQ 🍿 • Made with ❤️ for Direct Episode Link Gateway 🎬 • All rights reserved 🚀';
    }

    public static function save_footer_copyright($html) {
        $pdo = SLEA_DB::get_connection();
        $now = date('Y-m-d H:i:s');

        // Ensure proper UTF-8 4-byte encoding for emojis
        if (function_exists('mb_check_encoding') && !mb_check_encoding($html, 'UTF-8')) {
            $html = mb_convert_encoding($html, 'UTF-8', mb_detect_encoding($html) ?: 'UTF-8');
        }

        $stmt = $pdo->prepare("
            INSERT INTO settings (setting_key, setting_value, updated_at)
            VALUES ('footer_copyright', :val, :now)
            ON DUPLICATE KEY UPDATE setting_value = :val2, updated_at = :now2
        ");
        try {
            $stmt->execute([':val' => $html, ':now' => $now, ':val2' => $html, ':now2' => $now]);
        } catch (Exception $e) {
            // For SQLite fallback
            $stmt2 = $pdo->prepare("INSERT OR REPLACE INTO settings (setting_key, setting_value, updated_at) VALUES ('footer_copyright', :val, :now)");
            $stmt2->execute([':val' => $html, ':now' => $now]);
        }
        return true;
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
