<?php
// Start session
session_start();
// Include authentication helpers for role checking
require_once 'config/auth.php';

// Include database configuration
require_once 'config/database.php';

// Handle form submission for adding new doctor
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_doctor'])) {
    $name = sanitize($_POST['name']);
    $position = sanitize($_POST['position']);
    $signature_path = '';
    
    // Handle signature image upload
    if (isset($_FILES['signature']) && $_FILES['signature']['error'] === 0) {
        $target_dir = "assets/images/signatures/";
        
        // Create directory if it doesn't exist
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        
        $file_extension = pathinfo($_FILES['signature']['name'], PATHINFO_EXTENSION);
        $new_filename = uniqid('signature_') . '.' . $file_extension;
        $target_file = $target_dir . $new_filename;
        
        // Check if image file is a actual image
        $check = getimagesize($_FILES['signature']['tmp_name']);
        if ($check !== false) {
            // Upload file
            if (move_uploaded_file($_FILES['signature']['tmp_name'], $target_file)) {
                $signature_path = $target_file;
            }
        }
    }
    
    // Insert doctor into database
    $query = "INSERT INTO doctors (name, position, signature_image_path) VALUES ('$name', '$position', '$signature_path')";
    $result = executeQuery($query);
    
    if ($result) {
        $success_message = "Doctor added successfully!";
    } else {
        $error_message = "Error adding doctor. Please try again.";
    }
}

// Handle doctor deletion
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $doctor_id = sanitize($_GET['delete']);
    
    // Check if doctor is used in any reports
    $check_query = "SELECT COUNT(*) as count FROM reports WHERE doctor_id = '$doctor_id'";
    $check_result = executeQuery($check_query);
    $check_data = $check_result->fetch_assoc();
    
    if ($check_data['count'] > 0) {
        $error_message = "Cannot delete doctor. Doctor is associated with existing reports.";
    } else {
        // Get signature path to delete file
        $signature_query = "SELECT signature_image_path FROM doctors WHERE id = '$doctor_id'";
        $signature_result = executeQuery($signature_query);
        $signature_data = $signature_result->fetch_assoc();
        
        // Delete signature file if exists
        if (!empty($signature_data['signature_image_path']) && file_exists($signature_data['signature_image_path'])) {
            unlink($signature_data['signature_image_path']);
        }
        
        // Delete doctor from database
        $delete_query = "DELETE FROM doctors WHERE id = '$doctor_id'";
        $delete_result = executeQuery($delete_query);
        
        if ($delete_result) {
            $success_message = "Doctor deleted successfully!";
        } else {
            $error_message = "Error deleting doctor. Please try again.";
        }
    }
}

// Get all doctors
$query = "SELECT * FROM doctors ORDER BY name ASC";
$doctors = executeQuery($query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Doctors - Bato Medical Report System</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
    
    <!-- Print-specific styles -->
    <style type="text/css" media="print">
        .doctor-name, .doctor-position, .doctor-signature {
            color: blue !important;
            font-weight: bold !important;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="index.php">Bato Medical Report System</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="reports.php">Reports</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="manage_doctors.php">Doctors</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container my-4">
        <!-- Alerts -->
        <?php if (isset($success_message)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo $success_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo $error_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <!-- Add Doctor Form -->
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">Add New Doctor</h4>
                    </div>
                    <div class="card-body">
                        <form action="manage_doctors.php" method="POST" enctype="multipart/form-data">
                            <div class="mb-3">
                                <label for="name" class="form-label">Doctor Name</label>
                                <input type="text" class="form-control" id="name" name="name" required>
                            </div>
                            <div class="mb-3">
                                <label for="position" class="form-label">Position</label>
                                <input type="text" class="form-control" id="position" name="position" required>
                            </div>
                            <div class="mb-3">
                                <label for="signature" class="form-label">Signature Image</label>
                                <input type="file" class="form-control" id="signature" name="signature" accept="image/*">
                                <div class="form-text">Upload doctor's signature image (optional)</div>
                            </div>
                            <button type="submit" name="add_doctor" class="btn btn-primary">Add Doctor</button>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Doctors List -->
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">Doctors List</h4>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table id="doctorsTable" class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Name</th>
                                        <th>Position</th>
                                        <th>Signature</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    if ($doctors && $doctors->num_rows > 0) {
                                        while ($row = $doctors->fetch_assoc()) {
                                            echo "<tr>";
                                            echo "<td>{$row['id']}</td>";
                                            echo "<td class='doctor-name'>{$row['name']}</td>";
                                            echo "<td class='doctor-position'>{$row['position']}</td>";
                                            echo "<td class='doctor-signature'>";
                                            if (!empty($row['signature_image_path'])) {
                                                echo "<img src='{$row['signature_image_path']}' alt='Signature' style='max-height: 50px;'>";
                                            } else {
                                                echo "No signature";
                                            }
                                            echo "</td>";
                                            echo "<td>";
                                            if (hasRole(['admin'])) {
                                                echo "<button class='btn btn-sm btn-danger' onclick='deleteDoctor({$row['id']})' title='Delete'>
                                                    <i class='fas fa-trash'></i>
                                                </button>";
                                            }
                                            echo "</td>";
                                            echo "</tr>";
                                        }
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-dark text-white py-3 mt-4">
        <div class="container text-center">
            <p class="mb-0">© <?php echo date('Y'); ?> Bato Medical Report System. All rights reserved.</p>
        </div>
    </footer>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Delete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete this doctor? This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <a href="#" id="confirmDelete" class="btn btn-danger">Delete</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
    
    <script>
        $(document).ready(function() {
            $('#doctorsTable').DataTable();
        });
        
        function deleteDoctor(id) {
            $('#confirmDelete').attr('href', 'manage_doctors.php?delete=' + id);
            var deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
            deleteModal.show();
        }
    </script>
</body>
</html>
