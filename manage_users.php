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

// Require login and user-management permission to access this page
requireLogin();
if (!canManageUsers()) {
    header('Location: dashboard.php');
    exit;
}

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
        // Per-user permissions (checkboxes)
        $canEditReports         = isset($_POST['can_edit_reports']) ? 1 : 0;
        $canDeleteReports       = isset($_POST['can_delete_reports']) ? 1 : 0;
        $canEditPrescriptions   = isset($_POST['can_edit_prescriptions']) ? 1 : 0;
        $canDeletePrescriptions = isset($_POST['can_delete_prescriptions']) ? 1 : 0;
        $canEditTreatments      = isset($_POST['can_edit_treatments']) ? 1 : 0;
        $canDeleteTreatments    = isset($_POST['can_delete_treatments']) ? 1 : 0;
        $canGenerateLinks       = isset($_POST['can_generate_links']) ? 1 : 0;
        $canManagePatients      = isset($_POST['can_manage_patients']) ? 1 : 0;
        $canDeletePatients      = isset($_POST['can_delete_patients']) ? 1 : 0;
        $canManageDoctors       = isset($_POST['can_manage_doctors']) ? 1 : 0;
        $canManageUsers         = isset($_POST['can_manage_users']) ? 1 : 0;
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
                        $insertQuery = "INSERT INTO users (username, password, full_name, email, role, 
                                                            can_edit_reports, can_delete_reports,
                                                            can_edit_prescriptions, can_delete_prescriptions,
                                                            can_edit_treatments, can_delete_treatments,
                                                            can_generate_links,
                                                            can_manage_patients, can_delete_patients,
                                                            can_manage_doctors, can_manage_users,
                                                            doctor_id, is_active)
                                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                        $stmt = $conn->prepare($insertQuery);
                        if (!$stmt) {
                            $message = "Error preparing user insert: " . $conn->error;
                            $messageType = "danger";
                        } else {
                            $stmt->bind_param(
                                "sssssiiiiiiiiiiii",
                                $username,
                                $hashedPassword,
                                $fullName,
                                $email,
                                $role,
                                $canEditReports,
                                $canDeleteReports,
                                $canEditPrescriptions,
                                $canDeletePrescriptions,
                                $canEditTreatments,
                                $canDeleteTreatments,
                                $canGenerateLinks,
                                $canManagePatients,
                                $canDeletePatients,
                                $canManageDoctors,
                                $canManageUsers,
                                $doctorId,
                                $isActive
                            );
                            
                            if ($stmt->execute()) {
                                $message = "User created successfully";
                                $messageType = "success";
                            } else {
                                $message = "Error creating user: " . $conn->error;
                                $messageType = "danger";
                            }
                        }

                    }
                }
            } else {
                // Update existing user
                if (!empty($password)) {
                    // Update with new password
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    $updateQuery = "UPDATE users SET username = ?, password = ?, full_name = ?, email = ?, role = ?,
                                        can_edit_reports = ?, can_delete_reports = ?,
                                        can_edit_prescriptions = ?, can_delete_prescriptions = ?,
                                        can_edit_treatments = ?, can_delete_treatments = ?,
                                        can_generate_links = ?,
                                        can_manage_patients = ?, can_delete_patients = ?,
                                        can_manage_doctors = ?, can_manage_users = ?,
                                        doctor_id = ?, is_active = ?
                                   WHERE id = ?";
                    $stmt = $conn->prepare($updateQuery);
                    if (!$stmt) {
                        $message = "Error preparing user update (with password): " . $conn->error;
                        $messageType = "danger";
                    } else {
                        $stmt->bind_param(
                            "sssssiiiiiiiiiiiiii",
                            $username,
                            $hashedPassword,
                            $fullName,
                            $email,
                            $role,
                            $canEditReports,
                            $canDeleteReports,
                            $canEditPrescriptions,
                            $canDeletePrescriptions,
                            $canEditTreatments,
                            $canDeleteTreatments,
                            $canGenerateLinks,
                            $canManagePatients,
                            $canDeletePatients,
                            $canManageDoctors,
                            $canManageUsers,
                            $doctorId,
                            $isActive,
                            $userId
                        );
                    }
                } else {
                    // Update without changing password
                    $updateQuery = "UPDATE users SET username = ?, full_name = ?, email = ?, role = ?,
                                        can_edit_reports = ?, can_delete_reports = ?,
                                        can_edit_prescriptions = ?, can_delete_prescriptions = ?,
                                        can_edit_treatments = ?, can_delete_treatments = ?,
                                        can_generate_links = ?,
                                        can_manage_patients = ?, can_delete_patients = ?,
                                        can_manage_doctors = ?, can_manage_users = ?,
                                        doctor_id = ?, is_active = ?
                                   WHERE id = ?";
                    $stmt = $conn->prepare($updateQuery);
                    if (!$stmt) {
                        $message = "Error preparing user update (no password): " . $conn->error;
                        $messageType = "danger";
                    } else {
                        $stmt->bind_param(
                            "ssssiiiiiiiiiiiiii",
                            $username,
                            $fullName,
                            $email,
                            $role,
                            $canEditReports,
                            $canDeleteReports,
                            $canEditPrescriptions,
                            $canDeletePrescriptions,
                            $canEditTreatments,
                            $canDeleteTreatments,
                            $canGenerateLinks,
                            $canManagePatients,
                            $canDeletePatients,
                            $canManageDoctors,
                            $canManageUsers,
                            $doctorId,
                            $isActive,
                            $userId
                        );
                    }
                }
                
                if ($stmt && $stmt->execute()) {
                    $message = "User updated successfully";
                    $messageType = "success";
                } elseif ($stmt) {
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
                <a href="dashboard.php" class="btn btn-outline-secondary btn-sm mb-2">
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
                                    <?php
                                    // Only show delete for non-doctor users and never for your own account
                                    if ($user['id'] != $_SESSION['user_id'] && $user['role'] !== 'doctor'): ?>
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

                        <hr>
                        <h6>Permissions</h6>
                        <div class="row">
                            <div class="col-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="can_edit_reports" name="can_edit_reports">
                                    <label class="form-check-label" for="can_edit_reports">Can edit reports</label>
                                </div>
                                <div class="form-check">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="can_manage_doctors" name="can_manage_doctors">
                                    <label class="form-check-label" for="can_manage_doctors">Can manage doctors  label>
                                </      <input class="form-check-input" type="checkbox" id="can_delete_reports" name="can_delete_reports">
                             /   >
                            <div    <label class="form-check-label" for="can_delete_reports">Can delete reports</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="can_edit_prescriptions" name="can_edit_prescriptions">
                                    <label class="form-check-label" for="can_edit_prescriptions">Can edit prescriptions</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="can_generate_links" name="can_generate_links">
                                    <label class="form-check-label" for="can_generate_links">Can generate patient links</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="can_manage_patients" name="can_manage_patients">
                                    <label class="form-check-label" for="can_manage_patients">Can manage patients</label>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="can_delete_prescriptions" name="can_delete_prescriptions">
                                    <label class="form-check-label" for="can_delete_prescriptions">Can delete prescriptions</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="can_edit_treatments" name="can_edit_treatments">
                                    <label class="form-check-label" for="can_edit_treatments">Can edit nurse treatments</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="can_delete_treatments" name="can_delete_treatments">
                                    <label class="form-check-label" for="can_delete_treatments">Can delete nurse treatments</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="can_delete_patients" name="can_delete_patients">
                                    <label class="form-check-label" for="can_delete_patients">Can delete patients</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="can_manage_users" name="can_manage_users">
                                    <label class="form-check-label" for="can_manage_users">Can manage users</label>
                                </div>
                            </div>
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
            // Reset permissions to unchecked by default
            document.getElementById('can_edit_reports').checked = false;
            document.getElementById('can_delete_reports').checked = false;
            document.getElementById('can_edit_prescriptions').checked = false;
            document.getElementById('can_delete_prescriptions').checked = false;
            document.getElementById('can_edit_treatments').checked = false;
            document.getElementById('can_delete_treatments').checked = false;
            document.getElementById('can_generate_links').checked = false;
            document.getElementById('can_manage_patients').checked = false;
            document.getElementById('can_delete_patients').checked = false;
            document.getElementById('can_manage_doctors').checked = false;
            document.getElementById('can_manage_users').checked = false;
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

            var isActiveEl = document.getElementById('is_active');
            if (isActiveEl) {
                isActiveEl.checked = (user.is_active == 1);
            }

            // Helper to safely set checkbox state if element exists
            function setCheckbox(id, value) {
                var el = document.getElementById(id);
                if (el) {
                    el.checked = (value == 1);
                }
            }

            // Permissions
            setCheckbox('can_edit_reports',         user.can_edit_reports);
            setCheckbox('can_delete_reports',       user.can_delete_reports);
            setCheckbox('can_edit_prescriptions',   user.can_edit_prescriptions);
            setCheckbox('can_delete_prescriptions', user.can_delete_prescriptions);
            setCheckbox('can_edit_treatments',      user.can_edit_treatments);
            setCheckbox('can_delete_treatments',    user.can_delete_treatments);
            setCheckbox('can_generate_links',       user.can_generate_links);
            setCheckbox('can_manage_patients',      user.can_manage_patients);
            setCheckbox('can_delete_patients',      user.can_delete_patients);
            setCheckbox('can_manage_doctors',       user.can_manage_doctors);
            setCheckbox('can_manage_users',         user.can_manage_users);
            
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
