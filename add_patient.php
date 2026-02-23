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

// Initialize variables
$message = '';
$messageType = '';
$name = $civil_id = $mobile = $file_number = '';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $name = sanitize($_POST['name'] ?? '');
    $civil_id = sanitize($_POST['civil_id'] ?? '');
    $mobile = sanitize($_POST['mobile'] ?? '');
    $file_number = sanitize($_POST['file_number'] ?? '');
    
    // Validate required fields
    if (empty($name) || empty($civil_id) || empty($mobile) || empty($file_number)) {
        $message = '<div class="alert alert-danger">All fields are required.</div>';
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
            
            // Insert new patient
            $insertQuery = "INSERT INTO patients (name, civil_id, mobile, file_number) 
                           VALUES ('$name', '$civil_id', '$mobile', '$file_number')";
            
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
            $name = $civil_id = $mobile = $file_number = '';
            
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
            <a href="index.php" class="btn btn-secondary">
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
                            <label for="mobile" class="form-label required-field">Mobile Number</label>
                            <input type="tel" class="form-control" id="mobile" name="mobile" value="<?php echo htmlspecialchars($mobile); ?>" required>
                            <div class="invalid-feedback">
                                Please enter a valid mobile number.
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label for="file_number" class="form-label required-field">File Number</label>
                        <input type="text" class="form-control" id="file_number" name="file_number" value="<?php echo htmlspecialchars($file_number); ?>" required>
                        <div class="invalid-feedback">
                            Please enter patient's file number.
                        </div>
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