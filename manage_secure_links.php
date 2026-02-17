<?php
/**
 * Secure Links Management Interface
 * Allows staff to view, manage, and revoke secure report links
 */

// Start session
session_start();

// Include required files
require_once 'config/timezone.php';
require_once 'config/database.php';
require_once 'config/auth.php';
require_once 'config/secure_links.php';

// Require admin role to access this page
requireRole('admin');

// Handle actions
if (isset($_POST['action'])) {
    $action = $_POST['action'];
    
    switch ($action) {
        case 'revoke':
            $token = $_POST['token'] ?? '';
            if (revokeReportLink($token)) {
                $success = "Secure link has been revoked successfully.";
            } else {
                $error = "Failed to revoke secure link.";
            }
            break;
            
        case 'cleanup':
            $cleaned = cleanupExpiredTokens();
            $success = "Cleaned up $cleaned expired tokens.";
            break;
    }
}

// Get all active links with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;

$conn = getDbConnection();

// Count total active links
$countQuery = "SELECT COUNT(*) as total FROM report_links WHERE expiry_date > NOW()";
$countResult = $conn->query($countQuery);
$totalLinks = $countResult->fetch_assoc()['total'];
$totalPages = ceil($totalLinks / $perPage);

// Get active links with patient info
$query = "SELECT rl.*, p.name as patient_name, p.civil_id 
          FROM report_links rl 
          JOIN patients p ON rl.patient_id = p.id 
          WHERE rl.expiry_date > NOW() 
          ORDER BY rl.created_at DESC 
          LIMIT ? OFFSET ?";

$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $perPage, $offset);
$stmt->execute();
$result = $stmt->get_result();

$links = [];
while ($row = $result->fetch_assoc()) {
    $links[] = $row;
}

$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Secure Links - Bato Medical Report System</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="container-fluid p-4">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <a href="index.php" class="btn btn-secondary btn-sm mb-2">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
                <h2 class="mb-0"><i class="fas fa-link"></i> Manage Secure Report Links</h2>
            </div>
            <div>
                <button class="btn btn-warning" onclick="cleanupExpired()">
                    <i class="fas fa-trash"></i> Cleanup Expired
                </button>
            </div>
        </div>

        <!-- Alerts -->
        <?php if (isset($success)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <h5 class="card-title"><i class="fas fa-link"></i> Active Links</h5>
                        <h3><?php echo $totalLinks; ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <h5 class="card-title"><i class="fas fa-clock"></i> Valid for 48 Hours</h5>
                        <p class="mb-0">All links expire automatically</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card bg-info text-white">
                    <div class="card-body">
                        <h5 class="card-title"><i class="fas fa-shield-alt"></i> Secure Access</h5>
                        <p class="mb-0">64-character tokens</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Links Table -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Active Secure Links</h5>
            </div>
            <div class="card-body">
                <?php if (count($links) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Patient</th>
                                    <th>Token</th>
                                    <th>Created</th>
                                    <th>Expires</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($links as $link): ?>
                                    <tr>
                                        <td>
                                            <div>
                                                <strong><?php echo htmlspecialchars($link['patient_name']); ?></strong><br>
                                                <small class="text-muted">ID: <?php echo $link['civil_id']; ?></small>
                                            </div>
                                        </td>
                                        <td>
                                            <code class="small"><?php echo substr($link['token'], 0, 16); ?>...</code>
                                            <button class="btn btn-sm btn-outline-secondary ms-1" onclick="copyToken('<?php echo $link['token']; ?>')">
                                                <i class="fas fa-copy"></i>
                                            </button>
                                        </td>
                                        <td><?php echo date('M j, Y H:i', strtotime($link['created_at'])); ?></td>
                                        <td>
                                            <?php 
                                            $expiry = strtotime($link['expiry_date']);
                                            $now = time();
                                            $hoursLeft = ($expiry - $now) / 3600;
                                            
                                            if ($hoursLeft < 24) {
                                                echo '<span class="text-danger">' . date('M j, Y H:i', $expiry) . '</span>';
                                            } else {
                                                echo date('M j, Y H:i', $expiry);
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <?php if ($link['is_used']): ?>
                                                <span class="badge bg-warning">Used</span>
                                            <?php else: ?>
                                                <span class="badge bg-success">Active</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-info" onclick="testLink('<?php echo $link['token']; ?>')">
                                                <i class="fas fa-external-link-alt"></i> Test
                                            </button>
                                            <button class="btn btn-sm btn-danger" onclick="revokeLink('<?php echo $link['token']; ?>')">
                                                <i class="fas fa-ban"></i> Revoke
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                        <nav>
                            <ul class="pagination">
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $page - 1; ?>">Previous</a>
                                    </li>
                                <?php endif; ?>
                                
                                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                    </li>
                                <?php endfor; ?>
                                
                                <?php if ($page < $totalPages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $page + 1; ?>">Next</a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="fas fa-link fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">No Active Secure Links</h5>
                        <p class="text-muted">Secure links will appear here when generated.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Copy token to clipboard
        function copyToken(token) {
            navigator.clipboard.writeText(token).then(function() {
                alert('Token copied to clipboard!');
            });
        }
        
        // Test secure link
        function testLink(token) {
            window.open('report.php?token=' + token, '_blank');
        }
        
        // Revoke link
        function revokeLink(token) {
            if (confirm('Are you sure you want to revoke this secure link? This action cannot be undone.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="revoke">
                    <input type="hidden" name="token" value="${token}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        // Cleanup expired links
        function cleanupExpired() {
            if (confirm('Are you sure you want to clean up all expired links?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = '<input type="hidden" name="action" value="cleanup">';
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html>
