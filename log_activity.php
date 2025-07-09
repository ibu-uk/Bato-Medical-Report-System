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

// Get activity type and entity ID from request
$activityType = isset($_GET['type']) ? sanitize($_GET['type']) : '';
$entityId = isset($_GET['id']) ? sanitize($_GET['id']) : null;

// Validate activity type
$validActivityTypes = [
    'login', 'logout', 'create_report', 'view_report', 'print_report', 
    'create_prescription', 'view_prescription', 'print_prescription', 
    'create_treatment', 'view_treatment', 'print_treatment', 
    'add_patient', 'edit_patient'
];

if (!in_array($activityType, $validActivityTypes)) {
    // Return error response
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid activity type']);
    exit;
}

// Log the activity
$success = logUserActivity($activityType, $entityId);

// Return response
header('Content-Type: application/json');
echo json_encode(['success' => $success]);
exit;
?>
