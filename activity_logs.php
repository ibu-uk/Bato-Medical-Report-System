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
// Use only the specified activity types for filter and display
$activityTypes = [
    'login','logout','create_report','view_report','print_report',
    'create_prescription','view_prescription','print_prescription',
    'create_treatment','view_treatment','print_treatment',
    'add_patient','edit_patient','delete_report','edit_report',
    'add_prescription','delete_prescription','edit_prescription',
    'add_nurse_treatment','edit_nurse_treatment',
    'generate_link'
];
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
<body style="padding: 0; margin: 0;">

    <!-- Main Content - Full Width -->
    <div class="container-fluid p-4">
        <!-- Header Section -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <a href="index.php" class="btn btn-outline-secondary btn-sm mb-2">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
                <h2 class="mb-0"><i class="fas fa-history"></i> User Activity Logs</h2>
            </div>
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
                            <button type="submit" class="btn btn-primary w-100 me-2">
                                <i class="fas fa-search"></i> Apply Filters
                            </button>
                            <button type="button" class="btn btn-danger w-100" onclick="exportToPDF()">
                                <i class="fas fa-file-pdf"></i> Export PDF
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
                                    // Normalize activity type; fallback based on details for generate link
                                    $rawType = $row['activity_type'] ?? '';
                                    if (($rawType === '' || $rawType === null) && !empty($row['details']) && str_contains($row['details'], 'Generated patient dashboard link')) {
                                        $rawType = 'generate_link';
                                    }

                                    $activityTypeDisplay = str_replace('_', ' ', ucfirst($rawType));
                                    
                                    // Add icons based on activity type
                                    $icon = match($rawType) {
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
                                        'generate_link' => '<i class="fas fa-link text-primary"></i>',
                                        default => '<i class="fas fa-history"></i>'
                                    };
                                    
                                    echo $icon . ' ' . $activityTypeDisplay;
                                    ?>
                                </td>
                                <td>
    <?php
    // Show patient name for report-related actions
    $reportActions = ['view_report', 'print_report', 'delete_report'];
    if (in_array($row['activity_type'], $reportActions) && $row['entity_id']) {
        $patientName = '';
        // Use entity_name if already present, otherwise fetch from DB
        if (isset($row['entity_name']) && $row['entity_name']) {
            $patientName = $row['entity_name'];
        } else {
            // Query patient name from reports table
            $stmtPatient = $conn->prepare("SELECT p.name FROM reports r JOIN patients p ON r.patient_id = p.id WHERE r.id = ? LIMIT 1");
            $stmtPatient->bind_param("i", $row['entity_id']);
            $stmtPatient->execute();
            $stmtPatient->bind_result($patientNameResult);
            if ($stmtPatient->fetch()) {
                $patientName = $patientNameResult;
            }
            $stmtPatient->close();
        }
        echo '<strong>ID:</strong> ' . htmlspecialchars($row['entity_id']);
        if ($patientName) {
            echo '<br><strong>Name:</strong> ' . htmlspecialchars($patientName);
        }
    } elseif ($row['entity_id'] || (isset($row['entity_name']) && $row['entity_name'])) {
        if ($row['entity_id']) {
            echo '<strong>ID:</strong> ' . htmlspecialchars($row['entity_id']);
        }
        if ($row['entity_id'] && isset($row['entity_name']) && $row['entity_name']) {
            echo '<br>';
        }
        if (isset($row['entity_name']) && $row['entity_name']) {
            echo '<strong>Name:</strong> ' . htmlspecialchars($row['entity_name']);
        }
    } else {
        echo '-';
    }
    ?>
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
        
        // Export to PDF function
        function exportToPDF() {
            // Get current filter values
            var userId = $('#user_id').val();
            var activityType = $('#activity_type').val();
            var startDate = $('#start_date').val();
            var endDate = $('#end_date').val();
            
            // Build URL with current filters
            var url = 'export_logs_pdf.php?' + 
                     'user_id=' + encodeURIComponent(userId) + 
                     '&activity_type=' + encodeURIComponent(activityType) + 
                     '&start_date=' + encodeURIComponent(startDate) + 
                     '&end_date=' + encodeURIComponent(endDate);
            
            // Open PDF in new window
            window.open(url, '_blank');
        }
    </script>
</body>
</html>
