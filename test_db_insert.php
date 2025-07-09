<?php
// Include database configuration
require_once 'config/data.php';

echo "<h1>Database Test Script</h1>";

// Test direct database connection
echo "<h2>Testing Database Connection</h2>";
try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }
    echo "<p style='color: green;'>Database connection successful!</p>";
    echo "<p>Server info: " . $conn->server_info . "</p>";
    echo "<p>Host info: " . $conn->host_info . "</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>Connection error: " . $e->getMessage() . "</p>";
    exit;
}

// Show tables in the database
echo "<h2>Database Tables</h2>";
$result = $conn->query("SHOW TABLES");
if ($result) {
    echo "<ul>";
    while ($row = $result->fetch_row()) {
        echo "<li>" . $row[0] . "</li>";
    }
    echo "</ul>";
} else {
    echo "<p style='color: red;'>Error listing tables: " . $conn->error . "</p>";
}

// Show prescriptions table structure
echo "<h2>Prescriptions Table Structure</h2>";
$result = $conn->query("DESCRIBE prescriptions");
if ($result) {
    echo "<table border='1'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['Field'] . "</td>";
        echo "<td>" . $row['Type'] . "</td>";
        echo "<td>" . $row['Null'] . "</td>";
        echo "<td>" . $row['Key'] . "</td>";
        echo "<td>" . $row['Default'] . "</td>";
        echo "<td>" . $row['Extra'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color: red;'>Error describing prescriptions table: " . $conn->error . "</p>";
}

// Test direct insert
echo "<h2>Testing Direct Insert</h2>";
try {
    // Get a valid patient ID and doctor ID
    $patientResult = $conn->query("SELECT id FROM patients LIMIT 1");
    $doctorResult = $conn->query("SELECT id FROM doctors LIMIT 1");
    
    if (!$patientResult || !$doctorResult) {
        throw new Exception("Could not find valid patient or doctor IDs");
    }
    
    $patientRow = $patientResult->fetch_assoc();
    $doctorRow = $doctorResult->fetch_assoc();
    
    $patient_id = $patientRow['id'];
    $doctor_id = $doctorRow['id'];
    $prescription_date = date('Y-m-d');
    $consultation_report = "Test consultation report";
    $medications_str = "Test Medicine|1 tablet daily||Another Medicine|2 tablets twice daily";
    
    echo "<p>Using patient_id: $patient_id, doctor_id: $doctor_id</p>";
    
    // Try direct query first
    $query = "INSERT INTO prescriptions (patient_id, doctor_id, prescription_date, consultation_report, medications) 
              VALUES ('$patient_id', '$doctor_id', '$prescription_date', '$consultation_report', '$medications_str')";
    
    echo "<p>Query: $query</p>";
    
    if ($conn->query($query)) {
        $insert_id = $conn->insert_id;
        echo "<p style='color: green;'>Direct query insert successful! New ID: $insert_id</p>";
    } else {
        echo "<p style='color: red;'>Direct query insert failed: " . $conn->error . "</p>";
    }
    
    // Try prepared statement
    $stmt = $conn->prepare("INSERT INTO prescriptions (patient_id, doctor_id, prescription_date, consultation_report, medications) VALUES (?, ?, ?, ?, ?)");
    if (!$stmt) {
        echo "<p style='color: red;'>Prepare statement failed: " . $conn->error . "</p>";
    } else {
        $stmt->bind_param("iisss", $patient_id, $doctor_id, $prescription_date, $consultation_report, $medications_str);
        if ($stmt->execute()) {
            $insert_id = $stmt->insert_id;
            echo "<p style='color: green;'>Prepared statement insert successful! New ID: $insert_id</p>";
        } else {
            echo "<p style='color: red;'>Prepared statement insert failed: " . $stmt->error . "</p>";
        }
        $stmt->close();
    }
    
    // Check if the records were actually inserted
    echo "<h2>Verifying Inserted Records</h2>";
    $result = $conn->query("SELECT * FROM prescriptions WHERE medications IS NOT NULL ORDER BY id DESC LIMIT 5");
    if ($result && $result->num_rows > 0) {
        echo "<table border='1'>";
        echo "<tr><th>ID</th><th>Patient ID</th><th>Doctor ID</th><th>Date</th><th>Medications</th></tr>";
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . $row['id'] . "</td>";
            echo "<td>" . $row['patient_id'] . "</td>";
            echo "<td>" . $row['doctor_id'] . "</td>";
            echo "<td>" . $row['prescription_date'] . "</td>";
            echo "<td>" . htmlspecialchars($row['medications']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p style='color: red;'>No records found with medications or query error: " . $conn->error . "</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}

$conn->close();
echo "<p><a href='prescriptions.php'>Back to Prescriptions</a></p>";
?>
