<?php
/**
 * Admin Panel & AJAX Controls for WordPress
 * - Pending posts table with "Process Now" buttons
 * - One-click "Run Batch Automation"
 * - Cron Schedule settings (Every 5 mins, 15 mins, Hourly, etc.)
 * - Batch size configuration (e.g. 5, 10, 20 posts per run)
 * - Source Link detection pattern configuration
 * - Episode Button styling options
 * - Real-time activity logs viewer
 * - Direct Live Test Sandbox
 */

if (!defined('ABSPATH')) {
    exit;
}

class SLEA_Admin {

    public static function init() {
        add_action('admin_menu', array(__CLASS__, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue_assets'));
        add_action('admin_init', array(__CLASS__, 'register_settings'));

        // AJAX actions
        add_action('wp_ajax_slea_process_single', array(__CLASS__, 'ajax_process_single'));
        add_action('wp_ajax_slea_process_batch', array(__CLASS__, 'ajax_process_batch'));
        add_action('wp_ajax_slea_test_url', array(__CLASS__, 'ajax_test_url'));
        add_action('wp_ajax_slea_clear_logs', array(__CLASS__, 'ajax_clear_logs'));
        add_action('wp_ajax_slea_reset_counter', array(__CLASS__, 'ajax_reset_counter'));
        add_action('wp_ajax_slea_get_stats', array(__CLASS__, 'ajax_get_stats'));
    }

    public static function add_admin_menu() {
        add_menu_page(
            __('Source Link Automator', 'source-link-automator'),
            __('Episode Automator', 'source-link-automator'),
            'manage_options',
            'source-link-automator',
            array(__CLASS__, 'render_admin_page'),
            'dashicons-randomize',
            26
        );
    }

    public static function enqueue_assets($hook) {
        if ($hook !== 'toplevel_page_source-link-automator') {
            return;
        }

        wp_enqueue_style('slea-admin-css', SLEA_PLUGIN_URL . 'assets/admin.css', array(), SLEA_VERSION);
        wp_enqueue_script('slea-admin-js', SLEA_PLUGIN_URL . 'assets/admin.js', array('jquery'), SLEA_VERSION, true);

        wp_localize_script('slea-admin-js', 'sleaData', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('slea_ajax_nonce'),
        ));
    }

    public static function register_settings() {
        register_setting('slea_settings_group', 'slea_settings', array(__CLASS__, 'sanitize_settings'));
    }

    public static function sanitize_settings($input) {
        $clean = array();
        $clean['cron_enabled']        = !empty($input['cron_enabled']) ? 1 : 0;
        $clean['cron_interval']       = sanitize_text_field($input['cron_interval']);
        $clean['custom_cron_minutes'] = max(1, min(1440, intval($input['custom_cron_minutes'])));
        $clean['max_runs']            = max(0, intval($input['max_runs'])); // 0 = unlimited
        $clean['batch_limit']         = max(1, min(100, intval($input['batch_limit'])));
        $clean['source_anchor_text']  = sanitize_text_field($input['source_anchor_text']);
        $clean['url_pattern']         = sanitize_text_field($input['url_pattern']);
        $clean['button_prefix']       = sanitize_text_field($input['button_prefix']);
        $clean['start_number']        = max(1, intval($input['start_number']));
        $clean['pad_zeroes']          = !empty($input['pad_zeroes']) ? 1 : 0;
        $clean['open_new_tab']        = !empty($input['open_new_tab']) ? 1 : 0;
        $clean['margin_side']         = max(0, min(80, intval($input['margin_side'])));
        $clean['session_end_text']    = sanitize_text_field($input['session_end_text']);
        $clean['auto_publish']        = !empty($input['auto_publish']) ? 1 : 0;
        $clean['keep_backup_meta']    = !empty($input['keep_backup_meta']) ? 1 : 0;
        $clean['max_redirect_hops']   = max(5, min(50, intval($input['max_redirect_hops'])));
        $clean['request_timeout']     = max(5, min(60, intval($input['request_timeout'])));
        $clean['debug_logging']       = !empty($input['debug_logging']) ? 1 : 0;
        $clean['browser_heartbeat']   = !empty($input['browser_heartbeat']) ? 1 : 0;

        // Reschedule cron whenever settings change
        SLEA_Cron::schedule_event();

        return $clean;
    }

    /**
     * AJAX: Reset Run Counter
     */
    public static function ajax_reset_counter() {
        check_ajax_referer('slea_ajax_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permission denied.'));
        }

        SLEA_Cron::reset_run_counter();
        wp_send_json_success(SLEA_Cron::get_run_stats());
    }

    /**
     * AJAX: Get Live Stats
     */
    public static function ajax_get_stats() {
        check_ajax_referer('slea_ajax_nonce', 'nonce');
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => 'Permission denied.'));
        }

        $post_counts   = wp_count_posts('post');
        $pending_count = isset($post_counts->pending) ? (int)$post_counts->pending : 0;
        $ready_count   = isset($post_counts->ready) ? (int)$post_counts->ready : 0;
        $stats         = SLEA_Cron::get_run_stats();
        $stats['pending_posts'] = $pending_count;
        $stats['ready_posts']   = $ready_count;

        wp_send_json_success($stats);
    }

    /**
     * AJAX: Process Single Post
     */
    public static function ajax_process_single() {
        check_ajax_referer('slea_ajax_nonce', 'nonce');
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => 'Permission denied.'));
        }

        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        if (!$post_id) {
            wp_send_json_error(array('message' => 'Invalid Post ID.'));
        }

        $result = SLEA_Processor::process_post($post_id);
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    /**
     * AJAX: Process Batch
     */
    public static function ajax_process_batch() {
        check_ajax_referer('slea_ajax_nonce', 'nonce');
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => 'Permission denied.'));
        }

        $settings = get_option('slea_settings', array());
        $limit    = isset($_POST['limit']) ? intval($_POST['limit']) : (isset($settings['batch_limit']) ? intval($settings['batch_limit']) : 5);

        $results = SLEA_Processor::process_pending_batch($limit);
        wp_send_json_success(array(
            'count'   => count($results),
            'results' => $results,
        ));
    }

    /**
     * AJAX: Test single URL directly
     */
    public static function ajax_test_url() {
        check_ajax_referer('slea_ajax_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permission denied.'));
        }

        $test_url = isset($_POST['test_url']) ? esc_url_raw($_POST['test_url']) : '';
        if (!$test_url) {
            wp_send_json_error(array('message' => 'Provide a valid test URL.'));
        }

        $res = SLEA_Resolver::resolve_url($test_url);
        $final_url = $res['final'];
        $items = SLEA_Extractor::extract_links($res['final_html'], $final_url, true);
        if (empty($items)) {
            $items = SLEA_Extractor::extract_links($res['final_html'], $final_url, false);
        }

        $preview_html = SLEA_Button_Generator::generate_html($items);

        wp_send_json_success(array(
            'original'     => $test_url,
            'final'        => $final_url,
            'redirects'    => $res['redirects'],
            'chain'        => $res['chain'],
            'items'        => $items,
            'preview_html' => $preview_html,
        ));
    }

    /**
     * AJAX: Clear logs
     */
    public static function ajax_clear_logs() {
        check_ajax_referer('slea_ajax_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permission denied.'));
        }

        SLEA_Logger::clear_logs();
        wp_send_json_success();
    }

    /**
     * Render Admin Page
     */
    public static function render_admin_page() {
        $settings = get_option('slea_settings', array());
        $pending_query = new WP_Query(array(
            'post_type'      => 'post',
            'post_status'    => 'pending',
            'posts_per_page' => 50,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ));

        $post_counts   = wp_count_posts('post');
        $total_pending = isset($post_counts->pending) ? (int)$post_counts->pending : $pending_query->found_posts;
        $total_ready   = isset($post_counts->ready) ? (int)$post_counts->ready : 0;
        $stats = SLEA_Cron::get_run_stats();
        $next_cron = wp_next_scheduled(SLEA_Cron::CRON_HOOK);
        $logs = SLEA_Logger::get_logs();

        $max_runs = isset($settings['max_runs']) ? intval($settings['max_runs']) : 0;
        $runs_completed = $stats['runs_completed'];
        $cron_interval = isset($settings['cron_interval']) ? $settings['cron_interval'] : 'every_five_minutes';
        $custom_mins = isset($settings['custom_cron_minutes']) ? intval($settings['custom_cron_minutes']) : 10;
        $batch_limit = isset($settings['batch_limit']) ? intval($settings['batch_limit']) : 10;

        ?>
        <div class="wrap slea-admin-wrap">
            <h1 class="wp-heading-inline">Source Link & Episode Button Automator</h1>
            <p class="description">
                Engineered for WP Automatic & RSS feeds with 10+ pending posts. Detects source links, bypasses shorteners/redirects, generates isolated Gutenberg HTML blocks, and marks posts as Ready without mixing data.
            </p>
            <hr class="wp-header-end">

            <!-- Stats Bar -->
            <div class="slea-stats-grid">
                <div class="slea-stat-card">
                    <div class="slea-stat-value" id="slea-stat-pending-count"><?php echo intval($total_pending); ?></div>
                    <div class="slea-stat-label">Pending Posts in Queue</div>
                </div>

                <div class="slea-stat-card">
                    <div class="slea-stat-value" id="slea-stat-ready-count" style="color:#0a8553;"><?php echo intval($total_ready); ?></div>
                    <div class="slea-stat-label">
                        Ready Posts
                        <span class="slea-badge slea-badge-success" style="display:inline-block; margin-top:4px;">Processed &amp; Ready</span>
                    </div>
                </div>

                <div class="slea-stat-card">
                    <div class="slea-stat-value" id="slea-stat-runs-display">
                        <?php if ($max_runs > 0) : ?>
                            <?php echo esc_html("{$runs_completed} / {$max_runs}"); ?>
                        <?php else : ?>
                            <?php echo esc_html("Run #{$runs_completed} (Continuous)"); ?>
                        <?php endif; ?>
                    </div>
                    <div class="slea-stat-label">
                        Execution Counter
                        <?php if ($stats['is_limit_reached']) : ?>
                            <span class="slea-badge slea-badge-error" style="display:block; margin-top:4px;">Target Reached (Stopped)</span>
                        <?php endif; ?>
                    </div>
                    <button type="button" id="slea-btn-reset-counter" class="button button-small" style="margin-top:8px;">Reset Counter</button>
                </div>

                <div class="slea-stat-card">
                    <div class="slea-stat-value">
                        <?php echo !empty($settings['cron_enabled']) ? 'Active' : 'Paused'; ?>
                    </div>
                    <div class="slea-stat-label">
                        Schedule: <strong><?php echo esc_html(ucwords(str_replace('_', ' ', $cron_interval))); ?></strong>
                        <?php if ($next_cron) : ?>
                            <br><small>Next run in <?php echo human_time_diff(time(), $next_cron); ?></small>
                        <?php else : ?>
                            <br><small>No scheduled run</small>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="slea-stat-card">
                    <div class="slea-stat-value"><?php echo intval($batch_limit); ?></div>
                    <div class="slea-stat-label">Batch Size per Pass</div>
                </div>
            </div>

            <!-- Manual Trigger Actions -->
            <div class="slea-panel slea-panel-actions">
                <h2>Batch Execution & Manual Automation Trigger</h2>
                <p>Process pending posts immediately without waiting for the scheduler. Each post is strictly isolated with post-level atomic locks to prevent cross-contamination.</p>
                <div class="slea-button-row" style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
                    <button id="slea-btn-run-batch" class="button button-primary button-hero" data-limit="<?php echo intval($batch_limit); ?>">
                        <span class="dashicons dashicons-update"></span>
                        Process Next Batch of <?php echo intval($batch_limit); ?> Pending Posts
                    </button>
                    <button id="slea-btn-run-all" class="button button-secondary button-hero">
                        <span class="dashicons dashicons-controls-forward"></span>
                        Process ALL <?php echo intval($total_pending); ?> Pending Posts Sequentially
                    </button>
                    <button id="slea-btn-refresh-pending" class="button button-secondary">
                        <span class="dashicons dashicons-image-rotate"></span> Refresh Queue
                    </button>
                </div>
                <div id="slea-batch-feedback" class="slea-feedback-area" style="display:none; margin-top:15px;"></div>
            </div>

            <!-- Tabs Navigation -->
            <h2 class="nav-tab-wrapper">
                <a href="#tab-pending" class="nav-tab nav-tab-active" data-tab="pending">Pending Posts Queue (<?php echo intval($total_pending); ?>)</a>
                <a href="#tab-test" class="nav-tab" data-tab="test">Live URL Resolver Sandbox</a>
                <a href="#tab-settings" class="nav-tab" data-tab="settings">Scheduling & Tool Settings</a>
                <a href="#tab-logs" class="nav-tab" data-tab="logs">Execution Logs</a>
            </h2>

            <!-- Tab 1: Pending Posts List -->
            <div id="slea-tab-pending" class="slea-tab-content" style="display:block;">
                <p class="description" style="margin-bottom:12px;">
                    Showing up to 50 pending posts awaiting processing. Click "Automate & Publish" to run an individual post, or use the batch buttons above.
                </p>
                <table class="wp-list-table widefat fixed striped posts">
                    <thead>
                        <tr>
                            <th style="width: 70px;">ID</th>
                            <th>Post Title</th>
                            <th>Detected Source Link</th>
                            <th style="width: 130px;">Isolation Scope</th>
                            <th style="width: 140px;">Date</th>
                            <th style="width: 180px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($pending_query->have_posts()) : ?>
                            <?php while ($pending_query->have_posts()) : $pending_query->the_post();
                                $p_id = get_the_ID();
                                $content = get_the_content();
                                $source_info = SLEA_Processor::find_source_link($content, $settings, $p_id);
                            ?>
                                <tr id="slea-post-row-<?php echo $p_id; ?>">
                                    <td><strong>#<?php echo $p_id; ?></strong></td>
                                    <td>
                                        <strong><a href="<?php echo get_edit_post_link($p_id); ?>" target="_blank"><?php the_title(); ?></a></strong>
                                    </td>
                                    <td>
                                        <?php if ($source_info) : ?>
                                            <code class="slea-url-tag" style="word-break:break-all;"><?php echo esc_html($source_info['url']); ?></code>
                                            <small style="display:block; color:#666;">Source: <?php echo esc_html($source_info['match_type']); ?></small>
                                        <?php else : ?>
                                            <span class="slea-badge-warning">No Source Link Found</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="slea-badge slea-badge-info">Post #<?php echo $p_id; ?> Only</span>
                                    </td>
                                    <td><?php echo get_the_date('Y-m-d H:i'); ?></td>
                                    <td>
                                        <button class="button button-small button-primary slea-btn-process-post" data-post-id="<?php echo $p_id; ?>" <?php echo !$source_info ? 'disabled' : ''; ?>>
                                            Automate & Mark Ready
                                        </button>
                                        <a href="<?php echo get_preview_post_link($p_id); ?>" target="_blank" class="button button-small">Preview</a>
                                    </td>
                                </tr>
                            <?php endwhile; wp_reset_postdata(); ?>
                        <?php else : ?>
                            <tr>
                                <td colspan="6">No pending posts found. Once your RSS or WP Automatic plugin pulls posts into "Pending", they will appear here.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Tab 2: Live URL Tester & Sandbox -->
            <div id="slea-tab-test" class="slea-tab-content" style="display:none;">
                <div class="slea-panel">
                    <h3>Test Source Link Resolver & Button Extraction</h3>
                    <p>Enter any source URL (such as a <code>mydverse.com</code> link or shortener) to simulate redirect bypass, link extraction, and preview the generated episode buttons before processing posts.</p>

                    <div style="display: flex; gap: 10px; margin-bottom: 15px;">
                        <input type="url" id="slea-test-url-input" class="regular-text" style="flex: 1;" placeholder="https://mydverse.com/2026/09/our-universe-korean-drama-in-hindi-dubbed/" value="https://mydverse.com/2026/09/our-universe-korean-drama-in-hindi-dubbed/">
                        <button type="button" id="slea-btn-test-run" class="button button-primary">Test & Resolve</button>
                    </div>

                    <div id="slea-test-output" style="display:none;">
                        <h4>Test Result Summary</h4>
                        <div id="slea-test-meta"></div>
                        <h4>Generated Episode Buttons Preview:</h4>
                        <div id="slea-test-buttons-preview" style="background:#f8fafd; padding: 20px; border:1px solid #ccd3d9; border-radius: 8px;"></div>
                        <h4>Generated Gutenberg Custom HTML Block Code:</h4>
                        <textarea id="slea-test-html-code" rows="8" class="large-text code" readonly></textarea>
                    </div>
                </div>
            </div>

            <!-- Tab 3: Settings -->
            <div id="slea-tab-settings" class="slea-tab-content" style="display:none;">
                <form method="post" action="options.php">
                    <?php settings_fields('slea_settings_group'); ?>

                    <h2 class="title" style="margin-top:20px; padding-bottom:8px; border-bottom:1px solid #ddd;">Scheduling Options (No manual cron required)</h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row">Enable Scheduling</th>
                            <td>
                                <label>
                                    <input type="checkbox" name="slea_settings[cron_enabled]" value="1" <?php checked(!empty($settings['cron_enabled'])); ?>>
                                    Enable automatic background scheduling to process pending posts
                                </label>
                                <p class="description">When enabled, WordPress internal scheduling executes the automator automatically without manual crontab setup.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="slea-cron-interval">How often the tool should run</label></th>
                            <td>
                                <select name="slea_settings[cron_interval]" id="slea-cron-interval">
                                    <option value="every_one_minute" <?php selected($cron_interval, 'every_one_minute'); ?>>Every 1 Minute</option>
                                    <option value="every_two_minutes" <?php selected($cron_interval, 'every_two_minutes'); ?>>Every 2 Minutes</option>
                                    <option value="every_five_minutes" <?php selected($cron_interval, 'every_five_minutes'); ?>>Every 5 Minutes (Recommended)</option>
                                    <option value="every_ten_minutes" <?php selected($cron_interval, 'every_ten_minutes'); ?>>Every 10 Minutes</option>
                                    <option value="every_fifteen_minutes" <?php selected($cron_interval, 'every_fifteen_minutes'); ?>>Every 15 Minutes</option>
                                    <option value="every_thirty_minutes" <?php selected($cron_interval, 'every_thirty_minutes'); ?>>Every 30 Minutes</option>
                                    <option value="hourly" <?php selected($cron_interval, 'hourly'); ?>>Every 1 Hour</option>
                                    <option value="every_two_hours" <?php selected($cron_interval, 'every_two_hours'); ?>>Every 2 Hours</option>
                                    <option value="every_six_hours" <?php selected($cron_interval, 'every_six_hours'); ?>>Every 6 Hours</option>
                                    <option value="twicedaily" <?php selected($cron_interval, 'twicedaily'); ?>>Twice Daily (Every 12 Hours)</option>
                                    <option value="daily" <?php selected($cron_interval, 'daily'); ?>>Daily (Every 24 Hours)</option>
                                    <option value="custom" <?php selected($cron_interval, 'custom'); ?>>Custom Minutes Interval</option>
                                </select>
                                <div id="slea-custom-interval-wrap" style="margin-top:10px; <?php echo ($cron_interval !== 'custom') ? 'display:none;' : ''; ?>">
                                    <label>
                                        Run every <input type="number" name="slea_settings[custom_cron_minutes]" value="<?php echo esc_attr($custom_mins); ?>" min="1" max="1440" class="small-text"> minutes
                                    </label>
                                </div>
                                <p class="description">Select the frequency interval for automatic checking of pending posts.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="slea-max-runs">How many times the tool should run</label></th>
                            <td>
                                <input type="number" name="slea_settings[max_runs]" id="slea-max-runs" value="<?php echo esc_attr($max_runs); ?>" min="0" max="10000" class="small-text">
                                <p class="description">
                                    Specify the target number of execution passes (e.g. <code>5</code>, <code>10</code>, <code>20</code>, or <code>50</code>).<br>
                                    <strong>Set to 0</strong> for unlimited continuous background execution. When set to a positive number, the tool automatically pauses once the target runs are completed.
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Batch Size per Run</th>
                            <td>
                                <input type="number" name="slea_settings[batch_limit]" value="<?php echo esc_attr($batch_limit); ?>" min="1" max="100" class="small-text">
                                <p class="description">Maximum number of pending posts to resolve and publish per run (default: 10). Handles 10+ pending posts safely in sequence.</p>
                            </td>
                        </tr>
                    </table>

                    <h2 class="title" style="margin-top:30px; padding-bottom:8px; border-bottom:1px solid #ddd;">WP Automatic & Post Source Link Extraction</h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row">Source Link Anchor Text</th>
                            <td>
                                <input type="text" name="slea_settings[source_anchor_text]" value="<?php echo esc_attr($settings['source_anchor_text']); ?>" class="regular-text">
                                <p class="description">The hyperlink text created by WP Automatic or RSS feeds (default: <code>Source Link</code>).</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Source URL Regex Pattern</th>
                            <td>
                                <input type="text" name="slea_settings[url_pattern]" value="<?php echo esc_attr($settings['url_pattern']); ?>" class="regular-text">
                                <p class="description">Regex pattern to identify source URLs (e.g. <code>mydverse\.com\/[0-9]{4}\/[0-9]{2}\/</code>).</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Post Status on Success</th>
                            <td>
                                <p style="margin-top:0;">
                                    <span class="slea-badge slea-badge-success" style="background:#eafaf1; color:#0a8553; border:1px solid #c2ebd5; padding:4px 10px; border-radius:12px; font-weight:600; font-size:12px;">Status: Ready</span>
                                </p>
                                <p class="description">
                                    When processing succeeds, the post is automatically updated and marked as <strong>Ready</strong> (registered with identical permissions and restrictions to <strong>Pending</strong>). If any step of the process fails, the post remains safely in <strong>Pending</strong> status.
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Backup Original Content</th>
                            <td>
                                <label>
                                    <input type="checkbox" name="slea_settings[keep_backup_meta]" value="1" <?php checked(!empty($settings['keep_backup_meta'])); ?>>
                                    Store original RSS post content and source URL in custom fields (<code>_slea_original_content</code>, <code>_slea_source_url</code>) for safe rollback
                                </label>
                            </td>
                        </tr>
                    </table>

                    <h2 class="title" style="margin-top:30px; padding-bottom:8px; border-bottom:1px solid #ddd;">Gutenberg Custom HTML Block & Button Appearance</h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row">Button Label Prefix</th>
                            <td>
                                <input type="text" name="slea_settings[button_prefix]" value="<?php echo esc_attr($settings['button_prefix']); ?>" class="regular-text">
                                <p class="description">e.g. <code>Episode</code> (produces "Episode 01", "Episode 02"...)</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Start Number & Zero Padding</th>
                            <td>
                                <input type="number" name="slea_settings[start_number]" value="<?php echo esc_attr($settings['start_number']); ?>" min="1" class="small-text">
                                <label style="margin-left: 15px;">
                                    <input type="checkbox" name="slea_settings[pad_zeroes]" value="1" <?php checked(!empty($settings['pad_zeroes'])); ?>>
                                    Pad single-digit numbers with leading zero (e.g. 01, 02)
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Side Margins (px)</th>
                            <td>
                                <input type="number" name="slea_settings[margin_side]" value="<?php echo esc_attr($settings['margin_side']); ?>" min="0" max="80" class="small-text"> px
                                <p class="description">Outer side margins ensuring mobile responsiveness and max-width: 440px constraint (default: 20px).</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Session End Text</th>
                            <td>
                                <input type="text" name="slea_settings[session_end_text]" value="<?php echo esc_attr($settings['session_end_text']); ?>" class="regular-text">
                                <p class="description">Appears centered in red beneath the episode buttons (default: <code>- Session End -</code>). Leave blank to omit.</p>
                            </td>
                        </tr>
                    </table>

                    <?php submit_button('Save Scheduling & Automator Settings'); ?>
                </form>
            </div>

            <!-- Tab 4: Logs -->
            <div id="slea-tab-logs" class="slea-tab-content" style="display:none;">
                <div class="slea-panel">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                        <h3>Recent Execution Activity Logs</h3>
                        <button type="button" id="slea-btn-clear-logs" class="button">Clear Logs</button>
                    </div>
                    <div class="slea-logs-container">
                        <?php if (!empty($logs)) : ?>
                            <table class="widefat fixed">
                                <thead>
                                    <tr>
                                        <th style="width: 150px;">Time</th>
                                        <th style="width: 90px;">Type</th>
                                        <th style="width: 90px;">Post ID</th>
                                        <th>Message</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($logs as $log) : ?>
                                        <tr class="slea-log-row-<?php echo esc_attr($log['type']); ?>">
                                            <td><code><?php echo esc_html($log['time']); ?></code></td>
                                            <td><span class="slea-badge slea-badge-<?php echo esc_attr($log['type']); ?>"><?php echo esc_html(strtoupper($log['type'])); ?></span></td>
                                            <td><?php echo $log['post_id'] ? '#' . intval($log['post_id']) : '-'; ?></td>
                                            <td><?php echo esc_html($log['message']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else : ?>
                            <p>No activity logs recorded yet.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
}

