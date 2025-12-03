<?php
// Start session
session_start();

// Set headers to prevent caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Include configurations
require_once 'config/timezone.php';
require_once 'config/database.php';

// Initialize error handling
$error = null;

try {
    // Check if user is logged in
    if (isset($_SESSION['user_id'])) {
        // Log logout activity
        $user_id = $_SESSION['user_id'];
        
        // Ensure database connection
        global $conn;
        if (!isset($conn) || !$conn) {
            $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
            if ($conn->connect_error) {
                throw new Exception("Database connection failed: " . $conn->connect_error);
            }
        }
        
        // Log the logout activity
        $logQuery = "INSERT INTO user_activity_log (user_id, activity_type, ip_address, user_agent) VALUES (?, 'logout', ?, ?)";
        if ($logStmt = $conn->prepare($logQuery)) {
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
            $logStmt->bind_param("iss", $user_id, $ip, $userAgent);
            $logStmt->execute();
            $logStmt->close();
        }
    }

    // Clear all session variables
    $_SESSION = array();

    // Delete the session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }

    // Destroy the session
    session_destroy();

    // If this is an AJAX request, return JSON response
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'redirect' => 'login.php']);
        exit;
    }
    
    // Standard redirect for non-AJAX requests
    header("Location: login.php");
    exit;

} catch (Exception $e) {
    $error = $e->getMessage();
    error_log("Logout error: " . $error);
    
    // If this is an AJAX request, return error as JSON
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        header('Content-Type: application/json', true, 500);
        echo json_encode(['success' => false, 'error' => 'Logout failed: ' . $error]);
        exit;
    }
    
    // For non-AJAX, redirect to login with error message
    $_SESSION['error'] = 'Logout failed. Please try again.';
    header("Location: login.php");
    exit;
}