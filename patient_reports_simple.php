<?php
session_start();
require_once 'config/timezone.php';
require_once 'config/database.php';
require_once 'config/auth.php';
require_once 'config/secure_links.php';

$token = $_GET['token'] ?? '';
if (empty($token)) {
    die('No token provided');
}

$tokenData = validateReportToken($token);
if (!$tokenData) {
    die('Invalid token');
}

$patientId = $tokenData['patient_id'];
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

$reportsQuery = "SELECT COUNT(*) as count FROM reports WHERE patient_id = ?";
$stmt = $conn->prepare($reportsQuery);
$stmt->bind_param("i", $patientId);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$conn->close();

header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'patient_id' => $patientId,
    'reports_count' => $row['count'],
    'message' => 'Patient dashboard loaded successfully'
]);
?>
