<?php
/**
 * Standalone cPanel Episode Button & Link Extractor
 */

class SLEA_Extractor {
    private static $button_indicators = [
        'btn', 'button', 'download', 'ep', 'episode', 'watch', 'server', 'drive',
        'mega', 'fast', 'play', 'stream', 'link', 'dlink', 'click', 'quality',
        '480p', '720p', '1080p', '4k', '2160p', 'hevc', 'x264', 'x265', 'batch',
        'xcloud', 'filemoon', 'streamtape', 'doodstream', 'mixdrop', 'gdrive',
        'mediafire', 'hubcloud', 'gdtot', 'fastdl', 'filepress', 'katdrive',
        'gdflix', 'streamwish', 'vidhide', 'mp4upload', 'vidoza', 'dropload',
        'hexupload', 'terabox', 'dood', 'stream', 'direct', 'mirror'
    ];

    private static $excluded_domains = [
        'facebook.com', 'twitter.com', 'x.com', 'instagram.com', 'youtube.com',
        'youtu.be', 't.me', 'telegram.me', 'telegram.org', 'pinterest.com',
        'reddit.com', 'linkedin.com', 'whatsapp.com', 'tiktok.com', 'disqus.com',
        'blogger.com', 'google.com/search', 'policies.google.com', 'schema.org',
        'w3.org', 'pagespeed.web.dev', 'web.dev', 'rich-results'
    ];

    private static $nav_words = [
        'home', 'about', 'about us', 'contact', 'contact us', 'privacy',
        'privacy policy', 'terms', 'terms of service', 'dmca', 'disclaimer',
        'faq', 'sitemap', 'search', 'login', 'sign in', 'register', 'sign up',
        'rtl', 'category', 'categories', 'archive', 'archives', 'next',
        'previous', 'prev', 'back', 'newer posts', 'older posts', 'load more',
        'read more', 'share on facebook', 'share on twitter', 'share on telegram',
        'share on whatsapp', 'share', 'pin it', 'tweet', 'comment', 'cancel reply',
        'reply', 'post a comment', 'subscribe', 'rss', 'feed'
    ];

    public static function extract_buttons_from_html($html, $base_url = '', $button_only = true) {
        if (empty($html)) return [];

        // 1. Strip non-content structural elements (header, footer, nav, aside, script, style, comments)
        $clean_html = preg_replace('/<(?:script|style|noscript|header|footer|nav|aside)[^>]*>[\s\S]*?<\/(?:script|style|noscript|header|footer|nav|aside)>/i', ' ', $html);
        
        // Strip common navigation, widget, header, footer, social, sidebar, comment containers
        $clean_html = preg_replace('/<(?:div|section|aside|ul|nav)[^>]*class=[\'"][^\'"]*(?:header|footer|navbar|nav-menu|main-menu|site-header|site-footer|topbar|bottombar|sidebar|widget|popular-posts|recent-posts|label-list|breadcrumb|breadcrumbs|comments|comment-reply|widget-social|social-share|author-box)[^\'"]*[\'"][^>]*>[\s\S]*?<\/(?:div|section|aside|ul|nav)>/i', ' ', $clean_html);

        $items = [];
        $seen = [];

        // Match all <a> tags along with their character position
        if (preg_match_all('/<a\s+([^>]*?)href=[\'"]([^\'"]+)[\'"]([^>]*)>([\s\S]*?)<\/a>/i', $clean_html, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            foreach ($matches as $match) {
                $full_tag     = $match[0][0];
                $tag_offset   = $match[0][1];
                $attrs_before = $match[1][0];
                $href         = trim($match[2][0]);
                $attrs_after  = $match[3][0];
                $inner_html   = $match[4][0];

                $all_attrs = $attrs_before . ' ' . $attrs_after;
                $text = trim(strip_tags($inner_html));
                $text = preg_replace('/\s+/', ' ', $text);

                // Filter invalid / protocol links
                if (empty($href) || preg_match('/^(?:javascript:|mailto:|tel:|sms:|#)/i', $href)) {
                    continue;
                }

                $full_url = self::resolve_relative_url($href, $base_url);
                if (empty($full_url) || in_array($full_url, $seen, true)) {
                    continue;
                }

                // Check domain exclusions (social media, search, analytics, etc.)
                $host = strtolower(parse_url($full_url, PHP_URL_HOST) ?: '');
                $is_excluded = false;
                foreach (self::$excluded_domains as $ex) {
                    if (strpos($host, $ex) !== false || strpos($full_url, $ex) !== false) {
                        $is_excluded = true;
                        break;
                    }
                }
                if ($is_excluded) continue;

                // Check text exclusion against navigation, menu, icon, or footer labels
                $text_lower = strtolower($text);
                if (in_array($text_lower, self::$nav_words, true)) {
                    continue;
                }

                // Inspect surrounding DOM (180 chars before <a) for episode names or numbers
                $start_pos = max(0, $tag_offset - 180);
                $preceding_chunk = substr($clean_html, $start_pos, $tag_offset - $start_pos);

                $episode_num = self::detect_episode($text);
                $ep_label = '';
                if (empty($episode_num)) {
                    if (preg_match_all('/(?:Episode|Ep\.?|E)\s*(\d{1,3}(?:\s*-\s*\d{1,3})?)/i', $preceding_chunk, $ep_matches)) {
                        $last_ep = end($ep_matches[1]);
                        $ep_label = 'Episode ' . trim($last_ep);
                        if (preg_match('/^(\d+)/', trim($last_ep), $num_m)) {
                            $episode_num = intval($num_m[1]);
                        }
                    }
                }

                // Extract quality
                $quality = self::detect_quality($text . ' ' . $all_attrs . ' ' . $full_url . ' ' . $preceding_chunk);

                // Provider
                $provider = self::detect_provider($full_url);

                // Strict Button / Button Component filter
                $is_button = self::is_button_element($all_attrs, $text, $full_url, $preceding_chunk) || ($provider !== 'Download Server');

                if ($button_only && !$is_button && empty($quality) && empty($episode_num) && empty($ep_label)) {
                    continue;
                }

                // Format button label nicely
                $button_title = !empty($text) ? $text : ($provider !== 'Download Server' ? $provider : 'Download Link');
                if (!empty($ep_label) && stripos($button_title, 'episode') === false) {
                    $button_title = $ep_label . ' - ' . $button_title;
                }

                $seen[] = $full_url;
                $items[] = [
                    'text'        => $button_title,
                    'url'         => $full_url,
                    'quality'     => $quality,
                    'episode'     => $episode_num,
                    'is_button'   => $is_button,
                    'provider'    => $provider
                ];
            }
        }

        // Sort items naturally if episode numbers exist
        usort($items, function($a, $b) {
            if (!empty($a['episode']) && !empty($b['episode'])) {
                return intval($a['episode']) - intval($b['episode']);
            }
            return 0;
        });

        return $items;
    }

    private static function detect_quality($content) {
        $content = strtolower($content);
        if (strpos($content, '1080p') !== false || strpos($content, 'fhd') !== false) return '1080p';
        if (strpos($content, '720p') !== false || strpos($content, 'hd') !== false) return '720p';
        if (strpos($content, '480p') !== false || strpos($content, 'sd') !== false) return '480p';
        if (strpos($content, '4k') !== false || strpos($content, '2160p') !== false) return '4K';
        if (strpos($content, 'hevc') !== false) return 'HEVC';
        return null;
    }

    private static function detect_episode($text) {
        if (preg_match('/(?:Episode|Ep\.?|E)\s*(\d{1,3})/i', $text, $m)) {
            return intval($m[1]);
        }
        return null;
    }

    private static function detect_provider($url) {
        $host = strtolower(parse_url($url, PHP_URL_HOST) ?: '');
        if (strpos($host, 'xcloud') !== false) return 'XCloud';
        if (strpos($host, 'drive.google') !== false) return 'Google Drive';
        if (strpos($host, 'mega.nz') !== false || strpos($host, 'mega.io') !== false) return 'Mega';
        if (strpos($host, 'gdtot') !== false) return 'GDTot';
        if (strpos($host, 'filepress') !== false) return 'FilePress';
        if (strpos($host, 'hubcloud') !== false) return 'HubCloud';
        if (strpos($host, 'fastdl') !== false) return 'FastDL';
        if (strpos($host, 'mediafire') !== false) return 'MediaFire';
        if (strpos($host, 'filemoon') !== false) return 'FileMoon';
        if (strpos($host, 'streamtape') !== false) return 'StreamTape';
        if (strpos($host, 'dood') !== false) return 'DoodStream';
        if (strpos($host, 'vidhide') !== false) return 'VidHide';
        if (strpos($host, 'streamwish') !== false) return 'StreamWish';
        if (strpos($host, 'terabox') !== false) return 'TeraBox';
        if (strpos($host, 'katdrive') !== false) return 'KatDrive';
        if (strpos($host, 'gdflix') !== false) return 'GDFlix';
        if (strpos($host, 'vidoza') !== false) return 'Vidoza';
        if (strpos($host, 'mp4upload') !== false) return 'Mp4Upload';
        if (strpos($host, 'blogspot') !== false) return 'Blogspot Server';
        return 'Download Server';
    }

    private static function is_button_element($attrs, $text, $url, $preceding = '') {
        $combined = strtolower($attrs . ' ' . $text . ' ' . $url . ' ' . $preceding);
        
        // 1. Explicit button classes / roles / components
        if (preg_match('/\b(?:btn|button|btn-[a-z0-9_-]+|button-[a-z0-9_-]+|epgdrive|epitem|eplist|download-btn|dl-btn|action-btn|dlink|download-button|post-button)\b/i', $attrs)) {
            return true;
        }

        // 2. Button indicators in keywords
        foreach (self::$button_indicators as $kw) {
            if (strpos($combined, $kw) !== false) {
                return true;
            }
        }

        // 3. Known media / cloud download / stream providers
        $host = strtolower(parse_url($url, PHP_URL_HOST) ?: '');
        if (!empty($host) && self::detect_provider($url) !== 'Download Server') {
            return true;
        }

        // 4. Action text match
        if (preg_match('/^(?:Episode\s*\d+|Ep\.?\s*\d+|Download|Watch|Stream|XCloud|FastDL|Mega|G-?Drive|1080p|720p|480p|4k)\b/i', trim($text))) {
            return true;
        }

        return false;
    }
        $current_url = $url;
        $visited = [];
        $html = '';

        for ($i = 0; $i < $max_redirects; $i++) {
            if (empty($current_url) || in_array($current_url, $visited, true)) {
                break;
            }
            $visited[] = $current_url;

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $current_url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HEADER, true);
            $open_basedir = ini_get('open_basedir');
            if (empty($open_basedir)) {
                @curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                @curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
            } else {
                @curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
            }
            curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36');
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_ENCODING, ''); // Auto decode gzip/deflate
            curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
                'Accept-Language: en-US,en;q=0.9'
            ]);

            $raw = curl_exec($ch);
            $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            $effective_url = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL) ?: $current_url;
            curl_close($ch);

            if ($raw !== false && strlen($raw) > 0) {
                $headers = substr($raw, 0, $header_size);
                $body = substr($raw, $header_size);
                $html = $body;

                if (in_array($status, [301, 302, 303, 307, 308])) {
                    if (preg_match('/^Location:\s*([^\r\n]+)/mi', $headers, $m)) {
                        $next_url = trim($m[1]);
                        if (strpos($next_url, '//') === 0) {
                            $next_url = 'https:' . $next_url;
                        } elseif (strpos($next_url, '/') === 0) {
                            $parsed = parse_url($current_url);
                            $next_url = ($parsed['scheme'] ?? 'https') . '://' . ($parsed['host'] ?? '') . $next_url;
                        }
                        $current_url = $next_url;
                        continue;
                    }
                }
                $current_url = $effective_url;
                break;
            } else {
                // Stream context fallback if curl failed
                $context = stream_context_create([
                    'http' => [
                        'method' => 'GET',
                        'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36\r\nAccept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8\r\n",
                        'timeout' => $timeout,
                        'follow_location' => 1,
                        'max_redirects' => 5
                    ],
                    'ssl' => [
                        'verify_peer' => false,
                        'verify_peer_name' => false
                    ]
                ]);
                $fallback = @file_get_contents($current_url, false, $context);
                if (!empty($fallback)) {
                    $html = $fallback;
                }
                break;
            }
        }

        return [$current_url, $html ?: ''];
    }

    public static function extract_page_title($html, $url = '') {
        if (empty($html)) {
            return self::derive_title_from_url($url);
        }

        $title = '';

        // 1. Check <title> tag
        if (preg_match('/<title[^>]*>([\s\S]*?)<\/title>/i', $html, $tm)) {
            $candidate = trim(strip_tags($tm[1]));
            if (!empty($candidate)) {
                $title = $candidate;
            }
        }

        // 2. Check OpenGraph / Twitter meta title if <title> was generic or empty
        if (empty($title) || preg_match('/^(?:Blogger|Blogspot|Home|Untitled)$/i', $title)) {
            if (preg_match('/<meta\s+[^>]*property=[\'"]og:title[\'"][^>]*content=[\'"]([^\'"]+)[\'"]/i', $html, $ogm) ||
                preg_match('/<meta\s+[^>]*content=[\'"]([^\'"]+)[\'"][^>]*property=[\'"]og:title[\'"]/i', $html, $ogm)) {
                $title = trim($ogm[1]);
            }
        }

        // 3. Check <h1> or post title tags (.post-title, .entry-title, h1)
        if (empty($title) || preg_match('/^(?:Blogger|Blogspot|Home|Untitled)$/i', $title)) {
            if (preg_match('/<(?:h1|h2)[^>]*class=[\'"][^\'"]*(?:post-title|entry-title|title)[^\'"]*[\'"][^>]*>([\s\S]*?)<\/(?:h1|h2)>/i', $html, $hm) ||
                preg_match('/<h1[^>]*>([\s\S]*?)<\/h1>/i', $html, $hm)) {
                $cand_h1 = trim(strip_tags($hm[1]));
                if (!empty($cand_h1)) {
                    $title = $cand_h1;
                }
            }
        }

        if (!empty($title)) {
            $title = html_entity_decode($title, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $title = preg_replace('/\s+/', ' ', $title);
            // Strip suffixes like "- Blogger", "| Blogspot", "– Watch Online Free", "- MovieHubHQ", etc.
            $title = preg_replace('/\s*[-|–—:]\s*(?:Blogger|Blogspot|Watch Online|Download|HD Movies|MovieHubHQ|MovieHub).*$/i', '', $title);
            $title = preg_replace('/\s*[-|–—:]\s*Home$/i', '', $title);
            $title = trim($title);
        }

        if (empty($title) || preg_match('/^(?:Blogger|Blogspot|Home|Untitled)$/i', $title)) {
            return self::sanitize_page_title(self::derive_title_from_url($url));
        }

        return self::sanitize_page_title($title);
    }

    public static function sanitize_page_title($title) {
        if (empty($title) || !is_string($title)) return '';
        // If the page title contains "DramaVerse 2", "mydverse", "mydverse 2" then replace with "Movie Hub HQ"
        $patterns = [
            '/\bDramaVerse\s*2\b/i',
            '/\bmydverse\s*2\b/i',
            '/\bmydverse\b/i',
            '/\bDramaVerse\b/i',
        ];
        $title = preg_replace($patterns, 'Movie Hub HQ', $title);
        $title = trim(preg_replace('/\s+/', ' ', $title));
        return $title;
    }

    public static function derive_title_from_url($url) {
        if (empty($url)) return 'Episode Download Links';
        if (preg_match('/\/p\/([a-zA-Z0-9_-]+)\.html/i', $url, $m)) {
            return self::sanitize_page_title(ucwords(str_replace(['-', '_'], ' ', $m[1])));
        }
        $path = trim(parse_url($url, PHP_URL_PATH) ?: '', '/');
        $segments = explode('/', $path);
        $last = end($segments);
        if (!empty($last)) {
            $cleaned = preg_replace('/\.(html|php|asp)$/i', '', $last);
            return self::sanitize_page_title(ucwords(str_replace(['-', '_'], ' ', $cleaned)));
        }
        return 'Episode Download Links';
    }

    private static function resolve_relative_url($url, $base) {
        $url = trim($url);
        if (empty($url)) return '';
        if (strpos($url, '//') === 0) return 'https:' . $url;
        if (strpos($url, 'http://') === 0 || strpos($url, 'https://') === 0) return $url;
        if (!empty($base)) {
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
