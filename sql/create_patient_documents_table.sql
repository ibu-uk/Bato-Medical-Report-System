-- Patient File Management module migration
-- Creates patient_documents table and adds new columns/indexes safely.

CREATE TABLE IF NOT EXISTS patient_documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    uploaded_by INT NOT NULL DEFAULT 0,
    document_title VARCHAR(255) NOT NULL,
    document_category VARCHAR(50) NOT NULL DEFAULT 'other',
    notes TEXT NULL,
    expiry_date DATE NULL,
    reminder_days_before INT NOT NULL DEFAULT 0,
    file_path VARCHAR(255) NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_mime VARCHAR(100) NOT NULL,
    file_size INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_patient_documents_patient_id (patient_id),
    INDEX idx_patient_documents_category (document_category),
    INDEX idx_patient_documents_created_at (created_at),
    INDEX idx_patient_documents_expiry_date (expiry_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Migration for existing databases
SET @expiry_col_exists := (
    SELECT COUNT(1)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'patient_documents'
      AND column_name = 'expiry_date'
);

SET @add_expiry_col_sql := IF(
    @expiry_col_exists = 0,
    'ALTER TABLE patient_documents ADD COLUMN expiry_date DATE NULL AFTER notes',
    'SELECT 1'
);

PREPARE stmt_add_expiry_col FROM @add_expiry_col_sql;
EXECUTE stmt_add_expiry_col;
DEALLOCATE PREPARE stmt_add_expiry_col;

SET @reminder_col_exists := (
    SELECT COUNT(1)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'patient_documents'
      AND column_name = 'reminder_days_before'
);

SET @add_reminder_col_sql := IF(
    @reminder_col_exists = 0,
    'ALTER TABLE patient_documents ADD COLUMN reminder_days_before INT NOT NULL DEFAULT 0 AFTER expiry_date',
    'SELECT 1'
);

PREPARE stmt_add_reminder_col FROM @add_reminder_col_sql;
EXECUTE stmt_add_reminder_col;
DEALLOCATE PREPARE stmt_add_reminder_col;

SET @idx_exists := (
    SELECT COUNT(1)
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'patient_documents'
      AND index_name = 'idx_patient_documents_expiry_date'
);

SET @create_idx_sql := IF(
    @idx_exists = 0,
    'CREATE INDEX idx_patient_documents_expiry_date ON patient_documents (expiry_date)',
    'SELECT 1'
);

PREPARE stmt_create_idx FROM @create_idx_sql;
EXECUTE stmt_create_idx;
DEALLOCATE PREPARE stmt_create_idx;
