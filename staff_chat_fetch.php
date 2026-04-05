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

$sinceId = isset($_GET['since_id']) ? (int)$_GET['since_id'] : 0;
if ($sinceId < 0) {
    $sinceId = 0;
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

$currentUserId = (int)($_SESSION['user_id'] ?? 0);
$messages = [];

if ($sinceId > 0) {
    $query = "SELECT id, user_id, sender_name, message_text, is_bot, attachment_path, attachment_name, attachment_type, created_at
              FROM staff_chat_messages
              WHERE id > ?
              ORDER BY id ASC
              LIMIT 100";
    $stmt = $conn->prepare($query);
    if ($stmt) {
        $stmt->bind_param('i', $sinceId);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($result && $row = $result->fetch_assoc()) {
            $messages[] = [
                'id' => (int)$row['id'],
                'user_id' => isset($row['user_id']) ? (int)$row['user_id'] : 0,
                'sender_name' => $row['sender_name'],
                'message_text' => $row['message_text'],
                'is_bot' => (int)$row['is_bot'],
                'attachment_path' => $row['attachment_path'] ?? null,
                'attachment_name' => $row['attachment_name'] ?? null,
                'attachment_type' => $row['attachment_type'] ?? null,
                'is_own' => isset($row['user_id']) && (int)$row['user_id'] === $currentUserId,
                'created_at' => $row['created_at']
            ];
        }
        $stmt->close();
    }
} else {
    $query = "SELECT id, user_id, sender_name, message_text, is_bot, attachment_path, attachment_name, attachment_type, created_at
              FROM (
                SELECT id, user_id, sender_name, message_text, is_bot, attachment_path, attachment_name, attachment_type, created_at
                FROM staff_chat_messages
                ORDER BY id DESC
                LIMIT 80
              ) latest
              ORDER BY id ASC";
    $result = $conn->query($query);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $messages[] = [
                'id' => (int)$row['id'],
                'user_id' => isset($row['user_id']) ? (int)$row['user_id'] : 0,
                'sender_name' => $row['sender_name'],
                'message_text' => $row['message_text'],
                'is_bot' => (int)$row['is_bot'],
                'attachment_path' => $row['attachment_path'] ?? null,
                'attachment_name' => $row['attachment_name'] ?? null,
                'attachment_type' => $row['attachment_type'] ?? null,
                'is_own' => isset($row['user_id']) && (int)$row['user_id'] === $currentUserId,
                'created_at' => $row['created_at']
            ];
        }
    }
}

$conn->close();

echo json_encode([
    'success' => true,
    'messages' => $messages
]);
