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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
    <style>
        body {
            background-color: #f8f9fa;
            padding: 20px;
        }
        .card {
            border: none;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            margin-bottom: 20px;
        }
        .card-header {
            border-bottom: 1px solid rgba(0,0,0,.125);
            background-color: #f8f9fa;
        }
        .btn-back {
            margin-right: 10px;
        }
        .table-responsive {
            margin-top: 20px;
        }
        .action-buttons .btn {
            margin: 0 2px;
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
        }
        .action-buttons .btn-outline-primary {
            color: #0d6efd;
            border-color: #0d6efd;
        }
        .action-buttons .btn-outline-primary:hover {
            background-color: #0d6efd;
            color: white;
        }
        .action-buttons .btn-outline-warning {
            color: #ffc107;
            border-color: #ffc107;
        }
        .action-buttons .btn-outline-warning:hover {
            background-color: #ffc107;
            color: #000;
        }
        .action-buttons .btn-outline-danger {
            color: #dc3545;
            border-color: #dc3545;
        }
        .action-buttons .btn-outline-danger:hover {
            background-color: #dc3545;
            color: white;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row mb-3">
            <div class="col-12">
                <a href="index.php" class="btn btn-secondary btn-back">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
                <h2 class="d-inline-block">Nurse Treatments</h2>
            </div>
        </div>

<div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">All Treatment Records</h5>
                        <a href="add_nurse_treatment.php" class="btn btn-primary">
                            <i class="fas fa-plus"></i> New Treatment Record
                        </a>
                    </div>
                    <div class="card-body">
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
            
            <!-- Table will be here -->
            <!-- Treatments table -->
            <div class="table-responsive">
                <table id="nurseTreatmentsTable" class="table table-striped table-hover">
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
                        
                        $query = "SELECT nt.id, nt.treatment_date, p.name AS patient_name, p.file_number, nt.nurse_name, nt.payment_status,
                                  CASE 
                                      WHEN DATE(nt.treatment_date) = CURDATE() THEN 0 
                                      ELSE 1 
                                  END as is_today
                                  FROM nurse_treatments nt
                                  JOIN patients p ON nt.patient_id = p.id
                                  WHERE 1=1 $search_condition
                                  ORDER BY is_today ASC, nt.treatment_date DESC";
                        
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
                                echo "<td class='action-buttons'>
                                        <a href='view_nurse_treatment.php?id={$row['id']}' class='btn btn-sm btn-outline-primary me-1' title='View'>
                                            <i class='fas fa-eye'></i>
                                        </a>";
                                        if (hasRole(['admin', 'nurse'])) {
                                            echo "<a href='edit_nurse_treatment.php?id={$row['id']}' class='btn btn-sm btn-outline-warning me-1' title='Edit'>
                                                <i class='fas fa-edit'></i>
                                            </a>";
                                        }
                                        if (hasRole(['admin'])) {
                                            echo "<button type='button' class='btn btn-sm btn-outline-danger' data-bs-toggle='modal' data-bs-target='#deleteModal{$row['id']}' title='Delete'>
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
                                                            <button type='submit' name='delete_treatment' class='btn btn-outline-danger'>
                                                                <i class='fas fa-trash me-1'></i> Delete
                                                            </button>
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
                    </div> <!-- End table-responsive -->
                    </div> <!-- End card-body -->
                </div> <!-- End card -->
            </div> <!-- End col-12 -->
        </div> <!-- End row -->
    </div> <!-- End container -->

    <!-- JavaScript Libraries -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#nurseTreatmentsTable').DataTable({
                // Disable initial sort to maintain server-side ordering
                "order": [],
                // Disable all client-side sorting
                "ordering": false,
                // Keep search and pagination
                "searching": true,
                "paging": true
            });
        });
    </script>
