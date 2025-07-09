<?php
// Include database configuration
require_once 'config/database.php';

echo "<h1>Check Patient File Numbers</h1>";

try {
    // Connect to database
    $conn = getDbConnection();
    
    // Check if file_number column exists
    $checkQuery = "SHOW COLUMNS FROM patients LIKE 'file_number'";
    $checkResult = $conn->query($checkQuery);
    
    if ($checkResult->num_rows == 0) {
        echo "<p class='error'>The file_number column does not exist in the patients table.</p>";
    } else {
        echo "<p class='success'>The file_number column exists in the patients table.</p>";
        
        // Check if file_number values are populated
        $patientQuery = "SELECT id, name, civil_id, file_number FROM patients LIMIT 10";
        $patientResult = $conn->query($patientQuery);
        
        if ($patientResult->num_rows > 0) {
            echo "<h2>Sample Patient Records:</h2>";
            echo "<table border='1' cellpadding='5'>";
            echo "<tr><th>ID</th><th>Name</th><th>Civil ID</th><th>File Number</th></tr>";
            
            while ($row = $patientResult->fetch_assoc()) {
                echo "<tr>";
                echo "<td>" . $row['id'] . "</td>";
                echo "<td>" . $row['name'] . "</td>";
                echo "<td>" . $row['civil_id'] . "</td>";
                echo "<td>" . ($row['file_number'] ? $row['file_number'] : 'Not set') . "</td>";
                echo "</tr>";
            }
            
            echo "</table>";
        } else {
            echo "<p>No patient records found.</p>";
        }
        
        // Check reports with file numbers
        echo "<h2>Sample Reports with Patient File Numbers:</h2>";
        $reportQuery = "SELECT r.id, p.name, p.file_number 
                       FROM reports r 
                       JOIN patients p ON r.patient_id = p.id 
                       LIMIT 10";
        $reportResult = $conn->query($reportQuery);
        
        if ($reportResult->num_rows > 0) {
            echo "<table border='1' cellpadding='5'>";
            echo "<tr><th>Report ID</th><th>Patient Name</th><th>File Number</th></tr>";
            
            while ($row = $reportResult->fetch_assoc()) {
                echo "<tr>";
                echo "<td>" . $row['id'] . "</td>";
                echo "<td>" . $row['name'] . "</td>";
                echo "<td>" . ($row['file_number'] ? $row['file_number'] : 'Not set') . "</td>";
                echo "</tr>";
            }
            
            echo "</table>";
        } else {
            echo "<p>No reports found.</p>";
        }
    }
    
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
    h1, h2 {
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
    table {
        border-collapse: collapse;
        margin-bottom: 20px;
    }
    th {
        background-color: #f2f2f2;
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
