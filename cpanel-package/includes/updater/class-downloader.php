<?php
/**
 * Downloader
 * Securely fetches remote release manifest and downloads ZIP packages.
 */

class SLEA_Downloader {
    private $logger;

    public function __construct(SLEA_UpdateLogger $logger) {
        $this->logger = $logger;
    }

    public function fetch_manifest($manifest_url) {
        if (empty($manifest_url)) {
            throw new Exception("Update manifest URL is empty.");
        }

        // Add a dynamic cache buster to remote HTTP URLs to bypass CDN caches like raw.githubusercontent.com Varnish caches
        $fetch_url = $manifest_url;
        if (strpos($manifest_url, 'http://') === 0 || strpos($manifest_url, 'https://') === 0) {
            $separator = (strpos($manifest_url, '?') === false) ? '?' : '&';
            $fetch_url = $manifest_url . $separator . 't=' . time();
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

        if (function_exists('curl_init')) {
            $ch = curl_init($download_url);
            $fp = fopen($target_file, 'wb');
            curl_setopt($ch, CURLOPT_FILE, $fp);
            curl_setopt($ch, CURLOPT_HEADER, 0);
            if (!ini_get('open_basedir')) {
                @curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            }
            curl_setopt($ch, CURLOPT_TIMEOUT, 120);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
            curl_setopt($ch, CURLOPT_USERAGENT, 'MovieHubHQ-Updater/3.8');
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);

            $executed = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curl_err = curl_error($ch);
            curl_close($ch);
            fclose($fp);

            if ($executed && $http_code === 200 && filesize($target_file) > 1024) {
                $filesize = @filesize($target_file);
                $this->logger->log("Downloading package", "Successfully downloaded release package (" . round($filesize / 1024, 1) . " KB)");
                return true;
            }
            @unlink($target_file);
        }

        // Stream Fallback
        $ctx = stream_context_create([
            'http' => [
                'method'          => 'GET',
                'timeout'         => 120,
                'follow_location' => 1,
                'header'          => "User-Agent: MovieHubHQ-Updater/3.8\r\nAccept: */*\r\n"
            ],
            'ssl' => [
                'verify_peer'      => false,
                'verify_peer_name' => false
            ]
        ]);
        $content = @file_get_contents($download_url, false, $ctx);
        if ($content !== false && strlen($content) >= 1024) {
            file_put_contents($target_file, $content);
            $filesize = @filesize($target_file);
            $this->logger->log("Downloading package", "Downloaded package via stream context (" . round($filesize / 1024, 1) . " KB)");
            return true;
        }

        @unlink($target_file);
        throw new Exception("Package download failed from " . $download_url . ". Check server internet access or cURL / allow_url_fopen permissions.");
    }

    private function http_get($url) {
        // Local disk file fallback
        if (@file_exists($url) && @is_file($url)) {
            $local_content = @file_get_contents($url);
            if ($local_content !== false && strlen(trim($local_content)) > 10) {
                return $local_content;
            }
        }

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $has_open_basedir = !empty(ini_get('open_basedir'));
            if (!$has_open_basedir) {
                @curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                @curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
            }
            curl_setopt($ch, CURLOPT_TIMEOUT, 20);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
            curl_setopt($ch, CURLOPT_USERAGENT, 'MovieHubHQ-Updater/3.8');
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);

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

        // Attempt 2: Stream context fallback
        $ctx = stream_context_create([
            'http' => [
                'method'          => 'GET',
                'timeout'         => 20,
                'follow_location' => 1,
                'header'          => "User-Agent: MovieHubHQ-Updater/3.8\r\nAccept: application/json, */*\r\n"
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
