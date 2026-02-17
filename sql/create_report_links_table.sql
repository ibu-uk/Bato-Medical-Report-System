-- Secure Report Links Table
-- Created for secure patient report access without exposing patient IDs

CREATE TABLE report_links (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    report_file VARCHAR(255) NOT NULL,
    token VARCHAR(64) UNIQUE NOT NULL,
    expiry_date DATETIME NOT NULL,
    is_used TINYINT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_token (token),
    INDEX idx_expiry (expiry_date),
    INDEX idx_patient (patient_id)
);

-- Add foreign key constraint to patients table (optional)
-- ALTER TABLE report_links ADD CONSTRAINT fk_report_links_patient 
-- FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE;

-- Add comment for documentation
ALTER TABLE report_links COMMENT = 'Secure tokens for patient report access';
