<?php
// Include database configuration
require_once 'config/database.php';

echo "<h1>Fix Medications</h1>";

// Get all prescriptions
$query = "SELECT * FROM prescriptions";
$result = executeQuery($query);

if ($result && $result->num_rows > 0) {
    echo "<h2>Found " . $result->num_rows . " prescriptions</h2>";
    
    echo "<table border='1'>";
    echo "<tr><th>ID</th><th>Patient ID</th><th>Doctor ID</th><th>Prescription Date</th><th>Consultation Report</th><th>Action</th></tr>";
    
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td>" . $row['patient_id'] . "</td>";
        echo "<td>" . $row['doctor_id'] . "</td>";
        echo "<td>" . $row['prescription_date'] . "</td>";
        echo "<td>" . htmlspecialchars($row['consultation_report']) . "</td>";
        echo "<td><a href='?fix_id=" . $row['id'] . "'>Fix Medications</a></td>";
        echo "</tr>";
    }
    echo "</table>";
}

// Fix medications for a specific prescription
if (isset($_GET['fix_id'])) {
    $prescription_id = sanitize($_GET['fix_id']);
    
    // Check if prescription exists
    $query = "SELECT * FROM prescriptions WHERE id = '$prescription_id'";
    $result = executeQuery($query);
    
    if ($result && $result->num_rows > 0) {
        $prescription = $result->fetch_assoc();
        
        echo "<h2>Fixing medications for prescription ID: " . $prescription_id . "</h2>";
        
        // Check if consultation_report contains any text
        if (!empty($prescription['consultation_report'])) {
            // Add consultation report as a medication
            $medicine_name = $prescription['consultation_report'];
            $dose = "As directed";
            
            // Check if medication already exists
            $checkQuery = "SELECT * FROM prescription_medications WHERE prescription_id = '$prescription_id'";
            $checkResult = executeQuery($checkQuery);
            
            if ($checkResult && $checkResult->num_rows > 0) {
                echo "<p>Medications already exist for this prescription.</p>";
            } else {
                // Insert medication
                $query = "INSERT INTO prescription_medications (prescription_id, medicine_name, dose) 
                          VALUES ('$prescription_id', '$medicine_name', '$dose')";
                $result = executeQuery($query);
                
                if ($result) {
                    echo "<p>Successfully added medication from consultation report.</p>";
                } else {
                    echo "<p>Failed to add medication.</p>";
                }
            }
        } else {
            echo "<p>No consultation report found to convert to medication.</p>";
        }
        
        // Show current medications
        $query = "SELECT * FROM prescription_medications WHERE prescription_id = '$prescription_id'";
        $result = executeQuery($query);
        
        if ($result && $result->num_rows > 0) {
            echo "<h3>Current Medications:</h3>";
            echo "<table border='1'>";
            echo "<tr><th>ID</th><th>Medicine Name</th><th>Dose</th></tr>";
            
            while ($row = $result->fetch_assoc()) {
                echo "<tr>";
                echo "<td>" . $row['id'] . "</td>";
                echo "<td>" . htmlspecialchars($row['medicine_name']) . "</td>";
                echo "<td>" . htmlspecialchars($row['dose']) . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<p>No medications found for this prescription.</p>";
        }
    } else {
        echo "<p>Prescription not found.</p>";
    }
}

// Add a link to go back to prescriptions
echo "<p><a href='prescriptions.php'>Back to Prescriptions</a></p>";
?>
