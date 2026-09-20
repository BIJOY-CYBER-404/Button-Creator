<?php
/**
 * Standalone cPanel Episode Button & Link Extractor
 */

class SLEA_Extractor {
    private static $button_indicators = [
        'btn', 'button', 'download', 'ep', 'episode', 'watch', 'server', 'drive',
        'mega', 'fast', 'play', 'stream', 'link', 'dlink', 'click', 'quality',
        '480p', '720p', '1080p', '4k', '2160p', 'hevc', 'x264', 'x265', 'batch'
    ];

    private static $excluded_domains = [
        'facebook.com', 'twitter.com', 'instagram.com', 'youtube.com', 't.me',
        'telegram.org', 'pinterest.com', 'reddit.com', 'linkedin.com', 'whatsapp.com',
        'google.com/search', 'policies.google.com', 'schema.org', 'w3.org'
    ];

    public static function fetch_page($url, $timeout = 15) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_ENCODING, '');

        $html = curl_exec($ch);
        $final_url = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL) ?: $url;
        curl_close($ch);

        return [$final_url, $html ?: ''];
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
            return self::derive_title_from_url($url);
        }

        return $title;
    }

    public static function derive_title_from_url($url) {
        if (empty($url)) return 'Episode Download Links';
        if (preg_match('/\/p\/([a-zA-Z0-9_-]+)\.html/i', $url, $m)) {
            return ucwords(str_replace(['-', '_'], ' ', $m[1]));
        }
        $path = trim(parse_url($url, PHP_URL_PATH) ?: '', '/');
        $segments = explode('/', $path);
        $last = end($segments);
        if (!empty($last)) {
            $cleaned = preg_replace('/\.(html|php|asp)$/i', '', $last);
            return ucwords(str_replace(['-', '_'], ' ', $cleaned));
        }
        return 'Episode Download Links';
    }

    public static function extract_buttons_from_html($html, $base_url = '', $button_only = true) {
        if (empty($html)) return [];

        $items = [];
        $seen = [];

        // Match all <a> tags
        if (preg_match_all('/<a\s+([^>]*?)href=[\'"]([^\'"]+)[\'"]([^>]*)>([\s\S]*?)<\/a>/i', $html, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $attrs_before = $match[1];
                $href         = trim($match[2]);
                $attrs_after  = $match[3];
                $inner_html   = $match[4];

                $all_attrs = $attrs_before . ' ' . $attrs_after;
                $text = trim(strip_tags($inner_html));
                $text = preg_replace('/\s+/', ' ', $text);

                // Filter invalid links
                if (empty($href) || preg_match('/^(?:javascript:|mailto:|tel:|#)/i', $href)) {
                    continue;
                }

                $full_url = self::resolve_relative_url($href, $base_url);
                if (empty($full_url) || in_array($full_url, $seen, true)) {
                    continue;
                }

                // Check exclusions
                $host = strtolower(parse_url($full_url, PHP_URL_HOST) ?: '');
                $is_excluded = false;
                foreach (self::$excluded_domains as $ex) {
                    if (strpos($host, $ex) !== false) {
                        $is_excluded = true;
                        break;
                    }
                }
                if ($is_excluded) continue;

                // Extract qualities, episode numbers, etc.
                $quality = self::detect_quality($text . ' ' . $all_attrs . ' ' . $full_url);
                $episode_num = self::detect_episode($text);

                // Button score filter
                $is_button = self::is_button_element($all_attrs, $text, $full_url);

                if ($button_only && !$is_button && empty($quality) && empty($episode_num)) {
                    continue;
                }

                $seen[] = $full_url;
                $items[] = [
                    'text'        => !empty($text) ? $text : 'Episode Link',
                    'url'         => $full_url,
                    'quality'     => $quality,
                    'episode'     => $episode_num,
                    'is_button'   => $is_button,
                    'provider'    => self::detect_provider($full_url)
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
        if (strpos($host, 'drive.google') !== false) return 'Google Drive';
        if (strpos($host, 'mega.nz') !== false || strpos($host, 'mega.io') !== false) return 'Mega';
        if (strpos($host, 'gdtot') !== false) return 'GDTot';
        if (strpos($host, 'filepress') !== false) return 'FilePress';
        if (strpos($host, 'hubcloud') !== false) return 'HubCloud';
        if (strpos($host, 'fastdl') !== false) return 'FastDL';
        if (strpos($host, 'blogspot') !== false) return 'Blogspot Server';
        return 'Download Server';
    }

    private static function is_button_element($attrs, $text, $url) {
        $combined = strtolower($attrs . ' ' . $text . ' ' . $url);
        foreach (self::$button_indicators as $kw) {
            if (strpos($combined, $kw) !== false) {
                return true;
            }
        }
        return false;
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
