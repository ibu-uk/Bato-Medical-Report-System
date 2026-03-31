<?php
session_start();

require_once 'config/timezone.php';
require_once 'config/database.php';
require_once 'config/auth.php';
require_once 'config/support.php';

requireLogin();

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

ensureSupportTables($conn);

$isManager = supportCanManageTickets();
$currentUserId = (int)($_SESSION['user_id'] ?? 0);

$statusFilter = trim($_GET['status'] ?? '');
$userFilterId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
$dateFrom = trim($_GET['date_from'] ?? '');
$dateTo = trim($_GET['date_to'] ?? '');
$presetFilter = trim($_GET['preset'] ?? '');
$sortFilter = trim($_GET['sort'] ?? 'newest');

$allowedPresets = ['', 'today', 'this_week'];
if (!in_array($presetFilter, $allowedPresets, true)) {
    $presetFilter = '';
}

$allowedSorts = ['newest', 'oldest', 'urgent'];
if (!in_array($sortFilter, $allowedSorts, true)) {
    $sortFilter = 'newest';
}

if ($presetFilter === 'today') {
    $today = date('Y-m-d');
    $dateFrom = $today;
    $dateTo = $today;
} elseif ($presetFilter === 'this_week') {
    $dateFrom = date('Y-m-d', strtotime('monday this week'));
    $dateTo = date('Y-m-d', strtotime('sunday this week'));
}

$allowedStatuses = ['open', 'in_progress', 'resolved', 'closed'];
if ($statusFilter !== '' && !in_array($statusFilter, $allowedStatuses, true)) {
    $statusFilter = '';
}

if ($dateFrom !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
    $dateFrom = '';
}
if ($dateTo !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
    $dateTo = '';
}

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) {
    $page = 1;
}
$perPage = 20;

$where = [];
$paramTypes = '';
$params = [];

if ($isManager) {
    if ($userFilterId > 0) {
        $where[] = 'st.user_id = ?';
        $paramTypes .= 'i';
        $params[] = $userFilterId;
    }
} else {
    $where[] = 'st.user_id = ?';
    $paramTypes .= 'i';
    $params[] = $currentUserId;
}

if ($statusFilter !== '') {
    $where[] = 'st.status = ?';
    $paramTypes .= 's';
    $params[] = $statusFilter;
}

if ($dateFrom !== '') {
    $where[] = 'DATE(st.created_at) >= ?';
    $paramTypes .= 's';
    $params[] = $dateFrom;
}

if ($dateTo !== '') {
    $where[] = 'DATE(st.created_at) <= ?';
    $paramTypes .= 's';
    $params[] = $dateTo;
}

$whereSql = !empty($where) ? ('WHERE ' . implode(' AND ', $where)) : '';

$bindParams = function($stmt, $types, &$values) {
    if ($types === '') {
        return;
    }

    $refs = [];
    $refs[] = $types;
    foreach ($values as $k => $v) {
        $refs[] = &$values[$k];
    }
    call_user_func_array([$stmt, 'bind_param'], $refs);
};

$totalTickets = 0;
$countQuery = "SELECT COUNT(*) AS total FROM support_tickets st $whereSql";
$countStmt = $conn->prepare($countQuery);
if ($countStmt) {
    $countParams = $params;
    $bindParams($countStmt, $paramTypes, $countParams);
    $countStmt->execute();
    $countResult = $countStmt->get_result();
    if ($countResult && $countRow = $countResult->fetch_assoc()) {
        $totalTickets = (int)$countRow['total'];
    }
    $countStmt->close();
}

$totalPages = max(1, (int)ceil($totalTickets / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $perPage;

$users = [];
if ($isManager) {
    $usersResult = $conn->query('SELECT id, full_name FROM users ORDER BY full_name ASC');
    if ($usersResult) {
        while ($userRow = $usersResult->fetch_assoc()) {
            $users[] = $userRow;
        }
    }
}

$orderBySql = 'st.created_at DESC';
if ($sortFilter === 'oldest') {
    $orderBySql = 'st.created_at ASC';
} elseif ($sortFilter === 'urgent') {
    $orderBySql = "FIELD(st.priority, 'urgent', 'high', 'medium', 'low'), st.created_at DESC";
}

$tickets = false;
$query = "SELECT st.*, u.full_name AS requester_name, a.full_name AS assignee_name
          FROM support_tickets st
          JOIN users u ON u.id = st.user_id
          LEFT JOIN users a ON a.id = st.assigned_to
          $whereSql
          ORDER BY $orderBySql
          LIMIT ? OFFSET ?";

$stmt = $conn->prepare($query);
if ($stmt) {
    $ticketTypes = $paramTypes . 'ii';
    $ticketParams = $params;
    $ticketParams[] = $perPage;
    $ticketParams[] = $offset;
    $bindParams($stmt, $ticketTypes, $ticketParams);
    $stmt->execute();
    $tickets = $stmt->get_result();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Support Center - Bato Medical Report System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include_once 'includes/sidebar.php'; ?>

    <nav class="top-navbar">
        <div class="container-fluid">
            <div class="d-flex align-items-center justify-content-between w-100">
                <button class="btn btn-link d-md-none" id="mobileMenuToggle">
                    <i class="fas fa-bars"></i>
                </button>
                <h5 class="mb-0">Support Center</h5>
                <span class="badge bg-secondary"><?php echo $isManager ? 'Support Team View' : 'My Requests'; ?></span>
            </div>
        </div>
    </nav>

    <div class="main-content">
        <div class="container-fluid py-4">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-headset me-2"></i>Support Tickets</h5>
                    <button class="btn btn-primary btn-sm" id="openSupportModalBtn">
                        <i class="fas fa-plus me-1"></i> New Ticket
                    </button>
                </div>
                <div class="card-body">
                    <?php if (isset($_SESSION['success'])): ?>
                        <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
                    <?php endif; ?>
                    <?php if (isset($_SESSION['error'])): ?>
                        <div class="alert alert-danger"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
                    <?php endif; ?>

                    <form method="get" class="row g-2 align-items-end mb-3">
                        <div class="col-md-2">
                            <label class="form-label">From Date</label>
                            <input type="date" class="form-control" name="date_from" value="<?php echo htmlspecialchars($dateFrom); ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">To Date</label>
                            <input type="date" class="form-control" name="date_to" value="<?php echo htmlspecialchars($dateTo); ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status">
                                <option value="">All</option>
                                <option value="open" <?php echo $statusFilter === 'open' ? 'selected' : ''; ?>>Open</option>
                                <option value="in_progress" <?php echo $statusFilter === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                <option value="resolved" <?php echo $statusFilter === 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                                <option value="closed" <?php echo $statusFilter === 'closed' ? 'selected' : ''; ?>>Closed</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Quick Range</label>
                            <select class="form-select" name="preset">
                                <option value="" <?php echo $presetFilter === '' ? 'selected' : ''; ?>>Any</option>
                                <option value="today" <?php echo $presetFilter === 'today' ? 'selected' : ''; ?>>Today</option>
                                <option value="this_week" <?php echo $presetFilter === 'this_week' ? 'selected' : ''; ?>>This Week</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Sort</label>
                            <select class="form-select" name="sort">
                                <option value="newest" <?php echo $sortFilter === 'newest' ? 'selected' : ''; ?>>Newest</option>
                                <option value="oldest" <?php echo $sortFilter === 'oldest' ? 'selected' : ''; ?>>Oldest</option>
                                <option value="urgent" <?php echo $sortFilter === 'urgent' ? 'selected' : ''; ?>>Urgent First</option>
                            </select>
                        </div>

                        <?php if ($isManager): ?>
                        <div class="col-md-2">
                            <label class="form-label">User</label>
                            <select class="form-select" name="user_id">
                                <option value="0">All Users</option>
                                <?php foreach ($users as $user): ?>
                                    <option value="<?php echo (int)$user['id']; ?>" <?php echo $userFilterId === (int)$user['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($user['full_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php endif; ?>

                        <div class="col-md-2 d-flex gap-2">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-filter me-1"></i> Filter
                            </button>
                            <a href="support_center.php" class="btn btn-outline-secondary w-100">Reset</a>
                        </div>
                    </form>

                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Subject</th>
                                    <?php if ($isManager): ?><th>Requested By</th><?php endif; ?>
                                    <th>Attachment</th>
                                    <th>Type</th>
                                    <th>Priority</th>
                                    <th>Status</th>
                                    <th>Created</th>
                                    <?php if ($isManager): ?><th>Action</th><?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if ($tickets && $tickets->num_rows > 0): ?>
                                <?php while ($ticket = $tickets->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo (int)$ticket['id']; ?></td>
                                    <td>
                                        <div class="fw-semibold"><?php echo htmlspecialchars($ticket['subject']); ?></div>
                                        <div class="text-muted small"><?php echo htmlspecialchars($ticket['details']); ?></div>
                                    </td>
                                    <?php if ($isManager): ?>
                                    <td><?php echo htmlspecialchars($ticket['requester_name']); ?></td>
                                    <?php endif; ?>
                                    <td>
                                        <?php if (!empty($ticket['attachment_path'])): ?>
                                            <a class="btn btn-sm btn-outline-secondary" href="<?php echo htmlspecialchars($ticket['attachment_path']); ?>" target="_blank" rel="noopener">
                                                <i class="fas fa-paperclip me-1"></i>
                                                <?php echo htmlspecialchars($ticket['attachment_name'] ?: 'View file'); ?>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="badge text-bg-light border"><?php echo htmlspecialchars($ticket['issue_type']); ?></span></td>
                                    <td><span class="badge bg-<?php echo $ticket['priority'] === 'urgent' ? 'danger' : ($ticket['priority'] === 'high' ? 'warning text-dark' : 'secondary'); ?>"><?php echo htmlspecialchars($ticket['priority']); ?></span></td>
                                    <td><span class="badge bg-<?php echo $ticket['status'] === 'open' ? 'primary' : ($ticket['status'] === 'in_progress' ? 'warning text-dark' : ($ticket['status'] === 'resolved' ? 'success' : 'dark')); ?>"><?php echo htmlspecialchars($ticket['status']); ?></span></td>
                                    <td><?php echo date('Y-m-d H:i', strtotime($ticket['created_at'])); ?></td>
                                    <?php if ($isManager): ?>
                                    <td>
                                        <form method="post" action="update_support_ticket.php" class="d-flex gap-2">
                                            <input type="hidden" name="ticket_id" value="<?php echo (int)$ticket['id']; ?>">
                                            <select name="status" class="form-select form-select-sm" required>
                                                <option value="open" <?php echo $ticket['status'] === 'open' ? 'selected' : ''; ?>>Open</option>
                                                <option value="in_progress" <?php echo $ticket['status'] === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                                <option value="resolved" <?php echo $ticket['status'] === 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                                                <option value="closed" <?php echo $ticket['status'] === 'closed' ? 'selected' : ''; ?>>Closed</option>
                                            </select>
                                            <button class="btn btn-sm btn-outline-primary" type="submit">Save</button>
                                        </form>
                                    </td>
                                    <?php endif; ?>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="<?php echo $isManager ? 9 : 7; ?>" class="text-center text-muted py-4">
                                        No support tickets found.
                                    </td>
                                </tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="d-flex flex-wrap justify-content-between align-items-center mt-3 gap-2">
                        <div class="text-muted small">
                            <?php
                                $start = $totalTickets > 0 ? ($offset + 1) : 0;
                                $end = min($offset + $perPage, $totalTickets);
                                echo 'Showing ' . $start . ' - ' . $end . ' of ' . $totalTickets . ' tickets';
                            ?>
                        </div>

                        <?php if ($totalPages > 1): ?>
                        <nav>
                            <ul class="pagination pagination-sm mb-0">
                                <?php
                                    $baseFilters = [
                                        'status' => $statusFilter,
                                        'date_from' => $dateFrom,
                                        'date_to' => $dateTo,
                                        'preset' => $presetFilter,
                                        'sort' => $sortFilter
                                    ];
                                    if ($isManager && $userFilterId > 0) {
                                        $baseFilters['user_id'] = $userFilterId;
                                    }

                                    $startPage = max(1, $page - 2);
                                    $endPage = min($totalPages, $page + 2);
                                ?>
                                <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($baseFilters, ['page' => max(1, $page - 1)])); ?>">Prev</a>
                                </li>
                                <?php for ($p = $startPage; $p <= $endPage; $p++): ?>
                                <li class="page-item <?php echo $p === $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($baseFilters, ['page' => $p])); ?>"><?php echo $p; ?></a>
                                </li>
                                <?php endfor; ?>
                                <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($baseFilters, ['page' => min($totalPages, $page + 1)])); ?>">Next</a>
                                </li>
                            </ul>
                        </nav>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/sidebar.js"></script>
    <script>
    document.getElementById('mobileMenuToggle')?.addEventListener('click', function() {
        document.body.classList.toggle('sidebar-collapsed');
    });

    document.getElementById('openSupportModalBtn')?.addEventListener('click', function() {
        const helpLink = document.getElementById('helpCenterLink');
        if (helpLink) {
            helpLink.click();
        }
    });
    </script>
</body>
</html>
<?php
$conn->close();
