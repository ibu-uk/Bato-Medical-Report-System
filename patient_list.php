<?php
// Start session
session_start();

// Include database configuration
require_once 'config/database.php';

// Include authentication helpers
require_once 'config/auth.php';

// Require login to access this page
requireLogin();

// Create database connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Initialize variables
$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Build the query
$where = [];
$params = [];
$types = '';

if (!empty($search)) {
    $where[] = "(p.name LIKE ? OR p.file_number LIKE ? OR p.mobile LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [&$search_param, &$search_param, &$search_param]);
    $types .= 'sss';
}

$where_clause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// Get total count for pagination
$count_sql = "SELECT COUNT(*) as total FROM patients p $where_clause";
$count_stmt = $conn->prepare($count_sql);

// Debug: Output SQL and parameters
// echo "SQL: $count_sql<br>Params: " . print_r($params, true) . "<br>Types: $types<br>";

if ($count_stmt === false) {
    die('Prepare failed: ' . htmlspecialchars($conn->error) . "<br>SQL: $count_sql");
}

if (!empty($params)) {
    $bind_result = $count_stmt->bind_param($types, ...$params);
    if ($bind_result === false) {
        die('Bind param failed: ' . htmlspecialchars($count_stmt->error));
    }
}

$execute_result = $count_stmt->execute();
if ($execute_result === false) {
    die('Execute failed: ' . htmlspecialchars($count_stmt->error));
}
$total_records = $count_stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_records / $per_page);

// Get patients data with counts
$sql = "SELECT p.*, 
        (SELECT COUNT(*) FROM reports WHERE patient_id = p.id) as report_count,
        (SELECT COUNT(*) FROM prescriptions WHERE patient_id = p.id) as prescription_count,
        (SELECT COUNT(*) FROM nurse_treatments WHERE patient_id = p.id) as treatment_count
        FROM patients p ";

if (!empty($where_clause)) {
    $sql .= $where_clause . " ";
}

$sql .= "ORDER BY p.created_at DESC LIMIT ? OFFSET ?";

// Prepare the statement
$stmt = $conn->prepare($sql);
if ($stmt === false) {
    die('Prepare failed: ' . htmlspecialchars($conn->error) . "<br>SQL: $sql");
}

// Bind parameters
if (!empty($params)) {
    // Add limit and offset to params
    $types .= 'ii';
    $params[] = $per_page;
    $params[] = $offset;
    
    // Bind all parameters
    $bind_result = $stmt->bind_param($types, ...$params);
    if ($bind_result === false) {
        die('Bind param failed: ' . htmlspecialchars($stmt->error));
    }
} else {
    // Only bind limit and offset
    $bind_result = $stmt->bind_param('ii', $per_page, $offset);
    if ($bind_result === false) {
        die('Bind param failed: ' . htmlspecialchars($stmt->error));
    }
}

// Execute the statement
$execute_result = $stmt->execute();
if ($execute_result === false) {
    die('Execute failed: ' . htmlspecialchars($stmt->error));
}
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient List - Bato Medical Report System</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>
    <!-- Include Sidebar -->
    <?php include_once 'includes/sidebar.php'; ?>

    <!-- Top Navigation -->
    <nav class="top-navbar">
        <div class="container-fluid">
            <div class="d-flex align-items-center">
                <button class="btn btn-link d-md-none" id="mobileMenuToggle">
                    <i class="fas fa-bars"></i>
                </button>
                <div class="ms-auto d-flex align-items-center">
                    <div class="dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-user-circle me-1"></i> 
                            <span class="d-none d-md-inline"><?php echo isset($_SESSION['full_name']) ? htmlspecialchars($_SESSION['full_name']) : ''; ?></span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="profile.php"><i class="fas fa-id-card me-2"></i>Profile</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="main-content">
        <div class="container-fluid py-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="mb-0">Patient List</h2>
                <a href="add_patient.php" class="btn btn-primary">
                    <i class="fas fa-plus me-2"></i>Add New Patient
                </a>
            </div>

            <!-- Search and Filter Card -->
            <div class="card mb-4">
                <div class="card-body">
                    <form action="" method="GET" class="row g-3">
                        <div class="col-md-8">
                            <div class="input-group">
                                <input type="text" name="search" class="form-control" placeholder="Search by name, file number, or phone..." value="<?php echo htmlspecialchars($search); ?>">
                                <button class="btn btn-outline-secondary" type="submit">
                                    <i class="fas fa-search"></i> Search
                                </button>
                                <?php if (!empty($search)): ?>
                                    <a href="patient_list.php" class="btn btn-outline-danger">
                                        <i class="fas fa-times"></i> Clear
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="d-flex justify-content-end">
                                <div class="btn-group" role="group">
                                    <button type="button" class="btn btn-outline-secondary" id="exportPdf">
                                        <i class="fas fa-file-pdf me-1"></i> PDF
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary" id="exportExcel">
                                        <i class="fas fa-file-excel me-1"></i> Excel
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary" id="printList">
                                        <i class="fas fa-print me-1"></i> Print
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Patients Table -->
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover" id="patientsTable">
                            <thead class="table-light">
                                <tr>
                                    <th>File #</th>
                                    <th>Patient Name</th>
                                    <th>Mobile</th>
                                    <th>Reports</th>
                                    <th>Prescriptions</th>
                                    <th>Treatments</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($result->num_rows > 0): ?>
                                    <?php while ($row = $result->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($row['file_number']); ?></td>
                                            <td>
                                                <a href="view_patient.php?id=<?php echo $row['id']; ?>" class="text-primary">
                                                    <?php echo htmlspecialchars($row['name']); ?>
                                                </a>
                                            </td>
                                            <td><?php echo !empty($row['mobile']) ? htmlspecialchars($row['mobile']) : 'N/A'; ?></td>
                                            <td>
                                                <span class="badge bg-info"><?php echo $row['report_count']; ?></span>
                                            </td>
                                            <td>
                                                <span class="badge bg-success"><?php echo $row['prescription_count']; ?></span>
                                            </td>
                                            <td>
                                                <span class="badge bg-warning text-dark"><?php echo $row['treatment_count']; ?></span>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm" role="group">
                                                    <a href="view_patient.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-info" title="View">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <a href="edit_patient.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-primary" title="Edit">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <button type="button" class="btn btn-sm btn-danger delete-patient" data-id="<?php echo $row['id']; ?>" title="Delete">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="9" class="text-center">No patients found.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                        <nav aria-label="Page navigation" class="mt-4">
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
                                for ($i = $start; $i <= $end; $i++):
                                ?>
                                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>"><?php echo $i; ?></a>
                                    </li>
                                <?php endfor; ?>

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
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="fas fa-exclamation-triangle me-2"></i>Confirm Delete</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center mb-3">
                        <i class="fas fa-exclamation-circle text-danger" style="font-size: 4rem;"></i>
                    </div>
                    <h5 class="text-center mb-3">Are you sure you want to delete this patient?</h5>
                    <p class="text-center text-muted">
                        Patient: <strong id="deletePatientName"></strong><br>
                        <span class="text-danger">This action cannot be undone.</span>
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i> Cancel
                    </button>
                    <button type="button" class="btn btn-danger" id="confirmDelete">
                        <i class="fas fa-trash me-1"></i> Delete
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
    <!-- Sidebar JS -->
    <script src="assets/js/sidebar.js"></script>
    
    <script>
        $(document).ready(function() {
            // Initialize DataTable with updated column count
            $('#patientsTable').DataTable({
                "paging": false,
                "searching": false,
                "info": false,
                "ordering": true,
                "responsive": true,
                "columnDefs": [
                    { "orderable": true, "targets": [0, 1, 2] },  // Allow sorting on File #, Patient Name, Mobile
                    { "orderable": false, "targets": [3, 4, 5] }  // Disable sorting on Reports, Prescriptions, Actions
                ]
            });

            // Delete button click handler - using event delegation
            $(document).on('click', '.delete-patient', function(e) {
                e.preventDefault();
                const patientId = $(this).data('id');
                const patientName = $(this).closest('tr').find('td:nth-child(2)').text().trim();
                const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
                
                // Update modal content
                $('#deletePatientName').text(patientName);
                
                // Remove any existing click handlers to prevent multiple bindings
                $('#confirmDelete').off('click').on('click', function() {
                    // AJAX call to delete the patient
                    $.ajax({
                        url: 'delete_patient.php',
                        type: 'POST',
                        data: { id: patientId },
                        dataType: 'json',
                        success: function(response) {
                            if (response.success) {
                                // Show success message and reload the page
                                alert('Patient deleted successfully');
                                window.location.reload();
                            } else {
                                alert('Error: ' + (response.message || 'Failed to delete patient'));
                            }
                        },
                        error: function() {
                            alert('Error: Unable to connect to the server');
                        }
                    });
                    
                    deleteModal.hide();
                });
                
                deleteModal.show();
            });

            // Export buttons
            $('#exportPdf').click(function() {
                // Implement PDF export functionality
                alert('PDF export will be implemented here');
            });

            $('#exportExcel').click(function() {
                // Implement Excel export functionality
                window.location.href = 'export_patients.php?format=excel';
            });

            $('#printList').click(function() {
                window.print();
            });
        });
    </script>
</body>
</html>

<?php
// Function to calculate age from date of birth
function calculateAge($dob) {
    $birthDate = new DateTime($dob);
    $today = new DateTime('today');
    $age = $birthDate->diff($today)->y;
    return $age . ' yrs';
}
?>
