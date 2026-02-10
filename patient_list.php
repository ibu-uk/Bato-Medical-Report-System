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
$filter = isset($_GET['filter']) ? $_GET['filter'] : '';
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Build the query
$where = [];
$params = [];
$types = '';

if (!empty($search)) {
    $where[] = "(p.name LIKE ? OR p.file_number LIKE ? OR p.mobile LIKE ? OR p.civil_id LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [&$search_param, &$search_param, &$search_param, &$search_param]);
    $types .= 'ssss';
}

// Add filter condition
if (!empty($filter)) {
    switch ($filter) {
        case 'with_reports':
            $where[] = "(SELECT COUNT(*) FROM reports WHERE patient_id = p.id) > 0";
            break;
        case 'with_prescriptions':
            $where[] = "(SELECT COUNT(*) FROM prescriptions WHERE patient_id = p.id) > 0";
            break;
        case 'with_treatments':
            $where[] = "(SELECT COUNT(*) FROM nurse_treatments WHERE patient_id = p.id) > 0";
            break;
    }
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
    <style>
        /* Table styling */
        #patientsTable {
            border-collapse: separate;
            border-spacing: 0;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            overflow: hidden;
            width: 100%;
        }
        #patientsTable tbody tr {
            background-color: #fff !important;
            transition: background-color 0.2s;
        }
        #patientsTable tbody tr:not(:last-child) {
            border-bottom: 1px solid #c6c8ca;
        }
        #patientsTable tbody tr:hover {
            background-color: #f8f9fa !important;
        }
        /* Style for table cells */
        #patientsTable td, 
        #patientsTable th {
            padding: 12px 15px;
            vertical-align: middle;
            border-right: 1px solid #dee2e6;
        }
        #patientsTable td:last-child, 
        #patientsTable th:last-child {
            border-right: none;
        }
        /* Style for table header */
        #patientsTable thead th {
            background-color: #e9ecef;
            border-bottom: 2px solid #c6c8ca;
            font-weight: 600;
            color: #495057;
            text-transform: uppercase;
            font-size: 0.8rem;
            letter-spacing: 0.5px;
        }
        /* Style for action buttons */
        .btn-group-sm > .btn, .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
            line-height: 1.5;
            border-radius: 0.2rem;
        }
    </style>
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
                                <select name="filter" class="form-select" style="max-width: 200px;">
                                    <option value="">All Patients</option>
                                    <option value="with_reports" <?php echo $filter === 'with_reports' ? 'selected' : ''; ?>>With Reports</option>
                                    <option value="with_prescriptions" <?php echo $filter === 'with_prescriptions' ? 'selected' : ''; ?>>With Prescriptions</option>
                                    <option value="with_treatments" <?php echo $filter === 'with_treatments' ? 'selected' : ''; ?>>With Treatments</option>
                                </select>
                                <input type="text" name="search" class="form-control" placeholder="Search by name, file number, civil ID, or phone..." value="<?php echo htmlspecialchars($search); ?>">
                                <button class="btn btn-outline-secondary" type="submit">
                                    <i class="fas fa-search"></i> Search
                                </button>
                                <?php if (!empty($search) || !empty($filter)): ?>
                                    <a href="patient_list.php" class="btn btn-outline-danger">
                                        <i class="fas fa-times"></i> Clear
                                    </a>
                                <?php endif; ?>
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
                                    <th>Civil ID</th>
                                    <th>Mobile</th>
                                    <th>Reports</th>
                                    <th>Prescriptions</th>
                                    <th>Treatments</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody class="d-none d-sm-table-row-group">
                                <?php if ($result->num_rows > 0): ?>
                                    <?php while ($row = $result->fetch_assoc()): ?>
                                        <tr data-patient-id="<?php echo $row['id']; ?>">
                                            <td><?php echo htmlspecialchars($row['file_number']); ?></td>
                                            <td>
                                                <a href="view_patient.php?id=<?php echo $row['id']; ?>" class="text-dark text-decoration-none">
                                                    <?php echo htmlspecialchars($row['name']); ?>
                                                </a>
                                            </td>
                                            <td><?php echo htmlspecialchars($row['civil_id']); ?></td>
                                            <td class="d-none d-md-table-cell"><?php echo !empty($row['mobile']) ? htmlspecialchars($row['mobile']) : 'N/A'; ?></td>
                                            <td class="text-center">
                                                <span class="badge bg-info"><?php echo $row['report_count']; ?></span>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-success"><?php echo $row['prescription_count']; ?></span>
                                            </td>
                                            <td class="text-center">
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
                            <tbody class="d-sm-none" id="mobilePatientList">
                                <!-- Mobile view will be populated by JavaScript -->
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
                            </style>
                            <ul class="pagination justify-content-center">
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=1&search=<?php echo urlencode($search); ?>&filter=<?php echo urlencode($filter); ?>">First</a>
                                    </li>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&filter=<?php echo urlencode($filter); ?>">Previous</a>
                                    </li>
                                <?php endif; ?>

                                <?php
                                $start = max(1, $page - 2);
                                $end = min($total_pages, $page + 2);
                                // Show first page with ellipsis if needed
                                if ($start > 1) {
                                    echo '<li class="page-item"><a class="page-link" href="?page=1&search='.urlencode($search).'&filter='.urlencode($filter).'">1</a></li>';
                                    if ($start > 2) {
                                        echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                    }
                                }
                                
                                for ($i = $start; $i <= $end; $i++):
                                ?>
                                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&filter=<?php echo urlencode($filter); ?>"><?php echo $i; ?></a>
                                    </li>
                                <?php 
                                endfor; 
                                
                                // Show last page with ellipsis if needed
                                if ($end < $total_pages) {
                                    if ($end < $total_pages - 1) {
                                        echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                    }
                                    echo '<li class="page-item"><a class="page-link" href="?page='.$total_pages.'&search='.urlencode($search).'&filter='.urlencode($filter).'">'.$total_pages.'</a></li>';
                                }
                                ?>

                                <?php if ($page < $total_pages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&filter=<?php echo urlencode($filter); ?>">Next</a>
                                    </li>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $total_pages; ?>&search=<?php echo urlencode($search); ?>&filter=<?php echo urlencode($filter); ?>">Last</a>
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
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg" style="border-radius: 12px; overflow: hidden;">
                <div class="modal-header border-0" style="background: linear-gradient(135deg, #dc3545, #bb2d3b);">
                    <div class="d-flex align-items-center w-100">
                        <div class="d-flex align-items-center justify-content-center rounded-circle" style="width: 36px; height: 36px; background-color: rgba(255,255,255,0.2);">
                            <i class="fas fa-exclamation-triangle text-white"></i>
                        </div>
                        <h5 class="modal-title text-white ms-3 mb-0">Confirm Deletion</h5>
                        <button type="button" class="btn-close btn-close-white ms-auto" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                </div>
                <div class="modal-body p-4">
                    <div class="text-center">
                        <div class="d-flex justify-content-center mb-4">
                            <div class="position-relative" style="width: 100px; height: 100px;">
                                <div class="position-absolute w-100 h-100 rounded-circle" style="background: rgba(220, 53, 69, 0.1); animation: pulse 2s infinite;"></div>
                                <div class="position-absolute d-flex align-items-center justify-content-center w-100 h-100">
                                    <i class="fas fa-user-times text-danger" style="font-size: 3rem;"></i>
                                </div>
                            </div>
                        </div>
                        
                        <h4 class="fw-bold mb-3">Delete Patient?</h4>
                        <p class="text-muted mb-4">
                            You are about to delete <span id="deletePatientName" class="fw-bold text-dark"></span>.
                            <span class="d-block mt-2">This action <span class="text-danger fw-bold">cannot be undone</span> and will permanently remove:</span>
                        </p>
                        
                        <div class="alert alert-danger bg-danger bg-opacity-10 border-danger border-opacity-25 mb-4 text-start">
                            <ul class="mb-0 ps-3">
                                <li>Patient's personal information</li>
                                <li>All medical reports</li>
                                <li>Prescription history</li>
                                <li>Treatment records</li>
                            </ul>
                        </div>
                        
                        <p class="small text-muted mb-0">
                            <i class="fas fa-exclamation-circle me-1"></i> This action is irreversible. Please confirm your decision.
                        </p>
                    </div>
                </div>
                <div class="modal-footer justify-content-center border-0 pt-0 pb-4 px-4">
                    <button type="button" class="btn btn-outline-secondary px-4 me-3" data-bs-dismiss="modal" style="min-width: 120px;">
                        <i class="fas fa-times me-2"></i>Cancel
                    </button>
                    <button type="button" class="btn btn-danger px-4 shadow-sm" id="confirmDelete" style="min-width: 120px;">
                        <i class="fas fa-trash-alt me-2"></i>Delete
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
    
    <style>
        /* Mobile responsive styles */
        @media (max-width: 767.98px) {
            .mobile-patient-card {
                border: 1px solid #dee2e6;
                border-radius: 0.5rem;
                padding: 1rem;
                margin-bottom: 1rem;
                background: white;
                box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            }
            .mobile-patient-card .patient-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 0.5rem;
                border-bottom: 1px solid #eee;
                padding-bottom: 0.5rem;
            }
            .mobile-patient-card .patient-actions {
                display: flex;
                gap: 0.5rem;
            }
            .mobile-patient-card .patient-details {
                margin-bottom: 0.75rem;
            }
            .mobile-patient-card .patient-stats {
                display: flex;
                gap: 0.5rem;
                flex-wrap: wrap;
            }
            .mobile-patient-card .badge {
                font-size: 0.8rem;
                padding: 0.35em 0.65em;
            }
            .d-sm-none .btn {
                padding: 0.25rem 0.5rem;
                font-size: 0.75rem;
            }
        }
    </style>
    <script>
        function createMobilePatientCard(patient) {
            return `
                <div class="mobile-patient-card">
                    <div class="patient-header">
                        <div>
                            <strong>${patient.name}</strong>
                            <div class="text-muted small">File #${patient.fileNumber}</div>
                        </div>
                        <div class="patient-actions">
                            <a href="view_patient.php?id=${patient.id}" class="btn btn-sm btn-info" title="View">
                                <i class="fas fa-eye"></i>
                            </a>
                            <a href="edit_patient.php?id=${patient.id}" class="btn btn-sm btn-primary" title="Edit">
                                <i class="fas fa-edit"></i>
                            </a>
                            <button class="btn btn-sm btn-danger delete-patient" data-id="${patient.id}" title="Delete">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                    <div class="patient-details">
                        ${patient.mobile ? `<div><i class="fas fa-phone me-1"></i> ${patient.mobile}</div>` : ''}
                    </div>
                    <div class="patient-stats">
                        <span class="badge bg-info"><i class="fas fa-file-medical me-1"></i> ${patient.reportCount}</span>
                        <span class="badge bg-success"><i class="fas fa-prescription me-1"></i> ${patient.prescriptionCount}</span>
                        <span class="badge bg-warning text-dark"><i class="fas fa-user-nurse me-1"></i> ${patient.treatmentCount}</span>
                    </div>
                </div>
            `;
        }

        function updateMobileView() {
            if ($(window).width() < 768) {
                $('.d-sm-table-row-group').addClass('d-none');
                const $mobileList = $('#mobilePatientList');
                $mobileList.empty();
                
                $('tr[data-patient-id]').each(function() {
                    const $row = $(this);
                    const patient = {
                        id: $row.data('patient-id'),
                        name: $row.find('td:eq(1) a').text().trim(),
                        fileNumber: $row.find('td:eq(0)').text().trim(),
                        mobile: $row.find('td:eq(2)').text().trim(),
                        reportCount: $row.find('td:eq(3) .badge').text().trim(),
                        prescriptionCount: $row.find('td:eq(4) .badge').text().trim(),
                        treatmentCount: $row.find('td:eq(5) .badge').text().trim()
                    };
                    $mobileList.append(createMobilePatientCard(patient));
                });
                
                $mobileList.removeClass('d-none');
            } else {
                $('.d-sm-table-row-group').removeClass('d-none');
                $('#mobilePatientList').addClass('d-none');
            }
        }

        $(document).ready(function() {
            // Override DataTables error handling to show friendly message
            $.fn.dataTable.ext.errMode = function(settings, helpPage, message) {
                // Show a friendly message instead of an alert
                const friendlyAlert = `
                    <div class="alert alert-info alert-dismissible fade show" role="alert">
                        <i class="fas fa-info-circle me-2"></i>
                        No matching records found. Try adjusting your search terms.
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                `;
                $('.main-content .container-fluid').prepend(friendlyAlert);
                // Auto-dismiss after 5 seconds
                setTimeout(() => {
                    $('.alert').fadeOut();
                }, 5000);
            };
            
            // Handle window resize
            $(window).on('resize', updateMobileView);
            
            // Initial mobile view setup
            updateMobileView();
            
            // Simple DataTable initialization
            $('#patientsTable').DataTable({
                paging: false,
                searching: false,
                info: false,
                order: [[1, 'asc']],
                columnDefs: [
                    { orderable: false, targets: [6] } // Make Actions column not sortable
                ]
            });
            
            // Store the patient ID when delete button is clicked
            var currentPatientId = null;
            
            // Delete button click handler
            $(document).on('click', '.delete-patient', function(e) {
                e.preventDefault();
                currentPatientId = $(this).data('id');
                const patientName = $(this).closest('tr').find('td:nth-child(2)').text().trim();
                
                // Update modal content
                $('#deletePatientName').text(patientName);
                
                // Show the modal
                const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
                deleteModal.show();
            });
            
            // Handle delete confirmation
            $('#confirmDelete').off('click').on('click', function() {
                if (!currentPatientId) return;
                
                var $button = $(this);
                var originalText = $button.html();
                
                // Show loading state
                $button.prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Deleting...');
                
                // AJAX call to delete the patient
                $.ajax({
                    url: 'delete_patient.php',
                    type: 'POST',
                    data: { 
                        patient_id: currentPatientId,
                        action: 'delete_patient'
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response && response.success) {
                            // Hide the modal
                            const deleteModal = bootstrap.Modal.getInstance(document.getElementById('deleteModal'));
                            if (deleteModal) deleteModal.hide();
                            
                            // Show success message
                            const successAlert = `
                                <div class="alert alert-success alert-dismissible fade show" role="alert">
                                    <i class="fas fa-check-circle me-2"></i>
                                    ${response.message || 'Patient deleted successfully'}
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                            `;
                            
                            // Add the alert to the page
                            $('.main-content .container-fluid').prepend(successAlert);
                            
                            // Remove the deleted row from the table
                            $(`tr[data-patient-id="${currentPatientId}"]`).fadeOut(400, function() {
                                $(this).remove();
                                
                                // Update mobile view if needed
                                if ($(window).width() < 768) {
                                    updateMobileView();
                                }
                            });
                            
                            // Remove the deleted patient from the DataTable if it exists
                            if ($.fn.DataTable.isDataTable('#patientsTable')) {
                                var table = $('#patientsTable').DataTable();
                                table.row(`tr[data-patient-id="${currentPatientId}"]`).remove().draw(false);
                            }
                            
                            // Reset the currentPatientId
                            currentPatientId = null;
                        } else {
                            // Show error message
                            alert('Error: ' + (response ? response.message : 'Failed to delete patient'));
                            $button.prop('disabled', false).html(originalText);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Delete error:', error);
                        alert('Error: Unable to connect to the server. Please try again.');
                        $button.prop('disabled', false).html(originalText);
                    }
                });
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