<?php
session_start();

require_once 'config/timezone.php';
require_once 'config/database.php';
require_once 'config/auth.php';
require_once 'config/staff_chat.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

if (!hasRole(['admin'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Only admins can clear chat']);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

if (!ensureStaffChatTables($conn)) {
    $conn->close();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Chat table initialization failed']);
    exit;
}

$attachmentPaths = [];
$result = $conn->query("SELECT attachment_path FROM staff_chat_messages WHERE attachment_path IS NOT NULL AND attachment_path <> ''");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $attachmentPaths[] = (string)$row['attachment_path'];
    }
}

$deleteOk = $conn->query("DELETE FROM staff_chat_messages");
if ($deleteOk !== true) {
    $conn->close();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to clear chat messages']);
    exit;
}

$baseUploadDir = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'staff_chat' . DIRECTORY_SEPARATOR;
$baseUploadDirReal = realpath($baseUploadDir);

foreach ($attachmentPaths as $relativePath) {
    if (strpos($relativePath, 'uploads/staff_chat/') !== 0) {
        continue;
    }

    $fileName = basename($relativePath);
    if ($fileName === '' || $fileName === '.' || $fileName === '..') {
        continue;
    }

    if ($baseUploadDirReal === false) {
        continue;
    }

    $fullPath = $baseUploadDirReal . DIRECTORY_SEPARATOR . $fileName;
    if (is_file($fullPath)) {
        @unlink($fullPath);
    }
}

$conn->close();

echo json_encode([
    'success' => true,
    'message' => 'Staff chat has been cleared successfully.'
]);
