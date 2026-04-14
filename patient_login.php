<?php
session_start();

require_once 'config/timezone.php';
require_once 'config/database.php';
require_once 'config/auth.php';

if (isPatientLoggedIn()) {
    header('Location: patient_dashboard.php');
    exit;
}

$hasPortalIntent =
    trim((string)($_GET['token'] ?? '')) !== '' ||
    trim((string)($_GET['t'] ?? '')) !== '' ||
    (string)($_GET['portal'] ?? '') === '1';

if (isLoggedIn() && !$hasPortalIntent) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$success = '';
$prefillUsername = trim((string)($_GET['username'] ?? ''));
$registerToken = trim((string)($_GET['token'] ?? ''));
$registerShortToken = trim((string)($_GET['t'] ?? ''));

$registerUrl = '';
if ($registerToken !== '') {
    $registerUrl = 'patient_register.php?token=' . urlencode($registerToken);
} elseif ($registerShortToken !== '') {
    $registerUrl = 'patient_register.php?t=' . urlencode($registerShortToken);
} else {
    $registerUrl = 'patient_register.php';
}

if (isset($_GET['registered']) && $_GET['registered'] === '1') {
    $success = 'Account created successfully. Please sign in.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Username and password are required.';
    } else {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($conn->connect_error) {
            $error = 'Database connection failed. Please try again.';
        } else {
            $query = "SELECT id, name, portal_username, portal_password_hash, portal_is_active
                      FROM patients
                      WHERE portal_username = ?
                      LIMIT 1";

            $stmt = $conn->prepare($query);
            if (!$stmt) {
                // Most likely migration is not applied yet
                $error = 'Patient portal is not configured yet. Please ask clinic staff to enable portal login.';
            } else {
                $stmt->bind_param('s', $username);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result && $result->num_rows === 1) {
                    $patient = $result->fetch_assoc();

                    if (!(int)$patient['portal_is_active']) {
                        $error = 'Your portal account is inactive. Please contact clinic staff.';
                    } elseif (empty($patient['portal_password_hash']) || !password_verify($password, $patient['portal_password_hash'])) {
                        $error = 'Invalid username or password.';
                    } else {
                        session_regenerate_id(true);
                        $_SESSION['patient_id'] = (int)$patient['id'];
                        $_SESSION['patient_name'] = $patient['name'] ?? '';
                        $_SESSION['auth_type'] = 'patient';

                        $updateStmt = $conn->prepare('UPDATE patients SET portal_last_login = NOW() WHERE id = ?');
                        if ($updateStmt) {
                            $patientId = (int)$patient['id'];
                            $updateStmt->bind_param('i', $patientId);
                            $updateStmt->execute();
                            $updateStmt->close();
                        }

                        $stmt->close();
                        $conn->close();

                        header('Location: patient_dashboard.php');
                        exit;
                    }
                } else {
                    $error = 'Invalid username or password.';
                }

                $stmt->close();
            }

            $conn->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Login - Bato Clinic</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            min-height: 100vh;
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(140deg, #f5f7fb 0%, #e7eef6 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding: 20px;
        }

        .login-card {
            width: 100%;
            max-width: 460px;
            border: 0;
            border-radius: 14px;
            box-shadow: 0 12px 30px rgba(10, 30, 60, 0.14);
            overflow: hidden;
        }

        .login-header {
            background: #1f3a5f;
            color: #fff;
            padding: 20px;
        }

        .login-header h4 {
            margin: 0;
            font-size: 1.15rem;
            font-weight: 700;
        }

        .login-header p {
            margin: 6px 0 0;
            opacity: 0.92;
            font-size: 0.9rem;
        }

        .btn-portal {
            background: #1f3a5f;
            border-color: #1f3a5f;
            font-weight: 600;
            padding: 10px 14px;
        }

        .btn-portal:hover {
            background: #17304f;
            border-color: #17304f;
        }
    </style>
</head>
<body>
    <div class="card login-card">
        <div class="login-header">
            <h4><i class="fas fa-shield-heart me-2"></i>BATO CLINIC Patient Portal</h4>
            <p>Sign in to view your reports, prescriptions, treatments, and documents.</p>
        </div>
        <div class="card-body p-4">
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger" role="alert"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <?php if (!empty($success)): ?>
                <div class="alert alert-success" role="alert"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <form method="post" autocomplete="off">
                <div class="mb-3">
                    <label for="username" class="form-label">Portal Username</label>
                    <input type="text" class="form-control" id="username" name="username" value="<?php echo htmlspecialchars($prefillUsername); ?>" required>
                </div>

                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" class="form-control" id="password" name="password" required>
                </div>

                <button type="submit" class="btn btn-primary btn-portal w-100">
                    <i class="fas fa-sign-in-alt me-2"></i>Login to Dashboard
                </button>
            </form>

            <div class="text-center mt-3">
                <a href="<?php echo $registerUrl; ?>" class="btn btn-outline-primary btn-sm">
                    <i class="fas fa-user-plus me-1"></i> New patient? Register
                </a>
            </div>

            <div class="text-center mt-3">
                <small class="text-muted">Need access? Contact clinic reception.</small>
            </div>
        </div>
    </div>
</body>
</html>
