<?php
// Start session
session_start();

// Include database configuration
require_once 'config/database.php';
// Include authentication and role helper
require_once 'config/auth.php';

// Handle form submission for deleting treatment
if (isset($_POST['delete_treatment'])) {
    if (!canDeleteTreatments()) {
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

$search = '';
$filterDateFrom = '';
$filterDateTo = '';
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) {
    $page = 1;
}
$per_page = 10;

if (isset($_GET['search'])) {
    $search = trim((string)$_GET['search']);
}
if (isset($_GET['date_from'])) {
    $filterDateFrom = trim((string)$_GET['date_from']);
}
if (isset($_GET['date_to'])) {
    $filterDateTo = trim((string)$_GET['date_to']);
}

if ($filterDateFrom !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $filterDateFrom)) {
    $filterDateFrom = '';
}
if ($filterDateTo !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $filterDateTo)) {
    $filterDateTo = '';
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
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body {
            background-color: #f8f9fa;
            padding: 20px;
            padding-left: calc(var(--sidebar-width) + 20px);
        }
        body.sidebar-collapsed {
            padding-left: calc(var(--sidebar-collapsed-width) + 20px);
        }
        @media (max-width: 768px) {
            body,
            body.sidebar-collapsed {
                padding-left: 20px;
            }
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
        .patient-history-btn {
            background-color: #e9ecef;
            border-color: #d3d9df;
            color: #343a40;
            padding: 0.42rem 0.7rem;
            border-radius: 0.5rem;
            box-shadow: 0 3px 8px rgba(108, 117, 125, 0.22);
        }
        .patient-history-btn:hover,
        .patient-history-btn:focus {
            background-color: #dde2e6;
            border-color: #c6cdd5;
            color: #212529;
        }
        .patient-history-btn i {
            font-size: 1.15rem;
        }
        .patient-history-btn .doc-mark {
            margin-left: 0.2rem;
            font-size: 0.9rem;
            opacity: 0.95;
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
        .summary-card {
            border: 1px solid #cfe0ef;
            border-radius: 14px;
            background: linear-gradient(135deg, #f7fbff 0%, #eef5fc 100%);
            box-shadow: 0 8px 18px rgba(18, 61, 101, 0.12);
            padding: 0.9rem 1rem;
            height: 100%;
        }
        .summary-label {
            color: #5b6f84;
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            margin-bottom: 0.2rem;
        }
        .summary-value {
            font-size: 1.6rem;
            font-weight: 700;
            color: #143f64;
            line-height: 1.1;
        }
        .summary-icon {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            border: 2px solid #9db9d5;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #ffffff;
            color: #1c5785;
        }
    </style>
</head>
<body>
    <?php include_once 'includes/sidebar.php'; ?>

    <div class="container-fluid">
        <div class="row mb-3">
            <div class="col-12">
                <a href="dashboard.php" class="btn btn-secondary btn-back">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
                <a href="logout.php" class="btn btn-outline-danger float-end">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
                <h2 class="d-inline-block">Nurse Treatments</h2>
            </div>
        </div>

<div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">All Treatment Records</h5>
                        <?php if (canEditTreatments() || hasRole(['nurse'])): ?>
                        <a href="add_nurse_treatment.php" class="btn btn-primary">
                            <i class="fas fa-plus"></i> New Treatment Record
                        </a>
                        <?php endif; ?>
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
            
            <div class="row mb-3">
                <div class="col-12">
                    <form class="row g-2 align-items-end" method="get" action="nurse_treatments.php">
                        <div class="col-lg-4 col-md-6">
                            <label class="form-label mb-1">Search</label>
                            <input class="form-control" type="search" name="search" placeholder="Patient, file #, or nurse..." value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <div class="col-lg-2 col-md-3">
                            <label class="form-label mb-1">From Date</label>
                            <input class="form-control" type="date" name="date_from" value="<?php echo htmlspecialchars($filterDateFrom); ?>">
                        </div>
                        <div class="col-lg-2 col-md-3">
                            <label class="form-label mb-1">To Date</label>
                            <input class="form-control" type="date" name="date_to" value="<?php echo htmlspecialchars($filterDateTo); ?>">
                        </div>
                        <div class="col-lg-4 col-md-8 d-flex gap-2">
                            <button class="btn btn-outline-secondary w-100" type="submit">
                                <i class="fas fa-filter"></i> Filter
                            </button>
                            <a href="nurse_treatments.php" class="btn btn-outline-danger">Clear</a>
                        </div>
                    </form>
                </div>
            </div>

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
                        $searchSql = $conn->real_escape_string($search);
                        $filterDateFromSql = $conn->real_escape_string($filterDateFrom);
                        $filterDateToSql = $conn->real_escape_string($filterDateTo);

                        $offset = ($page - 1) * $per_page;

                        // Build search condition
                        $search_condition = '';
                        if (!empty($searchSql)) {
                            $search_condition = " AND (p.name LIKE '%$searchSql%' OR p.file_number LIKE '%$searchSql%' OR nt.nurse_name LIKE '%$searchSql%')";
                        }
                        $date_condition = '';
                        if ($filterDateFromSql !== '') {
                            $date_condition .= " AND DATE(nt.treatment_date) >= '$filterDateFromSql'";
                        }
                        if ($filterDateToSql !== '') {
                            $date_condition .= " AND DATE(nt.treatment_date) <= '$filterDateToSql'";
                        }
                        
                        // Get total count for pagination
                        $count_query = "SELECT COUNT(*) AS total 
                                      FROM nurse_treatments nt
                                      JOIN patients p ON nt.patient_id = p.id
                                      WHERE 1=1 $search_condition $date_condition";
                        $count_result = executeQuery($count_query);
                        $total = 0;
                        if ($count_result && $row = $count_result->fetch_assoc()) {
                            $total = (int)$row['total'];
                        }
                        $total_pages = ceil($total / $per_page);

                        // Main query with LIMIT and OFFSET
                        $query = "SELECT nt.id, nt.patient_id, nt.treatment_date, p.name AS patient_name, p.file_number, nt.nurse_name, nt.payment_status,
                                 CASE 
                                     WHEN DATE(nt.treatment_date) = CURDATE() THEN 0 
                                     ELSE 1 
                                 END as is_today
                                 FROM nurse_treatments nt
                                 JOIN patients p ON nt.patient_id = p.id
                                 WHERE 1=1 $search_condition $date_condition
                                 ORDER BY is_today ASC, nt.treatment_date DESC
                                 LIMIT $per_page OFFSET $offset";
                        
                        $result = executeQuery($query);
                        $noTreatmentRecords = true;
                        $currentPageCount = 0;
                        
                        if ($result && $result->num_rows > 0) {
                            $noTreatmentRecords = false;
                            while ($row = $result->fetch_assoc()) {
                                $currentPageCount++;
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
                                        </a>
                                        <a href='view_patient.php?id={$row['patient_id']}&return_to=nurse_treatments.php' class='btn patient-history-btn me-1' title='Patient History & Documents' target='_blank'>
                                            <i class='fas fa-user'></i><i class='fas fa-file-medical doc-mark'></i>
                                        </a>";
                                        if (canEditTreatments()) {
                                            echo "<a href='edit_nurse_treatment.php?id={$row['id']}' class='btn btn-sm btn-outline-warning me-1' title='Edit'>
                                                <i class='fas fa-edit'></i>
                                            </a>";
                                        }
                                        if (canDeleteTreatments()) {
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
                        }
                        ?>
                    </tbody>
                </table>

                <?php $hasActiveTreatmentFilters = ($search !== '' || $filterDateFrom !== '' || $filterDateTo !== ''); ?>
                <div class="alert alert-light border mt-3 mb-0">
                    <strong>Total Treatments<?php echo $hasActiveTreatmentFilters ? ' (Filtered)' : ''; ?>:</strong>
                    <?php echo (int)$total; ?>
                    <span class="ms-3 text-muted">Showing <?php echo (int)$currentPageCount; ?> on this page</span>
                </div>

                <?php if ($noTreatmentRecords): ?>
                    <div class="alert alert-info mt-3 mb-0 text-center">
                        <i class="fas fa-info-circle me-1"></i> No treatment records found for the selected filters.
                    </div>
                <?php endif; ?>

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
                                    <a class="page-link" href="?page=1&search=<?php echo urlencode($search); ?>&date_from=<?php echo urlencode($filterDateFrom); ?>&date_to=<?php echo urlencode($filterDateTo); ?>">First</a>
                                </li>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&date_from=<?php echo urlencode($filterDateFrom); ?>&date_to=<?php echo urlencode($filterDateTo); ?>">Previous</a>
                                </li>
                            <?php endif; ?>

                            <?php
                            $start = max(1, $page - 2);
                            $end = min($total_pages, $page + 2);
                            
                            // Show first page with ellipsis if needed
                            if ($start > 1) {
                                echo '<li class="page-item"><a class="page-link" href="?page=1&search='.urlencode($search).'&date_from='.urlencode($filterDateFrom).'&date_to='.urlencode($filterDateTo).'">1</a></li>';
                                if ($start > 2) {
                                    echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                }
                            }
                            
                            for ($i = $start; $i <= $end; $i++):
                            ?>
                                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&date_from=<?php echo urlencode($filterDateFrom); ?>&date_to=<?php echo urlencode($filterDateTo); ?>"><?php echo $i; ?></a>
                                </li>
                            <?php 
                            endfor; 
                            
                            // Show last page with ellipsis if needed
                            if ($end < $total_pages) {
                                if ($end < $total_pages - 1) {
                                    echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                }
                                echo '<li class="page-item"><a class="page-link" href="?page='.$total_pages.'&search='.urlencode($search).'&date_from='.urlencode($filterDateFrom).'&date_to='.urlencode($filterDateTo).'">'.$total_pages.'</a></li>';
                            }
                            ?>

                            <?php if ($page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&date_from=<?php echo urlencode($filterDateFrom); ?>&date_to=<?php echo urlencode($filterDateTo); ?>">Next</a>
                                </li>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $total_pages; ?>&search=<?php echo urlencode($search); ?>&date_from=<?php echo urlencode($filterDateFrom); ?>&date_to=<?php echo urlencode($filterDateTo); ?>">Last</a>
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
    <script src="assets/js/sidebar.js"></script>
    <?php include_once 'includes/support_ticket_widget.php'; ?>
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

        });
    </script>
