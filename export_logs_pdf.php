<?php
// Start session
session_start();

// Include timezone configuration
require_once 'config/timezone.php';

// Include database configuration
require_once 'config/database.php';

// Include authentication helpers
require_once 'config/auth.php';

// Require admin role to access this page
requireRole('admin');

// Include TCPDF library
require_once 'lib/tcpdf/tcpdf.php';

// Create new PDF document
$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

// Set document information
$pdf->SetCreator('Bato Medical Report System');
$pdf->SetAuthor('Bato Medical Report System');
$pdf->SetTitle('Activity Logs Report');
$pdf->SetSubject('User Activity Logs');

// Set default header data with transparent background
$pdf->SetHeaderData('', 0, 'Bato Medical Report System', 'Activity Logs Report', array(0,0,0), array(0,0,0), 0, array(255,255,255));

// Set header and footer fonts
$pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
$pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));

// Set default monospaced font
$pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);

// Set margins for better table layout
$pdf->SetMargins(15, 15, 15, 15); // left, top, right, bottom
$tableLeftMargin = 15; // Adjust as needed
$pdf->SetX($tableLeftMargin);
$pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
$pdf->SetFooterMargin(PDF_MARGIN_FOOTER);

// Set auto page breaks
$pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);

// Set image scale factor
$pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

// Set some language-dependent strings (optional)
if (@file_exists(dirname(__FILE__).'/lang/eng.php')) {
    require_once(dirname(__FILE__).'/lang/eng.php');
    $pdf->setLanguageArray($l);
}

// ---------------------------------------------------------

// Set font for Arabic support
$pdf->SetFont('aealarabiya', '', 10);

// Add a page
$pdf->AddPage();

// Get filter parameters
$userId = isset($_GET['user_id']) ? sanitize($_GET['user_id']) : '';
$activityType = isset($_GET['activity_type']) ? sanitize($_GET['activity_type']) : '';
$startDate = isset($_GET['start_date']) ? sanitize($_GET['start_date']) : date('Y-m-d', strtotime('-7 days'));
$endDate = isset($_GET['end_date']) ? sanitize($_GET['end_date']) : date('Y-m-d');

// Build query with filters (same as activity_logs.php)
$query = "SELECT l.*, u.username, u.full_name, u.role 
          FROM user_activity_log l
          LEFT JOIN users u ON l.user_id = u.id
          WHERE 1=1";

$params = array();
$types = "";

if (!empty($userId)) {
    $query .= " AND l.user_id = ?";
    $params[] = $userId;
    $types .= "i";
}

if (!empty($activityType)) {
    $query .= " AND l.activity_type = ?";
    $params[] = $activityType;
    $types .= "s";
}

if (!empty($startDate)) {
    $query .= " AND DATE(l.created_at) >= ?";
    $params[] = $startDate;
    $types .= "s";
}

if (!empty($endDate)) {
    $query .= " AND DATE(l.created_at) <= ?";
    $params[] = $endDate;
    $types .= "s";
}

$query .= " ORDER BY l.created_at DESC LIMIT 1000";

// Execute query - using same connection method as activity_logs.php
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// Build filter description for header
$filterDescription = [];
if (!empty($userId)) {
    $userQuery = "SELECT username, full_name FROM users WHERE id = ?";
    $userStmt = $conn->prepare($userQuery);
    $userStmt->bind_param("i", $userId);
    $userStmt->execute();
    $userResult = $userStmt->get_result();
    if ($user = $userResult->fetch_assoc()) {
        $filterDescription[] = "User: " . htmlspecialchars($user['username'] . ' (' . $user['full_name'] . ')');
    }
}
if (!empty($activityType)) {
    $filterDescription[] = "Activity: " . ucwords(str_replace('_', ' ', $activityType));
}
if (!empty($startDate) && !empty($endDate)) {
    $filterDescription[] = "Date: " . date('d/m/Y', strtotime($startDate)) . " - " . date('d/m/Y', strtotime($endDate));
}

// Add filter information
if (!empty($filterDescription)) {
    $pdf->SetFont('aealarabiya', 'B', 10);
    $pdf->Cell(0, 5, 'Filters Applied:', 0, 1, 'L');
    $pdf->SetFont('aealarabiya', '', 9);
    $pdf->Cell(0, 5, implode(' | ', $filterDescription), 0, 1, 'L');
    $pdf->Ln(5);
}

// Create table header
$pdf->SetFont('aealarabiya', 'B', 9);
$pdf->Cell(35, 7, 'Timestamp', 1, 0, 'L', 1);
$pdf->Cell(50, 7, 'User', 1, 0, 'L', 1);
$pdf->Cell(40, 7, 'Activity Type', 1, 0, 'L', 1);
$pdf->Cell(70, 7, 'Entity', 1, 1, 'L', 1);
$pdf->Ln();

// Reset font for data
$pdf->SetFont('aealarabiya', '', 8);

// Add table data
$rowCount = 0;
while ($row = $result->fetch_assoc()) {
    // Skip specific rows for professional report (you can modify these row numbers)
    $skipRows = [1, 2]; // Add row numbers to skip
    if (in_array($rowCount, $skipRows)) {
        $rowCount++;
        continue;
    }
    // Check if we need a new page
    if ($rowCount > 0 && $rowCount % 25 == 0) {
        $pdf->AddPage();
        // Re-add table header on new page
        $pdf->SetFont('aealarabiya', 'B', 9);
        $pdf->Cell(35, 7, 'Timestamp', 1, 0, 'L');
        $pdf->Cell(50, 7, 'User', 1, 0, 'L');
        $pdf->Cell(40, 7, 'Activity Type', 1, 0, 'L');
        $pdf->Cell(70, 7, 'Entity', 1, 1, 'L');
        $pdf->SetFont('aealarabiya', '', 8);
    }
    
    // Format timestamp
    $timestamp = date('d/m/Y H:i:s', strtotime($row['created_at']));
    
    // Format user info
    $userInfo = htmlspecialchars($row['full_name']) . ' (' . htmlspecialchars($row['username']) . ')';
    
    // Format activity type
    $activityTypeDisplay = str_replace('_', ' ', ucfirst($row['activity_type']));
    
    // Format entity information - using exact same logic as web interface
    $entityInfo = '';
    $reportActions = ['view_report', 'print_report', 'delete_report'];

    if (in_array($row['activity_type'], $reportActions) && $row['entity_id']) {
        $patientName = '';
        // Query patient name from reports table
        $stmtPatient = $conn->prepare("SELECT p.name FROM reports r JOIN patients p ON r.patient_id = p.id WHERE r.id = ? LIMIT 1");
        $stmtPatient->bind_param("i", $row['entity_id']);
        $stmtPatient->execute();
        $stmtPatient->bind_result($patientNameResult);
        if ($stmtPatient->fetch()) {
            $patientName = $patientNameResult;
        }
        $stmtPatient->close();
        
        $entityInfo = 'ID: ' . $row['entity_id'];
        if ($patientName) {
            $entityInfo .= "\nName: " . $patientName;
        }
    } elseif ($row['entity_id']) {
        $entityInfo = 'ID: ' . $row['entity_id'];
    } else {
        $entityInfo = '-';
    }
    
    // Add row to table - using regular Cell instead of MultiCell
    $pdf->Cell(35, 7, $timestamp, 1, 0, 'L', 0, '', 1);
    $pdf->Cell(50, 7, $userInfo, 1, 0, 'L', 0, '', 1);
    $pdf->Cell(40, 7, $activityTypeDisplay, 1, 0, 'L', 0, '', 1);
    $pdf->Cell(70, 7, $entityInfo, 1, 1, 'L', 0, '', 1);
    
    $rowCount++;
}

// Add summary at the bottom
$pdf->Ln(10);
$pdf->SetFont('aealarabiya', 'B', 10);
$pdf->Cell(0, 5, 'Summary:', 0, 1, 'L');
$pdf->SetFont('aealarabiya', '', 9);
$pdf->Cell(0, 5, 'Total Records: ' . $rowCount, 0, 1, 'L');
$pdf->Cell(0, 5, 'Generated on: ' . date('d/m/Y H:i:s'), 0, 1, 'L');

// Close and output PDF document
$pdf->Output('activity_logs_' . date('Y-m-d_H-i-s') . '.pdf', 'I');

// Close database connection
$conn->close();
?>
