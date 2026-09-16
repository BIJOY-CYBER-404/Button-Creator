<?php
/**
 * Processor for Pending Posts
 * Detects "Source Link" in pending posts (supports WP Automatic imports), resolves
 * destination shortlinks/redirects, extracts episode links, injects Gutenberg Custom HTML
 * blocks, strictly isolates each post to prevent cross-contamination, and auto-publishes.
 */

if (!defined('ABSPATH')) {
    exit;
}

class SLEA_Processor {

    /**
     * Process a single post by ID with strict isolation & atomic locking
     *
     * @param int $post_id The specific post ID to process
     * @param bool $force_publish Whether to force processing even if already published
     * @return array Result summary
     */
    public static function process_post($post_id, $force_publish = false) {
        $post_id = intval($post_id);
        if ($post_id <= 0) {
            return array('success' => false, 'error' => 'Invalid Post ID.');
        }

        // Fetch fresh post object directly from database
        $post = get_post($post_id);
        if (!$post) {
            return array('success' => false, 'error' => "Post #{$post_id} not found.");
        }

        // Check status: only process pending posts unless explicitly forced
        if (!$force_publish && $post->post_status !== 'pending') {
            return array(
                'success' => false,
                'error'   => "Post #{$post_id} has status '{$post->post_status}' (not 'pending'). Skipping.",
            );
        }

        // --- ATOMIC LOCKING TO PREVENT CONCURRENT DUPLICATE PROCESSING ---
        $lock_key = '_slea_processing_lock';
        $current_lock = get_post_meta($post_id, $lock_key, true);
        $now = time();

        // If locked within the last 300 seconds (5 mins), another process is actively working on it
        if ($current_lock && ($now - intval($current_lock)) < 300) {
            $msg = "Post #{$post_id} is currently locked by another process (started " . ($now - intval($current_lock)) . "s ago). Skipping to prevent conflict.";
            SLEA_Logger::log($msg, 'warning', $post_id);
            return array('success' => false, 'error' => $msg);
        }

        // Acquire lock immediately
        update_post_meta($post_id, $lock_key, $now);

        try {
            $content = $post->post_content;
            $settings = get_option('slea_settings', array());

            // 1. Detect Source Link in post content or WP Automatic post meta
            $source_link_info = self::find_source_link($content, $post_id, $settings);
            if (!$source_link_info || empty($source_link_info['url'])) {
                self::release_post_lock($post_id);
                $msg = "Post #{$post_id} ('{$post->post_title}') - No Source Link or shortlink found in content or custom fields.";
                SLEA_Logger::log($msg, 'info', $post_id);
                return array(
                    'success' => false,
                    'post_id' => $post_id,
                    'title'   => $post->post_title,
                    'error'   => 'No matching Source Link found in post content or metadata.',
                );
            }

            $source_url = esc_url_raw($source_link_info['url']);
            $raw_match  = isset($source_link_info['match']) ? $source_link_info['match'] : null;
            $source_type = isset($source_link_info['type']) ? $source_link_info['type'] : 'content_anchor';

            SLEA_Logger::log("Post #{$post_id}: Isolated Source Link ({$source_type}): {$source_url}", 'info', $post_id);

            // 2. Resolve URL and bypass shortlinks & redirects
            $timeout = isset($settings['request_timeout']) ? intval($settings['request_timeout']) : 15;
            $max_redirects = isset($settings['max_redirect_hops']) ? intval($settings['max_redirect_hops']) : 20;

            $resolve_res = SLEA_Resolver::resolve_url($source_url, array(
                'timeout'       => $timeout,
                'max_redirects' => $max_redirects,
            ));

            $final_url = $resolve_res['final'];
            $final_html = $resolve_res['final_html'];

            // If destination HTML was not retained during redirect chain, fetch final destination directly
            if (empty($final_html) || strlen($final_html) < 200) {
                $refetch = wp_remote_get($final_url, array(
                    'timeout'     => $timeout,
                    'redirection' => 5,
                    'user-agent'  => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36',
                    'sslverify'   => false,
                ));
                if (!is_wp_error($refetch)) {
                    $final_html = wp_remote_retrieve_body($refetch);
                }
            }

            // 3. Extract action/episode links from destination HTML
            $extracted_items = SLEA_Extractor::extract_links($final_html, $final_url, true);

            // If button_only returned 0, try with relaxed filter
            if (empty($extracted_items)) {
                $extracted_items = SLEA_Extractor::extract_links($final_html, $final_url, false);
            }

            if (empty($extracted_items)) {
                self::release_post_lock($post_id);
                $msg = "Post #{$post_id}: Resolved to {$final_url} ({$resolve_res['redirects']} hops), but found 0 extractable links.";
                SLEA_Logger::log($msg, 'warning', $post_id);
                return array(
                    'success'   => false,
                    'post_id'   => $post_id,
                    'title'     => $post->post_title,
                    'source'    => $source_url,
                    'final'     => $final_url,
                    'redirects' => $resolve_res['redirects'],
                    'error'     => 'No action links found on destination page.',
                );
            }

            // 4. Generate Gutenberg Custom HTML Block for this specific Post ID
            // Passes $post_id to ensure strict isolation, tagging data-slea-post-id="{post_id}"
            $buttons_html_block = SLEA_Button_Generator::generate_html($extracted_items, $settings, $post_id);

            // 5. Replace "Source Link" with generated Gutenberg Custom HTML block
            if ($raw_match && strpos($content, $raw_match) !== false) {
                $new_content = self::replace_source_link($content, $raw_match, $buttons_html_block);
            } else {
                // If source link came from WP Automatic meta or plain URL, append the Custom HTML block cleanly
                $new_content = rtrim($content) . "\n\n" . $buttons_html_block . "\n";
            }

            // 6. Anti-Cross-Contamination Integrity Check
            // Verify that the generated block strictly carries this post ID attribute
            if (strpos($buttons_html_block, 'data-slea-post-id="' . $post_id . '"') === false) {
                throw new Exception("Integrity error: generated markup does not match target Post #{$post_id}");
            }

            // 7. Save backup meta if requested
            if (!empty($settings['keep_backup_meta'])) {
                update_post_meta($post_id, '_slea_original_content', $content);
                update_post_meta($post_id, '_slea_source_url', $source_url);
                update_post_meta($post_id, '_slea_final_url', $final_url);
                update_post_meta($post_id, '_slea_extracted_count', count($extracted_items));
                update_post_meta($post_id, '_slea_processed_at', current_time('mysql'));
            }

            // 8. Update Post content & publish
            $update_data = array(
                'ID'           => $post_id,
                'post_content' => $new_content,
            );

            $will_publish = !empty($settings['auto_publish']);
            if ($will_publish && ($post->post_status === 'pending' || $force_publish)) {
                $update_data['post_status'] = 'publish';
            }

            $res = wp_update_post($update_data, true);
            if (is_wp_error($res)) {
                self::release_post_lock($post_id);
                $err = $res->get_error_message();
                SLEA_Logger::log("Post #{$post_id}: Update error: {$err}", 'error', $post_id);
                return array('success' => false, 'error' => $err);
            }

            // Release lock after successful write
            self::release_post_lock($post_id);

            $status_text = $will_publish ? 'Published' : 'Updated';
            $success_msg = "Post #{$post_id} ('{$post->post_title}') successfully {$status_text}! Resolved {$resolve_res['redirects']} hops, injected " . count($extracted_items) . " episode buttons into Custom HTML block.";
            SLEA_Logger::log($success_msg, 'success', $post_id);

            return array(
                'success'         => true,
                'post_id'         => $post_id,
                'title'           => $post->post_title,
                'source_url'      => $source_url,
                'final_url'       => $final_url,
                'redirects'       => $resolve_res['redirects'],
                'extracted_count' => count($extracted_items),
                'items'           => $extracted_items,
                'published'       => $will_publish,
            );

        } catch (Exception $e) {
            self::release_post_lock($post_id);
            $err_msg = $e->getMessage();
            SLEA_Logger::log("Post #{$post_id} Exception: {$err_msg}", 'error', $post_id);
            return array('success' => false, 'post_id' => $post_id, 'error' => $err_msg);
        }
    }

    /**
     * Release atomic post lock
     */
    public static function release_post_lock($post_id) {
        delete_post_meta($post_id, '_slea_processing_lock');
    }

    /**
     * Find Source Link in HTML content or WP Automatic custom fields.
     * Checks multiple patterns in order of specificity:
     *  1. Configured anchor text (e.g. <a ...>Source Link</a>)
     *  2. WP Automatic variations: "Source", "Original Link", "Source URL", "Original Post"
     *  3. Configured URL pattern in href (e.g. mydverse.com/YYYY/MM/...)
     *  4. Common link shorteners in href (bit.ly, ouo.io, tinyurl.com, cutt.ly, shrinke.me, etc.)
     *  5. Plain text URL matching domain or shortlink pattern in body
     *  6. WP Automatic post metadata (original_link, source_link, source_url, wp_automatic_source)
     *
     * @param string $content
     * @param int $post_id
     * @param array $settings
     * @return array|null ['url' => ..., 'match' => ..., 'type' => ...]
     */
    public static function find_source_link($content, $post_id = 0, $settings = array()) {
        $anchor_text = isset($settings['source_anchor_text']) ? trim($settings['source_anchor_text']) : 'Source Link';
        $url_pattern = isset($settings['url_pattern']) ? trim($settings['url_pattern']) : 'mydverse\.com\/[0-9]{4}\/[0-9]{2}\/';

        // Pattern 1: Hyperlink whose text matches configured anchor text (case-insensitive)
        if ($anchor_text) {
            $escaped_anchor = preg_quote($anchor_text, '/');
            if (preg_match('/<a\s+[^>]*href=[\'"]([^\'"]+)[\'"][^>]*>.*?' . $escaped_anchor . '.*?<\/a>/is', $content, $m)) {
                return array(
                    'url'   => trim($m[1]),
                    'match' => $m[0],
                    'type'  => 'anchor_match',
                );
            }
        }

        // Pattern 2: Hyperlinks matching url_pattern (e.g. mydverse.com/2026/09/...)
        if ($url_pattern) {
            if (preg_match('/<a\s+[^>]*href=[\'"]([^\'"]*' . $url_pattern . '[^\'"]*)[\'"][^>]*>.*?<\/a>/is', $content, $m)) {
                return array(
                    'url'   => trim($m[1]),
                    'match' => $m[0],
                    'type'  => 'pattern_href_match',
                );
            }
        }

        // Pattern 3: Common WP Automatic anchors ("Source", "Original Post", "Source Link", "Source URL")
        if (preg_match('/<a\s+[^>]*href=[\'"]([^\'"]+)[\'"][^>]*>.*?(?:source\s*(?:link|url|post)?|original\s*(?:post|link|source)?).*?<\/a>/is', $content, $m)) {
            return array(
                'url'   => trim($m[1]),
                'match' => $m[0],
                'type'  => 'wp_automatic_anchor',
            );
        }

        // Pattern 4: Known URL Shorteners in href (bit.ly, ouo.io, ouo.press, tinyurl, cutt.ly, shorturl.at, shrinke.me)
        $shortener_pattern = '(?:ouo\.(?:io|press)|bit\.ly|tinyurl\.com|cutt\.ly|shorturl\.at|shrinke\.me|t\.co|adf\.ly|shorte\.st)';
        if (preg_match('/<a\s+[^>]*href=[\'"]([^\'"]*' . $shortener_pattern . '[^\'"]*)[\'"][^>]*>.*?<\/a>/is', $content, $m)) {
            return array(
                'url'   => trim($m[1]),
                'match' => $m[0],
                'type'  => 'shortener_href_match',
            );
        }

        // Pattern 5: Plain text URL matching domain pattern
        if ($url_pattern) {
            if (preg_match('/(https?:\/\/[^\s<"\']*' . $url_pattern . '[^\s<"\']*)/i', $content, $m)) {
                return array(
                    'url'   => trim($m[1]),
                    'match' => $m[1],
                    'type'  => 'plain_url_pattern',
                );
            }
        }

        // Pattern 6: Plain text shortener URL
        if (preg_match('/(https?:\/\/[^\s<"\']*' . $shortener_pattern . '[^\s<"\']*)/i', $content, $m)) {
            return array(
                'url'   => trim($m[1]),
                'match' => $m[1],
                'type'  => 'plain_shortener_url',
            );
        }

        // Pattern 7: Fallback to WP Automatic post metadata custom fields
        if ($post_id > 0) {
            $meta_keys = array('original_link', 'source_link', 'source_url', 'wp_automatic_source', 'feed_link');
            foreach ($meta_keys as $key) {
                $meta_val = get_post_meta($post_id, $key, true);
                if (!empty($meta_val) && filter_var($meta_val, FILTER_VALIDATE_URL)) {
                    return array(
                        'url'   => trim($meta_val),
                        'match' => null, // Not in post body, will append Custom HTML block
                        'type'  => 'wp_automatic_meta_' . $key,
                    );
                }
            }
        }

        return null;
    }

    /**
     * Replace Source Link match with Custom HTML block cleanly, stripping empty enclosing paragraphs
     */
    private static function replace_source_link($content, $match, $replacement_html) {
        $escaped_match = preg_quote($match, '/');
        // Check if $match is the entire or main content of an enclosing <p>...</p> paragraph
        $para_pattern = '/<p[^>]*>\s*(?:<strong>|<b>)?\s*' . $escaped_match . '\s*(?:<\/strong>|<\/b>)?\s*<\/p>/is';

        if (preg_match($para_pattern, $content)) {
            return preg_replace($para_pattern, "\n\n" . $replacement_html . "\n\n", $content, 1);
        }

        // Otherwise replace just the match
        return str_replace($match, "\n\n" . $replacement_html . "\n\n", $content);
    }

    /**
     * Batch process pending posts sequentially with strict isolation between each post.
     * Prevents overlapping runs using a batch mutex lock.
     *
     * @param int $limit Max posts to process in one pass (e.g. 5, 10, 20)
     * @return array Results array per post
     */
    public static function process_pending_batch($limit = 10) {
        $limit = max(1, min(100, intval($limit)));

        // Global Batch Mutex: prevents two batch runners (cron + manual button) from running at the same moment
        $batch_lock = get_transient('slea_batch_running_lock');
        if ($batch_lock) {
            SLEA_Logger::log("Batch runner skipped: Another batch execution is already active.", 'warning');
            return array();
        }

        // Acquire batch mutex for up to 5 minutes
        set_transient('slea_batch_running_lock', time(), 300);

        if (function_exists('set_time_limit')) {
            @set_time_limit(300); // 5 minutes max execution time for batch
        }

        $args = array(
            'post_type'      => 'post',
            'post_status'    => 'pending',
            'posts_per_page' => $limit,
            'orderby'        => 'date',
            'order'          => 'ASC', // Process oldest pending posts first (FIFO)
            'fields'         => 'ids',
        );

        $query = new WP_Query($args);
        $results = array();

        try {
            if ($query->have_posts()) {
                SLEA_Logger::log("Starting isolated batch processing on " . count($query->posts) . " pending posts...", 'info');

                foreach ($query->posts as $post_id) {
                    // Process each post in complete isolation
                    $post_result = self::process_post($post_id);
                    $results[] = $post_result;

                    // Brief 100ms pause to ensure clean thread and memory teardown between posts
                    usleep(100000);
                }
            } else {
                SLEA_Logger::log("Batch processing check: No pending posts currently waiting in queue.", 'info');
            }
        } finally {
            // Always release batch mutex
            delete_transient('slea_batch_running_lock');
        }

        return $results;
    }
}
