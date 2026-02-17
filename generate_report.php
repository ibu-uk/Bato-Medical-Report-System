<?php
// Start session
session_start();

// Include timezone configuration
require_once 'config/timezone.php';

// Include database configuration
require_once 'config/database.php';

// Include authentication helpers
require_once 'config/auth.php';

// Require login to access this page
requireLogin();

// Get a single database connection for the entire process
$conn = getDbConnection();

// Check if form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $patientId = sanitize($_POST['patient_id']);
    $doctorId = sanitize($_POST['doctor_id']);
    $reportDate = sanitize($_POST['report_date']);
    $generatedBy = sanitize($_POST['generated_by']);
    $userId = isset($_POST['user_id']) ? sanitize($_POST['user_id']) : $_SESSION['user_id'];
    
    // Get test data
    $testTypeIds = isset($_POST['test_type_id']) ? $_POST['test_type_id'] : [];
    $testValues = isset($_POST['test_value']) ? $_POST['test_value'] : [];
    $testFlags = isset($_POST['test_flag']) ? $_POST['test_flag'] : [];
    $testRemarks = isset($_POST['test_remarks']) ? $_POST['test_remarks'] : [];
    
    // Get conclusion
    $conclusion = isset($_POST['conclusion']) ? sanitize($_POST['conclusion']) : null;
    
    // Insert report into database
    $reportQuery = "INSERT INTO reports (patient_id, doctor_id, report_date, generated_by, user_id, created_at) 
                   VALUES (?, ?, ?, ?, ?, NOW())";
    
    $stmt = $conn->prepare($reportQuery);
    if (!$stmt) {
        echo "<div class='alert alert-danger'>Error preparing report statement: " . $conn->error . "</div>";
        $conn->close();
        exit;
    }
    
    $stmt->bind_param("iisss", $patientId, $doctorId, $reportDate, $generatedBy, $userId);
    
    if ($stmt->execute()) {
        $reportId = $conn->insert_id;
        echo "<div class='alert alert-success'>Report saved successfully! Report ID: $reportId</div>";
    } else {
        echo "<div class='alert alert-danger'>Error saving report: " . $stmt->error . "</div>";
        $conn->close();
        exit;
    }
    
    // Insert test results
    if ($reportId && count($testTypeIds) > 0) {
        for ($i = 0; $i < count($testTypeIds); $i++) {
            if (!empty($testTypeIds[$i]) && isset($testValues[$i])) {
                $testTypeId = sanitize($testTypeIds[$i]);
                $testValue = sanitize($testValues[$i]);
                $testFlag = isset($testFlags[$i]) ? sanitize($testFlags[$i]) : null;
                $testRemark = isset($testRemarks[$i]) ? sanitize($testRemarks[$i]) : null;
                
                $testQuery = "INSERT INTO report_tests (report_id, test_type_id, test_value, flag, remarks) 
                             VALUES (?, ?, ?, ?, ?)";
                
                $testStmt = $conn->prepare($testQuery);
                $testStmt->bind_param("iisss", $reportId, $testTypeId, $testValue, $testFlag, $testRemark);
                
                if (!$testStmt->execute()) {
                    echo "<div class='alert alert-danger'>Error saving test result: " . $testStmt->error . "</div>";
                }
            }
        }
    }
    
    // Close connection
    $conn->close();
    
    // Redirect to view the report
    header("Location: view_report.php?id=$reportId");
    exit;
}
?>
