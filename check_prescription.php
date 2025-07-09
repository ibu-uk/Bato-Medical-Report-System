<?php
// Include database configuration
require_once 'config/database.php';

echo "<h1>Prescription Table Check</h1>";

// Check if prescription table exists
$query = "SHOW TABLES LIKE 'prescription'";
$result = executeQuery($query);

if ($result->num_rows > 0) {
    echo "<p>The 'prescription' table exists.</p>";
    
    // Check prescription table structure
    $query = "DESCRIBE prescription";
    $result = executeQuery($query);
    
    echo "<h2>Prescription Table Structure</h2>";
    echo "<table border='1'><tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>{$row['Field']}</td>";
        echo "<td>{$row['Type']}</td>";
        echo "<td>{$row['Null']}</td>";
        echo "<td>{$row['Key']}</td>";
        echo "<td>{$row['Default']}</td>";
        echo "<td>{$row['Extra']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Check if there's any data in the table
    $query = "SELECT * FROM prescription LIMIT 10";
    $result = executeQuery($query);
    
    echo "<h2>Prescription Sample Data</h2>";
    if ($result->num_rows > 0) {
        echo "<table border='1'><tr>";
        
        // Get column names
        $fields = $result->fetch_fields();
        foreach ($fields as $field) {
            echo "<th>{$field->name}</th>";
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
        echo "<p>No data found in prescription table.</p>";
    }
} else {
    echo "<p>The 'prescription' table does not exist.</p>";
}
?>
