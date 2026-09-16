<?php
/**
 * WP-Cron & Scheduling Manager
 * Controls how often the tool runs (1 min to daily or custom) and how many times it runs (target count or unlimited).
 */

if (!defined('ABSPATH')) {
    exit;
}

class SLEA_Cron {

    const CRON_HOOK = 'slea_run_pending_post_automator';

    public static function init() {
        // Register custom cron schedules
        add_filter('cron_schedules', array(__CLASS__, 'register_schedules'));

        // Attach action hook
        add_action(self::CRON_HOOK, array(__CLASS__, 'execute_cron'));
    }

    /**
     * Register flexible interval options
     */
    public static function register_schedules($schedules) {
        $settings = get_option('slea_settings', array());

        if (!isset($schedules['every_one_minute'])) {
            $schedules['every_one_minute'] = array(
                'interval' => 60,
                'display'  => __('Every 1 Minute', 'source-link-automator'),
            );
        }

        if (!isset($schedules['every_two_minutes'])) {
            $schedules['every_two_minutes'] = array(
                'interval' => 120,
                'display'  => __('Every 2 Minutes', 'source-link-automator'),
            );
        }

        if (!isset($schedules['every_five_minutes'])) {
            $schedules['every_five_minutes'] = array(
                'interval' => 300,
                'display'  => __('Every 5 Minutes', 'source-link-automator'),
            );
        }

        if (!isset($schedules['every_ten_minutes'])) {
            $schedules['every_ten_minutes'] = array(
                'interval' => 600,
                'display'  => __('Every 10 Minutes', 'source-link-automator'),
            );
        }

        if (!isset($schedules['every_fifteen_minutes'])) {
            $schedules['every_fifteen_minutes'] = array(
                'interval' => 900,
                'display'  => __('Every 15 Minutes', 'source-link-automator'),
            );
        }

        if (!isset($schedules['every_thirty_minutes'])) {
            $schedules['every_thirty_minutes'] = array(
                'interval' => 1800,
                'display'  => __('Every 30 Minutes', 'source-link-automator'),
            );
        }

        if (!isset($schedules['every_two_hours'])) {
            $schedules['every_two_hours'] = array(
                'interval' => 7200,
                'display'  => __('Every 2 Hours', 'source-link-automator'),
            );
        }

        if (!isset($schedules['every_six_hours'])) {
            $schedules['every_six_hours'] = array(
                'interval' => 21600,
                'display'  => __('Every 6 Hours', 'source-link-automator'),
            );
        }

        // Custom minutes interval if selected
        $custom_mins = isset($settings['custom_cron_minutes']) ? intval($settings['custom_cron_minutes']) : 0;
        if ($custom_mins > 0) {
            $schedules['slea_custom_interval'] = array(
                'interval' => $custom_mins * 60,
                'display'  => sprintf(__('Every %d Minutes (Custom)', 'source-link-automator'), $custom_mins),
            );
        }

        return $schedules;
    }

    /**
     * Schedule or reschedule the event based on settings
     */
    public static function schedule_event() {
        $settings = get_option('slea_settings', array());
        $enabled  = !empty($settings['cron_enabled']);
        $interval = isset($settings['cron_interval']) ? $settings['cron_interval'] : 'every_five_minutes';
        $max_runs = isset($settings['max_runs']) ? intval($settings['max_runs']) : 0;
        $completed = intval(get_option('slea_runs_completed', 0));

        // Clear existing schedule
        self::clear_event();

        // If max_runs limit was already reached, do not schedule
        if ($max_runs > 0 && $completed >= $max_runs) {
            return;
        }

        if ($enabled) {
            if ($interval === 'custom') {
                $interval = 'slea_custom_interval';
            }

            if (!wp_next_scheduled(self::CRON_HOOK)) {
                // Schedule next run 60 seconds from now or based on interval
                wp_schedule_event(time() + 60, $interval, self::CRON_HOOK);
            }
        }
    }

    /**
     * Clear scheduled cron event
     */
    public static function clear_event() {
        $timestamp = wp_next_scheduled(self::CRON_HOOK);
        if ($timestamp) {
            wp_unschedule_event($timestamp, self::CRON_HOOK);
        }
    }

    /**
     * Execute cron pass with execution limits and run tracking
     */
    public static function execute_cron() {
        $settings = get_option('slea_settings', array());
        $limit    = isset($settings['batch_limit']) ? intval($settings['batch_limit']) : 10;
        $max_runs = isset($settings['max_runs']) ? intval($settings['max_runs']) : 0; // 0 = unlimited
        $completed = intval(get_option('slea_runs_completed', 0));

        // Check if max runs limit has been reached
        if ($max_runs > 0 && $completed >= $max_runs) {
            SLEA_Logger::log("Scheduled runner halted: Target of {$max_runs} runs completed. Automator stopped.", 'info');
            self::clear_event();

            // Set cron_enabled to 0 in settings
            $settings['cron_enabled'] = 0;
            update_option('slea_settings', $settings);
            return;
        }

        // Increment run counter
        $completed++;
        update_option('slea_runs_completed', $completed);
        update_option('slea_last_run_time', current_time('mysql'));

        $run_status = ($max_runs > 0) ? "Run {$completed} of {$max_runs}" : "Continuous Run #{$completed}";
        SLEA_Logger::log("Automator started [{$run_status}] - processing up to {$limit} pending posts...", 'info');

        $results = SLEA_Processor::process_pending_batch($limit);
        $success_count = 0;

        foreach ($results as $res) {
            if (!empty($res['success'])) {
                $success_count++;
            }
        }

        SLEA_Logger::log("Automator finished [{$run_status}]: Processed " . count($results) . " posts ({$success_count} published).", 'info');

        // Check if this run fulfilled the target limit
        if ($max_runs > 0 && $completed >= $max_runs) {
            SLEA_Logger::log("Milestone reached: Target of {$max_runs} runs completed. Auto-scheduling is now stopped.", 'success');
            self::clear_event();
            $settings['cron_enabled'] = 0;
            update_option('slea_settings', $settings);
        }
    }

    /**
     * Get run counter statistics
     */
    public static function get_run_stats() {
        $settings  = get_option('slea_settings', array());
        $max_runs  = isset($settings['max_runs']) ? intval($settings['max_runs']) : 0;
        $completed = intval(get_option('slea_runs_completed', 0));
        $last_run  = get_option('slea_last_run_time', 'Never');
        $next_run  = wp_next_scheduled(self::CRON_HOOK);

        return array(
            'runs_completed'    => $completed,
            'max_runs'          => $max_runs,
            'is_limit_reached'  => ($max_runs > 0 && $completed >= $max_runs),
            'last_run'          => $last_run,
            'next_run_human'    => $next_run ? human_time_diff(time(), $next_run) : null,
            'is_cron_scheduled' => (bool)$next_run,
            'cron_enabled'      => !empty($settings['cron_enabled']),
        );
    }

    /**
     * Reset the run counter to 0
     */
    public static function reset_run_counter() {
        update_option('slea_runs_completed', 0);
        SLEA_Logger::log("Automator run counter reset to 0.", 'info');
        self::schedule_event();
    }
}
