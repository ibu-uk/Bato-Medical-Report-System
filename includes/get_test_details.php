<?php
// Include database configuration
require_once '../config/database.php';

// Check if test ID is provided
if (isset($_POST['test_id'])) {
    $testId = sanitize($_POST['test_id']);
    
    // Get test details
    $query = "SELECT unit, normal_range FROM test_types WHERE id = '$testId'";
    $result = executeQuery($query);
    
    if ($result && $result->num_rows > 0) {
        $test = $result->fetch_assoc();
        
        // Return test details as JSON
        echo json_encode([
            'success' => true,
            'unit' => $test['unit'],
            'normal_range' => $test['normal_range']
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Test not found'
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Test ID not provided'
    ]);
}
?>
