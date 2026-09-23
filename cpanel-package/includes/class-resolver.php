<?php
/**
 * Standalone cPanel URL Resolver Engine
 * High-performance, pure-PHP cURL resolver with session cookie persistence,
 * AdLinkFly/Sohojgyan gateway bypassing, and Blogspot episode destination validation.
 * Optimized for shared hosting without requiring external Python binaries or CLI dependencies.
 */

class SLEA_Resolver {
    private static $user_agent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36';

    /**
     * Check if a URL matches the target Blogspot episode page structure:
     * Format: https://mydverse02.blogspot.com/p/*.html
     * e.g. https://mydverse02.blogspot.com/p/flp-120926.html
     */
    public static function is_target_destination($url) {
        if (empty($url) || !is_string($url)) {
            return false;
        }

        $host = strtolower(parse_url($url, PHP_URL_HOST) ?: '');
        $path = parse_url($url, PHP_URL_PATH) ?: '';
        $query = parse_url($url, PHP_URL_QUERY) ?: '';

        // Intermediate safelink / tracker query parameters are NEVER the final destination
        if (!empty($query)) {
            $bad_params = ['url=', 'dest=', 'link=', 'token=', 'go=', 'safelink='];
            foreach ($bad_params as $bp) {
                if (stripos($query, $bp) !== false) {
                    return false;
                }
            }
        }

        // Exclude shortener/gateway hosts
        $excluded_keywords = [
            'sohojgyan', 'shrt.', 'go.', 'adlinkfly', 'ouo.io', 'bit.ly',
            'droplink', 'gplinks', 'shrinkme', 'tinyurl', 'teknosimple'
        ];
        foreach ($excluded_keywords as $keyword) {
            if (strpos($host, $keyword) !== false) {
                return false;
            }
        }

        // Exclude intermediate safelinks with query parameters
        if (!empty($query)) {
            $bad_params = ['url=', 'dest=', 'token=', 'link=', 'go=', 'safelink='];
            foreach ($bad_params as $bp) {
                if (stripos($query, $bp) !== false) {
                    return false;
                }
            }
        }

        // Exclude intermediate hosting safelinks
        if (strpos($host, 'mydriveverse') !== false && !empty($query)) {
            return false;
        }

        // Exclude system pages
        $bad_slugs = ['choose-best', 'web-hosting', 'seo-tips', 'sitemap', 'privacy', 'cookie', 'about', 'terms', 'contact'];
        foreach ($bad_slugs as $bs) {
            if (stripos($path, $bs) !== false) {
                return false;
            }
        }

        // 1. Any Blogspot page with /p/*.html or blog post path
        if (strpos($host, 'blogspot.') !== false) {
            if (preg_match('#/p/[a-zA-Z0-9_-]+\.html#i', $path)) {
                return true;
            }
            if (preg_match('#/\d{4}/\d{2}/[a-zA-Z0-9_-]+\.html#i', $path)) {
                return true;
            }
            if (substr($path, -5) === '.html') {
                return true;
            }
        }

        // 2. Any domain matching mydverse, dramaverse, moviehub, or dverse
        if (preg_match('/(?:mydverse|dramaverse|dverse|moviehub)/i', $host)) {
            if (substr($path, -5) === '.html' || strpos($path, '/p/') !== false) {
                return true;
            }
        }

        // 3. Any standard page ending in .html with valid path length
        if (substr($path, -5) === '.html' && strlen($path) > 7) {
            return true;
        }

        return false;
    }

    /**
     * Creates a temporary cookie file in a safe location respecting cPanel open_basedir
     */
    public static function get_temp_cookie_file($prefix = 'ck') {
        $cookie_dir = null;
        if (defined('DATA_DIR') && is_dir(DATA_DIR)) {
            $cookie_dir = DATA_DIR . '/cookies';
        } else {
            $cookie_dir = dirname(__DIR__) . '/data/cookies';
        }

        if (!is_dir($cookie_dir)) {
            @mkdir($cookie_dir, 0755, true);
        }

        if (is_dir($cookie_dir) && is_writable($cookie_dir)) {
            $f = $cookie_dir . '/slea_' . $prefix . '_' . uniqid() . '.tmp';
            @touch($f);
            return $f;
        }

        $fallback_dir = dirname(__DIR__) . '/cache';
        if (!is_dir($fallback_dir)) {
            @mkdir($fallback_dir, 0755, true);
        }
        if (is_dir($fallback_dir) && is_writable($fallback_dir)) {
            $f = $fallback_dir . '/slea_' . $prefix . '_' . uniqid() . '.tmp';
            @touch($f);
            return $f;
        }

        return @tempnam(sys_get_temp_dir(), 'slea_') ?: (sys_get_temp_dir() . '/slea_' . uniqid());
    }

    /**
     * Specialized direct resolver for safe.sohojgyan.com shortlinks
     * Supports formats:
     * - https://safe.sohojgyan.com/r03N3f
     * - https://safe.sohojgyan.com/JX6N5o
     * - https://sohojgyan.com/.../?code=r03N3f
     * Calls the official JSON decode endpoint: https://safe.sohojgyan.com/api/decode/{code}
     */
    public static function resolve_safe_sohojgyan($url) {
        if (empty($url) || !is_string($url)) {
            return null;
        }

        $code = '';
        if (preg_match('#safe\.sohojgyan\.com/([a-zA-Z0-9_-]+)#i', $url, $m)) {
            $candidate = trim($m[1]);
            if ($candidate !== 'api' && $candidate !== 'decode') {
                $code = $candidate;
            }
        }
        if (empty($code) && preg_match('#[?&]code=([a-zA-Z0-9_-]+)#i', $url, $m)) {
            $code = trim($m[1]);
        }
        if (empty($code) && preg_match('#/(?:r|s|d|c)/([a-zA-Z0-9_-]+)#i', $url, $m)) {
            $code = trim($m[1]);
        }

        if (!empty($code) && strlen($code) >= 3) {
            $api_url = "https://safe.sohojgyan.com/api/decode/" . urlencode($code);
            $raw_json = '';

            // 1. Try cURL with explicit Accept and Referer headers
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $api_url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 12);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
            curl_setopt($ch, CURLOPT_USERAGENT, self::$user_agent);
            curl_setopt($ch, CURLOPT_REFERER, 'https://sohojgyan.com/choose-the-right-web-hosting/');
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Accept: application/json, text/plain, */*',
                'Origin: https://sohojgyan.com'
            ]);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_ENCODING, '');
            $raw_json = curl_exec($ch);
            curl_close($ch);

            // 2. Stream context fallback if cURL returned empty or failed
            if (empty($raw_json)) {
                $context = stream_context_create([
                    'http' => [
                        'method' => 'GET',
                        'header' => "Accept: application/json, text/plain, */*\r\nReferer: https://sohojgyan.com/choose-the-right-web-hosting/\r\nUser-Agent: " . self::$user_agent . "\r\n",
                        'timeout' => 12,
                        'follow_location' => 1
                    ],
                    'ssl' => [
                        'verify_peer' => false,
                        'verify_peer_name' => false
                    ]
                ]);
                $raw_json = @file_get_contents($api_url, false, $context);
            }

            if (!empty($raw_json)) {
                $data = json_decode(trim($raw_json), true);
                if (!empty($data['success']) && !empty($data['data']['original_url'])) {
                    $orig = trim($data['data']['original_url']);
                    if (!empty($orig) && filter_var($orig, FILTER_VALIDATE_URL)) {
                        return $orig;
                    }
                }
            }
        }
        return null;
    }

    /**
     * Resolves an AdLinkFly / MightyScripts gateway page (such as go.sohojgyan.com/{code})
     * Pure PHP cURL with session cookie jar, counter wait, and AJAX POST token validation.
     */
    public static function bypass_adlinkfly_gateway($gateway_url, &$chain_log = []) {
        $cookie_file = self::get_temp_cookie_file();

        try {
            // Step 1: GET gateway page to establish session cookies and load CSRF tokens
            $get_res = self::fetch_url_curl($gateway_url, 10, $cookie_file);
            if (empty($get_res['body'])) {
                @unlink($cookie_file);
                return null;
            }

            $html = $get_res['body'];
            $chain_log[] = [
                'step'   => count($chain_log) + 1,
                'url'    => $gateway_url,
                'status' => $get_res['status'],
                'type'   => 'AdLinkFly Shortener Gateway'
            ];

            // Step 2: Parse form action and inputs (support both id="go-link" and action="/links/go")
            $form_action = '';
            $form_html = '';

            if (preg_match('/<form[^>]*id=[\'"]go-link[\'"][^>]*>([\s\S]*?)<\/form>/i', $html, $form_m)) {
                $form_html = $form_m[0];
                if (preg_match('/action=[\'"]([^\'"]+)[\'"]/i', $form_m[0], $act_m)) {
                    $form_action = $act_m[1];
                }
            } elseif (preg_match('/<form[^>]*action=[\'"]([^\'"]*\/links\/go[^\'"]*)[\'"][^>]*>([\s\S]*?)<\/form>/i', $html, $form_m)) {
                $form_action = $form_m[1];
                $form_html   = $form_m[0];
            }

            if (empty($form_html)) {
                @unlink($cookie_file);
                return null;
            }

            if (empty($form_action)) {
                $form_action = '/links/go';
            }

            $action_url = self::normalize_url($form_action, $gateway_url);

            // Extract all form inputs preserving literal values without mangling token signatures
            $post_parts = [];
            if (preg_match_all('/<input\b[^>]*>/i', $form_html, $input_tags)) {
                foreach ($input_tags[0] as $tag) {
                    if (preg_match('/\bname=[\'"]([^\'"]+)[\'"]/i', $tag, $nm)) {
                        $name = $nm[1];
                        $val = '';
                        if (preg_match('/\bvalue=[\'"]([^\'"]*)[\'"]/i', $tag, $vm)) {
                            $val = $vm[1];
                        }
                        $post_parts[] = urlencode($name) . '=' . urlencode($val);
                    }
                }
            }

            if (empty($post_parts)) {
                @unlink($cookie_file);
                return null;
            }

            $post_body = implode('&', $post_parts);

            // Step 3: Determine counter wait time (default 5s)
            $counter = 5;
            if (preg_match('/counter_value["\']?\s*:\s*(\d+)/i', $html, $cm)) {
                $counter = intval($cm[1]);
            }
            $wait_seconds = max($counter, 4) + 0.5;
            usleep(intval($wait_seconds * 1000000));

            // Step 4: Send AJAX POST request with session cookies
            $parsed_url = parse_url($gateway_url);
            $origin = ($parsed_url['scheme'] ?? 'https') . '://' . ($parsed_url['host'] ?? 'go.sohojgyan.com');

            $post_res = self::post_curl_json($action_url, $post_body, $gateway_url, $origin, $cookie_file);

            // Attempt retry once if clock-skew or network hiccup occurs
            if (empty($post_res['url'])) {
                usleep(800000);
                $post_res = self::post_curl_json($action_url, $post_body, $gateway_url, $origin, $cookie_file);
            }

            @unlink($cookie_file);

            if (!empty($post_res['url'])) {
                $final_dest = trim($post_res['url']);
                $chain_log[] = [
                    'step'     => count($chain_log) + 1,
                    'url'      => $action_url,
                    'status'   => 200,
                    'type'     => 'AdLinkFly AJAX Bypass Success',
                    'next_url' => $final_dest
                ];
                return $final_dest;
            }

        } catch (Throwable $e) {
            @unlink($cookie_file);
        }

        @unlink($cookie_file);
        return null;
    }

    /**
     * Resolves shortened URL with retry logic until the destination matches target Blogspot structure.
     */
    public static function resolve_shortlink_until_target($initial_url, $options = [], $max_retries = 2) {
        $initial_url = trim($initial_url);

        // Fast Check 0: Already the verified target destination
        if (self::is_target_destination($initial_url)) {
            return [
                'original'                   => $initial_url,
                'final'                      => $initial_url,
                'redirects'                  => 0,
                'attempts'                   => 1,
                'target_destination_verified'=> true,
                'chain'                      => [
                    [
                        'step'   => 1,
                        'url'    => $initial_url,
                        'status' => 200,
                        'type'   => 'Target Destination (Blogspot Episode Page)'
                    ]
                ],
                'final_html'                 => ''
            ];
        }

        // Fast Check 1: Safe SohojGyan direct format or any code parameter (e.g. https://safe.sohojgyan.com/r03N3f)
        if (stripos($initial_url, 'safe.sohojgyan') !== false ||
            (stripos($initial_url, 'sohojgyan') !== false && stripos($initial_url, 'code=') !== false) ||
            preg_match('#[?&]code=([a-zA-Z0-9_-]+)#i', $initial_url)) {
            $safe_direct = self::resolve_safe_sohojgyan($initial_url);
            if (!empty($safe_direct)) {
                list($dest_url, $fetched_html) = SLEA_Extractor::fetch_page($safe_direct);
                $final_target = !empty($dest_url) ? $dest_url : $safe_direct;
                $is_target = self::is_target_destination($final_target) || self::is_target_destination($safe_direct);

                return [
                    'original'                   => $initial_url,
                    'final'                      => $final_target,
                    'redirects'                  => 1,
                    'attempts'                   => 1,
                    'target_destination_verified'=> $is_target,
                    'chain'                      => [
                        [
                            'step'   => 1,
                            'url'    => $initial_url,
                            'status' => 302,
                            'type'   => 'Safe SohojGyan Shortlink Gateway'
                        ],
                        [
                            'step'   => 2,
                            'url'    => $final_target,
                            'status' => 200,
                            'type'   => 'Target Destination (Blogspot Episode Page)'
                        ]
                    ],
                    'final_html'                 => $fetched_html ?: ''
                ];
            }
        }

        // Fast Check 2: Direct SohojGyan Shortlink bypass (shrt.sohojgyan.com/{code})
        if (preg_match('#(?:shrt\.sohojgyan\.com|go\.sohojgyan\.com)/([a-zA-Z0-9_-]{3,30})#i', $initial_url, $cm)) {
            $short_code = $cm[1];
            $gateway_url = "https://go.sohojgyan.com/{$short_code}";
            $chain = [
                [
                    'step'   => 1,
                    'url'    => $initial_url,
                    'status' => 302,
                    'type'   => 'Shortlink Gateway'
                ]
            ];
            $bypassed = self::bypass_adlinkfly_gateway($gateway_url, $chain);
            if (!empty($bypassed)) {
                if (self::is_target_destination($bypassed)) {
                    $chain[] = [
                        'step'   => count($chain) + 1,
                        'url'    => $bypassed,
                        'status' => 200,
                        'type'   => 'Target Destination (Blogspot Episode Page)'
                    ];
                    return [
                        'original'                   => $initial_url,
                        'final'                      => $bypassed,
                        'redirects'                  => count($chain) - 1,
                        'attempts'                   => 1,
                        'target_destination_verified'=> true,
                        'chain'                      => $chain,
                        'final_html'                 => ''
                    ];
                } else {
                    // Continue resolution from bypassed intermediate link
                    $sub_res = self::resolve_url($bypassed, $options);
                    if (!empty($sub_res['final']) && self::is_target_destination($sub_res['final'])) {
                        $full_chain = array_merge($chain, $sub_res['chain'] ?? []);
                        return [
                            'original'                   => $initial_url,
                            'final'                      => $sub_res['final'],
                            'redirects'                  => count($full_chain) - 1,
                            'attempts'                   => 1,
                            'target_destination_verified'=> true,
                            'chain'                      => $full_chain,
                            'final_html'                 => $sub_res['final_html'] ?? ''
                        ];
                    }
                }
            }
        }

        // General Resolution Loop
        $last_res = null;
        for ($attempt = 1; $attempt <= $max_retries; $attempt++) {
            $target_url = $initial_url;

            $res = self::resolve_url($target_url, $options);
            $last_res = $res;

            if (!empty($res['final']) && self::is_target_destination($res['final'])) {
                $last_res['attempts'] = $attempt;
                $last_res['target_destination_verified'] = true;
                return $last_res;
            }

            // If not verified and Python is available on hosting, try Python as final fallback
            if ($attempt === $max_retries && !self::is_target_destination($last_res['final'] ?? '')) {
                $py_res = self::resolve_via_python($initial_url);
                if ($py_res && self::is_target_destination($py_res['final'] ?? '')) {
                    $py_res['attempts'] = $attempt;
                    $py_res['target_destination_verified'] = true;
                    return $py_res;
                }
            }

            if ($attempt < $max_retries) {
                sleep(1);
            }
        }

        if ($last_res) {
            $last_res['attempts'] = $max_retries;
            $last_res['target_destination_verified'] = self::is_target_destination($last_res['final'] ?? '');
        }

        return $last_res;
    }

    /**
     * Resolve a URL through all HTTP redirects, meta refreshes, AdLinkFly forms, and script hops.
     * Pure PHP cURL with persistent session cookie jar.
     */
    public static function resolve_url($initial_url, $options = []) {
        $max_hops = isset($options['max_redirects']) ? intval($options['max_redirects']) : 12;
        $timeout  = isset($options['timeout']) ? intval($options['timeout']) : 8;

        $chain   = [];
        $visited = [];
        $current = self::normalize_url($initial_url);
        $final_html = '';

        $cookie_file = self::get_temp_cookie_file('chain');

        while (count($chain) < $max_hops) {
            if (empty($current) || in_array($current, $visited, true)) {
                break;
            }
            $visited[] = $current;

            // Fast Check: If current is already target destination
            if (self::is_target_destination($current)) {
                $chain[] = [
                    'step'   => count($chain) + 1,
                    'url'    => $current,
                    'status' => 200,
                    'type'   => 'Target Destination (Blogspot Episode Page)'
                ];
                break;
            }

            // Check if current is safe.sohojgyan or has code= parameter
            if (stripos($current, 'safe.sohojgyan') !== false || (stripos($current, 'sohojgyan') !== false && stripos($current, 'code=') !== false)) {
                $decoded = self::resolve_safe_sohojgyan($current);
                if (!empty($decoded)) {
                    $chain[] = [
                        'step'     => count($chain) + 1,
                        'url'      => $current,
                        'status'   => 302,
                        'type'     => 'Safe SohojGyan Gateway Decoded',
                        'next_url' => $decoded
                    ];
                    $current = $decoded;
                    if (self::is_target_destination($current)) {
                        $chain[] = [
                            'step'   => count($chain) + 1,
                            'url'    => $current,
                            'status' => 200,
                            'type'   => 'Target Destination (Blogspot Episode Page)'
                        ];
                        break;
                    }
                    continue;
                }
            }

            // Check if current is go.sohojgyan.com gateway: execute bypass
            if (stripos($current, 'go.sohojgyan.com') !== false && !stripos($current, '/links/go')) {
                $bypassed = self::bypass_adlinkfly_gateway($current, $chain);
                if (!empty($bypassed)) {
                    $current = $bypassed;
                    if (self::is_target_destination($current)) {
                        $chain[] = [
                            'step'   => count($chain) + 1,
                            'url'    => $current,
                            'status' => 200,
                            'type'   => 'Target Destination (Blogspot Episode Page)'
                        ];
                        break;
                    }
                    continue;
                }
            }

            // Execute HTTP Request with cURL
            $response = self::fetch_url_curl($current, $timeout, $cookie_file);

            if ($response['error']) {
                $chain[] = [
                    'step'   => count($chain) + 1,
                    'url'    => $current,
                    'status' => 0,
                    'type'   => 'Fetch Error: ' . $response['error']
                ];
                break;
            }

            $status = $response['status'];
            $body   = $response['body'];
            $final_html = $body;

            // 1. Check HTTP Redirect (301, 302, 303, 307, 308)
            if ($response['redirect_url']) {
                $next = self::normalize_url($response['redirect_url'], $current);
                $chain[] = [
                    'step'     => count($chain) + 1,
                    'url'      => $current,
                    'status'   => $status,
                    'type'     => "HTTP Redirect ({$status})",
                    'next_url' => $next
                ];

                if (self::is_target_destination($next)) {
                    $chain[] = [
                        'step'   => count($chain) + 1,
                        'url'    => $next,
                        'status' => 200,
                        'type'   => 'Target Destination (Blogspot Episode Page)'
                    ];
                    $current = $next;
                    break;
                }

                $current = $next;
                continue;
            }

            // 2. Check if current URL is already the target destination
            if (self::is_target_destination($current)) {
                $chain[] = [
                    'step'   => count($chain) + 1,
                    'url'    => $current,
                    'status' => $status,
                    'type'   => 'Target Destination (Blogspot Episode Page)'
                ];
                break;
            }

            // 3. Inspect URL query parameters for encoded safelink destination or token
            // e.g. mydriveverse.blogspot.com/p/...html?url=SWowM25kSg%3D%3D
            $token_dest = self::extract_safelink_token_dest($current, $body);
            if ($token_dest && $token_dest !== $current && !in_array($token_dest, $visited, true)) {
                $chain[] = [
                    'step'     => count($chain) + 1,
                    'url'      => $current,
                    'status'   => $status,
                    'type'     => 'Safelink Query Token Unpacked',
                    'next_url' => $token_dest
                ];
                $current = $token_dest;
                continue;
            }

            // 4. Inspect HTML for AdLinkFly form
            if (stripos($body, '/links/go') !== false) {
                $bypassed = self::bypass_adlinkfly_gateway($current, $chain);
                if (!empty($bypassed)) {
                    $current = $bypassed;
                    if (self::is_target_destination($current)) {
                        $chain[] = [
                            'step'   => count($chain) + 1,
                            'url'    => $current,
                            'status' => 200,
                            'type'   => 'Target Destination (Blogspot Episode Page)'
                        ];
                        break;
                    }
                    continue;
                }
            }

            // 5. Inspect HTML for Meta Refresh
            $meta_url = self::extract_meta_refresh($body, $current);
            if ($meta_url && $meta_url !== $current && !in_array($meta_url, $visited, true)) {
                $chain[] = [
                    'step'     => count($chain) + 1,
                    'url'      => $current,
                    'status'   => $status,
                    'type'     => 'HTML Meta Refresh',
                    'next_url' => $meta_url
                ];
                $current = $meta_url;
                continue;
            }

            // 6. Inspect JavaScript location redirects
            $js_url = self::extract_js_redirect($body, $current);
            if ($js_url && $js_url !== $current && !in_array($js_url, $visited, true)) {
                $chain[] = [
                    'step'     => count($chain) + 1,
                    'url'      => $current,
                    'status'   => $status,
                    'type'     => 'JavaScript Location Redirect',
                    'next_url' => $js_url
                ];
                $current = $js_url;
                continue;
            }

            // 7. Inspect direct Blogspot episode link in HTML
            $direct_link = self::extract_target_link_from_html($body, $current);
            if ($direct_link && $direct_link !== $current && !in_array($direct_link, $visited, true)) {
                $chain[] = [
                    'step'     => count($chain) + 1,
                    'url'      => $current,
                    'status'   => $status,
                    'type'     => 'Identified Blogspot Episode Link in HTML',
                    'next_url' => $direct_link
                ];
                $current = $direct_link;
                continue;
            }

            // Terminal page reached
            $chain[] = [
                'step'   => count($chain) + 1,
                'url'    => $current,
                'status' => $status,
                'type'   => 'Destination Page'
            ];
            break;
        }

        @unlink($cookie_file);

        return [
            'original'   => $initial_url,
            'final'      => $current,
            'redirects'  => max(0, count($chain) - 1),
            'chain'      => $chain,
            'final_html' => $final_html
        ];
    }

    /**
     * Inspects query parameters (e.g. ?url=..., ?token=..., ?link=...) for base64 encoded destination or shortcode.
     */
    private static function extract_safelink_token_dest($current_url, $html = '') {
        $parsed = parse_url($current_url);
        $query_str = $parsed['query'] ?? '';
        if (empty($query_str)) return null;

        parse_str($query_str, $params);
        $keys = ['url', 'link', 'token', 'dest', 'go', 'safelink', 'code'];
        foreach ($keys as $k) {
            if (!empty($params[$k])) {
                $raw = trim($params[$k]);
                $decoded = self::safe_base64_decode($raw);
                if (!empty($decoded)) {
                    // Case A: Directly decodes to a valid URL
                    if (filter_var($decoded, FILTER_VALIDATE_URL)) {
                        $p = parse_url($decoded);
                        if (!empty($p['host']) && $p['host'] !== ($parsed['host'] ?? '')) {
                            return $decoded;
                        }
                    }
                    // Case B: Decodes to a shortcode (e.g. "Ij03ndJ" or "AAhg")
                    if (preg_match('/^[a-zA-Z0-9_-]{3,30}$/', $decoded)) {
                        $candidate = "https://go.sohojgyan.com/{$decoded}";
                        if ($candidate !== $current_url && strpos($current_url, $decoded) === false) {
                            return $candidate;
                        }
                    }
                }
            }
        }

        // Look for mainUrl or template strings in html
        if (!empty($html)) {
            if (preg_match('/(?:mainUrl|goUrl|redirectUrl|targetUrl)\s*=\s*[`\'"](https?:\/\/go\.sohojgyan\.com\/[^`\'"]+)[`\'"]/i', $html, $tm)) {
                return $tm[1];
            }
        }

        return null;
    }

    private static function safe_base64_decode($str) {
        $str = rawurldecode($str);
        $padding = (4 - (strlen($str) % 4)) % 4;
        $str .= str_repeat('=', $padding);
        $decoded = @base64_decode($str, true);
        return $decoded ? trim($decoded) : '';
    }

    private static function fetch_url_curl($url, $timeout = 8, $cookie_file = null) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 4);
        curl_setopt($ch, CURLOPT_USERAGENT, self::$user_agent);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_ENCODING, ''); // Accepts gzip / deflate for 5x faster network transfer

        if ($cookie_file) {
            curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie_file);
            curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie_file);
        }

        $raw_response = curl_exec($ch);
        $error = curl_error($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);

        $headers = substr($raw_response ?: '', 0, $header_size);
        $body    = substr($raw_response ?: '', $header_size);

        $redirect_url = null;
        if (in_array($status, [301, 302, 303, 307, 308])) {
            if (preg_match('/^Location:\s*([^\r\n]+)/mi', $headers, $matches)) {
                $redirect_url = trim($matches[1]);
            }
        }

        curl_close($ch);

        return [
            'status'       => $status,
            'body'         => $body,
            'error'        => $error,
            'redirect_url' => $redirect_url
        ];
    }

    private static function post_curl_json($url, $post_data, $referer, $origin, $cookie_file) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($post_data) ? http_build_query($post_data) : $post_data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 4);
        curl_setopt($ch, CURLOPT_USERAGENT, self::$user_agent);
        curl_setopt($ch, CURLOPT_REFERER, $referer);
        curl_setopt($ch, CURLOPT_ENCODING, '');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/json, text/javascript, */*; q=0.01',
            'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
            'X-Requested-With: XMLHttpRequest',
            'Origin: ' . $origin
        ]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        if ($cookie_file) {
            curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie_file);
            curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie_file);
        }

        $result = curl_exec($ch);
        curl_close($ch);

        if ($result) {
            $json = json_decode($result, true);
            if (is_array($json)) {
                return $json;
            }
        }
        return null;
    }

    private static function extract_meta_refresh($html, $base_url) {
        if (preg_match('/<meta[^>]*http-equiv=[\'"]refresh[\'"][^>]*content=[\'"]\s*\d+\s*;\s*url=([^\'\"]+)[\'"]/i', $html, $m)) {
            return self::normalize_url(trim($m[1]), $base_url);
        }
        return null;
    }

    private static function extract_js_redirect($html, $base_url) {
        $patterns = [
            '/(?:window\.)?location(?:\.href)?\s*=\s*[\'"]([^\'"]+)[\'"]/i',
            '/location\.replace\([\'"]([^\'"]+)[\'"]\)/i'
        ];
        foreach ($patterns as $p) {
            if (preg_match($p, $html, $m)) {
                $cand = trim($m[1]);
                if (!empty($cand) && !preg_match('/^(?:javascript:|#)/i', $cand)) {
                    return self::normalize_url($cand, $base_url);
                }
            }
        }
        return null;
    }

    private static function extract_target_link_from_html($html, $base_url) {
        if (empty($html)) return null;

        // 1. Any Blogspot /p/*.html or episode link in anchor tags
        if (preg_match_all('/<a\s+[^>]*href=[\'"]([^\'"]+)[\'"]/i', $html, $matches)) {
            foreach ($matches[1] as $cand) {
                $cand = self::normalize_url($cand, $base_url);
                if (self::is_target_destination($cand) && $cand !== $base_url) {
                    return $cand;
                }
            }
        }

        // 2. Look inside script tags or JavaScript variables for target destination URL
        if (preg_match_all('/[\'"](https?:\/\/[a-zA-Z0-9.-]+\.blogspot\.[a-z.]+\/p\/[a-zA-Z0-9_-]+\.html)[\'"]/i', $html, $smatches)) {
            foreach ($smatches[1] as $cand) {
                if (self::is_target_destination($cand) && $cand !== $base_url) {
                    return $cand;
                }
            }
        }

        // 3. Look for Episode Wise Links anchor (shortlink candidate to resolve next)
        if (preg_match('/<a\s+[^>]*href=[\'"]([^\'"]+)[\'"][^>]*>[\s\S]*?(?:Episode[\s_-]*Wise|Download[\s_-]*Episodes?|Click\s+Here|Get\s+Link)[\s\S]*?<\/a>/i', $html, $ewm)) {
            $cand = self::normalize_url($ewm[1], $base_url);
            if (stripos($cand, 'mydriveverse') === false && $cand !== $base_url) {
                return $cand;
            }
        }

        // 4. Look for SohojGyan decode API or safelink_code embedded in JavaScript
        if (stripos($html, 'safe.sohojgyan') !== false || stripos($html, 'safelink_code') !== false) {
            if (preg_match('#safe\.sohojgyan\.com/api/decode/([a-zA-Z0-9_-]+)#i', $html, $scm) ||
                preg_match('#safelink_code[\'"]?\s*,\s*[\'"]([a-zA-Z0-9_-]+)#i', $html, $scm) ||
                preg_match('#(?:code|safelink_code)\s*=\s*[\'"]([a-zA-Z0-9_-]{4,30})[\'"]#i', $html, $scm)) {
                $code = trim($scm[1]);
                $decoded = self::resolve_safe_sohojgyan("https://safe.sohojgyan.com/{$code}");
                if (!empty($decoded)) {
                    return $decoded;
                }
            }
        }

        return null;
    }

    /**
     * Fallback resolution via Python engine ONLY if python3 is confirmed installed on the hosting server.
     */
    private static function resolve_via_python($url) {
        // Check if exec is disabled in php.ini
        $disabled = explode(',', ini_get('disable_functions') ?: '');
        $disabled = array_map('trim', $disabled);
        if (in_array('proc_open', $disabled) || in_array('exec', $disabled)) {
            return null;
        }

        $python_bin = null;
        @exec('python3 --version 2>&1', $out, $code);
        if ($code === 0) {
            $python_bin = 'python3';
        } else {
            @exec('/usr/bin/python3 --version 2>&1', $out2, $code2);
            if ($code2 === 0) {
                $python_bin = '/usr/bin/python3';
            }
        }

        if ($python_bin && file_exists(__DIR__ . '/../resolver.py')) {
            $script_path = realpath(__DIR__ . '/../resolver.py');
            $descriptors = [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w']
            ];
            $process = @proc_open("$python_bin " . escapeshellarg($script_path) . " --json", $descriptors, $pipes);
            if (is_resource($process)) {
                fwrite($pipes[0], json_encode(['url' => $url]));
                fclose($pipes[0]);
                $stdout = stream_get_contents($pipes[1]);
                fclose($pipes[1]);
                fclose($pipes[2]);
                proc_close($process);

                if (!empty($stdout)) {
                    $json = json_decode(trim($stdout), true);
                    $data = (!empty($json['data']) && is_array($json['data'])) ? $json['data'] : $json;
                    if (is_array($data) && !empty($data['final']) && self::is_target_destination($data['final'])) {
                        return [
                            'original'   => $url,
                            'final'      => $data['final'],
                            'redirects'  => isset($data['redirects']) ? intval($data['redirects']) : 1,
                            'chain'      => isset($data['chain']) ? $data['chain'] : [],
                            'final_html' => isset($data['final_html']) ? $data['final_html'] : ''
                        ];
                    }
                }
            }
        }
        return null;
    }

    private static function normalize_url($url, $base = null) {
        $url = trim($url);
        if (empty($url)) return '';
        if (strpos($url, '//') === 0) {
            return 'https:' . $url;
        }
        if (strpos($url, 'http://') === 0 || strpos($url, 'https://') === 0) {
            return $url;
        }
        if ($base) {
            $parts = parse_url($base);
            $scheme = isset($parts['scheme']) ? $parts['scheme'] : 'https';
            $host   = isset($parts['host']) ? $parts['host'] : '';
            if (strpos($url, '/') === 0) {
                return "{$scheme}://{$host}{$url}";
            }
            $path = isset($parts['path']) ? dirname($parts['path']) : '/';
            $path = rtrim($path, '/');
            return "{$scheme}://{$host}{$path}/{$url}";
        }
        return 'https://' . $url;
    }
}
