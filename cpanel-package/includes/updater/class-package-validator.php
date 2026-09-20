<?php
/**
 * Package Validator
 * Validates ZIP integrity, SHA256 checksums, PHP compatibility, and Zip Slip vulnerabilities.
 */

class SLEA_PackageValidator {
    private $logger;

    public function __construct(SLEA_UpdateLogger $logger) {
        $this->logger = $logger;
    }

    public function validate_zip($zip_file, $expected_checksum = '') {
        $this->logger->log("Verifying package", "Validating ZIP package integrity...");

        if (!file_exists($zip_file)) {
            throw new Exception("ZIP package file does not exist: " . basename($zip_file));
        }

        // 1. Checksum verification
        if (!empty($expected_checksum)) {
            $actual_checksum = hash_file('sha256', $zip_file);
            if (strtolower($actual_checksum) !== strtolower($expected_checksum)) {
                throw new Exception("SHA-256 checksum mismatch! Expected: " . $expected_checksum . ", Got: " . $actual_checksum);
            }
            $this->logger->log("Verifying package", "SHA-256 checksum verified successfully.");
        }

        // 2. ZipArchive validity check
        if (!class_exists('ZipArchive')) {
            throw new Exception("PHP ZipArchive extension is required on the host server to perform updates.");
        }

        $zip = new ZipArchive();
        $res = $zip->open($zip_file);
        if ($res !== true) {
            throw new Exception("Failed to open update ZIP package. Error code: " . $res);
        }

        // 3. Zip Slip & Path Traversal Security Check
        $num_files = $zip->numFiles;
        $has_entrypoint = false;

        for ($i = 0; $i < $num_files; $i++) {
            $filename = $zip->getNameIndex($i);

            // Zip Slip protection check
            if (
                strpos($filename, '../') !== false ||
                strpos($filename, '..\\') !== false ||
                strpos($filename, '/..') !== false ||
                strpos($filename, '\\..') !== false ||
                strpos($filename, ':') !== false ||
                substr($filename, 0, 1) === '/' ||
                substr($filename, 0, 1) === '\\'
            ) {
                $zip->close();
                throw new Exception("Security Violation (Zip Slip): Unsafe file path detected in ZIP entry: '" . $filename . "'");
            }

            // Entrypoint / key file detection
            if (in_array(basename($filename), ['index.php', 'config.php', 'view.php', 'api.php'])) {
                $has_entrypoint = true;
            }
        }

        $zip->close();

        if (!$has_entrypoint) {
            throw new Exception("Invalid update package structure: No core application files (index.php, config.php, view.php) found in package.");
        }

        $this->logger->log("Verifying package", "ZIP structure and Zip Slip security validation passed (" . $num_files . " entries).");
        return true;
    }

    public function check_compatibility($manifest = []) {
        $this->logger->log("Checking compatibility", "Checking server environment compatibility...");

        $min_php = !empty($manifest['minimum_php']) ? $manifest['minimum_php'] : '8.0';
        if (version_compare(PHP_VERSION, $min_php, '<')) {
            throw new Exception("Server PHP version (" . PHP_VERSION . ") does not meet the minimum requirement (" . $min_php . ") for this update.");
        }

        $required_exts = ['pdo', 'json', 'zip'];
        $missing_exts = [];
        foreach ($required_exts as $ext) {
            if (!extension_loaded($ext)) {
                $missing_exts[] = $ext;
            }
        }

        if (!empty($missing_exts)) {
            throw new Exception("Missing required PHP extensions: " . implode(', ', $missing_exts));
        }

        $this->logger->log("Checking compatibility", "Server environment passed all PHP & extension checks.");
        return true;
    }
}
