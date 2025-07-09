<?php
// Start session
session_start();

// Include database configuration
require_once 'config/database.php';

// Check if TCPDF is installed
if (!file_exists('lib/tcpdf/tcpdf.php')) {
    die('TCPDF library not found. Please install TCPDF in the lib/tcpdf directory.');
}

// Include TCPDF library
require_once('lib/tcpdf/tcpdf.php');

// Check if report ID is provided
if (!isset($_GET['id'])) {
    header("Location: index.php");
    exit;
}

$reportId = sanitize($_GET['id']);

// Get report details
$reportQuery = "SELECT r.*, p.name as patient_name, p.civil_id, p.mobile, p.file_number,
                d.name as doctor_name, d.position as doctor_position, d.signature_image_path
                FROM reports r
                JOIN patients p ON r.patient_id = p.id
                JOIN doctors d ON r.doctor_id = d.id
                WHERE r.id = '$reportId'";
$reportResult = executeQuery($reportQuery);

if (!$reportResult || $reportResult->num_rows === 0) {
    header("Location: index.php");
    exit;
}

$report = $reportResult->fetch_assoc();

// Get test results
$testsQuery = "SELECT rt.test_value, rt.flag, rt.remarks, tt.name as test_name, tt.unit, tt.normal_range
               FROM report_tests rt
               JOIN test_types tt ON rt.test_type_id = tt.id
               WHERE rt.report_id = '$reportId'";
$testsResult = executeQuery($testsQuery);

// Get clinic info
$clinicQuery = "SELECT * FROM clinic_info LIMIT 1";
$clinicResult = executeQuery($clinicQuery);
$clinic = $clinicResult->fetch_assoc();

// Use patient's file number or generate one if not available
$fileNumber = !empty($report['file_number']) ? $report['file_number'] : 'N-' . str_pad($reportId, 4, '0', STR_PAD_LEFT);

// Format date
$visitDate = date('d/m/Y', strtotime($report['report_date']));
$printedDate = date('d/m/Y H:i A');

// Create new PDF document
class MYPDF extends TCPDF {
    // Page header
    public function Header() {
        // Get clinic info from global variable
        global $clinic;
        
        // Set header position further down from the top edge
        $this->SetY(15); // Start 15mm from top instead of default
        
        // Use text-based logo since server doesn't have Imagick or GD extension
        // Match the style from view_report.php
        $this->SetFont('dejavusans', 'B', 36); // Bold font for BATO
        $this->SetTextColor(0, 0, 0); // Black color
        $this->SetXY(15, $this->GetY());
        $this->Cell(75, 15, 'BATO', 0, 1, 'L');
        
        // Add "Health/Beauty" text below
        $this->SetFont('dejavusans', '', 12);
        $this->SetXY(15, $this->GetY() - 5);
        $this->Cell(75, 5, 'Health/Beauty', 0, 1, 'L');
        
        // Reset text color
        $this->SetTextColor(0, 0, 0);
        
        // Set clinic info
        $this->SetFont('dejavusans', '', 12);
        $this->SetXY(120, $this->GetY() - 20);
        $this->Cell(75, 5, 'BATO CLINIC', 0, 1, 'R');
        
        $this->SetFont('dejavusans', '', 8);
        $this->SetXY(120, $this->GetY());
        $this->Cell(75, 4, $clinic['address'], 0, 1, 'R');
        
        $this->SetXY(120, $this->GetY());
        $this->Cell(75, 4, 'Phone: ' . $clinic['phone'], 0, 1, 'R');
        
        $this->SetXY(120, $this->GetY());
        $this->Cell(75, 4, 'Email: ' . $clinic['email'], 0, 1, 'R');
        
        $this->SetXY(120, $this->GetY());
        $this->Cell(75, 4, 'Website: ' . $clinic['website'], 0, 1, 'R');
        
        // Line - positioned lower to match the view_report.php layout
        $this->Line(15, $this->GetY() + 5, 195, $this->GetY() + 5);
    }

    // Page footer
    public function Footer() {
        // Position at 15 mm from bottom
        $this->SetY(-15);
        // Set font
        $this->SetFont('dejavusans', 'I', 8);
        // Page number
        $this->Cell(0, 10, 'Page '.$this->getAliasNumPage().' of '.$this->getAliasNbPages(), 0, false, 'R', 0, '', 0, false, 'T', 'M');
    }
}

// Create new PDF document with custom margins
$pdf = new MYPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

// Set font subsetting mode
$pdf->setFontSubsetting(true);

// Add Arabic font
$pdf->setRTL(false); // Left-to-right by default
$pdf->SetFont('dejavusans', '', 10);

// Set document information
$pdf->SetCreator('Bato Medical Report System');
$pdf->SetAuthor('Bato Clinic');
$pdf->SetTitle('Medical Report - ' . $report['patient_name']);
$pdf->SetSubject('Medical Report');
$pdf->SetKeywords('Medical, Report, Test, Results');

// Set default header data
$pdf->SetHeaderData(PDF_HEADER_LOGO, PDF_HEADER_LOGO_WIDTH, PDF_HEADER_TITLE, PDF_HEADER_STRING);

// Set header and footer fonts
$pdf->setHeaderFont(Array('dejavusans', '', 10));
$pdf->setFooterFont(Array('dejavusans', '', 8));

// Set default monospaced font
$pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);

// Set margins - increased top margin to move content down
$pdf->SetMargins(15, 50, 15);
$pdf->SetHeaderMargin(15); // Increased from 10 to 15
$pdf->SetFooterMargin(10);

// Set auto page breaks
$pdf->SetAutoPageBreak(TRUE, 15);

// Set image scale factor
$pdf->setImageScale(1.5); // Increased scale factor for better image quality

// Add a page
$pdf->AddPage();

// Set font
$pdf->SetFont('dejavusans', '', 10);

// Patient Information - Layout matching the image with left and right columns
// Increased Y position to add more space from the header
$pdf->SetY(50);

// Left column - Patient information
$leftX = 15;
$pdf->SetX($leftX);

// Patient Name
$pdf->SetFont('dejavusans', 'B', 9);
$pdf->Cell(25, 5, 'Patient Name', 0, 0);
$pdf->SetFont('dejavusans', '', 9);
$pdf->Cell(5, 5, ':', 0, 0);
$pdf->Cell(65, 5, $report['patient_name'], 0, 1);

// Civil ID
$pdf->SetX($leftX);
$pdf->SetFont('dejavusans', 'B', 9);
$pdf->Cell(25, 5, 'Civil ID', 0, 0);
$pdf->SetFont('dejavusans', '', 9);
$pdf->Cell(5, 5, ':', 0, 0);
$pdf->Cell(65, 5, $report['civil_id'], 0, 1);

// Mobile
$pdf->SetX($leftX);
$pdf->SetFont('dejavusans', 'B', 9);
$pdf->Cell(25, 5, 'Mobile', 0, 0);
$pdf->SetFont('dejavusans', '', 9);
$pdf->Cell(5, 5, ':', 0, 0);
$pdf->Cell(65, 5, $report['mobile'], 0, 1);

// Removed 'Referred By' as requested

// Right column - File information
$rightX = 120;
$rightY = 40; // Same Y position as left column

// File No.
$pdf->SetXY($rightX, $rightY);
$pdf->SetFont('dejavusans', 'B', 9);
$pdf->Cell(25, 5, 'File No.', 0, 0);
$pdf->SetFont('dejavusans', '', 9);
$pdf->Cell(5, 5, ':', 0, 0);
$pdf->Cell(45, 5, $fileNumber, 0, 1);

// Removed 'Collected At' as requested

// Printed At - adjusted position since Collected At was removed
$pdf->SetXY($rightX, $rightY + 5);
$pdf->SetFont('dejavusans', 'B', 9);
$pdf->Cell(25, 5, 'Printed At', 0, 0);
$pdf->SetFont('dejavusans', '', 9);
$pdf->Cell(5, 5, ':', 0, 0);
$pdf->Cell(45, 5, $printedDate, 0, 1);

$pdf->Ln(10); // Increased space between patient info and test results table

// Test Results Header
$pdf->SetFont('dejavusans', 'B', 10);
$pdf->Ln(1);

// Test Results Table Header
$pdf->SetFont('dejavusans', 'B', 9);
$pdf->SetFillColor(245, 245, 245);
$pdf->Cell(80, 5, 'Test', 1, 0, 'L', 1);
$pdf->Cell(30, 5, 'Result', 1, 0, 'C', 1);
$pdf->Cell(30, 5, 'Unit', 1, 0, 'C', 1);
$pdf->Cell(40, 5, 'Ref. Range', 1, 1, 'C', 1);

// Test Results Table Content
$pdf->SetFont('dejavusans', '', 8);
if ($testsResult && $testsResult->num_rows > 0) {
    $testsResult->data_seek(0); // Reset pointer to beginning
    while ($test = $testsResult->fetch_assoc()) {
        $pdf->Cell(80, 5, $test['test_name'], 1, 0, 'L');
        
        // Display test value with flag in red if present
        if (!empty($test['flag'])) {
            $pdf->SetTextColor(255, 0, 0); // Set text color to red
            $pdf->Cell(30, 5, $test['test_value'] . ' ' . $test['flag'], 1, 0, 'C');
            $pdf->SetTextColor(0, 0, 0); // Reset text color to black
        } else {
            $pdf->Cell(30, 5, $test['test_value'], 1, 0, 'C');
        }
        
        $pdf->Cell(30, 5, $test['unit'], 1, 0, 'C');
        $pdf->Cell(40, 5, $test['normal_range'], 1, 1, 'C');
        
        // Display remarks if present - match the style from view_report.php
        if (!empty($test['remarks'])) {
            // Add remarks as a new row under the test
            $pdf->SetFont('dejavusans', '', 8);
            
            // Show remarks on its own line
            $pdf->Cell(80, 5, 'Remarks: ' . $test['remarks'], 1, 0, 'L');
            $pdf->Cell(100, 5, '', 1, 1); // Empty cells to complete the row
            
            $pdf->SetFont('dejavusans', '', 8); // Reset font
        }
    }
} else {
    $pdf->Cell(180, 7, 'No test results found', 1, 1, 'C');
}

$pdf->Ln(20);

// Doctor Signature - positioned at a fixed position from the top, moved lower as requested
$pdf->SetY(210); // Set at 210mm from top of page (moved lower)
$pdf->SetFont('dejavusans', '', 10);

// Ensure signature doesn't break to next page
$pdf->setPageMark();

// Doctor Signature - on left side
$pdf->SetXY(15, $pdf->GetY());

// Use text-based signature since server doesn't have Imagick or GD extension
// Match the style from view_report.php
$pdf->SetFont('dejavusans', 'I', 10);

// Draw a signature line
$pdf->Line(15, $pdf->GetY(), 85, $pdf->GetY());
$pdf->Ln(2);

// Add doctor's name without the "(signed)" text to match view_report.php
$pdf->SetFont('dejavusans', '', 10);
$pdf->Cell(60, 5, $report['doctor_name'], 0, 1, 'L');

// Add position below
$pdf->SetXY(15, $pdf->GetY());
$pdf->SetFont('dejavusans', '', 9);
$pdf->Cell(75, 6, $report['doctor_position'], 0, 1, 'L');

// Add page number at the bottom right
$pdf->SetY(-20);
$pdf->SetFont('dejavusans', '', 9);
$pdf->Cell(0, 10, 'Page 1 of 1', 0, 0, 'R');

// Sanitize patient name for filename
function sanitizeFilename($string) {
    // Replace non-alphanumeric characters with underscores
    $string = preg_replace('/[^\p{L}\p{N}_]/u', '_', $string);
    // Remove multiple underscores
    $string = preg_replace('/_+/', '_', $string);
    // Trim underscores from beginning and end
    $string = trim($string, '_');
    // If empty, use a default name
    if (empty($string)) {
        $string = 'Medical_Report';
    }
    return $string;
}

// Create filename with patient name and file number
$patientName = sanitizeFilename($report['patient_name']);
$filename = $patientName . '_' . $fileNumber . '.pdf';

// Output the PDF
$pdf->Output($filename, 'D');
?>
