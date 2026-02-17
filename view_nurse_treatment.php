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

// Check if token is provided
$token = isset($_GET['token']) ? $_GET['token'] : '';
$doc = isset($_GET['doc']) ? $_GET['doc'] : '';

if (empty($token) || empty($doc)) {
    header("Location: index.php");
    exit;
}

// Decode the encrypted document ID
$decoded = base64_decode($doc);
if ($decoded === false) {
    die('Access denied: Invalid document reference');
}

// Extract treatment ID and patient ID from decoded data
$parts = explode('_', $decoded);
if (count($parts) !== 2) {
    die('Access denied: Invalid document reference');
}

$treatmentId = (int)$parts[0];
$decodedPatientId = (int)$parts[1];

// Validate token and get patient ID
$tokenData = validateReportToken($token);

if (!$tokenData) {
    die('Access denied: Invalid or expired token');
}

// Get patient ID from validated token
$patientId = $tokenData['patient_id'];

// Verify the decoded patient ID matches the token's patient ID
if ($decodedPatientId !== $patientId) {
    die('Access denied: Document does not belong to this patient');
}

// Create database connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get treatment details and verify it belongs to the token's patient
$query = "SELECT nt.*, p.name AS patient_name, p.civil_id, p.mobile, p.file_number 
          FROM nurse_treatments nt
          JOIN patients p ON nt.patient_id = p.id
          WHERE nt.id = ? AND nt.patient_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $treatmentId, $patientId);
$stmt->execute();
$result = $stmt->get_result();

if (!$result || $result->num_rows == 0) {
    $_SESSION['error'] = "Treatment record not found.";
    header('Location: nurse_treatments.php');
    exit;
}

$treatment = $result->fetch_assoc();

// Get clinic info
$query = "SELECT * FROM clinic_info LIMIT 1";
$clinic_result = executeQuery($query);
$clinic = $clinic_result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="format-detection" content="telephone=no">
    <title>Nurse Treatment - <?php echo $treatment['patient_name']; ?></title>
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
                width: 90% !important;
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
            .table {
                width: 100% !important;
            }
            .table td, .table th {
                padding: 0.3rem !important;
            }
            .card {
                border: none !important;
            }
            .card-header {
                border-bottom: 1px solid #000 !important;
            }
            @page {
                size: auto;
                margin: 10mm; /* Add page margin */
                margin-bottom: 0 !important;
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
            
            /* Hide document title in print */
            title, head title {
                display: none !important;
            }
            
            /* Hide any browser-generated headers */
            @page {
                margin-top: 0.5cm;
                margin-header: 0;
                marks: none;
            }
        }
        
        /* Custom style for BATO logo */
        .bato-logo {
            max-height: 100px;
            margin-bottom: 10px;
            filter: invert(1) brightness(0);
            -webkit-filter: invert(1) brightness(0);
        }
        
        /* Nurse information styling for print */
        .nurse-name {
            color: blue !important;
            font-weight: bold !important;
        }
        
        .payment-status {
            display: inline-block;
            padding: 5px 15px;
            font-weight: bold;
            border: 2px solid;
            border-radius: 5px;
        }

        .payment-status.paid {
            color: #198754;
            border-color: #198754;
        }

        .payment-status.unpaid {
            color: #ffc107;
            border-color: #ffc107;
        }
    </style>
</head>
<body>
    <!-- Report Actions (no back button for patient view) -->
    <div class="container-fluid no-print">
        <div class="row mb-3">
            <div class="col-12">
                <button onclick="printReport()" class="btn btn-primary">
                    <i class="fas fa-print"></i> Print/Save as PDF
                </button>
                <h2 class="d-inline-block ms-3">Nurse Treatment Record</h2>
            </div>
        </div>
    </div>

    <!-- Report Content -->
    <div class="container report-container" style="margin-top: 50px;">
        <!-- Header with Logo and Clinic Info -->
        <div class="row">
            <div class="col-6 text-start">
                <!-- BATO Health/Beauty Logo - Using the exact same image as view_report.php -->
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

        <!-- Patient Information - Exact match with view_report.php -->
        <div class="row mb-2">
            <div class="col-md-6">
                <table class="table table-borderless table-sm mb-0">
                    <tr style="font-size: 0.9rem; line-height: 1.2;">
                        <th width="120" style="padding: 2px;">Patient Name</th>
                        <td style="padding: 2px;">: <?php echo $treatment['patient_name']; ?></td>
                    </tr>
                    <tr style="font-size: 0.9rem; line-height: 1.2;">
                        <th style="padding: 2px;">Civil ID</th>
                        <td style="padding: 2px;">: <?php echo $treatment['civil_id']; ?></td>
                    </tr>
                    <tr style="font-size: 0.9rem; line-height: 1.2;">
                        <th style="padding: 2px;">Mobile</th>
                        <td style="padding: 2px;">: <?php echo $treatment['mobile']; ?></td>
                    </tr>
                    <tr style="font-size: 0.9rem; line-height: 1.2;">
                        <th style="padding: 2px;">File No.</th>
                        <td style="padding: 2px;">: <?php echo $treatment['file_number']; ?></td>
                    </tr>
                </table>
            </div>
            <div class="col-md-6">
                <table class="table table-borderless table-sm mb-0">
                    <tr style="font-size: 0.9rem; line-height: 1.2;">
                        <th width="120" style="padding: 2px;">Treatment Date</th>
                        <td style="padding: 2px;">: <?php echo date('d/m/Y', strtotime($treatment['treatment_date'])); ?></td>
                    </tr>
                    <tr style="font-size: 0.9rem; line-height: 1.2;">
                        <th style="padding: 2px;">Nurse Name</th>
                        <td style="padding: 2px;">: <span class="nurse-name"><?php echo $treatment['nurse_name']; ?></span></td>
                    </tr>
                    <tr style="font-size: 0.9rem; line-height: 1.2;">
                        <th style="padding: 2px;">Payment</th>
                        <td style="padding: 2px;">: <span class="payment-status <?php echo $treatment['payment_status'] == 'Paid' ? 'paid' : 'unpaid'; ?>">
                            <?php echo $treatment['payment_status']; ?>
                        </span></td>
                    </tr>
                </table>
            </div>
        </div>
        
        <!-- Treatment Details -->
        <div class="row mb-4">
            <div class="col-12">
                <h5 class="border-bottom pb-2">REPORT</h5>
                <p><?php echo nl2br($treatment['report'] ?? ''); ?></p>
            </div>
        </div>
        
        <!-- Treatment Details -->
        <div class="row mb-4">
            <div class="col-12">
                <h5 class="border-bottom pb-2">TREATMENT</h5>
                <p><?php echo nl2br($treatment['treatment'] ?? ''); ?></p>
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
            // Store patient name for the suggested filename
            var patientName = "<?php echo preg_replace('/[^\p{L}\p{N}_]/u', '_', $treatment['patient_name']); ?>";
            var treatmentDate = "<?php echo date('Y-m-d', strtotime($treatment['treatment_date'])); ?>";
            var suggestedFilename = "Nurse_Treatment_" + patientName + "_" + treatmentDate;
            
            // Don't change the document title to avoid showing it in the print header
            // Just print the document directly
            window.print();
            
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
