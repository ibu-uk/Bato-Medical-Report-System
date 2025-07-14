-- New modules for Bato Medical Report System
USE bato_medical;

-- Prescriptions/Medication Cards table
CREATE TABLE IF NOT EXISTS prescriptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    doctor_id INT NOT NULL,
    prescription_date DATE NOT NULL,
    consultation_report TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id),
    FOREIGN KEY (doctor_id) REFERENCES doctors(id)
);

-- Prescription medications table
CREATE TABLE IF NOT EXISTS prescription_medications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    prescription_id INT NOT NULL,
    medicine_name VARCHAR(255) NOT NULL,
    dose VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (prescription_id) REFERENCES prescriptions(id) ON DELETE CASCADE
);

-- Nurse Treatment Reports table
CREATE TABLE IF NOT EXISTS nurse_treatments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    treatment_date DATE NOT NULL,
    nurse_name VARCHAR(255) NOT NULL,
    report TEXT,
    treatment TEXT,
    payment_status ENUM('Unpaid', 'Paid') DEFAULT 'Unpaid',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id)
);
