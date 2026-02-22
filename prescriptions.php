<?php
// Start session
session_start();

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Include database configuration
require_once 'config/database.php';
// Include authentication and role functions
require_once 'config/auth.php';

// Page access control - only admin and doctor can access prescriptions
if (!hasRole(['admin', 'doctor'])) {
    header('Location: index.php');
    exit;
}

// Handle form submission for deleting prescription
if (isset($_POST['delete_prescription']) && isset($_POST['csrf_token']) && $_POST['csrf_token'] === $_SESSION['csrf_token']) {
    $prescription_id = sanitize($_POST['prescription_id']);
    // Fetch patient name for logging before deletion
    $patient_name = '';
    global $conn;
if (!isset($conn) || !$conn) {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        $_SESSION['error'] = "Database connection failed: " . $conn->connect_error;
        header('Location: prescriptions.php');
        exit;
    }
}
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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="assets/css/arabic-fonts.css">
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
            background-color: #fff;
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
        }
        .prescription-date {
            white-space: nowrap;
        }
    </style>
</head>
<body style="padding: 20px; margin: 0;">
    <div class="container-fluid">
        <div class="row mb-3">
            <div class="col-12">
                <a href="index.php" class="btn btn-secondary btn-back">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
                <h2 class="d-inline-block">Prescriptions</h2>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">All Prescriptions</h5>
                        <?php if (hasRole(['admin', 'doctor'])): ?>
                        <a href="add_prescription.php" class="btn btn-primary">
                            <i class="fas fa-plus"></i> New Prescription
                        </a>
                        <?php endif; ?>
                    </div>
            
                    <div class="card-body">
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
                        
                        <div class="table-responsive">
                            <table id="prescriptionsTable" class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>#</th>
                                        <th>Date</th>
                                        <th>Patient</th>
                                        <th>File #</th>
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
$query = "SELECT pr.id, pr.prescription_date, p.name AS patient_name, p.file_number, d.name AS doctor_name,
          CASE 
              WHEN DATE(pr.prescription_date) = CURDATE() THEN 0 
              ELSE 1 
          END as is_today
          FROM prescriptions pr
          JOIN patients p ON pr.patient_id = p.id
          JOIN doctors d ON pr.doctor_id = d.id
          WHERE 1=1 $search_condition
          ORDER BY is_today ASC, pr.prescription_date DESC
          LIMIT $per_page OFFSET $offset";

$result = executeQuery($query);

                        if ($result && $result->num_rows > 0) {
                            while ($row = $result->fetch_assoc()) {
                                echo "<tr>";
                                echo "<td>{$row['id']}</td>";
                                echo "<td class='prescription-date'>" . date('d-m-Y', strtotime($row['prescription_date'])) . "</td>";
                                echo "<td>{$row['patient_name']}</td>";
                                echo "<td>{$row['file_number']}</td>";
                                echo "<td>{$row['doctor_name']}</td>";
                                
                                // Actions column
                                echo "<td class='action-buttons'>";
                                // View is allowed for all roles that can access this page
                                echo "<a href='view_prescription.php?id={$row['id']}' class='btn btn-sm btn-outline-primary' title='View' target='_blank'><i class='fas fa-eye'></i></a> ";

                                // Only admin/doctor can edit or delete prescriptions
                                if (hasRole(['admin', 'doctor'])) {
                                    echo "<a href='edit_prescription.php?id={$row['id']}' class='btn btn-sm btn-outline-warning' title='Edit'><i class='fas fa-edit'></i></a> ";
                                    // Delete form with CSRF protection
                                    echo "<form id='deleteForm{$row['id']}' method='POST' style='display:inline;'>";
                                    echo "<input type='hidden' name='prescription_id' value='{$row['id']}'>";
                                    echo "<input type='hidden' name='delete_prescription' value='1'>";
                                    echo "<input type='hidden' name='csrf_token' value='".$_SESSION['csrf_token']."'>";
                                    echo "<button type='button' class='btn btn-sm btn-outline-danger' ";
                                    echo "onclick=\"if(confirm('Are you sure you want to delete this prescription?')) { this.form.submit(); }\" ";
                                    echo "title='Delete'><i class='fas fa-trash'></i></button>";
                                    echo "</form>";
                                }
                                echo "</td></tr>";
                            }
                        } else {
                            echo "<tr><td colspan='5' class='text-center'>No prescriptions found</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>

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
                                <a class="page-link" href="?page=1">First</a>
                            </li>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page - 1; ?>">Previous</a>
                            </li>
                        <?php endif; ?>

                        <?php
                        $start = max(1, $page - 2);
                        $end = min($total_pages, $page + 2);
                        
                        // Show first page with ellipsis if needed
                        if ($start > 1) {
                            echo '<li class="page-item"><a class="page-link" href="?page=1">1</a></li>';
                            if ($start > 2) {
                                echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                            }
                        }
                        
                        for ($i = $start; $i <= $end; $i++):
                        ?>
                            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                            </li>
                        <?php 
                        endfor; 
                        
                        // Show last page with ellipsis if needed
                        if ($end < $total_pages) {
                            if ($end < $total_pages - 1) {
                                echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                            }
                            echo '<li class="page-item"><a class="page-link" href="?page='.$total_pages.'">'.$total_pages.'</a></li>';
                        }
                        ?>

                        <?php if ($page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page + 1; ?>">Next</a>
                            </li>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $total_pages; ?>">Last</a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            <?php endif; ?>

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
    <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.1/moment.min.js"></script>
    
    <script>
        $(document).ready(function() {
            // Convert date from DD-MM-YYYY to YYYY-MM-DD for sorting
            function parseDate(dateString) {
                if (!dateString) return null;
                var parts = dateString.split('-');
                return new Date(parts[2], parts[1] - 1, parts[0]);
            }

            // Disable DataTables pagination since we're using server-side pagination
            $('#prescriptionsTable').DataTable({
                paging: false,
                searching: true,
                info: false,
                order: [[1, 'desc']], // Sort by date column (index 1) in descending order by default
                dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>><"row"<"col-sm-12"tr>><"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
                language: {
                    search: "_INPUT_",
                    searchPlaceholder: "Search prescriptions..."
                },
                columnDefs: [
                    { orderable: false, targets: [0, 5] }, // Disable sorting on ID and Actions columns
                    { 
                        targets: 1, // Target the date column (index 1)
                        type: 'date',
                        className: 'text-nowrap',
                        render: function(data, type, row) {
                            // For sorting, return a sortable date string (YYYY-MM-DD)
                            if (type === 'sort') {
                                var parts = data.split('-');
                                if (parts.length === 3) {
                                    return parts[2] + '-' + parts[1] + '-' + parts[0];
                                }
                                return data;
                            }
                            // For display, return the formatted date as is (DD-MM-YYYY)
                            return data;
                        }
                    }
                ]
            });
        });
    </script>
</body>
</html>
