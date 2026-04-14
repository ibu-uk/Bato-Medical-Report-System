-- Enable patient self-service portal login
-- Run this once in phpMyAdmin for database: bato_medical

SET @db_name := DATABASE();

SET @col_exists := (
    SELECT COUNT(1)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'patients'
      AND COLUMN_NAME = 'email'
);
SET @sql := IF(
    @col_exists = 0,
    'ALTER TABLE patients ADD COLUMN email VARCHAR(255) NULL AFTER mobile',
    'SELECT "patients.email already exists"'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists := (
    SELECT COUNT(1)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'patients'
      AND COLUMN_NAME = 'portal_username'
);
SET @sql := IF(
    @col_exists = 0,
    'ALTER TABLE patients ADD COLUMN portal_username VARCHAR(100) NULL',
    'SELECT "patients.portal_username already exists"'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists := (
    SELECT COUNT(1)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'patients'
      AND COLUMN_NAME = 'portal_password_hash'
);
SET @sql := IF(
    @col_exists = 0,
    'ALTER TABLE patients ADD COLUMN portal_password_hash VARCHAR(255) NULL AFTER portal_username',
    'SELECT "patients.portal_password_hash already exists"'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists := (
    SELECT COUNT(1)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'patients'
      AND COLUMN_NAME = 'portal_is_active'
);
SET @sql := IF(
    @col_exists = 0,
    'ALTER TABLE patients ADD COLUMN portal_is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER portal_password_hash',
    'SELECT "patients.portal_is_active already exists"'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists := (
    SELECT COUNT(1)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'patients'
      AND COLUMN_NAME = 'portal_last_login'
);
SET @sql := IF(
    @col_exists = 0,
    'ALTER TABLE patients ADD COLUMN portal_last_login DATETIME NULL AFTER portal_is_active',
    'SELECT "patients.portal_last_login already exists"'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

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
