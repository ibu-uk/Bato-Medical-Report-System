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
        
        /* Table styling */
        #nurseTreatmentsTable {
            border-collapse: separate;
            border-spacing: 0;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            overflow: hidden;
            width: 100%;
        }
        #nurseTreatmentsTable tbody tr {
            background-color: #fff !important;
            transition: background-color 0.2s;
        }
        #nurseTreatmentsTable tbody tr:not(:last-child) {
            border-bottom: 1px solid #c6c8ca;
        }
        #nurseTreatmentsTable tbody tr:hover {
            background-color: #f8f9fa !important;
        }
        /* Style for table cells */
        #nurseTreatmentsTable td, 
        #nurseTreatmentsTable th {
            padding: 12px 15px;
            vertical-align: middle;
            border-right: 1px solid #dee2e6;
        }
        #nurseTreatmentsTable td:last-child, 
        #nurseTreatmentsTable th:last-child {
            border-right: none;
        }
        /* Style for table header */
        #nurseTreatmentsTable thead th {
            background-color: #e9ecef;
            border-bottom: 2px solid #c6c8ca;
            font-weight: 600;
            color: #495057;
            text-transform: uppercase;
            font-size: 0.8rem;
            letter-spacing: 0.5px;
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
                        // Ensure database connection is established
                        if (!isset($conn) || !$conn) {
                            $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
                            if ($conn->connect_error) {
                                die("Connection failed: " . $conn->connect_error);
                            }
                        }
                        
                        // Pagination variables
                        $search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
                        $page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
                        $per_page = 10; // Items per page
                        $offset = ($page - 1) * $per_page;

                        // Build search condition
                        $search_condition = '';
                        if (!empty($search)) {
                            $search_condition = " AND (p.name LIKE '%$search%' OR p.file_number LIKE '%$search%' OR nt.nurse_name LIKE '%$search%')";
                        }
                        
                        // Get total count for pagination
                        $count_query = "SELECT COUNT(*) AS total 
                                      FROM nurse_treatments nt
                                      JOIN patients p ON nt.patient_id = p.id
                                      WHERE 1=1 $search_condition";
                        $count_result = executeQuery($count_query);
                        $total = 0;
                        if ($count_result && $row = $count_result->fetch_assoc()) {
                            $total = (int)$row['total'];
                        }
                        $total_pages = ceil($total / $per_page);

                        // Main query with LIMIT and OFFSET
                        $query = "SELECT nt.id, nt.treatment_date, p.name AS patient_name, p.file_number, nt.nurse_name, nt.payment_status,
                                 CASE 
                                     WHEN DATE(nt.treatment_date) = CURDATE() THEN 0 
                                     ELSE 1 
                                 END as is_today
                                 FROM nurse_treatments nt
                                 JOIN patients p ON nt.patient_id = p.id
                                 WHERE 1=1 $search_condition
                                 ORDER BY is_today ASC, nt.treatment_date DESC
                                 LIMIT $per_page OFFSET $offset";
                        
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
                                        <a href='view_treatment.php?id={$row['id']}' class='btn btn-sm btn-outline-primary me-1' title='View'>
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

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <nav aria-label="Page navigation" class="mt-4">
                        <style>
                            .pagination .page-link {
                                color: #212529;
                                border-color: #dee2e6;
                            }
                            .pagination .page-item.active .page-link {
                                background-color: #212529;
                                border-color: #212529;
                                color: #fff;
                            }
                            .pagination .page-item:not(.active) .page-link:hover {
                                background-color: #f8f9fa;
                            }
                            .pagination {
                                margin-bottom: 0;
                            }
                        </style>
                        <ul class="pagination justify-content-center">
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=1&search=<?php echo urlencode($search); ?>">First</a>
                                </li>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>">Previous</a>
                                </li>
                            <?php endif; ?>

                            <?php
                            $start = max(1, $page - 2);
                            $end = min($total_pages, $page + 2);
                            
                            // Show first page with ellipsis if needed
                            if ($start > 1) {
                                echo '<li class="page-item"><a class="page-link" href="?page=1&search='.urlencode($search).'">1</a></li>';
                                if ($start > 2) {
                                    echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                }
                            }
                            
                            for ($i = $start; $i <= $end; $i++):
                            ?>
                                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>"><?php echo $i; ?></a>
                                </li>
                            <?php 
                            endfor; 
                            
                            // Show last page with ellipsis if needed
                            if ($end < $total_pages) {
                                if ($end < $total_pages - 1) {
                                    echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                }
                                echo '<li class="page-item"><a class="page-link" href="?page='.$total_pages.'&search='.urlencode($search).'">'.$total_pages.'</a></li>';
                            }
                            ?>

                            <?php if ($page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>">Next</a>
                                </li>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $total_pages; ?>&search=<?php echo urlencode($search); ?>">Last</a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                <?php endif; ?>

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
            // Initialize DataTable without the default search box and pagination controls
            $('#nurseTreatmentsTable').DataTable({
                paging: false,      // Disable DataTables pagination (using server-side)
                searching: false,   // Disable DataTables search (using our custom search)
                info: false,       // Hide 'Showing X of Y entries' info
                ordering: false,    // Disable client-side sorting
                dom: 't'           // Only show the table (no other elements)
            });

            // Add our custom search box
            var searchHtml = `
                <div class="row mb-3">
                    <div class="col-12">
                        <form class="d-flex align-items-center" role="search" method="get" action="nurse_treatments.php">
                            <div class="input-group" style="max-width: 500px;">
                                <input class="form-control" type="search" name="search" 
                                       placeholder="Search by patient, file #, or nurse..." 
                                       value="<?php echo htmlspecialchars($search); ?>">
                                <button class="btn btn-outline-secondary" type="submit">
                                    <i class="fas fa-search"></i> Search
                                </button>
                                <?php if (!empty($search)): ?>
                                    <a href="nurse_treatments.php" class="btn btn-outline-danger">
                                        <i class="fas fa-times"></i> Clear
                                    </a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>
            `;
            
            // Insert search box before the table
            $('.table-responsive').before(searchHtml);
            
            // Make the search form work with our server-side search
            $('form[role="search"]').on('submit', function(e) {
                // Let the form submit normally - we're using method="get"
            });
        });
    </script>
