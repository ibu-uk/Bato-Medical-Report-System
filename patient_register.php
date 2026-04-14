<?php
session_start();

require_once 'config/timezone.php';
require_once 'config/database.php';
require_once 'config/auth.php';
require_once 'config/secure_links.php';

if (isPatientLoggedIn()) {
    header('Location: patient_dashboard.php');
    exit;
}

$token = '';
if (isset($_GET['token']) && trim((string)$_GET['token']) !== '') {
    $token = trim((string)$_GET['token']);
} elseif (isset($_GET['t']) && trim((string)$_GET['t']) !== '') {
    $token = decodeUrlToken(trim((string)$_GET['t']));
}

$registrationMode = 'manual';
$tokenStatusMessage = 'You can register using your Civil ID.';
$tokenData = false;
$patientId = 0;

if ($token !== '') {
    $tokenData = validateReportToken($token);
    if ($tokenData && (int)($tokenData['patient_id'] ?? 0) > 0) {
        $registrationMode = 'token';
        $tokenStatusMessage = '';
        $patientId = (int)$tokenData['patient_id'];
    } else {
        $tokenStatusMessage = 'Secure link is invalid or expired. You can still register using your Civil ID.';
    }
}

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die('Database connection failed.');
}

$patient = null;
if ($registrationMode === 'token') {
    $query = "SELECT id, name, civil_id, portal_username, portal_password_hash, portal_is_active
              FROM patients
              WHERE id = ?
              LIMIT 1";
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        $conn->close();
        die('Failed to load patient account data.');
    }

    $stmt->bind_param('i', $patientId);
    $stmt->execute();
    $result = $stmt->get_result();
    $patient = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    if (!$patient) {
        $conn->close();
        die('Patient was not found.');
    }
}

$alreadyRegistered = $patient
    ? (!empty($patient['portal_username']) && !empty($patient['portal_password_hash']))
    : false;
$error = '';
$success = '';
$username = '';
$civilInput = '';

$normalizeCivil = static function($value) {
    return preg_replace('/[^a-zA-Z0-9]/', '', (string)$value);
};

$findPatientByCivil = static function($connRef, $civilNorm) {
    $lookupQuery = "SELECT id, name, civil_id, email, portal_username, portal_password_hash, portal_is_active
                    FROM patients
                    WHERE REPLACE(REPLACE(civil_id, '-', ''), ' ', '') = ?
                    LIMIT 1";
    $lookupStmt = $connRef->prepare($lookupQuery);
    if (!$lookupStmt) {
        return null;
    }
    $lookupStmt->bind_param('s', $civilNorm);
    $lookupStmt->execute();
    $lookupResult = $lookupStmt->get_result();
    $lookupRow = $lookupResult ? $lookupResult->fetch_assoc() : null;
    $lookupStmt->close();
    return $lookupRow ?: null;
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $civilInput = trim((string)($_POST['civil_id'] ?? ''));
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $confirmPassword = (string)($_POST['confirm_password'] ?? '');
    $civilInputNorm = $normalizeCivil($civilInput);
    $civilStoredNorm = '';

    if ($registrationMode === 'manual' && $civilInputNorm !== '') {
        $patient = $findPatientByCivil($conn, $civilInputNorm);
    }

    if ($patient) {
        $patientId = (int)$patient['id'];
        $alreadyRegistered = !empty($patient['portal_username']) && !empty($patient['portal_password_hash']);
        $civilStoredNorm = $normalizeCivil((string)($patient['civil_id'] ?? ''));
    }

    if ($civilInput === '' || $username === '' || $password === '' || $confirmPassword === '') {
        $error = 'All fields are required.';
    } elseif (!$patient) {
        $error = 'No patient found with this Civil ID.';
    } elseif ($alreadyRegistered) {
        $error = 'An account already exists for this patient. Please sign in.';
    } elseif ($civilInputNorm === '' || $civilStoredNorm === '' || $civilInputNorm !== $civilStoredNorm) {
        $error = 'Civil ID does not match our records.';
    } elseif (strlen($username) < 4) {
        $error = 'Username must be at least 4 characters.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($password !== $confirmPassword) {
        $error = 'Password confirmation does not match.';
    } else {
        $checkStmt = $conn->prepare('SELECT id FROM patients WHERE portal_username = ? AND id != ? LIMIT 1');
        if (!$checkStmt) {
            $error = 'Failed to validate username. Please try again.';
        } else {
            $checkStmt->bind_param('si', $username, $patientId);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();
            $usernameTaken = $checkResult && $checkResult->num_rows > 0;
            $checkStmt->close();

            if ($usernameTaken) {
                $error = 'This username is already taken. Please choose another one.';
            }
        }
    }

    if ($error === '') {
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $updateStmt = $conn->prepare('UPDATE patients SET portal_username = ?, portal_password_hash = ?, portal_is_active = 1 WHERE id = ?');
        if (!$updateStmt) {
            $error = 'Failed to create account. Please try again.';
        } else {
            $updateStmt->bind_param('ssi', $username, $passwordHash, $patientId);
            if ($updateStmt->execute()) {
                $updateStmt->close();
                $conn->close();
                if ($registrationMode === 'token' && $token !== '') {
                    header('Location: patient_login.php?registered=1&username=' . urlencode($username) . '&token=' . urlencode($token));
                } else {
                    header('Location: patient_login.php?registered=1&username=' . urlencode($username) . '&portal=1');
                }
                exit;
            }
            $updateStmt->close();
            $error = 'Failed to save account credentials. Please try again.';
        }
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Registration - Bato Clinic</title>
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

        .register-card {
            width: 100%;
            max-width: 520px;
            border: 0;
            border-radius: 14px;
            box-shadow: 0 12px 30px rgba(10, 30, 60, 0.14);
            overflow: hidden;
        }

        .register-header {
            background: #1f3a5f;
            color: #fff;
            padding: 20px;
        }

        .register-header h4 {
            margin: 0;
            font-size: 1.15rem;
            font-weight: 700;
        }

        .register-header p {
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
    <div class="card register-card">
        <div class="register-header">
            <h4><i class="fas fa-user-plus me-2"></i>BATO CLINIC First-Time Registration</h4>
            <p>Verify your Civil ID and create your portal account.</p>
        </div>
        <div class="card-body p-4">
            <?php if ($tokenStatusMessage !== ''): ?>
                <div class="alert alert-warning" role="alert"><?php echo htmlspecialchars($tokenStatusMessage); ?></div>
            <?php endif; ?>

            <?php if (!empty($patient['name'])): ?>
                <p class="mb-3"><strong>Patient:</strong> <?php echo htmlspecialchars((string)$patient['name']); ?></p>
            <?php endif; ?>

            <?php if ($alreadyRegistered): ?>
                <div class="alert alert-info">An account is already created for this patient. Please sign in.</div>
                <a href="<?php echo $token !== '' ? ('patient_login.php?token=' . urlencode($token)) : 'patient_login.php?portal=1'; ?>" class="btn btn-outline-primary">
                    <i class="fas fa-sign-in-alt me-1"></i> Go to Sign In
                </a>
            <?php else: ?>
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger" role="alert"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                <?php if (!empty($success)): ?>
                    <div class="alert alert-success" role="alert"><?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>
                <form method="post" autocomplete="off">
                    <div class="mb-3">
                        <label for="civil_id" class="form-label">Civil ID (mandatory)</label>
                        <input type="text" class="form-control" id="civil_id" name="civil_id" value="<?php echo htmlspecialchars($civilInput); ?>" required>
                    </div>

                    <div class="mb-3">
                        <label for="username" class="form-label">Create Username</label>
                        <input type="text" class="form-control" id="username" name="username" value="<?php echo htmlspecialchars($username); ?>" required>
                    </div>

                    <div class="mb-3">
                        <label for="password" class="form-label">Create Password</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>

                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">Confirm Password</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                    </div>

                    <button type="submit" class="btn btn-primary btn-portal w-100">
                        <i class="fas fa-check-circle me-2"></i>Create Account
                    </button>
                </form>

                <div class="text-center mt-3">
                    <a href="<?php echo $token !== '' ? ('patient_login.php?token=' . urlencode($token)) : 'patient_login.php?portal=1'; ?>" class="btn btn-link text-decoration-none">Already registered? Sign In</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
