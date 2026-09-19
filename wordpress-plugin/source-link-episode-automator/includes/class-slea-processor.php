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
                // On failure: ensure post remains in 'pending' status
                if ($post->post_status !== 'pending') {
                    wp_update_post(array('ID' => $post_id, 'post_status' => 'pending'));
                }
                $msg = "Post #{$post_id} ('{$post->post_title}') - No Source Link or shortlink found in content or custom fields. Post kept in 'pending' status.";
                SLEA_Logger::log($msg, 'info', $post_id);
                return array(
                    'success' => false,
                    'post_id' => $post_id,
                    'title'   => $post->post_title,
                    'status'  => 'pending',
                    'error'   => 'No matching Source Link found in post content or metadata.',
                );
            }

            $source_url = esc_url_raw($source_link_info['url']);
            $raw_match  = isset($source_link_info['match']) ? $source_link_info['match'] : null;
            $source_type = isset($source_link_info['type']) ? $source_link_info['type'] : 'content_anchor';

            SLEA_Logger::log("Post #{$post_id}: Step 3 - Isolated Source Link ({$source_type}): {$source_url}", 'info', $post_id);

            $timeout = isset($settings['request_timeout']) ? intval($settings['request_timeout']) : 15;
            $max_redirects = isset($settings['max_redirect_hops']) ? intval($settings['max_redirect_hops']) : 20;

            // Step 4: From the source link page, identify the shortened URL associated with "Episode Wise Links"
            // (e.g. https://shrt.sohojgyan.com/Ij03ndJ)
            $shortlink_info = self::identify_episode_wise_shortlink($source_url, $timeout);
            $shortened_url  = esc_url_raw($shortlink_info['url']);
            $shortlink_src  = $shortlink_info['source'];

            SLEA_Logger::log("Post #{$post_id}: Step 4 - Identified Shortened URL ({$shortlink_src}): {$shortened_url}", 'info', $post_id);

            // Step 5: Resolve the identified shortened URL and access the final destination page
            $resolve_res = SLEA_Resolver::resolve_url($shortened_url, array(
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
                    'user-agent'  => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
                    'sslverify'   => false,
                ));
                if (!is_wp_error($refetch)) {
                    $final_html = wp_remote_retrieve_body($refetch);
                }
            }

            // Step 6: Extract the relevant action/episode links from the resolved destination page
            $extracted_items = SLEA_Extractor::extract_links($final_html, $final_url, true);

            // If button_only returned 0, try with relaxed filter
            if (empty($extracted_items)) {
                $extracted_items = SLEA_Extractor::extract_links($final_html, $final_url, false);
            }

            if (empty($extracted_items)) {
                self::release_post_lock($post_id);
                // On failure: ensure post remains in 'pending' status
                if ($post->post_status !== 'pending') {
                    wp_update_post(array('ID' => $post_id, 'post_status' => 'pending'));
                }
                $msg = "Post #{$post_id}: Resolved to {$final_url} ({$resolve_res['redirects']} hops), but found 0 extractable links. Post kept in 'pending' status.";
                SLEA_Logger::log($msg, 'warning', $post_id);
                return array(
                    'success'       => false,
                    'post_id'       => $post_id,
                    'title'         => $post->post_title,
                    'source'        => $source_url,
                    'shortened_url' => $shortened_url,
                    'final'         => $final_url,
                    'redirects'     => $resolve_res['redirects'],
                    'status'        => 'pending',
                    'error'         => 'No action links found on destination page.',
                );
            }

            // Step 7: Generate the required HTML code for the extracted links using mobile-friendly blue alternating buttons
            // Bound strictly to this specific Post ID (data-slea-post-id="{post_id}")
            $buttons_html_block = SLEA_Button_Generator::generate_html($extracted_items, $settings, $post_id);

            // Step 8: Replace original source link and insert Custom HTML block at end of post content
            $new_content = self::replace_source_link($content, $raw_match, $buttons_html_block);

            // Step 9: Anti-Cross-Contamination Integrity Check
            // Verify that the generated block strictly carries this post ID attribute
            if (strpos($buttons_html_block, 'data-slea-post-id="' . $post_id . '"') === false) {
                throw new Exception("Integrity error: generated markup does not match target Post #{$post_id}");
            }

            // Save isolated post execution context in metadata
            if (!empty($settings['keep_backup_meta'])) {
                update_post_meta($post_id, '_slea_original_content', $content);
                update_post_meta($post_id, '_slea_source_url', $source_url);
                update_post_meta($post_id, '_slea_shortened_url', $shortened_url);
                update_post_meta($post_id, '_slea_final_url', $final_url);
                update_post_meta($post_id, '_slea_extracted_count', count($extracted_items));
                update_post_meta($post_id, '_slea_processed_at', current_time('mysql'));
            }

            // Step 10: Update post status - allow choosing 'publish' (live on site) or 'ready' (internal review)
            $target_status = 'publish';
            if (!empty($settings['target_status']) && in_array($settings['target_status'], array('publish', 'ready', 'pending', 'draft'))) {
                $target_status = $settings['target_status'];
            } elseif (isset($settings['auto_publish']) && empty($settings['auto_publish'])) {
                $target_status = 'ready';
            }

            $update_data = array(
                'ID'           => $post_id,
                'post_content' => $new_content,
                'post_status'  => $target_status,
            );

            $res = wp_update_post($update_data, true);
            if (is_wp_error($res)) {
                // If update failed, ensure post remains in 'pending' status
                wp_update_post(array(
                    'ID'          => $post_id,
                    'post_status' => 'pending',
                ));
                self::release_post_lock($post_id);
                $err = $res->get_error_message();
                SLEA_Logger::log("Post #{$post_id}: Update error: {$err}. Kept in 'pending' status.", 'error', $post_id);
                return array('success' => false, 'post_id' => $post_id, 'status' => 'pending', 'error' => $err);
            }

            // Release lock after successful write
            self::release_post_lock($post_id);

            $status_label = ($target_status === 'publish') ? 'Published' : 'Ready';
            $success_msg = "Post #{$post_id} ('{$post->post_title}') successfully marked as '{$status_label}'! Resolved {$resolve_res['redirects']} hops, injected " . count($extracted_items) . " episode buttons into Custom HTML block.";
            SLEA_Logger::log($success_msg, 'success', $post_id);

            return array(
                'success'         => true,
                'post_id'         => $post_id,
                'title'           => $post->post_title,
                'source_url'      => $source_url,
                'shortened_url'   => $shortened_url,
                'final_url'       => $final_url,
                'redirects'       => $resolve_res['redirects'],
                'extracted_count' => count($extracted_items),
                'items'           => $extracted_items,
                'status'          => $target_status,
            );

        } catch (Exception $e) {
            // If any process failed, ensure post status remains 'pending'
            if ($post && $post->post_status !== 'pending') {
                wp_update_post(array(
                    'ID'          => $post_id,
                    'post_status' => 'pending',
                ));
            }
            self::release_post_lock($post_id);
            $err_msg = $e->getMessage();
            SLEA_Logger::log("Post #{$post_id} Exception: {$err_msg}. Kept in 'pending' status.", 'error', $post_id);
            return array('success' => false, 'post_id' => $post_id, 'status' => 'pending', 'error' => $err_msg);
        }
    }

    /**
     * Release atomic post lock
     */
    public static function release_post_lock($post_id) {
        delete_post_meta($post_id, '_slea_processing_lock');
    }

    /**
     * Finds and isolates the "Source link" hyperlink in post content or post metadata.
     * Priority order:
     *  1. Explicit "<p><a href="...">Source link </a></p>" or "<a href="...">Source link</a>" markup
     *  2. Hyperlinks with anchor text matching "Source link" / "Source" / "Original link"
     *  3. Hyperlinks matching url_pattern (e.g. mydverse.com/YYYY/MM/...)
     *  4. Text labeled "Source link:" preceding an anchor tag
     *  5. Common link shorteners in href
     *  6. Plain text URLs in body
     *  7. WP Automatic post metadata custom fields
     *
     * EXCLUSION RULE: Any URL containing movihubhq.com is strictly excluded and ignored.
     *
     * @param string $content
     * @param int $post_id
     * @param array $settings
     * @return array|null ['url' => ..., 'match' => ..., 'type' => ...]
     */
    public static function find_source_link($content, $post_id = 0, $settings = array()) {
        $anchor_text = isset($settings['source_anchor_text']) ? trim($settings['source_anchor_text']) : 'Source Link';
        $url_pattern = isset($settings['url_pattern']) ? trim($settings['url_pattern']) : 'mydverse\.com\/[0-9]{4}\/[0-9]{2}\/';

        // Helper closure to verify URL is not excluded (e.g. movihubhq.com)
        $is_valid_url = function($url) {
            if (empty($url)) {
                return false;
            }
            $lower = strtolower($url);
            // Strict exclusion of movihubhq.com
            if (strpos($lower, 'movihubhq.com') !== false || strpos($lower, 'movihub') !== false) {
                return false;
            }
            return true;
        };

        // Pattern 0: Exact paragraph-wrapped or bold-wrapped "Source link" hyperlink:
        // e.g. <p><a href="https://mydverse.com/2026/09/the-tale-of-lady-ok-korean-drama-in-hindi/" target="_blank" rel="noopener">Source link </a></p>
        if (preg_match('/<p[^>]*>\s*(?:<strong>|<b>)?\s*(<a\s+[^>]*href=[\'"]([^\'"]+)[\'"][^>]*>[\s\xc2\xa0]*(?:<strong>|<b>)?[\s\xc2\xa0]*source[\s\xc2\xa0_-]*link[\s\xc2\xa0]*(?:<\/strong>|<\/b>)?[\s\xc2\xa0]*<\/a>)\s*(?:<\/strong>|<\/b>)?\s*<\/p>/is', $content, $m)) {
            $candidate_url = trim($m[2]);
            if ($is_valid_url($candidate_url)) {
                return array(
                    'url'   => $candidate_url,
                    'match' => $m[0], // full paragraph match so replacement leaves clean document
                    'anchor_only' => $m[1],
                    'type'  => 'enclosed_source_link_paragraph',
                );
            }
        }

        // Pattern 1: Hyperlink whose anchor text specifically contains "Source link"
        if (preg_match('/<a\s+[^>]*href=[\'"]([^\'"]+)[\'"][^>]*>[\s\xc2\xa0]*(?:<strong>|<b>)?[\s\xc2\xa0]*source[\s\xc2\xa0_-]*link[\s\xc2\xa0]*(?:<\/strong>|<\/b>)?[\s\xc2\xa0]*<\/a>/is', $content, $m)) {
            $candidate_url = trim($m[1]);
            if ($is_valid_url($candidate_url)) {
                return array(
                    'url'   => $candidate_url,
                    'match' => $m[0],
                    'type'  => 'exact_source_link_anchor',
                );
            }
        }

        // Pattern 1b: Text label "Source link:" preceding an anchor tag
        if (preg_match('/(?:<p[^>]*>)?\s*(?:<strong>|<b>)?\s*source[\s\xc2\xa0_-]*link:?\s*(?:<\/strong>|<\/b>)?\s*(<a\s+[^>]*href=[\'"]([^\'"]+)[\'"][^>]*>.*?<\/a>)\s*(?:<\/p>)?/is', $content, $m)) {
            $candidate_url = trim($m[2]);
            if ($is_valid_url($candidate_url)) {
                return array(
                    'url'   => $candidate_url,
                    'match' => $m[0],
                    'type'  => 'labeled_source_link',
                );
            }
        }

        // Pattern 2: Hyperlinks matching url_pattern (e.g. mydverse.com/2026/09/...)
        if ($url_pattern) {
            if (preg_match_all('/<a\s+[^>]*href=[\'"]([^\'"]*' . $url_pattern . '[^\'"]*)[\'"][^>]*>.*?<\/a>/is', $content, $all_m, PREG_SET_ORDER)) {
                foreach ($all_m as $m) {
                    $candidate_url = trim($m[1]);
                    if ($is_valid_url($candidate_url)) {
                        return array(
                            'url'   => $candidate_url,
                            'match' => $m[0],
                            'type'  => 'pattern_href_match',
                        );
                    }
                }
            }
        }

        // Pattern 3: Anchor whose text matches configured anchor text (case-insensitive)
        if ($anchor_text) {
            $escaped_anchor = preg_quote($anchor_text, '/');
            if (preg_match_all('/<a\s+[^>]*href=[\'"]([^\'"]+)[\'"][^>]*>.*?' . $escaped_anchor . '.*?<\/a>/is', $content, $all_m, PREG_SET_ORDER)) {
                foreach ($all_m as $m) {
                    $candidate_url = trim($m[1]);
                    if ($is_valid_url($candidate_url)) {
                        return array(
                            'url'   => $candidate_url,
                            'match' => $m[0],
                            'type'  => 'anchor_match',
                        );
                    }
                }
            }
        }

        // Pattern 4: Common WP Automatic anchors ("Source", "Original Post", "Source Link", "Source URL")
        if (preg_match_all('/<a\s+[^>]*href=[\'"]([^\'"]+)[\'"][^>]*>.*?(?:source\s*(?:link|url|post)?|original\s*(?:post|link|source)?).*?<\/a>/is', $content, $all_m, PREG_SET_ORDER)) {
            foreach ($all_m as $m) {
                $candidate_url = trim($m[1]);
                if ($is_valid_url($candidate_url)) {
                    return array(
                        'url'   => $candidate_url,
                        'match' => $m[0],
                        'type'  => 'wp_automatic_anchor',
                    );
                }
            }
        }

        // Pattern 5: Known URL Shorteners in href (bit.ly, ouo.io, ouo.press, tinyurl, cutt.ly, shorturl.at, shrinke.me)
        $shortener_pattern = '(?:ouo\.(?:io|press)|bit\.ly|tinyurl\.com|cutt\.ly|shorturl\.at|shrinke\.me|t\.co|adf\.ly|shorte\.st)';
        if (preg_match_all('/<a\s+[^>]*href=[\'"]([^\'"]*' . $shortener_pattern . '[^\'"]*)[\'"][^>]*>.*?<\/a>/is', $content, $all_m, PREG_SET_ORDER)) {
            foreach ($all_m as $m) {
                $candidate_url = trim($m[1]);
                if ($is_valid_url($candidate_url)) {
                    return array(
                        'url'   => $candidate_url,
                        'match' => $m[0],
                        'type'  => 'shortener_href_match',
                    );
                }
            }
        }

        // Pattern 6: Plain text URL matching domain pattern in content
        if ($url_pattern) {
            if (preg_match_all('/(https?:\/\/[^\s<"\']*' . $url_pattern . '[^\s<"\']*)/i', $content, $all_m, PREG_SET_ORDER)) {
                foreach ($all_m as $m) {
                    $candidate_url = trim($m[1]);
                    if ($is_valid_url($candidate_url)) {
                        return array(
                            'url'   => $candidate_url,
                            'match' => $m[1],
                            'type'  => 'plain_url_pattern',
                        );
                    }
                }
            }
        }

        // Pattern 7: Fallback to WP Automatic post metadata custom fields
        if ($post_id > 0) {
            $meta_keys = array('original_link', 'source_link', 'source_url', 'wp_automatic_source', 'feed_link');
            foreach ($meta_keys as $key) {
                $meta_val = get_post_meta($post_id, $key, true);
                if (!empty($meta_val) && filter_var($meta_val, FILTER_VALIDATE_URL)) {
                    $meta_url = trim($meta_val);
                    if ($is_valid_url($meta_url)) {
                        return array(
                            'url'   => $meta_url,
                            'match' => null, // Not in post body, will append Custom HTML block
                            'type'  => 'wp_automatic_meta_' . $key,
                        );
                    }
                }
            }
        }

        return null;
    }

    /**
     * Identify the shortened URL associated with "Episode Wise Links" from the source link page.
     * Looks for URLs following structures like https://shrt.sohojgyan.com/Ij03ndJ or links labeled "Episode Wise Links".
     *
     * @param string $source_url
     * @param int $timeout
     * @return array array('url' => ..., 'source' => ...)
     */
    public static function identify_episode_wise_shortlink($source_url, $timeout = 15) {
        // 1. If source_url is already a target Blogspot destination or shortened URL, return it directly
        if (SLEA_Resolver::is_target_destination($source_url)) {
            return array(
                'url'    => $source_url,
                'source' => 'direct_target_destination',
            );
        }

        if (preg_match('/^https?:\/\/shrt\.sohojgyan\.com\/[a-zA-Z0-9_-]+/i', $source_url)
            || preg_match('/^https?:\/\/(?:shrt\.[a-z0-9.-]+|go\.sohojgyan\.com)\/[a-zA-Z0-9_-]+/i', $source_url)) {
            return array(
                'url'    => $source_url,
                'source' => 'direct_shortener_source',
            );
        }

        // 2. Fetch the source link page HTML
        $response = wp_remote_get($source_url, array(
            'timeout'     => $timeout,
            'redirection' => 5,
            'user-agent'  => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
            'sslverify'   => false,
        ));

        if (is_wp_error($response)) {
            return array(
                'url'    => $source_url,
                'source' => 'fetch_error_fallback',
            );
        }

        $page_html = wp_remote_retrieve_body($response);
        if (empty($page_html)) {
            return array(
                'url'    => $source_url,
                'source' => 'empty_body_fallback',
            );
        }

        // 3. Priority 0: Direct link to Blogspot destination structure (e.g. https://mydverse02.blogspot.com/p/flp-120926.html)
        if (preg_match('/<a\s+[^>]*href=[\'"](https?:\/\/[a-zA-Z0-9.-]*blogspot\.[a-z.]+\/p\/[a-zA-Z0-9_-]+\.html)[\'"]/i', $page_html, $m)) {
            return array(
                'url'    => trim($m[1]),
                'source' => 'direct_target_blogspot_destination',
            );
        }

        // 4. Priority A: Direct link to shrt.sohojgyan.com/<token>
        if (preg_match('/<a\s+[^>]*href=[\'"](https?:\/\/shrt\.sohojgyan\.com\/[a-zA-Z0-9_-]+)[\'"]/i', $page_html, $m)) {
            return array(
                'url'    => trim($m[1]),
                'source' => 'domain_shrt_sohojgyan',
            );
        }

        // 4. Priority B: Anchor whose text or inner element contains "Episode Wise Links" / "Episode Wise Link" / "Episode-Wise"
        if (preg_match('/<a\s+[^>]*href=[\'"]([^\'"]+)[\'"][^>]*>[\s\S]*?(?:Episode[\s_-]*Wise[\s_-]*Links?|Episode[\s_-]*Wise)[\s\S]*?<\/a>/i', $page_html, $m)) {
            $candidate = trim($m[1]);
            $resolved = SLEA_Resolver::resolve_relative_url($source_url, $candidate);
            return array(
                'url'    => $resolved,
                'source' => 'anchor_episode_wise_text',
            );
        }

        // 5. Priority C: Container or span with "Episode Wise Links" followed nearby by an <a> tag
        if (preg_match('/(?:Episode[\s_-]*Wise[\s_-]*Links?|Episode[\s_-]*Wise)[\s\S]{0,300}?<a\s+[^>]*href=[\'"]([^\'"]+)[\'"]/i', $page_html, $m)) {
            $candidate = trim($m[1]);
            $resolved = SLEA_Resolver::resolve_relative_url($source_url, $candidate);
            return array(
                'url'    => $resolved,
                'source' => 'heading_episode_wise_context',
            );
        }

        // 6. Priority D: Any shortener or intermediate redirect domain on page
        if (preg_match('/<a\s+[^>]*href=[\'"](https?:\/\/(?:shrt\.[a-z0-9.-]+|go\.sohojgyan\.com)\/[a-zA-Z0-9_-]+)[\'"]/i', $page_html, $m)) {
            return array(
                'url'    => trim($m[1]),
                'source' => 'domain_shrt_pattern',
            );
        }

        // Fallback: If no shortlink found on the page, return source_url directly
        return array(
            'url'    => $source_url,
            'source' => 'page_fallback',
        );
    }

    /**
     * Replaces ONLY the "Source link" hyperlink in the post content with the generated output HTML code.
     * Preserves all other post content (text, images, headings, paragraphs) completely intact and in place.
     *
     * @param string $content Full post content
     * @param string|null $match The matched hyperlink markup (e.g. <a ...>Source link</a>)
     * @param string $replacement_html The generated Custom HTML block
     * @return string Modified content where only the Source Link hyperlink is replaced
     */
    public static function replace_source_link($content, $match, $replacement_html) {
        if (empty($match)) {
            // If no inline match was found in post body (e.g. detected from post metadata),
            // safely append the HTML block to the content without modifying existing text.
            return rtrim($content) . "\n\n" . $replacement_html . "\n";
        }

        $escaped_match = preg_quote($match, '/');

        // 1. If the match is the sole or primary content of an enclosing <p>...</p> paragraph,
        // replace that paragraph directly in-place:
        $para_pattern = '/<p[^>]*>\s*(?:<strong>|<b>)?\s*' . $escaped_match . '\s*(?:<\/strong>|<\/b>)?\s*<\/p>/is';
        if (preg_match($para_pattern, $content)) {
            return preg_replace($para_pattern, $replacement_html, $content, 1);
        }

        // 2. If wrapped in bold/strong tags, replace in-place:
        $strong_pattern = '/<(?:strong|b)>\s*' . $escaped_match . '\s*<\/(?:strong|b)>/is';
        if (preg_match($strong_pattern, $content)) {
            return preg_replace($strong_pattern, $replacement_html, $content, 1);
        }

        // 3. Direct in-place replacement of the hyperlink markup itself
        $pos = strpos($content, $match);
        if ($pos !== false) {
            return substr_replace($content, $replacement_html, $pos, strlen($match));
        }

        // 4. Regex fallback replacement (1 occurrence only)
        return preg_replace('/' . $escaped_match . '/s', $replacement_html, $content, 1);
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
        $start_time = time();
        $max_duration_seconds = 25; // Never run longer than 25 seconds to prevent gateway timeouts

        if (function_exists('wp_suspend_cache_addition')) {
            wp_suspend_cache_addition(true);
        }

        try {
            if ($query->have_posts()) {
                SLEA_Logger::log("Starting isolated batch processing on " . count($query->posts) . " pending posts...", 'info');

                foreach ($query->posts as $post_id) {
                    // Check if execution time is approaching server cutoff
                    if ((time() - $start_time) >= $max_duration_seconds) {
                        SLEA_Logger::log("Batch safely yielded after {$max_duration_seconds}s to keep server responsive. Remaining posts will run in next pass.", 'info');
                        break;
                    }

                    // Process each post in complete isolation
                    $post_result = self::process_post($post_id);
                    $results[] = $post_result;

                    // Brief 50ms pause
                    usleep(50000);
                }
            } else {
                SLEA_Logger::log("Batch processing check: No pending posts currently waiting in queue.", 'info');
            }
        } finally {
            if (function_exists('wp_suspend_cache_addition')) {
                wp_suspend_cache_addition(false);
            }
            // Always release batch mutex
            delete_transient('slea_batch_running_lock');
        }

        return $results;
    }
}
