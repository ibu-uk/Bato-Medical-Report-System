<?php
// Include database configuration
require_once 'config/data.php';

// Define BATO SYSTEM database connection
define('BATO_DB_HOST', 'localhost');
define('BATO_DB_USER', 'root');
define('BATO_DB_PASS', '');
define('BATO_DB_NAME', 'rcd_bato_local');

// Create connection to BATO SYSTEM database
function getBatoDbConnection() {
    $conn = new mysqli(BATO_DB_HOST, BATO_DB_USER, BATO_DB_PASS, BATO_DB_NAME);
    
    // Check connection
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    
    return $conn;
}

echo "<h1>Update Patient File Numbers</h1>";

try {
    // Connect to BATO SYSTEM database
    $batoConn = getBatoDbConnection();
    
    // Connect to our new database
    $newConn = getDbConnection();
    
    // Get all patients from our system
    $patientsQuery = "SELECT id, name, civil_id FROM patients";
    $patientsResult = $newConn->query($patientsQuery);
    
    if ($patientsResult && $patientsResult->num_rows > 0) {
        echo "<h2>Updating Patient File Numbers:</h2>";
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>ID</th><th>Name</th><th>Civil ID</th><th>Original File Number</th><th>Status</th></tr>";
        
        $updatedCount = 0;
        $notFoundCount = 0;
        
        while ($patient = $patientsResult->fetch_assoc()) {
            $patientId = $patient['id'];
            $patientName = $patient['name'];
            $civilId = $patient['civil_id'];
            
            // Find matching patient in BATO SYSTEM by civil_id
            $batoQuery = "SELECT increment_number FROM patients WHERE civil_id = '$civilId' LIMIT 1";
            $batoResult = $batoConn->query($batoQuery);
            
            echo "<tr>";
            echo "<td>" . $patientId . "</td>";
            echo "<td>" . $patientName . "</td>";
            echo "<td>" . $civilId . "</td>";
            
            if ($batoResult && $batoResult->num_rows > 0) {
                $batoPatient = $batoResult->fetch_assoc();
                $incrementNumber = $batoPatient['increment_number'];
                $formattedFileNumber = 'N-' . $incrementNumber;
                
                // Update patient file number
                $updateQuery = "UPDATE patients SET file_number = '$formattedFileNumber' WHERE id = $patientId";
                if ($newConn->query($updateQuery)) {
                    echo "<td>" . $formattedFileNumber . "</td>";
                    echo "<td class='success'>Updated successfully</td>";
                    $updatedCount++;
                } else {
                    echo "<td>" . $formattedFileNumber . "</td>";
                    echo "<td class='error'>Failed to update: " . $newConn->error . "</td>";
                }
            } else {
                // Try to find by name if civil_id doesn't match
                $nameSearch = $batoConn->real_escape_string($patientName);
                $batoNameQuery = "SELECT increment_number FROM patients WHERE full_name LIKE '%$nameSearch%' LIMIT 1";
                $batoNameResult = $batoConn->query($batoNameQuery);
                
                if ($batoNameResult && $batoNameResult->num_rows > 0) {
                    $batoPatient = $batoNameResult->fetch_assoc();
                    $incrementNumber = $batoPatient['increment_number'];
                    $formattedFileNumber = 'N-' . $incrementNumber;
                    
                    // Update patient file number
                    $updateQuery = "UPDATE patients SET file_number = '$formattedFileNumber' WHERE id = $patientId";
                    if ($newConn->query($updateQuery)) {
                        echo "<td>" . $formattedFileNumber . "</td>";
                        echo "<td class='warning'>Updated by name match</td>";
                        $updatedCount++;
                    } else {
                        echo "<td>" . $formattedFileNumber . "</td>";
                        echo "<td class='error'>Failed to update: " . $newConn->error . "</td>";
                    }
                } else {
                    echo "<td>Not found</td>";
                    echo "<td class='error'>No matching patient in BATO SYSTEM</td>";
                    $notFoundCount++;
                }
            }
            
            echo "</tr>";
        }
        
        echo "</table>";
        echo "<p>Total patients processed: " . $patientsResult->num_rows . "</p>";
        echo "<p>Patients updated: " . $updatedCount . "</p>";
        echo "<p>Patients not found: " . $notFoundCount . "</p>";
    } else {
        echo "<p>No patients found in the system.</p>";
    }
    
    // Update reports to use patient file numbers
    echo "<h2>Updating Reports:</h2>";
    $reportQuery = "SELECT r.id as report_id, r.patient_id, p.file_number 
                   FROM reports r 
                   JOIN patients p ON r.patient_id = p.id";
    $reportResult = $newConn->query($reportQuery);
    
    if ($reportResult && $reportResult->num_rows > 0) {
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
            } else {
                echo "<td class='error'>No file number available</td>";
            }
            echo "</tr>";
        }
        
        echo "</table>";
    } else {
        echo "<p>No reports found in the system.</p>";
    }
    
    echo "<p><a href='check_file_numbers.php'>Check File Numbers</a></p>";
    echo "<p><a href='index.php'>Return to Medical Report System</a></p>";
    
    $batoConn->close();
    $newConn->close();
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
