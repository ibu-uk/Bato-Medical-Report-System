<?php
// Start session
session_start();

// Include timezone configuration
require_once 'config/timezone.php';

// Include database configuration
require_once 'config/data.php';

// Check if prescription ID is provided
if (!isset($_GET['id'])) {
    header("Location: prescriptions.php");
    exit;
}

$prescriptionId = sanitize($_GET['id']);

// Get prescription details
$prescriptionQuery = "SELECT p.*, pt.name AS patient_name, pt.civil_id, pt.mobile, pt.file_number,
                d.name as doctor_name, d.position as doctor_position, d.signature_image_path
                FROM prescriptions p
                JOIN patients pt ON p.patient_id = pt.id
                JOIN doctors d ON p.doctor_id = d.id
                WHERE p.id = '$prescriptionId'";

// Use direct database connection for reliability
global $conn;
if (!isset($conn) || !$conn) {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
}

$stmt = $conn->prepare($prescriptionQuery);
if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}

$stmt->execute();
$prescriptionResult = $stmt->get_result();

if (!$prescriptionResult || $prescriptionResult->num_rows === 0) {
    header("Location: prescriptions.php");
    exit;
}

$prescription = $prescriptionResult->fetch_assoc();
$stmt->close();

// Get medications from the prescription record
$medications = [];

// Parse medications from the prescription record
if (isset($prescription['medications']) && !empty($prescription['medications'])) {
    // Parse medications string
    $med_items = explode('||', $prescription['medications']);
    foreach ($med_items as $med_item) {
        $parts = explode('|', $med_item);
        if (count($parts) >= 2) {
            $medications[] = [
                'medicine_name' => $parts[0],
                'dose' => $parts[1]
            ];
        } elseif (count($parts) == 1 && !empty($parts[0])) {
            // Handle case where there's only a medicine name without dose
            $medications[] = [
                'medicine_name' => $parts[0],
                'dose' => ''
            ];
        }
    }
}

// Get clinic info
$clinicQuery = "SELECT * FROM clinic_info LIMIT 1";
$clinicResult = executeQuery($clinicQuery);
$clinic = $clinicResult->fetch_assoc();

// Use patient's file number or generate one if not available
$fileNumber = !empty($prescription['file_number']) ? $prescription['file_number'] : 'P-' . str_pad($prescriptionId, 4, '0', STR_PAD_LEFT);

// Format date
$prescriptionDate = date('d/m/Y', strtotime($prescription['prescription_date']));
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
        $string = 'Prescription';
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
    <title>Prescription - <?php echo $prescription['patient_name']; ?></title>
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
                size: auto;   /* auto is the default anyway */
                margin: 0mm;  /* removes default margin */
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
            display: none !important; /* Hide doctor information */
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
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="reports.php">Reports</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="prescriptions.php">Prescriptions</a>
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
                    <i class="fas fa-print"></i> Print/Save as PDF
                </button>
                <a href="prescriptions.php" class="btn btn-secondary">
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
                        <td style="padding: 2px;">: <?php echo $prescription['patient_name']; ?></td>
                    </tr>
                    <tr style="font-size: 0.9rem; line-height: 1.2;">
                        <th style="padding: 2px;">Civil ID</th>
                        <td style="padding: 2px;">: <?php echo $prescription['civil_id']; ?></td>
                    </tr>
                </table>
            </div>
            <div class="col-md-6">
                <table class="table table-borderless table-sm mb-0">
                    <tr style="font-size: 0.9rem; line-height: 1.2;">
                        <th width="120" style="padding: 2px;">Doctor Name</th>
                        <td style="padding: 2px;">: <?php echo $prescription['doctor_name']; ?></td>
                    </tr>
                    <tr style="font-size: 0.9rem; line-height: 1.2;">
                        <th style="padding: 2px;">Date</th>
                        <td style="padding: 2px;">: <?php echo $prescriptionDate; ?></td>
                    </tr>
                    <tr style="font-size: 0.9rem; line-height: 1.2;">
                        <th style="padding: 2px;">Printed At</th>
                        <td style="padding: 2px;">: <?php echo $printedDate; ?></td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- Added space between patient info and prescription title -->
        <div style="margin-top: 20px;"></div>
        
        <!-- Prescription Title -->
        <h3 class="text-center mb-4">MEDICATION/PRESCRIPTION CARD</h3>
        
        <!-- Medications Table -->
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Medicine/Product</th>
                    <th>Dose</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($medications)): ?>
                    <?php foreach ($medications as $medication): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($medication['medicine_name']); ?></td>
                            <td><?php echo htmlspecialchars($medication['dose']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php elseif (!empty($prescription['consultation_report'])): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($prescription['consultation_report']); ?></td>
                        <td>As directed</td>
                    </tr>
                <?php else: ?>
                    <tr>
                        <td colspan="2" class="text-center">No medications added</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
        
        <!-- Consultation Report -->
        <div class="row mb-4" style="margin-top: 25px;">
            <div class="col-12">
                <h5 class="border-bottom pb-2">CONSULTATION REPORT</h5>
                <p><?php echo nl2br($prescription['consultation_report'] ?? ''); ?></p>
            </div>
        </div>
        <!-- Footer with Doctor Signature - adjusted position (hidden as requested) -->
        <div class="row" style="margin-top: 100px; page-break-inside: avoid !important; break-inside: avoid !important;">
            <div class="col-md-6">
                <!-- Doctor signature and information hidden as requested -->
                <div style="display: none;">
                    <?php if (!empty($prescription['signature_image_path'])): ?>
                    <img src="<?php echo $prescription['signature_image_path']; ?>" alt="Doctor Signature" class="doctor-signature" style="max-height: 80px;">
                    <?php endif; ?>
                    <p class="mt-2 doctor-name" style="margin-bottom: 0;"><?php echo $prescription['doctor_name']; ?></p>
                    <p class="doctor-position" style="margin-top: 0;"><?php echo $prescription['doctor_position']; ?></p>
                </div>
            </div>
            <div class="col-md-6">
                <!-- Empty space for clinic stamp if needed -->
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
            var patientName = "<?php echo sanitizeFilename($prescription['patient_name']); ?>";
            var fileNumber = "<?php echo $fileNumber; ?>";
            var suggestedFilename = "Prescription_" + patientName + "_" + fileNumber;
            
            // Set the document title to the suggested filename before printing
            // This will be suggested as the filename when saving as PDF
            var originalTitle = document.title;
            document.title = suggestedFilename;
            
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
