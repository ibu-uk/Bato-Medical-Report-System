<?php
// Include database configuration
require_once 'config/data.php';

echo "<h1>Fix Report File Numbers</h1>";

try {
    // Connect to database
    $conn = getDbConnection();
    
    // Check if file_number column exists
    $checkQuery = "SHOW COLUMNS FROM patients LIKE 'file_number'";
    $checkResult = $conn->query($checkQuery);
    
    if ($checkResult->num_rows == 0) {
        echo "<p class='error'>The file_number column does not exist in the patients table. Please run update_schema.php first.</p>";
        echo "<p><a href='update_schema.php'>Run Update Schema</a></p>";
    } else {
        // Get all reports with their patients
        $reportQuery = "SELECT r.id as report_id, r.patient_id, p.file_number 
                       FROM reports r 
                       JOIN patients p ON r.patient_id = p.id";
        $reportResult = $conn->query($reportQuery);
        
        if ($reportResult->num_rows > 0) {
            $updatedCount = 0;
            $notUpdatedCount = 0;
            
            echo "<h2>Updating Reports:</h2>";
            echo "<table border='1' cellpadding='5'>";
            echo "<tr><th>Report ID</th><th>Patient ID</th><th>File Number</th><th>Status</th></tr>";
            
            while ($row = $reportResult->fetch_assoc()) {
                $reportId = $row['report_id'];
                $patientId = $row['patient_id'];
                $fileNumber = $row['file_number'];
                
                echo "<tr>";
                echo "<td>" . $reportId . "</td>";
                echo "<td>" . $patientId . "</td>";
                echo "<td>" . ($fileNumber ? $fileNumber : 'Not set') . "</td>";
                
                if ($fileNumber) {
                    echo "<td class='success'>File number available</td>";
                    $updatedCount++;
                } else {
                    // If file_number is not set, update it with a default format
                    $defaultFileNumber = 'N-' . str_pad($patientId, 4, '0', STR_PAD_LEFT);
                    
                    // Update the patient's file_number
                    $updateQuery = "UPDATE patients SET file_number = '$defaultFileNumber' WHERE id = $patientId";
                    if ($conn->query($updateQuery)) {
                        echo "<td class='warning'>Updated with default: $defaultFileNumber</td>";
                        $updatedCount++;
                    } else {
                        echo "<td class='error'>Failed to update</td>";
                        $notUpdatedCount++;
                    }
                }
                echo "</tr>";
            }
            
            echo "</table>";
            echo "<p>Total reports processed: " . $reportResult->num_rows . "</p>";
            echo "<p>Reports with file numbers: " . $updatedCount . "</p>";
            echo "<p>Reports not updated: " . $notUpdatedCount . "</p>";
            
            echo "<p class='success'>Process completed. All patients should now have file numbers.</p>";
        } else {
            echo "<p>No reports found in the system.</p>";
        }
    }
    
    echo "<p><a href='check_file_numbers.php'>Check File Numbers</a></p>";
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
    .warning {
        color: orange;
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
