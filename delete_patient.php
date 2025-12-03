<?php
// Start session
session_start();

// Include configuration files
require_once 'config/database.php';
require_once 'config/auth.php';

// Check if user is logged in and has admin/doctor role
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'doctor')) {
    $_SESSION['error'] = 'You do not have permission to perform this action.';
    header('Location: login.php');
    exit();
}

// Check if patient ID is provided
if (!isset($_POST['patient_id']) || !is_numeric($_POST['patient_id'])) {
    $_SESSION['error'] = 'Invalid patient ID.';
    header('Location: patient_list.php');
    exit();
}

$patient_id = (int)$_POST['patient_id'];

// Create database connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
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
    
    $_SESSION['success'] = 'Patient and all related records have been deleted successfully.';
    
} catch (Exception $e) {
    // An error occurred, rollback the transaction
    $conn->rollback();
    
    // Log the error (you might want to implement proper error logging)
    error_log("Error deleting patient: " . $e->getMessage());
    
    $_SESSION['error'] = 'An error occurred while deleting the patient. Please try again.';
}

// Close connection
$conn->close();

// Redirect back to patient list
header('Location: patient_list.php');
exit();
