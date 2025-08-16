<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Nurse Treatment Record</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
<?php
// Start session
session_start();
// Include authentication and role helper
require_once 'config/auth.php';
// Include database connection
require_once 'config/database.php';
// Include navigation and layout
include_once 'includes/navbar.php';

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

<div class="container mt-4">
    <h2>Edit Nurse Treatment Record</h2>
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
    <?php endif; ?>
    <form method="POST" action="edit_nurse_treatment.php?id=<?php echo $treatment_id; ?>">
        <div class="mb-3">
            <label for="treatment_date" class="form-label">Treatment Date</label>
            <input type="date" class="form-control" id="treatment_date" name="treatment_date" value="<?php echo htmlspecialchars($treatment['treatment_date']); ?>" required>
        </div>
        <div class="mb-3">
            <label for="patient_id" class="form-label">Patient</label>
            <select class="form-control" id="patient_id" name="patient_id" required>
                <option value="<?php echo $patient['id']; ?>" selected><?php echo htmlspecialchars($patient['name']); ?></option>
                <!-- More patient options could be loaded here if needed -->
            </select>
        </div>
        <div class="mb-3">
            <label for="civil_id" class="form-label">Civil ID</label>
            <input type="text" class="form-control" id="civil_id" value="<?php echo htmlspecialchars($patient['civil_id']); ?>" readonly>
        </div>
        <div class="mb-3">
            <label for="mobile" class="form-label">Mobile</label>
            <input type="text" class="form-control" id="mobile" value="<?php echo htmlspecialchars($patient['mobile']); ?>" readonly>
        </div>
        <div class="mb-3">
            <label for="nurse_name" class="form-label">Nurse Name</label>
            <input type="text" class="form-control" id="nurse_name" name="nurse_name" value="<?php echo htmlspecialchars($treatment['nurse_name']); ?>" required>
        </div>
        <div class="mb-3">
            <label for="payment_status" class="form-label">Payment Status</label>
            <select class="form-control" id="payment_status" name="payment_status" required>
                <option value="Unpaid" <?php echo $treatment['payment_status'] == 'Unpaid' ? 'selected' : ''; ?>>Unpaid</option>
                <option value="Paid" <?php echo $treatment['payment_status'] == 'Paid' ? 'selected' : ''; ?>>Paid</option>
            </select>
        </div>
        <div class="mb-3">
            <label for="report" class="form-label">Report</label>
            <textarea class="form-control" id="report" name="report" rows="3" placeholder="Enter report details here..."><?php echo htmlspecialchars($treatment['report']); ?></textarea>
        </div>
        <div class="mb-3">
            <label for="treatment" class="form-label">Treatment</label>
            <textarea class="form-control" id="treatment" name="treatment" rows="3" placeholder="Enter treatment details here..."><?php echo htmlspecialchars($treatment['treatment']); ?></textarea>
        </div>
        <button type="submit" class="btn btn-primary">Save Changes</button>
        <a href="view_nurse_treatment.php?id=<?php echo $treatment_id; ?>" class="btn btn-secondary">Cancel</a>
    </form>
</div>
<?php include_once 'includes/footer.php'; ?>
