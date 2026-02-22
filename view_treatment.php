<?php
// Start session
session_start();

// Include timezone configuration
require_once 'config/timezone.php';

// Include database configuration
require_once 'config/database.php';

// Include secure links functions
require_once 'config/secure_links.php';

// Check if token or ID is provided
$token = isset($_GET['token']) ? $_GET['token'] : '';
$doc = isset($_GET['doc']) ? $_GET['doc'] : '';
$treatmentId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Check if user is logged in as staff (admin/doctor/nurse/receptionist)
require_once 'config/auth.php';
$isStaff = isset($_SESSION['user_id']) && hasRole(['admin', 'doctor', 'nurse', 'receptionist']);

// This will hold the patient ID when access is via token (patients)
$patientId = null;

if ($isStaff && $treatmentId > 0) {
    // Staff access: open directly by treatment ID, no token/doc required
} elseif (!empty($token) && !empty($doc)) {
    // Patient access via secure link: validate token and doc
    $tokenData = validateReportToken($token);
    if (!$tokenData) {
        die('Access denied: Invalid or expired token');
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

    // Get patient ID from validated token
    $patientId = (int)$tokenData['patient_id'];

    // Verify the decoded patient ID matches the token's patient ID
    if ($decodedPatientId !== $patientId) {
        die('Access denied: Document does not belong to this patient');
    }
} else {
    // No valid staff ID or token+doc provided
    header('Location: index.php');
    exit;
}

// Verify the treatment belongs to the patient (for patients) or just load by ID (for staff)
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get treatment details with all necessary fields (using nurse_treatments table)
// Note: patients table stores name/mobile; we alias into the fields we need
if ($isStaff && $treatmentId > 0) {
    $stmt = $conn->prepare("SELECT 
        nt.*, 
        p.name AS patient_name,
        p.name AS first_name,
        '' AS last_name,
        NULL AS date_of_birth,
        NULL AS gender,
        p.mobile AS phone,
        NULL AS email,
        NULL AS address,
        NULL AS blood_group,
        NULL AS allergies
    FROM nurse_treatments nt 
    JOIN patients p ON nt.patient_id = p.id 
    WHERE nt.id = ?");
    if (!$stmt) {
        die('Database error: ' . $conn->error);
    }
    $stmt->bind_param('i', $treatmentId);
} else {
    $stmt = $conn->prepare("SELECT 
        nt.*, 
        p.name AS patient_name,
        p.name AS first_name,
        '' AS last_name,
        NULL AS date_of_birth,
        NULL AS gender,
        p.mobile AS phone,
        NULL AS email,
        NULL AS address,
        NULL AS blood_group,
        NULL AS allergies
    FROM nurse_treatments nt 
    JOIN patients p ON nt.patient_id = p.id 
    WHERE nt.id = ? AND nt.patient_id = ?");
    if (!$stmt) {
        die('Database error: ' . $conn->error);
    }
    $stmt->bind_param('ii', $treatmentId, $patientId);
}
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die('Treatment not found or access denied');
}

$treatment = $result->fetch_assoc();
$conn->close();

// Get clinic info for header
$clinicQuery = "SELECT * FROM clinic_info LIMIT 1";
$clinicResult = executeQuery($clinicQuery);
$clinic = $clinicResult ? $clinicResult->fetch_assoc() : [
    'phone' => '',
    'email' => '',
    'website' => '',
    'address' => ''
];

// Function to sanitize patient name for filename
function sanitizeFilename($string) {
    // Replace spaces with underscores
    $string = str_replace(' ', '_', $string);
    // Remove anything which isn't a word, whitespace, or a hyphen
    $string = preg_replace("/[^\w\s-]/", '', $string);
    // Convert to lowercase
    $string = strtolower($string);
    // Trim whitespace
    $string = trim($string);
    return $string;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nurse Treatment - Bato Medical Report System</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        @media print {
            .no-print {
                display: none !important;
            }
            body, .report-container {
                font-size: 12px !important;
            }
            h3, .text-center.mb-4 {
                margin-top: 0 !important;
                margin-bottom: 5px !important;
            }
            .signature-line {
                border-top: 1px solid #000;
                width: 200px;
                display: inline-block;
                margin-top: 50px;
            }
            .signature-name {
                margin-top: 5px;
                font-weight: bold;
            }
            .signature-title {
                font-style: italic;
                font-size: 0.9em;
            }
        }
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .report-container {
            background-color: white;
            padding: 30px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            margin: 20px auto;
            max-width: 8.5in;
        }
        /* BATO logo styling (match view_report.php so logo is dark/bold) */
        .bato-logo {
            max-height: 100px;
            width: auto;
            margin-bottom: 10px;
            filter: invert(1) brightness(0);
            -webkit-filter: invert(1) brightness(0);
        }
        .patient-info {
            margin: 20px 0;
            padding: 15px;
            background-color: #f8f9fa;
            border-radius: 5px;
        }
        .treatment-details {
            margin: 20px 0;
        }
        .treatment-item {
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
        .treatment-item:last-child {
            border-bottom: none;
        }
        .signature-area {
            margin-top: 50px;
            text-align: right;
        }
        .signature-line {
            border-top: 1px solid #000;
            width: 200px;
            display: inline-block;
            margin-top: 50px;
        }
    </style>
</head>
<body>
    <!-- Treatment Actions -->
    <div class="container-fluid no-print">
        <div class="row mb-3">
            <div class="col-12">
                <?php if ($isStaff && empty($token)): ?>
                    <a href="nurse_treatments.php" class="btn btn-secondary me-2">
                        <i class="bi bi-arrow-left"></i> Back to Treatments
                    </a>
                <?php endif; ?>

                <button onclick="printTreatment()" class="btn btn-primary btn-print">
                    <i class="bi bi-printer"></i> Print / Save as PDF
                </button>
            </div>
        </div>
    </div>

    <div class="container report-container">
        <!-- Header with Logo and Clinic Info -->
        <div class="row">
            <div class="col-6 text-start">
                <!-- BATO Health/Beauty Logo - match style with view_report/view_prescription -->
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

        <!-- Patient & Treatment Information (similar to view_prescription.php) -->
        <div class="row mb-2">
            <div class="col-md-6">
                <table class="table table-borderless table-sm mb-0">
                    <tr style="font-size: 0.9rem; line-height: 1.2;">
                        <th width="120" style="padding: 2px;">Patient Name</th>
                        <td style="padding: 2px;">: <?php echo htmlspecialchars($treatment['patient_name'] ?? $treatment['first_name']); ?></td>
                    </tr>
                    <tr style="font-size: 0.9rem; line-height: 1.2;">
                        <th style="padding: 2px;">Civil ID</th>
                        <td style="padding: 2px;">: <?php echo htmlspecialchars($treatment['civil_id'] ?? ''); ?></td>
                    </tr>
                    <tr style="font-size: 0.9rem; line-height: 1.2;">
                        <th style="padding: 2px;">Mobile</th>
                        <td style="padding: 2px;">: <?php echo htmlspecialchars($treatment['phone'] ?? ''); ?></td>
                    </tr>
                    <tr style="font-size: 0.9rem; line-height: 1.2;">
                        <th style="padding: 2px;">File No.</th>
                        <td style="padding: 2px;">: <?php echo htmlspecialchars($treatment['file_number'] ?? ''); ?></td>
                    </tr>
                </table>
            </div>
            <div class="col-md-6">
                <table class="table table-borderless table-sm mb-0">
                    <tr style="font-size: 0.9rem; line-height: 1.2;">
                        <th width="120" style="padding: 2px;">Treatment Date</th>
                        <td style="padding: 2px;">: <?php echo !empty($treatment['treatment_date']) ? date('d/m/Y', strtotime($treatment['treatment_date'])) : ''; ?></td>
                    </tr>
                    <tr style="font-size: 0.9rem; line-height: 1.2;">
                        <th style="padding: 2px;">Nurse Name</th>
                        <td style="padding: 2px;">: <?php echo htmlspecialchars($treatment['nurse_name'] ?? ''); ?></td>
                    </tr>
                    <tr style="font-size: 0.9rem; line-height: 1.2;">
                        <th style="padding: 2px;">Payment</th>
                        <td style="padding: 2px;">: 
                            <?php if (($treatment['payment_status'] ?? '') === 'Paid'): ?>
                                <span class="badge bg-success">Paid</span>
                            <?php else: ?>
                                <span class="badge bg-warning text-dark"><?php echo htmlspecialchars($treatment['payment_status'] ?? 'Unpaid'); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- REPORT section -->
        <div class="row mb-4" style="margin-top: 10px;">
            <div class="col-12">
                <h5 class="border-bottom pb-2">REPORT</h5>
                <p><?php echo nl2br(htmlspecialchars($treatment['report'] ?? '')); ?></p>
            </div>
        </div>

        <!-- TREATMENT section -->
        <div class="row mb-4">
            <div class="col-12">
                <h5 class="border-bottom pb-2">TREATMENT</h5>
                <p><?php echo nl2br(htmlspecialchars($treatment['treatment'] ?? '')); ?></p>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Print functionality
        function printTreatment() {
            // Store patient name and file number for the suggested filename
            var patientName = "<?php echo sanitizeFilename($treatment['first_name'] . '_' . $treatment['last_name']); ?>";
            var treatmentId = "<?php echo $treatment['id']; ?>";
            
            // Open print dialog
            window.print();
            
            // Set the document title for the print dialog
            document.title = "Treatment_" + treatmentId + "_" + patientName;
        }
        
        // Call print function when page loads (for print button)
        document.addEventListener('DOMContentLoaded', function() {
            // Add event listener for print button
            var printBtn = document.querySelector('.btn-print');
            if (printBtn) {
                printBtn.addEventListener('click', printTreatment);
            }
        });
    </script>
</body>
</html>
