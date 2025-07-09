<?php
// Start session
session_start();

// Include database configuration
require_once 'config/data.php';
// Include authentication and role functions
require_once 'config/auth.php';

// Handle form submission for deleting prescription
if (isset($_POST['delete_prescription'])) {
    $prescription_id = sanitize($_POST['prescription_id']);
    $delete_query = "DELETE FROM prescriptions WHERE id = '$prescription_id'";
    if (executeQuery($delete_query)) {
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
            <div class="row mb-4">
                <div class="col-md-6">
                    <form method="GET" action="prescriptions.php" class="d-flex">
                        <div class="input-group">
                            <input type="text" name="search" class="form-control" placeholder="Search by patient name" value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                            <button type="submit" class="btn btn-outline-secondary">
                                <i class="fas fa-search"></i>
                            </button>
                            <?php if (isset($_GET['search']) && !empty($_GET['search'])): ?>
                                <a href="prescriptions.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-times"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Prescriptions table -->
            <div class="table-responsive">
                <table class="table table-striped table-sm">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Date</th>
                            <th>Patient Name</th>
                            <th>Doctor</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        // Build query based on search
                        $search_condition = '';
                        if (isset($_GET['search']) && !empty($_GET['search'])) {
                            $search = sanitize($_GET['search']);
                            $search_condition = "AND p.name LIKE '%$search%'";
                        }
                        
                        $query = "SELECT pr.id, pr.prescription_date, p.name AS patient_name, d.name AS doctor_name
                                  FROM prescriptions pr
                                  JOIN patients p ON pr.patient_id = p.id
                                  JOIN doctors d ON pr.doctor_id = d.id
                                  WHERE 1=1 $search_condition
                                  ORDER BY pr.prescription_date DESC";
                        
                        $result = executeQuery($query);
                        
                        if ($result && $result->num_rows > 0) {
                            while ($row = $result->fetch_assoc()) {
                                echo "<tr>";
                                echo "<td>{$row['id']}</td>";
                                echo "<td>" . date('d-m-Y', strtotime($row['prescription_date'])) . "</td>";
                                echo "<td>{$row['patient_name']}</td>";
                                echo "<td>{$row['doctor_name']}</td>";
                                echo "<td>
                                        <a href='view_prescription.php?id={$row['id']}' class='btn btn-sm btn-primary me-1'>
                                            <i class='fas fa-eye'></i> View
                                        </a>";
                                        if (hasRole(['admin'])) {
                                            echo "<a href='edit_prescription.php?id={$row['id']}' class='btn btn-sm btn-warning me-1' title='Edit'><i class='fas fa-edit'></i> Edit</a>";
                                        }
                                        echo "<button type='button' class='btn btn-sm btn-danger' data-bs-toggle='modal' data-bs-target='#deleteModal{$row['id']}' title='Delete'>
                                            <i class='fas fa-trash'></i>
                                        </button>
                                        
                                        <!-- Delete Modal -->
                                        <div class='modal fade' id='deleteModal{$row['id']}' tabindex='-1' aria-labelledby='deleteModalLabel' aria-hidden='true'>
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
</body>
</html>
