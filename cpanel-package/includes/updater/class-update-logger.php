<?php
/**
 * Update Logger
 * Handles step logging, error formatting, and sensitive credential redaction.
 */

class SLEA_UpdateLogger {
    private $update_id;
    private $logs = [];
    private $log_file;

    public function __construct($update_id) {
        $this->update_id = $update_id;
        $log_dir = APP_ROOT . '/temp/logs';
        if (!is_dir($log_dir)) {
            @mkdir($log_dir, 0755, true);
        }
        $this->log_file = $log_dir . '/' . $update_id . '.log';
    }

    public function log($step, $message, $type = 'info') {
        $timestamp = date('Y-m-d H:i:s');
        $clean_message = $this->redact_sensitive_data($message);
        
        $entry = [
            'time' => $timestamp,
            'step' => $step,
            'type' => $type,
            'msg'  => $clean_message
        ];

        $this->logs[] = $entry;

        $log_line = sprintf("[%s] [%s] [%s] %s\n", $timestamp, strtoupper($type), $step, $clean_message);
        @file_put_contents($this->log_file, $log_line, FILE_APPEND);

        $this->sync_db_log($step, $clean_message, $type);
    }

    public function get_logs() {
        return $this->logs;
    }

    public function redact_sensitive_data($text) {
        if (!is_string($text)) {
            return $text;
        }
        // Redact passwords, DB_PASS, secret keys
        $patterns = [
            '/(password|passwd|pass|db_pass|secret|token)\s*=\s*[\'"][^\'"]+[\'"]/i' => '$1=***REDACTED***',
            '/(authorization:\s*bearer\s+)[a-zA-Z0-9_\-\.]+/i' => '$1***REDACTED***',
            '/(DB_PASS\', \')[^\']+(\')/i' => 'DB_PASS\', \'***REDACTED***$2'
        ];
        return preg_replace(array_keys($patterns), array_values($patterns), $text);
    }

    private function sync_db_log($step, $message, $type) {
        try {
            $pdo = SLEA_DB::get_connection();
            $stmt = $pdo->prepare("SELECT details_json FROM update_history WHERE update_id = :uid LIMIT 1");
            $stmt->execute([':uid' => $this->update_id]);
            $row = $stmt->fetch();

            $details = [];
            if ($row && !empty($row['details_json'])) {
                $details = json_decode($row['details_json'], true) ?: [];
            }

            $details['logs'] = $this->logs;

            $update = $pdo->prepare("UPDATE update_history SET step = :step, details_json = :details, completed_at = :now WHERE update_id = :uid");
            $update->execute([
                ':step'    => $step,
                ':details' => json_encode($details, JSON_UNESCAPED_SLASHES),
                ':now'     => date('Y-m-d H:i:s'),
                ':uid'     => $this->update_id
            ]);
        } catch (Exception $e) {
            // Ignore logger DB error
        }
    }
}
