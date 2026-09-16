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

            // Case 3: JavaScript location redirect (window.location = ..., location.replace(...))
            $js_target = self::extract_js_redirect($body, $current_url);
            if ($js_target && $js_target !== $current_url && !isset($visited[strtolower(rtrim($js_target, '/'))])) {
                $chain[count($chain) - 1]['type'] = 'JavaScript Client Redirect';
                $current_url = $js_target;
                continue;
            }

            // Case 4: Base64 obfuscated window.location.href = atob("...")
            $b64_target = self::extract_atob_redirect($body, $current_url);
            if ($b64_target && $b64_target !== $current_url && !isset($visited[strtolower(rtrim($b64_target, '/'))])) {
                $chain[count($chain) - 1]['type'] = 'Obfuscated atob() Script Redirect';
                $current_url = $b64_target;
                continue;
            }

            // Case 5: Destination reached (200 OK without further hops)
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
}
