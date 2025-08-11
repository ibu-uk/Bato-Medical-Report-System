<?php
// Start session
session_start();

// Include database configuration
require_once 'config/database.php';
// Include authentication and role functions
require_once 'config/auth.php';

// Restrict access to admin and doctor roles only
if (!hasRole(['admin', 'doctor'])) {
    header('Location: index.php');
    exit;
}

// Handle form submission for deleting prescription
if (isset($_POST['delete_prescription'])) {
    $prescription_id = sanitize($_POST['prescription_id']);
    // Fetch patient name for logging before deletion
    $patient_name = '';
    $stmt = $conn->prepare("SELECT p.name FROM prescriptions pr JOIN patients p ON pr.patient_id = p.id WHERE pr.id = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("i", $prescription_id);
        $stmt->execute();
        $stmt->bind_result($patient_name_result);
        if ($stmt->fetch()) {
            $patient_name = $patient_name_result;
        }
        $stmt->close();
    }
    $delete_query = "DELETE FROM prescriptions WHERE id = '$prescription_id'";
    if (executeQuery($delete_query)) {
        // Log activity
        logUserActivity('delete_prescription', $prescription_id, null, $patient_name);
        $_SESSION['success'] = "Prescription deleted successfully.";
    } else {
        $_SESSION['error'] = "Failed to delete prescription.";
    }
    header('Location: prescriptions.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prescriptions - Bato Medical Report System</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
    <!-- Arabic Fonts CSS -->
    <link rel="stylesheet" href="assets/css/arabic-fonts.css">
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
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
                        <a class="nav-link active" href="prescriptions.php"><i class="fas fa-prescription"></i> Prescriptions</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="nurse_treatments.php"><i class="fas fa-user-nurse"></i> Nurse Treatments</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="manage_doctors.php">Doctors</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="manage_test_types.php"><i class="fas fa-vial"></i> Test Types</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="add_patient.php"><i class="fas fa-user-plus"></i> Add Patient</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

<div class="container mt-4">
    <div class="row">
        <!-- Main content -->
        <main class="col-12">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Medication/Prescription Cards</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <a href="add_prescription.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i> New Prescription
                    </a>
                </div>
            </div>
            
            <?php
            // Display success or error messages
            if (isset($_SESSION['success'])) {
                echo '<div class="alert alert-success">' . $_SESSION['success'] . '</div>';
                unset($_SESSION['success']);
            }
            if (isset($_SESSION['error'])) {
                echo '<div class="alert alert-danger">' . $_SESSION['error'] . '</div>';
                unset($_SESSION['error']);
            }
            ?>
            
            <!-- Search form -->
            <!-- Prescriptions table -->
            <div class="table-responsive">
                <table id="prescriptionsTable" class="table table-striped table-sm">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Date</th>
                            <th>Patient Name / File Number</th>
                            <th>Doctor</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        // No backend search: DataTables handles searching client-side
                        $search_condition = '';
                        
                        // Pagination variables
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10; // Prescriptions per page
$offset = ($page - 1) * $per_page;

// Get total count for pagination
$count_query = "SELECT COUNT(*) AS total
                FROM prescriptions pr
                JOIN patients p ON pr.patient_id = p.id
                JOIN doctors d ON pr.doctor_id = d.id
                WHERE 1=1 $search_condition";
$count_result = executeQuery($count_query);
$total = 0;
if ($count_result && $row = $count_result->fetch_assoc()) {
    $total = (int)$row['total'];
}
$total_pages = ceil($total / $per_page);

// Main query with LIMIT and OFFSET
$query = "SELECT pr.id, pr.prescription_date, p.name AS patient_name, p.file_number, d.name AS doctor_name
          FROM prescriptions pr
          JOIN patients p ON pr.patient_id = p.id
          JOIN doctors d ON pr.doctor_id = d.id
          WHERE 1=1 $search_condition
          ORDER BY pr.prescription_date DESC
          LIMIT $per_page OFFSET $offset";

$result = executeQuery($query);

                        if ($result && $result->num_rows > 0) {
                            while ($row = $result->fetch_assoc()) {
                                echo "<tr>";
                                echo "<td>{$row['id']}</td>";
                                echo "<td>" . date('d-m-Y', strtotime($row['prescription_date'])) . "</td>";
                                echo "<td>{$row['patient_name']} ({$row['file_number']})</td>";
                                echo "<td>{$row['doctor_name']}</td>";
                                echo "<td>
                                        <a href='view_prescription.php?id={$row['id']}' class='btn btn-sm btn-primary me-1'>
                                            <i class='fas fa-eye'></i> View
                                        </a>";
                                        if (hasRole(['admin', 'doctor'])) {
                                            echo "<a href='edit_prescription.php?id={$row['id']}' class='btn btn-sm btn-warning me-1' title='Edit'><i class='fas fa-edit'></i> Edit</a>";
                                        }
                                        if (hasRole(['admin', 'doctor'])) {
                                            echo "<button type='button' class='btn btn-sm btn-danger' data-bs-toggle='modal' data-bs-target='#deleteModal{$row['id']}' title='Delete'>
                                                <i class='fas fa-trash'></i>
                                            </button>";
                                        }
                                        
                                        // Delete Modal
                                        echo "<div class='modal fade' id='deleteModal{$row['id']}' tabindex='-1' aria-labelledby='deleteModalLabel' aria-hidden='true'>
                                            <div class='modal-dialog'>
                                                <div class='modal-content'>
                                                    <div class='modal-header'>
                                                        <h5 class='modal-title' id='deleteModalLabel'>Confirm Delete</h5>
                                                        <button type='button' class='btn-close' data-bs-dismiss='modal' aria-label='Close'></button>
                                                    </div>
                                                    <div class='modal-body'>
                                                        Are you sure you want to delete this prescription?
                                                    </div>
                                                    <div class='modal-footer'>
                                                        <button type='button' class='btn btn-secondary' data-bs-dismiss='modal'>Cancel</button>
                                                        <form method='POST'>
                                                            <input type='hidden' name='prescription_id' value='{$row['id']}'>
                                                            <button type='submit' name='delete_prescription' class='btn btn-danger'>Delete</button>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                      </td>";
                                echo "</tr>";
                            }
                        } else {
                            echo "<tr><td colspan='5' class='text-center'>No prescriptions found</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>

        </main>
    </div>
</div>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Custom JS -->
    <script src="assets/js/script.js"></script>
    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#prescriptionsTable').DataTable({
                order: [[1, 'desc']]
            });
        });
    </script>
</body>
</html>
