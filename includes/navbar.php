<?php
// Ensure session and auth are available
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/auth.php';
?>
<nav class="navbar navbar-expand-lg navbar-dark" style="background-color: #6c757d;">
    <div class="container">
        <a class="navbar-brand" href="index.php">Bato Medical Report System</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <a class="nav-link" href="index.php">Home</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="reports.php">Reports</a>
                </li>
                <?php if (hasRole(['admin', 'doctor'])): ?>
                <li class="nav-item">
                    <a class="nav-link" href="prescriptions.php"><i class="fas fa-prescription"></i> Prescriptions</a>
                </li>
                <?php endif; ?>
                <li class="nav-item">
                    <a class="nav-link" href="nurse_treatments.php"><i class="fas fa-user-nurse"></i> Nurse Treatments</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="manage_doctors.php">Doctors</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="manage_test_types.php"><i class="fas fa-vial"></i> Test Types</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="add_patient.php"><i class="fas fa-user-plus"></i> Add Patient</a>
                </li>
            </ul>
        </div>
        <div class="d-flex align-items-center">
            <?php if (isset($_SESSION['user_name'])): ?>
                <span class="text-white me-2"><i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
            <?php endif; ?>
        </div>
    </div>
</nav>
