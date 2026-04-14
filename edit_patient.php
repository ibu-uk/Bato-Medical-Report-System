<?php
// Start session
session_start();

// Include configuration files
require_once 'config/database.php';
require_once 'config/auth.php';
require_once 'config/timezone.php';
require_once 'config/helpers.php';

// Require login and patient-management permission
requireLogin();
if (!canManagePatients()) {
    header('Location: patient_list.php');
    exit;
}

// Get database connection
$conn = getDbConnection();

// Check if ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: patient_list.php');
    exit;
}

$patient_id = (int)$_GET['id'];
$message = '';
$messageType = '';

// Fetch patient data
$query = "SELECT * FROM patients WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $patient_id);
$stmt->execute();
$result = $stmt->get_result();
$patient = $result->fetch_assoc();

if (!$patient) {
    header('Location: patient_list.php');
    exit;
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize input using database's sanitize function
    $name = $conn->real_escape_string(trim($_POST['name'] ?? ''));
    $civil_id = $conn->real_escape_string(trim($_POST['civil_id'] ?? ''));
    $mobile = $conn->real_escape_string(trim($_POST['mobile'] ?? ''));
    $email = $conn->real_escape_string(trim($_POST['email'] ?? ''));
    $file_number = $conn->real_escape_string(trim($_POST['file_number'] ?? ''));
    $portal_username = $conn->real_escape_string(trim($_POST['portal_username'] ?? ''));
    $portal_password = $_POST['portal_password'] ?? '';
    $portal_is_active = isset($_POST['portal_is_active']) ? 1 : 0;
    
    // Validate required fields
    if (empty($name) || empty($civil_id) || empty($mobile) || empty($email) || empty($file_number)) {
        $message = 'All fields are required.';
        $messageType = 'error';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Please enter a valid email address.';
        $messageType = 'error';
    } elseif (!empty($portal_password) && empty($portal_username)) {
        $message = 'Portal username is required if portal password is set.';
        $messageType = 'error';
    } elseif (!empty($portal_username) && empty($portal_password) && empty($patient['portal_password_hash'])) {
        $message = 'Portal password is required when enabling patient login for the first time.';
        $messageType = 'error';
    } else {
        try {
            // Check if file number already exists for another patient
            $checkQuery = "SELECT id FROM patients WHERE file_number = ? AND id != ?";
            $checkStmt = $conn->prepare($checkQuery);
            $checkStmt->bind_param('si', $file_number, $patient_id);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();
            
            if ($checkResult->num_rows > 0) {
                throw new Exception("File number '$file_number' is already in use by another patient.");
            }

            // Check if portal username already exists for another patient
            if (!empty($portal_username)) {
                $checkPortalQuery = "SELECT id FROM patients WHERE portal_username = ? AND id != ?";
                $checkPortalStmt = $conn->prepare($checkPortalQuery);
                $checkPortalStmt->bind_param('si', $portal_username, $patient_id);
                $checkPortalStmt->execute();
                $checkPortalResult = $checkPortalStmt->get_result();

                if ($checkPortalResult->num_rows > 0) {
                    throw new Exception("Portal username '$portal_username' is already in use by another patient.");
                }
            }

            // Portal values
            $portal_password_hash = null;
            if (!empty($portal_username)) {
                if (!empty($portal_password)) {
                    $portal_password_hash = password_hash($portal_password, PASSWORD_DEFAULT);
                } else {
                    $portal_password_hash = $patient['portal_password_hash'] ?? null;
                }
            } else {
                $portal_is_active = 0;
            }
            
            // Update patient
            $updateQuery = "UPDATE patients SET name = ?, civil_id = ?, mobile = ?, email = ?, file_number = ?, portal_username = ?, portal_password_hash = ?, portal_is_active = ? WHERE id = ?";
            $updateStmt = $conn->prepare($updateQuery);
            if ($updateStmt === false) {
                throw new Exception("Prepare failed: " . $conn->error . " SQL: " . $updateQuery);
            }
            $updateStmt->bind_param('sssssssii', $name, $civil_id, $mobile, $email, $file_number, $portal_username, $portal_password_hash, $portal_is_active, $patient_id);
            
            if ($updateStmt->execute()) {
                // Log activity if function exists
                if (function_exists('logUserActivity')) {
                    logUserActivity('update_patient', $patient_id, 'patient', $name);
                }
                
                $message = 'Patient updated successfully.';
                $messageType = 'success';
                
                // Refresh patient data
                $stmt->execute();
                $result = $stmt->get_result();
                $patient = $result->fetch_assoc();
            } else {
                throw new Exception('Failed to update patient. Please try again.');
            }
        } catch (Exception $e) {
            $message = $e->getMessage();
            $messageType = 'error';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Patient - Bato Medical Report System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background-color: #f0f2f5;
            padding: 20px;
        }
        .form-container {
            max-width: 600px;
            margin: 20px auto;
            padding: 25px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .form-title {
            color: #0d6efd;
            margin-bottom: 25px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
        }
        .required-field::after {
            content: " *";
            color: #dc3545;
        }
        .btn-back {
            min-width: 100px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="form-container">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="h4 mb-0">
                    <i class="fas fa-user-edit text-primary me-2"></i>Edit Patient
                </h2>
                <a href="patient_list.php" class="btn btn-outline-secondary btn-back">
                    <i class="fas fa-arrow-left me-1"></i> Back
                </a>
            </div>
                
                <?php if (!empty($message)): ?>
                    <div class="alert alert-<?php echo $messageType === 'error' ? 'danger' : 'success'; ?> alert-dismissible fade show" role="alert">
                        <?php echo $message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" class="needs-validation" novalidate>
                    <div class="mb-3">
                        <label for="name" class="form-label required-field">Full Name</label>
                        <input type="text" class="form-control" id="name" name="name" value="<?php echo sanitize_output($patient['name']); ?>" required>
                        <div class="invalid-feedback">
                            Please enter patient's full name.
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="civil_id" class="form-label required-field">Civil ID</label>
                            <input type="text" class="form-control" id="civil_id" name="civil_id" value="<?php echo sanitize_output($patient['civil_id']); ?>" required>
                            <div class="invalid-feedback">
                                Please enter patient's Civil ID.
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="email" class="form-label required-field">Email</label>
                            <input type="email" class="form-control" id="email" name="email" value="<?php echo sanitize_output($patient['email'] ?? ''); ?>" required>
                            <div class="invalid-feedback">
                                Please enter a valid email address.
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="mobile" class="form-label required-field">Mobile Number</label>
                            <input type="tel" class="form-control" id="mobile" name="mobile" value="<?php echo sanitize_output($patient['mobile']); ?>" required>
                            <div class="invalid-feedback">
                                Please enter a valid mobile number.
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="file_number" class="form-label required-field">File Number</label>
                            <input type="text" class="form-control" id="file_number" name="file_number" value="<?php echo sanitize_output($patient['file_number']); ?>" required>
                            <div class="invalid-feedback">
                                Please enter patient's file number.
                            </div>
                        </div>
                    </div>

                    <hr>
                    <h6 class="mb-3">Patient Portal Login (Optional)</h6>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="portal_username" class="form-label">Portal Username</label>
                            <input type="text" class="form-control" id="portal_username" name="portal_username" value="<?php echo sanitize_output($patient['portal_username'] ?? ''); ?>" autocomplete="off">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="portal_password" class="form-label">Reset Portal Password</label>
                            <input type="password" class="form-control" id="portal_password" name="portal_password" autocomplete="new-password">
                            <small class="text-muted">Leave blank to keep current password.</small>
                        </div>
                    </div>

                    <div class="form-check mb-4">
                        <input class="form-check-input" type="checkbox" id="portal_is_active" name="portal_is_active" <?php echo !empty($patient['portal_is_active']) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="portal_is_active">
                            Enable portal login for this patient
                        </label>
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <a href="patient_list.php" class="btn btn-secondary me-md-2">
                            <i class="fas fa-arrow-left me-1"></i> Back to List
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i> Update Patient
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Form validation
    (function () {
        'use strict'
        const forms = document.querySelectorAll('.needs-validation')
        Array.from(forms).forEach(form => {
            form.addEventListener('submit', event => {
                if (!form.checkValidity()) {
                    event.preventDefault()
                    event.stopPropagation()
                }
                form.classList.add('was-validated')
            }, false)
        })
    })()
    </script>
</body>
</html>
