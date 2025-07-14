<?php
/**
 * Authentication helper functions for Bato Medical Report System
 */

// Function to check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Function to require login
function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: login.php");
        exit;
    }
}

// Function to check user role
function hasRole($roles) {
    if (!isLoggedIn()) {
        return false;
    }
    
    if (!is_array($roles)) {
        $roles = [$roles];
    }
    
    return in_array($_SESSION['role'], $roles);
}

// Function to require specific role
function requireRole($roles) {
    if (!hasRole($roles)) {
        if (!isLoggedIn()) {
            header("Location: login.php");
        } else {
            header("Location: unauthorized.php");
        }
        exit;
    }
}

// Function to log user activity
function logUserActivity($activityType, $entityId = null, $details = null, $entityName = null) {
    if (!isLoggedIn()) {
        return false;
    }
    
    global $conn;
    if (!isset($conn) || !$conn) {
        require_once 'database.php';
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($conn->connect_error) {
            return false;
        }
    }
    
    // Get current timestamp with timezone
    $timestamp = date('Y-m-d H:i:s');
    
    // Check if the user_activity_log table has the necessary columns
    $checkColumnsQuery = "SHOW COLUMNS FROM user_activity_log LIKE 'details'";
    $columnsResult = $conn->query($checkColumnsQuery);
    
    // If the details column doesn't exist, alter the table to add it
    if ($columnsResult->num_rows == 0) {
        $alterQuery = "ALTER TABLE user_activity_log 
                       ADD COLUMN details TEXT NULL AFTER entity_id, 
                       ADD COLUMN entity_name VARCHAR(255) NULL AFTER details";
        $conn->query($alterQuery);
    }
    
    // Prepare the query with the new columns
    $logQuery = "INSERT INTO user_activity_log 
                (user_id, activity_type, entity_id, details, entity_name, ip_address, user_agent, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    $logStmt = $conn->prepare($logQuery);
    $userId = $_SESSION['user_id'];
    $ip = $_SERVER['REMOTE_ADDR'];
    $userAgent = $_SERVER['HTTP_USER_AGENT'];
    $logStmt->bind_param("isisssss", $userId, $activityType, $entityId, $details, $entityName, $ip, $userAgent, $timestamp);
    $result = $logStmt->execute();
    $logStmt->close();
    
    return $result;
}

// sanitize() function is already defined in database.php

// Function to get current user information
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    global $conn;
    if (!isset($conn) || !$conn) {
        require_once 'database.php';
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($conn->connect_error) {
            return null;
        }
    }
    
    $query = "SELECT * FROM users WHERE id = ?";
    $stmt = $conn->prepare($query);
    $userId = $_SESSION['user_id'];
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        return $result->fetch_assoc();
    }
    
    return null;
}

// Function to get user by ID
function getUserById($userId) {
    global $conn;
    if (!isset($conn) || !$conn) {
        require_once 'database.php';
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($conn->connect_error) {
            return null;
        }
    }
    
    $query = "SELECT * FROM users WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        return $result->fetch_assoc();
    }
    
    return null;
}

// Function to get user name by ID
function getUserNameById($userId) {
    $user = getUserById($userId);
    return $user ? $user['full_name'] : 'Unknown User';
}
?>
