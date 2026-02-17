<?php
require_once 'config/database.php';

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die("DB Connection failed: " . $conn->connect_error);
}

echo "Database connection: SUCCESS\n";

// Check if report_links table exists
$result = $conn->query("SHOW TABLES LIKE 'report_links'");
if ($result->num_rows > 0) {
    echo "report_links table: EXISTS\n";
} else {
    echo "report_links table: MISSING\n";
}

// Check if there are any tokens
$result = $conn->query("SELECT COUNT(*) as count FROM report_links");
$row = $result->fetch_assoc();
echo "Tokens in database: " . $row['count'] . "\n";

$conn->close();
?>
