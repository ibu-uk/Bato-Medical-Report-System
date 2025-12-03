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

// Get all reports
$query = "SELECT r.id, r.report_date, r.created_at, p.name as patient_name, p.civil_id, d.name as doctor_name, 
          u.full_name as generated_by
          FROM reports r
          JOIN patients p ON r.patient_id = p.id
          JOIN doctors d ON r.doctor_id = d.id
          LEFT JOIN users u ON r.user_id = u.id
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
        /* Table row styling */
        #reportsTable tbody tr {
            background-color: transparent !important;
            transition: background-color 0.2s;
        }
        #reportsTable tbody tr:hover {
            background-color: #f2f2f2 !important;
        }
        /* Remove DataTables default row hover */
        .table-hover tbody tr:hover td, .table-hover tbody tr:hover th {
            background-color: transparent !important;
        }
        /* Style for table header */
        #reportsTable thead th {
            background-color: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
        }
        /* Remove blue highlight from pagination */
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
                        <a href="index.php" class="btn btn-primary">
                            <i class="fas fa-plus"></i> New Report
                        </a>
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
                                            $report_link = "http://142.93.208.83/Bato-Medical-Report-System/view_report.php?id=" . $row['id'];
                                            $whatsapp_message = "BATO CLINIC: Your medical report is ready.\nView (valid 3 days):\n$report_link\n\nYou can also download as PDF.";
                                            
                                            echo '<td class="action-buttons">
                                                <a href="view_report.php?id=' . $row['id'] . '" class="btn btn-sm btn-outline-primary" title="View">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <button onclick="showWhatsAppModal(`' . addslashes($whatsapp_message) . '`)" class="btn btn-sm btn-outline-success" title="Share via WhatsApp">
                                                    <i class="fab fa-whatsapp"></i>
                                                </button>';
                                            
                                            // Only show edit and delete buttons for admin and doctor users
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

    <!-- WhatsApp Message Modal -->
    <div class="modal fade" id="whatsappModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Copy WhatsApp Message</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <textarea id="whatsappMessageText" class="form-control" rows="5" readonly></textarea>
                    <small class="text-muted">Select and copy the message above (Ctrl+C or right-click > Copy).</small>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
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
        $(document).ready(function() {
            // Initialize DataTable with responsive extension
            $('#reportsTable').DataTable({
                order: [[3, 'desc']], // Sort by report date (column index 3) in descending order
                responsive: true,
                pageLength: 25,
                dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>rt<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
                language: {
                    search: "_INPUT_",
                    searchPlaceholder: "Search reports..."
                },
                columnDefs: [
                    { type: 'date', targets: 3 }, // Ensure proper date sorting for the report date column
                    { className: 'text-center', targets: [0, 7] } // Center align ID and Actions columns
                ],
                // Remove DataTables default styling
                initComplete: function() {
                    $('.dataTables_filter input').addClass('form-control');
                    $('.dataTables_length select').addClass('form-select');
                    $('.dataTables_paginate .paginate_button').addClass('btn btn-sm btn-outline-secondary');
                },
                // Custom row styling
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
        
        function deleteReport(id) {
            if (confirm('Are you sure you want to delete this report? This action cannot be undone.')) {
                window.location.href = 'delete_report.php?id=' + id;
            }
        }
        
        function showWhatsAppModal(message) {
            var textarea = document.getElementById('whatsappMessageText');
            textarea.value = message;
            var modal = new bootstrap.Modal(document.getElementById('whatsappModal'));
            modal.show();
            setTimeout(function() { 
                textarea.select(); 
                document.execCommand('copy');
            }, 300);
        }
    </script>
</body>
</html>
