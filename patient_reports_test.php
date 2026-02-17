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

$conn->close();

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
            </div>
            <div class="card-body p-4">
                <?php if ($reportsResult->num_rows > 0): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle me-2"></i>
                        Found <?php echo $reportsResult->num_rows; ?> report(s)
                    </div>
                    <?php while ($report = $reportsResult->fetch_assoc()): ?>
                        <div class="border rounded p-3 mb-3">
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
                                        <strong>Tests:</strong> <?php echo $report['test_count']; ?> tests
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
                    <div class="no-records">
                        <i class="fas fa-folder-open fa-3x mb-3"></i>
                        <h5>No Reports Found</h5>
                        <p>You don't have any medical reports yet.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
