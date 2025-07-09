<?php
// Include database configuration
require_once 'config/data.php';

echo "<h1>Database Schema Update</h1>";

try {
    // Connect to database
    $conn = getDbConnection();
    
    // Check if file_number column exists
    $checkQuery = "SHOW COLUMNS FROM patients LIKE 'file_number'";
    $checkResult = $conn->query($checkQuery);
    
    if ($checkResult->num_rows == 0) {
        // Add file_number column to patients table
        $alterQuery = "ALTER TABLE patients ADD COLUMN file_number VARCHAR(20) AFTER mobile";
        if ($conn->query($alterQuery)) {
            echo "<p class='success'>Successfully added file_number column to patients table.</p>";
        } else {
            echo "<p class='error'>Error adding file_number column: " . $conn->error . "</p>";
        }
    } else {
        echo "<p>file_number column already exists in patients table.</p>";
    }
    
    // Check if clinic_info table exists
    $checkTableQuery = "SHOW TABLES LIKE 'clinic_info'";
    $checkTableResult = $conn->query($checkTableQuery);
    
    if ($checkTableResult->num_rows == 0) {
        // Create clinic_info table
        $createTableQuery = "CREATE TABLE IF NOT EXISTS clinic_info (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            address TEXT NOT NULL,
            phone VARCHAR(50) NOT NULL,
            email VARCHAR(100) NOT NULL,
            website VARCHAR(100) NOT NULL,
            logo_path VARCHAR(255) NOT NULL
        )";
        
        if ($conn->query($createTableQuery)) {
            echo "<p class='success'>Successfully created clinic_info table.</p>";
            
            // Insert default clinic info
            $insertQuery = "INSERT INTO clinic_info (name, address, phone, email, website, logo_path) 
                           VALUES ('Bato Medical Center', 'Kuwait City, Block 3, Street 5', '+965 2222 3333', 'info@batomedical.com', 'www.batomedical.com', 'assets/images/logo.png')";
            
            if ($conn->query($insertQuery)) {
                echo "<p class='success'>Successfully added default clinic information.</p>";
            } else {
                echo "<p class='error'>Error adding clinic information: " . $conn->error . "</p>";
            }
        } else {
            echo "<p class='error'>Error creating clinic_info table: " . $conn->error . "</p>";
        }
    } else {
        echo "<p>clinic_info table already exists.</p>";
    }
    
    echo "<p><a href='import_patients.php'>Go to Import Patients</a></p>";
    echo "<p><a href='index.php'>Return to Medical Report System</a></p>";
    
    $conn->close();
} catch (Exception $e) {
    echo "<p class='error'>Error: " . $e->getMessage() . "</p>";
}
?>
<style>
    body {
        font-family: Arial, sans-serif;
        margin: 20px;
        line-height: 1.6;
    }
    h1 {
        color: #0066cc;
    }
    .success {
        color: green;
        font-weight: bold;
    }
    .error {
        color: red;
        font-weight: bold;
    }
    a {
        display: inline-block;
        margin-top: 10px;
        padding: 8px 16px;
        background-color: #0066cc;
        color: white;
        text-decoration: none;
        border-radius: 4px;
    }
    a:hover {
        background-color: #0052a3;
    }
</style>
