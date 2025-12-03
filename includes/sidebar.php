<!-- Sidebar -->
<div class="sidebar">
    <div class="sidebar-header">
        <h3>Clinic Management</h3>
        <div class="menu-toggle">
            <i class="fas fa-bars"></i>
        </div>
    </div>

    <div class="sidebar-menu">
        <ul class="nav flex-column">
            <li class="nav-item">
                <a href="index.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>">
                    <i class="fas fa-home"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            
            <li class="nav-item">
                <a href="reports.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : ''; ?>">
                    <i class="fas fa-file-medical"></i>
                    <span>Medical Reports</span>
                </a>
            </li>

            <li class="nav-item">
                <a href="prescriptions.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'prescriptions.php' || basename($_SERVER['PHP_SELF']) == 'add_prescription.php') ? 'active' : ''; ?>">
                    <i class="fas fa-prescription"></i>
                    <span>Prescriptions</span>
                </a>
            </li>

            <li class="nav-item">
                <a href="nurse_treatments.php" class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'nurse_treatments.php' || basename($_SERVER['PHP_SELF']) == 'add_nurse_treatment.php') ? 'active' : ''; ?>">
                    <i class="fas fa-user-nurse"></i>
                    <span>Nurse Treatments</span>
                </a>
            </li>

            <li class="nav-item">
                <a href="#" class="nav-link has-submenu" data-bs-toggle="collapse" data-bs-target="#patientsSubmenu">
                    <i class="fas fa-users"></i>
                    <span>Patients</span>
                    <i class="fas fa-chevron-right dropdown-icon"></i>
                </a>
                <ul class="collapse submenu" id="patientsSubmenu">
                    <li><a href="add_patient.php" class="nav-link">Add Patient</a></li>
                    <li><a href="patient_list.php" class="nav-link">Patient List</a></li>
                </ul>
            </li>

            <?php if (hasRole(['admin'])): ?>
            <li class="nav-item">
                <a href="#" class="nav-link has-submenu" data-bs-toggle="collapse" data-bs-target="#doctorsSubmenu">
                    <i class="fas fa-user-md"></i>
                    <span>Doctors</span>
                    <i class="fas fa-chevron-right dropdown-icon"></i>
                </a>
                <ul class="collapse submenu" id="doctorsSubmenu">
                    <li><a href="add_doctor.php" class="nav-link">Add Doctor</a></li>
                    <li><a href="manage_doctors.php" class="nav-link">Doctor List</a></li>
                </ul>
            </li>

            <li class="nav-item">
                <a href="#" class="nav-link has-submenu" data-bs-toggle="collapse" data-bs-target="#testsSubmenu">
                    <i class="fas fa-vial"></i>
                    <span>Tests</span>
                    <i class="fas fa-chevron-right dropdown-icon"></i>
                </a>
                <ul class="collapse submenu" id="testsSubmenu">
                    <li><a href="add_test_type.php" class="nav-link">Add Test Type</a></li>
                    <li><a href="manage_test_types.php" class="nav-link">Test Types List</a></li>
                </ul>
            </li>
            <?php endif; ?>

            <?php if (hasRole(['admin'])): ?>
            <li class="nav-item">
                <a href="manage_users.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'manage_users.php' ? 'active' : ''; ?>">
                    <i class="fas fa-users-cog"></i>
                    <span>Staff Management</span>
                </a>
            </li>

            <li class="nav-item">
                <a href="activity_logs.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'activity_logs.php' ? 'active' : ''; ?>">
                    <i class="fas fa-history"></i>
                    <span>Activity Logs</span>
                </a>
            </li>
            <?php endif; ?>

            <!-- Profile and Logout Links -->
            <li class="nav-item mt-auto">
                <div class="border-top mt-3 pt-2">
                    <a href="profile.php" class="nav-link">
                        <i class="fas fa-user-circle"></i>
                        <span>My Profile</span>
                    </a>
                    <a href="javascript:void(0)" class="nav-link text-danger" id="logoutLink">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>Logout</span>
                    </a>
                    
                    <!-- SweetAlert2 CSS -->
                    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
                    
                    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
                    <script>
                    document.getElementById('logoutLink').addEventListener('click', function(e) {
                        e.preventDefault();
                        
                        Swal.fire({
                            title: 'Logout Confirmation',
                            text: 'Are you sure you want to log out?',
                            icon: 'question',
                            showCancelButton: true,
                            confirmButtonColor: '#d33',
                            cancelButtonColor: '#6c757d',
                            confirmButtonText: 'Yes, log me out',
                            cancelButtonText: 'Cancel',
                            customClass: {
                                confirmButton: 'btn btn-danger',
                                cancelButton: 'btn btn-secondary me-2'
                            },
                            buttonsStyling: false
                        }).then((result) => {
                            if (result.isConfirmed) {
                                // Add a small delay to show the loading state
                                Swal.fire({
                                    title: 'Logging out...',
                                    text: 'Please wait while we log you out.',
                                    allowOutsideClick: false,
                                    didOpen: () => {
                                        Swal.showLoading();
                                    }
                                });
                                
                                // Redirect after a short delay
                                setTimeout(function() {
                                    window.location.href = 'logout.php';
                                }, 500);
                            }
                        });
                    });
                    </script>
                </div>
            </li>

        </ul>
    </div>
</div>
