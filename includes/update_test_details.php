<?php
// Include database configuration
require_once '../config/database.php';

// Include authentication helpers
require_once '../config/auth.php';

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if all required fields are provided
    if (isset($_POST['test_id']) && isset($_POST['unit']) && isset($_POST['normal_range'])) {
        $testId = sanitize($_POST['test_id']);
        $unit = sanitize($_POST['unit']);
        $normalRange = sanitize($_POST['normal_range']);
        
        // Update test details in the database
        $query = "UPDATE test_types SET unit = '$unit', normal_range = '$normalRange' WHERE id = '$testId'";
        $result = executeQuery($query);
        
        // Log the activity
        $userId = $_SESSION['user_id'] ?? 0;
        $testName = '';
        
        // Get test name for logging
        $nameQuery = "SELECT name FROM test_types WHERE id = '$testId'";
        $nameResult = executeQuery($nameQuery);
        if ($nameResult && $nameResult->num_rows > 0) {
            $row = $nameResult->fetch_assoc();
            $testName = $row['name'];
        }
        
        // Insert into activity log
        $logQuery = "INSERT INTO activity_logs (user_id, activity_type, details, timestamp) 
                    VALUES ('$userId', 'test_update', 'Updated test type: $testName', NOW())";
        executeQuery($logQuery);
        
        // Return response
        if ($result) {
            echo json_encode([
                'success' => true,
                'message' => 'Test details updated successfully'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Failed to update test details'
            ]);
        }
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Missing required fields'
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
}
?>
