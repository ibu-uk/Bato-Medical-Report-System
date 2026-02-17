<?php
echo "Patient dashboard file is accessible and working!\n";
echo "Current time: " . date('Y-m-d H:i:s') . "\n";
echo "Token parameter: " . ($_GET['token'] ?? 'NOT_SET') . "\n";

// Test basic database connection
require_once 'config/database.php';
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    echo "Database connection: FAILED\n";
} else {
    echo "Database connection: SUCCESS\n";
}

$conn->close();
?>
