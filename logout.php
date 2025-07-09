<?php
// Start session
session_start();

// Include timezone configuration
require_once 'config/timezone.php';

// Include database configuration
require_once 'config/database.php';

// Check if user is logged in
if (isset($_SESSION['user_id'])) {
    // Log logout activity
    $user_id = $_SESSION['user_id'];
    
    global $conn;
    if (!isset($conn) || !$conn) {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($conn->connect_error) {
            die("Connection failed: " . $conn->connect_error);
        }
    }
    
    $logQuery = "INSERT INTO user_activity_log (user_id, activity_type, ip_address, user_agent) VALUES (?, 'logout', ?, ?)";
    $logStmt = $conn->prepare($logQuery);
    $ip = $_SERVER['REMOTE_ADDR'];
    $userAgent = $_SERVER['HTTP_USER_AGENT'];
    $logStmt->bind_param("iss", $user_id, $ip, $userAgent);
    $logStmt->execute();
    $logStmt->close();
}

// Destroy all session data
$_SESSION = array();

// If session cookie is used, destroy it
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy the session
session_destroy();

// Redirect to login page
header("Location: login.php");
exit;
?>
