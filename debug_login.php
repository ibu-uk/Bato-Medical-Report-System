<?php
// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Start session
session_start();

// Include database configuration
require_once 'config/database.php';

// Connect to database
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get user from database
$username = 'admin';
$query = "SELECT * FROM users WHERE username = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

echo "<h2>User Information</h2>";
if ($result->num_rows === 1) {
    $user = $result->fetch_assoc();
    echo "User found:<br>";
    echo "Username: " . htmlspecialchars($user['username']) . "<br>";
    echo "Password Hash: " . htmlspecialchars($user['password']) . "<br>";
    
    // Test password verification
    $password = 'admin123';
    $verify_result = password_verify($password, $user['password']);
    echo "<br>Password Verification Test:<br>";
    echo "Password: " . htmlspecialchars($password) . "<br>";
    echo "Result: " . ($verify_result ? 'MATCH' : 'NO MATCH') . "<br>";
    
    // Show other user fields
    echo "<br>Other User Information:<br>";
    foreach ($user as $key => $value) {
        if ($key !== 'password') {
            echo htmlspecialchars($key) . ": " . htmlspecialchars($value ?? 'NULL') . "<br>";
        }
    }
} else {
    echo "No user found with username 'admin'";
}

// Close connection
$stmt->close();
$conn->close();
?>
