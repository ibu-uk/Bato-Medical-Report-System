<?php
// Start session
session_start();

// Include database configuration
require_once 'config/data.php';

// Process form submission
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $name = sanitize($_POST['name']);
    $civil_id = sanitize($_POST['civil_id']);
    $mobile = sanitize($_POST['mobile']);
    $file_number = sanitize($_POST['file_number']);
    
    // Validate required fields
    if (empty($name) || empty($civil_id) || empty($mobile) || empty($file_number)) {
        $message = 'All fields are required.';
        $messageType = 'error';
    } else {
        // Check if file number already exists
        $checkQuery = "SELECT * FROM patients WHERE file_number = '$file_number'";
        $checkResult = executeQuery($checkQuery);
        
        if ($checkResult->num_rows > 0) {
            $message = 'File number already exists. Please use a different one.';
            $messageType = 'error';
        } else {
            // Insert new patient
            $insertQuery = "INSERT INTO patients (name, civil_id, mobile, file_number) 
                            VALUES ('$name', '$civil_id', '$mobile', '$file_number')";
            
            if (executeInsert($insertQuery)) {
                $message = 'Patient added successfully.';
                $messageType = 'success';
                
                // Clear form data after successful submission
                $name = $civil_id = $mobile = $file_number = '';
            } else {
                $message = 'Error adding patient.';
                $messageType = 'error';
            }
        }
    }
}

// No need to get existing file numbers
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Patient - Bato Medical Report System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
            padding-top: 20px;
        }
        .container {
            max-width: 800px;
        }
        .card {
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .card-header {
            background-color: #4e73df;
            color: white;
            border-radius: 10px 10px 0 0 !important;
        }
        .btn-primary {
            background-color: #4e73df;
            border-color: #4e73df;
        }
        .btn-primary:hover {
            background-color: #2e59d9;
            border-color: #2e59d9;
        }
        .recent-numbers {
            background-color: #f1f1f1;
            padding: 10px;
            border-radius: 5px;
            margin-top: 20px;
        }
        .alert {
            margin-top: 20px;
        }
        .arabic-input {
            direction: rtl;
            text-align: right;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-user-plus me-2"></i>Add New Patient</h3>
            </div>
            <div class="card-body">
                <?php if (!empty($message)): ?>
                    <div class="alert alert-<?php echo $messageType === 'success' ? 'success' : 'danger'; ?>">
                        <?php echo $message; ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="name" class="form-label">Patient Name (English or Arabic)</label>
                            <input type="text" class="form-control" id="name" name="name" value="<?php echo isset($name) ? htmlspecialchars($name) : ''; ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="civil_id" class="form-label">Civil ID</label>
                            <input type="text" class="form-control" id="civil_id" name="civil_id" value="<?php echo isset($civil_id) ? htmlspecialchars($civil_id) : ''; ?>">
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="mobile" class="form-label">Mobile Number</label>
                            <input type="text" class="form-control" id="mobile" name="mobile" value="<?php echo isset($mobile) ? htmlspecialchars($mobile) : ''; ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="file_number" class="form-label">File Number (Manual Assignment)</label>
                            <input type="text" class="form-control" id="file_number" name="file_number" value="<?php echo isset($file_number) ? htmlspecialchars($file_number) : ''; ?>">
                            <small class="text-muted">Assign your own file number (e.g., N-1234)</small>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-between">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Save Patient
                        </button>
                        <a href="index.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                        </a>
                    </div>
                </form>
                
                <?php if ($messageType === 'success'): ?>
                <div class="alert alert-success mt-4">
                    <i class="fas fa-check-circle me-2"></i> Patient added successfully. You can now select this patient when creating reports.
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle between English and Arabic input
        document.getElementById('name').addEventListener('input', function() {
            const text = this.value;
            const arabicPattern = /[\u0600-\u06FF]/;
            
            if (arabicPattern.test(text)) {
                this.classList.add('arabic-input');
            } else {
                this.classList.remove('arabic-input');
            }
        });
    </script>
</body>
</html>
