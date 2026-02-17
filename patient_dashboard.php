<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

// Include required files
require_once 'config/database.php';
require_once 'config/secure_links.php';

// Check if token exists
if (!isset($_GET['token']) || empty(trim($_GET['token']))) {
    die('Error: No access token provided.');
}

$token = trim($_GET['token']);

// Validate the token and get patient ID
$patientData = validateReportToken($token);

if (!$patientData) {
    die('Error: Invalid or expired token. Please request a new link.');
}

$patientId = $patientData['patient_id'];

// Get database connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get patient information
$stmt = $conn->prepare("SELECT * FROM patients WHERE id = ?");
if ($stmt === false) {
    die("Error preparing patient query: " . $conn->error);
}

$stmt->bind_param('i', $patientId);
if (!$stmt->execute()) {
    die("Error executing patient query: " . $stmt->error);
}

$result = $stmt->get_result();
if ($result->num_rows === 0) {
    die('Error: Patient not found.');
}
$patient = $result->fetch_assoc();
$stmt->close();

// Get patient's reports
$reports = [];
$query = "SELECT * FROM reports WHERE patient_id = ? ORDER BY report_date DESC";
$stmt = $conn->prepare($query);
if ($stmt === false) {
    die("Error preparing reports query: " . $conn->error);
}

$stmt->bind_param('i', $patientId);
if (!$stmt->execute()) {
    die("Error executing reports query: " . $stmt->error);
}

$reportsResult = $stmt->get_result();
while ($row = $reportsResult->fetch_assoc()) {
    $reports[] = $row;
}
$stmt->close();

// Get patient's prescriptions
$prescriptions = [];
$query = "SELECT * FROM prescriptions WHERE patient_id = ? ORDER BY prescription_date DESC";
$stmt = $conn->prepare($query);
if ($stmt === false) {
    die("Error preparing prescriptions query: " . $conn->error);
}

$stmt->bind_param('i', $patientId);
if (!$stmt->execute()) {
    die("Error executing prescriptions query: " . $stmt->error);
}

$prescriptionsResult = $stmt->get_result();
while ($row = $prescriptionsResult->fetch_assoc()) {
    $prescriptions[] = $row;
}
$stmt->close();

// Get patient's nurse treatments
$treatments = [];
try {
    // Use nurse_treatments table which stores nurse treatment records
    $query = "SELECT id, treatment_date, nurse_name FROM nurse_treatments WHERE patient_id = ? ORDER BY treatment_date DESC";
    $stmt = $conn->prepare($query);

    if ($stmt === false) {
        throw new Exception("Error preparing nurse treatments query: " . $conn->error);
    }

    $stmt->bind_param('i', $patientId);
    if (!$stmt->execute()) {
        throw new Exception("Error executing nurse treatments query: " . $stmt->error);
    }

    $treatmentsResult = $stmt->get_result();
    while ($row = $treatmentsResult->fetch_assoc()) {
        $treatments[] = $row;
    }
    $stmt->close();
} catch (Exception $e) {
    // If there's an error (like table doesn't exist), just show no treatments
    $treatments = [];
    error_log("Nurse treatments query error: " . $e->getMessage());
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Dashboard - Bato Medical Report System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        :root {
            --primary-color: #0d6efd;
            --secondary-color: #6c757d;
            --light-color: #f8f9fa;
            --dark-color: #212529;
            --border-radius: 12px;
            --box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            --transition: all 0.3s ease;
        }
        
        body {
            background-color: #f5f7fa;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            color: #333;
            line-height: 1.6;
            padding: 15px;
            overflow-x: hidden; /* prevent accidental horizontal scroll on mobiles */
        }
        
        .card {
            border: none;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            margin-bottom: 20px;
            transition: var(--transition);
            overflow: hidden;
            background: white;
        }
        
        .card:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.12);
        }
        
        .card-header {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border: none;
            font-weight: 600;
            padding: 1rem 1.25rem;
            border-bottom: 1px solid rgba(0,0,0,0.05);
            color: var(--dark-color);
        }
        
        .card-body {
            padding: 1.25rem;
        }
        
        .patient-info-card {
            margin-bottom: 30px;
            border-left: 4px solid var(--primary-color);
        }
        
        .info-item {
            margin-bottom: 12px;
            padding: 10px 15px;
            background: rgba(13, 110, 253, 0.05);
            border-radius: 8px;
            transition: var(--transition);
        }
        
        .info-item:hover {
            background: rgba(13, 110, 253, 0.1);
            transform: translateX(5px);
        }
        
        .info-item i {
            width: 24px;
            text-align: center;
            margin-right: 10px;
            color: var(--primary-color);
        }
        
        .document-card {
            height: 100%;
            display: flex;
            flex-direction: column;
        }
        
        .document-list {
            flex: 1;
        }
        
        .document-item {
            padding: 12px 15px;
            border-bottom: 1px solid rgba(0,0,0,0.05);
            transition: var(--transition);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .document-item:last-child {
            border-bottom: none;
        }
        
        .document-item:hover {
            background: rgba(13, 110, 253, 0.03);
        }
        
        .document-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: rgba(13, 110, 253, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 12px;
            color: var(--primary-color);
            font-size: 18px;
        }
        
        .document-info {
            flex: 1;
        }
        
        .document-date {
            font-size: 0.85rem;
            color: var(--secondary-color);
        }
        
        .badge-count {
            background: var(--primary-color);
            color: white;
            border-radius: 20px;
            padding: 5px 10px;
            font-weight: 600;
            font-size: 0.8rem;
        }
        
        .empty-message {
            padding: 30px 15px;
            text-align: center;
            color: var(--secondary-color);
            font-style: italic;
            background: rgba(0,0,0,0.02);
            border-radius: 8px;
            margin: 10px;
        }
        
        .btn-back {
            background: white;
            color: var(--dark-color);
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 8px 16px;
            font-weight: 500;
            transition: var(--transition);
            margin-bottom: 20px;
            display: inline-flex;
            align-items: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        
        .btn-back:hover {
            background: #f1f3f5;
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .btn-back i {
            margin-right: 8px;
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            body {
                padding: 10px;
            }

            .card {
                margin-bottom: 12px;
            }

            .patient-info-card .card-body {
                padding: 0.75rem 1rem;
            }

            .patient-info-card h4 {
                font-size: 1.15rem;
            }

            .info-item {
                padding: 6px 10px;
                font-size: 0.9rem;
                margin-bottom: 8px;
            }

            .info-item i {
                margin-right: 6px;
            }

            .document-card .card-header {
                padding: 0.75rem 1rem;
            }

            .document-item {
                padding: 8px 10px;
            }

            .document-icon {
                width: 32px;
                height: 32px;
                font-size: 15px;
                margin-right: 8px;
            }

            .badge-count {
                padding: 3px 8px;
                font-size: 0.7rem;
            }

            .empty-message {
                padding: 20px 10px;
                margin: 8px 0;
            }
        }
        
        /* Animation for page load */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .card {
            animation: fadeIn 0.4s ease-out forwards;
        }
        
        /* Custom scrollbar for webkit browsers */
        ::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }
        
        ::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }
        
        ::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 10px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: #a8a8a8;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <!-- Patient Information Card -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card patient-info-card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-user-circle me-2"></i>Patient Information</h5>
                        <span class="badge bg-primary">Patient ID: <?php echo $patientId; ?></span>
                    </div>
                    <div class="card-body p-0">
                        <div class="p-4">
                            <div class="text-center mb-4">
                                <div class="mx-auto" style="width: 100px; height: 100px; background: rgba(13, 110, 253, 0.1); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 15px;">
                                    <i class="fas fa-user-tie" style="font-size: 40px; color: var(--primary-color);"></i>
                                </div>
                                <h4 class="mb-1">
                                    <?php 
                                        echo !empty($patient['name']) ? htmlspecialchars($patient['name']) : 
                                             (!empty($patient['first_name']) ? 
                                                 htmlspecialchars($patient['first_name'] . ' ' . ($patient['last_name'] ?? '')) : 
                                                 'N/A'); 
                                    ?>
                                </h4>
                                <?php if (!empty($patient['file_number'])): ?>
                                <p class="text-muted">File #<?php echo htmlspecialchars($patient['file_number']); ?></p>
                                <?php endif; ?>
                            </div>
                            
                            <div class="row g-3">
                                <?php if (!empty($patient['civil_id'])): ?>
                                <div class="col-12">
                                    <div class="info-item d-flex align-items-center">
                                        <i class="fas fa-id-card"></i>
                                        <div>
                                            <div class="text-muted small">Civil ID</div>
                                            <div class="fw-medium"><?php echo htmlspecialchars($patient['civil_id']); ?></div>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($patient['mobile'])): ?>
                                <div class="col-md-6">
                                    <div class="info-item d-flex align-items-center">
                                        <i class="fas fa-mobile-alt"></i>
                                        <div>
                                            <div class="text-muted small">Mobile</div>
                                            <div class="fw-medium"><?php echo htmlspecialchars($patient['mobile']); ?></div>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($patient['phone'])): ?>
                                <div class="col-md-6">
                                    <div class="info-item d-flex align-items-center">
                                        <i class="fas fa-phone-alt"></i>
                                        <div>
                                            <div class="text-muted small">Phone</div>
                                            <div class="fw-medium"><?php echo htmlspecialchars($patient['phone']); ?></div>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($patient['email'])): ?>
                                <div class="col-12">
                                    <div class="info-item d-flex align-items-center">
                                        <i class="fas fa-envelope"></i>
                                        <div class="text-truncate">
                                            <div class="text-muted small">Email</div>
                                            <div class="fw-medium text-truncate"><?php echo htmlspecialchars($patient['email']); ?></div>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Documents Section -->
        <div class="row g-4">
            <!-- Medical Reports -->
            <div class="col-12 col-md-6 col-lg-4">
                <div class="card document-card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center">
                            <div class="me-2" style="width: 36px; height: 36px; background: rgba(13, 110, 253, 0.1); border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                                <i class="fas fa-file-medical" style="color: var(--primary-color);"></i>
                            </div>
                            <span>Medical Reports</span>
                        </div>
                        <span class="badge-count"><?php echo count($reports); ?></span>
                    </div>
                    <div class="document-list p-3">
                        <?php if (!empty($reports)): ?>
                            <?php foreach ($reports as $report): ?>
                                <?php $docParam = base64_encode($report['id'] . '_' . $patientId); ?>
                                <a href="view_report.php?token=<?php echo urlencode($token); ?>&doc=<?php echo urlencode($docParam); ?>" 
                                   class="text-decoration-none text-dark document-item" target="_blank">
                                    <div class="d-flex align-items-center">
                                        <div class="document-icon">
                                            <i class="fas fa-file-alt"></i>
                                        </div>
                                        <div class="document-info">
                                            <div class="fw-medium"><?php echo htmlspecialchars($report['report_title'] ?? 'Untitled Report'); ?></div>
                                            <div class="document-date"><?php echo date('M d, Y', strtotime($report['report_date'])); ?></div>
                                        </div>
                                    </div>
                                    <i class="fas fa-chevron-right text-muted"></i>
                                </a>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-message">
                                <i class="fas fa-inbox display-4 text-muted mb-2"></i>
                                <p class="mb-0">No medical reports found</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Prescriptions -->
            <div class="col-12 col-md-6 col-lg-4">
                <div class="card document-card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center">
                            <div class="me-2" style="width: 36px; height: 36px; background: rgba(40, 167, 69, 0.1); border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                                <i class="fas fa-prescription" style="color: #28a745;"></i>
                            </div>
                            <span>Prescriptions</span>
                        </div>
                        <span class="badge-count" style="background: #28a745;"><?php echo count($prescriptions); ?></span>
                    </div>
                    <div class="document-list p-3">
                        <?php if (!empty($prescriptions)): ?>
                            <?php foreach ($prescriptions as $prescription): ?>
                                <?php $docParam = base64_encode($prescription['id'] . '_' . $patientId); ?>
                                <a href="view_prescription.php?token=<?php echo urlencode($token); ?>&doc=<?php echo urlencode($docParam); ?>" 
                                   class="text-decoration-none text-dark document-item" target="_blank">
                                    <div class="d-flex align-items-center">
                                        <div class="document-icon" style="background: rgba(40, 167, 69, 0.1); color: #28a745;">
                                            <i class="fas fa-pills"></i>
                                        </div>
                                        <div class="document-info">
                                            <div class="fw-medium"><?php echo htmlspecialchars($prescription['prescription_title'] ?? 'Untitled Prescription'); ?></div>
                                            <div class="document-date"><?php echo date('M d, Y', strtotime($prescription['prescription_date'])); ?></div>
                                        </div>
                                    </div>
                                    <i class="fas fa-chevron-right text-muted"></i>
                                </a>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-message">
                                <i class="fas fa-inbox display-4 text-muted mb-2"></i>
                                <p class="mb-0">No prescriptions found</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Treatments -->
            <div class="col-12 col-md-6 col-lg-4">
                <div class="card document-card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center">
                            <div class="me-2" style="width: 36px; height: 36px; background: rgba(255, 193, 7, 0.1); border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                                <i class="fas fa-heartbeat" style="color: #ffc107;"></i>
                            </div>
                            <span>Treatments</span>
                        </div>
                        <span class="badge-count" style="background: #ffc107;"><?php echo count($treatments); ?></span>
                    </div>
                    <div class="document-list p-3">
                        <?php if (!empty($treatments)): ?>
                            <?php foreach ($treatments as $treatment): ?>
                                <?php $docParam = base64_encode($treatment['id'] . '_' . $patientId); ?>
                                <a href="view_nurse_treatment.php?token=<?php echo urlencode($token); ?>&doc=<?php echo urlencode($docParam); ?>" 
                                   class="text-decoration-none text-dark document-item" target="_blank">
                                    <div class="d-flex align-items-center">
                                        <div class="document-icon" style="background: rgba(255, 193, 7, 0.1); color: #ffc107;">
                                            <i class="fas fa-stethoscope"></i>
                                        </div>
                                        <div class="document-info">
                                            <div class="fw-medium"><?php echo htmlspecialchars($treatment['treatment_type'] ?? 'Treatment'); ?></div>
                                            <div class="document-date">
                                                <?php echo date('M d, Y', strtotime($treatment['treatment_date'])); ?>
                                                <?php if (!empty($treatment['nurse_name'])): ?>
                                                    <span class="ms-2">• <?php echo htmlspecialchars($treatment['nurse_name']); ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                    <i class="fas fa-chevron-right text-muted"></i>
                                </a>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-message">
                                <i class="fas fa-inbox display-4 text-muted mb-2"></i>
                                <p class="mb-0">No treatments found</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <footer class="mt-5 pt-4 border-top">
            <div class="container">
                <div class="row">
                    <div class="col-12 text-center">
                        <p class="text-muted mb-0">
                            <small>© <?php echo date('Y'); ?> Bato Medical Report System. All rights reserved.</small>
                        </p>
                        <p class="text-muted mt-2">
                            <small>Secure Patient Portal - Last updated: <?php echo date('M d, Y h:i A'); ?></small>
                        </p>
                    </div>
                </div>
            </div>
        </footer>
    </div>

    <!-- JavaScript Libraries -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Add any necessary JavaScript here
        document.addEventListener('DOMContentLoaded', function() {
            // Add any initialization code here
        });
    </script>
</body>
</html>