<?php
// Start session
session_start();

// Include configuration files
require_once 'config/database.php';
require_once 'config/auth.php';
require_once 'config/timezone.php';
require_once 'config/helpers.php';

// Check if patient ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: patient_list.php');
    exit();
}

$patient_id = (int)$_GET['id'];

// Create database connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// sanitize() function is now in config/helpers.php

// Get patient details
$patient_query = "SELECT * FROM patients WHERE id = ?";
$stmt = $conn->prepare($patient_query);
$stmt->bind_param('i', $patient_id);
$stmt->execute();
$patient_result = $stmt->get_result();

if ($patient_result->num_rows === 0) {
    // Patient not found, redirect to patient list
    header('Location: patient_list.php?error=patient_not_found');
    exit();
}

$patient = $patient_result->fetch_assoc();

// Get patient's reports with doctor information
$reports_query = "SELECT r.*, d.name as doctor_name 
                 FROM reports r 
                 LEFT JOIN doctors d ON r.doctor_id = d.id 
                 WHERE r.patient_id = ? 
                 ORDER BY r.report_date DESC";
$stmt = $conn->prepare($reports_query);
$stmt->bind_param('i', $patient_id);
$stmt->execute();
$reports = $stmt->get_result();

// Get patient's prescriptions
$prescriptions_query = "SELECT p.*, d.name as doctor_name 
                       FROM prescriptions p 
                       LEFT JOIN doctors d ON p.doctor_id = d.id 
                       WHERE p.patient_id = ? 
                       ORDER BY p.prescription_date DESC";
$stmt = $conn->prepare($prescriptions_query);
$stmt->bind_param('i', $patient_id);
$stmt->execute();
$prescriptions = $stmt->get_result();

// Get patient's treatments
$treatments_query = "SELECT * FROM nurse_treatments WHERE patient_id = ? ORDER BY treatment_date DESC";
$stmt = $conn->prepare($treatments_query);
$stmt->bind_param('i', $patient_id);
$stmt->execute();
$treatments = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Patient - Bato Medical Report System</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
            padding: 20px;
        }
        .card {
            border: none;
            border-radius: 8px;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            margin-bottom: 20px;
        }
        .card-header {
            background-color: #f8f9fa;
            border-bottom: 1px solid rgba(0,0,0,.125);
            padding: 1rem 1.5rem;
            border-radius: 8px 8px 0 0 !important;
        }
        .card-body {
            padding: 1.5rem;
        }
        .btn-back {
            margin-right: 10px;
        }
        .form-label {
            font-weight: 500;
            margin-bottom: 0.5rem;
        }
        .patient-info {
            background-color: #fff;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }
        .info-label {
            font-weight: 600;
            color: #6c757d;
        }
        .nav-tabs .nav-link {
            color: #495057;
            font-weight: 500;
        }
        .nav-tabs .nav-link.active {
            font-weight: 600;
            color: #0d6efd;
        }
    </style>
</head>
<body>

    <!-- Main Content -->
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <a href="patient_list.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>
                <span class="d-none d-sm-inline">Back to Patients</span>
            </a>
        </div>
    </div>

    <!-- Patient Information -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0"><i class="fas fa-user me-2"></i>Patient Information</h5>
        </div>
        <div class="card-body">
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="border rounded p-3 h-100">
                        <p class="text-muted mb-2 small"><i class="fas fa-user me-2"></i>Full Name</p>
                        <h5 class="mb-0 fw-bold"><?php echo htmlspecialchars($patient['name']); ?></h5>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="border rounded p-3 h-100">
                        <p class="text-muted mb-2 small"><i class="fas fa-id-card me-2"></i>Civil ID</p>
                        <h5 class="mb-0 fw-bold"><?php echo htmlspecialchars($patient['civil_id']); ?></h5>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="border rounded p-3 h-100">
                        <p class="text-muted mb-2 small"><i class="fas fa-phone me-2"></i>Mobile Number</p>
                        <h5 class="mb-0 fw-bold">
                            <?php 
                            $phone = !empty($patient['mobile']) ? $patient['mobile'] : 
                                    (!empty($patient['phone']) ? $patient['phone'] : 'N/A');
                            echo htmlspecialchars($phone);
                            ?>
                        </h5>
                    </div>
                </div>
            </div>
            <?php if (!empty($patient['address'])): ?>
            <div class="row">
                <div class="col-12">
                    <h6 class="text-muted mb-1">Address</h6>
                    <p class="mb-0"><?php echo nl2br(sanitize($patient['address'])); ?></p>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Medical Information Tabs -->
    <ul class="nav nav-tabs mb-4" id="patientTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="reports-tab" data-bs-toggle="tab" data-bs-target="#reports" type="button" role="tab">
                <i class="fas fa-file-medical me-2"></i>Reports
                <span class="badge bg-primary ms-2"><?php echo $reports->num_rows; ?></span>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="prescriptions-tab" data-bs-toggle="tab" data-bs-target="#prescriptions" type="button" role="tab">
                <i class="fas fa-prescription me-2"></i>Prescriptions
                <span class="badge bg-success ms-2"><?php echo $prescriptions->num_rows; ?></span>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="treatments-tab" data-bs-toggle="tab" data-bs-target="#treatments" type="button" role="tab">
                <i class="fas fa-user-nurse me-2"></i>Treatments
                <span class="badge bg-warning text-dark ms-2"><?php echo $treatments->num_rows; ?></span>
            </button>
        </li>
    </ul>

    <div class="tab-content" id="patientTabsContent">
        <!-- Reports Tab -->
        <div class="tab-pane fade show active" id="reports" role="tabpanel" aria-labelledby="reports-tab">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Medical Reports</h5>
                    <a href="add_report.php?patient_id=<?php echo $patient_id; ?>" class="btn btn-sm btn-primary">
                        <i class="fas fa-plus me-1"></i>Add Report
                    </a>
                </div>
                <div class="card-body">
                    <?php if ($reports->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Report Type</th>
                                        <th>Doctor</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($report = $reports->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo date('M j, Y', strtotime($report['report_date'])); ?></td>
                                            <td><?php echo !empty($report['report_type']) ? sanitize($report['report_type']) : 'General'; ?></td>
                                            <td><?php echo !empty($report['doctor_name']) ? sanitize($report['doctor_name']) : 'N/A'; ?></td>
                                            <td>
                                                <a href="view_report.php?id=<?php echo $report['id']; ?>" class="btn btn-sm btn-info" title="View">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info mb-0">
                            <i class="fas fa-info-circle me-2"></i> No medical reports found for this patient.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Prescriptions Tab -->
        <div class="tab-pane fade" id="prescriptions" role="tabpanel" aria-labelledby="prescriptions-tab">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Prescriptions</h5>
                    <a href="add_prescription.php?patient_id=<?php echo $patient_id; ?>" class="btn btn-sm btn-primary">
                        <i class="fas fa-plus me-1"></i>Add Prescription
                    </a>
                </div>
                <div class="card-body">
                    <?php if ($prescriptions->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Doctor</th>
                                        <th>Medication</th>
                                        <th>Dosage</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($prescription = $prescriptions->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo date('M j, Y', strtotime($prescription['prescription_date'])); ?></td>
                                            <td><?php echo !empty($prescription['doctor_name']) ? sanitize($prescription['doctor_name']) : 'N/A'; ?></td>
                                            <td><?php echo sanitize($prescription['medication']); ?></td>
                                            <td><?php echo !empty($prescription['dosage']) ? sanitize($prescription['dosage']) : 'N/A'; ?></td>
                                            <td>
                                                <a href="view_prescription.php?id=<?php echo $prescription['id']; ?>" class="btn btn-sm btn-info" title="View">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info mb-0">
                            <i class="fas fa-info-circle me-2"></i> No prescriptions found for this patient.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Treatments Tab -->
        <div class="tab-pane fade" id="treatments" role="tabpanel" aria-labelledby="treatments-tab">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Nurse Treatments</h5>
                    <a href="add_treatment.php?patient_id=<?php echo $patient_id; ?>" class="btn btn-sm btn-primary">
                        <i class="fas fa-plus me-1"></i>Add Treatment
                    </a>
                </div>
                <div class="card-body">
                    <?php if ($treatments->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Treatment Type</th>
                                        <th>Nurse</th>
                                        <th>Notes</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($treatment = $treatments->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo date('M j, Y', strtotime($treatment['treatment_date'])); ?></td>
                                            <td><?php echo sanitize($treatment['treatment_type']); ?></td>
                                            <td><?php echo !empty($treatment['nurse_name']) ? sanitize($treatment['nurse_name']) : 'N/A'; ?></td>
                                            <td><?php echo !empty($treatment['notes']) ? substr(sanitize($treatment['notes']), 0, 50) . '...' : 'N/A'; ?></td>
                                            <td>
                                                <a href="view_treatment.php?id=<?php echo $treatment['id']; ?>" class="btn btn-sm btn-info" title="View">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info mb-0">
                            <i class="fas fa-info-circle me-2"></i> No treatments found for this patient.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript Libraries -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
</script>

<?php
// Function to calculate age from date of birth
function calculateAge($dob) {
    $birthDate = new DateTime($dob);
    $today = new DateTime('today');
    $age = $birthDate->diff($today)->y;
    return $age;
}
?>
