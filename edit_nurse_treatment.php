<?php
// Include header and database connection
include_once 'includes/header.php';
include_once 'config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
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

<div class="container mt-4">
    <div class="row">
        <!-- Main content -->
        <main class="col-12">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Edit Nurse Treatment Record</h1>
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
            
            <form method="POST" action="edit_nurse_treatment.php?id=<?php echo $treatment_id; ?>">
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
                    
                    <!-- Treatment Details -->
                    <div class="col-md-6 mb-3">
                        <div class="card">
                            <div class="card-header">
                                <h5>Treatment Details</h5>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label for="treatment_date" class="form-label">Date</label>
                                    <input type="date" class="form-control" id="treatment_date" name="treatment_date" value="<?php echo $treatment['treatment_date']; ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label for="nurse_name" class="form-label">Nurse Name</label>
                                    <input type="text" class="form-control" id="nurse_name" name="nurse_name" value="<?php echo $treatment['nurse_name']; ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label for="payment_status" class="form-label">Payment Status</label>
                                    <select class="form-select" id="payment_status" name="payment_status" required>
                                        <option value="Unpaid" <?php echo $treatment['payment_status'] == 'Unpaid' ? 'selected' : ''; ?>>Unpaid</option>
                                        <option value="Paid" <?php echo $treatment['payment_status'] == 'Paid' ? 'selected' : ''; ?>>Paid</option>
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
                                <textarea class="form-control" name="report" rows="5" placeholder="Enter report details here"><?php echo $treatment['report']; ?></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <div class="card">
                            <div class="card-header">
                                <h5>Treatment</h5>
                            </div>
                            <div class="card-body">
                                <textarea class="form-control" name="treatment" rows="5" placeholder="Enter treatment details here"><?php echo $treatment['treatment']; ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="d-grid gap-2 d-md-flex justify-content-md-end mb-4">
                    <a href="view_nurse_treatment.php?id=<?php echo $treatment_id; ?>" class="btn btn-secondary me-md-2">Cancel</a>
                    <button type="submit" class="btn btn-primary">Update Treatment Record</button>
                </div>
            </form>
        </main>
    </div>
</div>

<?php include_once 'includes/footer.php'; ?>
