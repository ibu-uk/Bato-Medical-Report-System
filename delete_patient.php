<?php
// Start session
session_start();

// Set content type to JSON
header('Content-Type: application/json');

// Include configuration files
require_once 'config/database.php';
require_once 'config/auth.php';

// Check if user is logged in and has admin/doctor role
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'doctor')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'You do not have permission to perform this action.']);
    exit();
}

// Check if patient ID is provided
if (!isset($_POST['patient_id']) || !is_numeric($_POST['patient_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid patient ID.']);
    exit();
}

$patient_id = (int)$_POST['patient_id'];

// Create database connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
    exit();
}

// Start transaction
$conn->begin_transaction();

try {
    // 1. Delete related records first (to maintain referential integrity)
    
    // Delete reports
    $delete_reports = $conn->prepare("DELETE FROM reports WHERE patient_id = ?");
    $delete_reports->bind_param('i', $patient_id);
    $delete_reports->execute();
    
    // Delete prescriptions
    $delete_prescriptions = $conn->prepare("DELETE FROM prescriptions WHERE patient_id = ?");
    $delete_prescriptions->bind_param('i', $patient_id);
    $delete_prescriptions->execute();
    
    // Delete nurse treatments
    $delete_treatments = $conn->prepare("DELETE FROM nurse_treatments WHERE patient_id = ?");
    $delete_treatments->bind_param('i', $patient_id);
    $delete_treatments->execute();
    
    // 2. Finally, delete the patient
    $delete_patient = $conn->prepare("DELETE FROM patients WHERE id = ?");
    $delete_patient->bind_param('i', $patient_id);
    $delete_patient->execute();
    
    // If we got here, commit the transaction
    $conn->commit();
    
    // Return success response
    echo json_encode([
        'success' => true, 
        'message' => 'Patient and all related records have been deleted successfully.'
    ]);
    
} catch (Exception $e) {
    // An error occurred, rollback the transaction
    $conn->rollback();
    
    // Log the error
    error_log("Error deleting patient: " . $e->getMessage());
    
    // Return error response
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'An error occurred while deleting the patient: ' . $e->getMessage()
    ]);
}

// Close connection
$conn->close();
exit();
