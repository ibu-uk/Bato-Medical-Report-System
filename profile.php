<?php
// Start session
session_start();

// Include timezone configuration
require_once 'config/timezone.php';

// Include database configuration
require_once 'config/database.php';

// Include authentication helpers
require_once 'config/auth.php';

// Require login to access this page
requireLogin();

// Get current user information
$user = getCurrentUser();

// Process form submission
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = sanitize($_POST['full_name']);
    $email = sanitize($_POST['email']);
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    // Validate input
    if (empty($fullName) || empty($email)) {
        $message = "Name and email are required";
        $messageType = "danger";
    } else {
        // Check if changing password
        if (!empty($newPassword)) {
            // Verify current password
            if (empty($currentPassword) || !password_verify($currentPassword, $user['password'])) {
                $message = "Current password is incorrect";
                $messageType = "danger";
            } elseif ($newPassword !== $confirmPassword) {
                $message = "New passwords do not match";
                $messageType = "danger";
            } elseif (strlen($newPassword) < 6) {
                $message = "New password must be at least 6 characters";
                $messageType = "danger";
            } else {
                // Update user with new password
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                $updateQuery = "UPDATE users SET full_name = ?, email = ?, password = ? WHERE id = ?";
                $stmt = $conn->prepare($updateQuery);
                $stmt->bind_param("sssi", $fullName, $email, $hashedPassword, $_SESSION['user_id']);
                
                if ($stmt->execute()) {
                    $message = "Profile updated successfully with new password";
                    $messageType = "success";
                    
                    // Update session data
                    $_SESSION['full_name'] = $fullName;
                    
                    // Refresh user data
                    $user = getCurrentUser();
                } else {
                    $message = "Error updating profile: " . $conn->error;
                    $messageType = "danger";
                }
            }
        } else {
            // Update user without changing password
            $updateQuery = "UPDATE users SET full_name = ?, email = ? WHERE id = ?";
            $stmt = $conn->prepare($updateQuery);
            $stmt->bind_param("ssi", $fullName, $email, $_SESSION['user_id']);
            
            if ($stmt->execute()) {
                $message = "Profile updated successfully";
                $messageType = "success";
                
                // Update session data
                $_SESSION['full_name'] = $fullName;
                
                // Refresh user data
                $user = getCurrentUser();
            } else {
                $message = "Error updating profile: " . $conn->error;
                $messageType = "danger";
            }
        }
    }
}

// Get user activity log
$activityQuery = "SELECT * FROM user_activity_log WHERE user_id = ? ORDER BY created_at DESC LIMIT 20";
$stmt = $conn->prepare($activityQuery);
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$activityResult = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Profile - Bato Medical Report System</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="index.php">Bato Medical Report System</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="reports.php">Reports</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="prescriptions.php"><i class="fas fa-prescription"></i> Prescriptions</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="nurse_treatments.php"><i class="fas fa-user-nurse"></i> Nurse Treatments</a>
                    </li>
                    <?php if (hasRole(['admin'])): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="manage_doctors.php">Doctors</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="manage_test_types.php"><i class="fas fa-vial"></i> Test Types</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="manage_users.php"><i class="fas fa-users-cog"></i> Users</a>
                    </li>
                    <?php endif; ?>
                    <li class="nav-item">
                        <a class="nav-link" href="add_patient.php"><i class="fas fa-user-plus"></i> Add Patient</a>
                    </li>
                </ul>
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle active" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($_SESSION['full_name']); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                            <li><a class="dropdown-item active" href="profile.php"><i class="fas fa-id-card"></i> Profile</a></li>
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
        <div class="row">
            <div class="col-md-4">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">User Profile</h5>
                    </div>
                    <div class="card-body">
                        <div class="text-center mb-4">
                            <div class="avatar-circle mb-3">
                                <span class="initials"><?php echo strtoupper(substr($user['full_name'], 0, 1)); ?></span>
                            </div>
                            <h4><?php echo htmlspecialchars($user['full_name']); ?></h4>
                            <span class="badge <?php 
                                echo match($user['role']) {
                                    'admin' => 'bg-danger',
                                    'doctor' => 'bg-primary',
                                    'receptionist' => 'bg-success',
                                    'nurse' => 'bg-info',
                                    default => 'bg-secondary'
                                };
                            ?>">
                                <?php echo ucfirst(htmlspecialchars($user['role'])); ?>
                            </span>
                        </div>
                        
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item">
                                <strong><i class="fas fa-user"></i> Username:</strong> 
                                <?php echo htmlspecialchars($user['username']); ?>
                            </li>
                            <li class="list-group-item">
                                <strong><i class="fas fa-envelope"></i> Email:</strong> 
                                <?php echo htmlspecialchars($user['email']); ?>
                            </li>
                            <li class="list-group-item">
                                <strong><i class="fas fa-clock"></i> Last Login:</strong> 
                                <?php echo $user['last_login'] ? date('d/m/Y H:i', strtotime($user['last_login'])) : 'Never'; ?>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <div class="col-md-8">
                <?php if (!empty($message)): ?>
                <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                    <?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>
                
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Update Profile</h5>
                    </div>
                    <div class="card-body">
                        <form method="post" action="profile.php">
                            <div class="mb-3">
                                <label for="full_name" class="form-label">Full Name</label>
                                <input type="text" class="form-control" id="full_name" name="full_name" value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                            </div>
                            
                            <hr>
                            <h6>Change Password</h6>
                            <p class="text-muted small">Leave blank if you don't want to change your password</p>
                            
                            <div class="mb-3">
                                <label for="current_password" class="form-label">Current Password</label>
                                <input type="password" class="form-control" id="current_password" name="current_password">
                            </div>
                            
                            <div class="mb-3">
                                <label for="new_password" class="form-label">New Password</label>
                                <input type="password" class="form-control" id="new_password" name="new_password">
                            </div>
                            
                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">Confirm New Password</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                            </div>
                            
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Update Profile
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Recent Activity</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Activity</th>
                                        <th>Date & Time</th>
                                        <th>IP Address</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    if ($activityResult->num_rows > 0) {
                                        while ($activity = $activityResult->fetch_assoc()) {
                                            // Format activity type for display
                                            $activityType = str_replace('_', ' ', $activity['activity_type']);
                                            $activityType = ucwords($activityType);
                                            
                                            // Set icon based on activity type
                                            $icon = match($activity['activity_type']) {
                                                'login' => '<i class="fas fa-sign-in-alt text-success"></i>',
                                                'logout' => '<i class="fas fa-sign-out-alt text-danger"></i>',
                                                'create_report' => '<i class="fas fa-file-medical text-primary"></i>',
                                                'view_report' => '<i class="fas fa-eye text-info"></i>',
                                                'print_report' => '<i class="fas fa-print text-secondary"></i>',
                                                'create_prescription' => '<i class="fas fa-prescription text-primary"></i>',
                                                'view_prescription' => '<i class="fas fa-eye text-info"></i>',
                                                'print_prescription' => '<i class="fas fa-print text-secondary"></i>',
                                                'create_treatment' => '<i class="fas fa-user-nurse text-primary"></i>',
                                                'view_treatment' => '<i class="fas fa-eye text-info"></i>',
                                                'print_treatment' => '<i class="fas fa-print text-secondary"></i>',
                                                'add_patient' => '<i class="fas fa-user-plus text-success"></i>',
                                                'edit_patient' => '<i class="fas fa-user-edit text-warning"></i>',
                                                default => '<i class="fas fa-history"></i>'
                                            };
                                    ?>
                                    <tr>
                                        <td><?php echo $icon; ?> <?php echo $activityType; ?></td>
                                        <td><?php echo date('d/m/Y H:i:s', strtotime($activity['created_at'])); ?></td>
                                        <td><?php echo htmlspecialchars($activity['ip_address']); ?></td>
                                    </tr>
                                    <?php
                                        }
                                    } else {
                                        echo '<tr><td colspan="3" class="text-center">No activity found</td></tr>';
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
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
    
    <style>
        .avatar-circle {
            width: 100px;
            height: 100px;
            background-color: #0d6efd;
            border-radius: 50%;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .initials {
            font-size: 42px;
            color: white;
            font-weight: bold;
        }
    </style>
</body>
</html>
