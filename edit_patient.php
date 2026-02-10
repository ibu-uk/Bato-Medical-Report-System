<?php
// Start session
session_start();

// Include configuration files
require_once 'config/database.php';
require_once 'config/auth.php';
require_once 'config/timezone.php';
require_once 'config/helpers.php';

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
    $file_number = $conn->real_escape_string(trim($_POST['file_number'] ?? ''));
    
    // Validate required fields
    if (empty($name) || empty($civil_id) || empty($mobile) || empty($file_number)) {
        $message = 'All fields are required.';
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
            
            // Update patient
            $updateQuery = "UPDATE patients SET name = ?, civil_id = ?, mobile = ?, file_number = ? WHERE id = ?";
            $updateStmt = $conn->prepare($updateQuery);
            if ($updateStmt === false) {
                throw new Exception("Prepare failed: " . $conn->error . " SQL: " . $updateQuery);
            }
            $updateStmt->bind_param('ssssi', $name, $civil_id, $mobile, $file_number, $patient_id);
            
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
                            <label for="mobile" class="form-label required-field">Mobile Number</label>
                            <input type="tel" class="form-control" id="mobile" name="mobile" value="<?php echo sanitize_output($patient['mobile']); ?>" required>
                            <div class="invalid-feedback">
                                Please enter a valid mobile number.
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label for="file_number" class="form-label required-field">File Number</label>
                        <input type="text" class="form-control" id="file_number" name="file_number" value="<?php echo sanitize_output($patient['file_number']); ?>" required>
                        <div class="invalid-feedback">
                            Please enter patient's file number.
                        </div>
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
