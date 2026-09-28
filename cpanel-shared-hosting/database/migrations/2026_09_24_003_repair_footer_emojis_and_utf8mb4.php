<?php
/**
 * Migration: Convert MySQL settings table to UTF8MB4 & Auto-Repair Corrupted Emojis (??)
 */

$driver = SLEA_DB::get_driver();
$pdo = SLEA_DB::get_connection();

if ($driver === 'mysql') {
    try {
        $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("SET CHARACTER SET utf8mb4");
        $pdo->exec("ALTER TABLE settings CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("ALTER TABLE settings MODIFY setting_value LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("ALTER TABLE pages CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("ALTER TABLE pages MODIFY buttons_json LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("ALTER TABLE pages MODIFY title VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("ALTER TABLE pages MODIFY description TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    } catch (Exception $e) {
        error_log("UTF8MB4 Migration notice: " . $e->getMessage());
    }
}

// Auto-repair existing footer copyright if it contains ?? question marks
try {
    $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'footer_copyright' LIMIT 1");
    $stmt->execute();
    $row = $stmt->fetch();
    if ($row && !empty($row['setting_value'])) {
        $val = $row['setting_value'];
        if (strpos($val, '??') !== false) {
            $repaired = SLEA_Datastore::repair_corrupted_emojis($val);
            if ($repaired !== $val) {
                SLEA_Datastore::save_footer_copyright($repaired);
            }
        }
    }
} catch (Exception $e) {
    error_log("Footer repair migration error: " . $e->getMessage());
}
