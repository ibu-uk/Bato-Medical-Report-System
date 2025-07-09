<?php
// Start session
session_start();

// Include database configuration
require_once 'config/database.php';

// Set up error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Updating Clinic Information</h1>";

// New clinic information
$clinicInfo = [
    'name' => 'BATO CLINIC',
    'address' => 'Salmiya, Block 1, Street 75, Building 24',
    'phone' => '60072702, 60082802',
    'email' => 'clinicbato@gmail.com',
    'website' => 'www.batoclinic.com',
    'logo_path' => 'assets/images/logo.png'
];

// Check if clinic_info table exists
$checkTableQuery = "SHOW TABLES LIKE 'clinic_info'";
$tableResult = executeQuery($checkTableQuery);

if ($tableResult->num_rows == 0) {
    // Create clinic_info table if it doesn't exist
    $createTableQuery = "CREATE TABLE IF NOT EXISTS clinic_info (
        id INT(11) NOT NULL AUTO_INCREMENT,
        name VARCHAR(100) NOT NULL,
        address VARCHAR(255) NOT NULL,
        phone VARCHAR(100) NOT NULL,
        email VARCHAR(100) NOT NULL,
        website VARCHAR(100) NOT NULL,
        logo_path VARCHAR(255) NOT NULL,
        PRIMARY KEY (id)
    )";
    
    if (executeQuery($createTableQuery)) {
        echo "<p style='color: green;'>Successfully created clinic_info table.</p>";
    } else {
        echo "<p style='color: red;'>Error creating clinic_info table.</p>";
        exit;
    }
}

// Check if there's existing clinic info
$checkExistingQuery = "SELECT * FROM clinic_info LIMIT 1";
$existingResult = executeQuery($checkExistingQuery);

if ($existingResult->num_rows > 0) {
    // Update existing clinic info
    $updateQuery = "UPDATE clinic_info SET 
        name = '" . sanitize($clinicInfo['name']) . "',
        address = '" . sanitize($clinicInfo['address']) . "',
        phone = '" . sanitize($clinicInfo['phone']) . "',
        email = '" . sanitize($clinicInfo['email']) . "',
        website = '" . sanitize($clinicInfo['website']) . "',
        logo_path = '" . sanitize($clinicInfo['logo_path']) . "'
        WHERE id = 1";
    
    if (executeQuery($updateQuery)) {
        echo "<p style='color: green;'>Successfully updated clinic information.</p>";
    } else {
        echo "<p style='color: red;'>Error updating clinic information.</p>";
        exit;
    }
} else {
    // Insert new clinic info
    $insertQuery = "INSERT INTO clinic_info (name, address, phone, email, website, logo_path) 
        VALUES (
            '" . sanitize($clinicInfo['name']) . "',
            '" . sanitize($clinicInfo['address']) . "',
            '" . sanitize($clinicInfo['phone']) . "',
            '" . sanitize($clinicInfo['email']) . "',
            '" . sanitize($clinicInfo['website']) . "',
            '" . sanitize($clinicInfo['logo_path']) . "'
        )";
    
    if (executeQuery($insertQuery)) {
        echo "<p style='color: green;'>Successfully added clinic information.</p>";
    } else {
        echo "<p style='color: red;'>Error adding clinic information.</p>";
        exit;
    }
}

echo "<div style='margin-top: 20px; padding: 15px; background-color: #dff0d8; border: 1px solid #d6e9c6; border-radius: 4px;'>";
echo "<h2 style='color: #3c763d;'>Clinic Information Updated Successfully!</h2>";
echo "<p>The following information has been updated:</p>";
echo "<ul>";
echo "<li><strong>Name:</strong> " . htmlspecialchars($clinicInfo['name']) . "</li>";
echo "<li><strong>Address:</strong> " . htmlspecialchars($clinicInfo['address']) . "</li>";
echo "<li><strong>Phone:</strong> " . htmlspecialchars($clinicInfo['phone']) . "</li>";
echo "<li><strong>Email:</strong> " . htmlspecialchars($clinicInfo['email']) . "</li>";
echo "<li><strong>Website:</strong> " . htmlspecialchars($clinicInfo['website']) . "</li>";
echo "</ul>";
echo "<p>This information will now appear on all reports and PDF downloads.</p>";
echo "<p><a href='reports.php' style='display: inline-block; padding: 10px 20px; background-color: #5cb85c; color: white; text-decoration: none; border-radius: 4px;'>View Reports</a></p>";
echo "</div>";
?>

<style>
body {
    font-family: Arial, sans-serif;
    line-height: 1.6;
    margin: 20px;
    max-width: 800px;
    margin: 0 auto;
}
h1 {
    color: #333;
    border-bottom: 1px solid #ddd;
    padding-bottom: 10px;
}
p {
    margin-bottom: 10px;
}
</style>
