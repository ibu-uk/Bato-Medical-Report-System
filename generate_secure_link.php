<?php
// Disable all error reporting to prevent HTML output
error_reporting(0);
ini_set('display_errors', 0);

// Start output buffering to prevent any output before JSON
ob_start();

// Start session FIRST - this is critical
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include timezone configuration
require_once 'config/timezone.php';

// Include database configuration
require_once 'config/database.php';

// Include authentication helpers
require_once 'config/auth.php';

// Include secure links functions
require_once 'config/secure_links.php';

// Function to send JSON response and exit
function sendJsonResponse($success, $message, $data = []) {
    // Clean any output buffer completely
    ob_end_clean();
    
    // Create response array
    $response = [
        'success' => $success,
        'message' => $message
    ];
    
    // Add data if provided
    if (!empty($data)) {
        $response = array_merge($response, $data);
    }
    
    // Set headers
    header('Content-Type: application/json');
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: no-cache');
    
    // Output clean JSON
    echo json_encode($response, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES);
    exit;
}

// Check if user is logged in (without redirect)
function isUserLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Debug: Check if session is started
if (!session_id()) {
    error_log("Session not started");
    sendJsonResponse(false, 'Session not started. Please refresh the page and try again.');
}

// Check if user is logged in or admin (allow admins to generate links for patients)
if (!isUserLoggedIn()) {
    error_log("User not logged in. Session data: " . print_r($_SESSION, true));
    sendJsonResponse(false, 'Please login to generate secure links');
}

// Handle POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get and sanitize input
    $reportId = isset($_POST['report_id']) ? (int)$_POST['report_id'] : 0;
    $patientId = isset($_POST['patient_id']) ? (int)$_POST['patient_id'] : 0;
    
    // Validate input
    if ($reportId <= 0 || $patientId <= 0) {
        sendJsonResponse(false, 'Invalid report or patient ID');
    }
    
    // Verify report exists and belongs to the patient
    try {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        
        if ($conn->connect_error) {
            sendJsonResponse(false, 'Database connection failed: ' . $conn->connect_error);
        }
        
        $query = "SELECT r.*, p.name as patient_name FROM reports r 
                  JOIN patients p ON r.patient_id = p.id 
                  WHERE r.id = ? AND r.patient_id = ?";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ii", $reportId, $patientId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            sendJsonResponse(false, 'Report not found or access denied');
        }
        
        $report = $result->fetch_assoc();
        $stmt->close();
        $conn->close();
        
    } catch (Exception $e) {
        sendJsonResponse(false, 'Database error: ' . $e->getMessage());
    }
    
    // Create secure link first
    $token = createSecureReportLink($patientId, '', 8760); // 1 year (365 days * 24 hours = 8760 hours)
    
    if ($token) {
        // Generate patient dashboard file path with token
        $reportFile = 'patient_reports.php?token=' . $token;
        
        // Update the token with the correct file path
        $updateQuery = "UPDATE report_links SET report_file = ? WHERE token = ?";
        $updateStmt = $conn->prepare($updateQuery);
        $updateStmt->bind_param("ss", $reportFile, $token);
        $updateStmt->execute();
        
        $secureUrl = getSecureReportUrl($token);
        $expiryDate = date("Y-m-d H:i:s", strtotime("+48 hours"));
        
        sendJsonResponse(true, 'Secure link generated successfully', [
            'token' => $token,
            'url' => $secureUrl,
            'expiry' => $expiryDate,
            'debug' => 'Response generated successfully'
        ]);
    } else {
        sendJsonResponse(false, 'Failed to generate secure link', [
            'debug_info' => [
                'patient_id' => $patientId,
                'report_id' => $reportId,
                'report_file' => $reportFile,
                'file_exists' => file_exists($reportFile)
            ]
        ]);
    }
} else {
    sendJsonResponse(false, 'Invalid request method');
}

/**
 * Generate PDF report file (integrate with your existing PDF generation)
 * 
 * @param int $reportId Report ID
 * @return string|false File path or false on failure
 */
function generateReportPDF($reportId) {
    // Create reports directory if it doesn't exist
    $reportsDir = __DIR__ . '/reports';
    if (!is_dir($reportsDir)) {
        mkdir($reportsDir, 0755, true);
    }
    
    // Generate PDF using your existing export system
    $pdfPath = $reportsDir . '/patient_report_' . $reportId . '.pdf';
    
    try {
        // Use your existing export system - capture the HTML from view_report.php
        $url = 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . '/view_report.php?id=' . $reportId;
        
        // Get the HTML content from your existing report page
        $htmlContent = file_get_contents($url);
        
        if ($htmlContent === false) {
            throw new Exception("Could not fetch report page");
        }
        
        // Include TCPDF if available
        if (file_exists(__DIR__ . '/lib/tcpdf/tcpdf.php')) {
            require_once __DIR__ . '/lib/tcpdf/tcpdf.php';
            
            // Create PDF from HTML
            $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
            $pdf->SetCreator('Bato Medical Report System');
            $pdf->SetAuthor('Bato Medical Report System');
            $pdf->SetTitle('Medical Report');
            
            // Add a page
            $pdf->AddPage();
            
            // Write the HTML content to PDF
            $pdf->writeHTML($htmlContent, true, false, true, false, '');
            
            // Save PDF
            $pdf->Output($pdfPath, 'F');
            
            if (file_exists($pdfPath)) {
                return $pdfPath;
            }
        }
        
        // Fallback: create a simple text file if PDF generation fails
        $fallbackPath = str_replace('.pdf', '.txt', $pdfPath);
        $content = "Medical Report - Report ID: $reportId\n";
        $content .= "Generated on: " . date('Y-m-d H:i:s') . "\n";
        $content .= "This is a placeholder report file.\n";
        
        if (file_put_contents($fallbackPath, $content)) {
            return $fallbackPath;
        }
        
    } catch (Exception $e) {
        error_log("PDF generation failed: " . $e->getMessage());
    }
    
    return false;
}
?>
