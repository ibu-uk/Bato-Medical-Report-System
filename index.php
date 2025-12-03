<?php
// Start session
session_start();

// Include database configuration first
require_once 'config/database.php';

// Create database connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Include timezone configuration
require_once 'config/timezone.php';

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
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>
    <!-- Include Sidebar -->
    <?php include_once 'includes/sidebar.php'; ?>

    <!-- Top Navigation -->
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

    <!-- Main Content -->
    <div class="main-content">
        <div class="container-fluid py-4">
        <!-- Dashboard Cards -->
        <div class="row mb-4">
            <!-- Total Patients Card -->
            <div class="col-md-3 mb-3">
                <div class="card border-left-primary h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                    Total Patients</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?php 
                                    $result = $conn->query("SELECT COUNT(*) as total FROM patients");
                                    $row = $result->fetch_assoc();
                                    echo $row['total'];
                                    ?>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-users fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Total Reports Card -->
            <div class="col-md-3 mb-3">
                <div class="card border-left-success h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                    Total Reports</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?php 
                                    $result = $conn->query("SELECT COUNT(*) as total FROM reports");
                                    $row = $result->fetch_assoc();
                                    echo $row['total'];
                                    ?>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-file-medical fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Total Prescriptions Card -->
            <div class="col-md-3 mb-3">
                <div class="card border-left-info h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                    Total Prescriptions</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?php 
                                    $result = $conn->query("SELECT COUNT(*) as total FROM prescriptions");
                                    $row = $result->fetch_assoc();
                                    echo $row['total'];
                                    ?>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-prescription fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Total Nurse Treatments Card -->
            <div class="col-md-3 mb-3">
                <div class="card border-left-warning h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                    Nurse Treatments</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">
                                    <?php 
                                    $result = $conn->query("SELECT COUNT(*) as total FROM nurse_treatments");
                                    $row = $result->fetch_assoc();
                                    echo $row['total'];
                                    ?>
                                </div>
                            </div>
                            <div class="col-auto">
                                <i class="fas fa-user-nurse fa-2x text-gray-300"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

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
                                            <label for="file_number" class="form-label">File Number</label>
                                            <input type="text" class="form-control" id="file_number" name="file_number" readonly>
                                        </div>
                                    </div>
                                    <div class="row">
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
                                    <div id="testsContainer">
    <!-- Test rows will be added here dynamically -->
</div>
<div class="mt-3">
    <button type="button" class="btn btn-success" id="addTestBtn">
        <i class="fas fa-plus"></i> Add Test
    </button>
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
                                            <input type="text" class="form-control" id="generated_by" name="generated_by" value="<?php echo isset($_SESSION['full_name']) ? htmlspecialchars($_SESSION['full_name']) : ''; ?>" readonly>
                                            <input type="hidden" name="user_id" value="<?php echo $_SESSION['user_id']; ?>">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Conclusion Section -->
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h5 class="mb-0">Conclusion / Doctor's Final Notes</h5>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label for="conclusion" class="form-label">Conclusion / Doctor's Final Notes</label>
                                        <textarea class="form-control" id="conclusion" name="conclusion" rows="4" placeholder="Enter summary, interpretation, or final notes for this report..."></textarea>
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
            <p class="mb-0">  <?php echo date('Y'); ?> Bato Medical Report System. All rights reserved.</p>
        </div>
    </div>
    </div>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Sidebar JS -->
    <script src="assets/js/sidebar.js"></script>
    <!-- Custom JS -->
    <script src="assets/js/script.js"></script>
    
    <script>
        // Mobile menu toggle
        document.getElementById('mobileMenuToggle')?.addEventListener('click', function() {
            document.body.classList.toggle('sidebar-collapsed');
        });
        
        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(event) {
            const sidebar = document.querySelector('.sidebar');
            const menuToggle = document.getElementById('mobileMenuToggle');
            
            if (window.innerWidth <= 768 && 
                !sidebar.contains(event.target) && 
                event.target !== menuToggle && 
                !menuToggle.contains(event.target)) {
                document.body.classList.add('sidebar-collapsed');
            }
        });
    </script>
</body>
</html>
