<?php
// Include database configuration
require_once '../config/database.php';

// Check if patient ID is provided
if (isset($_POST['patient_id'])) {
    $patientId = sanitize($_POST['patient_id']);
    
    // Get patient details
    $query = "SELECT mobile FROM patients WHERE id = '$patientId'";
    $result = executeQuery($query);
    
    if ($result && $result->num_rows > 0) {
        $patient = $result->fetch_assoc();
        
        // Return patient details as JSON
        echo json_encode([
            'success' => true,
            'mobile' => $patient['mobile']
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Patient not found'
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Patient ID not provided'
    ]);
}
?>
