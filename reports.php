<?php
// Start session
session_start();

// Include timezone configuration
require_once 'config/timezone.php';

// Include database configuration
require_once 'config/database.php';

// Include authentication helpers
require_once 'config/auth.php';

// Require login to access this page
requireLogin();

// Get all reports, including whether the patient already has an active link
$query = "SELECT r.id, r.report_date, r.created_at, r.patient_id,
                 p.name as patient_name, p.civil_id,
                 d.name as doctor_name,
                 u.full_name as generated_by,
                 COALESCE(rl.active_links, 0) AS active_links
          FROM reports r
          JOIN patients p ON r.patient_id = p.id
          JOIN doctors d ON r.doctor_id = d.id
          LEFT JOIN users u ON r.user_id = u.id
          LEFT JOIN (
              SELECT patient_id, COUNT(*) AS active_links
              FROM report_links
              WHERE expiry_date > NOW()
              GROUP BY patient_id
          ) rl ON rl.patient_id = r.patient_id
          ORDER BY r.created_at DESC";
$reports = executeQuery($query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - Bato Medical Report System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
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
            border-bottom: 1px solid rgba(0,0,0,.125);
            background-color: #f8f9fa;
        }
        .btn-back {
            margin-right: 10px;
        }
        .table-responsive {
            margin-top: 20px;
        }
        .action-buttons .btn {
            margin: 0 2px;
            padding: 0.25rem 0.5rem;
        }
        /* Table styling */
        #reportsTable {
            border-collapse: separate;
            border-spacing: 0;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            overflow: hidden;
        }
        #reportsTable tbody tr {
            background-color: #fff !important;
            transition: background-color 0.2s;
            border-bottom: 1px solid #dee2e6;
        }
        #reportsTable tbody tr:not(:last-child) {
            border-bottom: 1px solid #c6c8ca;
        }
        #reportsTable tbody tr:hover {
            background-color: #f8f9fa !important;
        }
        #reportsTable td, 
        #reportsTable th {
            padding: 12px 15px;
            vertical-align: middle;
            border-right: 1px solid #dee2e6;
        }
        #reportsTable td:last-child, 
        #reportsTable th:last-child {
            border-right: none;
        }
        #reportsTable thead th {
            background-color: #e9ecef;
            border-bottom: 2px solid #c6c8ca;
            font-weight: 600;
            color: #495057;
            text-transform: uppercase;
            font-size: 0.8rem;
            letter-spacing: 0.5px;
        }
        .page-item.active .page-link {
            background-color: #6c757d;
            border-color: #6c757d;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row mb-3">
            <div class="col-12">
                <a href="index.php" class="btn btn-secondary btn-back">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
                <h2 class="d-inline-block">Medical Reports</h2>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">All Reports</h5>
                        <?php if (hasRole(['admin', 'doctor'])): ?>
                        <a href="index.php" class="btn btn-primary">
                            <i class="fas fa-plus"></i> New Report
                        </a>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <?php
                        // Display success or error messages
                        if (isset($_SESSION['success'])) {
                            echo '<div class="alert alert-success">' . $_SESSION['success'] . '</div>';
                            unset($_SESSION['success']);
                        }
                        if (isset($_SESSION['error'])) {
                            echo '<div class="alert alert-danger">' . $_SESSION['error'] . '</div>';
                            unset($_SESSION['error']);
                        }
                        ?>
                        <div class="table-responsive">
                            <table id="reportsTable" class="table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Patient Name</th>
                                        <th>Civil ID</th>
                                        <th>Report Date</th>
                                        <th>Doctor</th>
                                        <th>Created By</th>
                                        <th>Created At</th>
                                        <?php if (hasRole(['admin', 'receptionist'])): ?>
                                        <th>Link Status</th>
                                        <?php endif; ?>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    if ($reports && $reports->num_rows > 0) {
                                        while ($row = $reports->fetch_assoc()) {
                                            echo "<tr>";
                                            echo "<td>{$row['id']}</td>";
                                            echo "<td>{$row['patient_name']}</td>";
                                            echo "<td>{$row['civil_id']}</td>";
                                            echo "<td>" . date('Y-m-d', strtotime($row['report_date'])) . "</td>";
                                            echo "<td>{$row['doctor_name']}</td>";
                                            echo "<td>{$row['generated_by']}</td>";
                                            echo "<td>" . date('Y-m-d H:i', strtotime($row['created_at'])) . "</td>";

                                            // Link status column: show whether patient already has an active link (only admin & receptionist)
                                            if (hasRole(['admin', 'receptionist'])) {
                                                if (!empty($row['active_links']) && (int)$row['active_links'] > 0) {
                                                    echo "<td><span class='badge bg-success'>Link Active</span></td>";
                                                } else {
                                                    echo "<td><span class='badge bg-secondary'>No Link</span></td>";
                                                }
                                            }

                                            echo '<td class="action-buttons">';

                                            // View button is available to all logged-in roles
                                            echo '<a href="view_report.php?id=' . $row['id'] . '" class="btn btn-sm btn-outline-primary" title="View" target="_blank">
                                                    <i class="fas fa-eye"></i>
                                                </a>';

                                            // Admin and receptionist can generate links
                                            if (hasRole(['admin', 'receptionist'])) {
                                                echo '<a href="javascript:void(0);" onclick="generatePatientLink(' . $row['patient_id'] . ')" class="btn btn-sm btn-outline-success" title="Generate Patient Link">
                                                        <i class="fas fa-link"></i>
                                                    </a>';
                                            }

                                            // Only admin/doctor can edit or delete
                                            if (hasRole(['admin', 'doctor'])) {
                                                echo '<a href="edit_report.php?id=' . $row['id'] . '" class="btn btn-sm btn-outline-warning" title="Edit">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <button class="btn btn-sm btn-outline-danger" onclick="if(confirm(\'Are you sure you want to delete this report?\')) { window.location.href=\'delete_report.php?id=' . $row['id'] . '\'; }" title="Delete">
                                                        <i class="fas fa-trash"></i>
                                                    </button>';
                                            }

                                            echo '</td>';
                                            echo "</tr>";
                                        }
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Delete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete this report? This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <a href="#" id="confirmDelete" class="btn btn-danger">Delete</a>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript Libraries -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
    
    <script>
    // Function to show toast notification
    function showToast(message, isError = false) {
        const toast = document.createElement('div');
        toast.className = `toast-notification ${isError ? 'error' : ''}`;
        toast.textContent = message;
        document.body.appendChild(toast);
        
        setTimeout(() => toast.classList.add('show'), 10);
        
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 300);
        }, 3000);
    }
    
    // Function to handle modal cleanup
    function setupModalCleanup(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            const bsModal = bootstrap.Modal.getInstance(modal);
            if (bsModal) {
                modal.addEventListener('hidden.bs.modal', function() {
                    bsModal.dispose();
                    modal.remove();
                });
            }
        }
    }
    
    // Function to generate patient link
    function generatePatientLink(patientId) {
        console.log('Generating link for patient ID:', patientId);
        showToast('Generating secure link...');
        
        fetch('generate_secure_link_simple.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'patient_id=' + patientId
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok: ' + response.statusText);
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                // Remove any existing modals first
                const existingModal = document.getElementById('secureLinkModal');
                if (existingModal) {
                    existingModal.remove();
                }

                // Create and show the modal
                const modalHtml = `
                    <div class="modal fade" id="secureLinkModal" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog modal-lg">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">
                                        <i class="fas fa-shield-alt me-2"></i>
                                        Patient Link Generated Successfully
                                    </h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="alert alert-success">
                                        <i class="fas fa-check-circle me-2"></i>
                                        <strong>Success!</strong> Patient dashboard link has been generated.
                                    </div>
                                    <div class="mb-3">
                                        <h6><i class="fas fa-info-circle me-2"></i> About This Link:</h6>
                                        <ul>
                                            <li><strong>Permanent Access:</strong> One link for patient's complete medical history</li>
                                            <li><strong>All Reports:</strong> Patient can view all their medical reports</li>
                                            <li><strong>Prescriptions:</strong> All medication records included</li>
                                            <li><strong>Nurse Treatments:</strong> All treatment records included</li>
                                            <li><strong>Secure:</strong> Token-based authentication prevents unauthorized access</li>
                                            <li><strong>Privacy:</strong> No patient IDs exposed in URLs</li>
                                        </ul>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label"><strong>Patient Dashboard Link:</strong></label>
                                        <div class="input-group">
                                            <input type="text" class="form-control" value="${data.url}" id="patientLinkInput" readonly>
                                            <button class="btn btn-outline-primary" type="button" onclick="copyToClipboard('patientLinkInput')">
                                                <i class="fas fa-copy"></i> Copy
                                            </button>
                                        </div>
                                    </div>
                                    <div class="text-center">
                                        <a href="${data.url}" target="_blank" class="btn btn-primary">
                                            <i class="fas fa-external-link-alt me-2"></i>
                                            Open Patient Dashboard
                                        </a>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                </div>
                            </div>
                        </div>
                    </div>`;

                // Add modal to the DOM
                document.body.insertAdjacentHTML('beforeend', modalHtml);
                
                // Initialize and show the modal
                const modalElement = document.getElementById('secureLinkModal');
                const modal = new bootstrap.Modal(modalElement);
                modal.show();
                
                // Clean up the modal when it's closed
                modalElement.addEventListener('hidden.bs.modal', function () {
                    modal.dispose();
                    modalElement.remove();
                });
            } else {
                throw new Error(data.message || 'Unknown error occurred');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('Failed to generate patient link: ' + error.message, true);
        });
    }

    // Function to view document with token
    function viewDocumentWithToken(documentId, patientId, documentType) {
        console.log('Viewing document:', {documentId, patientId, documentType});
        showToast('Generating document link...');
        
        // Hide all generate link buttons while loading
        document.querySelectorAll('.generate-link-btn').forEach(btn => {
            btn.style.display = 'none';
        })
        .then(data => {
            if (data.success) {
                // Create secure document URL with encoded ID
                const docParam = btoa(documentId + '_' + patientId);
                // Get the base URL dynamically including the Bato-Medical-Report-System folder
                const pathParts = window.location.pathname.split('/');
                // Remove the current file name (reports.php) and any empty parts
                pathParts.pop();
                const basePath = pathParts.join('/');
                let viewUrl;
                
                if (documentType === 'report') {
                    viewUrl = `${window.location.origin}${basePath}/view_report.php?token=${encodeURIComponent(data.token)}&doc=${docParam}`;
                } else if (documentType === 'prescription') {
                    viewUrl = 'view_prescription.php?token=' + encodeURIComponent(data.token) + '&doc=' + docParam;
                } else if (documentType === 'treatment') {
                    viewUrl = 'view_nurse_treatment.php?token=' + encodeURIComponent(data.token) + '&doc=' + docParam;
                }
                
                // Open in new window
                window.open(viewUrl, '_blank');
                showToast('Document opened in new tab');
            } else {
                throw new Error(data.message || 'Failed to generate document link');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('Failed to open document: ' + error.message, true);
        });
    }

    // Helper function to copy text to clipboard (with fallback when navigator.clipboard is unavailable)
    function copyToClipboard(elementId) {
        const element = document.getElementById(elementId);
        if (!element) return;

        const text = element.value;

        // Prefer modern Clipboard API when available
        if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
            navigator.clipboard.writeText(text)
                .then(() => {
                    showToast('Copied to clipboard!');
                })
                .catch(err => {
                    console.error('Failed to copy with Clipboard API:', err);
                    // Fallback for browsers where writeText fails
                    const textarea = document.createElement('textarea');
                    textarea.value = text;
                    document.body.appendChild(textarea);
                    textarea.select();
                    try {
                        document.execCommand('copy');
                        showToast('Copied to clipboard!');
                    } catch (e) {
                        console.error('Fallback copy failed:', e);
                        showToast('Failed to copy. Please try again.', true);
                    }
                    document.body.removeChild(textarea);
                });
        } else {
            // Older browsers / non-secure origins: use execCommand directly
            const textarea = document.createElement('textarea');
            textarea.value = text;
            document.body.appendChild(textarea);
            textarea.select();
            try {
                document.execCommand('copy');
                showToast('Copied to clipboard!');
            } catch (e) {
                console.error('Fallback copy failed:', e);
                showToast('Failed to copy. Please try again.', true);
            }
            document.body.removeChild(textarea);
        }
    }

    // Initialize when document is ready
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize DataTable
        const dataTable = $('#reportsTable').DataTable({
            order: [[3, 'desc']], // Sort by report date (column index 3) in descending order
            responsive: true,
            pageLength: 25,
            dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>rt<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
            language: {
                search: "_INPUT_",
                searchPlaceholder: "Search reports..."
            },
            columnDefs: [
                { type: 'date', targets: 3 }, // Ensure proper date sorting
                { className: 'text-center', targets: [0, 7] } // Center align ID and Actions columns
            ],
            initComplete: function() {
                $('.dataTables_filter input').addClass('form-control');
                $('.dataTables_length select').addClass('form-select');
                $('.dataTables_paginate .paginate_button').addClass('btn btn-sm btn-outline-secondary');
            },
            createdRow: function(row, data, dataIndex) {
                $(row).css('background-color', 'transparent');
                $(row).hover(
                    function() { $(this).css('background-color', '#f2f2f2'); },
                    function() { $(this).css('background-color', 'transparent'); }
                );
            }
        });

        // Remove DataTables default classes that cause blue highlight
        $('.dataTables_wrapper .dataTables_length select').removeClass('custom-select custom-select-sm');
        $('.dataTables_wrapper .dataTables_filter input').removeClass('form-control-sm');
    });
    
    // Add toast styles
    const style = document.createElement('style');
    style.textContent = `
        .toast-notification {
            position: fixed;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            background-color: #28a745;
            color: white;
            padding: 12px 24px;
            border-radius: 4px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            z-index: 1051;
            opacity: 0;
            transition: opacity 0.3s ease-in-out;
            max-width: 90%;
            text-align: center;
            pointer-events: none;
        }
        .toast-notification.error {
            background-color: #dc3545;
        }
        .toast-notification.show {
            opacity: 1;
        }
        
        /* Modal styles */
        .modal-backdrop {
            z-index: 1040 !important;
        }
        .modal {
            z-index: 1050 !important;
        }
    `;
    document.head.appendChild(style);
    </script>
</body>
</html>