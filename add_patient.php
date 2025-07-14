<?php
// Start session
session_start();

// Include database configuration
require_once 'config/database.php';

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
        // Initialize error flag
        $hasError = false;
        $errorMessages = [];
        
        // Check if file number already exists
        $checkFileQuery = "SELECT * FROM patients WHERE file_number = '$file_number'";
        $checkFileResult = executeQuery($checkFileQuery);
        
        if ($checkFileResult->num_rows > 0) {
            $errorMessages[] = "File number '$file_number' already exists";
            $hasError = true;
        }
        
        // Check if civil ID already exists
        $checkCivilQuery = "SELECT * FROM patients WHERE civil_id = '$civil_id'";
        $checkCivilResult = executeQuery($checkCivilQuery);
        
        if ($checkCivilResult->num_rows > 0) {
            $errorMessages[] = "Civil ID '$civil_id' already exists";
            $hasError = true;
        }
        
        // Check for similar names (exact match)
        $checkNameQuery = "SELECT * FROM patients WHERE name = '$name'";
        $checkNameResult = executeQuery($checkNameQuery);
        
        if ($checkNameResult->num_rows > 0) {
            $errorMessages[] = "A patient with the name '$name' already exists. Please verify if this is a duplicate record";
            $hasError = true;
        }
        
        // If any validation errors occurred
        if ($hasError) {
            $message = "<strong>Cannot add patient:</strong><br>" . implode("<br>", $errorMessages) . 
                      "<br><br><strong>Please check existing records before adding a new patient.</strong>";
            $messageType = 'error';
        } else {
            // Insert new patient if no duplicates found
            $insertQuery = "INSERT INTO patients (name, civil_id, mobile, file_number) 
                            VALUES ('$name', '$civil_id', '$mobile', '$file_number')";
            
            if (executeInsert($insertQuery)) {
                // Log the activity
                $userId = $_SESSION['user_id'] ?? 0;
                $logQuery = "INSERT INTO activity_logs (user_id, activity_type, details, timestamp) 
                           VALUES ('$userId', 'add_patient', 'Added new patient: $name (ID: $civil_id)', NOW())";
                executeQuery($logQuery);
                
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
        <div class="card mb-4">
            <div class="card-header">
                <h3><i class="fas fa-search me-2"></i>Check Existing Patients</h3>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i> Before adding a new patient, please search to check if they already exist in the system.
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-8">
                        <div class="input-group">
                            <input type="text" class="form-control" id="patient_search" placeholder="Search by name, civil ID, or file number...">
                            <button class="btn btn-primary" id="search_btn" type="button">
                                <i class="fas fa-search"></i> Search
                            </button>
                            <button class="btn btn-secondary" id="clear_search" type="button">
                                <i class="fas fa-times"></i> Clear
                            </button>
                        </div>
                        <div id="search_status" class="small text-muted mt-1">Type at least 3 characters to search</div>
                    </div>
                </div>
                
                <div id="search_results" class="mt-3" style="display: none;">
                    <h5>Search Results:</h5>
                    <div class="table-responsive">
                        <table class="table table-striped table-bordered">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Civil ID</th>
                                    <th>File Number</th>
                                    <th>Mobile</th>
                                </tr>
                            </thead>
                            <tbody id="results_body">
                                <!-- Results will be populated here -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
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
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $(document).ready(function() {
            // Toggle between English and Arabic input
            $('#name').on('input', function() {
                const text = $(this).val();
                const arabicPattern = /[\u0600-\u06FF]/;
                
                if (arabicPattern.test(text)) {
                    $(this).addClass('arabic-input');
                } else {
                    $(this).removeClass('arabic-input');
                }
            });
            
            // Real-time validation for civil ID
            $('#civil_id').on('input', function() {
                const civilId = $(this).val().trim();
                
                if (civilId.length >= 3) {
                    // Check if civil ID exists
                    $.ajax({
                        url: 'includes/check_patient_exists.php',
                        type: 'POST',
                        data: { field: 'civil_id', value: civilId },
                        dataType: 'json',
                        success: function(response) {
                            if (response.exists) {
                                $('#civil_id').addClass('is-invalid');
                                if (!$('#civil_id_feedback').length) {
                                    $('#civil_id').after('<div id="civil_id_feedback" class="invalid-feedback">This Civil ID already exists in the system.</div>');
                                }
                            } else {
                                $('#civil_id').removeClass('is-invalid');
                                $('#civil_id_feedback').remove();
                            }
                        }
                    });
                }
            });
            
            // Real-time validation for file number
            $('#file_number').on('input', function() {
                const fileNumber = $(this).val().trim();
                
                if (fileNumber.length >= 3) {
                    // Check if file number exists
                    $.ajax({
                        url: 'includes/check_patient_exists.php',
                        type: 'POST',
                        data: { field: 'file_number', value: fileNumber },
                        dataType: 'json',
                        success: function(response) {
                            if (response.exists) {
                                $('#file_number').addClass('is-invalid');
                                if (!$('#file_number_feedback').length) {
                                    $('#file_number').after('<div id="file_number_feedback" class="invalid-feedback">This File Number already exists in the system.</div>');
                                }
                            } else {
                                $('#file_number').removeClass('is-invalid');
                                $('#file_number_feedback').remove();
                            }
                        }
                    });
                }
            });
            
            // Search functionality
            let searchTimeout;
            
            $('#patient_search').on('input', function() {
                const searchTerm = $(this).val().trim();
                const statusElement = $('#search_status');
                
                // Clear any pending timeout
                clearTimeout(searchTimeout);
                
                if (searchTerm.length === 0) {
                    statusElement.text('Type at least 3 characters to search');
                    $('#search_results').hide();
                    return;
                }
                
                if (searchTerm.length < 3) {
                    statusElement.text('Type at least 3 characters to search');
                    return;
                }
                
                statusElement.text('Searching...');
                
                // Set a timeout to avoid making requests on every keystroke
                searchTimeout = setTimeout(function() {
                    searchPatients(searchTerm);
                }, 500);
            });
            
            // Search button click
            $('#search_btn').click(function() {
                const searchTerm = $('#patient_search').val().trim();
                
                if (searchTerm.length >= 3) {
                    searchPatients(searchTerm);
                } else {
                    $('#search_status').text('Type at least 3 characters to search');
                }
            });
            
            // Clear search button
            $('#clear_search').click(function() {
                $('#patient_search').val('');
                $('#search_status').text('Type at least 3 characters to search');
                $('#search_results').hide();
            });
            
            // Function to search patients
            function searchPatients(searchTerm) {
                $.ajax({
                    url: 'includes/search_patients.php',
                    type: 'GET',
                    data: { search: searchTerm },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success && response.patients.length > 0) {
                            // Clear existing results
                            $('#results_body').empty();
                            
                            // Add patient results
                            $.each(response.patients, function(index, patient) {
                                const row = `<tr>
                                    <td>${patient.name}</td>
                                    <td>${patient.civil_id}</td>
                                    <td>${patient.file_number}</td>
                                    <td>${patient.mobile}</td>
                                </tr>`;
                                $('#results_body').append(row);
                            });
                            
                            // Show results
                            $('#search_results').show();
                            $('#search_status').text(`Found ${response.patients.length} matching patients`);
                        } else {
                            // No results
                            $('#search_results').hide();
                            $('#search_status').text('No matching patients found');
                        }
                    },
                    error: function() {
                        $('#search_status').text('Error searching patients');
                        $('#search_results').hide();
                    }
                });
            }
            
            // Form submission validation
            $('form').submit(function(e) {
                let hasErrors = false;
                
                // Check for invalid fields
                if ($('.is-invalid').length > 0) {
                    e.preventDefault();
                    alert('Please fix the errors before submitting the form.');
                    return false;
                }
                
                // Final validation
                const name = $('#name').val().trim();
                const civilId = $('#civil_id').val().trim();
                const mobile = $('#mobile').val().trim();
                const fileNumber = $('#file_number').val().trim();
                
                if (!name || !civilId || !mobile || !fileNumber) {
                    e.preventDefault();
                    alert('All fields are required.');
                    return false;
                }
            });
        });
    </script>
</body>
</html>
