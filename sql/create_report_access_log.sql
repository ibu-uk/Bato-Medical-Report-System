-- Report Access Log Table
-- Tracks all secure report access for security auditing

CREATE TABLE report_access_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    token_id INT NOT NULL,
    patient_id INT NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    accessed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_token_id (token_id),
    INDEX idx_patient_id (patient_id),
    INDEX idx_accessed_at (accessed_at),
    FOREIGN KEY (token_id) REFERENCES report_links(id) ON DELETE CASCADE
);

-- Add comment for documentation
ALTER TABLE report_access_log COMMENT = 'Security audit log for report access';
