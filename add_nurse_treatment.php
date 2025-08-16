<?php
// Start session
session_start();

// Include database configuration
require_once 'config/database.php';
require_once 'config/auth.php';

// No authentication check needed

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // For debugging - uncomment to see form data
    // echo "<pre>";
    // print_r($_POST);
    // echo "</pre>";
    // exit;
    
    // Validate form data
    $patient_id = sanitize($_POST['patient_id']);
    $treatment_date = sanitize($_POST['treatment_date']);
    $nurse_name = sanitize($_POST['nurse_name']);
    $report = sanitize($_POST['report']);
    $treatment = sanitize($_POST['treatment']);
    $payment_status = sanitize($_POST['payment_status']);
    
    // Validate required fields
    if (empty($patient_id) || empty($treatment_date) || empty($nurse_name)) {
        $_SESSION['error'] = "Please fill in all required fields.";
    } else {
        try {
            // Use direct database connection for reliability
            global $conn;
            if (!isset($conn) || !$conn) {
                $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
                if ($conn->connect_error) {
                    throw new Exception("Connection failed: " . $conn->connect_error);
                }
            }
            
            // Begin transaction
            $conn->query("START TRANSACTION");
            
            // Insert treatment record using prepared statement
            $stmt = $conn->prepare("INSERT INTO nurse_treatments (patient_id, treatment_date, nurse_name, report, treatment, payment_status) 
                      VALUES (?, ?, ?, ?, ?, ?)");
                      
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            
            $stmt->bind_param("isssss", $patient_id, $treatment_date, $nurse_name, $report, $treatment, $payment_status);
            $stmt->execute();
            
            if ($stmt->affected_rows < 1) {
                throw new Exception("Failed to insert treatment record");
            }
            
            $treatment_id = $stmt->insert_id;
            $stmt->close();

            // Fetch patient name for logging
            $patient_name = '';
            $stmt2 = $conn->prepare("SELECT name FROM patients WHERE id = ? LIMIT 1");
            if ($stmt2) {
                $stmt2->bind_param("i", $patient_id);
                $stmt2->execute();
                $stmt2->bind_result($patient_name_result);
                if ($stmt2->fetch()) {
                    $patient_name = $patient_name_result;
                }
                $stmt2->close();
            }

            // Log activity
            logUserActivity('add_nurse_treatment', $treatment_id, null, $patient_name);

            // Commit transaction
            $conn->query("COMMIT");

            $_SESSION['success'] = "Treatment record added successfully.";
            header('Location: nurse_treatments.php');
            exit;
        } catch (Exception $e) {
            // Rollback transaction on error
            if (isset($conn)) {
                $conn->query("ROLLBACK");
            }
            $_SESSION['error'] = "Error adding treatment record: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Nurse Treatment - Bato Medical Report System</title>
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
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
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
                        <a class="nav-link" href="reports.php">Medical Reports</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="prescriptions.php"><i class="fas fa-prescription"></i> Prescriptions</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="nurse_treatments.php"><i class="fas fa-user-nurse"></i> Nurse Treatments</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="manage_doctors.php">Doctors</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="manage_test_types.php"><i class="fas fa-vial"></i> Test Types</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="add_patient.php"><i class="fas fa-user-plus"></i> Add Patient</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

<div class="container mt-4">
    <div class="row">
        <!-- Main content -->
        <main class="col-12">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">New Nurse Treatment Record</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <a href="nurse_treatments.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Treatment Records
                    </a>
                </div>
            </div>
            
            <?php
            // Display success or error messages
            if (isset($_SESSION['error'])) {
                echo '<div class="alert alert-danger">' . $_SESSION['error'] . '</div>';
                unset($_SESSION['error']);
            }
            ?>
            
            <form method="POST" action="add_nurse_treatment.php">
                <div class="row mb-4">
                    <!-- Patient Selection -->
                    <div class="col-md-6 mb-3">
                        <div class="card">
                            <div class="card-header">
                                <h5>Patient Information</h5>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label for="patient_search" class="form-label">Search Patient</label>
                                    <div class="input-group mb-2">
                                        <input type="text" class="form-control" id="patient_search" placeholder="Search by name, mobile or civil ID" autocomplete="off">
                                        <button class="btn btn-outline-secondary" type="button" id="clear_search">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                    <div id="search_status" class="small text-muted mb-2">Type at least 3 characters to search</div>
                                </div>
                                <div class="mb-3">
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
                                <div class="mb-3">
                                    <label for="civil_id" class="form-label">Civil ID</label>
                                    <input type="text" class="form-control" id="civil_id" readonly>
                                </div>
                                <div class="mb-3">
                                    <label for="mobile" class="form-label">Mobile</label>
                                    <input type="text" class="form-control" id="mobile" readonly>
                                </div>
                                <div class="mb-3">
                                    <label for="file_number" class="form-label">File Number</label>
                                    <input type="text" class="form-control" id="file_number" readonly>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Treatment Details -->
                    <div class="col-md-6 mb-3">
                        <div class="card">
                            <div class="card-header">
                                <h5>Treatment Details</h5>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label for="treatment_date" class="form-label">Date</label>
                                    <input type="date" class="form-control" id="treatment_date" name="treatment_date" value="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label for="nurse_name" class="form-label">Nurse Name</label>
                                    <input type="text" class="form-control" id="nurse_name" name="nurse_name" required>
                                </div>
                                <div class="mb-3">
                                    <label for="payment_status" class="form-label">Payment Status</label>
                                    <select class="form-select" id="payment_status" name="payment_status" required>
                                        <option value="Unpaid">Unpaid</option>
                                        <option value="Paid">Paid</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Report and Treatment Section -->
                <div class="row mb-4">
                    <div class="col-md-6 mb-3">
                        <div class="card">
                            <div class="card-header">
                                <h5>Report</h5>
                            </div>
                            <div class="card-body">
                                <textarea class="form-control" name="report" rows="5" placeholder="Enter report details here"></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <div class="card">
                            <div class="card-header">
                                <h5>Treatment</h5>
                            </div>
                            <div class="card-body">
                                <textarea class="form-control" name="treatment" rows="5" placeholder="Enter treatment details here"></textarea>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="d-grid gap-2 d-md-flex justify-content-md-end mb-4">
                    <button type="reset" class="btn btn-secondary me-md-2">Reset</button>
                    <button type="submit" class="btn btn-primary">Save Treatment Record</button>
                </div>
            </form>
        </main>
    </div>
</div>

<!-- jQuery (must be loaded first) -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<!-- Bootstrap JS Bundle with Popper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<!-- Custom JS -->
<script src="assets/js/script.js"></script>

<script>
$(document).ready(function() {
    // Patient search functionality
    $('#patient_search').on('keyup', function() {
        var query = $(this).val();
        if (query.length >= 3) {
            $('#search_status').text('Searching...');
            $.ajax({
                url: 'includes/search_patients.php',
                method: 'POST',
                data: {query: query},
                success: function(data) {
                    $('#patient_select').html(data);
                    $('#search_status').text('Select a patient from the list');
                },
                error: function() {
                    $('#search_status').text('Error searching for patients');
                }
            });
        } else {
            $('#search_status').text('Type at least 3 characters to search');
        }
    });

    // Update patient details when a patient is selected
    $(document).on('change', '#patient_select', function() {
        var patientId = $(this).val();
        if (patientId) {
            $.ajax({
                url: 'includes/get_patient_details.php',
                method: 'POST',
                data: {patient_id: patientId},
                dataType: 'json',
                success: function(data) {
                    $('#patient_id').val(patientId);
                    $('#civil_id').val(data.civil_id);
                    $('#mobile').val(data.mobile);
                },
                error: function() {
                    alert('Error fetching patient details');
                }
            });
        } else {
            $('#civil_id').val('');
            $('#mobile').val('');
        }
    });
});
</script>
</body>
</html>
