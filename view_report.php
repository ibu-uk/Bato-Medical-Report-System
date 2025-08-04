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

// Log the report view activity
logUserActivity('view_report', $reportId);

// Get test results
$testsQuery = "SELECT rt.test_value, rt.flag, rt.remarks, tt.name as test_name, tt.unit, tt.normal_range
               FROM report_tests rt
               JOIN test_types tt ON rt.test_type_id = tt.id
               WHERE rt.report_id = '$reportId'";
$testsResult = executeQuery($testsQuery);

// Count number of tests for dynamic print page break
$testCount = 0;
if ($testsResult && $testsResult->num_rows > 0) {
    $testCount = $testsResult->num_rows;
    // Rewind result pointer for later fetch
    $testsResult->data_seek(0);
}


// Get clinic info
$clinicQuery = "SELECT * FROM clinic_info LIMIT 1";
$clinicResult = executeQuery($clinicQuery);
$clinic = $clinicResult->fetch_assoc();

// Use patient's file number or generate one if not available
$fileNumber = !empty($report['file_number']) ? $report['file_number'] : 'N-' . str_pad($reportId, 4, '0', STR_PAD_LEFT);

// Format date
$visitDate = date('d/m/Y', strtotime($report['report_date']));
$printedDate = date('d/m/Y H:i A');

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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="format-detection" content="telephone=no">
    <title>Medical Report - <?php echo $report['patient_name']; ?></title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        @media print {
            .no-print {
                display: none !important;
            }
            body {
                padding: 0;
                margin: 0;
            }
            .report-container {
                width: 90% !important; /* Reduced from 100% to 90% */
                max-width: 90% !important;
                margin: 25px auto 0 !important; /* Center the content and add top margin */
                padding: 30px 40px !important; /* Increased padding to move content inward */

            }
            /* Ensure layout is preserved when printing */
            .row {
                display: flex !important;
                flex-wrap: wrap !important;
            }
            .col-6, .col-md-6 {
                width: 50% !important;
                flex: 0 0 50% !important;
                max-width: 50% !important;
            }
            /* Preserve text alignment */
            .text-start {
                text-align: left !important;
            }
            .text-end {
                text-align: right !important;
            }
            
            /* Hide URL and other browser-generated content when printing */
            @page {
                size: auto;
                margin-top: 25mm;
                margin-right: 10mm;
                margin-bottom: 15mm;
                margin-left: 10mm;
            }
            @page :header {
                display: none !important;
                visibility: hidden !important;
            }
            @page :footer {
                display: none !important;
                visibility: hidden !important;
            }
            /* Hide page numbers - comprehensive approach */
            html {
                counter-reset: page !important;
            }
            /* Target all possible page number elements across browsers */
            .pagenumber, .pagecount, #pageFooter, .page-number, .page-count,
            #footer, .footer, footer, #header, .header, header,
            .page, #page, [class*='page-number'], [id*='page-number'],
            [class*='pageNumber'], [id*='pageNumber'] {
                display: none !important;
                visibility: hidden !important;
                opacity: 0 !important;
                height: 0 !important;
                max-height: 0 !important;
                min-height: 0 !important;
                position: absolute !important;
                top: -9999px !important;
                left: -9999px !important;
                z-index: -9999 !important;
            }
            /* Override browser defaults */
            body::after, body::before {
                display: none !important;
                content: "" !important;
            }
        }
        
        /* Custom style for BATO logo */
        .bato-logo {
            max-height: 100px;
            margin-bottom: 10px;
            filter: invert(1) brightness(0);
            -webkit-filter: invert(1) brightness(0);
        }
        
        /* Doctor information styling for print */
        .doctor-name, .doctor-position, .doctor-signature {
            color: blue !important;
            font-weight: bold !important;
            /* display: none !important;  Hide doctor information */
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary no-print">
        <div class="container">
            <a class="navbar-brand" href="index.php">Bato Medical Report System</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="reports.php">Reports</a>
                    </li>
                    <?php if (hasRole(['admin'])): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="manage_doctors.php">Doctors</a>
                    </li>
                    <?php endif; ?>
                </ul>
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-user-circle"></i> <?php echo isset($_SESSION['full_name']) ? htmlspecialchars($_SESSION['full_name']) : ''; ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                            <li><a class="dropdown-item" href="profile.php"><i class="fas fa-id-card"></i> Profile</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Report Actions -->
    <div class="container my-4 no-print">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Medical Report</h2>
            <div>
                <button onclick="printReport()" class="btn btn-primary">
                    <i class="fas fa-print"></i> Print Report
                </button>
                <?php 
                // Log print activity when button is clicked
                if (isset($_GET['print']) && $_GET['print'] == '1') {
                    logUserActivity('print_report', $reportId);
                }
                ?>
                <a href="reports.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            </div>
        </div>
    </div>

    <!-- Report Content -->
    <div class="container report-container" style="margin-top: 25px;">
        <!-- Header with Logo and Clinic Info -->
        <div class="row">
            <div class="col-6 text-start">
                <!-- BATO Health/Beauty Logo - Using the actual image with custom CSS class -->
                <img src="assets/images/IMG_4554.PNG" alt="BATO Health/Beauty" class="bato-logo">
            </div>
            <div class="col-6 text-end">
                <div class="clinic-info">
                    <p style="margin-bottom: 5px; font-size: 12px;">BATO CLINIC</p>
                    <p style="margin-bottom: 0; font-size: 12px;">Phone: <?php echo $clinic['phone']; ?></p>
                    <p style="margin-bottom: 0; font-size: 12px;">Email: <?php echo $clinic['email']; ?></p>
                    <p style="margin-bottom: 0; font-size: 12px;">Website: <?php echo $clinic['website']; ?></p>
                    <p style="margin-bottom: 0; font-size: 12px;"><?php echo $clinic['address']; ?></p>
                </div>
            </div>
        </div>
        <hr style="margin-top: 0; margin-bottom: 15px;">

        <!-- Patient Information - More Compact -->
        <div class="row mb-2">
            <div class="col-md-6">
                <table class="table table-borderless table-sm mb-0">
                    <tr style="font-size: 0.9rem; line-height: 1.2;">
                        <th width="120" style="padding: 2px;">Patient Name</th>
                        <td style="padding: 2px;">: <?php echo $report['patient_name']; ?></td>
                    </tr>
                    <tr style="font-size: 0.9rem; line-height: 1.2;">
                        <th style="padding: 2px;">Civil ID</th>
                        <td style="padding: 2px;">: <?php echo $report['civil_id']; ?></td>
                    </tr>
                    <tr style="font-size: 0.9rem; line-height: 1.2;">
                        <th style="padding: 2px;">Mobile</th>
                        <td style="padding: 2px;">: <?php echo $report['mobile']; ?></td>
                    </tr>
                    <!-- Removed Referred By field as requested -->
                </table>
            </div>
            <div class="col-md-6">
                <table class="table table-borderless table-sm mb-0">
                    <tr style="font-size: 0.9rem; line-height: 1.2;">
                        <th width="120" style="padding: 2px;">File No.</th>
                        <td style="padding: 2px;">: <?php echo $fileNumber; ?></td>
                    </tr>
                    <tr style="font-size: 0.9rem; line-height: 1.2;">
                        <th style="padding: 2px;">Printed At</th>
                        <td style="padding: 2px;">: <?php echo $printedDate; ?></td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- Added space between patient info and test results -->
        <div style="margin-top: 20px;"></div>

        <!-- Medical Report Title -->
        <h3 class="text-center mb-4">MEDICAL REPORT</h3>

        <!-- Test Results -->
        <div class="test-category mb-2">
            <div>
                <div class="table-responsive">
                    <table class="table table-bordered table-sm" style="background: #fff; table-layout: fixed;">
<style>
@media print {
    body, .report-container {
        font-size: 12px !important;
    }
    h3, .text-center.mb-4 {
        font-size: 1.1rem !important;
        margin-bottom: 0.6rem !important;
    }
    .table th, .table td {
        padding: 2px 4px !important;
        font-size: 12px !important;
        line-height: 1.1 !important;
    }
    .table thead th {
        font-size: 11.5px !important;
        padding-top: 3px !important;
        padding-bottom: 3px !important;
    }
    .report-test-name {
        white-space: normal;
        overflow: visible;
        text-overflow: initial;
        max-width: none;
        font-size: 12px !important;
        word-break: break-word;
    }
    .report-unit, .report-range {
        width: 70px !important;
        font-size: 12px !important;
    }
    .alert-secondary {
        font-size: 12px !important;
        padding: 8px 10px !important;
        margin-top: 8px !important;
        margin-bottom: 0 !important;
    }
    .doctor-signature {
        max-height: 60px !important;
        margin-top: 10px !important;
    }
    .row, .col-md-6, .col-6 {
        margin-bottom: 2px !important;
    }
    .clinic-info p {
        margin-bottom: 1px !important;
        font-size: 10px !important;
    }
    .table {
        margin-bottom: 0.5rem !important;
    }
    .mb-4, .mb-2, .mb-0 {
        margin-bottom: 0.3rem !important;
    }
    .mt-4 {
        margin-top: 0.4rem !important;
    }
    .report-container {
        padding: 10px 14px !important;
        margin-top: 10px !important;
    }
}

.report-test-name {
    white-space: normal;
    overflow: visible;
    text-overflow: initial;
    max-width: none;
    font-size: 0.95rem;
    word-break: break-word;
}
.report-unit, .report-range {
    width: 80px;
    font-size: 0.95rem;
}
</style>
                        <thead>
                            <tr style="font-size: 0.9rem;">
                                <th style="width:40%">Test</th>
                                <th style="width:15%">Result</th>
                                <th class="report-unit">Unit</th>
                                <th class="report-range">Ref. Range</th>
                            </tr>
                        </thead>
                        <tbody style="font-size: 0.85rem;">
                            <?php
                            if ($testsResult && $testsResult->num_rows > 0) {
                                while ($test = $testsResult->fetch_assoc()) {
                                    echo "<tr>";
                                    echo "<td class='report-test-name' style='padding: 3px 5px;'>{$test['test_name']}</td>";
                                    
                                    // Display test value with flag color: green for NORMAL, red for HIGH/LOW, default otherwise
                                    if (!empty($test['flag'])) {
                                        $flag = strtoupper($test['flag']);
                                        $flagColor = ($flag === 'NORMAL') ? 'green' : (($flag === 'HIGH' || $flag === 'LOW') ? 'red' : '');
                                        $flagStyle = $flagColor ? "color: $flagColor; font-weight: bold;" : '';
                                        echo "<td style='padding: 3px 5px;'>{$test['test_value']} <span style='$flagStyle'>{$test['flag']}</span></td>";
                                    } else {
                                        echo "<td style='padding: 3px 5px;'>{$test['test_value']}</td>";
                                    }
                                    
                                    echo "<td style='padding: 3px 5px;'>{$test['unit']}</td>";
                                    echo "<td style='padding: 3px 5px;'>{$test['normal_range']}</td>";
                                    echo "</tr>";
                                    
                                    // Display remarks if present but more compact
                                    if (!empty($test['remarks'])) {
                                        echo "<tr>";
                                        echo "<td colspan='4' style='padding: 2px 5px;'><small><strong>Remarks:</strong> ";
                                        
                                        // Combine remarks into a single line with bullet points
                                        $remarks = explode("\n", $test['remarks']);
                                        $formattedRemarks = [];
                                        foreach ($remarks as $remark) {
                                            if (trim($remark) !== '') {
                                                $formattedRemarks[] = "• " . trim($remark);
                                            }
                                        }
                                        echo implode(" | ", $formattedRemarks);
                                        echo "</small></td>";
                                        echo "</tr>";
                                    }
                                }
                            } else {
                                echo "<tr><td colspan='4' class='text-center'>No test results found</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
            <!-- 50px space after test table -->
<div style="height:20px;"></div>
<!-- Conclusion and Signature: keep together on print -->
<div style="page-break-inside: avoid;">
    <?php if (!empty($report['conclusion'])): ?>
    <div class="alert alert-secondary mt-4" style="border: 2px solid #888; background: #f9f9f9;">
        <strong>Conclusion:</strong><br>
        <span style="white-space: pre-line;">
            <?php echo nl2br(htmlspecialchars($report['conclusion'])); ?>
        </span>
    </div>
    <?php endif; ?>

    <!-- Signature block directly after the table, always at the end, no extra line or border -->
    <div style="margin-top: 24px;">
        <?php if (!empty($report['signature_image_path'])): ?>
        <img src="<?php echo $report['signature_image_path']; ?>" alt="Doctor Signature" class="doctor-signature" style="max-height: 80px;">
        <?php endif; ?>
        <!-- Doctor name and position hidden as requested
        <p class="mt-2 doctor-name" style="margin-bottom: 0;">
            <?php echo $report['doctor_name']; ?>
        </p>
        <p class="doctor-position" style="margin-top: 0;">
            <?php echo $report['doctor_position']; ?>
        </p>
        -->
    </div>
</div>
            </div>
        <!-- Page Number - hidden as requested -->
        <div class="text-end mt-4" style="display: none;">
            <p>Page 1 of 1</p>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Script to handle print/save as PDF with custom filename -->
    <script>
        // Custom print function to suggest filename when saving as PDF
        function printReport() {
            // Store patient name and file number for the suggested filename
            var patientName = "<?php echo sanitizeFilename($report['patient_name']); ?>";
            var fileNumber = "<?php echo $fileNumber; ?>";
            var suggestedFilename = patientName + "_" + fileNumber;
            
            // Set the document title to the suggested filename before printing
            // This will be suggested as the filename when saving as PDF
            var originalTitle = document.title;
            document.title = suggestedFilename;
            
            // Log the print activity
            fetch('log_activity.php?type=print_report&id=<?php echo $reportId; ?>', {
                method: 'GET',
                credentials: 'same-origin'
            });
            
            // Print the document
            window.print();
            
            // Restore the original title
            setTimeout(function() {
                document.title = originalTitle;
            }, 100);
            
            return true;
        }
        
        // Initialize when the document is loaded
        document.addEventListener('DOMContentLoaded', function() {
            // Make sure all print buttons use our custom function
            var printButtons = document.querySelectorAll('button[onclick="window.print()"]');
            printButtons.forEach(function(button) {
                button.setAttribute('onclick', 'printReport(); return false;');
            });
        });
    </script>
</body>
</html>
