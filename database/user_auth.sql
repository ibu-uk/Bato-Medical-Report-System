-- User Authentication for Bato Medical Report System
USE bato_medical;

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    role ENUM('admin', 'doctor', 'receptionist', 'nurse') NOT NULL,
    doctor_id INT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    last_login DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE SET NULL
);

-- User activity log
CREATE TABLE IF NOT EXISTS user_activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    activity_type ENUM('login', 'logout', 'create_report', 'view_report', 'print_report', 'create_prescription', 'view_prescription', 'print_prescription', 'create_treatment', 'view_treatment', 'print_treatment', 'add_patient', 'edit_patient') NOT NULL,
    entity_id INT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Add user_id column to reports table
ALTER TABLE reports 
ADD COLUMN user_id INT NULL AFTER generated_by,
ADD FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL;

-- Add user_id column to prescriptions table
ALTER TABLE prescriptions 
ADD COLUMN user_id INT NULL AFTER consultation_report,
ADD FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL;

-- Add user_id column to nurse_treatments table
ALTER TABLE nurse_treatments 
ADD COLUMN user_id INT NULL AFTER payment_status,
ADD FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL;

-- Create default admin user (password: admin123)
INSERT INTO users (username, password, full_name, email, role) VALUES
('admin', '$2y$10$8Ux8OXwR0RVp9Ug5aZ/ZWOUFCNQuiQJwpVGz4OlFPQXEKJW5m5KMa', 'System Administrator', 'admin@example.com', 'admin');
