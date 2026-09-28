-- Migration: Initialize One-Click Application Update System Tables
CREATE TABLE IF NOT EXISTS migrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    migration_name VARCHAR(255) NOT NULL UNIQUE,
    batch INT DEFAULT 1,
    executed_at DATETIME NOT NULL
);

CREATE TABLE IF NOT EXISTS update_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    update_id VARCHAR(64) NOT NULL UNIQUE,
    old_version VARCHAR(30) NOT NULL,
    new_version VARCHAR(30) NOT NULL,
    status VARCHAR(30) NOT NULL,
    step VARCHAR(100) DEFAULT '',
    error_message TEXT,
    rollback_status VARCHAR(30) DEFAULT '',
    migration_status VARCHAR(30) DEFAULT '',
    started_at DATETIME NOT NULL,
    completed_at DATETIME,
    details_json LONGTEXT
);
