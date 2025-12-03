<?php
// Include database configuration
require_once 'config/database.php';

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

// Start the import process
echo "<h1>Patient Import Process</h1>";

try {
    // Connect to BATO SYSTEM database
    $batoConn = getBatoDbConnection();
    
    // Get patients from BATO SYSTEM
    $query = "SELECT id, civil_id, full_name, mobile_number, increment_number FROM patients";
    $result = $batoConn->query($query);
    
    if ($result) {
        $importCount = 0;
        
        // Connect to our new database
        $newConn = getDbConnection();
        
        // Clear existing patients table to avoid duplicates
        $newConn->query("TRUNCATE TABLE patients");
        
        // Import each patient
        while ($row = $result->fetch_assoc()) {
            $civilId = $newConn->real_escape_string($row['civil_id']);
            $name = $newConn->real_escape_string($row['full_name']);
            $mobile = $newConn->real_escape_string($row['mobile_number']);
            $incrementNumber = $newConn->real_escape_string($row['increment_number']);
            
            // Format file number as N-XXXX
            $formattedFileNumber = 'N-' . $incrementNumber;
            
            // Insert into new database
            $insertQuery = "INSERT INTO patients (name, civil_id, mobile, file_number, created_at) 
                           VALUES ('$name', '$civilId', '$mobile', '$formattedFileNumber', NOW())";
            
            if ($newConn->query($insertQuery)) {
                $importCount++;
                echo "<p>Imported patient: $name</p>";
            } else {
                echo "<p>Error importing patient $name: " . $newConn->error . "</p>";
            }
        }
        
        echo "<h2>Import Complete</h2>";
        echo "<p>Successfully imported $importCount patients.</p>";
        echo "<p><a href='index.php'>Return to Medical Report System</a></p>";
        
        // Close connections
        $newConn->close();
    } else {
        echo "<p>Error: " . $batoConn->error . "</p>";
    }
    
    // Close BATO connection
    $batoConn->close();
    
} catch (Exception $e) {
    echo "<p>Error: " . $e->getMessage() . "</p>";
}
?>
