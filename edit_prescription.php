<?php
// Start session at the very top
session_start();

// Include database configuration
require_once 'config/data.php';
// Include authentication and role functions
require_once 'config/auth.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Prescription - Bato Medical Report System</title>
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

<?php

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Check if prescription ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: prescriptions.php');
    exit;
}

$prescription_id = sanitize($_GET['id']);

// Get prescription details
$query = "SELECT * FROM prescriptions WHERE id = '$prescription_id'";
$result = executeQuery($query);

if (!$result || $result->num_rows == 0) {
    $_SESSION['error'] = "Prescription not found.";
    header('Location: prescriptions.php');
    exit;
}

$prescription = $result->fetch_assoc();

// Get patient details
$patient_id = $prescription['patient_id'];
$query = "SELECT * FROM patients WHERE id = '$patient_id'";
$patient_result = executeQuery($query);
$patient = $patient_result->fetch_assoc();

// Get medications from the prescription record
$medications = [];
if (isset($prescription['medications']) && !empty($prescription['medications'])) {
    // Parse medications string
    $med_items = explode('||', $prescription['medications']);
    foreach ($med_items as $med_item) {
        $parts = explode('|', $med_item);
        if (count($parts) >= 2) {
            $medications[] = [
                'medicine_name' => $parts[0],
                'dose' => $parts[1]
            ];
        } elseif (count($parts) == 1 && !empty($parts[0])) {
            // Handle case where there's only a medicine name without dose
            $medications[] = [
                'medicine_name' => $parts[0],
                'dose' => ''
            ];
        }
    }
}

// Get doctors for dropdown
$query = "SELECT * FROM doctors ORDER BY name";
$doctors_result = executeQuery($query);
$doctors = [];
if ($doctors_result && $doctors_result->num_rows > 0) {
    while ($row = $doctors_result->fetch_assoc()) {
        $doctors[] = $row;
    }
}

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
        // Process medications into a single string
        $medications = [];
        $medications_str = "";
        $medication_added = false;
        
        if (isset($_POST['medicine_name']) && is_array($_POST['medicine_name'])) {
            for ($i = 0; $i < count($_POST['medicine_name']); $i++) {
                if (!empty($_POST['medicine_name'][$i])) {
                    $medicine_name = sanitize($_POST['medicine_name'][$i]);
                    $dose = isset($_POST['dose'][$i]) ? sanitize($_POST['dose'][$i]) : '';
                    $medications[] = $medicine_name . '|' . $dose;
                    $medication_added = true;
                }
            }
        }
        
        // If no medications were added, use consultation report as medication
        if (!$medication_added && !empty($consultation_report)) {
            $medications[] = $consultation_report . '|';
        }
        
        // Join medications with delimiter
        $medications_str = implode('||', $medications);
        
        // Begin transaction
        try {
            // Use direct database connection for reliability
            global $conn;
            if (!isset($conn) || !$conn) {
                $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
                if ($conn->connect_error) {
                    throw new Exception("Connection failed: " . $conn->connect_error);
                }
            }
            
            $conn->query("START TRANSACTION");
            
            // Update prescription with medications in a single column
            $stmt = $conn->prepare("UPDATE prescriptions SET 
                      patient_id = ?, 
                      doctor_id = ?, 
                      prescription_date = ?, 
                      consultation_report = ?,
                      medications = ?
                      WHERE id = ?");
                      
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            
            $stmt->bind_param("iisssi", $patient_id, $doctor_id, $prescription_date, $consultation_report, $medications_str, $prescription_id);
            $stmt->execute();
            
            if ($stmt->affected_rows < 1) {
                throw new Exception("Failed to update prescription");
            }
            
            $stmt->close();
            
            // Commit transaction
            $conn->query("COMMIT");
            
            $_SESSION['success'] = "Prescription updated successfully.";
            header('Location: view_prescription.php?id=' . $prescription_id);
            exit;
        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->query("ROLLBACK");
            $_SESSION['error'] = "Error updating prescription: " . $e->getMessage();
        }
    }
}
?>

<div class="container-fluid mt-4">
    <div class="row">
        <!-- Main content -->
        <main class="col-12 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Edit Prescription</h1>
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
            
            <form method="POST" action="edit_prescription.php?id=<?php echo $prescription_id; ?>">
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
                                        <input type="text" class="form-control" id="patient_search" placeholder="Search by name, mobile or civil ID" autocomplete="off" value="<?php echo $patient['name']; ?>">
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
                                            <option value="<?php echo $patient_id; ?>" selected><?php echo $patient['name']; ?></option>
                                            <!-- Patient options will be loaded via AJAX -->
                                        </select>
                                        <a href="add_patient.php" class="btn btn-success">
                                            <i class="fas fa-user-plus"></i> New
                                        </a>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label for="civil_id" class="form-label">Civil ID</label>
                                    <input type="text" class="form-control" id="civil_id" value="<?php echo $patient['civil_id']; ?>" readonly>
                                </div>
                                <div class="mb-3">
                                    <label for="mobile" class="form-label">Mobile</label>
                                    <input type="text" class="form-control" id="mobile" value="<?php echo $patient['mobile']; ?>" readonly>
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
                                    <input type="date" class="form-control" id="prescription_date" name="prescription_date" value="<?php echo $prescription['prescription_date']; ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label for="doctor_id" class="form-label">Doctor</label>
                                    <select class="form-select" id="doctor_id" name="doctor_id" required>
                                        <option value="">-- Select Doctor --</option>
                                        <?php foreach ($doctors as $doctor): ?>
                                            <option value="<?php echo $doctor['id']; ?>" <?php echo ($doctor['id'] == $prescription['doctor_id']) ? 'selected' : ''; ?>>
                                                <?php echo $doctor['name']; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Medications Section -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5>Medications</h5>
                                <button type="button" class="btn btn-sm btn-success" id="add_medication">
                                    <i class="fas fa-plus"></i> Add Medication
                                </button>
                            </div>
                            <div class="card-body">
                                <div id="medications_container">
                                    <?php if (!empty($medications)): ?>
                                        <?php foreach ($medications as $index => $medication): ?>
                                            <div class="row medication-row mb-3">
                                                <div class="col-md-5">
                                                    <input type="text" class="form-control" name="medicine_name[]" placeholder="Medicine/Product" value="<?php echo $medication['medicine_name']; ?>" required>
                                                </div>
                                                <div class="col-md-5">
                                                    <input type="text" class="form-control" name="dose[]" placeholder="Dose" value="<?php echo $medication['dose']; ?>" required>
                                                </div>
                                                <div class="col-md-2">
                                                    <button type="button" class="btn btn-danger remove-medication">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div class="row medication-row mb-3">
                                            <div class="col-md-5">
                                                <input type="text" class="form-control" name="medicine_name[]" placeholder="Medicine/Product" required>
                                            </div>
                                            <div class="col-md-5">
                                                <input type="text" class="form-control" name="dose[]" placeholder="Dose" required>
                                            </div>
                                            <div class="col-md-2">
                                                <button type="button" class="btn btn-danger remove-medication">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Consultation Report Section -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5>Consultation Report</h5>
                            </div>
                            <div class="card-body">
                                <textarea class="form-control" name="consultation_report" rows="5" placeholder="Enter consultation report here"><?php echo $prescription['consultation_report']; ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="d-grid gap-2 d-md-flex justify-content-md-end mb-4">
                    <a href="view_prescription.php?id=<?php echo $prescription_id; ?>" class="btn btn-secondary me-md-2">Cancel</a>
                    <button type="submit" class="btn btn-primary">Update Prescription</button>
                </div>
            </form>
        </main>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Add medication row
    document.getElementById('add_medication').addEventListener('click', function() {
        const container = document.getElementById('medications_container');
        const newRow = document.createElement('div');
        newRow.className = 'row medication-row mb-3';
        newRow.innerHTML = `
            <div class="col-md-5">
                <input type="text" class="form-control" name="medicine_name[]" placeholder="Medicine/Product" required>
            </div>
            <div class="col-md-5">
                <input type="text" class="form-control" name="dose[]" placeholder="Dose" required>
            </div>
            <div class="col-md-2">
                <button type="button" class="btn btn-danger remove-medication">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        `;
        container.appendChild(newRow);
        
        // Add event listener to the new remove button
        newRow.querySelector('.remove-medication').addEventListener('click', function() {
            container.removeChild(newRow);
        });
    });
    
    // Remove medication row
    document.querySelectorAll('.remove-medication').forEach(button => {
        button.addEventListener('click', function() {
            const row = this.closest('.medication-row');
            if (document.querySelectorAll('.medication-row').length > 1) {
                row.parentNode.removeChild(row);
            } else {
                alert('At least one medication is required.');
            }
        });
    });
});
</script>

<?php include_once 'includes/footer.php'; ?>
