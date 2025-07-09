<?php
// Include database configuration
require_once 'config/data.php';

echo "<h1>Updating Prescription Table</h1>";

// Add medication columns to prescriptions table
$alterQuery = "ALTER TABLE prescriptions 
               ADD COLUMN medications TEXT AFTER consultation_report";

// Execute the query directly
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if ($conn->query($alterQuery) === TRUE) {
    echo "<p>Successfully added medications column to prescriptions table</p>";
} else {
    echo "<p>Error adding medications column: " . $conn->error . "</p>";
    // Check if column already exists
    if (strpos($conn->error, "Duplicate column name") !== false) {
        echo "<p>Column already exists, continuing...</p>";
    } else {
        die("Cannot continue due to database error");
    }
}

// Now migrate any existing medications from prescription_medications to prescriptions
echo "<h2>Migrating existing medications</h2>";

// Get all prescriptions with medications
$query = "SELECT p.id, p.consultation_report, GROUP_CONCAT(CONCAT(pm.medicine_name, '|', pm.dose) SEPARATOR '||') as meds 
          FROM prescriptions p 
          LEFT JOIN prescription_medications pm ON p.id = pm.prescription_id 
          GROUP BY p.id";

$result = $conn->query($query);

if ($result && $result->num_rows > 0) {
    echo "<p>Found " . $result->num_rows . " prescriptions to update</p>";
    
    $updated = 0;
    $failed = 0;
    
    while ($row = $result->fetch_assoc()) {
        $prescriptionId = $row['id'];
        $medications = $row['meds'] ?: '';
        
        // Update prescription with medications
        $updateQuery = "UPDATE prescriptions SET medications = ? WHERE id = ?";
        $stmt = $conn->prepare($updateQuery);
        $stmt->bind_param("si", $medications, $prescriptionId);
        
        if ($stmt->execute()) {
            $updated++;
        } else {
            $failed++;
            echo "<p>Failed to update prescription ID $prescriptionId: " . $stmt->error . "</p>";
        }
        
        $stmt->close();
    }
    
    echo "<p>Updated $updated prescriptions successfully. Failed to update $failed prescriptions.</p>";
} else {
    echo "<p>No prescriptions found with medications or error executing query.</p>";
}

echo "<h2>Update Complete</h2>";
echo "<p><a href='prescriptions.php'>Go to Prescriptions</a></p>";

$conn->close();
?>
