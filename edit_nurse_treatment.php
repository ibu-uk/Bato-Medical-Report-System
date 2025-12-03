<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Nurse Treatment Record - Bato Medical Report System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
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
        .form-control, .form-select {
            border-radius: 6px;
            padding: 0.5rem 0.75rem;
        }
        textarea.form-control {
            min-height: 100px;
        }
        .action-buttons {
            margin-top: 1.5rem;
            padding-top: 1rem;
            border-top: 1px solid #eee;
        }
    </style>
</head>
<body>
<?php
// Start session
session_start();
// Include authentication and role helper
require_once 'config/auth.php';
// Include database connection
require_once 'config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Only allow admin or nurse users to access this page
if (!hasRole(['admin', 'nurse'])) {
    $_SESSION['error'] = "You do not have permission to edit treatment records.";
    header('Location: nurse_treatments.php');
    exit;
}
// Check if treatment ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: nurse_treatments.php');
    exit;
}

$treatment_id = sanitize($_GET['id']);

// Get treatment details
$query = "SELECT * FROM nurse_treatments WHERE id = '$treatment_id'";
$result = executeQuery($query);

if (!$result || $result->num_rows == 0) {
    $_SESSION['error'] = "Treatment record not found.";
    header('Location: nurse_treatments.php');
    exit;
}

$treatment = $result->fetch_assoc();

// Get patient details
$patient_id = $treatment['patient_id'];
$query = "SELECT * FROM patients WHERE id = '$patient_id'";
$patient_result = executeQuery($query);
$patient = $patient_result->fetch_assoc();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate form data
    $patient_id = sanitize($_POST['patient_id']);
    $treatment_date = sanitize($_POST['treatment_date']);
    $nurse_name = sanitize($_POST['nurse_name']);
    $report = sanitize($_POST['report']);
    $treatment_text = sanitize($_POST['treatment']);
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
            
            // Update treatment record using prepared statement
            $stmt = $conn->prepare("UPDATE nurse_treatments SET 
                      patient_id = ?, 
                      treatment_date = ?, 
                      nurse_name = ?, 
                      report = ?, 
                      treatment = ?, 
                      payment_status = ? 
                      WHERE id = ?");
                      
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }
            
            $stmt->bind_param("isssssi", $patient_id, $treatment_date, $nurse_name, $report, $treatment_text, $payment_status, $treatment_id);
            $stmt->execute();
            
            if ($stmt->affected_rows < 0) { // Note: affected_rows can be 0 if no changes were made
                throw new Exception("Failed to update treatment record");
            }
            
            $stmt->close();
            
            // Commit transaction
            $conn->query("COMMIT");
            
            // Log activity
            logUserActivity('edit_nurse_treatment', $treatment_id, null, $patient['name']);
            $_SESSION['success'] = "Treatment record updated successfully.";
            header('Location: view_nurse_treatment.php?id=' . $treatment_id);
            exit;
        } catch (Exception $e) {
            // Rollback transaction on error
            if (isset($conn)) {
                $conn->query("ROLLBACK");
            }
            $_SESSION['error'] = "Error updating treatment record: " . $e->getMessage();
        }
    }
}
?>

<div class="container">
    <div class="row mb-4">
        <div class="col-12">
            <a href="nurse_treatments.php" class="btn btn-secondary btn-back">
                <i class="fas fa-arrow-left me-2"></i>Back to Treatments
            </a>
            <h2 class="d-inline-block ms-2">Edit Treatment Record</h2>
        </div>
    </div>
    
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0"><i class="fas fa-edit me-2"></i>Treatment Details</h5>
        </div>
        <div class="card-body">
            <form method="POST" action="edit_nurse_treatment.php?id=<?php echo $treatment_id; ?>">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="treatment_date" class="form-label">Treatment Date</label>
                        <input type="date" class="form-control" id="treatment_date" name="treatment_date" 
                               value="<?php echo htmlspecialchars($treatment['treatment_date']); ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="patient_id" class="form-label">Patient</label>
                        <select class="form-select" id="patient_id" name="patient_id" required>
                            <option value="<?php echo $patient['id']; ?>" selected>
                                <?php echo htmlspecialchars($patient['name']); ?>
                            </option>
                        </select>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="civil_id" class="form-label">Civil ID</label>
                        <input type="text" class="form-control" id="civil_id" 
                               value="<?php echo htmlspecialchars($patient['civil_id']); ?>" readonly>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="mobile" class="form-label">Mobile</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-phone"></i></span>
                            <input type="text" class="form-control" id="mobile" 
                                   value="<?php echo htmlspecialchars($patient['mobile']); ?>" readonly>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="nurse_name" class="form-label">Nurse Name</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-user-nurse"></i></span>
                            <input type="text" class="form-control" id="nurse_name" name="nurse_name" 
                                   value="<?php echo htmlspecialchars($treatment['nurse_name']); ?>" required>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="payment_status" class="form-label">Payment Status</label>
                        <select class="form-select" id="payment_status" name="payment_status" required>
                            <option value="Unpaid" <?php echo $treatment['payment_status'] == 'Unpaid' ? 'selected' : ''; ?>>
                                <i class="fas fa-times-circle text-danger me-2"></i>Unpaid
                            </option>
                            <option value="Paid" <?php echo $treatment['payment_status'] == 'Paid' ? 'selected' : ''; ?>>
                                <i class="fas fa-check-circle text-success me-2"></i>Paid
                            </option>
                        </select>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="report" class="form-label">Report</label>
                    <textarea class="form-control" id="report" name="report" rows="3" 
                              placeholder="Enter report details here..."><?php echo htmlspecialchars($treatment['report']); ?></textarea>
                </div>

                <div class="mb-3">
                    <label for="treatment" class="form-label">Treatment</label>
                    <textarea class="form-control" id="treatment" name="treatment" rows="4" 
                              placeholder="Enter treatment details here..."><?php echo htmlspecialchars($treatment['treatment']); ?></textarea>
                </div>

                <div class="action-buttons">
                    <button type="submit" class="btn btn-primary px-4">
                        <i class="fas fa-save me-2"></i>Save Changes
                    </button>
                    <a href="view_nurse_treatment.php?id=<?php echo $treatment_id; ?>" class="btn btn-outline-secondary ms-2">
                        <i class="fas fa-times me-2"></i>Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- JavaScript Libraries -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
