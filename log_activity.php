<?php
// Start session
session_start();

// Include timezone configuration
require_once 'config/timezone.php';

// Include database configuration
require_once 'config/database.php';

// Include authentication helpers
require_once 'config/auth.php';

// Check if user is logged in
if (!isLoggedIn()) {
    // Return error response
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

// Get parameters from request
$method = $_SERVER['REQUEST_METHOD'];
if ($method === 'GET') {
    $activityType = isset($_GET['type']) ? sanitize($_GET['type']) : '';
    $entityId = isset($_GET['id']) ? sanitize($_GET['id']) : null;
    $details = isset($_GET['details']) ? sanitize($_GET['details']) : null;
    $entityName = isset($_GET['name']) ? sanitize($_GET['name']) : null;
} else if ($method === 'POST') {
    $activityType = isset($_POST['type']) ? sanitize($_POST['type']) : '';
    $entityId = isset($_POST['id']) ? sanitize($_POST['id']) : null;
    $details = isset($_POST['details']) ? sanitize($_POST['details']) : null;
    $entityName = isset($_POST['name']) ? sanitize($_POST['name']) : null;
} else {
    // Return error for unsupported methods
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unsupported request method']);
    exit;
}

// Validate activity type
$validActivityTypes = [
    // Authentication activities
    'login', 'logout', 'failed_login', 'password_change', 'profile_update',
    
    // Report activities
    'create_report', 'view_report', 'edit_report', 'delete_report', 'print_report', 'export_report',
    
    // Patient activities
    'add_patient', 'edit_patient', 'delete_patient', 'view_patient', 'search_patient',
    
    // Test type activities
    'add_test_type', 'edit_test_type', 'delete_test_type', 'view_test_types',
    
    // Doctor activities
    'add_doctor', 'edit_doctor', 'delete_doctor', 'view_doctors',
    
    // User management activities
    'add_user', 'edit_user', 'delete_user', 'view_users', 'change_user_role',
    
    // System activities
    'system_settings', 'backup_data', 'restore_data', 'view_logs',
    
    // Other activities
    'create_prescription', 'view_prescription', 'edit_prescription', 'delete_prescription', 'print_prescription',
    'create_treatment', 'view_treatment', 'edit_treatment', 'delete_treatment', 'print_treatment',
    'import_data', 'export_data'
];

if (!in_array($activityType, $validActivityTypes)) {
    // Return error response
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid activity type: ' . $activityType]);
    exit;
}

// Log the activity with enhanced details
$success = logUserActivity($activityType, $entityId, $details, $entityName);

// Return response
header('Content-Type: application/json');
echo json_encode(['success' => $success]);
exit;
?>
