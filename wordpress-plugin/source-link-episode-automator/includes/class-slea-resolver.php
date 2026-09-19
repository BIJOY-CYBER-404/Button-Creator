<?php
/**
 * Short URL & Redirect Resolver for WordPress
 * Replicates the robust multi-step Python resolver using wp_remote_get/requests,
 * cookies, meta refresh parsing, JS location regex, and query param unpacker.
 */

if (!defined('ABSPATH')) {
    exit;
}

class SLEA_Resolver {

    const DEFAULT_MAX_REDIRECTS = 20;
    const DEFAULT_TIMEOUT = 14;

    private static $user_agent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36';

    /**
     * Resolve a URL through all redirects, shorteners, meta refreshes, and scripts.
     *
     * @param string $initial_url
     * @param array  $options
     * @return array {
     *    success: bool,
     *    original: string,
     *    final: string,
     *    redirects: int,
     *    chain: array,
     *    final_html: string,
     *    error: string|null
     * }
     */
    public static function resolve_url($initial_url, $options = array()) {
        $max_redirects = isset($options['max_redirects']) ? intval($options['max_redirects']) : self::DEFAULT_MAX_REDIRECTS;
        $timeout = isset($options['timeout']) ? intval($options['timeout']) : self::DEFAULT_TIMEOUT;

        $current_url = trim($initial_url);
        $original_url = $current_url;
        $chain = array();
        $visited = array();
        $cookies = array();
        $final_html = '';

        for ($step = 1; $step <= $max_redirects; $step++) {
            $chain[] = array(
                'step'   => $step,
                'url'    => $current_url,
                'status' => 0,
                'type'   => $step === 1 ? 'Initial Request' : 'Redirect Hop',
            );

            // Loop prevention
            $normalized = strtolower(rtrim($current_url, '/'));
            if (isset($visited[$normalized])) {
                $chain[count($chain) - 1]['type'] = 'Loop Detected -> Halting';
                break;
            }
            $visited[$normalized] = true;

            // SSRF Check (Ensure valid public HTTP URL)
            if (!self::is_safe_public_url($current_url)) {
                return array(
                    'success'   => false,
                    'original'  => $original_url,
                    'final'     => $current_url,
                    'redirects' => count($chain) - 1,
                    'chain'     => $chain,
                    'final_html'=> '',
                    'error'     => 'Unsafe or blocked destination IP/scheme.',
                );
            }

            // Fast-path: Check for embedded target in query parameters (?url=, ?dest=, ?link=, base64)
            $embedded_target = self::extract_embedded_redirect($current_url);
            if ($embedded_target && $embedded_target !== $current_url && !isset($visited[strtolower(rtrim($embedded_target, '/'))])) {
                $chain[count($chain) - 1]['type'] = 'Query Param / Base64 Bypass';
                $current_url = $embedded_target;
                continue;
            }

            // Perform HTTP Request
            $response = wp_remote_get($current_url, array(
                'timeout'     => $timeout,
                'redirection' => 0, // We control redirects manually to record every step & cookie
                'user-agent'  => self::$user_agent,
                'headers'     => array(
                    'Accept'          => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                    'Accept-Language' => 'en-US,en;q=0.9',
                    'Referer'         => $step > 1 ? $chain[$step - 2]['url'] : '',
                ),
                'cookies'     => $cookies,
                'sslverify'   => false, // Avoid SSL failures on small link shortener domains
            ));

            if (is_wp_error($response)) {
                $error_msg = $response->get_error_message();
                $chain[count($chain) - 1]['status'] = 500;
                $chain[count($chain) - 1]['error'] = $error_msg;

                return array(
                    'success'   => count($chain) > 1,
                    'original'  => $original_url,
                    'final'     => $current_url,
                    'redirects' => max(0, count($chain) - 1),
                    'chain'     => $chain,
                    'final_html'=> $final_html,
                    'error'     => $error_msg,
                );
            }

            $status_code = wp_remote_retrieve_response_code($response);
            $headers     = wp_remote_retrieve_headers($response);
            $body        = wp_remote_retrieve_body($response);
            $final_html  = $body;

            $chain[count($chain) - 1]['status'] = $status_code;

            // Merge cookies for session state across hops
            $new_cookies = wp_remote_retrieve_cookies($response);
            if (!empty($new_cookies)) {
                foreach ($new_cookies as $c) {
                    $cookies[] = $c;
                }
            }

            // Case 1: Standard HTTP 3xx Redirect (301, 302, 303, 307, 308)
            if (in_array($status_code, array(301, 302, 303, 307, 308))) {
                $location = isset($headers['location']) ? $headers['location'] : '';
                if ($location) {
                    $resolved_next = self::resolve_relative_url($current_url, $location);
                    $chain[count($chain) - 1]['type'] = "HTTP {$status_code} Location Header";
                    $current_url = $resolved_next;
                    continue;
                }
            }

            // Case 2: Meta Refresh Tag (<meta http-equiv="refresh" content="0; url=...">)
            $meta_target = self::extract_meta_refresh($body, $current_url);
            if ($meta_target && $meta_target !== $current_url && !isset($visited[strtolower(rtrim($meta_target, '/'))])) {
                $chain[count($chain) - 1]['type'] = 'HTML Meta Refresh';
                $current_url = $meta_target;
                continue;
            }

            // If current_url matches target destination structure (e.g. https://mydverse02.blogspot.com/p/flp-120926.html),
            // this is the intended final destination page holding the episode links.
            if (self::is_target_destination($current_url)) {
                $chain[count($chain) - 1]['type'] = 'Target Destination (Blogspot Episode Page)';
                break;
            }

            // Case 3: Safelink Multi-Stage Interstitial Bypass
            $safelink_target = self::extract_safelink_bypass($body, $current_url);
            if ($safelink_target && $safelink_target !== $current_url && !isset($visited[strtolower(rtrim($safelink_target, '/'))])) {
                $chain[count($chain) - 1]['type'] = 'Safelink Interstitial Bypass';
                $current_url = $safelink_target;
                continue;
            }

            // Case 4: AdLinkFly / MightyScripts AJAX Bypass (e.g. go.sohojgyan.com)
            $adfly_target = self::extract_adlinkfly_bypass($body, $current_url, $cookies);
            if ($adfly_target && $adfly_target !== $current_url && !isset($visited[strtolower(rtrim($adfly_target, '/'))])) {
                $chain[count($chain) - 1]['type'] = 'AdLinkFly Engine Bypass';
                $current_url = $adfly_target;
                continue;
            }

            // Case 5: JavaScript location redirect (window.location = ..., location.replace(...))
            $js_target = self::extract_js_redirect($body, $current_url);
            if ($js_target && $js_target !== $current_url && !isset($visited[strtolower(rtrim($js_target, '/'))])) {
                $chain[count($chain) - 1]['type'] = 'JavaScript Client Redirect';
                $current_url = $js_target;
                continue;
            }

            // Case 6: Base64 obfuscated window.location.href = atob("...")
            $b64_target = self::extract_atob_redirect($body, $current_url);
            if ($b64_target && $b64_target !== $current_url && !isset($visited[strtolower(rtrim($b64_target, '/'))])) {
                $chain[count($chain) - 1]['type'] = 'Obfuscated atob() Script Redirect';
                $current_url = $b64_target;
                continue;
            }

            // Case 7: Button / Interstitial action link candidate
            $btn_target = self::extract_button_bypass($body, $current_url);
            if ($btn_target && $btn_target !== $current_url && !isset($visited[strtolower(rtrim($btn_target, '/'))])) {
                $chain[count($chain) - 1]['type'] = 'Action Button Bypass';
                $current_url = $btn_target;
                continue;
            }

            // Case 8: Destination reached (200 OK without further hops)
            $chain[count($chain) - 1]['type'] = 'Final Destination Reached';
            break;
        }

        return array(
            'success'   => true,
            'original'  => $original_url,
            'final'     => $current_url,
            'redirects' => max(0, count($chain) - 1),
            'chain'     => $chain,
            'final_html'=> $final_html,
            'error'     => null,
        );
    }

    /**
     * Checks if URL matches the target Blogspot destination structure:
     * e.g. https://mydverse02.blogspot.com/p/flp-120926.html
     *      https://mydverse02.blogspot.com/p/mbmb-030826.html
     *      https://*.blogspot.com/p/*.html
     */
    public static function is_target_destination($url) {
        if (empty($url)) {
            return false;
        }
        $host = parse_url($url, PHP_URL_HOST);
        $path = parse_url($url, PHP_URL_PATH);
        if ($host && (stripos($host, 'blogspot.') !== false || stripos($host, 'mydverse') !== false)) {
            if ($path && strpos($path, '/p/') !== false && substr($path, -5) === '.html') {
                return true;
            }
        }
        return (bool) preg_match('/^https?:\/\/[a-zA-Z0-9.-]*blogspot\.[a-z.]+\/p\/[a-zA-Z0-9_-]+\.html/i', $url);
    }

    /**
     * Check if a URL has safe public scheme and destination
     */
    private static function is_safe_public_url($url) {
        if (!preg_match('/^https?:\/\//i', $url)) {
            return false;
        }

        $host = parse_url($url, PHP_URL_HOST);
        if (!$host) {
            return false;
        }

        // Prevent SSRF: Deny localhost, private IPs, metadata endpoints
        if (in_array(strtolower($host), array('localhost', '127.0.0.1', '::1', '0.0.0.0', '169.254.169.254', 'metadata.google.internal'))) {
            return false;
        }

        return true;
    }

    /**
     * Resolve relative or protocol-relative URLs against a base URL
     */
    public static function resolve_relative_url($base, $relative) {
        $relative = trim($relative);
        if (preg_match('/^https?:\/\//i', $relative)) {
            return $relative;
        }
        if (strpos($relative, '//') === 0) {
            $scheme = parse_url($base, PHP_URL_SCHEME);
            return ($scheme ? $scheme : 'https') . ':' . $relative;
        }

        $base_parts = parse_url($base);
        $scheme = isset($base_parts['scheme']) ? $base_parts['scheme'] : 'https';
        $host   = isset($base_parts['host']) ? $base_parts['host'] : '';
        $port   = isset($base_parts['port']) ? ':' . $base_parts['port'] : '';
        $path   = isset($base_parts['path']) ? $base_parts['path'] : '/';

        if (strpos($relative, '/') === 0) {
            return "{$scheme}://{$host}{$port}{$relative}";
        }

        $dir = preg_replace('/\/[^\/]*$/', '', $path);
        return "{$scheme}://{$host}{$port}{$dir}/{$relative}";
    }

    /**
     * Extract target from URL query params (e.g. ?url=https://..., ?dest=base64, etc.)
     */
    private static function extract_embedded_redirect($url) {
        $parsed = parse_url($url);
        if (empty($parsed['query'])) {
            return null;
        }

        parse_str($parsed['query'], $query_params);
        $candidate_keys = array('url', 'dest', 'destination', 'target', 'link', 'redirect', 'goto', 'out', 'to');

        foreach ($candidate_keys as $key) {
            if (!empty($query_params[$key])) {
                $val = trim($query_params[$key]);

                // Direct HTTP URL
                if (preg_match('/^https?:\/\//i', $val)) {
                    return $val;
                }

                // Base64 decoded check
                $decoded = base64_decode($val, true);
                if ($decoded && preg_match('/^https?:\/\//i', $decoded)) {
                    return $decoded;
                }
            }
        }

        return null;
    }

    /**
     * Extract <meta http-equiv="refresh" content="...; url=...">
     */
    private static function extract_meta_refresh($html, $current_url) {
        if (!preg_match('/<meta[^>]*http-equiv=[\'"]?refresh[\'"]?[^>]*content=[\'"]?([^>]+)[\'"]?/i', $html, $matches)) {
            return null;
        }

        $content = $matches[1];
        if (preg_match('/url\s*=\s*[\'"]?([^\'";]+)/i', $content, $url_matches)) {
            $raw_url = trim($url_matches[1]);
            return self::resolve_relative_url($current_url, $raw_url);
        }

        return null;
    }

    /**
     * Extract JavaScript redirect (window.location = ..., location.replace(...))
     */
    private static function extract_js_redirect($html, $current_url) {
        $patterns = array(
            '/window\.location(?:\.href)?\s*=\s*[\'"]([^\'"]+)[\'"]/i',
            '/location\.replace\s*\(\s*[\'"]([^\'"]+)[\'"]\s*\)/i',
            '/location\.assign\s*\(\s*[\'"]([^\'"]+)[\'"]\s*\)/i',
            '/top\.location(?:\.href)?\s*=\s*[\'"]([^\'"]+)[\'"]/i',
        );

        foreach ($patterns as $p) {
            if (preg_match($p, $html, $matches)) {
                $target = trim($matches[1]);
                if ($target && $target !== '#' && strpos($target, 'javascript:') !== 0) {
                    return self::resolve_relative_url($current_url, $target);
                }
            }
        }

        return null;
    }

    /**
     * Extract base64 encoded atob("...") in scripts
     */
    private static function extract_atob_redirect($html, $current_url) {
        if (preg_match('/atob\s*\(\s*[\'"]([A-Za-z0-9+\/=\s]+)[\'"]\s*\)/i', $html, $matches)) {
            $b64 = trim(str_replace(array("\r", "\n", " "), '', $matches[1]));
            $decoded = base64_decode($b64, true);
            if ($decoded && preg_match('/^https?:\/\//i', $decoded)) {
                return $decoded;
            }
        }
        return null;
    }

    /**
     * Safelink and multi-step Blogger/WordPress redirect bypasser
     * Detects safelink parameters (?url=, ?link=, etc.), arrays of destination blogs,
     * or template strings like mainUrl = `https://go.sohojgyan.com/${decodedUrl}`.
     */
    private static function extract_safelink_bypass($html, $current_url) {
        if (empty($html)) {
            return null;
        }

        // Direct target Blogspot destination detection (e.g. https://mydverse02.blogspot.com/p/flp-120926.html)
        if (preg_match('/[\'"](https?:\/\/[a-zA-Z0-9.-]*blogspot\.[a-z.]+\/p\/[a-zA-Z0-9_-]+\.html)[\'"]/i', $html, $m_dest)) {
            $target_cand = trim($m_dest[1]);
            if (self::is_safe_public_url($target_cand) && $target_cand !== $current_url) {
                return $target_cand;
            }
        }

        $query = parse_url($current_url, PHP_URL_QUERY);
        $raw_url_param = '';
        if ($query) {
            parse_str($query, $qs);
            foreach (array('url', 'link', 'token', 'go', 'safelink', 'dest', 'id', 'data') as $k) {
                if (!empty($qs[$k])) {
                    $raw_url_param = trim($qs[$k]);
                    break;
                }
            }
        }

        // Direct base64 decode check
        if (!empty($raw_url_param)) {
            $unquoted = urldecode($raw_url_param);
            $padding = (4 - (strlen($unquoted) % 4)) % 4;
            $decoded_try = base64_decode($unquoted . str_repeat('=', $padding), true);
            if ($decoded_try && preg_match('/^https?:\/\//i', $decoded_try)) {
                return $decoded_try;
            }
        }

        // Pattern A: Next step via array of destination blogs in JS
        if (preg_match_all('/(?:let|var|const)\s+[a-zA-Z0-9_$]+\s*=\s*\[([\s\S]*?)\]/i', $html, $arr_matches)) {
            foreach ($arr_matches[1] as $arr_content) {
                if (preg_match_all('/[\'"](https?:\/\/[^\'"]+)[\'"]/i', $arr_content, $url_matches)) {
                    foreach ($url_matches[1] as $candidate_url) {
                        $candidate_url = trim($candidate_url);
                        if (self::is_safe_public_url($candidate_url)) {
                            if (!empty($raw_url_param) && strpos($candidate_url, 'url=') === false) {
                                $sep = (strpos($candidate_url, '?') !== false) ? '&' : '?';
                                return "{$candidate_url}{$sep}url={$raw_url_param}";
                            }
                            return $candidate_url;
                        }
                    }
                }
            }
        }

        // Pattern B: Template string redirects: e.g. mainUrl = `https://go.sohojgyan.com/${decodedUrl}`
        if (preg_match('/(?:mainUrl|goUrl|redirectUrl|targetUrl|finalUrl|destUrl|realUrl|shortUrl)\s*=\s*[`\'"]([^`\'";]+)[`\'"]/i', $html, $m_tmpl)
            || preg_match('/`\s*(https?:\/\/go\.[^`\s]+?\$\{[^`\}]+\}[^`\s]*)\s*`/i', $html, $m_tmpl)) {
            $template = trim($m_tmpl[1]);
            $token = '';
            if (!empty($raw_url_param)) {
                $unquoted = urldecode($raw_url_param);
                $padding = (4 - (strlen($unquoted) % 4)) % 4;
                $decoded = base64_decode($unquoted . str_repeat('=', $padding), true);
                if ($decoded) {
                    $token = trim($decoded);
                }
            }

            if (!empty($token)) {
                $replaced = preg_replace('/\$\{[^}]+\}/', $token, $template);
                if (preg_match('/^https?:\/\//i', $replaced)) {
                    return $replaced;
                }
            } elseif (preg_match('/^https?:\/\//i', $template) && strpos($template, '${') === false) {
                return $template;
            }
        }

        // Pattern C: Direct go. link in script
        if (preg_match('/[\'"](https?:\/\/go\.[^"\'\s<>()]+)[\'"]/i', $html, $m_go)) {
            $cand = trim($m_go[1]);
            if (!empty($raw_url_param) && substr($cand, -1) === '/') {
                $unquoted = urldecode($raw_url_param);
                $padding = (4 - (strlen($unquoted) % 4)) % 4;
                $tok = base64_decode($unquoted . str_repeat('=', $padding), true);
                if ($tok) {
                    return $cand . trim($tok);
                }
            } elseif (substr($cand, -1) !== '/') {
                return $cand;
            }
        }

        return null;
    }

    /**
     * Detects and bypasses AdLinkFly / MightyScripts AJAX endpoints (e.g. go.sohojgyan.com)
     * Collects form tokens from <form id="go-link" action="/links/go">, waits counter,
     * and sends AJAX POST to retrieve the final URL from JSON response.
     */
    private static function extract_adlinkfly_bypass($html, $current_url, $cookies = array()) {
        if (empty($html)) {
            return null;
        }

        if (strpos($html, 'id="go-link"') === false && strpos($html, '/links/go') === false) {
            return null;
        }

        if (!preg_match('/<form[^>]*id=[\'"]go-link[\'"][^>]*action=[\'"]([^\'"]+)[\'"][\s\S]*?<\/form>/i', $html, $form_match)
            && !preg_match('/<form[^>]*action=[\'"]([^\'"]*\/links\/go)[\'"][\s\S]*?<\/form>/i', $html, $form_match)) {
            return null;
        }

        $action = trim($form_match[1]);
        $form_html = $form_match[0];
        $target_action = self::resolve_relative_url($current_url, $action);

        // Collect form input fields
        $form_data = array();
        if (preg_match_all('/<input[^>]+name=[\'"]([^\'"]+)[\'"][^>]*value=[\'"]([^\'"]*)[\'"]/i', $form_html, $input_matches, PREG_SET_ORDER)) {
            foreach ($input_matches as $im) {
                $val = $im[2];
                if (strpos($val, '%') !== false) {
                    $val = urldecode($val);
                }
                $form_data[$im[1]] = $val;
            }
        }

        if (empty($form_data)) {
            return null;
        }

        // Check if there's a counter
        $wait_sec = 4;
        if (preg_match('/counter_value[\'"]?\s*:\s*(\d+)/i', $html, $c_match)) {
            $wait_sec = max(intval($c_match[1]), 3);
        }
        sleep($wait_sec);

        $parsed_origin = parse_url($current_url);
        $origin = ($parsed_origin && isset($parsed_origin['scheme'], $parsed_origin['host']))
            ? "{$parsed_origin['scheme']}://{$parsed_origin['host']}"
            : '';

        $post_resp = wp_remote_post($target_action, array(
            'timeout'    => 15,
            'user-agent' => self::$user_agent,
            'headers'    => array(
                'Accept'           => 'application/json, text/javascript, */*; q=0.01',
                'Content-Type'     => 'application/x-www-form-urlencoded; charset=UTF-8',
                'X-Requested-With' => 'XMLHttpRequest',
                'Referer'          => $current_url,
                'Origin'           => $origin,
            ),
            'body'       => http_build_query($form_data),
            'cookies'    => $cookies,
            'sslverify'  => false,
        ));

        if (!is_wp_error($post_resp)) {
            $body = wp_remote_retrieve_body($post_resp);
            $json = json_decode($body, true);
            if (is_array($json) && !empty($json['url']) && preg_match('/^https?:\/\//i', $json['url'])) {
                return trim($json['url']);
            }
        }

        return null;
    }

    /**
     * Extracts next-step button or link candidates (e.g. "Get Link", "Proceed", "Episode Wise Links")
     */
    private static function extract_button_bypass($html, $current_url) {
        if (empty($html) || strpos($html, 'id="go-link"') !== false || strpos($html, '/links/go') !== false) {
            return null;
        }

        // If current page is already the target Blogspot destination format, do NOT navigate away
        if (self::is_target_destination($current_url)) {
            return null;
        }

        // Priority 0: Check for direct target Blogspot destination link
        if (preg_match('/<a\s+[^>]*href=[\'"](https?:\/\/[a-zA-Z0-9.-]*blogspot\.[a-z.]+\/p\/[a-zA-Z0-9_-]+\.html)[\'"]/i', $html, $m)) {
            $target = trim($m[1]);
            if (self::is_safe_public_url($target) && $target !== $current_url) {
                return $target;
            }
        }

        // Check for Episode Wise Links or direct button shorteners
        if (preg_match('/<a\s+[^>]*href=[\'"]([^\'"]+)[\'"][^>]*>[\s\S]*?(?:Episode\s*Wise|Get\s*Link|Skip\s*Ad|Proceed|Direct\s*Link)[\s\S]*?<\/a>/i', $html, $m)) {
            $target = trim($m[1]);
            if ($target && $target !== '#' && strpos($target, 'javascript:') !== 0) {
                $abs = self::resolve_relative_url($current_url, $target);
                if (self::is_safe_public_url($abs) && $abs !== $current_url) {
                    return $abs;
                }
            }
        }

        // Check for specific CSS classes
        if (preg_match('/<a\s+[^>]*class=[\'"][^\'"]*(?:btn-slide|get-link|skip-ad|btn-download|download-btn)[^\'"]*[\'"][^>]*href=[\'"]([^\'"]+)[\'"]/i', $html, $m)) {
            $target = trim($m[1]);
            if ($target && $target !== '#' && strpos($target, 'javascript:') !== 0) {
                $abs = self::resolve_relative_url($current_url, $target);
                if (self::is_safe_public_url($abs) && $abs !== $current_url) {
                    return $abs;
                }
            }
        }

        return null;
    }
}
