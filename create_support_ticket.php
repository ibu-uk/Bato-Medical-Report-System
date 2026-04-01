<?php
session_start();

require_once 'config/timezone.php';
require_once 'config/database.php';
require_once 'config/auth.php';
require_once 'config/support.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

$subject = trim($_POST['subject'] ?? '');
$details = trim($_POST['details'] ?? '');
$issueType = trim($_POST['issue_type'] ?? 'general');
$priority = trim($_POST['priority'] ?? 'medium');
$currentPage = trim($_POST['current_page'] ?? '');

if ($subject === '' || $details === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Subject and details are required']);
    exit;
}

$allowedIssueTypes = ['general', 'bug', 'access', 'performance', 'training'];
if (!in_array($issueType, $allowedIssueTypes, true)) {
    $issueType = 'general';
}

$allowedPriorities = ['low', 'medium', 'high', 'urgent'];
if (!in_array($priority, $allowedPriorities, true)) {
    $priority = 'medium';
}

$attachmentPath = null;
$attachmentName = null;
$attachmentType = null;

if (isset($_FILES['attachment']) && is_array($_FILES['attachment']) && ($_FILES['attachment']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
    if (($_FILES['attachment']['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Attachment upload failed. Please try again.']);
        exit;
    }

    $maxBytes = 20 * 1024 * 1024; // 20MB
    $size = (int)($_FILES['attachment']['size'] ?? 0);
    if ($size <= 0 || $size > $maxBytes) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Attachment must be between 1 byte and 20MB.']);
        exit;
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $tmpName = $_FILES['attachment']['tmp_name'];
    $mimeType = $finfo ? finfo_file($finfo, $tmpName) : '';
    if ($finfo) {
        finfo_close($finfo);
    }

    $allowedMimes = [
        'image/jpeg', 'image/png', 'image/webp', 'image/gif',
        'video/mp4', 'video/webm', 'video/quicktime'
    ];

    if (!in_array($mimeType, $allowedMimes, true)) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Only image (jpg, png, webp, gif) or video (mp4, webm, mov) files are allowed.']);
        exit;
    }

    $uploadDir = __DIR__ . '/uploads/support';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to prepare upload folder.']);
        exit;
    }

    if (!is_writable($uploadDir)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Upload folder is not writable on the server.']);
        exit;
    }

    $originalName = basename((string)($_FILES['attachment']['name'] ?? 'attachment'));
    $safeOriginalName = preg_replace('/[^A-Za-z0-9._-]/', '_', $originalName);
    if ($safeOriginalName === '' || $safeOriginalName === null) {
        $safeOriginalName = 'attachment';
    }

    $extension = strtolower((string)pathinfo($safeOriginalName, PATHINFO_EXTENSION));
    $newFileName = 'ticket_' . time() . '_' . bin2hex(random_bytes(6));
    if ($extension !== '') {
        $newFileName .= '.' . $extension;
    }

    $targetPath = $uploadDir . '/' . $newFileName;
    if (!move_uploaded_file($tmpName, $targetPath)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to save uploaded attachment.']);
        exit;
    }

    $attachmentPath = 'uploads/support/' . $newFileName;
    $attachmentName = $safeOriginalName;
    $attachmentType = $mimeType;
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

$ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

$ticketId = supportCreateTicket(
    $conn,
    (int)$_SESSION['user_id'],
    $subject,
    $details,
    $issueType,
    $priority,
    $currentPage,
    $ipAddress,
    $userAgent,
    $attachmentPath,
    $attachmentName,
    $attachmentType
);

if (!$ticketId) {
    $conn->close();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to create support ticket']);
    exit;
}

logUserActivity('support_ticket_create', $ticketId, 'Created support ticket #' . $ticketId, $subject);

$conn->close();

echo json_encode([
    'success' => true,
    'message' => 'Support ticket created successfully',
    'ticket_id' => $ticketId
]);
