-- Create OTP table for patient first-time registration email verification
CREATE TABLE IF NOT EXISTS patient_registration_otp (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    email VARCHAR(255) NOT NULL,
    purpose VARCHAR(30) NOT NULL DEFAULT 'registration',
    otp_hash VARCHAR(255) NOT NULL,
    expires_at DATETIME NOT NULL,
    attempts INT NOT NULL DEFAULT 0,
    max_attempts INT NOT NULL DEFAULT 5,
    is_verified TINYINT(1) NOT NULL DEFAULT 0,
    verified_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_patient_registration_otp_patient_purpose (patient_id, purpose),
    INDEX idx_patient_registration_otp_expires (expires_at),
    INDEX idx_patient_registration_otp_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
