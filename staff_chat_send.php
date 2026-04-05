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

$message = trim((string)($_POST['message'] ?? ''));
$hasAttachment = isset($_FILES['attachment']) && is_array($_FILES['attachment']) && (int)($_FILES['attachment']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;

if ($message === '' && !$hasAttachment) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Message or image is required']);
    exit;
}

if (mb_strlen($message) > 1000) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Message is too long (max 1000 characters)']);
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

$userId = (int)($_SESSION['user_id'] ?? 0);
$senderName = staffChatResolveSenderName($conn, $userId);
$isAdmin = hasRole(['admin']);

$attachmentPath = null;
$attachmentName = null;
$attachmentType = null;

if ($hasAttachment) {
    $file = $_FILES['attachment'];
    $uploadError = (int)$file['error'];
    if ($uploadError !== UPLOAD_ERR_OK) {
        $conn->close();
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Attachment upload failed. Please try again.']);
        exit;
    }

    $maxBytes = 10 * 1024 * 1024; // 10MB
    if ((int)$file['size'] > $maxBytes) {
        $conn->close();
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Image exceeds 10MB limit']);
        exit;
    }

    $allowedMime = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif'
    ];

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $detectedMime = $finfo ? finfo_file($finfo, $file['tmp_name']) : '';
    if ($finfo) {
        finfo_close($finfo);
    }

    if (!isset($allowedMime[$detectedMime])) {
        $conn->close();
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Only JPG, PNG, WEBP, or GIF images are allowed']);
        exit;
    }

    $uploadDirFs = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'staff_chat';
    $uploadDirWeb = 'uploads/staff_chat';

    if (!is_dir($uploadDirFs) && !mkdir($uploadDirFs, 0755, true)) {
        $conn->close();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Could not create upload directory']);
        exit;
    }

    if (!is_writable($uploadDirFs)) {
        $conn->close();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Upload directory is not writable']);
        exit;
    }

    $safeBase = preg_replace('/[^a-zA-Z0-9_\.-]/', '_', (string)$file['name']);
    if ($safeBase === '') {
        $safeBase = 'chat_image';
    }

    $extension = $allowedMime[$detectedMime];
    $storedFileName = date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $extension;
    $targetFsPath = $uploadDirFs . DIRECTORY_SEPARATOR . $storedFileName;

    if (!move_uploaded_file($file['tmp_name'], $targetFsPath)) {
        $conn->close();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to save uploaded image']);
        exit;
    }

    $attachmentPath = $uploadDirWeb . '/' . $storedFileName;
    $attachmentName = $safeBase;
    $attachmentType = $detectedMime;
}

$recipientMode = $isAdmin ? trim((string)($_POST['recipient_mode'] ?? 'all')) : 'single';
$targetThreadIds = [];
$isBroadcast = 0;

if ($isAdmin) {
    if ($recipientMode === 'single') {
        $singleRecipientId = (int)($_POST['recipient_user_id'] ?? 0);
        $targetThreadIds = staffChatFilterAllowedRecipientIds($conn, [$singleRecipientId], $userId);
    } elseif ($recipientMode === 'multiple') {
        $rawRecipientIds = $_POST['recipient_user_ids'] ?? [];
        if (!is_array($rawRecipientIds)) {
            $rawRecipientIds = [$rawRecipientIds];
        }
        $targetThreadIds = staffChatFilterAllowedRecipientIds($conn, $rawRecipientIds, $userId);
    } elseif ($recipientMode === 'all') {
        $allRecipients = staffChatGetNonAdminRecipients($conn, $userId);
        foreach ($allRecipients as $recipient) {
            $targetThreadIds[] = (int)$recipient['id'];
        }
        $isBroadcast = 1;
    } else {
        $conn->close();
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Invalid recipient mode']);
        exit;
    }

    if ($recipientMode !== 'all' && empty($targetThreadIds)) {
        $conn->close();
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Please select at least one staff recipient']);
        exit;
    }
}

$insertedMessageIds = [];
if ($isAdmin) {
    if ($recipientMode === 'all') {
        $messageId = staffChatInsertMessage($conn, $userId, $senderName, $message, 0, $attachmentPath, $attachmentName, $attachmentType, 0, 1);
        if ($messageId > 0) {
            $insertedMessageIds[] = $messageId;
        }
    } else {
        foreach ($targetThreadIds as $threadUserId) {
            $messageId = staffChatInsertMessage($conn, $userId, $senderName, $message, 0, $attachmentPath, $attachmentName, $attachmentType, (int)$threadUserId, 0);
            if ($messageId > 0) {
                $insertedMessageIds[] = $messageId;
            }
        }
    }
} else {
    $messageId = staffChatInsertMessage($conn, $userId, $senderName, $message, 0, $attachmentPath, $attachmentName, $attachmentType, $userId, 0);
    if ($messageId > 0) {
        $insertedMessageIds[] = $messageId;
    }
}

if (empty($insertedMessageIds)) {
    $conn->close();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not send message']);
    exit;
}

$botReply = $message !== '' ? staffChatGenerateBotReply($conn, $message) : '';
$botMessageId = 0;
if ($botReply !== '') {
    $botThreadUserId = $userId > 0 ? $userId : 0;
    $botMessageId = staffChatInsertMessage($conn, 0, 'Clinic Assistant Bot', $botReply, 1, null, null, null, $botThreadUserId, 0);
}

$latestMessageId = !empty($insertedMessageIds) ? max($insertedMessageIds) : 0;

$conn->close();

echo json_encode([
    'success' => true,
    'message_id' => $latestMessageId,
    'message_ids' => $insertedMessageIds,
    'bot_message_id' => $botMessageId
]);
