<?php
session_start();

require_once 'config/timezone.php';
require_once 'config/database.php';
require_once 'config/auth.php';
require_once 'config/support.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

if (!supportCanManageTickets()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit;
}

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

if (!ensureSupportTables($conn)) {
    $conn->close();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Support table initialization failed']);
    exit;
}

$openCount = 0;
$latestOpenId = 0;

$countResult = $conn->query("SELECT COUNT(*) AS total FROM support_tickets WHERE status = 'open'");
if ($countResult && $row = $countResult->fetch_assoc()) {
    $openCount = (int)$row['total'];
}

$latestResult = $conn->query("SELECT id FROM support_tickets WHERE status = 'open' ORDER BY id DESC LIMIT 1");
if ($latestResult && $row = $latestResult->fetch_assoc()) {
    $latestOpenId = (int)$row['id'];
}

$conn->close();

echo json_encode([
    'success' => true,
    'open_count' => $openCount,
    'latest_open_ticket_id' => $latestOpenId
]);
