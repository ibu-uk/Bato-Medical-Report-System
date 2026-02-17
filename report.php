<?php
/**
 * Secure Patient Report Access Endpoint
 * Provides secure access to patient reports via token validation
 */

// Include required files
require_once 'config/timezone.php';
require_once 'config/secure_links.php';

// Get token from URL
$token = isset($_GET['token']) ? trim($_GET['token']) : '';

// Validate token
if (empty($token)) {
    die('Access denied: No token provided');
}

$tokenData = validateReportToken($token);

if (!$tokenData) {
    die('Access denied: Invalid or expired token');
}

// Get report file path
$reportFile = $tokenData['report_file'];

// Check if it's a patient_reports.php link (patient dashboard with token)
if (strpos($reportFile, 'patient_reports.php') !== false) {
    // Extract token from the file path
    parse_str(parse_url($reportFile, PHP_URL_QUERY), $params);
    $token = $params['token'] ?? '';
    
    if (!empty($token)) {
        // Redirect to patient dashboard with token
        header('Location: ' . $reportFile);
        exit;
    }
}

// Check if it's a clean_report.php link (clean version without navigation)
if (strpos($reportFile, 'clean_report.php') !== false) {
    // Extract report ID from file path
    parse_str(parse_url($reportFile, PHP_URL_QUERY), $params);
    $reportId = $params['id'] ?? 0;
    
    if ($reportId > 0) {
        // Redirect to clean report page
        header('Location: ' . $reportFile);
        exit;
    }
}

// Check if it's a view_report.php link (full version)
if (strpos($reportFile, 'view_report.php') !== false) {
    // Extract report ID from file path
    parse_str(parse_url($reportFile, PHP_URL_QUERY), $params);
    $reportId = $params['id'] ?? 0;
    
    if ($reportId > 0) {
        // Redirect to actual report page
        header('Location: ' . $reportFile);
        exit;
    }
}

// For actual PDF files (if they exist)
if (file_exists($reportFile)) {
    // Get patient information for display
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    $patientQuery = "SELECT p.name FROM patients p WHERE p.id = ?";
    $patientStmt = $conn->prepare($patientQuery);
    $patientStmt->bind_param("i", $tokenData['patient_id']);
    $patientStmt->execute();
    $patientResult = $patientStmt->get_result();
    $patient = $patientResult->fetch_assoc();
    $patientStmt->close();
    $conn->close();
    
    // Set headers for PDF display
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="medical_report_' . date('Y-m-d') . '.pdf"');
    header('Content-Length: ' . filesize($reportFile));
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');
    header('Expires: 0');
    
    // Output the PDF file
    readfile($reportFile);
} else {
    die('Report file not found');
}

// Log the access (optional)
logReportAccess($tokenData['id'], $tokenData['patient_id']);

/**
 * Log report access for security auditing
 * 
 * @param int $tokenId Token ID
 * @param int $patientId Patient ID
 */
function logReportAccess($tokenId, $patientId) {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    $query = "INSERT INTO report_access_log (token_id, patient_id, ip_address, user_agent, accessed_at) 
              VALUES (?, ?, ?, ?, NOW())";
    
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iiss", $tokenId, $patientId, $ipAddress, $userAgent);
    $stmt->execute();
    
    $stmt->close();
    $conn->close();
}
?>
