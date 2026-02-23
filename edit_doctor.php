<?php
// Start session
session_start();

// Include authentication helpers for role checking
require_once 'config/auth.php';

// Only allow admin role to edit doctors
if (!hasRole(['admin'])) {
    header('Location: index.php');
    exit;
}

// Include database configuration
require_once 'config/database.php';

$success_message = null;
$error_message = null;
$doctor = null;

// Validate doctor id
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: manage_doctors.php');
    exit;
}

$doctor_id = sanitize($_GET['id']);

// Fetch existing doctor
$doctor_query = "SELECT * FROM doctors WHERE id = '$doctor_id' LIMIT 1";
$doctor_result = executeQuery($doctor_query);

if ($doctor_result && $doctor_result->num_rows === 1) {
    $doctor = $doctor_result->fetch_assoc();
} else {
    // If doctor not found, go back to list
    header('Location: manage_doctors.php');
    exit;
}

// Handle form submission for updating doctor
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_doctor'])) {
    $name = sanitize($_POST['name']);
    $position = sanitize($_POST['position']);
    $signature_path = $doctor['signature_image_path'];

    // Handle signature image upload (optional replacement)
    if (isset($_FILES['signature']) && $_FILES['signature']['error'] === 0) {
        $target_dir = 'assets/images/signatures/';

        // Create directory if it doesn't exist
        if (!file_exists($target_dir)) {
            mkdir($target_dir, 0777, true);
        }

        $file_extension = pathinfo($_FILES['signature']['name'], PATHINFO_EXTENSION);
        $new_filename = uniqid('signature_') . '.' . $file_extension;
        $target_file = $target_dir . $new_filename;

        // Check if image file is an actual image
        $check = getimagesize($_FILES['signature']['tmp_name']);
        if ($check !== false) {
            if (move_uploaded_file($_FILES['signature']['tmp_name'], $target_file)) {
                // Delete old signature file if exists
                if (!empty($signature_path) && file_exists($signature_path)) {
                    @unlink($signature_path);
                }
                $signature_path = $target_file;
            }
        }
    }

    // Update doctor in database
    $update_query = "UPDATE doctors SET name = '$name', position = '$position', signature_image_path = '$signature_path' WHERE id = '$doctor_id'";
    $update_result = executeQuery($update_query);

    if ($update_result) {
        $success_message = 'Doctor updated successfully!';
        // Refresh doctor data
        $doctor_query = "SELECT * FROM doctors WHERE id = '$doctor_id' LIMIT 1";
        $doctor_result = executeQuery($doctor_query);
        if ($doctor_result && $doctor_result->num_rows === 1) {
            $doctor = $doctor_result->fetch_assoc();
        }
    } else {
        $error_message = 'Error updating doctor. Please try again.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Doctor - Bato Medical Report System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="container my-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h3 class="mb-0">Edit Doctor</h3>
            <a href="manage_doctors.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-1"></i> Back to Doctors
            </a>
        </div>

        <?php if ($success_message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo $success_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo $error_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Doctor Details</h5>
            </div>
            <div class="card-body">
                <form action="edit_doctor.php?id=<?php echo htmlspecialchars($doctor_id); ?>" method="POST" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label for="name" class="form-label">Doctor Name</label>
                        <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($doctor['name']); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="position" class="form-label">Position</label>
                        <input type="text" class="form-control" id="position" name="position" value="<?php echo htmlspecialchars($doctor['position']); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="signature" class="form-label">Signature Image</label>
                        <input type="file" class="form-control" id="signature" name="signature" accept="image/*">
                        <div class="form-text">Upload new signature image (leave empty to keep existing)</div>
                        <?php if (!empty($doctor['signature_image_path'])): ?>
                            <div class="mt-2">
                                <p class="mb-1">Current Signature:</p>
                                <img src="<?php echo htmlspecialchars($doctor['signature_image_path']); ?>" alt="Signature" style="max-height: 60px;">
                            </div>
                        <?php endif; ?>
                    </div>
                    <button type="submit" name="update_doctor" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i> Update Doctor
                    </button>
                </form>
            </div>
        </div>
    </div>

    <footer class="bg-dark text-white py-3 mt-4">
        <div class="container text-center">
            <p class="mb-0">© <?php echo date('Y'); ?> Bato Medical Report System. All rights reserved.</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
