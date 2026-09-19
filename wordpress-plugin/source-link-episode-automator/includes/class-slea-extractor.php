<?php
/**
 * HTML Parser & Action/Episode Link Extractor for WordPress
 * Faithfully mirrors the Python extractor.py logic using PHP DOMDocument or regex fallback.
 */

if (!defined('ABSPATH')) {
    exit;
}

class SLEA_Extractor {

    private static $action_words = array(
        'download', 'watch', 'stream', 'play', 'open', 'direct',
        'server', 'link', 'links', 'get', 'view', 'continue', 'mirror',
        'xcloud', 'filemoon', 'streamtape', 'doodstream', 'mixdrop', 'hubcloud', 'gdflix',
        'episode', 'episodes', 'ep', 'eps', 'wise', 'gdrive', 'drive', 'mediafire', 'mega',
        '480p', '720p', '1080p', '2160p', '4k', 'hevc', 'fast', 'click here', 'click'
    );

    private static $nav_words = array(
        'home', 'about', 'contact', 'privacy', 'privacy-policy',
        'cookie', 'cookie-policy', 'sitemap', 'search', 'login',
        'register', 'rtl', 'category', 'categories', 'archive',
        'next', 'previous', 'prev',
    );

    private static $excluded_tests = array(
        'rich results test',
        'pagespeed insights',
    );

    /**
     * Extract links from HTML content
     *
     * @param string $html
     * @param string $base_url
     * @param bool   $button_only
     * @return array List of items: [ ['url' => ..., 'text' => ..., 'tag' => ..., 'class' => ...] ]
     */
    public static function extract_links($html, $base_url, $button_only = true) {
        if (empty($html)) {
            return array();
        }

        $items = array();
        $seen = array();

        // Prefer DOMDocument if available
        if (class_exists('DOMDocument')) {
            $dom = new DOMDocument();
            // Suppress HTML5 parsing warnings
            libxml_use_internal_errors(true);
            $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
            libxml_clear_errors();

            // 1. Process <a> tags
            $links = $dom->getElementsByTagName('a');
            foreach ($links as $link) {
                $href = $link->getAttribute('href');
                $class = $link->getAttribute('class');
                $id = $link->getAttribute('id');
                $text = trim($link->textContent);

                // Check title or alt if text is empty
                if (empty($text)) {
                    $text = trim($link->getAttribute('title'));
                }
                if (empty($text)) {
                    $imgs = $link->getElementsByTagName('img');
                    if ($imgs->length > 0) {
                        $text = trim($imgs->item(0)->getAttribute('alt'));
                    }
                }

                $clean_url = self::clean_and_resolve_url($href, $base_url);
                if (!$clean_url) {
                    continue;
                }

                if (self::is_blocked_test_link($text, $clean_url)) {
                    continue;
                }

                if ($button_only && !self::is_action_element('a', $text, $class, $id)) {
                    continue;
                }

                $norm = strtolower(rtrim($clean_url, '/'));
                if (isset($seen[$norm])) {
                    continue;
                }
                $seen[$norm] = true;

                $items[] = array(
                    'type'  => 'link',
                    'tag'   => 'a',
                    'text'  => self::clean_text($text),
                    'url'   => $clean_url,
                    'class' => $class,
                    'id'    => $id,
                );
            }

            // 2. Process <button> tags with data-url or onclick
            $buttons = $dom->getElementsByTagName('button');
            foreach ($buttons as $btn) {
                $data_url = $btn->getAttribute('data-url');
                if (empty($data_url)) {
                    $data_url = $btn->getAttribute('data-href');
                }
                if (empty($data_url)) {
                    $onclick = $btn->getAttribute('onclick');
                    if (preg_match('/(?:location\.href|open)\s*\(\s*[\'"]([^\'"]+)[\'"]/i', $onclick, $m)) {
                        $data_url = $m[1];
                    }
                }

                if (empty($data_url)) {
                    continue;
                }

                $class = $btn->getAttribute('class');
                $id = $btn->getAttribute('id');
                $text = trim($btn->textContent);

                $clean_url = self::clean_and_resolve_url($data_url, $base_url);
                if (!$clean_url || self::is_blocked_test_link($text, $clean_url)) {
                    continue;
                }

                $norm = strtolower(rtrim($clean_url, '/'));
                if (isset($seen[$norm])) {
                    continue;
                }
                $seen[$norm] = true;

                $items[] = array(
                    'type'  => 'button',
                    'tag'   => 'button',
                    'text'  => self::clean_text($text),
                    'url'   => $clean_url,
                    'class' => $class,
                    'id'    => $id,
                );
            }
        } else {
            // Regex fallback if DOMDocument is not loaded in PHP
            preg_match_all('/<a\s+[^>]*href=[\'"]([^\'"]+)[\'"][^>]*>(.*?)<\/a>/is', $html, $matches, PREG_SET_ORDER);
            foreach ($matches as $m) {
                $raw_href = $m[1];
                $inner = strip_tags($m[2]);
                $clean_url = self::clean_and_resolve_url($raw_href, $base_url);
                if (!$clean_url || self::is_blocked_test_link($inner, $clean_url)) {
                    continue;
                }

                $norm = strtolower(rtrim($clean_url, '/'));
                if (isset($seen[$norm])) {
                    continue;
                }
                $seen[$norm] = true;

                $items[] = array(
                    'type'  => 'link',
                    'tag'   => 'a',
                    'text'  => self::clean_text($inner),
                    'url'   => $clean_url,
                    'class' => '',
                    'id'    => '',
                );
            }
        }

        return $items;
    }

    /**
     * Check if text or attributes match action words or button classes
     */
    private static function is_action_element($tag, $text, $class = '', $id = '') {
        $text_lower = strtolower($text);
        $class_lower = strtolower($class);
        $id_lower = strtolower($id);

        // Filter out obvious nav terms
        foreach (self::$nav_words as $nw) {
            if ($text_lower === $nw) {
                return false;
            }
        }

        // Check explicit action words
        foreach (self::$action_words as $w) {
            if (strpos($text_lower, $w) !== false) {
                return true;
            }
        }

        // Check CSS classes
        if (preg_match('/(btn|button|download|episode|stream|link|action|cta)/i', $class_lower . ' ' . $id_lower)) {
            return true;
        }

        return false;
    }

    private static function clean_and_resolve_url($href, $base_url) {
        $href = trim($href);
        if (empty($href) || $href === '#' || strpos($href, 'javascript:') === 0 || strpos($href, 'mailto:') === 0 || strpos($href, 'tel:') === 0) {
            return null;
        }

        return SLEA_Resolver::resolve_relative_url($base_url, $href);
    }

    private static function is_blocked_test_link($text, $url) {
        $t = strtolower($text);
        $u = strtolower($url);

        foreach (self::$excluded_tests as $phrase) {
            if (strpos($t, $phrase) !== false) {
                return true;
            }
        }

        if (strpos($u, 'rich-results') !== false || strpos($u, 'pagespeed') !== false) {
            return true;
        }

        return false;
    }

    private static function clean_text($text) {
        $text = preg_replace('/\s+/', ' ', $text);
        return trim($text);
    }
}
