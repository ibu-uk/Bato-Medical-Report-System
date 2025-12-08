<?php
// Include database configuration
require_once 'config/database.php';

// Start session if needed
session_start();

// Include authentication helpers for role checking
require_once 'config/auth.php';

// Initialize variables
$message = '';
$messageType = 'success';

// Check if form is submitted for updating test type
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_test'])) {
    // Sanitize input data
    $name = sanitize($_POST['name']);
    $unit = sanitize($_POST['unit']);
    $normal_range = sanitize($_POST['normal_range']);
    $test_id = sanitize($_POST['test_id']);
    
    if (empty($name) || empty($unit) || empty($normal_range)) {
        $message = "All fields are required";
        $messageType = 'danger';
    } else {
        // Update existing test type
        $query = "UPDATE test_types SET name = '$name', unit = '$unit', normal_range = '$normal_range' WHERE id = '$test_id'";
        $result = executeQuery($query);
        
        if ($result) {
            $_SESSION['success'] = "Test type updated successfully";
            header('Location: manage_test_types.php');
            exit();
        } else {
            $message = "Error updating test type";
            $messageType = 'danger';
        }
    }
}

// Check if edit request is made
$edit_mode = false;
$test_to_edit = null;
if (isset($_GET['edit']) && !empty($_GET['edit'])) {
    $edit_mode = true;
    $test_id = sanitize($_GET['edit']);
    
    // Get test details
    $query = "SELECT id, name, unit, normal_range FROM test_types WHERE id = '$test_id'";
    $result = executeQuery($query);
    
    if ($result && $result->num_rows > 0) {
        $test_to_edit = $result->fetch_assoc();
    } else {
        $_SESSION['error'] = "Test type not found";
        header('Location: manage_test_types.php');
        exit();
    }
}

// Check if delete request is made
if (isset($_GET['delete']) && !empty($_GET['delete'])) {
    $test_id = sanitize($_GET['delete']);
    
    // First, check if the test type is being used in any reports
    $check_query = "SELECT COUNT(*) as count FROM report_tests WHERE test_type_id = '$test_id'";
    $check_result = executeQuery($check_query);
    $row = $check_result->fetch_assoc();
    
    if ($row['count'] > 0) {
        $_SESSION['error'] = "Cannot delete test type as it is being used in existing reports";
    } else {
        // Delete test type if not in use
        $query = "DELETE FROM test_types WHERE id = '$test_id'";
        $result = executeQuery($query);
        
        if ($result) {
            $_SESSION['success'] = "Test type deleted successfully";
        } else {
            $_SESSION['error'] = "Error deleting test type";
        }
    }
    
    header('Location: manage_test_types.php');
    exit();
}

// Handle search functionality
$search_term = '';
$where_conditions = [];
$params = [];

if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search_term = sanitize($_GET['search']);
    $where_conditions[] = "(name LIKE '%$search_term%' OR unit LIKE '%$search_term%' OR normal_range LIKE '%$search_term%')";
}

// Build the base query
$base_query = "FROM test_types";
if (!empty($where_conditions)) {
    $base_query .= " WHERE " . implode(' AND ', $where_conditions);
}

// Get total count for pagination
$count_query = "SELECT COUNT(*) as total $base_query";
$count_result = executeQuery($count_query);
$total_records = $count_result->fetch_assoc()['total'];

// Pagination settings
$per_page = 10;
$total_pages = ceil($total_records / $per_page);
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $per_page;

// Get paginated results
$query = "SELECT id, name, unit, normal_range $base_query ORDER BY name LIMIT $offset, $per_page";
$result = executeQuery($query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Test Types - Bato Medical Report System</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Custom CSS -->
    <style>
        :root {
            --primary-color: #6c757d;  /* Changed to match the gray from the image */
            --secondary-color: #5a6268;  /* Slightly darker gray for hover states */
            --success-color: #28a745;
            --danger-color: #dc3545;
            --light-bg: #f8f9fa;
            --header-bg: #6c757d;  /* Main header background color */
            --header-text: #ffffff;  /* White text for header */
        }
        
        body {
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            line-height: 1.6;
            background-color: #f5f7fb;
            color: #333;
        }
        
        .page-header {
            background-color: var(--header-bg);
            color: var(--header-text);
            padding: 1.5rem 0;
            margin: -1.5rem -1.5rem 2rem -1.5rem;
            border-radius: 0;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.1);
            border-bottom: 1px solid rgba(0, 0, 0, 0.1);
        }
        
        .page-header h1 {
            font-weight: 600;
            margin: 0;
        }
        
        .card {
            border: 1px solid rgba(0, 0, 0, 0.125);
            border-radius: 0.25rem;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.05);
            margin-bottom: 1.5rem;
            transition: all 0.2s ease-in-out;
        }
        
        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.1);
        }
        
        .card-header {
            background-color: #fff;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            font-weight: 600;
            padding: 1rem 1.25rem;
            border-radius: 0.5rem 0.5rem 0 0 !important;
        }
        
        .table th {
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.5px;
            background-color: var(--header-bg);
            color: white;
            border-bottom: none;
            padding: 0.75rem 1rem;
        }
        
        .table td {
            vertical-align: middle;
        }
        
        .btn {
            font-weight: 500;
            padding: 0.5rem 1rem;
            border-radius: 0.375rem;
            transition: all 0.2s ease-in-out;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }
        
        .btn i {
            font-size: 0.9em;
        }
        
        .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
        }
        
        .pagination .page-link {
            color: var(--header-bg);
            border: 1px solid #dee2e6;
            margin: 0 2px;
            border-radius: 0.25rem;
        }
        
        .pagination .page-item.active .page-link {
            background-color: var(--header-bg);
            border-color: var(--header-bg);
            color: white;
        }
        
        .search-box {
            max-width: 500px;
            margin: 0 auto 2rem;
        }
        
        .no-results {
            text-align: center;
            padding: 3rem 1rem;
            color: #6c757d;
        }
        
        .no-results i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }
        
        /* Edit form modal */
        .modal-content {
            border: none;
            border-radius: 0.5rem;
            box-shadow: 0 0.5rem 1.5rem rgba(0, 0, 0, 0.2);
        }
        
        .modal-header {
            background-color: var(--header-bg);
            color: var(--header-text);
            border-radius: 0;
            padding: 1rem 1.5rem;
            border-bottom: 1px solid rgba(0, 0, 0, 0.1);
        }
        
        .modal-title {
            font-weight: 600;
        }
        
        .form-label {
            font-weight: 500;
            margin-bottom: 0.5rem;
        }
        
        .form-control, .form-select {
            padding: 0.5rem 0.75rem;
            border-radius: 0.375rem;
            border: 1px solid #ced4da;
            transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: #86b7fe;
            box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
        }
    </style>
</head>
<body>

<div class="container py-4">
    <div class="page-header mb-4">
        <div class="container d-flex justify-content-between align-items-center">
            <h1 class="mb-0">
                <i class="fas fa-flask me-2"></i>Manage Test Types
            </h1>
            <a href="index.php" class="btn btn-light">
                <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
            </a>
        </div>
    </div>
    
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php 
            echo $_SESSION['success'];
            unset($_SESSION['success']);
            ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php 
            echo $_SESSION['error'];
            unset($_SESSION['error']);
            ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($message)): ?>
        <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">
                <i class="fas fa-list-ul me-2"></i>Test Types List
            </h5>
            <a href="add_test_type.php" class="btn btn-primary btn-sm">
                <i class="fas fa-plus me-1"></i> Add New Test Type
            </a>
        </div>
        <div class="card-body">
            
            <!-- Search Form -->
            <div class="search-box mb-4">
                <form method="get" action="manage_test_types.php" class="needs-validation" novalidate>
                    <div class="input-group shadow-sm">
                        <span class="input-group-text bg-white border-end-0">
                            <i class="fas fa-search text-muted"></i>
                        </span>
                        <input type="text" 
                               class="form-control border-start-0 ps-0" 
                               name="search" 
                               placeholder="Search test types..." 
                               value="<?php echo htmlspecialchars($search_term ?? ''); ?>"
                               aria-label="Search test types">
                        <?php if (isset($_GET['search']) && !empty($_GET['search'])): ?>
                            <a href="manage_test_types.php" class="btn btn-outline-secondary" type="button">
                                <i class="fas fa-times"></i>
                            </a>
                        <?php endif; ?>
                        <button class="btn btn-primary" type="submit">
                            <i class="fas fa-search me-1"></i> Search
                        </button>
                    </div>
                </form>
            </div>
            
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Test Name</th>
                            <th>Unit</th>
                            <th>Normal Range</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result && $result->num_rows > 0): ?>
                            <?php while ($row = $result->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="bg-primary bg-opacity-10 p-2 rounded me-3">
                                                <i class="fas fa-flask text-primary"></i>
                                            </div>
                                            <div>
                                                <h6 class="mb-0"><?php echo htmlspecialchars($row['name']); ?></h6>
                                                <small class="text-muted">ID: <?php echo $row['id']; ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td><span class="badge bg-light text-dark"><?php echo htmlspecialchars($row['unit']); ?></span></td>
                                    <td><span class="badge bg-success bg-opacity-10 text-success"><?php echo htmlspecialchars($row['normal_range']); ?></span></td>
                                    <td class="text-end">
                                        <div class="btn-group" role="group">
                                            <button type="button" 
                                                    class="btn btn-sm btn-outline-primary"
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#editTestModal" 
                                                    data-id="<?php echo $row['id']; ?>"
                                                    data-name="<?php echo htmlspecialchars($row['name']); ?>"
                                                    data-unit="<?php echo htmlspecialchars($row['unit']); ?>"
                                                    data-range="<?php echo htmlspecialchars($row['normal_range']); ?>">
                                                <i class="fas fa-edit me-1"></i> Edit
                                            </button>
                                            <a href="manage_test_types.php?delete=<?php echo $row['id']; ?>" 
                                               class="btn btn-sm btn-outline-danger" 
                                               onclick="return confirm('Are you sure you want to delete this test type? This action cannot be undone.')">
                                                <i class="fas fa-trash-alt me-1"></i> Delete
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="text-center py-5">
                                    <div class="no-results">
                                        <i class="fas fa-inbox"></i>
                                        <h5>No test types found</h5>
                                        <p class="text-muted">
                                            <?php echo !empty($search_term) ? 'No results match your search criteria.' : 'Get started by adding a new test type.'; ?>
                                        </p>
                                        <?php if (empty($search_term)): ?>
                                            <a href="add_test_type.php" class="btn btn-primary mt-2">
                                                <i class="fas fa-plus me-1"></i> Add Test Type
                                            </a>
                                        <?php else: ?>
                                            <a href="manage_test_types.php" class="btn btn-outline-primary mt-2">
                                                <i class="fas fa-times me-1"></i> Clear Search
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                
                <?php if ($total_pages > 1): ?>
                <div class="d-flex justify-content-between align-items-center mt-4">
                    <div class="text-muted">
                        Showing page <?php echo $current_page; ?> of <?php echo $total_pages; ?>
                    </div>
                    <nav aria-label="Test types pagination">
                        <ul class="pagination mb-0">
                            <?php if ($current_page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=1<?php echo !empty($search_term) ? '&search=' . urlencode($search_term) : ''; ?>" aria-label="First">
                                        <i class="fas fa-angle-double-left"></i>
                                    </a>
                                </li>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo ($current_page - 1) . (!empty($search_term) ? '&search=' . urlencode($search_term) : ''); ?>" aria-label="Previous">
                                        <i class="fas fa-angle-left"></i>
                                    </a>
                                </li>
                            <?php endif; ?>
                            
                            <?php if ($current_page > 3): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=1<?php echo !empty($search_term) ? '&search=' . urlencode($search_term) : ''; ?>">1</a>
                                </li>
                                <?php if ($current_page > 4): ?>
                                    <li class="page-item disabled">
                                        <span class="page-link">...</span>
                                    </li>
                                <?php endif; ?>
                            <?php endif; ?>
                            
                            <?php
                            $start = max(1, $current_page - 1);
                            $end = min($total_pages, $current_page + 1);
                            
                            for ($i = $start; $i <= $end; $i++):
                                $active = ($i == $current_page) ? 'active' : '';
                            ?>
                                <li class="page-item <?php echo $active; ?>">
                                    <a class="page-link" href="?page=<?php echo $i . (!empty($search_term) ? '&search=' . urlencode($search_term) : ''); ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                            
                            <?php if ($current_page < $total_pages - 2): ?>
                                <?php if ($current_page < $total_pages - 3): ?>
                                    <li class="page-item disabled">
                                        <span class="page-link">...</span>
                                    </li>
                                <?php endif; ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $total_pages . (!empty($search_term) ? '&search=' . urlencode($search_term) : ''); ?>">
                                        <?php echo $total_pages; ?>
                                    </a>
                                </li>
                            <?php endif; ?>
                            
                            <?php if ($current_page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo ($current_page + 1) . (!empty($search_term) ? '&search=' . urlencode($search_term) : ''); ?>" aria-label="Next">
                                        <i class="fas fa-angle-right"></i>
                                    </a>
                                </li>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $total_pages . (!empty($search_term) ? '&search=' . urlencode($search_term) : ''); ?>" aria-label="Last">
                                        <i class="fas fa-angle-double-right"></i>
                                    </a>
                                </li>
                        <?php endif; ?>
                    </ul>
                    <p class="text-center text-muted">
                        Showing <?php echo ($offset + 1); ?> to <?php echo min($offset + $per_page, $total_records); ?> of <?php echo $total_records; ?> entries
                    </p>
                </nav>
                </ul>
                    </nav>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Edit Test Type Modal -->
<div class="modal fade" id="editTestModal" tabindex="-1" aria-labelledby="editTestModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editTestModalLabel">
                    <i class="fas fa-edit me-2"></i>Edit Test Type
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="editTestForm" method="post" action="manage_test_types.php" class="needs-validation" novalidate>
                <div class="modal-body">
                    <input type="hidden" name="test_id" id="edit_test_id">
                    <div class="mb-3">
                        <label for="edit_name" class="form-label">Test Name</label>
                        <input type="text" class="form-control" id="edit_name" name="name" required>
                        <div class="invalid-feedback">
                            Please provide a test name.
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="edit_unit" class="form-label">Unit</label>
                        <input type="text" class="form-control" id="edit_unit" name="unit" required>
                        <div class="invalid-feedback">
                            Please provide a unit of measurement.
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="edit_normal_range" class="form-label">Normal Range</label>
                        <input type="text" class="form-control" id="edit_normal_range" name="normal_range" required>
                        <div class="invalid-feedback">
                            Please provide the normal range for this test.
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i> Cancel
                    </button>
                    <button type="submit" name="update_test" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Bootstrap JS Bundle with Popper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
// Enable Bootstrap tooltips
var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
    return new bootstrap.Tooltip(tooltipTriggerEl);
});

// Initialize edit modal with test data
document.addEventListener('DOMContentLoaded', function() {
    var editTestModal = document.getElementById('editTestModal');
    if (editTestModal) {
        editTestModal.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget;
            var id = button.getAttribute('data-id');
            var name = button.getAttribute('data-name');
            var unit = button.getAttribute('data-unit');
            var range = button.getAttribute('data-range');
            
            var modalTitle = editTestModal.querySelector('.modal-title');
            var testIdInput = editTestModal.querySelector('#edit_test_id');
            var nameInput = editTestModal.querySelector('#edit_name');
            var unitInput = editTestModal.querySelector('#edit_unit');
            var rangeInput = editTestModal.querySelector('#edit_normal_range');
            
            modalTitle.textContent = 'Edit: ' + name;
            testIdInput.value = id;
            nameInput.value = name;
            unitInput.value = unit;
            rangeInput.value = range;
            
            // Reset validation
            var form = editTestModal.querySelector('.needs-validation');
            form.classList.remove('was-validated');
        });
    }
    
    // Enable form validation
    var forms = document.querySelectorAll('.needs-validation');
    Array.prototype.slice.call(forms).forEach(function(form) {
        form.addEventListener('submit', function(event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
    });
});

// Auto-dismiss alerts after 5 seconds
document.addEventListener('DOMContentLoaded', function() {
    var alerts = document.querySelectorAll('.alert-dismissible');
    alerts.forEach(function(alert) {
        setTimeout(function() {
            var bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        }, 5000);
    });
});
</script>

</body>
</html>
