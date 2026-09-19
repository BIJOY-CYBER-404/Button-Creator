<?php
/**
 * Plugin Name: Source Link & Episode Button Automator
 * Plugin URI:  https://mydverse.com/
 * Description: Automatically detects "Source Link" in pending RSS/WP Automatic imported posts, resolves redirects and bypasses shortlinks to target Blogspot destinations, extracts episode links, injects mobile-width buttons in isolated Gutenberg Custom HTML blocks, and marks posts as Ready on schedule or via batch execution.
 * Version:     2.2.0
 * Author:      Automator Team
 * License:     GPL-2.0+
 * Text Domain: source-link-automator
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

define('SLEA_VERSION', '2.2.0');
define('SLEA_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SLEA_PLUGIN_URL', plugin_dir_url(__FILE__));
define('SLEA_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Require Core Modules
require_once SLEA_PLUGIN_DIR . 'includes/class-slea-logger.php';
require_once SLEA_PLUGIN_DIR . 'includes/class-slea-resolver.php';
require_once SLEA_PLUGIN_DIR . 'includes/class-slea-extractor.php';
require_once SLEA_PLUGIN_DIR . 'includes/class-slea-button-generator.php';
require_once SLEA_PLUGIN_DIR . 'includes/class-slea-processor.php';
require_once SLEA_PLUGIN_DIR . 'includes/class-slea-cron.php';
require_once SLEA_PLUGIN_DIR . 'includes/class-slea-admin.php';

/**
 * Register custom post status "Ready" with identical permissions and restrictions as "Pending"
 * - Protected from search and public archive visibility
 * - Shows in admin "All" posts table and has its own status filter tab in edit.php
 */
function slea_register_ready_post_status() {
    if (!get_post_status_object('ready')) {
        register_post_status('ready', array(
            'label'                     => _x('Ready', 'post status', 'source-link-automator'),
            'public'                    => false,
            'protected'                 => true,
            'exclude_from_search'       => true,
            'show_in_admin_all_list'    => true,
            'show_in_admin_status_list' => true,
            'label_count'               => _n_noop('Ready <span class="count">(%s)</span>', 'Ready <span class="count">(%s)</span>', 'source-link-automator'),
        ));
    }
}
add_action('init', 'slea_register_ready_post_status');

/**
 * Display "— Ready" label in post states column in WordPress admin (edit.php)
 * Completely defensive against missing arguments, non-objects, or null posts
 */
function slea_display_ready_post_state($states = array(), $post = null) {
    if (!is_array($states)) {
        $states = array();
    }
    if (!$post) {
        return $states;
    }
    $post_id = is_object($post) && isset($post->ID) ? (int)$post->ID : (is_numeric($post) ? (int)$post : 0);
    if ($post_id > 0 && function_exists('get_post_status')) {
        if (get_post_status($post_id) === 'ready') {
            $states['ready'] = _x('Ready', 'post status', 'source-link-automator');
        }
    }
    return $states;
}
add_filter('display_post_states', 'slea_display_ready_post_state', 10, 2);

/**
 * Add "Ready" option to Classic Editor status dropdown in the Publish meta box
 */
function slea_add_ready_to_classic_editor() {
    if (!is_admin()) {
        return;
    }
    global $post;
    if (!$post || !is_object($post) || !isset($post->post_type) || $post->post_type !== 'post') {
        return;
    }
    $status = isset($post->ID) ? get_post_status($post->ID) : '';
    $is_ready = ($status === 'ready');
    ?>
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        var $select = $('select#post_status');
        if ($select.length && $select.find('option[value="ready"]').length === 0) {
            $select.append('<option value="ready" <?php selected($is_ready, true); ?>><?php esc_html_e('Ready', 'source-link-automator'); ?></option>');
        }
        <?php if ($is_ready) : ?>
            $('#post-status-display').text('<?php echo esc_js(__('Ready', 'source-link-automator')); ?>');
        <?php endif; ?>
    });
    </script>
    <?php
}
add_action('post_submitbox_misc_actions', 'slea_add_ready_to_classic_editor');

/**
 * Add "Ready" option to Quick Edit & Bulk Edit status dropdowns in edit.php
 */
function slea_add_ready_to_quick_edit() {
    if (!is_admin()) {
        return;
    }
    global $post_type;
    if (empty($post_type) || $post_type !== 'post') {
        return;
    }
    ?>
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        $('select[name="_status"]').each(function() {
            if ($(this).find('option[value="ready"]').length === 0) {
                $(this).append('<option value="ready"><?php esc_html_e('Ready', 'source-link-automator'); ?></option>');
            }
        });
    });
    </script>
    <?php
}
add_action('admin_footer-edit.php', 'slea_add_ready_to_quick_edit');

/**
 * Activation hook: setup cron schedules and default options
 */
function slea_activate_plugin() {
    // Default settings
    $default_settings = array(
        'cron_enabled'        => 0, // Safe default: off until configured to prevent server load
        'target_status'       => 'publish', // Default to 'publish' so posts appear live on website!
        'cron_interval'       => 'every_five_minutes',
        'custom_cron_minutes' => 10,
        'max_runs'            => 0,
        'batch_limit'         => 5, // Safe 5 posts per batch to prevent gateway timeouts
        'source_anchor_text'  => 'Source Link',
        'url_pattern'         => 'mydverse\.com\/[0-9]{4}\/[0-9]{2}\/',
        'button_prefix'       => 'Episode',
        'start_number'        => 1,
        'pad_zeroes'          => 1,
        'open_new_tab'        => 1,
        'margin_side'         => 20,
        'session_end_text'    => '- Session End -',
        'auto_publish'        => 1,
        'keep_backup_meta'    => 1,
        'max_redirect_hops'   => 15,
        'request_timeout'     => 10,
        'debug_logging'       => 1,
    );

    $existing = get_option('slea_settings');
    if (!$existing) {
        update_option('slea_settings', $default_settings);
    } else {
        $merged = wp_parse_args($existing, $default_settings);
        update_option('slea_settings', $merged);
    }

    // Only schedule if enabled
    if (!empty($existing['cron_enabled'])) {
        SLEA_Cron::schedule_event();
    }
}
register_activation_hook(__FILE__, 'slea_activate_plugin');

/**
 * Deactivation hook: clear scheduled cron jobs
 */
function slea_deactivate_plugin() {
    SLEA_Cron::clear_event();
}
register_deactivation_hook(__FILE__, 'slea_deactivate_plugin');

/**
 * Initialize Plugin
 */
function slea_init_plugin() {
    SLEA_Cron::init();
    if (is_admin()) {
        SLEA_Admin::init();
    }
}
add_action('plugins_loaded', 'slea_init_plugin');
