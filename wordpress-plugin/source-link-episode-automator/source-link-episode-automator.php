<?php
/**
 * Plugin Name: Source Link & Episode Button Automator
 * Plugin URI:  https://mydverse.com/
 * Description: Automatically detects "Source Link" in pending RSS/WP Automatic imported posts, resolves redirects and bypasses shortlinks, extracts episode links, injects mobile-width buttons in isolated Gutenberg Custom HTML blocks, and auto-publishes on schedule (configurable frequency & run count) or via batch execution.
 * Version:     2.0.0
 * Author:      Automator Team
 * License:     GPL-2.0+
 * Text Domain: source-link-automator
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

define('SLEA_VERSION', '2.0.0');
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
 * Activation hook: setup cron schedules and default options
 */
function slea_activate_plugin() {
    // Default settings
    $default_settings = array(
        'cron_enabled'        => 1,
        'cron_interval'       => 'every_five_minutes', // 1 min, 2 min, 5 min, 10 min, 15 min, 30 min, hourly, etc.
        'custom_cron_minutes' => 10,
        'max_runs'            => 0, // 0 = unlimited continuous runs, or specify number (e.g. 10)
        'batch_limit'         => 10, // Handles 10+ pending posts smoothly
        'source_anchor_text'  => 'Source Link', // case-insensitive anchor text or URL pattern
        'url_pattern'         => 'mydverse\.com\/[0-9]{4}\/[0-9]{2}\/', // regex to detect source link URLs
        'button_prefix'       => 'Episode',
        'start_number'        => 1,
        'pad_zeroes'          => 1,
        'open_new_tab'        => 1,
        'margin_side'         => 20,
        'session_end_text'    => '- Session End -',
        'auto_publish'        => 1, // Change status from 'pending' to 'publish'
        'keep_backup_meta'    => 1, // Store original content & source link in post meta
        'max_redirect_hops'   => 20,
        'request_timeout'     => 15,
        'debug_logging'       => 1,
    );

    $existing = get_option('slea_settings');
    if (!$existing) {
        update_option('slea_settings', $default_settings);
    } else {
        $merged = wp_parse_args($existing, $default_settings);
        update_option('slea_settings', $merged);
    }

    // Schedule cron job
    SLEA_Cron::schedule_event();
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
