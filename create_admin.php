<?php
// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include database configuration
require_once 'config/data.php';

// Connect to database
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create a new admin user
$username = 'admin2';
$password = 'admin123';
$password_hash = password_hash($password, PASSWORD_DEFAULT);
$full_name = 'System Administrator 2';
$email = 'admin2@example.com';
$role = 'admin';

// Check if user already exists
$check_query = "SELECT id FROM users WHERE username = ?";
$check_stmt = $conn->prepare($check_query);
$check_stmt->bind_param("s", $username);
$check_stmt->execute();
$check_result = $check_stmt->get_result();

if ($check_result->num_rows > 0) {
    echo "User '$username' already exists. Updating password...<br>";
    $update_query = "UPDATE users SET password = ? WHERE username = ?";
    $update_stmt = $conn->prepare($update_query);
    $update_stmt->bind_param("ss", $password_hash, $username);
    
    if ($update_stmt->execute()) {
        echo "Password updated successfully!<br>";
    } else {
        echo "Error updating password: " . $conn->error . "<br>";
    }
    $update_stmt->close();
} else {
    echo "Creating new admin user...<br>";
    $insert_query = "INSERT INTO users (username, password, full_name, email, role, is_active) VALUES (?, ?, ?, ?, ?, 1)";
    $insert_stmt = $conn->prepare($insert_query);
    $insert_stmt->bind_param("sssss", $username, $password_hash, $full_name, $email, $role);
    
    if ($insert_stmt->execute()) {
        echo "Admin user created successfully!<br>";
    } else {
        echo "Error creating user: " . $conn->error . "<br>";
    }
    $insert_stmt->close();
}

// Display the hash that was used
echo "<br>Username: $username<br>";
echo "Password: $password<br>";
echo "Generated Hash: $password_hash<br>";
echo "<br>Try logging in with these credentials.<br>";

// Close connection
$check_stmt->close();
$conn->close();
?>
