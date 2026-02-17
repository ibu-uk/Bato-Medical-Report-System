<?php
// Disable all error reporting and output buffering to prevent any HTML/output before JSON
error_reporting(0);
ini_set('display_errors', 0);
ob_start();

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include required files
require_once 'config/timezone.php';
require_once 'config/database.php';
require_once 'config/auth.php';
require_once 'config/secure_links.php';

// Handle POST request only
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

// Get input data
$reportId = isset($_POST['report_id']) ? (int)$_POST['report_id'] : 0;
$patientId = isset($_POST['patient_id']) ? (int)$_POST['patient_id'] : 0;

// Debug: Log the input values
error_log("DEBUG: report_id=$reportId, patient_id=$patientId");

// Validate input
if ($reportId <= 0 || $patientId <= 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid report or patient ID']);
    exit;
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Please login to generate secure links']);
    exit;
}

try {
    // Get database connection
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($conn->connect_error) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Database connection failed']);
        exit;
    }
    
    // Verify report exists and belongs to patient
    $query = "SELECT r.*, p.name as patient_name FROM reports r 
               JOIN patients p ON r.patient_id = p.id
               WHERE r.id = ? AND r.patient_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $reportId, $patientId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Debug: Log query results
    error_log("DEBUG: Query executed, result count: " . $result->num_rows);
    
    if ($result->num_rows === 0) {
        error_log("DEBUG: No report found - Report ID: $reportId, Patient ID: $patientId");
        $conn->close();
        sendJsonResponse(false, 'Report not found');
    }
    
    $report = $result->fetch_assoc();
    $stmt->close();
    $conn->close();
    
    // Debug: Log successful report retrieval
    error_log("DEBUG: Report found - ID: " . $report['id'] . ", Patient: " . $report['patient_name']);
    
    // Get the actual patient ID from the report (not from POST) to ensure consistency with token validation
    $reportPatientQuery = "SELECT patient_id FROM reports WHERE id = ?";
    $reportPatientStmt = $conn->prepare($reportPatientQuery);
    $reportPatientStmt->bind_param("i", $reportId);
    $reportPatientStmt->execute();
    $reportPatientResult = $reportPatientStmt->get_result();

    if ($reportPatientResult->num_rows === 0) {
        error_log("DEBUG: Report ID $reportId not found in database");
        $conn->close();
        sendJsonResponse(false, 'Report not found');
    }

    $reportPatientData = $reportPatientResult->fetch_assoc();
    $actualPatientId = $reportPatientData['patient_id'];

    error_log("DEBUG: Report $reportId belongs to patient_id $actualPatientId");

    // Use the actual patient ID from the report to ensure consistency with token validation
    $patientId = $actualPatientId;
    
    // Create secure link
    try {
        error_log("DEBUG: Attempting to create secure link for patient_id: $patientId, report_id: $reportId");
        
        // Test database connection first
        $testConn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($testConn->connect_error) {
            error_log("DEBUG: Database connection failed: " . $testConn->connect_error);
            sendJsonResponse(false, 'Database connection failed');
        }
        $testConn->close();
        
        $token = createSecureReportLink($patientId, '', 8760);
        
        error_log("DEBUG: createSecureReportLink returned: " . ($token ? 'SUCCESS' : 'FAILED'));
        
        if ($token) {
            // Generate patient dashboard URL
            $reportFile = 'patient_reports.php?token=' . $token;
            
            $secureUrl = getSecureReportUrl($token);
            $expiryDate = date("Y-m-d H:i:s", strtotime("+48 hours"));
            
            error_log("DEBUG: Sending success response with token: $token");
            
            // Clean any output buffer and send JSON
            ob_clean();
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'message' => 'Secure link generated successfully',
                'token' => $token,
                'url' => $secureUrl,
                'expiry' => $expiryDate
            ]);
            exit;
        } else {
            error_log("DEBUG: Sending failure response - createSecureReportLink returned false");
            
            // Clean any output buffer and send JSON
            ob_clean();
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Failed to generate secure link'
            ]);
            exit;
        }
    } catch (Exception $e) {
        error_log("DEBUG: Exception caught: " . $e->getMessage());
        sendJsonResponse(false, 'Error: ' . $e->getMessage());
    }
    
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
