<?php
// Include database configuration
require_once 'config/data.php';

// Get prescription ID from URL or use a default for testing
$prescriptionId = isset($_GET['id']) ? sanitize($_GET['id']) : '1';

echo "<h1>Test Adding Medication</h1>";
echo "<p>Adding medication to prescription ID: $prescriptionId</p>";

// Check if the prescription exists
$prescriptionQuery = "SELECT * FROM prescriptions WHERE id = '$prescriptionId'";
$prescriptionResult = executeQuery($prescriptionQuery);

if (!$prescriptionResult || $prescriptionResult->num_rows === 0) {
    echo "<p>No prescription found with ID: $prescriptionId</p>";
    exit;
}

// Add a test medication
$medicine_name = "Test Medicine";
$dose = "1 tablet daily";

$query = "INSERT INTO prescription_medications (prescription_id, medicine_name, dose) 
          VALUES ('$prescriptionId', '$medicine_name', '$dose')";
$result = executeQuery($query);

if ($result) {
    echo "<p>Successfully added test medication to prescription ID: $prescriptionId</p>";
    echo "<p>Medicine Name: $medicine_name</p>";
    echo "<p>Dose: $dose</p>";
    
    // Check if it was actually inserted
    $checkQuery = "SELECT * FROM prescription_medications WHERE prescription_id = '$prescriptionId'";
    $checkResult = executeQuery($checkQuery);
    
    if ($checkResult && $checkResult->num_rows > 0) {
        echo "<h2>Medications now in database:</h2>";
        echo "<table border='1'>";
        echo "<tr><th>ID</th><th>Prescription ID</th><th>Medicine Name</th><th>Dose</th></tr>";
        
        while ($row = $checkResult->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . $row['id'] . "</td>";
            echo "<td>" . $row['prescription_id'] . "</td>";
            echo "<td>" . $row['medicine_name'] . "</td>";
            echo "<td>" . $row['dose'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>Failed to retrieve the added medication.</p>";
    }
} else {
    echo "<p>Failed to add test medication.</p>";
}

// Add a link to view the prescription
echo "<p><a href='view_prescription.php?id=$prescriptionId'>View Prescription</a></p>";
?>
