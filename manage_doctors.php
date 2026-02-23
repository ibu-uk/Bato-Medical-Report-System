<?php
// Start session
session_start();
// Include authentication helpers for role checking
require_once 'config/auth.php';

// Include database configuration
require_once 'config/database.php';

// Note: adding new doctors is now handled in add_doctor.php

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
    <!-- Main Content -->
    <div class="container my-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h3 class="mb-0">Doctors</h3>
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-1"></i> Back to Dashboard
            </a>
        </div>

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
            <!-- Doctors List -->
            <div class="col-12">
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
                                                // Edit button
                                                echo "<a href='edit_doctor.php?id={$row['id']}' class='btn btn-sm btn-warning me-1' title='Edit'>
                                                    <i class='fas fa-edit'></i>
                                                </a>";

                                                // Delete button
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
