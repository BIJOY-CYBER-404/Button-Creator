<?php
/**
 * Standalone cPanel URL Resolver Engine
 * Bypasses URL shorteners, follows redirect hops, and validates Blogspot episode destination format.
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
            $bad_params = ['url=', 'dest=', 'link=', 'token=', 'go='];
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

        // Explicitly REJECT mydriveverse.blogspot.com and intermediate hosting safelinks
        if (strpos($host, 'mydriveverse') !== false || strpos($url, 'mydriveverse') !== false) {
            return false;
        }

        // Exclude system pages
        $bad_slugs = ['choose-best', 'web-hosting', 'seo-tips', 'sitemap', 'privacy', 'cookie', 'about', 'terms', 'contact'];
        foreach ($bad_slugs as $bs) {
            if (stripos($path, $bs) !== false) {
                return false;
            }
        }

        // Strict Target Match: https://mydverse02.blogspot.com/p/*.html
        if ($host === 'mydverse02.blogspot.com' || strpos($host, 'mydverse02.blogspot.') !== false) {
            if (strpos($path, '/p/') !== false && substr($path, -5) === '.html') {
                $parts = explode('/p/', $path);
                $slug = substr(end($parts), 0, -5);
                if (!empty($slug) && preg_match('/^[a-zA-Z0-9_-]+$/', $slug)) {
                    return true;
                }
            }
        }

        if (preg_match('#https?://(?:www\.)?mydverse02\.blogspot\.[a-z.]+/p/[a-zA-Z0-9_-]+\.html#i', $url)) {
            return true;
        }

        return false;
    }

    /**
     * Specialized direct resolver for safe.sohojgyan.com shortlinks
     * e.g. https://safe.sohojgyan.com/JX6N5o or ?code=JX6N5o
     * Calls the official JSON decode endpoint: https://safe.sohojgyan.com/api/decode/{code}
     */
    public static function resolve_safe_sohojgyan($url) {
        if (empty($url) || !is_string($url)) {
            return null;
        }

        $code = '';
        if (preg_match('#safe\.sohojgyan\.com/([a-zA-Z0-9_-]+)#i', $url, $m)) {
            $code = $m[1];
        } elseif (preg_match('#[?&]code=([a-zA-Z0-9_-]+)#i', $url, $m)) {
            $code = $m[1];
        }

        if (!empty($code) && strlen($code) >= 3) {
            $api_url = "https://safe.sohojgyan.com/api/decode/" . urlencode($code);
            $res = self::fetch_url_curl($api_url, 8);
            if (!empty($res['body'])) {
                $data = json_decode(trim($res['body']), true);
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
     * Resolves shortened URL with retry logic until the destination matches target Blogspot structure.
     */
    public static function resolve_shortlink_until_target($initial_url, $options = [], $max_retries = 3) {
        $last_res = null;

        // Fast direct check: safe.sohojgyan.com format
        $safe_direct = self::resolve_safe_sohojgyan($initial_url);
        if (!empty($safe_direct) && self::is_target_destination($safe_direct)) {
            return [
                'original'                   => $initial_url,
                'final'                      => $safe_direct,
                'redirects'                  => 1,
                'attempts'                   => 1,
                'target_destination_verified'=> true,
                'chain'                      => [
                    [
                        'step'   => 1,
                        'url'    => $initial_url,
                        'status' => 302,
                        'type'   => 'Safe SohojGyan Shortlink Gateway'
                    ],
                    [
                        'step'   => 2,
                        'url'    => $safe_direct,
                        'status' => 200,
                        'type'   => 'Target Destination (Blogspot Episode Page)'
                    ]
                ],
                'final_html'                 => ''
            ];
        }

        for ($attempt = 1; $attempt <= $max_retries; $attempt++) {
            $target_url = $initial_url;

            // On retry attempt 2, if shrt.sohojgyan is used, try direct gateway variant
            if ($attempt === 2 && stripos($initial_url, 'shrt.sohojgyan') !== false) {
                $path = trim(parse_url($initial_url, PHP_URL_PATH) ?: '', '/');
                $parts = explode('/', $path);
                $code = end($parts);
                if ($code && strlen($code) >= 3) {
                    $target_url = "https://go.sohojgyan.com/{$code}";
                }
            }

            $res = self::resolve_url($target_url, $options);
            $last_res = $res;

            if (!empty($res['final']) && self::is_target_destination($res['final'])) {
                $last_res['attempts'] = $attempt;
                $last_res['target_destination_verified'] = true;
                return $last_res;
            }

            if ($attempt < $max_retries) {
                sleep(1 * $attempt);
            }
        }

        if ($last_res) {
            $last_res['attempts'] = $max_retries;
            $last_res['target_destination_verified'] = self::is_target_destination(isset($last_res['final']) ? $last_res['final'] : '');
        }

        return $last_res;
    }

    /**
     * Attempt resolving via Python engine if python3 is available on cPanel hosting.
     */
    private static function resolve_via_python($url) {
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

    /**
     * Resolve a URL through all HTTP redirects, meta refreshes, AdLinkFly forms, and script hops.
     */
    public static function resolve_url($initial_url, $options = []) {
        $py_res = self::resolve_via_python($initial_url);
        if ($py_res) {
            return $py_res;
        }

        $max_hops = isset($options['max_redirects']) ? intval($options['max_redirects']) : 25;
        $timeout  = isset($options['timeout']) ? intval($options['timeout']) : 20;

        $chain   = [];
        $visited = [];
        $current = self::normalize_url($initial_url);
        $final_html = '';

        // Immediate decode for safe.sohojgyan shortlink
        if (stripos($current, 'safe.sohojgyan') !== false || (strpos($current, 'code=') !== false && stripos($current, 'sohojgyan') !== false)) {
            $safe_first = self::resolve_safe_sohojgyan($current);
            if (!empty($safe_first)) {
                $chain[] = [
                    'step'     => count($chain) + 1,
                    'url'      => $current,
                    'status'   => 302,
                    'type'     => 'Safe SohojGyan Shortlink Decoded',
                    'next_url' => $safe_first
                ];
                $current = $safe_first;
                if (self::is_target_destination($current)) {
                    $chain[] = [
                        'step'   => count($chain) + 1,
                        'url'    => $current,
                        'status' => 200,
                        'type'   => 'Target Destination (Blogspot Episode Page)'
                    ];
                    return [
                        'original'  => $initial_url,
                        'final'     => $current,
                        'redirects' => count($chain) - 1,
                        'chain'     => $chain,
                        'final_html'=> ''
                    ];
                }
            }
        }

        while (count($chain) < $max_hops) {
            if (empty($current) || in_array($current, $visited, true)) {
                break;
            }
            $visited[] = $current;

            // Execute HTTP Request with cURL
            $response = self::fetch_url_curl($current, $timeout);

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

            // 1. Check HTTP Redirect
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

            // 3. Inspect HTML for AdLinkFly token form or auto-submit bypass
            $bypass_url = self::check_adlinkfly_form_or_ajax($current, $body);
            if ($bypass_url) {
                $chain[] = [
                    'step'     => count($chain) + 1,
                    'url'      => $current,
                    'status'   => $status,
                    'type'     => 'Shortener Gateway Bypass Form',
                    'next_url' => $bypass_url
                ];
                $current = $bypass_url;
                continue;
            }

            // 4. Inspect HTML for Meta Refresh
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

            // 5. Inspect JavaScript location redirects
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

            // 6. Inspect direct Blogspot episode link in HTML
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

        return [
            'original'   => $initial_url,
            'final'      => $current,
            'redirects'  => max(0, count($chain) - 1),
            'chain'      => $chain,
            'final_html' => $final_html
        ];
    }

    private static function fetch_url_curl($url, $timeout = 15) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_USERAGENT, self::$user_agent);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_ENCODING, '');

        $raw_response = curl_exec($ch);
        $error = curl_error($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);

        $headers = substr($raw_response, 0, $header_size);
        $body    = substr($raw_response, $header_size);

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

    private static function check_adlinkfly_form_or_ajax($current_url, $html) {
        if (empty($html)) return null;

        // Check for Sohojgyan / AdLinkFly form action
        if (preg_match('/<form[^>]*action=[\'"]([^\'"]*\/links\/go[^\'"]*)[\'"][^>]*>([\s\S]*?)<\/form>/i', $html, $form_match)) {
            $form_action = $form_match[1];
            $form_body   = $form_match[2];

            $post_data = [];
            if (preg_match_all('/<input[^>]*name=[\'"]([^\'"]+)[\'"][^>]*value=[\'"]([^\'"]*)[\'"]/i', $form_body, $inputs, PREG_SET_ORDER)) {
                foreach ($inputs as $inp) {
                    $post_data[$inp[1]] = $inp[2];
                }
            }

            if (!empty($post_data)) {
                $action_url = self::normalize_url($form_action, $current_url);
                $resp = self::post_curl_json($action_url, $post_data, $current_url);
                if (!empty($resp['url'])) {
                    return $resp['url'];
                }
            }
        }

        return null;
    }

    private static function post_curl_json($url, $data, $referer) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_USERAGENT, self::$user_agent);
        curl_setopt($ch, CURLOPT_REFERER, $referer);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'X-Requested-With: XMLHttpRequest',
            'Accept: application/json, text/javascript, */*; q=0.01'
        ]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

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

        // 1. Highest priority: exact match https://mydverse02.blogspot.com/p/*.html
        if (preg_match('/<a\s+[^>]*href=[\'"](https?:\/\/(?:www\.)?mydverse02\.blogspot\.[a-z.]+\/p\/[a-zA-Z0-9_-]+\.html)[\'"]/i', $html, $m)) {
            return $m[1];
        }

        // 2. Look inside script tags or variables for target link https://mydverse02.blogspot.com/p/*.html
        if (preg_match('/[\'"](https?:\/\/(?:www\.)?mydverse02\.blogspot\.[a-z.]+\/p\/[a-zA-Z0-9_-]+\.html)[\'"]/i', $html, $sm)) {
            return $sm[1];
        }

        // 3. Look for Episode Wise Links anchor (shortlink candidate to resolve next)
        if (preg_match('/<a\s+[^>]*href=[\'"]([^\'"]+)[\'"][^>]*>[\s\S]*?(?:Episode[\s_-]*Wise|Download[\s_-]*Episodes?)[\s\S]*?<\/a>/i', $html, $ewm)) {
            $cand = $ewm[1];
            if (stripos($cand, 'mydriveverse') === false) {
                return self::normalize_url($cand, $base_url);
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
