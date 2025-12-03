-- Database schema for Bato Medical Report System

-- Create database if it doesn't exist
CREATE DATABASE IF NOT EXISTS bato_medical;
USE bato_medical;

-- Patients table
CREATE TABLE IF NOT EXISTS patients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    civil_id VARCHAR(20) NOT NULL UNIQUE,
    mobile VARCHAR(20) NOT NULL,
    file_number VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Doctors table
CREATE TABLE IF NOT EXISTS doctors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    position VARCHAR(255) NOT NULL,
    signature_image_path VARCHAR(255)
);

-- Test types table
CREATE TABLE IF NOT EXISTS test_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    unit VARCHAR(50),
    normal_range VARCHAR(100)
);

-- Reports table
CREATE TABLE IF NOT EXISTS reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    doctor_id INT NOT NULL,
    report_date DATE NOT NULL,
    generated_by VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id),
    FOREIGN KEY (doctor_id) REFERENCES doctors(id)
);

-- Report tests table
CREATE TABLE IF NOT EXISTS report_tests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    report_id INT NOT NULL,
    test_type_id INT NOT NULL,
    test_value VARCHAR(50) NOT NULL,
    flag VARCHAR(10) DEFAULT NULL,
    remarks TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (report_id) REFERENCES reports(id) ON DELETE CASCADE,
    FOREIGN KEY (test_type_id) REFERENCES test_types(id)
);

-- Insert sample doctors
INSERT INTO doctors (name, position, signature_image_path) VALUES
('Dr. Ahmed Al-Sayed', 'Consultant Hematologist', 'assets/images/signatures/default_signature.png'),
('Dr. Fatima Al-Qasim', 'Specialist Pathologist', 'assets/images/signatures/default_signature.png'),
('Dr. Mohammed Al-Rashidi', 'Lab Director', 'assets/images/signatures/default_signature.png');

-- Insert sample data for test types (based on the sample report and user requirements)
INSERT INTO test_types (name, unit, normal_range) VALUES
-- CBC Tests
('Red Blood Cell Count (RBC)', 'x 10^12/L', '3.8 - 5.8'),
('Hemoglobin (Hgb)', 'g/L', '11.7 - 15.5'),
('Hematocrit (HCT)', '%', '35.0 - 47.0'),
('Mean Corpuscular Volume (MCV)', 'fL', '80.0 - 99.0'),
('Mean Corpuscular Haemoglobin(MCH)', 'pg', '26.5 - 33.6'),
('Mean Corpuscular Hemoglobin Concentration (MCHC)', 'g/L', '31.5 - 37.0'),
('Red Cell Distribution Width (RDW)', '%', '11.7 - 15.5'),
('White Blood Cell Count (WBC)', 'x 10^9/L', '3.90 - 11.10'),
('Neutrophils', '%', '50.0 - 70.0'),
('Neutrophils', 'x 10^9/L', '2.00 - 7.50'),
('Lymphocyte%', '%', '20.00 - 45.000'),
('Lymphocytes', 'x 10^9/L', '1.50 - 3.50'),
('Lymphocytes', '%', '5.0 - 9.0'),
('Monocytes', 'x 10^9/L', '0.04 - 0.80'),
('Eosinophils', '%', '0.00 - 4.000'),
('Eosinophils', 'x 10^9/L', '0.04 - 0.40'),
('Basophils%', '%', '0.00 - 1.000'),
('Basophils', 'x 10^9/L', '0.015 - 0.10'),
('Platelets Count (PLT)', 'x 10^9/L', '150 - 450'),
('Mean Platelet Volume (MPV)', 'fL', '6.5 - 11.6'),

-- Additional Tests Requested by User
('HCG-QUANTITATIVE', 'mIU/mL', '0 - 5'),
('Urine Culture', '', ''),
('AMH', 'ng/mL', '1.0 - 3.5'),
('Progesterone', 'ng/mL', '0.1 - 0.3'),
('SHBG', 'nmol/L', '20 - 130'),
('DHEA-S', 'μg/dL', '35 - 430'),
('RFT Blood Test', '', ''),
('LFT Blood Test', '', ''),
('Urine Routine', '', ''),
('LH', 'mIU/mL', '1.9 - 12.5'),
('FSH', 'mIU/mL', '2.5 - 10.2'),
('Estradiol', 'pg/mL', '30 - 400'),
('Glutathione', 'μmol/L', '2.0 - 5.5'),
('D Bilirubin', 'mg/dL', '0.0 - 0.3'),
('T Bilirubin', 'mg/dL', '0.1 - 1.2'),
('FBS (Fasting Blood Sugar)', 'mg/dL', '70 - 100'),
('ALT (SGPT)', 'U/L', '7 - 56');

-- Insert sample clinic information
CREATE TABLE IF NOT EXISTS clinic_info (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    logo_path VARCHAR(255) NOT NULL,
    address VARCHAR(255) NOT NULL,
    phone VARCHAR(50) NOT NULL,
    email VARCHAR(100) NOT NULL,
    website VARCHAR(100)
);

INSERT INTO clinic_info (name, logo_path, address, phone, email, website) VALUES
('AL SHAMEL LAB', 'assets/images/logo.png', 'Sulaiman Al Salem Block 2, Street 122, Building 40F, Al-Kuwait Complex', '+965 2208 6700', 'info@alshamellab.com', 'www.alshamellab.com');
