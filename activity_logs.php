<?php
// Start session
session_start();

// Include timezone configuration
require_once 'config/timezone.php';

// Include database configuration
require_once 'config/database.php';

// Initialize database connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Include authentication helpers
require_once 'config/auth.php';

// Require admin role to access this page
requireRole('admin');

// Set default filters
$userId = isset($_GET['user_id']) ? sanitize($_GET['user_id']) : '';
$activityType = isset($_GET['activity_type']) ? sanitize($_GET['activity_type']) : '';
$startDate = isset($_GET['start_date']) ? sanitize($_GET['start_date']) : date('Y-m-d', strtotime('-7 days'));
$endDate = isset($_GET['end_date']) ? sanitize($_GET['end_date']) : date('Y-m-d');

// Build query with filters
$query = "SELECT l.*, u.username, u.full_name, u.role 
          FROM user_activity_log l
          LEFT JOIN users u ON l.user_id = u.id
          WHERE 1=1";

$params = array();
$types = "";

if (!empty($userId)) {
    $query .= " AND l.user_id = ?";
    $params[] = $userId;
    $types .= "i";
}

if (!empty($activityType)) {
    $query .= " AND l.activity_type = ?";
    $params[] = $activityType;
    $types .= "s";
}

if (!empty($startDate)) {
    $query .= " AND DATE(l.created_at) >= ?";
    $params[] = $startDate;
    $types .= "s";
}

if (!empty($endDate)) {
    $query .= " AND DATE(l.created_at) <= ?";
    $params[] = $endDate;
    $types .= "s";
}

$query .= " ORDER BY l.created_at DESC LIMIT 1000";

// Execute query
$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// Using executeQuery function from database.php

// Get all users for filter dropdown
$usersQuery = "SELECT id, username, full_name FROM users ORDER BY username";
$usersResult = executeQuery($usersQuery);
$users = array();
while ($row = $usersResult->fetch_assoc()) {
    $users[] = $row;
}

// Get distinct activity types for filter dropdown
$activityTypesQuery = "SELECT DISTINCT activity_type FROM user_activity_log ORDER BY activity_type";
$activityTypesResult = executeQuery($activityTypesQuery);
$activityTypes = array();
while ($row = $activityTypesResult->fetch_assoc()) {
    $activityTypes[] = $row['activity_type'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Activity Logs - Bato Medical Report System</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
    <!-- DateRangePicker CSS -->
    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.css" />
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php">Bato Medical Report System</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="reports.php">Reports</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="prescriptions.php">Prescriptions</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="nurse_treatments.php">Nurse Treatments</a>
                    </li>
                    <?php if (hasRole(['admin'])): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="manage_doctors.php">Doctors</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="manage_test_types.php">Test Types</a>
                    </li>
                    <?php endif; ?>
                    <li class="nav-item">
                        <a class="nav-link" href="add_patient.php">Add Patient</a>
                    </li>
                    <?php if (hasRole(['admin'])): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="manage_users.php">Users</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="activity_logs.php">Activity Logs</a>
                    </li>
                    <?php endif; ?>
                </ul>
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($_SESSION['full_name']); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                            <li><a class="dropdown-item" href="profile.php"><i class="fas fa-id-card"></i> Profile</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container my-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="fas fa-history"></i> User Activity Logs</h2>
        </div>
        
        <!-- Filters -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-filter"></i> Filter Activity Logs</h5>
            </div>
            <div class="card-body">
                <form method="get" action="activity_logs.php" id="filterForm">
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <label for="user_id" class="form-label">User</label>
                            <select class="form-select" id="user_id" name="user_id">
                                <option value="">All Users</option>
                                <?php foreach ($users as $user): ?>
                                <option value="<?php echo $user['id']; ?>" <?php echo $userId == $user['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($user['username'] . ' (' . $user['full_name'] . ')'); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="activity_type" class="form-label">Activity Type</label>
                            <select class="form-select" id="activity_type" name="activity_type">
                                <option value="">All Activities</option>
                                <?php foreach ($activityTypes as $type): ?>
                                <option value="<?php echo $type; ?>" <?php echo $activityType == $type ? 'selected' : ''; ?>>
                                    <?php echo ucwords(str_replace('_', ' ', $type)); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="date_range" class="form-label">Date Range</label>
                            <div class="input-group">
                                <input type="text" class="form-control" id="date_range" name="date_range">
                                <input type="hidden" id="start_date" name="start_date" value="<?php echo $startDate; ?>">
                                <input type="hidden" id="end_date" name="end_date" value="<?php echo $endDate; ?>">
                                <span class="input-group-text"><i class="fas fa-calendar"></i></span>
                            </div>
                        </div>
                        <div class="col-md-2 mb-3 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-search"></i> Apply Filters
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Activity Logs Table -->
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table id="activityTable" class="table table-striped table-bordered">
                        <thead>
                            <tr>
                                <th>Timestamp</th>
                                <th>User</th>
                                <th>Activity Type</th>
                                <th>Entity</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo date('d/m/Y H:i:s', strtotime($row['created_at'])); ?></td>
                                <td><?php echo htmlspecialchars($row['full_name']); ?> (<?php echo htmlspecialchars($row['username']); ?>)</td>
                                <td>
                                    <?php 
                                    $activityTypeDisplay = str_replace('_', ' ', ucfirst($row['activity_type']));
                                    
                                    // Add icons based on activity type
                                    $icon = match($row['activity_type']) {
                                        'login' => '<i class="fas fa-sign-in-alt text-success"></i>',
                                        'logout' => '<i class="fas fa-sign-out-alt text-danger"></i>',
                                        'create_report' => '<i class="fas fa-file-medical text-primary"></i>',
                                        'view_report' => '<i class="fas fa-eye text-info"></i>',
                                        'print_report' => '<i class="fas fa-print text-secondary"></i>',
                                        'add_patient' => '<i class="fas fa-user-plus text-success"></i>',
                                        'edit_patient' => '<i class="fas fa-user-edit text-warning"></i>',
                                        'delete_report' => '<i class="fas fa-trash text-danger"></i>',
                                        'delete_patient' => '<i class="fas fa-user-times text-danger"></i>',
                                        'search_patient' => '<i class="fas fa-search text-info"></i>',
                                        'edit_test_type' => '<i class="fas fa-vial text-warning"></i>',
                                        'view_test_types' => '<i class="fas fa-vials text-info"></i>',
                                        'export_report' => '<i class="fas fa-file-export text-primary"></i>',
                                        'import_data' => '<i class="fas fa-file-import text-primary"></i>',
                                        'view_logs' => '<i class="fas fa-history text-secondary"></i>',
                                        default => '<i class="fas fa-history"></i>'
                                    };
                                    
                                    echo $icon . ' ' . $activityTypeDisplay;
                                    ?>
                                </td>
                                <td>
                                    <?php if ($row['entity_id'] || (isset($row['entity_name']) && $row['entity_name'])): ?>
                                        <?php if ($row['entity_id']): ?>
                                            <strong>ID:</strong> <?php echo htmlspecialchars($row['entity_id']); ?>
                                        <?php endif; ?>
                                        <?php if ($row['entity_id'] && isset($row['entity_name']) && $row['entity_name']): ?>
                                            <br>
                                        <?php endif; ?>
                                        <?php if (isset($row['entity_name']) && $row['entity_name']): ?>
                                            <strong>Name:</strong> <?php echo htmlspecialchars($row['entity_name']); ?>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-dark text-white py-3 mt-4">
        <div class="container text-center">
            <p class="mb-0">© <?php echo date('Y'); ?> Bato Medical Report System. All rights reserved.</p>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
    <!-- Moment.js -->
    <script type="text/javascript" src="https://cdn.jsdelivr.net/momentjs/latest/moment.min.js"></script>
    <!-- DateRangePicker -->
    <script type="text/javascript" src="https://cdn.jsdelivr.net/npm/daterangepicker/daterangepicker.min.js"></script>
    
    <script>
        $(document).ready(function() {
            // Initialize DataTable
            $('#activityTable').DataTable({
                order: [[0, 'desc']],
                pageLength: 25,
                lengthMenu: [10, 25, 50, 100]
            });
            
            // Initialize DateRangePicker
            $('#date_range').daterangepicker({
                startDate: moment('<?php echo $startDate; ?>'),
                endDate: moment('<?php echo $endDate; ?>'),
                ranges: {
                   'Today': [moment(), moment()],
                   'Yesterday': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
                   'Last 7 Days': [moment().subtract(6, 'days'), moment()],
                   'Last 30 Days': [moment().subtract(29, 'days'), moment()],
                   'This Month': [moment().startOf('month'), moment().endOf('month')],
                   'Last Month': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')]
                },
                locale: {
                    format: 'YYYY-MM-DD'
                }
            }, function(start, end, label) {
                $('#start_date').val(start.format('YYYY-MM-DD'));
                $('#end_date').val(end.format('YYYY-MM-DD'));
            });
        });
    </script>
</body>
</html>
