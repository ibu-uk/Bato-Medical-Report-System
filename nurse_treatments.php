<?php
// Start session
session_start();

// Include database configuration
require_once 'config/database.php';
// Include authentication and role helper
require_once 'config/auth.php';

// Handle form submission for deleting treatment
if (isset($_POST['delete_treatment'])) {
    if (!hasRole(['admin'])) {
        $_SESSION['error'] = "You do not have permission to delete treatment records.";
        header('Location: nurse_treatments.php');
        exit;
    }
    $treatment_id = sanitize($_POST['treatment_id']);
    // Fetch patient name for logging before deletion
    $patient_name = '';
    global $conn;
if (!isset($conn) || !$conn) {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        $_SESSION['error'] = "Database connection failed: " . $conn->connect_error;
        header('Location: nurse_treatments.php');
        exit;
    }
}
$stmt = $conn->prepare("SELECT p.name FROM nurse_treatments nt JOIN patients p ON nt.patient_id = p.id WHERE nt.id = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("i", $treatment_id);
        $stmt->execute();
        $stmt->bind_result($patient_name_result);
        if ($stmt->fetch()) {
            $patient_name = $patient_name_result;
        }
        $stmt->close();
    }
    $delete_query = "DELETE FROM nurse_treatments WHERE id = '$treatment_id'";
    if (executeQuery($delete_query)) {
        // Log activity
        logUserActivity('delete_nurse_treatment', $treatment_id, null, $patient_name);
        $_SESSION['success'] = "Treatment record deleted successfully.";
    } else {
        $_SESSION['error'] = "Failed to delete treatment record.";
    }
    header('Location: nurse_treatments.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nurse Treatments - Bato Medical Report System</title>
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
                        <a class="nav-link" href="prescriptions.php"><i class="fas fa-prescription"></i> Prescriptions</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="nurse_treatments.php"><i class="fas fa-user-nurse"></i> Nurse Treatments</a>
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
                <h1 class="h2">Nurse Treatment Records</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <a href="add_nurse_treatment.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i> New Treatment Record
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
            <!-- Treatments table -->
            <div class="table-responsive">
                <table id="nurseTreatmentsTable" class="table table-striped table-sm">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Date</th>
                            <th>Patient Name / File Number</th>
                            <th>Nurse Name</th>
                            <th>Payment Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        // No backend search: DataTables handles searching client-side
                        $search_condition = '';
                        
                        $query = "SELECT nt.id, nt.treatment_date, p.name AS patient_name, p.file_number, nt.nurse_name, nt.payment_status
                                  FROM nurse_treatments nt
                                  JOIN patients p ON nt.patient_id = p.id
                                  WHERE 1=1 $search_condition
                                  ORDER BY nt.treatment_date DESC";
                        
                        $result = executeQuery($query);
                        
                        if ($result && $result->num_rows > 0) {
                            while ($row = $result->fetch_assoc()) {
                                $payment_badge = $row['payment_status'] == 'Paid' ? 
                                    '<span class="badge bg-success">Paid</span>' : 
                                    '<span class="badge bg-warning text-dark">Unpaid</span>';
                                
                                echo "<tr>";
                                echo "<td>{$row['id']}</td>";
                                echo "<td>" . date('d-m-Y', strtotime($row['treatment_date'])) . "</td>";
                                echo "<td>{$row['patient_name']} ({$row['file_number']})</td>";
                                echo "<td>{$row['nurse_name']}</td>";
                                echo "<td>$payment_badge</td>";
                                echo "<td>
                                        <a href='view_nurse_treatment.php?id={$row['id']}' class='btn btn-sm btn-info me-1' title='View'>
                                            <i class='fas fa-eye'></i>
                                        </a>";
                                        if (hasRole(['admin', 'nurse'])) {
                                            echo "<a href='edit_nurse_treatment.php?id={$row['id']}' class='btn btn-sm btn-warning me-1' title='Edit'><i class='fas fa-edit'></i> Edit</a>";
                                        }
                                        if (hasRole(['admin'])) {
                                            echo "<button type='button' class='btn btn-sm btn-danger' data-bs-toggle='modal' data-bs-target='#deleteModal{$row['id']}' title='Delete'>
                                                <i class='fas fa-trash'></i>
                                            </button>";
                                        }
                                      echo "
                                      <!-- Delete Modal -->
                                        <div class='modal fade' id='deleteModal{$row['id']}' tabindex='-1' aria-labelledby='deleteModalLabel' aria-hidden='true'>
                                            <div class='modal-dialog'>
                                                <div class='modal-content'>
                                                    <div class='modal-header'>
                                                        <h5 class='modal-title' id='deleteModalLabel'>Confirm Delete</h5>
                                                        <button type='button' class='btn-close' data-bs-dismiss='modal' aria-label='Close'></button>
                                                    </div>
                                                    <div class='modal-body'>
                                                        Are you sure you want to delete this treatment record?
                                                    </div>
                                                    <div class='modal-footer'>
                                                        <button type='button' class='btn btn-secondary' data-bs-dismiss='modal'>Cancel</button>
                                                        <form method='POST'>
                                                            <input type='hidden' name='treatment_id' value='{$row['id']}'>
                                                            <button type='submit' name='delete_treatment' class='btn btn-danger'>Delete</button>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                      </td>";
                                echo "</tr>";
                            }
                        } else {
                            echo "<tr><td colspan='6' class='text-center'>No treatment records found</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
</div>

<?php include_once 'includes/footer.php'; ?>
    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#nurseTreatmentsTable').DataTable({
                order: [[1, 'desc']]
            });
        });
    </script>
