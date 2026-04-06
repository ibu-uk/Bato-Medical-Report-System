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
            <?php if (hasRole(['admin'])): ?>
            <li class="nav-item">
                <a href="dashboard.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
                    <i class="fas fa-home"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <?php endif; ?>

            <li class="nav-item">
                <a href="index.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>">
                    <i class="fas fa-file-circle-plus"></i>
                    <span>Create Report</span>
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
                    <?php if (hasRole(['admin'])): ?>
                    <li><a href="manage_document_categories.php" class="nav-link">Document Categories</a></li>
                    <?php endif; ?>
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

            <li class="nav-item">
                <a href="support_center.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'support_center.php' ? 'active' : ''; ?>">
                    <i class="fas fa-headset"></i>
                    <span>Support Center</span>
                    <?php if ((isset($_SESSION['role']) && $_SESSION['role'] === 'admin') || !empty($_SESSION['can_manage_users'])): ?>
                    <span class="badge bg-danger" id="supportTicketBadge" style="display:none;">0</span>
                    <?php endif; ?>
                </a>
            </li>

            <li class="nav-item">
                <a href="staff_chat.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'staff_chat.php' ? 'active' : ''; ?>" id="staffChatNavLink">
                    <i class="fas fa-comments"></i>
                    <span>Staff Chat</span>
                    <span class="badge bg-danger" id="staffChatBadge" style="display:none;">0</span>
                </a>
            </li>

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

                    function openSupportTicketModal() {
                        const issueFormHtml = `
                            <div class="text-start">
                                <div class="mb-2">
                                    <label class="form-label">Subject</label>
                                    <input type="text" id="supportSubject" class="form-control" maxlength="255" placeholder="Short title of issue">
                                </div>
                                <div class="mb-2">
                                    <label class="form-label">Issue Type</label>
                                    <select id="supportIssueType" class="form-select">
                                        <option value="general">General</option>
                                        <option value="bug">Bug/Error</option>
                                        <option value="access">Access/Permission</option>
                                        <option value="performance">Slow Performance</option>
                                        <option value="training">Need Guidance/Training</option>
                                    </select>
                                </div>
                                <div class="mb-2">
                                    <label class="form-label">Priority</label>
                                    <select id="supportPriority" class="form-select">
                                        <option value="medium">Medium</option>
                                        <option value="low">Low</option>
                                        <option value="high">High</option>
                                        <option value="urgent">Urgent</option>
                                    </select>
                                </div>
                                <div class="mb-0">
                                    <label class="form-label">Details</label>
                                    <textarea id="supportDetails" class="form-control" rows="4" placeholder="Explain what happened and what you need help with"></textarea>
                                </div>
                                <div class="mt-2">
                                    <label class="form-label">Attachment (Optional: screenshot/image/video, max 20MB)</label>
                                    <input type="file" id="supportAttachment" class="form-control" accept="image/*,video/*">
                                </div>
                                <div class="mt-3 pt-2 border-top rounded px-2 py-2" style="background:#eef3f8;border:1px solid #c9d7e6;">
                                    <small class="fw-semibold" style="color:#1b3954;">For assistance, contact IT Support - Mohamed Ibrahim | Mobile/WhatsApp: 96566680241 | Email: it@batoclinic.com</small>
                                </div>
                            </div>
                        `;

                        Swal.fire({
                            title: 'Create Support Ticket',
                            html: issueFormHtml,
                            width: 650,
                            showCancelButton: true,
                            confirmButtonText: 'Submit Ticket',
                            cancelButtonText: 'Cancel',
                            focusConfirm: false,
                            preConfirm: () => {
                                const subject = document.getElementById('supportSubject').value.trim();
                                const details = document.getElementById('supportDetails').value.trim();
                                const attachmentInput = document.getElementById('supportAttachment');
                                const attachment = attachmentInput && attachmentInput.files ? attachmentInput.files[0] : null;

                                if (!subject || !details) {
                                    Swal.showValidationMessage('Subject and details are required');
                                    return false;
                                }

                                if (attachment && attachment.size > 20 * 1024 * 1024) {
                                    Swal.showValidationMessage('Attachment exceeds 20MB.');
                                    return false;
                                }

                                return {
                                    subject,
                                    details,
                                    issue_type: document.getElementById('supportIssueType').value,
                                    priority: document.getElementById('supportPriority').value,
                                    current_page: window.location.pathname + window.location.search,
                                    attachment
                                };
                            }
                        }).then((result) => {
                            if (!result.isConfirmed || !result.value) {
                                return;
                            }

                            const payload = new FormData();
                            payload.append('subject', result.value.subject);
                            payload.append('details', result.value.details);
                            payload.append('issue_type', result.value.issue_type);
                            payload.append('priority', result.value.priority);
                            payload.append('current_page', result.value.current_page);
                            if (result.value.attachment) {
                                payload.append('attachment', result.value.attachment);
                            }

                            Swal.fire({
                                title: 'Submitting...',
                                allowOutsideClick: false,
                                didOpen: () => Swal.showLoading()
                            });

                            fetch('create_support_ticket.php', {
                                method: 'POST',
                                body: payload
                            })
                            .then((response) => response.json())
                            .then((data) => {
                                if (!data.success) {
                                    throw new Error(data.message || 'Failed to create support ticket');
                                }

                                Swal.fire({
                                    icon: 'success',
                                    title: 'Ticket Created',
                                    text: `Support ticket #${data.ticket_id} created successfully.`,
                                    confirmButtonText: 'Open Support Center'
                                }).then(() => {
                                    window.location.href = 'support_center.php';
                                });
                            })
                            .catch((err) => {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Request Failed',
                                    text: err.message
                                });
                            });
                        });
                    }

                    window.openSupportTicketModal = openSupportTicketModal;

                    document.addEventListener('click', function(e) {
                        const helpDeskFloatingBtn = e.target.closest('#helpDeskFloatingBtn');
                        if (!helpDeskFloatingBtn) {
                            return;
                        }

                        e.preventDefault();
                        window.location.href = 'staff_chat.php';
                    });

                    document.addEventListener('keydown', function(event) {
                        if (event.key === 'F1') {
                            event.preventDefault();
                            openSupportTicketModal();
                        }
                    });

                    (function startStaffChatPolling() {
                        const chatBadgeEl = document.getElementById('staffChatBadge');
                        const staffChatNavLinkEl = document.getElementById('staffChatNavLink');
                        const currentUserId = <?php echo (int)($_SESSION['user_id'] ?? 0); ?>;
                        const latestSeenIdStorageKey = `staff_chat_latest_seen_id_${currentUserId}`;
                        const unreadCountStorageKey = `staff_chat_unread_count_${currentUserId}`;
                        const isOnStaffChatPage = <?php echo basename($_SERVER['PHP_SELF']) === 'staff_chat.php' ? 'true' : 'false'; ?>;
                        let hasInitialized = Number(localStorage.getItem(latestSeenIdStorageKey) || 0) > 0;

                        function renderChatBadge() {
                            if (!chatBadgeEl) {
                                return;
                            }

                            const unreadCount = Number(localStorage.getItem(unreadCountStorageKey) || 0);
                            if (unreadCount > 0) {
                                chatBadgeEl.style.display = 'inline-block';
                                chatBadgeEl.textContent = String(unreadCount);
                            } else {
                                chatBadgeEl.style.display = 'none';
                            }
                        }

                        function markAsRead() {
                            localStorage.setItem(unreadCountStorageKey, '0');
                            renderChatBadge();
                        }

                        staffChatNavLinkEl?.addEventListener('click', function() {
                            markAsRead();
                        });

                        if (isOnStaffChatPage) {
                            markAsRead();
                        } else {
                            renderChatBadge();
                        }

                        function poll() {
                            const sinceId = Number(localStorage.getItem(latestSeenIdStorageKey) || 0);

                            fetch('staff_chat_fetch.php?since_id=' + encodeURIComponent(String(sinceId)), {
                                headers: { 'Accept': 'application/json' }
                            })
                                .then((response) => response.json())
                                .then((data) => {
                                    if (!data.success || !Array.isArray(data.messages)) {
                                        return;
                                    }

                                    let latestId = sinceId;
                                    let incomingVisibleMessages = 0;

                                    data.messages.forEach((message) => {
                                        const messageId = Number(message.id || 0);
                                        if (messageId > latestId) {
                                            latestId = messageId;
                                        }
                                        incomingVisibleMessages += 1;
                                    });

                                    if (!hasInitialized && sinceId === 0) {
                                        if (latestId > 0) {
                                            localStorage.setItem(latestSeenIdStorageKey, String(latestId));
                                        }
                                        hasInitialized = true;
                                        return;
                                    }

                                    hasInitialized = true;

                                    if (latestId > sinceId) {
                                        localStorage.setItem(latestSeenIdStorageKey, String(latestId));
                                    }

                                    if (isOnStaffChatPage) {
                                        markAsRead();
                                        return;
                                    }

                                    if (incomingVisibleMessages > 0) {
                                        const currentUnread = Number(localStorage.getItem(unreadCountStorageKey) || 0);
                                        localStorage.setItem(unreadCountStorageKey, String(currentUnread + incomingVisibleMessages));
                                        renderChatBadge();

                                        Swal.fire({
                                            toast: true,
                                            position: 'top-end',
                                            icon: 'info',
                                            title: incomingVisibleMessages === 1
                                                ? 'New staff chat message'
                                                : `${incomingVisibleMessages} new staff chat messages`,
                                            showConfirmButton: false,
                                            timer: 2500,
                                            timerProgressBar: true
                                        });
                                    }
                                })
                                .catch(() => {
                                    // Ignore transient polling failures.
                                });
                        }

                        poll();
                        setInterval(poll, 5000);
                    })();

                    <?php if ((isset($_SESSION['role']) && $_SESSION['role'] === 'admin') || !empty($_SESSION['can_manage_users'])): ?>
                    (function startSupportTicketPolling() {
                        const badgeEl = document.getElementById('supportTicketBadge');
                        const latestIdStorageKey = 'support_latest_open_ticket_id';
                        let hasInitialized = false;

                        function poll() {
                            fetch('support_ticket_stats.php', { headers: { 'Accept': 'application/json' } })
                                .then((response) => response.json())
                                .then((data) => {
                                    if (!data.success) {
                                        return;
                                    }

                                    const openCount = Number(data.open_count || 0);
                                    const latestOpenTicketId = Number(data.latest_open_ticket_id || 0);

                                    if (badgeEl) {
                                        if (openCount > 0) {
                                            badgeEl.style.display = 'inline-block';
                                            badgeEl.textContent = String(openCount);
                                        } else {
                                            badgeEl.style.display = 'none';
                                        }
                                    }

                                    const storedLatestId = Number(localStorage.getItem(latestIdStorageKey) || 0);

                                    if (!hasInitialized) {
                                        localStorage.setItem(latestIdStorageKey, String(Math.max(storedLatestId, latestOpenTicketId)));
                                        hasInitialized = true;
                                        return;
                                    }

                                    if (latestOpenTicketId > storedLatestId) {
                                        localStorage.setItem(latestIdStorageKey, String(latestOpenTicketId));
                                        Swal.fire({
                                            icon: 'info',
                                            title: 'New support ticket received',
                                            text: 'A staff member submitted a new support ticket.',
                                            showCancelButton: true,
                                            confirmButtonText: 'Open Support Center',
                                            cancelButtonText: 'Later',
                                            allowOutsideClick: true
                                        }).then((result) => {
                                            if (result.isConfirmed) {
                                                window.location.href = 'support_center.php';
                                            }
                                        });
                                    }
                                })
                                .catch(() => {
                                    // Silent fail to avoid interrupting user flow.
                                });
                        }

                        poll();
                        setInterval(poll, 15000);
                    })();
                    <?php endif; ?>
                    </script>
                </div>
            </li>

        </ul>
    </div>
</div>

<button type="button" class="helpdesk-fab" id="helpDeskFloatingBtn" title="Do you need help?">
    <i class="fas fa-headset"></i>
    <span>DO YOU NEED HELP!</span>
</button>
