<?php
/**
 * Downloader
 * Securely fetches remote release manifest and downloads ZIP packages.
 */

if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__)) {
    http_response_code(403);
    exit('Forbidden');
}

class SLEA_Downloader {
    private $logger;

    public function __construct(SLEA_UpdateLogger $logger) {
        $this->logger = $logger;
    }

    private function assert_safe_remote_url($url) {
        $url = trim((string)$url);
        if (!preg_match('/^https:\/\//i', $url)) {
            throw new Exception("Security policy requires HTTPS URLs for remote update resources.");
        }
        $host = parse_url($url, PHP_URL_HOST);
        if (empty($host)) {
            throw new Exception("Invalid remote URL host.");
        }
        $host_clean = trim($host, '[]');
        if (in_array(strtolower($host_clean), ['localhost', '127.0.0.1', '0.0.0.0', '::1'], true)) {
            throw new Exception("Loopback update URLs are forbidden.");
        }
        $ip = @gethostbyname($host_clean);
        if ($ip && filter_var($ip, FILTER_VALIDATE_IP)) {
            if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                throw new Exception("Private or reserved network IP addresses are forbidden.");
            }
        }
        return true;
    }

    public function fetch_manifest($manifest_url) {
        if (empty($manifest_url)) {
            throw new Exception("Update manifest URL is empty.");
        }
        $this->assert_safe_remote_url($manifest_url);

        // Add dynamic random cache buster to remote HTTP URLs to bypass CDN caches like raw.githubusercontent.com Fastly/Varnish caches
        $fetch_url = $manifest_url;
        if (strpos($manifest_url, 'http://') === 0 || strpos($manifest_url, 'https://') === 0) {
            $separator = (strpos($manifest_url, '?') === false) ? '?' : '&';
            $cb = time() . '_' . mt_rand(100000, 999999);
            $fetch_url = $manifest_url . $separator . '_nocache=1&_cb=' . $cb . '&ts=' . microtime(true);
        }

        $this->logger->log("Checking update", "Fetching release manifest from " . $manifest_url);

        $json_raw = $this->http_get($fetch_url);
        if (empty($json_raw)) {
            throw new Exception("Unable to retrieve update manifest from " . $manifest_url);
        }

        // Clean UTF-8 Byte-Order-Mark (BOM) and whitespace
        $json_raw = preg_replace('/^\xEF\xBB\xBF/', '', trim($json_raw));

        // Direct parse attempt
        $manifest = json_decode($json_raw, true);

        // Fallback: extract JSON object from surrounding text/HTML if present
        if ((!$manifest || !is_array($manifest)) && ($start = strpos($json_raw, '{')) !== false && ($end = strrpos($json_raw, '}')) !== false && $end > $start) {
            $json_clean = substr($json_raw, $start, $end - $start + 1);
            $manifest = json_decode($json_clean, true);
        }

        if (!$manifest || !is_array($manifest)) {
            $snippet = htmlspecialchars(substr(strip_tags($json_raw), 0, 150));
            $json_err = json_last_error_msg();
            throw new Exception("Invalid update manifest format from " . $manifest_url . " (JSON error: " . $json_err . "). Response preview: '" . $snippet . "'");
        }

        if (empty($manifest['version']) || empty($manifest['download_url'])) {
            throw new Exception("Update manifest from " . $manifest_url . " is missing required 'version' or 'download_url' fields.");
        }

        return $manifest;
    }

    public function download_package($download_url, $target_file) {
        $this->logger->log("Downloading package", "Downloading release ZIP from " . $download_url);

        $dir = dirname($target_file);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        if (file_exists($target_file)) {
            @unlink($target_file);
        }

        $candidate_urls = array_unique(array_filter([
            $download_url,
            "https://raw.githubusercontent.com/BIJOY-CYBER-404/Button-Creator/main/public/cpanel-app-package.zip",
            "https://cdn.jsdelivr.net/gh/BIJOY-CYBER-404/Button-Creator@main/public/cpanel-app-package.zip",
            "https://raw.githubusercontent.com/BIJOY-CYBER-404/Button-Creator/main/cpanel-app-package.zip",
            "https://raw.githubusercontent.com/BIJOY-CYBER-404/Button-Creator/main/public/cpanel-shared-hosting.zip"
        ]));

        foreach ($candidate_urls as $cand_url) {
            try {
                $this->assert_safe_remote_url($cand_url);
            } catch (Exception $e) {
                continue;
            }
            $fetch_package_url = $cand_url;
            if (strpos($cand_url, 'http://') === 0 || strpos($cand_url, 'https://') === 0) {
                $separator = (strpos($cand_url, '?') === false) ? '?' : '&';
                $cb = time() . '_' . mt_rand(100000, 999999);
                $fetch_package_url = $cand_url . $separator . '_nocache=1&_cb=' . $cb;
            }

            if (function_exists('curl_init')) {
                $ch = curl_init($fetch_package_url);
                $fp = fopen($target_file, 'wb');
                curl_setopt($ch, CURLOPT_FILE, $fp);
                curl_setopt($ch, CURLOPT_HEADER, 0);
                if (defined('CURLPROTO_HTTPS')) {
                    @curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTPS);
                    @curl_setopt($ch, CURLOPT_REDIR_PROTOCOLS, CURLPROTO_HTTPS);
                }
                if (!ini_get('open_basedir')) {
                    @curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                }
                curl_setopt($ch, CURLOPT_TIMEOUT, 180);
                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 20);
                curl_setopt($ch, CURLOPT_USERAGENT, 'MovieHubHQ-Updater/19.0');
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
                curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
                curl_setopt($ch, CURLOPT_FORBID_REUSE, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Cache-Control: no-cache, no-store, must-revalidate, max-age=0',
                    'Pragma: no-cache',
                    'Expires: 0',
                    'Accept: */*'
                ]);

                $executed = curl_exec($ch);
                $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                fclose($fp);

                if ($executed && $http_code === 200 && filesize($target_file) > 1024) {
                    $filesize = @filesize($target_file);
                    $this->logger->log("Downloading package", "Successfully downloaded release package (" . round($filesize / 1024, 1) . " KB)");
                    return true;
                }
                @unlink($target_file);
            }

            // Stream Fallback with anti-cache headers
            $ctx = stream_context_create([
                'http' => [
                    'method'          => 'GET',
                    'timeout'         => 180,
                    'follow_location' => 1,
                    'header'          => "User-Agent: MovieHubHQ-Updater/19.0\r\n" .
                                         "Cache-Control: no-cache, no-store, must-revalidate, max-age=0\r\n" .
                                         "Pragma: no-cache\r\n" .
                                         "Expires: 0\r\n" .
                                         "Accept: */*\r\n"
                ],
                'ssl' => [
                    'verify_peer'      => false,
                    'verify_peer_name' => false
                ]
            ]);
            $content = @file_get_contents($fetch_package_url, false, $ctx);
            if ($content !== false && strlen($content) >= 1024) {
                file_put_contents($target_file, $content);
                $filesize = @filesize($target_file);
                $this->logger->log("Downloading package", "Downloaded package via stream context (" . round($filesize / 1024, 1) . " KB)");
                return true;
            }
        }

        @unlink($target_file);
        throw new Exception("Package download failed from " . $download_url . ". Check server internet access or cURL / allow_url_fopen permissions.");
    }

    private function http_get($url) {
        $this->assert_safe_remote_url($url);

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            if (defined('CURLPROTO_HTTPS')) {
                @curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTPS);
                @curl_setopt($ch, CURLOPT_REDIR_PROTOCOLS, CURLPROTO_HTTPS);
            }
            $has_open_basedir = !empty(ini_get('open_basedir'));
            if (!$has_open_basedir) {
                @curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                @curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
            }
            curl_setopt($ch, CURLOPT_TIMEOUT, 25);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 12);
            curl_setopt($ch, CURLOPT_USERAGENT, 'MovieHubHQ-Updater/19.0');
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
            curl_setopt($ch, CURLOPT_FORBID_REUSE, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Cache-Control: no-cache, no-store, must-revalidate, max-age=0',
                'Pragma: no-cache',
                'Expires: 0',
                'Accept: application/json, text/plain, */*'
            ]);

            $resp = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $redirect_url = curl_getinfo($ch, CURLINFO_REDIRECT_URL);
            curl_close($ch);

            // Manual redirect follow if open_basedir is active and returned 30x
            if ($has_open_basedir && !empty($redirect_url) && in_array($http_code, [301, 302, 303, 307, 308])) {
                return $this->http_get($redirect_url);
            }

            if ($resp !== false && $http_code === 200 && strlen(trim($resp)) > 10) {
                return $resp;
            }
        }

        $ctx = stream_context_create([
            'http' => [
                'method'          => 'GET',
                'timeout'         => 25,
                'follow_location' => 1,
                'header'          => "User-Agent: MovieHubHQ-Updater/19.0\r\n" .
                                     "Cache-Control: no-cache, no-store, must-revalidate, max-age=0\r\n" .
                                     "Pragma: no-cache\r\n" .
                                     "Expires: 0\r\n" .
                                     "Accept: application/json, text/plain, */*\r\n"
            ],
            'ssl' => [
                'verify_peer'      => false,
                'verify_peer_name' => false
            ]
        ]);
        $resp = @file_get_contents($url, false, $ctx);
        if ($resp !== false && strlen(trim($resp)) > 10) {
            return $resp;
        }

        return false;
    }
}
