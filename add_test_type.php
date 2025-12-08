<?php
// Include database configuration
require_once 'config/database.php';

// Start session if needed
session_start();

// Include authentication helpers for role checking
require_once 'config/auth.php';

// Check if user has permission to add test types
if (!hasRole(['admin', 'lab_technician'])) {
    $_SESSION['error'] = 'You do not have permission to add test types.';
    header('Location: manage_test_types.php');
    exit();
}

// Initialize variables
$message = '';
$messageType = '';
$errors = [];
$formData = [
    'name' => '',
    'unit' => '',
    'normal_range' => ''
];

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Check if form is submitted for adding new test type
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errors[] = 'Invalid form submission. Please try again.';
    } else {
        // Sanitize and validate input data
        $formData = [
            'name' => trim($_POST['name'] ?? ''),
            'unit' => trim($_POST['unit'] ?? ''),
            'normal_range' => trim($_POST['normal_range'] ?? '')
        ];
        
        // Validate required fields
        if (empty($formData['name'])) {
            $errors[] = 'Test name is required.';
        } elseif (strlen($formData['name']) > 100) {
            $errors[] = 'Test name must be less than 100 characters.';
        }
        
        if (empty($formData['unit'])) {
            $errors[] = 'Unit is required.';
        } elseif (strlen($formData['unit']) > 50) {
            $errors[] = 'Unit must be less than 50 characters.';
        }
        
        if (empty($formData['normal_range'])) {
            $errors[] = 'Normal range is required.';
        } elseif (strlen($formData['normal_range']) > 100) {
            $errors[] = 'Normal range must be less than 100 characters.';
        }
        
        // Check for duplicate test name (case-insensitive)
        if (empty($errors)) {
            $checkQuery = "SELECT id FROM test_types WHERE LOWER(name) = LOWER(?)";
            $stmt = $conn->prepare($checkQuery);
            $stmt->bind_param("s", $formData['name']);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $errors[] = 'A test with this name already exists.';
            }
            $stmt->close();
        }
        
        // If no errors, proceed with insertion
        if (empty($errors)) {
            $insertQuery = "INSERT INTO test_types (name, unit, normal_range) VALUES (?, ?, ?)";
            $stmt = $conn->prepare($insertQuery);
            $stmt->bind_param("sss", 
                $formData['name'],
                $formData['unit'],
                $formData['normal_range']
            );
            
            if ($stmt->execute()) {
                $_SESSION['success'] = 'Test type added successfully.';
                header('Location: manage_test_types.php');
                exit();
            } else {
                $errors[] = 'Error adding test type: ' . $conn->error;
            }
            $stmt->close();
        }
    }
    
    // If we got here, there were errors
    $message = implode('<br>', $errors);
    $messageType = 'danger';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Test Type - Bato Medical Report System</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --header-bg: #6c757d;
            --header-text: #ffffff;
        }
        
        body {
            background-color: #f8f9fa;
            padding: 20px;
        }
        
        .form-container {
            max-width: 600px;
            margin: 20px auto;
            background: white;
            padding: 2rem;
            border-radius: 0.5rem;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }
        
        .form-title {
            color: var(--header-bg);
            margin-bottom: 1.5rem;
            text-align: center;
            font-weight: 600;
        }
        
        .required-field::after {
            content: " *";
            color: #dc3545;
        }
        
        .btn-primary {
            background-color: var(--header-bg);
            border-color: var(--header-bg);
        }
        
        .btn-primary:hover {
            background-color: #5a6268;
            border-color: #545b62;
        }
        
        .form-control:focus {
            border-color: #86b7fe;
            box-shadow: 0 0 0 0.25rem rgba(108, 117, 125, 0.25);
        }
        
        .back-link {
            color: var(--header-bg);
            text-decoration: none;
        }
        
        .back-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <a href="index.php" class="back-link">
                <i class="fas fa-arrow-left me-1"></i> Back to Dashboard
            </a>
        </div>
        
        <div class="form-container">
            <h2 class="form-title">
                <i class="fas fa-plus-circle me-2"></i>Add New Test Type
            </h2>
            
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                    <?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <form method="POST" action="add_test_type.php" class="needs-validation" novalidate>
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                
                <div class="mb-4">
                    <label for="name" class="form-label required-field">Test Name</label>
                    <input type="text" 
                           class="form-control <?php echo (isset($errors) && in_array('Test name is required.', $errors)) ? 'is-invalid' : ''; ?>" 
                           id="name" 
                           name="name" 
                           value="<?php echo htmlspecialchars($formData['name']); ?>" 
                           required
                           maxlength="100">
                    <div class="invalid-feedback">
                        Please provide a valid test name (max 100 characters).
                    </div>
                    <div class="form-text">Enter the name of the test (e.g., Complete Blood Count, Lipid Profile)</div>
                </div>
                
                <div class="mb-4">
                    <label for="unit" class="form-label required-field">Unit of Measurement</label>
                    <input type="text" 
                           class="form-control <?php echo (isset($errors) && in_array('Unit is required.', $errors)) ? 'is-invalid' : ''; ?>" 
                           id="unit" 
                           name="unit" 
                           value="<?php echo htmlspecialchars($formData['unit']); ?>" 
                           required
                           maxlength="50">
                    <div class="invalid-feedback">
                        Please provide a unit of measurement (max 50 characters).
                    </div>
                    <div class="form-text">Enter the unit (e.g., mg/dL, U/L, x10³/µL)</div>
                </div>
                
                <div class="mb-4">
                    <label for="normal_range" class="form-label required-field">Normal Range</label>
                    <input type="text" 
                           class="form-control <?php echo (isset($errors) && in_array('Normal range is required.', $errors)) ? 'is-invalid' : ''; ?>" 
                           id="normal_range" 
                           name="normal_range" 
                           value="<?php echo htmlspecialchars($formData['normal_range']); ?>" 
                           required
                           maxlength="100">
                    <div class="invalid-feedback">
                        Please provide the normal range for this test (max 100 characters).
                    </div>
                    <div class="form-text">Enter the reference range (e.g., 0.0-1.0, 120-200, Negative)</div>
                </div>
                
                <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                    <a href="index.php" class="btn btn-outline-secondary me-md-2">
                        <i class="fas fa-times me-1"></i> Cancel
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i> Save Test Type
                    </button>
                </div>
            </form>
        </div>
    </div>

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
        Array.prototype.slice.call(forms).forEach(function (form) {
            form.addEventListener('submit', function (event) {
                if (!form.checkValidity()) {
                    event.preventDefault()
                    event.stopPropagation()
                }
                form.classList.add('was-validated')
            }, false)
        })
        
        // Auto-format normal range input
        const normalRangeInput = document.getElementById('normal_range');
        if (normalRangeInput) {
            normalRangeInput.addEventListener('input', function(e) {
                // Remove any non-numeric, dash, or decimal characters
                this.value = this.value.replace(/[^\d\-.]/g, '');
            });
        }
    })()
    </script>
</body>
</html>