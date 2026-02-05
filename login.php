<?php
// Start session
session_start();

// Include timezone configuration
require_once 'config/timezone.php';

// Check if user is already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

// Include database configuration
require_once 'config/database.php';

$error = '';

// Process login form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize($_POST['username']);
    $password = $_POST['password']; // Don't sanitize password before verification
    
    // Validate input
    if (empty($username) || empty($password)) {
        $error = "Username and password are required";
    } else {
        // Check user credentials
        $query = "SELECT id, username, password, full_name, role, is_active FROM users WHERE username = ?";
        
        global $conn;
        if (!isset($conn) || !$conn) {
            $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
            if ($conn->connect_error) {
                die("Connection failed: " . $conn->connect_error);
            }
        }
        
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            $error = "Database error: " . $conn->error;
        } else {
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 1) {
                $user = $result->fetch_assoc();
                
                // Verify password
                
if (password_verify($password, $user['password'])) {
                    // Check if user is active
                    if ($user['is_active']) {
                        // Set session variables
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['username'] = $user['username'];
                        $_SESSION['full_name'] = $user['full_name'];
                        $_SESSION['role'] = $user['role'];
                        
                        // Update last login time
                        $updateQuery = "UPDATE users SET last_login = NOW() WHERE id = ?";
                        $updateStmt = $conn->prepare($updateQuery);
                        $updateStmt->bind_param("i", $user['id']);
                        $updateStmt->execute();
                        $updateStmt->close();
                        
                        // Log login activity
                        $logQuery = "INSERT INTO user_activity_log (user_id, activity_type, ip_address, user_agent) VALUES (?, 'login', ?, ?)";
                        $logStmt = $conn->prepare($logQuery);
                        $ip = $_SERVER['REMOTE_ADDR'];
                        $userAgent = $_SERVER['HTTP_USER_AGENT'];
                        $logStmt->bind_param("iss", $user['id'], $ip, $userAgent);
                        $logStmt->execute();
                        $logStmt->close();
                        
                        // Redirect to dashboard
                        header("Location: index.php");
                        exit;
                    } else {
                        $error = "Your account is inactive. Please contact the administrator.";
                    }
                } else {
                    $error = "Invalid username or password";
                }
            } else {
                $error = "Invalid username or password";
            }
            
            $stmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Bato Medical Report System</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body {
            /* Clinic background image - full screen cover */
            background: #f8f9fa url('assets/images/bato clinic.png') no-repeat center center fixed;
            background-size: cover;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-container {
            max-width: 400px;
            width: 100%;
            padding: 20px;
        }
        .login-logo {
            text-align: center;
            margin-bottom: 30px;
        }
        .login-logo img {
            max-height: 100px;
        }
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
        }
        .card-header {
            background-color: #0d6efd;
            color: white;
            text-align: center;
            border-radius: 10px 10px 0 0 !important;
            padding: 20px;
        }
        .btn-primary {
            width: 100%;
            padding: 12px;
            border-radius: 5px;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-logo">
            <!-- <img src="assets/images/logo.png" alt="Bato Medical Logo"> -->
        </div>
        <div class="card">
            <div class="card-header">
                <h4 class="mb-0">Bato Medical Report System</h4>
            </div>
            <div class="card-body p-4">
                <?php if (!empty($error)): ?>
                <div class="alert alert-danger" role="alert">
                    <?php echo $error; ?>
                </div>
                <?php endif; ?>
                
                <form method="post" action="login.php">
                    <div class="mb-3">
                        <label for="username" class="form-label">Username</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-user"></i></span>
                            <input type="text" class="form-control" id="username" name="username" required autofocus>
                        </div>
                    </div>
                    <div class="mb-4">
                        <label for="password" class="form-label">Password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-sign-in-alt me-2"></i> Login
                    </button>
                </form>
            </div>
        </div>
        <div class="text-center mt-3 text-muted">
            <small>&copy; <?php echo date('Y'); ?> Bato Medical Report System</small>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
