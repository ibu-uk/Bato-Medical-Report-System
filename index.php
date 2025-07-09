<?php
// Start session
session_start();

// Include timezone configuration
require_once 'config/timezone.php';

// Include database configuration
require_once 'config/data.php';

// Include authentication helpers
require_once 'config/auth.php';

// Require login to access this page
requireLogin();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bato Medical Report System</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
    <!-- Arabic Fonts CSS -->
    <link rel="stylesheet" href="assets/css/arabic-fonts.css">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php">Bato Medical Report System</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link active" href="index.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="reports.php">Reports</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="prescriptions.php">Prescriptions</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="nurse_treatments.php">Nurse Treatments</a>
                    </li>
                    <?php if (hasRole(['admin'])): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="manage_doctors.php">Doctors</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="manage_test_types.php">Test Types</a>
                    </li>
                    <?php endif; ?>
                    <li class="nav-item">
                        <a class="nav-link" href="add_patient.php">Add Patient</a>
                    </li>
                    <?php if (hasRole(['admin'])): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="manage_users.php">Users</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="activity_logs.php">Activity Logs</a>
                    </li>
                    <?php endif; ?>
                </ul>
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($_SESSION['full_name']); ?>
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

    <!-- Main Content -->
    <div class="container my-4">
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="mb-0">Medical Report Generator</h4>
                    </div>
                    <div class="card-body">
                        <form id="reportForm" action="generate_report.php" method="post">
                            <!-- Patient Information Section -->
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h5 class="mb-0">Patient Information</h5>
                                </div>
                                <div class="card-body">
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label for="patient_search" class="form-label">Search Patient</label>
                                            <div class="input-group mb-2">
                                                <input type="text" class="form-control" id="patient_search" placeholder="Search by name, mobile or civil ID" autocomplete="off">
                                                <button class="btn btn-outline-secondary" type="button" id="clear_search">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </div>
                                            <div id="search_status" class="small text-muted mb-2">Type at least 3 characters to search</div>
                                            <label for="patient" class="form-label">Select Patient</label>
                                            <div class="input-group">
                                                <select class="form-select" id="patient" name="patient_id" required>
                                                    <option value="">-- Select Patient --</option>
                                                    <!-- Patient options will be loaded via AJAX -->
                                                </select>
                                                <a href="add_patient.php" class="btn btn-success">
                                                    <i class="fas fa-user-plus"></i> New
                                                </a>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="civil_id" class="form-label">Civil ID</label>
                                            <input type="text" class="form-control" id="civil_id" name="civil_id" readonly>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <label for="mobile" class="form-label">Mobile</label>
                                            <input type="text" class="form-control" id="mobile" name="mobile" readonly>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="report_date" class="form-label">Report Date</label>
                                            <input type="date" class="form-control" id="report_date" name="report_date" value="<?php echo date('Y-m-d'); ?>" required>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Test Results Section -->
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h5 class="mb-0">Test Results</h5>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <button type="button" class="btn btn-success" id="addTestBtn">
                                            <i class="fas fa-plus"></i> Add Test
                                        </button>
                                    </div>
                                    <div id="testsContainer">
                                        <!-- Test rows will be added here dynamically -->
                                    </div>
                                </div>
                            </div>

                            <!-- Doctor Information Section -->
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h5 class="mb-0">Doctor Information</h5>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <label for="doctor" class="form-label">Select Doctor</label>
                                            <select class="form-select" id="doctor" name="doctor_id" required>
                                                <option value="">-- Select Doctor --</option>
                                                <?php
                                                $doctors = executeQuery("SELECT id, name, position FROM doctors ORDER BY name");
                                                while ($row = $doctors->fetch_assoc()) {
                                                    echo "<option value='{$row['id']}' data-position='{$row['position']}'>{$row['name']}</option>";
                                                }
                                                ?>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="doctor_position" class="form-label">Position</label>
                                            <input type="text" class="form-control" id="doctor_position" name="doctor_position" readonly>
                                        </div>
                                    </div>
                                    <div class="row mt-3">
                                        <div class="col-md-6">
                                            <label for="generated_by" class="form-label">Generated By</label>
                                            <input type="text" class="form-control" id="generated_by" name="generated_by" value="<?php echo htmlspecialchars($_SESSION['full_name']); ?>" readonly>
                                            <input type="hidden" name="user_id" value="<?php echo $_SESSION['user_id']; ?>">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Submit Button -->
                            <div class="text-center">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-file-pdf"></i> Generate Report
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-dark text-white py-3 mt-4">
        <div class="container text-center">
            <p class="mb-0">© <?php echo date('Y'); ?> Bato Medical Report System. All rights reserved.</p>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Custom JS -->
    <script src="assets/js/script.js"></script>
</body>
</html>
