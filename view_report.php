<?php
// Start session
session_start();

// Include timezone configuration
require_once 'config/timezone.php';

// Include database configuration
require_once 'config/database.php';

// Include authentication helpers
require_once 'config/auth.php';

// Include secure links functions
require_once 'config/secure_links.php';

// Check if token or ID is provided
$token = isset($_GET['token']) ? $_GET['token'] : '';
$doc = isset($_GET['doc']) ? $_GET['doc'] : '';
$reportId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Check if user is logged in as staff (admin/doctor/nurse/receptionist)
$isStaff = isset($_SESSION['user_id']) && hasRole(['admin', 'doctor', 'nurse', 'receptionist']);

// This will hold the patient ID when access is via token (patients)
$patientId = null;

if ($isStaff && $reportId > 0) {
    // Staff access: open directly by report ID, no token/doc required
    // $patientId stays null; we won't restrict by patient in the query
} elseif (!empty($token) && !empty($doc)) {
    // Patient access: validate token and doc
    $tokenData = validateReportToken($token);
    if (!$tokenData) {
        die('Access denied: Invalid or expired token');
    }

    // Decode the encrypted document ID
    $decoded = base64_decode($doc);
    if ($decoded === false) {
        die('Access denied: Invalid document reference');
    }

    // Extract report ID and patient ID from decoded data
    $parts = explode('_', $decoded);
    if (count($parts) !== 2) {
        die('Access denied: Invalid document reference');
    }

    $reportId = (int)$parts[0];
    $decodedPatientId = (int)$parts[1];

    // Get patient ID from validated token
    $patientId = (int)$tokenData['patient_id'];

    // Verify the decoded patient ID matches the token's patient ID
    if ($decodedPatientId !== $patientId) {
        die('Access denied: Document does not belong to this patient');
    }
} else {
    // No valid staff ID or token+doc provided
    header("Location: index.php");
    exit;
}

// Create database connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get report details
// For staff: load by report ID only
// For patients: ensure report belongs to the token's patient
if ($isStaff && $reportId > 0) {
    $reportQuery = "SELECT r.*, p.name as patient_name, p.civil_id, p.mobile, p.file_number,
                    d.name as doctor_name, d.position as doctor_position, d.signature_image_path
                    FROM reports r
                    JOIN patients p ON r.patient_id = p.id
                    JOIN doctors d ON r.doctor_id = d.id
                    WHERE r.id = ?";
    $stmt = $conn->prepare($reportQuery);
    $stmt->bind_param("i", $reportId);
} else {
    $reportQuery = "SELECT r.*, p.name as patient_name, p.civil_id, p.mobile, p.file_number,
                    d.name as doctor_name, d.position as doctor_position, d.signature_image_path
                    FROM reports r
                    JOIN patients p ON r.patient_id = p.id
                    JOIN doctors d ON r.doctor_id = d.id
                    WHERE r.id = ? AND r.patient_id = ?";
    $stmt = $conn->prepare($reportQuery);
    $stmt->bind_param("ii", $reportId, $patientId);
}
$stmt->execute();
$reportResult = $stmt->get_result();

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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
            padding: 20px;
        }
        .report-container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            margin-top: 20px;
        }
        .btn-back {
            margin-right: 10px;
        }
        .no-print {
            margin-bottom: 20px;
        }
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
    <!-- Report Actions -->
    <div class="container-fluid no-print">
        <div class="row mb-3">
            <div class="col-12">
                <?php if ($isStaff && empty($token)): ?>
                    <a href="reports.php" class="btn btn-secondary me-2">
                        <i class="fas fa-arrow-left"></i> Back to Reports
                    </a>
                <?php endif; ?>

                <button onclick="printReport()" class="btn btn-primary">
                    <i class="fas fa-print"></i> Save as PDF
                </button>
                <?php 
                // Log print activity when button is clicked
                if (isset($_GET['print']) && $_GET['print'] == '1') {
                    logUserActivity('print_report', $reportId);
                }
                ?>
                <h2 class="d-inline-block ms-3">Medical Report</h2>
            </div>
        </div>
    </div>

    <!-- Report Content -->
    <div class="container report-container" style="margin-top: 25px;">
        <!-- Header with Logo and Clinic Info -->
        <div class="row">
            <div class="col-6 text-start">
                <!-- Logo on the left side -->
                <img src="assets/images/IMG_4554.PNG" alt="BATO Health/Beauty" class="bato-logo mb-1">
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
                        <th style="padding: 2px;">Report Date</th>
                        <td style="padding: 2px;">: <?php echo $visitDate; ?></td>
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
    
    <!-- Script to handle print/save as PDF and secure link generation -->
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
            
            // Log print activity
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
        
        // Generate secure link function
        function generateSecureLink() {
            var reportId = <?php echo $reportId; ?>;
            var patientId = <?php echo $report['patient_id']; ?>;
            
            console.log('Generating secure link...', {reportId, patientId});
            
            // Show loading state
            var btn = event.target;
            var originalText = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Generating...';
            btn.disabled = true;
            
            // Send request to generate secure link
            fetch('generate_secure_link_clean.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'report_id=' + reportId + '&patient_id=' + patientId
            })
            .then(response => {
                console.log('Response received:', response);
                return response.json();
            })
            .then(data => {
                console.log('Data received:', data);
                if (data.success) {
                    // Show success modal with link
                    showSecureLinkModal(data.url, data.token, data.expiry);
                    logUserActivity('generate_secure_link', reportId);
                } else {
                    // Show detailed error information
                    let errorMsg = data.message;
                    if (data.debug_info) {
                        errorMsg += '\n\nDebug Info:\n';
                        errorMsg += 'Patient ID: ' + data.debug_info.patient_id + '\n';
                        errorMsg += 'Report ID: ' + data.debug_info.report_id + '\n';
                        errorMsg += 'Report File: ' + data.debug_info.attempted_file + '\n';
                        errorMsg += 'File Exists: ' + (data.debug_info.file_exists ? 'Yes' : 'No');
                    }
                    console.error('Error details:', data);
                    alert('Error: ' + errorMsg);
                }
            })
            .catch(error => {
                console.error('Fetch error:', error);
                alert('Error generating secure link: ' + error.message);
            })
            .finally(() => {
                // Restore button state
                btn.innerHTML = originalText;
                btn.disabled = false;
            });
        }
        
        // Show secure link modal
        function showSecureLinkModal(url, token, expiry) {
            // Remove any existing modal
            var existingModal = document.getElementById('secureLinkModal');
            if (existingModal) {
                existingModal.remove();
            }
            
            // Create modal HTML
            var modalHtml = `
                <div class="modal fade show" id="secureLinkModal" style="display: block; background: rgba(0,0,0,0.5);">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">
                                    <i class="fas fa-shield-alt me-2"></i>
                                    Patient Secure Link Generated
                                </h5>
                                <button type="button" class="btn-close" onclick="closeSecureLinkModal()"></button>
                            </div>
                            <div class="modal-body">
                                <div class="alert alert-success">
                                    <i class="fas fa-check-circle me-2"></i>
                                    <strong>Success!</strong> Patient dashboard link has been generated.
                                </div>
                                
                                <p><strong>Patient can access:</strong></p>
                                <ul>
                                    <li>All their medical reports (old and new)</li>
                                    <li>Complete report history</li>
                                    <li>Individual report details</li>
                                    <li>Print any report</li>
                                </ul>
                                
                                <div class="mb-3">
                                    <label class="form-label"><strong>Secure URL:</strong></label>
                                    <div class="input-group mb-3">
                                    <input type="text" class="form-control" value="${url}" readonly id="secureUrlInput">
                                    <button class="btn btn-outline-primary" type="button" onclick="copySecureLink(this)">
                                            <i class="fas fa-copy"></i> Copy
                                        </button>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label"><strong>Access Token:</strong></label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" value="${token}" readonly id="secureTokenInput">
                                        <button class="btn btn-outline-secondary" type="button" onclick="copyToken(this)">
                                            <i class="fas fa-copy"></i> Copy
                                        </button>
                                    </div>
                                </div>
                                
                                <p><strong>Expires:</strong> ${expiry}</p>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle"></i> 
                                    This link will never expire and gives access to all patient reports
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-primary" onclick="testSecureLink('${url}')">
                                    <i class="fas fa-external-link-alt me-1"></i>
                                    Test Link
                                </button>
                                <button type="button" class="btn btn-secondary" onclick="closeSecureLinkModal()">
                                    <i class="fas fa-times me-1"></i>
                                    Close
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            // Add modal to page
            document.body.insertAdjacentHTML('beforeend', modalHtml);
            
            // Initialize Bootstrap modal
            var modal = new bootstrap.Modal(document.getElementById('secureLinkModal'));
            modal.show();
        }
        
        // Helper to show temporary "Copied" state on a button
        function showCopiedState(button) {
            if (!button) return;
            var originalText = button.innerHTML;
            button.innerHTML = '<i class="fas fa-check"></i> Copied!';
            setTimeout(function() {
                button.innerHTML = originalText;
            }, 2000);
        }

        // Copy secure link function
        function copySecureLink(button) {
            var urlInput = document.getElementById('secureUrlInput');
            if (!urlInput) return;

            // Prefer modern Clipboard API when available
            if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
                navigator.clipboard.writeText(urlInput.value)
                    .then(function() {
                        showCopiedState(button);
                    })
                    .catch(function() {
                        // Fallback if writeText fails
                        urlInput.select();
                        document.execCommand('copy');
                        showCopiedState(button);
                    });
            } else {
                // Fallback for browsers/environments without navigator.clipboard
                urlInput.select();
                document.execCommand('copy');
                showCopiedState(button);
            }
        }
        
        // Copy token function
        function copyToken(button) {
            var tokenInput = document.getElementById('secureTokenInput');
            if (!tokenInput) return;

            if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
                navigator.clipboard.writeText(tokenInput.value)
                    .then(function() {
                        showCopiedState(button);
                    })
                    .catch(function() {
                        tokenInput.select();
                        document.execCommand('copy');
                        showCopiedState(button);
                    });
            } else {
                tokenInput.select();
                document.execCommand('copy');
                showCopiedState(button);
            }
        }
        
        // Test secure link function
        function testSecureLink(url) {
            window.open(url, '_blank');
        }
        
        // Close modal function
        function closeSecureLinkModal() {
            var modal = document.getElementById('secureLinkModal');
            if (modal) {
                modal.remove();
            }
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
