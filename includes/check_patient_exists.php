<?php
// Include database configuration
require_once '../config/database.php';

// Set headers for JSON response
header('Content-Type: application/json');

// Default response
$response = [
    'exists' => false,
    'message' => ''
];

// Check if required parameters are provided
if (isset($_POST['field']) && isset($_POST['value'])) {
    $field = sanitize($_POST['field']);
    $value = sanitize($_POST['value']);
    
    // Validate field name to prevent SQL injection
    $allowedFields = ['civil_id', 'file_number', 'name'];
    
    if (!in_array($field, $allowedFields)) {
        $response['message'] = 'Invalid field name';
        echo json_encode($response);
        exit;
    }
    
    // Query to check if the value exists for the given field
    $query = "SELECT id FROM patients WHERE $field = '$value'";
    $result = executeQuery($query);
    
    if ($result && $result->num_rows > 0) {
        $response['exists'] = true;
        $response['message'] = "A patient with this $field already exists";
    }
}

// Return JSON response
echo json_encode($response);
?>
