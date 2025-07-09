<?php
// Include database configuration
require_once 'config/data.php';

// Get prescription ID from URL or use a default for testing
$prescriptionId = isset($_GET['id']) ? sanitize($_GET['id']) : '1';

echo "<h1>Add Test Medication</h1>";

// Check if the prescription exists
$prescriptionQuery = "SELECT * FROM prescriptions WHERE id = '$prescriptionId'";
$prescriptionResult = executeQuery($prescriptionQuery);

if (!$prescriptionResult || $prescriptionResult->num_rows === 0) {
    echo "<p>No prescription found with ID: $prescriptionId</p>";
    exit;
}

$prescription = $prescriptionResult->fetch_assoc();
echo "<h2>Adding medication to prescription ID: $prescriptionId</h2>";
echo "<p>Patient ID: {$prescription['patient_id']}</p>";
echo "<p>Doctor ID: {$prescription['doctor_id']}</p>";
echo "<p>Date: {$prescription['prescription_date']}</p>";

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $medicine_name = sanitize($_POST['medicine_name']);
    $dose = sanitize($_POST['dose']);
    
    if (empty($medicine_name)) {
        echo "<p style='color: red;'>Medicine name is required</p>";
    } else {
        // Insert the medication
        $query = "INSERT INTO prescription_medications (prescription_id, medicine_name, dose) 
                  VALUES ('$prescriptionId', '$medicine_name', '$dose')";
        $result = executeQuery($query);
        
        if ($result) {
            echo "<p style='color: green;'>Medication added successfully!</p>";
        } else {
            echo "<p style='color: red;'>Failed to add medication</p>";
        }
    }
}

// Display current medications
$medicationsQuery = "SELECT * FROM prescription_medications WHERE prescription_id = '$prescriptionId'";
$medicationsResult = executeQuery($medicationsQuery);

echo "<h2>Current Medications</h2>";
if ($medicationsResult && $medicationsResult->num_rows > 0) {
    echo "<table border='1'>";
    echo "<tr><th>ID</th><th>Medicine Name</th><th>Dose</th></tr>";
    
    while ($row = $medicationsResult->fetch_assoc()) {
        echo "<tr>";
        echo "<td>{$row['id']}</td>";
        echo "<td>" . htmlspecialchars($row['medicine_name']) . "</td>";
        echo "<td>" . htmlspecialchars($row['dose']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No medications found for this prescription</p>";
}

// Add medication form
?>
<h2>Add New Medication</h2>
<form method="post" action="">
    <div>
        <label>Medicine/Product:</label>
        <input type="text" name="medicine_name" required>
    </div>
    <div>
        <label>Dose:</label>
        <input type="text" name="dose">
    </div>
    <button type="submit">Add Medication</button>
</form>

<p><a href="view_prescription.php?id=<?php echo $prescriptionId; ?>">View Prescription</a></p>
<p><a href="prescriptions.php">Back to Prescriptions</a></p>
