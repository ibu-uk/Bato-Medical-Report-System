<?php
// Disable all error reporting and output buffering to prevent any HTML/output before JSON
error_reporting(0);
ini_set('display_errors', 0);
ob_start();

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include required files
require_once 'config/timezone.php';
require_once 'config/database.php';
require_once 'config/auth.php';
require_once 'config/secure_links.php';

// Check if token is provided
$token = isset($_GET['token']) ? $_GET['token'] : '';

if (empty($token)) {
    die('Access denied: No token provided');
}

// Validate token and get patient ID
$tokenData = validateReportToken($token);

if (!$tokenData) {
    die('Access denied: Invalid or expired token');
}

// Get patient ID from validated token
$patientId = $tokenData['patient_id'];

// Debug: Log token validation
error_log("DEBUG: Token validated successfully for patient ID: $patientId");

// Create database connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    error_log("DEBUG: Database connection failed: " . $conn->connect_error);
    die("Connection failed: " . $conn->connect_error);
}

error_log("DEBUG: Database connection successful");

// Get patient information
$patientQuery = "SELECT * FROM patients WHERE id = ?";
$patientStmt = $conn->prepare($patientQuery);
if (!$patientStmt) {
    error_log("DEBUG: Patient prepare failed: " . $conn->error);
    die("Prepare failed: " . $conn->error);
}
$patientStmt->bind_param("i", $patientId);
$patientStmt->execute();
$patientResult = $patientStmt->get_result();

error_log("DEBUG: Patient query executed, result count: " . $patientResult->num_rows);

if ($patientResult->num_rows === 0) {
    error_log("DEBUG: Patient not found for ID: $patientId");
    die('Patient not found');
}

$patient = $patientResult->fetch_assoc();
$patientStmt->close();

error_log("DEBUG: Patient found: " . $patient['name']);

// Get all reports for this patient
$reportsQuery = "SELECT r.*, 
                       (SELECT COUNT(*) FROM report_tests rt WHERE rt.report_id = r.id) as test_count
                       FROM reports r 
                       WHERE r.patient_id = ? 
                       ORDER BY r.report_date DESC, r.id DESC";
$reportsStmt = $conn->prepare($reportsQuery);
if (!$reportsStmt) {
    error_log("DEBUG: Reports prepare failed: " . $conn->error);
    die("Prepare failed: " . $conn->error);
}
$reportsStmt->bind_param("i", $patientId);
$reportsStmt->execute();
$reportsResult = $reportsStmt->get_result();

error_log("DEBUG: Reports query executed, result count: " . $reportsResult->num_rows);

// Get all prescriptions for this patient
$prescriptionsQuery = "SELECT p.*, d.name as doctor_name 
                       FROM prescriptions p 
                       JOIN doctors d ON p.doctor_id = d.id
                       WHERE p.patient_id = ? 
                       ORDER BY p.prescription_date DESC, p.id DESC";
$prescriptionsStmt = $conn->prepare($prescriptionsQuery);
if (!$prescriptionsStmt) {
    error_log("DEBUG: Prescriptions prepare failed: " . $conn->error);
    die("Prepare failed: " . $conn->error);
}
$prescriptionsStmt->bind_param("i", $patientId);
$prescriptionsStmt->execute();
$prescriptionsResult = $prescriptionsStmt->get_result();

error_log("DEBUG: Prescriptions query executed, result count: " . $prescriptionsResult->num_rows);

// Get all nurse treatments for this patient (with table existence check)
$nurseTreatmentsQuery = "SELECT nt.*, d.name as doctor_name 
                          FROM nurse_treatments nt
                          LEFT JOIN doctors d ON nt.doctor_id = d.id
                          WHERE nt.patient_id = ? 
                          ORDER BY nt.treatment_date DESC, nt.id DESC";
$nurseTreatmentsStmt = $conn->prepare($nurseTreatmentsQuery);
if ($nurseTreatmentsStmt) {
    $nurseTreatmentsStmt->bind_param("i", $patientId);
    $nurseTreatmentsStmt->execute();
    $nurseTreatmentsResult = $nurseTreatmentsStmt->get_result();
    
    error_log("DEBUG: Nurse treatments query executed, result count: " . $nurseTreatmentsResult->num_rows);
} else {
    error_log("DEBUG: Nurse treatments prepare failed: " . $conn->error);
    $nurseTreatmentsResult = false;
}

$conn->close();

// Get clinic info
$clinicQuery = "SELECT * FROM clinic_info LIMIT 1";
$clinicResult = $conn->query($clinicQuery);
$clinic = $clinicResult->fetch_assoc();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .dashboard-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        .section-card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            margin-bottom: 20px;
        }
        .section-title {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px 8px 0 0;
            font-weight: bold;
            color: #495057;
        }
        .no-records {
            text-align: center;
            padding: 40px;
            color: #6c757d;
        }
        .report-item {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            transition: all 0.3s ease;
        }
        .report-item:hover {
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }
        .test-count {
            background: #e9ecef;
            color: #495057;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 0.8em;
        }
    </style>
</head>
<body>
    <div class="container-fluid dashboard-container">
        <div class="row mb-4">
            <div class="col-12">
                <h2><i class="fas fa-user-injured me-2"></i>Patient Dashboard</h2>
                <p class="text-muted">Welcome, <?php echo htmlspecialchars($patient['name']); ?>! Here are your medical records.</p>
            </div>
        </div>

        <!-- Reports Section -->
        <div class="section-card">
            <div class="section-title">
                <i class="fas fa-file-medical me-2"></i>
                Medical Reports
                <span class="badge bg-primary text-white ms-2"><?php echo $reportsResult->num_rows; ?></span>
            </div>
            <div class="card-body p-4">
                <?php if ($reportsResult->num_rows > 0): ?>
                    <?php while ($report = $reportsResult->fetch_assoc()): ?>
                        <div class="report-item">
                            <div class="row">
                                <div class="col-md-8">
                                    <h6 class="mb-2">
                                        <i class="fas fa-calendar-alt me-2"></i>
                                        <?php echo date('d/m/Y', strtotime($report['report_date'])); ?>
                                    </h6>
                                    <p class="mb-1">
                                        <strong>Doctor:</strong> <?php echo htmlspecialchars($report['doctor_name']); ?>
                                    </p>
                                    <p class="mb-1">
                                        <strong>Tests:</strong> 
                                        <span class="test-count"><?php echo $report['test_count']; ?> tests</span>
                                    </p>
                                </div>
                                <div class="col-md-4 text-end">
                                    <div class="mt-3">
                                        <a href="view_report.php?token=<?php echo urlencode($token); ?>&doc=<?php echo base64_encode($report['id'] . '_' . $patientId); ?>" 
                                           class="btn btn-primary btn-sm" 
                                           target="_blank">
                                            <i class="fas fa-eye me-1"></i>
                                            View Report
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>No Medical Records Found</strong>
                        <p>This patient doesn't have any medical records yet. Once reports, prescriptions, or treatments are added to their file, they will appear here automatically.</p>
                        <p class="mb-3">
                            <small>
                                <i class="fas fa-lightbulb me-1"></i>
                                <strong>Note:</strong> Contact your healthcare provider if you believe this is an error.
                            </small>
                        </p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Prescriptions Section -->
        <div class="section-card">
            <div class="section-title">
                <i class="fas fa-prescription me-2"></i>
                Prescriptions
                <span class="badge bg-success text-white ms-2"><?php echo $prescriptionsResult->num_rows; ?></span>
            </div>
            <div class="card-body p-4">
                <?php if ($prescriptionsResult->num_rows > 0): ?>
                    <?php while ($prescription = $prescriptionsResult->fetch_assoc()): ?>
                        <div class="report-item">
                            <div class="row">
                                <div class="col-md-8">
                                    <h6 class="mb-2">
                                        <i class="fas fa-calendar-alt me-2"></i>
                                        <?php echo date('d/m/Y', strtotime($prescription['prescription_date'])); ?>
                                    </h6>
                                    <p class="mb-1">
                                        <strong>Doctor:</strong> <?php echo htmlspecialchars($prescription['doctor_name']); ?>
                                    </p>
                                </div>
                                <div class="col-md-4 text-end">
                                    <div class="mt-3">
                                        <a href="view_prescription.php?token=<?php echo urlencode($token); ?>&doc=<?php echo base64_encode($prescription['id'] . '_' . $patientId); ?>" 
                                           class="btn btn-success btn-sm" 
                                           target="_blank">
                                            <i class="fas fa-eye me-1"></i>
                                            View Prescription
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="no-records">
                        <i class="fas fa-folder-open fa-3x mb-3"></i>
                        <h5>No Prescriptions Found</h5>
                        <p>You don't have any prescriptions yet.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Nurse Treatments Section -->
        <?php if ($nurseTreatmentsResult !== false): ?>
        <div class="section-card">
            <div class="section-title">
                <i class="fas fa-user-nurse me-2"></i>
                Nurse Treatments
                <span class="badge bg-warning text-white ms-2"><?php echo $nurseTreatmentsResult->num_rows; ?></span>
            </div>
            <div class="card-body p-4">
                <?php if ($nurseTreatmentsResult->num_rows > 0): ?>
                    <?php while ($treatment = $nurseTreatmentsResult->fetch_assoc()): ?>
                        <div class="report-item">
                            <div class="row">
                                <div class="col-md-8">
                                    <h6 class="mb-2">
                                        <i class="fas fa-calendar-alt me-2"></i>
                                        <?php echo date('d/m/Y', strtotime($treatment['treatment_date'])); ?>
                                    </h6>
                                    <p class="mb-1">
                                        <strong>Doctor:</strong> <?php echo htmlspecialchars($treatment['doctor_name']); ?>
                                    </p>
                                </div>
                                <div class="col-md-4 text-end">
                                    <div class="mt-3">
                                        <a href="view_nurse_treatment.php?token=<?php echo urlencode($token); ?>&doc=<?php echo base64_encode($treatment['id'] . '_' . $patientId); ?>" 
                                           class="btn btn-warning btn-sm" 
                                           target="_blank">
                                            <i class="fas fa-eye me-1"></i>
                                            View Treatment
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="no-records">
                        <i class="fas fa-folder-open fa-3x mb-3"></i>
                        <h5>No Nurse Treatments Found</h5>
                        <p>You don't have any nurse treatments yet.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <div class="text-center mt-4">
        <p class="text-muted">
            <small>
                <i class="fas fa-shield-alt me-1"></i>
                Secure Patient Portal | BATO Clinic
                <br>
                Generated: <?php echo date('d/m/Y H:i'); ?>
            </small>
        </p>
    </div>
</body>
</html>
