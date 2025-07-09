<?php
// Include database configuration
require_once 'config/database.php';

// Get prescription ID from URL or use a default for testing
$prescriptionId = isset($_GET['id']) ? sanitize($_GET['id']) : '1';

echo "<h1>Prescription Debugging</h1>";
echo "<p>Checking prescription ID: $prescriptionId</p>";

// Get prescription details
$prescriptionQuery = "SELECT p.*, pt.name AS patient_name, pt.civil_id, pt.mobile, pt.file_number,
                d.name as doctor_name, d.position as doctor_position
                FROM prescriptions p
                JOIN patients pt ON p.patient_id = pt.id
                JOIN doctors d ON p.doctor_id = d.id
                WHERE p.id = '$prescriptionId'";
$prescriptionResult = executeQuery($prescriptionQuery);

if (!$prescriptionResult || $prescriptionResult->num_rows === 0) {
    echo "<p>No prescription found with ID: $prescriptionId</p>";
    exit;
}

$prescription = $prescriptionResult->fetch_assoc();
echo "<h2>Prescription Details</h2>";
echo "<pre>";
print_r($prescription);
echo "</pre>";

// Check if prescription_medications table exists
echo "<h2>Checking Tables</h2>";
$checkTable = "SHOW TABLES LIKE 'prescription_medications'";
$tableResult = executeQuery($checkTable);
if ($tableResult && $tableResult->num_rows > 0) {
    echo "<p>Table 'prescription_medications' exists</p>";
} else {
    echo "<p>Table 'prescription_medications' does not exist</p>";
}

// Check if medicines table exists
$checkTable = "SHOW TABLES LIKE 'medicines'";
$tableResult = executeQuery($checkTable);
if ($tableResult && $tableResult->num_rows > 0) {
    echo "<p>Table 'medicines' exists</p>";
} else {
    echo "<p>Table 'medicines' does not exist</p>";
}

// Check if prescription table exists
$checkTable = "SHOW TABLES LIKE 'prescription'";
$tableResult = executeQuery($checkTable);
if ($tableResult && $tableResult->num_rows > 0) {
    echo "<p>Table 'prescription' exists</p>";
} else {
    echo "<p>Table 'prescription' does not exist</p>";
}

// Get medications from prescription_medications table
echo "<h2>Medications from prescription_medications table</h2>";
$medicationsQuery = "SELECT * FROM prescription_medications 
               WHERE prescription_id = '$prescriptionId'";
$medicationsResult = executeQuery($medicationsQuery);
$medications = [];

if ($medicationsResult && $medicationsResult->num_rows > 0) {
    echo "<p>Found " . $medicationsResult->num_rows . " medications</p>";
    echo "<table border='1'>";
    echo "<tr><th>ID</th><th>Prescription ID</th><th>Medicine Name</th><th>Dose</th></tr>";
    
    while ($row = $medicationsResult->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td>" . $row['prescription_id'] . "</td>";
        echo "<td>" . $row['medicine_name'] . "</td>";
        echo "<td>" . $row['dose'] . "</td>";
        echo "</tr>";
        $medications[] = $row;
    }
    echo "</table>";
} else {
    echo "<p>No medications found in prescription_medications table</p>";
}

// Check if we can find any data in the add_prescription.php file
echo "<h2>Form Field Analysis</h2>";
$formFile = file_get_contents('add_prescription.php');
if ($formFile) {
    echo "<p>Found add_prescription.php file</p>";
    
    // Check for medicine field names
    if (strpos($formFile, 'name="medicine_name[]"') !== false) {
        echo "<p>Form uses field name: medicine_name[]</p>";
    }
    if (strpos($formFile, 'name="medicine[]"') !== false) {
        echo "<p>Form uses field name: medicine[]</p>";
    }
    if (strpos($formFile, 'name="dose[]"') !== false) {
        echo "<p>Form uses field name: dose[]</p>";
    }
} else {
    echo "<p>Could not find add_prescription.php file</p>";
}

// Check the actual data in the database
echo "<h2>Direct Database Query</h2>";
$conn = new mysqli('localhost', 'root', 'TheSild@2025b', 'bato_medical');
if ($conn->connect_error) {
    echo "<p>Connection failed: " . $conn->connect_error . "</p>";
} else {
    $result = $conn->query("SELECT * FROM prescription_medications WHERE prescription_id = '$prescriptionId'");
    if ($result && $result->num_rows > 0) {
        echo "<p>Found " . $result->num_rows . " medications using direct query</p>";
        echo "<table border='1'><tr>";
        
        // Get column names
        $fields = $result->fetch_fields();
        foreach ($fields as $field) {
            echo "<th>" . $field->name . "</th>";
        }
        echo "</tr>";
        
        // Reset pointer
        $result->data_seek(0);
        
        // Display data
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            foreach ($row as $key => $value) {
                echo "<td>" . htmlspecialchars($value) . "</td>";
            }
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No medications found using direct query</p>";
    }
}
?>
