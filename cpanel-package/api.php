<?php
/**
 * Protected Admin AJAX API for cPanel
 * All actions strictly require admin authentication.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/class-db.php';
require_once __DIR__ . '/includes/class-auth.php';
require_once __DIR__ . '/includes/class-datastore.php';
require_once __DIR__ . '/includes/class-resolver.php';
require_once __DIR__ . '/includes/class-extractor.php';
require_once __DIR__ . '/includes/class-updater.php';

header('Content-Type: application/json; charset=utf-8');

$action = isset($_GET['action']) ? trim($_GET['action']) : '';
$raw_input = file_get_contents('php://input');
$data = json_decode($raw_input, true) ?: [];

// Strict Admin Authorization Check
SLEA_Auth::require_admin();

// Ensure script execution time doesn't exceed 40 seconds on shared hosting
@set_time_limit(40);

$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$base_url = rtrim($protocol . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']), '/\\');

try {
    switch ($action) {
        case 'create_page_from_url':
            $input_url = trim($data['url'] ?? '');
            if (empty($input_url)) {
                throw new Exception('Please provide a shortened URL or source link.');
            }

            // 1. Resolve URL with retry until target Blogspot structure
            $resolved_dest = $input_url;
            $final_html = '';

            if (SLEA_Resolver::is_target_destination($input_url)) {
                $resolved_dest = $input_url;
                list($_, $final_html) = SLEA_Extractor::fetch_page($input_url);
            } else {
                $res_obj = SLEA_Resolver::resolve_shortlink_until_target($input_url, [], 4);
                $resolved_dest = !empty($res_obj['final']) ? $res_obj['final'] : $input_url;
                $final_html = !empty($res_obj['final_html']) ? $res_obj['final_html'] : '';

                if (!SLEA_Resolver::is_target_destination($resolved_dest)) {
                    if (preg_match('/(https?:\/\/(?:www\.)?[a-zA-Z0-9.-]+\.blogspot\.[a-z.]+\/p\/[a-zA-Z0-9_-]+\.html)/i', $final_html, $tdm)) {
                        $resolved_dest = $tdm[1];
                        list($_, $fetched_html) = SLEA_Extractor::fetch_page($resolved_dest);
                        if (!empty($fetched_html)) {
                            $final_html = $fetched_html;
                        }
                    }
                }

                if (empty($final_html) || strlen($final_html) < 200) {
                    list($_, $fetched_html) = SLEA_Extractor::fetch_page($resolved_dest);
                    if (!empty($fetched_html)) {
                        $final_html = $fetched_html;
                    }
                }
            }

            // 2. Extract Episode Buttons first
            $buttons = SLEA_Extractor::extract_buttons_from_html($final_html, $resolved_dest, true);

            if (!SLEA_Resolver::is_target_destination($resolved_dest) && empty($buttons)) {
                throw new Exception('Resolved destination could not be matched to an episode page: ' . $resolved_dest);
            }

            // 3. Extract Page Title strictly from this target HTML (or explicit admin title)
            $page_title = '';
            if (!empty($data['title'])) {
                $page_title = trim($data['title']);
            }
            if (empty($page_title)) {
                $page_title = SLEA_Extractor::extract_page_title($final_html, $resolved_dest);
            }
            // Sanitize title: if page title contains "DramaVerse 2", "mydverse", "mydverse 2" replace with "Movie Hub HQ"
            $page_title = SLEA_Extractor::sanitize_page_title($page_title);

            // Generate different randomized slug for every page
            $chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
            $rand_str = '';
            for ($i = 0; $i < 8; $i++) {
                $rand_str .= $chars[random_int(0, strlen($chars) - 1)];
            }
            $slug_candidate = 'ep-' . $rand_str;

            // 4. Save Page to Datastore
            $saved_page = SLEA_Datastore::save_page([
                'title'        => $page_title,
                'slug'         => $slug_candidate,
                'source_url'   => $input_url,
                'resolved_url' => $resolved_dest,
                'theme'        => !empty($data['theme']) ? $data['theme'] : (defined('DEFAULT_PAGE_THEME') ? DEFAULT_PAGE_THEME : 'indigo'),
                'is_public'    => isset($data['is_public']) ? intval($data['is_public']) : 1,
                'buttons'      => $buttons
            ]);

            $clean_url = $base_url . '/p/' . $saved_page['slug'];
            $view_url  = $base_url . '/view.php?slug=' . $saved_page['slug'];

            echo json_encode([
                'success' => true,
                'data'    => [
                    'id'           => $saved_page['id'],
                    'slug'         => $saved_page['slug'],
                    'title'        => $saved_page['title'],
                    'clean_url'    => $clean_url,
                    'view_url'     => $view_url,
                    'is_public'    => intval($saved_page['is_public']),
                    'resolved_url' => $resolved_dest,
                    'target_valid' => SLEA_Resolver::is_target_destination($resolved_dest),
                    'button_count' => count($buttons),
                    'buttons'      => $buttons
                ]
            ]);
            break;

        case 'toggle_page_status':
            $id = $data['id'] ?? 0;
            if (empty($id)) throw new Exception('Page ID is required.');
            $updated = SLEA_Datastore::toggle_public_status($id);
            echo json_encode(['success' => true, 'page' => $updated]);
            break;

        case 'update_page':
            $id = $data['id'] ?? 0;
            if (empty($id)) throw new Exception('Page ID is required.');
            $existing = SLEA_Datastore::get_page_by_id($id);
            if (!$existing) throw new Exception('Page not found.');

            $raw_title = $data['title'] ?? $existing['title'];
            $sanitized_title = SLEA_Extractor::sanitize_page_title($raw_title);

            $saved = SLEA_Datastore::save_page([
                'id'          => $id,
                'title'       => $sanitized_title,
                'slug'        => $data['slug'] ?? $existing['slug'],
                'description' => $data['description'] ?? $existing['description'],
                'is_public'   => isset($data['is_public']) ? intval($data['is_public']) : $existing['is_public'],
                'theme'       => $data['theme'] ?? $existing['theme'],
                'buttons'     => isset($data['buttons']) ? $data['buttons'] : $existing['buttons'],
                'source_url'  => $existing['source_url'],
                'resolved_url'=> $existing['resolved_url']
            ]);
            echo json_encode(['success' => true, 'page' => $saved]);
            break;

        case 'bulk_delete_pages':
            $ids = $data['ids'] ?? [];
            if (!is_array($ids) || empty($ids)) throw new Exception('No page IDs provided for bulk deletion.');
            $deleted = 0;
            foreach ($ids as $del_id) {
                if (!empty($del_id)) {
                    SLEA_Datastore::delete_page($del_id);
                    $deleted++;
                }
            }
            echo json_encode(['success' => true, 'deleted_count' => $deleted, 'message' => "Successfully deleted {$deleted} page(s)."]);
            break;

        case 'delete_page':
            $id = $data['id'] ?? 0;
            if (empty($id)) throw new Exception('Page ID is required.');
            SLEA_Datastore::delete_page($id);
            echo json_encode(['success' => true]);
            break;

        case 'list_pages':
            $pages = SLEA_Datastore::get_all_pages();
            echo json_encode(['success' => true, 'data' => $pages]);
            break;

        case 'save_site_identity':
            $site_data = $data['site_identity'] ?? [];
            if (!is_array($site_data)) throw new Exception('Invalid site identity format.');
            $saved = SLEA_Datastore::save_site_identity($site_data);
            echo json_encode(['success' => true, 'site_identity' => $saved]);
            break;

        case 'get_site_identity':
            $site_identity = SLEA_Datastore::get_site_identity();
            echo json_encode(['success' => true, 'site_identity' => $site_identity]);
            break;

        case 'save_menu_items':
            $items = $data['items'] ?? [];
            if (!is_array($items)) throw new Exception('Invalid items format.');
            SLEA_Datastore::save_menu_items($items);
            echo json_encode(['success' => true]);
            break;

        case 'save_footer_copyright':
            $html = $data['footer_html'] ?? '';
            SLEA_Datastore::save_footer_copyright($html);
            echo json_encode(['success' => true]);
            break;

        case 'save_ad_settings':
            $ad_data = $data['ad_settings'] ?? [];
            if (!is_array($ad_data)) throw new Exception('Invalid ad settings format.');
            $saved = SLEA_Datastore::save_ad_settings($ad_data);
            echo json_encode(['success' => true, 'ad_settings' => $saved]);
            break;

        case 'get_ad_settings':
            $ad_settings = SLEA_Datastore::get_ad_settings();
            echo json_encode(['success' => true, 'ad_settings' => $ad_settings]);
            break;

        case 'save_maintenance_settings':
            $m_data = $data['maintenance_settings'] ?? [];
            if (!is_array($m_data)) throw new Exception('Invalid maintenance settings format.');
            $saved = SLEA_Datastore::save_maintenance_settings($m_data);
            echo json_encode(['success' => true, 'maintenance_settings' => $saved]);
            break;

        case 'get_maintenance_settings':
            $m_settings = SLEA_Datastore::get_maintenance_settings();
            echo json_encode(['success' => true, 'maintenance_settings' => $m_settings]);
            break;

        case 'save_share_settings':
            $s_data = $data['share_settings'] ?? [];
            if (!is_array($s_data)) throw new Exception('Invalid share settings format.');
            $saved = SLEA_Datastore::save_share_settings($s_data);
            echo json_encode(['success' => true, 'share_settings' => $saved]);
            break;

        case 'get_share_settings':
            $s_settings = SLEA_Datastore::get_share_settings();
            echo json_encode(['success' => true, 'share_settings' => $s_settings]);
            break;

        case 'resolve':
            $input_url = trim($data['url'] ?? '');
            if (empty($input_url)) throw new Exception('URL is required.');
            $res = SLEA_Resolver::resolve_shortlink_until_target($input_url, [], 4);
            echo json_encode(['success' => true, 'data' => $res]);
            break;

        case 'extract':
            $url = trim($data['url'] ?? '');
            $html = $data['html'] ?? '';
            if (empty($url) && empty($html)) throw new Exception('URL or HTML content is required.');
            if (empty($html) && !empty($url)) {
                list($url, $html) = SLEA_Extractor::fetch_page($url);
            }
            $buttons = SLEA_Extractor::extract_buttons_from_html($html, $url, true);
            $page_title = SLEA_Extractor::extract_page_title($html, $url);
            echo json_encode([
                'success' => true,
                'data' => [
                    'items'      => $buttons,
                    'count'      => count($buttons),
                    'page_title' => $page_title
                ]
            ]);
            break;

        case 'check_update':
            $res = SLEA_Updater::check_for_updates(true);
            echo json_encode($res);
            break;

        case 'run_update':
            $res = SLEA_Updater::run_update();
            echo json_encode($res);
            break;

        case 'get_update_config':
            $config = SLEA_Updater::get_config();
            echo json_encode(['success' => true, 'config' => $config]);
            break;

        case 'save_update_config':
            $new_cfg = $data['config'] ?? [];
            $saved = SLEA_Updater::save_config($new_cfg);
            echo json_encode(['success' => true, 'config' => $saved]);
            break;

        case 'get_update_history':
            $history = SLEA_Updater::get_update_history();
            echo json_encode(['success' => true, 'history' => $history]);
            break;

        case 'health':
            $logger = new SLEA_UpdateLogger('health_check');
            $checker = new SLEA_HealthChecker($logger);
            $ok = $checker->verify_health();
            echo json_encode(['success' => $ok, 'version' => defined('APP_VERSION') ? APP_VERSION : 'v-3.8.0', 'time' => date('Y-m-d H:i:s')]);
            break;

        default:
            throw new Exception('Unknown API action.');
    }
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error'   => $e->getMessage()
    ]);
}
