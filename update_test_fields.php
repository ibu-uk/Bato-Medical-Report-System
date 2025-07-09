<?php
// Include database configuration
require_once 'config/database.php';

echo "<h1>Update Test Fields</h1>";

try {
    // Connect to database
    $conn = getDbConnection();
    
    // Check if flag column exists in report_tests table
    $checkFlagQuery = "SHOW COLUMNS FROM report_tests LIKE 'flag'";
    $checkFlagResult = $conn->query($checkFlagQuery);
    
    if ($checkFlagResult->num_rows == 0) {
        // Add flag column to report_tests table
        $alterFlagQuery = "ALTER TABLE report_tests ADD COLUMN flag VARCHAR(10) DEFAULT NULL AFTER test_value";
        if ($conn->query($alterFlagQuery)) {
            echo "<p class='success'>Successfully added flag column to report_tests table.</p>";
        } else {
            echo "<p class='error'>Error adding flag column: " . $conn->error . "</p>";
        }
    } else {
        echo "<p>flag column already exists in report_tests table.</p>";
    }
    
    // Check if remarks column exists in report_tests table
    $checkRemarksQuery = "SHOW COLUMNS FROM report_tests LIKE 'remarks'";
    $checkRemarksResult = $conn->query($checkRemarksQuery);
    
    if ($checkRemarksResult->num_rows == 0) {
        // Add remarks column to report_tests table
        $alterRemarksQuery = "ALTER TABLE report_tests ADD COLUMN remarks TEXT DEFAULT NULL AFTER flag";
        if ($conn->query($alterRemarksQuery)) {
            echo "<p class='success'>Successfully added remarks column to report_tests table.</p>";
        } else {
            echo "<p class='error'>Error adding remarks column: " . $conn->error . "</p>";
        }
    } else {
        echo "<p>remarks column already exists in report_tests table.</p>";
    }
    
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
    h1 {
        color: #0066cc;
    }
    .success {
        color: green;
        font-weight: bold;
    }
    .error {
        color: red;
        font-weight: bold;
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
