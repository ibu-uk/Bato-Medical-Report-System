<?php
// Start session
session_start();

// Include database configuration
require_once 'config/database.php';

// No authentication check needed

// For debugging - see form data
// Uncomment for debugging
// if ($_SERVER['REQUEST_METHOD'] === 'POST') {
//     echo "<pre>";
//     print_r($_POST);
//     echo "</pre>";
//     // Don't exit so we can see if the form submission works
// }

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate form data
    $patient_id = sanitize($_POST['patient_id']);
    $doctor_id = sanitize($_POST['doctor_id']);
    $prescription_date = sanitize($_POST['prescription_date']);
    $consultation_report = sanitize($_POST['consultation_report']);
    
    // Validate required fields
    if (empty($patient_id) || empty($doctor_id) || empty($prescription_date)) {
        $_SESSION['error'] = "Please fill in all required fields.";
    } else {
        // Process medications
        $medications_str = '';
        $medications = [];
        $medication_added = false;
        
        // Debug code removed for production
        
        if (isset($_POST['medicine_name']) && is_array($_POST['medicine_name'])) {
            for ($i = 0; $i < count($_POST['medicine_name']); $i++) {
                if (!empty($_POST['medicine_name'][$i])) {
                    $medicine_name = sanitize($_POST['medicine_name'][$i]);
                    $dose = isset($_POST['dose'][$i]) ? sanitize($_POST['dose'][$i]) : '';
                    $medications[] = $medicine_name . '|' . $dose;
                    
                    // Medication added successfully
                }
            }
        }
        
        // Convert medications array to string
        if (!empty($medications)) {
            $medications_str = implode('||', $medications);
            $medication_added = true;
        }
        
        // Medications string created successfully
        
        // Begin transaction
        try {
            // Direct database connection to ensure it works
            global $conn;
            if (!isset($conn) || !$conn) {
                $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
                if ($conn->connect_error) {
                    throw new Exception("Database connection failed: " . $conn->connect_error);
                }
            }
            
            $conn->query("START TRANSACTION");
            
            // Insert prescription with medications using prepared statement
            $stmt = $conn->prepare("INSERT INTO prescriptions (patient_id, doctor_id, prescription_date, consultation_report, medications) VALUES (?, ?, ?, ?, ?)");
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            
            $stmt->bind_param("iisss", $patient_id, $doctor_id, $prescription_date, $consultation_report, $medications_str);
            $result = $stmt->execute();
            
            if (!$result) {
                throw new Exception("Execute failed: " . $stmt->error);
            }
            
            $prescription_id = $conn->insert_id;
            $stmt->close();
            
            // If no medications were added, add the consultation report as a medication
            if (!$medication_added && !empty($consultation_report)) {
                $medications_str = "Consultation Notes|$consultation_report";
                $update_query = "UPDATE prescriptions SET medications = ? WHERE id = ?";
                $stmt = $conn->prepare($update_query);
                if ($stmt) {
                    $stmt->bind_param("si", $medications_str, $prescription_id);
                    $stmt->execute();
                    $stmt->close();
                }
            }
            
            // Commit transaction
            $conn->query("COMMIT");
            
            $_SESSION['success'] = "Prescription added successfully.";
            header('Location: prescriptions.php');
            exit;
        } catch (Exception $e) {
            // Rollback transaction on error
            executeQuery("ROLLBACK");
            $_SESSION['error'] = "Error adding prescription: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Prescription - Bato Medical Report System</title>
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
                        <a class="nav-link" href="reports.php">Reports</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="prescriptions.php"><i class="fas fa-prescription"></i> Prescriptions</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="nurse_treatments.php"><i class="fas fa-user-nurse"></i> Nurse Treatments</a>
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

<div class="container-fluid mt-4">
    <div class="row">
        <!-- Main content -->
        <main class="col-md-12 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">New Medication/Prescription Card</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <a href="prescriptions.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Prescriptions
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
            
            <form method="POST" action="add_prescription.php">
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
                            </div>
                        </div>
                    </div>
                    
                    <!-- Prescription Details -->
                    <div class="col-md-6 mb-3">
                        <div class="card">
                            <div class="card-header">
                                <h5>Prescription Details</h5>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label for="prescription_date" class="form-label">Date</label>
                                    <input type="date" class="form-control" id="prescription_date" name="prescription_date" value="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label for="doctor" class="form-label">Doctor</label>
                                    <select class="form-select" id="doctor" name="doctor_id" required>
                                        <option value="">-- Select Doctor --</option>
                                        <?php
                                        $doctors = executeQuery("SELECT id, name FROM doctors ORDER BY name");
                                        while ($doctor = $doctors->fetch_assoc()) {
                                            echo "<option value='{$doctor['id']}'>{$doctor['name']}</option>";
                                        }
                                        ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Medications Section -->
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5>Medications</h5>
                        <button type="button" class="btn btn-primary btn-sm" id="addMedicationBtn">
                            <i class="fas fa-plus"></i> Add Medication
                        </button>
                    </div>
                    <div class="card-body">
                        <div id="medicationsContainer">
                            <div class="medication-row mb-3">
                                <div class="row">
                                    <div class="col-md-6">
                                        <label class="form-label">Medicine/Product</label>
                                        <input type="text" class="form-control" name="medicine_name[]" required>
                                    </div>
                                    <div class="col-md-5">
                                        <label class="form-label">Dose</label>
                                        <input type="text" class="form-control" name="dose[]" required>
                                    </div>
                                    <div class="col-md-1 d-flex align-items-end">
                                        <button type="button" class="btn btn-danger remove-medication">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Consultation Report Section -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5>Consultation Report</h5>
                    </div>
                    <div class="card-body">
                        <textarea class="form-control" name="consultation_report" rows="5" placeholder="Enter consultation report or notes here"></textarea>
                    </div>
                </div>
                
                <div class="d-grid gap-2 d-md-flex justify-content-md-end mb-4">
                    <button type="reset" class="btn btn-secondary me-md-2">Reset</button>
                    <button type="submit" class="btn btn-primary">Save Prescription</button>
                </div>
            </form>
        </main>
    </div>
</div>

<!-- jQuery (must be loaded first) -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<!-- Bootstrap JS Bundle with Popper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
// JavaScript for dynamic medication rows
$(document).ready(function() {
    console.log('Document ready - jQuery loaded successfully');
    
    // Add medication row
    $('#addMedicationBtn').click(function() {
        console.log('Add medication button clicked');
        const newRow = `
            <div class="medication-row mb-3">
                <div class="row">
                    <div class="col-md-6">
                        <label class="form-label">Medicine/Product</label>
                        <input type="text" class="form-control" name="medicine_name[]" required>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label">Dose</label>
                        <input type="text" class="form-control" name="dose[]" required>
                    </div>
                    <div class="col-md-1 d-flex align-items-end">
                        <button type="button" class="btn btn-danger remove-medication">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            </div>
        `;
        $('#medicationsContainer').append(newRow);
    });
    
    // Remove medication row
    $(document).on('click', '.remove-medication', function() {
        console.log('Remove medication button clicked');
        // Don't remove if it's the only row
        if ($('.medication-row').length > 1) {
            $(this).closest('.medication-row').remove();
        } else {
            alert('At least one medication is required.');
        }
    });
    
    // Form submission validation
    $('form').on('submit', function(e) {
        // Check if at least one medication has been entered
        let hasValidMedication = false;
        $('input[name="medicine_name[]"]').each(function() {
            if ($(this).val().trim() !== '') {
                hasValidMedication = true;
                return false; // Break the loop
            }
        });
        
        if (!hasValidMedication) {
            alert('Please enter at least one medication');
            e.preventDefault();
            return false;
        }
    });
});
</script>

    <!-- Custom JS -->
    <script src="assets/js/script.js"></script>
</body>
</html>
