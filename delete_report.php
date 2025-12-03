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

// Only admin or doctor can access
if (!hasRole(['admin', 'doctor'])) {
    header('Location: reports.php');
    exit;
}

// Check if report ID is provided
if (!isset($_GET['id'])) {
    header("Location: reports.php");
    exit;
}

$reportId = sanitize($_GET['id']);

// Ensure database connection is initialized
if (!isset($conn) || !$conn) {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        die("Database connection failed: " . $conn->connect_error);
    }
}

// Fetch patient name for logging
$patientName = '';

$stmt = $conn->prepare("SELECT p.name FROM reports r JOIN patients p ON r.patient_id = p.id WHERE r.id = ? LIMIT 1");
if ($stmt) {
    $stmt->bind_param("i", $reportId);
    $stmt->execute();
    $stmt->bind_result($patientNameResult);
    if ($stmt->fetch()) {
        $patientName = $patientNameResult;
    }
    $stmt->close();
}
// Log the delete activity before deleting the report
logUserActivity('delete_report', $reportId, null, $patientName);

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
