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

// Process form submissions
$message = '';
$messageType = '';

// Handle user creation/update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'create' || $_POST['action'] === 'update') {
        $userId = isset($_POST['user_id']) ? sanitize($_POST['user_id']) : null;
        $username = sanitize($_POST['username']);
        $fullName = sanitize($_POST['full_name']);
        $email = sanitize($_POST['email']);
        $role = sanitize($_POST['role']);
        $doctorId = ($role === 'doctor' && isset($_POST['doctor_id'])) ? sanitize($_POST['doctor_id']) : null;
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        $password = isset($_POST['password']) ? $_POST['password'] : '';
        
        // Validate input
        if (empty($username) || empty($fullName) || empty($email) || empty($role)) {
            $message = "All fields are required";
            $messageType = "danger";
        } else {
            // Check if username already exists (for new users)
            if ($_POST['action'] === 'create') {
                $checkQuery = "SELECT id FROM users WHERE username = ?";
                $stmt = $conn->prepare($checkQuery);
                $stmt->bind_param("s", $username);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    $message = "Username already exists";
                    $messageType = "danger";
                } else {
                    // Create new user
                    if (empty($password)) {
                        $message = "Password is required for new users";
                        $messageType = "danger";
                    } else {
                        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                        $insertQuery = "INSERT INTO users (username, password, full_name, email, role, doctor_id, is_active) VALUES (?, ?, ?, ?, ?, ?, ?)";
                        $stmt = $conn->prepare($insertQuery);
                        $stmt->bind_param("sssssii", $username, $hashedPassword, $fullName, $email, $role, $doctorId, $isActive);
                        
                        if ($stmt->execute()) {
    $message = "User created successfully";
    $messageType = "success";
} else {
    $message = "Error creating user: " . $conn->error;
    $messageType = "danger";
}

                    }
                }
            } else {
                // Update existing user
                if (!empty($password)) {
                    // Update with new password
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    $updateQuery = "UPDATE users SET username = ?, password = ?, full_name = ?, email = ?, role = ?, doctor_id = ?, is_active = ? WHERE id = ?";
                    $stmt = $conn->prepare($updateQuery);
                    $stmt->bind_param("sssssiis", $username, $hashedPassword, $fullName, $email, $role, $doctorId, $isActive, $userId);
                } else {
                    // Update without changing password
                    $updateQuery = "UPDATE users SET username = ?, full_name = ?, email = ?, role = ?, doctor_id = ?, is_active = ? WHERE id = ?";
                    $stmt = $conn->prepare($updateQuery);
                    $stmt->bind_param("ssssiis", $username, $fullName, $email, $role, $doctorId, $isActive, $userId);
                }
                
                if ($stmt->execute()) {
    $message = "User updated successfully";
    $messageType = "success";
} else {
    $message = "Error updating user: " . $conn->error;
    $messageType = "danger";
}

            }
        }
    } elseif ($_POST['action'] === 'delete' && isset($_POST['user_id'])) {
        $userId = sanitize($_POST['user_id']);
        
        // Prevent deleting your own account
        if ($userId == $_SESSION['user_id']) {
            $message = "You cannot delete your own account";
            $messageType = "danger";
        } else {
            $deleteQuery = "DELETE FROM users WHERE id = ?";
            $stmt = $conn->prepare($deleteQuery);
            $stmt->bind_param("i", $userId);
            
            if ($stmt->execute()) {
                $message = "User deleted successfully";
                $messageType = "success";
            } else {
                $message = "Error deleting user: " . $conn->error;
                $messageType = "danger";
            }
        }
    }
}

// Get all users
$usersQuery = "SELECT u.*, d.name as doctor_name FROM users u LEFT JOIN doctors d ON u.doctor_id = d.id ORDER BY u.username";
$usersResult = executeQuery($usersQuery);

// Get all doctors for the dropdown
$doctorsQuery = "SELECT id, name FROM doctors ORDER BY name";
$doctorsResult = executeQuery($doctorsQuery);
$doctors = [];
while ($row = $doctorsResult->fetch_assoc()) {
    $doctors[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - Bato Medical Report System</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

    <!-- Main Content -->
    <div class="container my-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <a href="index.php" class="btn btn-outline-secondary btn-sm mb-2">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
                <h2 class="mb-0">Manage Users</h2>
            </div>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#userModal" onclick="resetUserForm()">
                <i class="fas fa-user-plus"></i> Add New User
            </button>
        </div>

        <?php if (!empty($message)): ?>
        <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Username</th>
                                <th>Full Name</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Doctor</th>
                                <th>Status</th>
                                <th>Last Login</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($user = $usersResult->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($user['username']); ?></td>
                                <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td>
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
                                </td>
                                <td><?php echo $user['doctor_id'] ? htmlspecialchars($user['doctor_name']) : '-'; ?></td>
                                <td>
                                    <span class="badge <?php echo $user['is_active'] ? 'bg-success' : 'bg-danger'; ?>">
                                        <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </td>
                                <td><?php echo $user['last_login'] ? date('d/m/Y H:i', strtotime($user['last_login'])) : 'Never'; ?></td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-primary" onclick="editUser(<?php echo htmlspecialchars(json_encode($user)); ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                    <button type="button" class="btn btn-sm btn-danger" onclick="deleteUser(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')">
                                        <i class="fas fa-trash"></i>
                                    </button>
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

    <!-- User Modal -->
    <div class="modal fade" id="userModal" tabindex="-1" aria-labelledby="userModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="userModalLabel">Add New User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="userForm" method="post" action="manage_users.php">
                    <div class="modal-body">
                        <input type="hidden" name="action" id="userAction" value="create">
                        <input type="hidden" name="user_id" id="userId" value="">
                        
                        <div class="mb-3">
                            <label for="username" class="form-label">Username</label>
                            <input type="text" class="form-control" id="username" name="username" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control" id="password" name="password">
                            <div class="form-text" id="passwordHelp">Leave blank to keep current password when editing.</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="full_name" class="form-label">Full Name</label>
                            <input type="text" class="form-control" id="full_name" name="full_name" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="role" class="form-label">Role</label>
                            <select class="form-select" id="role" name="role" required onchange="toggleDoctorSelect()">
                                <option value="admin">Administrator</option>
                                <option value="doctor">Doctor</option>
                                <option value="receptionist">Receptionist</option>
                                <option value="nurse">Nurse</option>
                            </select>
                        </div>
                        
                        <div class="mb-3" id="doctorSelectGroup" style="display: none;">
                            <label for="doctor_id" class="form-label">Associated Doctor</label>
                            <select class="form-select" id="doctor_id" name="doctor_id">
                                <option value="">-- Select Doctor --</option>
                                <?php foreach ($doctors as $doctor): ?>
                                <option value="<?php echo $doctor['id']; ?>"><?php echo htmlspecialchars($doctor['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="is_active" name="is_active" checked>
                            <label class="form-check-label" for="is_active">
                                Active
                            </label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete User Modal -->
    <div class="modal fade" id="deleteUserModal" tabindex="-1" aria-labelledby="deleteUserModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteUserModalLabel">Confirm Delete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete the user <strong id="deleteUserName"></strong>?</p>
                    <p class="text-danger">This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <form method="post" action="manage_users.php">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="user_id" id="deleteUserId">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Delete</button>
                    </form>
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
    
    <script>
        // Function to reset user form
        function resetUserForm() {
            document.getElementById('userModalLabel').textContent = 'Add New User';
            document.getElementById('userAction').value = 'create';
            document.getElementById('userId').value = '';
            document.getElementById('userForm').reset();
            document.getElementById('password').required = true;
            document.getElementById('passwordHelp').style.display = 'none';
            toggleDoctorSelect();
        }
        
        // Function to edit user
        function editUser(user) {
            document.getElementById('userModalLabel').textContent = 'Edit User';
            document.getElementById('userAction').value = 'update';
            document.getElementById('userId').value = user.id;
            document.getElementById('username').value = user.username;
            document.getElementById('password').value = '';
            document.getElementById('password').required = false;
            document.getElementById('passwordHelp').style.display = 'block';
            document.getElementById('full_name').value = user.full_name;
            document.getElementById('email').value = user.email;
            document.getElementById('role').value = user.role;
            document.getElementById('doctor_id').value = user.doctor_id || '';
            document.getElementById('is_active').checked = user.is_active == 1;
            
            toggleDoctorSelect();
            
            // Show modal
            var userModal = new bootstrap.Modal(document.getElementById('userModal'));
            userModal.show();
        }
        
        // Function to delete user
        function deleteUser(userId, username) {
            document.getElementById('deleteUserId').value = userId;
            document.getElementById('deleteUserName').textContent = username;
            
            // Show modal
            var deleteModal = new bootstrap.Modal(document.getElementById('deleteUserModal'));
            deleteModal.show();
        }
        
        // Function to toggle doctor select based on role
        function toggleDoctorSelect() {
            var role = document.getElementById('role').value;
            var doctorSelectGroup = document.getElementById('doctorSelectGroup');
            
            if (role === 'doctor') {
                doctorSelectGroup.style.display = 'block';
                document.getElementById('doctor_id').required = true;
            } else {
                doctorSelectGroup.style.display = 'none';
                document.getElementById('doctor_id').required = false;
            }
        }
    </script>
</body>
</html>
