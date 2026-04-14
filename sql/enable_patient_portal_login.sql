-- Enable patient self-service portal login
-- Run this once in phpMyAdmin for database: bato_medical

ALTER TABLE patients
    ADD COLUMN IF NOT EXISTS email VARCHAR(255) NULL AFTER mobile,
    ADD COLUMN IF NOT EXISTS portal_username VARCHAR(100) NULL,
    ADD COLUMN IF NOT EXISTS portal_password_hash VARCHAR(255) NULL AFTER portal_username,
    ADD COLUMN IF NOT EXISTS portal_is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER portal_password_hash,
    ADD COLUMN IF NOT EXISTS portal_last_login DATETIME NULL AFTER portal_is_active;

SET @idx_exists := (
    SELECT COUNT(1)
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'patients'
      AND index_name = 'uk_patients_portal_username'
);

SET @sql := IF(
    @idx_exists = 0,
    'ALTER TABLE patients ADD UNIQUE KEY uk_patients_portal_username (portal_username)',
    'SELECT "uk_patients_portal_username already exists"'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Optional: quick check
-- SELECT id, name, portal_username, portal_is_active, portal_last_login FROM patients LIMIT 20;
