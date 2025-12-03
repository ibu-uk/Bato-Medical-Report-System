<?php
// Start session
session_start();

// Include configurations
require_once 'config/timezone.php';
require_once 'config/database.php';
require_once 'config/auth.php';

// Require login
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
            // For admin, don't require current password to change other users' passwords
            if ($_SESSION['user_id'] != ($_POST['user_id'] ?? $_SESSION['user_id']) && !hasRole(['admin'])) {
                $message = "You don't have permission to change this user's password";
                $messageType = "danger";
            } 
            // For own account, require current password
            elseif ($_SESSION['user_id'] == ($_POST['user_id'] ?? $_SESSION['user_id']) && 
                   (empty($currentPassword) || !password_verify($currentPassword, $user['password']))) {
                $message = "Current password is incorrect";
                $messageType = "danger";
            } 
            elseif ($newPassword !== $confirmPassword) {
                $message = "New passwords do not match";
                $messageType = "danger";
            } 
            elseif (strlen($newPassword) < 6) {
                $message = "New password must be at least 6 characters";
                $messageType = "danger";
            } 
            else {
                // Update user with new password
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                $userId = $_POST['user_id'] ?? $_SESSION['user_id'];
                $updateQuery = "UPDATE users SET full_name = ?, email = ?, password = ? WHERE id = ?";
                $stmt = $conn->prepare($updateQuery);
                $stmt->bind_param("sssi", $fullName, $email, $hashedPassword, $userId);
                
                if ($stmt->execute()) {
                    $message = "Profile updated successfully with new password";
                    $messageType = "success";
                    
                    // Update session data if it's the current user
                    if ($_SESSION['user_id'] == $userId) {
                        $_SESSION['full_name'] = $fullName;
                        $user = getCurrentUser();
                    }
                } else {
                    $message = "Error updating profile: " . $conn->error;
                    $messageType = "danger";
                }
            }
        } 
        else {
            // Update user without changing password
            $userId = $_POST['user_id'] ?? $_SESSION['user_id'];
            $updateQuery = "UPDATE users SET full_name = ?, email = ? WHERE id = ?";
            $stmt = $conn->prepare($updateQuery);
            $stmt->bind_param("ssi", $fullName, $email, $userId);
            
            if ($stmt->execute()) {
                $message = "Profile updated successfully";
                $messageType = "success";
                
                // Update session data if it's the current user
                if ($_SESSION['user_id'] == $userId) {
                    $_SESSION['full_name'] = $fullName;
                    $user = getCurrentUser();
                }
            } else {
                $message = "Error updating profile: " . $conn->error;
                $messageType = "danger";
            }
        }
    }
}

// If admin is editing another user's profile
if (hasRole(['admin']) && isset($_GET['user_id']) && $_GET['user_id'] != $_SESSION['user_id']) {
    $editUserId = (int)$_GET['user_id'];
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->bind_param("i", $editUserId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Profile - Bato Medical Report System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
            background-color: #f8f9fa;
            border-bottom: 1px solid rgba(0,0,0,.125);
        }
        .profile-img {
            width: 150px;
            height: 150px;
            object-fit: cover;
        }
        .btn-back {
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
            <?php if (hasRole(['admin']) && isset($_GET['user_id']) && $_GET['user_id'] != $_SESSION['user_id']): ?>
                <div class="alert alert-info py-2 mb-0">
                    <i class="fas fa-user-edit"></i> You are editing another user's profile
                </div>
            <?php endif; ?>
        </div>

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
                            <?php if (!(hasRole(['admin']) && isset($_GET['user_id']) && $_GET['user_id'] != $_SESSION['user_id'])): ?>
                                <p class="text-muted small">Leave blank if you don't want to change the password</p>
                            <?php else: ?>
                                <p class="text-muted small">Enter new password to change the user's password (leave blank to keep current password)</p>
                            <?php endif; ?>
                            
                            <?php if (!(hasRole(['admin']) && isset($_GET['user_id']) && $_GET['user_id'] != $_SESSION['user_id'])): ?>
                                <div class="mb-3">
                                    <label for="current_password" class="form-label">Current Password</label>
                                    <input type="password" class="form-control" id="current_password" name="current_password">
                                </div>
                            <?php endif; ?>
                            
                            <div class="mb-3">
                                <label for="new_password" class="form-label">New Password</label>
                                <input type="password" class="form-control" id="new_password" name="new_password">
                            </div>
                            
                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">Confirm New Password</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                            </div>
                            
                            <?php if (hasRole(['admin']) && isset($_GET['user_id'])): ?>
                                <input type="hidden" name="user_id" value="<?php echo (int)$_GET['user_id']; ?>">
                            <?php endif; ?>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Save Changes
                                </button>
                                <a href="manage_users.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-arrow-left"></i> Back to Users
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript Libraries -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Enable Bootstrap tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    </script>
</body>
</html>
