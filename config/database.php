<?php
/**
 * Database configuration file
 */

// Database credentials
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', 'TheSild@2025b');
define('DB_NAME', 'medical-v2');

// Create database connection
function getDbConnection() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    // Check connection
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    
    return $conn;
}

// Execute a query and return the result
function executeQuery($sql) {
    $conn = getDbConnection();
    $result = $conn->query($sql);
    $conn->close();
    return $result;
}

// Execute a query and return the inserted ID
function executeInsert($sql) {
    $conn = getDbConnection();
    $conn->query($sql);
    $lastId = $conn->insert_id;
    $conn->close();
    return $lastId;
}

// Sanitize input data
function sanitize($data) {
    $conn = getDbConnection();
    return $conn->real_escape_string($data);
}
?>
