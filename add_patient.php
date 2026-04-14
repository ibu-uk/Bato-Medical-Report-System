<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session
session_start();

// Include database configuration
require_once 'config/database.php';
require_once 'config/auth.php';
require_once 'config/timezone.php';

// Require login and patient management permission
requireLogin();
if (!canManagePatients()) {
    header('Location: dashboard.php');
    exit;
}

// Initialize variables
$message = '';
$messageType = '';
$name = $civil_id = $mobile = $email = $file_number = '';
$portal_username = '';
$portal_is_active = 1;

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $name = sanitize($_POST['name'] ?? '');
    $civil_id = sanitize($_POST['civil_id'] ?? '');
    $mobile = sanitize($_POST['mobile'] ?? '');
    $email = sanitize(trim($_POST['email'] ?? ''));
    $file_number = sanitize($_POST['file_number'] ?? '');
    $portal_username = sanitize(trim($_POST['portal_username'] ?? ''));
    $portal_password = $_POST['portal_password'] ?? '';
    $portal_is_active = isset($_POST['portal_is_active']) ? 1 : 0;

    // Validate required fields
    if (empty($name) || empty($civil_id) || empty($mobile) || empty($file_number)) {
        $message = '<div class="alert alert-danger">All fields are required.</div>';
        $messageType = 'error';
    } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = '<div class="alert alert-danger">Please enter a valid email address.</div>';
        $messageType = 'error';
    } elseif (!empty($portal_password) && empty($portal_username)) {
        $message = '<div class="alert alert-danger">Portal username is required if portal password is set.</div>';
        $messageType = 'error';
    } elseif (!empty($portal_username) && empty($portal_password)) {
        $message = '<div class="alert alert-danger">Portal password is required when enabling patient login.</div>';
        $messageType = 'error';
    } else {
        try {
            // Check if file number already exists
            $checkFileQuery = "SELECT * FROM patients WHERE file_number = '$file_number'";
            $checkFileResult = executeQuery($checkFileQuery);

            if ($checkFileResult->num_rows > 0) {
                throw new Exception("File number '$file_number' already exists");
            }

            // Check if civil ID already exists
            $checkCivilQuery = "SELECT * FROM patients WHERE civil_id = '$civil_id'";
            $checkCivilResult = executeQuery($checkCivilQuery);

            if ($checkCivilResult->num_rows > 0) {
                throw new Exception("Civil ID '$civil_id' already exists");
            }

            // Check if mobile already exists
            $checkMobileQuery = "SELECT * FROM patients WHERE mobile = '$mobile'";
            $checkMobileResult = executeQuery($checkMobileQuery);

            if ($checkMobileResult && $checkMobileResult->num_rows > 0) {
                throw new Exception("Mobile number '$mobile' already exists");
            }

            // Ensure patients.email column exists for OTP registration flow
            $emailColumnResult = executeQuery("SHOW COLUMNS FROM patients LIKE 'email'");
            if (!$emailColumnResult || $emailColumnResult->num_rows === 0) {
                $alterResult = executeQuery("ALTER TABLE patients ADD COLUMN email VARCHAR(255) NULL AFTER mobile");
                if (!$alterResult) {
                    throw new Exception('Failed to add email column to patients table.');
                }
            }

            // Check if portal username already exists
            if (!empty($portal_username)) {
                $checkPortalQuery = "SELECT id FROM patients WHERE portal_username = '$portal_username'";
                $checkPortalResult = executeQuery($checkPortalQuery);

                if ($checkPortalResult && $checkPortalResult->num_rows > 0) {
                    throw new Exception("Portal username '$portal_username' is already in use");
                }
            }

            // Build portal values
            $portalUsernameSql = 'NULL';
            $portalPasswordHashSql = 'NULL';
            $portalActiveSql = 0;

            if (!empty($portal_username)) {
                $portalPasswordHash = sanitize(password_hash($portal_password, PASSWORD_DEFAULT));
                $portalUsernameSql = "'$portal_username'";
                $portalPasswordHashSql = "'$portalPasswordHash'";
                $portalActiveSql = $portal_is_active ? 1 : 0;
            }

            // Insert new patient
            $insertQuery = "INSERT INTO patients (name, civil_id, mobile, email, file_number, portal_username, portal_password_hash, portal_is_active) 
                           VALUES ('$name', '$civil_id', '$mobile', '$email', '$file_number', $portalUsernameSql, $portalPasswordHashSql, $portalActiveSql)";

            $patient_id = executeInsert($insertQuery);

            // Log activity if function exists
            if (function_exists('logUserActivity')) {
                logUserActivity('add_patient', $patient_id, 'patient', $name);
            }

            $message = '<div class="alert alert-success">
                <i class="fas fa-check-circle me-2"></i> Patient added successfully.
            </div>';
            $messageType = 'success';

            // Clear form
            $_POST = [];
            $name = $civil_id = $mobile = $email = $file_number = '';
            $portal_username = '';
            $portal_is_active = 1;

        } catch (Exception $e) {
            $message = '<div class="alert alert-danger">
                <i class="fas fa-exclamation-circle me-2"></i> ' . htmlspecialchars($e->getMessage()) . '
            </div>';
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
    <title>Add New Patient - Bato Medical Report System</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .required-field::after {
            content: " *";
            color: red;
        }
    </style>
</head>
<body>
    <div class="container my-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h3 class="mb-0">Add New Patient</h3>
            <a href="dashboard.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-1"></i> Back to Dashboard
            </a>
        </div>

        <?php if (!empty($message)): ?>
            <div class="alert <?php echo $messageType === 'error' ? 'alert-danger' : 'alert-success'; ?> alert-dismissible fade show" role="alert">
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Patient Details</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="" class="needs-validation" novalidate>
                    <div class="mb-3">
                        <label for="name" class="form-label required-field">Full Name</label>
                        <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($name); ?>" required>
                        <div class="invalid-feedback">
                            Please enter patient's full name.
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="civil_id" class="form-label required-field">Civil ID</label>
                            <input type="text" class="form-control" id="civil_id" name="civil_id" value="<?php echo htmlspecialchars($civil_id); ?>" required>
                            <div class="invalid-feedback">
                                Please enter patient's Civil ID.
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="email" class="form-label">Email (Optional)</label>
                            <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>">
                            <div class="invalid-feedback">
                                Please enter a valid email address.
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="mobile" class="form-label required-field">Mobile Number</label>
                            <input type="tel" class="form-control" id="mobile" name="mobile" value="<?php echo htmlspecialchars($mobile); ?>" required>
                            <div class="invalid-feedback">
                                Please enter a valid mobile number.
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="file_number" class="form-label required-field">File Number</label>
                            <input type="text" class="form-control" id="file_number" name="file_number" value="<?php echo htmlspecialchars($file_number); ?>" required>
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
                            <input type="text" class="form-control" id="portal_username" name="portal_username" value="<?php echo htmlspecialchars($portal_username); ?>" autocomplete="off">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="portal_password" class="form-label">Portal Password</label>
                            <input type="password" class="form-control" id="portal_password" name="portal_password" autocomplete="new-password">
                            <small class="text-muted">Required only if setting portal username.</small>
                        </div>
                    </div>

                    <div class="form-check mb-4">
                        <input class="form-check-input" type="checkbox" id="portal_is_active" name="portal_is_active" <?php echo $portal_is_active ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="portal_is_active">
                            Enable portal login for this patient
                        </label>
                    </div>

                    <div class="d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i> Save Patient
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <footer class="bg-dark text-white py-3 mt-4">
        <div class="container text-center">
            <p class="mb-0"> 2024 Bato Medical Report System. All rights reserved.</p>
        </div>
    </footer>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Form Validation -->
    <script>
    // Client-side form validation
    (function () {
        'use strict'
        
        // Fetch all the forms we want to apply custom Bootstrap validation styles to
        var forms = document.querySelectorAll('.needs-validation')
        
        // Loop over them and prevent submission
        Array.prototype.slice.call(forms)
            .forEach(function (form) {
                form.addEventListener('submit', function (event) {
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