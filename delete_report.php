<?php
// Start session
session_start();

// Include timezone configuration
require_once 'config/timezone.php';

// Include database configuration
require_once 'config/data.php';

// Include authentication helpers
require_once 'config/auth.php';

// Require login to access this page
requireLogin();

// Check if report ID is provided
if (!isset($_GET['id'])) {
    header("Location: reports.php");
    exit;
}

$reportId = sanitize($_GET['id']);

// Log the delete activity before deleting the report
logUserActivity('delete_report', $reportId);

// Delete report tests first (foreign key constraint)
$deleteTestsQuery = "DELETE FROM report_tests WHERE report_id = ?";
$stmt = $conn->prepare($deleteTestsQuery);
$stmt->bind_param("i", $reportId);
$stmt->execute();

// Delete the report
$deleteReportQuery = "DELETE FROM reports WHERE id = ?";
$stmt = $conn->prepare($deleteReportQuery);
$stmt->bind_param("i", $reportId);
$stmt->execute();

// Redirect back to reports page
header("Location: reports.php?deleted=1");
exit;
?>
