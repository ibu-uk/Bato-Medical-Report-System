<?php
/**
 * Generate Secure Report Link Handler
 * Backend API for generating secure patient report links
 */

// Start output buffering to prevent any HTML output
ob_start();

// Set JSON response header FIRST
header('Content-Type: application/json');

// Include required files
require_once 'config/timezone.php';
require_once 'config/database.php';
require_once 'config/auth.php';
require_once 'config/secure_links.php';

// Function to send JSON response and exit
function sendJsonResponse($success, $message, $data = []) {
    ob_clean(); // Clean any buffered output
    $response = array_merge(['success' => $success, 'message' => $message], $data);
    echo json_encode($response);
    exit;
}

// Require login to access this endpoint
if (!function_exists('requireLogin')) {
    sendJsonResponse(false, 'Authentication system not available');
}

try {
    requireLogin();
} catch (Exception $e) {
    sendJsonResponse(false, 'Authentication failed: ' . $e->getMessage());
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
    
    // Generate PDF file path
    $reportFile = 'reports/patient_report_' . $reportId . '.pdf';
    
    // If PDF doesn't exist, create a simple placeholder
    if (!file_exists($reportFile)) {
        $reportFile = generateReportPDF($reportId);
        
        if (!$reportFile) {
            // Fallback: create a simple text file
            $fallbackPath = str_replace('.pdf', '.txt', $reportFile);
            $content = "Medical Report - Report ID: $reportId\n";
            $content .= "Patient ID: $patientId\n";
            $content .= "Generated on: " . date('Y-m-d H:i:s') . "\n";
            $content .= "This is a placeholder report file.\n";
            $content .= "The actual PDF will be generated when accessed.\n";
            
            if (file_put_contents($fallbackPath, $content)) {
                $reportFile = $fallbackPath;
            } else {
                sendJsonResponse(false, 'Failed to create report file', [
                    'debug_info' => [
                        'patient_id' => $patientId,
                        'report_id' => $reportId,
                        'attempted_file' => $reportFile
                    ]
                ]);
            }
        }
    }
    
    // Create secure link
    $token = createSecureReportLink($patientId, $reportFile, 48);
    
    if ($token) {
        $secureUrl = getSecureReportUrl($token);
        $expiryDate = date("Y-m-d H:i:s", strtotime("+48 hours"));
        
        sendJsonResponse(true, 'Secure link generated successfully', [
            'token' => $token,
            'url' => $secureUrl,
            'expiry' => $expiryDate
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
    
    // Use your existing PDF generation logic
    // We'll simulate the PDF generation by creating a simple PDF for now
    try {
        // Include TCPDF if available
        if (file_exists(__DIR__ . '/lib/tcpdf/tcpdf.php')) {
            require_once __DIR__ . '/lib/tcpdf/tcpdf.php';
            
            // Get report data
            $conn = getDbConnection();
            $query = "SELECT r.*, p.name as patient_name, p.civil_id, p.mobile 
                      FROM reports r 
                      JOIN patients p ON r.patient_id = p.id 
                      WHERE r.id = ?";
            
            $stmt = $conn->prepare($query);
            $stmt->bind_param("i", $reportId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                $stmt->close();
                $conn->close();
                return false;
            }
            
            $report = $result->fetch_assoc();
            $stmt->close();
            $conn->close();
            
            // Create PDF
            $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
            $pdf->SetCreator('Bato Medical Report System');
            $pdf->SetAuthor('Bato Medical Report System');
            $pdf->SetTitle('Medical Report - ' . $report['patient_name']);
            
            $pdf->AddPage();
            $pdf->SetFont('helvetica', '', 12);
            
            // Add report content
            $pdf->Cell(0, 10, 'Bato Medical Report System', 0, 1, 'C');
            $pdf->Ln(10);
            $pdf->Cell(0, 10, 'Patient: ' . $report['patient_name'], 0, 1);
            $pdf->Cell(0, 10, 'Civil ID: ' . $report['civil_id'], 0, 1);
            $pdf->Cell(0, 10, 'Mobile: ' . $report['mobile'], 0, 1);
            $pdf->Ln(10);
            $pdf->Cell(0, 10, 'Report Date: ' . date('d/m/Y', strtotime($report['report_date'])), 0, 1);
            $pdf->Ln(10);
            
            // Add conclusion if available
            if (!empty($report['conclusion'])) {
                $pdf->MultiCell(0, 10, 'Conclusion: ' . $report['conclusion'], 0, 1);
            }
            
            // Save PDF
            $pdf->Output($pdfPath, 'F');
            
            if (file_exists($pdfPath)) {
                return $pdfPath;
            }
        }
        
        // Fallback: create a simple text file if PDF generation fails
        $fallbackPath = str_replace('.pdf', '.txt', $pdfPath);
        $content = "Medical Report - Patient ID: $reportId\n";
        $content .= "Generated on: " . date('Y-m-d H:i:s') . "\n";
        $content .= "This is a placeholder report file.\n";
        
        if (file_put_contents($fallbackPath, $content)) {
            return $fallbackPath;
        }
        
    } catch (Exception $e) {
        // Log error if needed
        error_log("PDF generation failed: " . $e->getMessage());
    }
    
    return false;
}
?>
