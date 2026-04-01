<?php
session_start();

require_once 'config/database.php';
require_once 'config/timezone.php';
require_once 'config/auth.php';

requireLogin();

if (!hasRole(['admin'])) {
    header('Location: index.php');
    exit;
}

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

$counts = [
    'patients' => 0,
    'reports' => 0,
    'prescriptions' => 0,
    'nurse_treatments' => 0
];

$metrics = [
    'new_patients_month' => 0,
    'reports_last_7_days' => 0,
    'reports_today' => 0,
    'prescriptions_today' => 0,
    'nurse_treatments_today' => 0
];

$result = $conn->query("SELECT COUNT(*) AS total FROM patients");
if ($result && $row = $result->fetch_assoc()) {
    $counts['patients'] = (int)$row['total'];
}

$result = $conn->query("SELECT COUNT(*) AS total FROM reports");
if ($result && $row = $result->fetch_assoc()) {
    $counts['reports'] = (int)$row['total'];
}

$result = $conn->query("SELECT COUNT(*) AS total FROM prescriptions");
if ($result && $row = $result->fetch_assoc()) {
    $counts['prescriptions'] = (int)$row['total'];
}

$result = $conn->query("SELECT COUNT(*) AS total FROM nurse_treatments");
if ($result && $row = $result->fetch_assoc()) {
    $counts['nurse_treatments'] = (int)$row['total'];
}

$result = $conn->query("SELECT COUNT(*) AS total FROM patients WHERE created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01') AND created_at < DATE_ADD(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL 1 MONTH)");
if ($result && $row = $result->fetch_assoc()) {
    $metrics['new_patients_month'] = (int)$row['total'];
}

$result = $conn->query("SELECT COUNT(*) AS total FROM reports WHERE created_at >= (NOW() - INTERVAL 7 DAY)");
if ($result && $row = $result->fetch_assoc()) {
    $metrics['reports_last_7_days'] = (int)$row['total'];
}

$result = $conn->query("SELECT COUNT(*) AS total FROM reports WHERE created_at >= CURDATE() AND created_at < DATE_ADD(CURDATE(), INTERVAL 1 DAY)");
if ($result && $row = $result->fetch_assoc()) {
    $metrics['reports_today'] = (int)$row['total'];
}

$result = $conn->query("SELECT COUNT(*) AS total FROM prescriptions WHERE created_at >= CURDATE() AND created_at < DATE_ADD(CURDATE(), INTERVAL 1 DAY)");
if ($result && $row = $result->fetch_assoc()) {
    $metrics['prescriptions_today'] = (int)$row['total'];
}

$result = $conn->query("SELECT COUNT(*) AS total FROM nurse_treatments WHERE created_at >= CURDATE() AND created_at < DATE_ADD(CURDATE(), INTERVAL 1 DAY)");
if ($result && $row = $result->fetch_assoc()) {
    $metrics['nurse_treatments_today'] = (int)$row['total'];
}

$recentPatients = [];
$recentPatientsQuery = "SELECT p.id, p.name, p.file_number, p.mobile, p.created_at
                        FROM patients p
                        ORDER BY p.created_at DESC
                        LIMIT 6";
$result = $conn->query($recentPatientsQuery);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $recentPatients[] = $row;
    }
}

$recentReports = [];
$recentReportsQuery = "SELECT r.id, r.report_date, r.created_at, p.name AS patient_name, p.file_number, d.name AS doctor_name
                       FROM reports r
                       JOIN patients p ON p.id = r.patient_id
                       JOIN doctors d ON d.id = r.doctor_id
                       ORDER BY r.created_at DESC
                       LIMIT 6";
$result = $conn->query($recentReportsQuery);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $recentReports[] = $row;
    }
}

$recentPrescriptions = [];
$recentPrescriptionsQuery = "SELECT pr.id, pr.prescription_date, p.name AS patient_name, p.file_number, d.name AS doctor_name
                             FROM prescriptions pr
                             JOIN patients p ON p.id = pr.patient_id
                             JOIN doctors d ON d.id = pr.doctor_id
                             ORDER BY pr.prescription_date DESC, pr.id DESC
                             LIMIT 6";
$result = $conn->query($recentPrescriptionsQuery);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $recentPrescriptions[] = $row;
    }
}

$recentNurseTreatments = [];
$recentNurseTreatmentsQuery = "SELECT nt.id, nt.treatment_date, nt.nurse_name, p.name AS patient_name, p.file_number
                               FROM nurse_treatments nt
                               JOIN patients p ON p.id = nt.patient_id
                               ORDER BY nt.treatment_date DESC, nt.id DESC
                               LIMIT 6";
$result = $conn->query($recentNurseTreatmentsQuery);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $recentNurseTreatments[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Bato Medical Report System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/arabic-fonts.css">
    <style>
        :root {
            --dashboard-bg: #f4f8fb;
            --brand-deep: #0f3d5e;
            --brand-mid: #156083;
            --brand-soft: #deedf7;
            --tile-border: #d7e4ef;
            --muted-text: #526171;
        }
        .main-content {
            background: radial-gradient(circle at top right, #e6f1f8 0%, var(--dashboard-bg) 38%, #f8fbfd 100%);
            min-height: calc(100vh - 56px);
        }
        .kpi-tile {
            border-radius: 14px;
            border: 1px solid var(--tile-border);
            background: #ffffff;
            box-shadow: 0 10px 22px rgba(13, 43, 66, 0.08);
            height: 100%;
            transition: transform .15s ease, box-shadow .15s ease;
        }
        .kpi-tile:hover {
            transform: translateY(-2px);
            box-shadow: 0 14px 26px rgba(13, 43, 66, 0.12);
        }
        .kpi-label {
            font-size: .78rem;
            color: var(--muted-text);
            text-transform: uppercase;
            letter-spacing: .04em;
            margin-bottom: .2rem;
        }
        .kpi-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: #0e2f45;
            line-height: 1.1;
        }
        .kpi-icon {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: 2px solid currentColor;
            opacity: 0.9;
            background: #f8fbff;
        }
        .clinic-panel {
            border: 1px solid var(--tile-border);
            border-radius: 14px;
            box-shadow: 0 10px 22px rgba(13, 43, 66, 0.08);
            overflow: hidden;
        }
        .clinic-panel .card-header {
            background: linear-gradient(90deg, var(--brand-deep), var(--brand-mid));
            border-bottom: 0;
            color: #ffffff;
        }
        .clinic-panel .card-header .btn-outline-primary {
            border-color: #cfe6f6;
            color: #eaf5fc;
            background: rgba(255, 255, 255, 0.08);
        }
        .clinic-panel .card-header .btn-outline-primary:hover {
            border-color: #ffffff;
            color: #0f3d5e;
            background: #ffffff;
        }
        .clinic-panel .table thead.table-light th {
            background: #edf4fa;
            color: #1f4b67;
            border-bottom: 1px solid #d6e5f1;
        }
        .clinic-panel .table tbody tr:hover {
            background: #f6fbff;
        }
        .clinic-panel .list-group-item {
            border-color: #e5edf5;
        }
        .mini-badge {
            font-size: .73rem;
            border: 1px solid #c5d9ea;
            color: #335772;
            background: #f1f7fc;
            border-radius: 999px;
            padding: .15rem .55rem;
        }
    </style>
</head>
<body>
    <?php include_once 'includes/sidebar.php'; ?>

    <nav class="top-navbar">
        <div class="container-fluid">
            <div class="d-flex align-items-center">
                <button class="btn btn-link d-md-none" id="mobileMenuToggle">
                    <i class="fas fa-bars"></i>
                </button>
                <div class="ms-auto d-flex align-items-center">
                    <div class="dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-user-circle me-1"></i>
                            <span class="d-none d-md-inline"><?php echo isset($_SESSION['full_name']) ? htmlspecialchars($_SESSION['full_name']) : ''; ?></span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="profile.php"><i class="fas fa-id-card me-2"></i>Profile</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <div class="main-content">
        <div class="container-fluid py-4">
            <div class="d-flex align-items-center mb-3">
                <div>
                    <h4 class="mb-1">Dashboard</h4>
                    <p class="text-muted mb-0">Clinic snapshot with newest patients, prescriptions, and nurse treatments.</p>
                </div>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-md-3 col-sm-6">
                    <div class="kpi-tile p-3 d-flex justify-content-between align-items-center">
                        <div>
                            <div class="kpi-label">Total Patients</div>
                            <div class="kpi-value"><?php echo $counts['patients']; ?></div>
                        </div>
                        <span class="kpi-icon text-success"><i class="fas fa-users"></i></span>
                    </div>
                </div>

                <div class="col-md-3 col-sm-6">
                    <div class="kpi-tile p-3 d-flex justify-content-between align-items-center">
                        <div>
                            <div class="kpi-label">Total Reports</div>
                            <div class="kpi-value"><?php echo $counts['reports']; ?></div>
                        </div>
                        <span class="kpi-icon text-primary"><i class="fas fa-file-medical"></i></span>
                    </div>
                </div>

                <div class="col-md-3 col-sm-6">
                    <div class="kpi-tile p-3 d-flex justify-content-between align-items-center">
                        <div>
                            <div class="kpi-label">Prescriptions</div>
                            <div class="kpi-value"><?php echo $counts['prescriptions']; ?></div>
                        </div>
                        <span class="kpi-icon text-info"><i class="fas fa-prescription"></i></span>
                    </div>
                </div>

                <div class="col-md-3 col-sm-6">
                    <div class="kpi-tile p-3 d-flex justify-content-between align-items-center">
                        <div>
                            <div class="kpi-label">Nurse Treatments</div>
                            <div class="kpi-value"><?php echo $counts['nurse_treatments']; ?></div>
                        </div>
                        <span class="kpi-icon text-warning"><i class="fas fa-user-nurse"></i></span>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="kpi-tile p-3 d-flex justify-content-between align-items-center">
                        <div>
                            <div class="kpi-label">New Patients (This Month)</div>
                            <div class="kpi-value"><?php echo $metrics['new_patients_month']; ?></div>
                        </div>
                        <span class="kpi-icon text-danger"><i class="fas fa-user-plus"></i></span>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="kpi-tile p-3 d-flex justify-content-between align-items-center">
                        <div>
                            <div class="kpi-label">Reports (Last 7 Days)</div>
                            <div class="kpi-value"><?php echo $metrics['reports_last_7_days']; ?></div>
                        </div>
                        <span class="kpi-icon text-secondary"><i class="fas fa-calendar-week"></i></span>
                    </div>
                </div>

                <div class="col-md-4 col-sm-6">
                    <div class="kpi-tile p-3 d-flex justify-content-between align-items-center">
                        <div>
                            <div class="kpi-label">Reports Today</div>
                            <div class="kpi-value"><?php echo $metrics['reports_today']; ?></div>
                        </div>
                        <span class="kpi-icon text-primary"><i class="fas fa-print"></i></span>
                    </div>
                </div>

                <div class="col-md-4 col-sm-6">
                    <div class="kpi-tile p-3 d-flex justify-content-between align-items-center">
                        <div>
                            <div class="kpi-label">Prescriptions Today</div>
                            <div class="kpi-value"><?php echo $metrics['prescriptions_today']; ?></div>
                        </div>
                        <span class="kpi-icon text-info"><i class="fas fa-prescription"></i></span>
                    </div>
                </div>

                <div class="col-md-4 col-sm-6">
                    <div class="kpi-tile p-3 d-flex justify-content-between align-items-center">
                        <div>
                            <div class="kpi-label">Nurse Treatments Today</div>
                            <div class="kpi-value"><?php echo $metrics['nurse_treatments_today']; ?></div>
                        </div>
                        <span class="kpi-icon text-warning"><i class="fas fa-user-nurse"></i></span>
                    </div>
                </div>
            </div>

            <div class="row g-3">
                <div class="col-lg-6">
                    <div class="card clinic-panel">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h6 class="mb-0"><i class="fas fa-user-clock me-2"></i>Recently Added Patients</h6>
                            <a href="patient_list.php" class="btn btn-sm btn-outline-primary">View All</a>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Patient</th>
                                            <th>File #</th>
                                            <th>Mobile</th>
                                            <th>Added</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!empty($recentPatients)): ?>
                                            <?php foreach ($recentPatients as $patient): ?>
                                            <tr>
                                                <td class="fw-semibold"><?php echo htmlspecialchars($patient['name']); ?></td>
                                                <td><?php echo htmlspecialchars($patient['file_number']); ?></td>
                                                <td><?php echo htmlspecialchars($patient['mobile']); ?></td>
                                                <td><?php echo date('Y-m-d', strtotime($patient['created_at'])); ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr><td colspan="4" class="text-center text-muted py-4">No recent patients found.</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="card clinic-panel">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h6 class="mb-0"><i class="fas fa-file-medical me-2"></i>Recently Added Reports</h6>
                            <a href="reports.php" class="btn btn-sm btn-outline-primary">View All</a>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Patient</th>
                                            <th>File #</th>
                                            <th>Doctor</th>
                                            <th>Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!empty($recentReports)): ?>
                                            <?php foreach ($recentReports as $item): ?>
                                            <tr>
                                                <td class="fw-semibold"><?php echo htmlspecialchars($item['patient_name']); ?></td>
                                                <td><?php echo htmlspecialchars($item['file_number']); ?></td>
                                                <td><?php echo htmlspecialchars($item['doctor_name']); ?></td>
                                                <td><?php echo date('Y-m-d', strtotime($item['report_date'])); ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr><td colspan="4" class="text-center text-muted py-4">No reports available yet.</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="card clinic-panel">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h6 class="mb-0"><i class="fas fa-prescription me-2"></i>Recently Added Prescriptions</h6>
                            <a href="prescriptions.php" class="btn btn-sm btn-outline-primary">View All</a>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Patient</th>
                                            <th>File #</th>
                                            <th>Doctor</th>
                                            <th>Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!empty($recentPrescriptions)): ?>
                                            <?php foreach ($recentPrescriptions as $item): ?>
                                            <tr>
                                                <td class="fw-semibold"><?php echo htmlspecialchars($item['patient_name']); ?></td>
                                                <td><?php echo htmlspecialchars($item['file_number']); ?></td>
                                                <td><?php echo htmlspecialchars($item['doctor_name']); ?></td>
                                                <td><?php echo date('Y-m-d', strtotime($item['prescription_date'])); ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr><td colspan="4" class="text-center text-muted py-4">No prescriptions available yet.</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="card clinic-panel">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h6 class="mb-0"><i class="fas fa-user-nurse me-2"></i>Recently Added Nurse Treatments</h6>
                            <a href="nurse_treatments.php" class="btn btn-sm btn-outline-primary">View All</a>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Patient</th>
                                            <th>File #</th>
                                            <th>Nurse</th>
                                            <th>Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!empty($recentNurseTreatments)): ?>
                                            <?php foreach ($recentNurseTreatments as $item): ?>
                                            <tr>
                                                <td class="fw-semibold"><?php echo htmlspecialchars($item['patient_name']); ?></td>
                                                <td><?php echo htmlspecialchars($item['file_number']); ?></td>
                                                <td><?php echo htmlspecialchars($item['nurse_name']); ?></td>
                                                <td><?php echo date('Y-m-d', strtotime($item['treatment_date'])); ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr><td colspan="4" class="text-center text-muted py-4">No nurse treatments available yet.</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <footer class="bg-dark text-white py-3 mt-4">
        <div class="container text-center">
            <p class="mb-0"><?php echo date('Y'); ?> Bato Medical Report System. All rights reserved.</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/sidebar.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('mobileMenuToggle')?.addEventListener('click', function() {
                document.body.classList.toggle('sidebar-collapsed');
            });
        });
    </script>
</body>
</html>
