<?php
/**
 * Simple Logger for Source Link Automator
 */

if (!defined('ABSPATH')) {
    exit;
}

class SLEA_Logger {

    private static $option_name = 'slea_activity_logs';
    private static $max_logs = 100;

    /**
     * Log an event
     */
    public static function log($message, $type = 'info', $post_id = null) {
        $settings = get_option('slea_settings', array());
        if (empty($settings['debug_logging']) && $type === 'info') {
            return;
        }

        $logs = get_option(self::$option_name, array());
        if (!is_array($logs)) {
            $logs = array();
        }

        $entry = array(
            'time'    => current_time('mysql'),
            'type'    => $type, // info, success, warning, error
            'message' => sanitize_text_field($message),
            'post_id' => $post_id ? absint($post_id) : null,
        );

        array_unshift($logs, $entry);

        if (count($logs) > self::$max_logs) {
            $logs = array_slice($logs, 0, self::$max_logs);
        }

        update_option(self::$option_name, $logs, false);
    }

    /**
     * Get recent logs
     */
    public static function get_logs() {
        $logs = get_option(self::$option_name, array());
        return is_array($logs) ? $logs : array();
    }

    /**
     * Clear all logs
     */
    public static function clear_logs() {
        update_option(self::$option_name, array(), false);
    }
}
