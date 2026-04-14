<?php
session_start();

require_once 'config/timezone.php';
require_once 'config/database.php';
require_once 'config/auth.php';
require_once 'config/secure_links.php';
require_once 'config/patient_documents.php';

$isStaff = isLoggedIn() && canManagePatients();
$isPatientSession = isPatientLoggedIn();

$token = '';
$tokenPatientId = null;
if (!$isStaff && !$isPatientSession) {
    if (isset($_GET['token']) && trim((string)$_GET['token']) !== '') {
        $token = trim((string)$_GET['token']);
    } elseif (isset($_GET['t']) && trim((string)$_GET['t']) !== '') {
        $token = decodeUrlToken(trim((string)$_GET['t']));
    }

    if ($token === '') {
        http_response_code(403);
        echo 'Access denied.';
        exit;
    }

    $tokenData = validateReportToken($token);
    if (!$tokenData) {
        http_response_code(403);
        echo 'Invalid or expired token.';
        exit;
    }

    $tokenPatientId = (int)($tokenData['patient_id'] ?? 0);
}

$documentId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$mode = trim((string)($_GET['mode'] ?? 'download'));
$disposition = $mode === 'inline' ? 'inline' : 'attachment';

if ($documentId <= 0) {
    http_response_code(400);
    echo 'Invalid document ID.';
    exit;
}

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    http_response_code(500);
    echo 'Database connection failed.';
    exit;
}

if (!ensurePatientDocumentTables($conn)) {
    $conn->close();
    http_response_code(500);
    echo 'Patient document table initialization failed.';
    exit;
}

$query = "SELECT id, patient_id, file_path, file_name, file_mime FROM patient_documents WHERE id = ? LIMIT 1";
$stmt = $conn->prepare($query);
if (!$stmt) {
    $conn->close();
    http_response_code(500);
    echo 'Failed to prepare document query.';
    exit;
}

$stmt->bind_param('i', $documentId);
$stmt->execute();
$result = $stmt->get_result();
$document = $result ? $result->fetch_assoc() : null;
$stmt->close();
$conn->close();

if (!$document) {
    http_response_code(404);
    echo 'Document not found.';
    exit;
}

$documentPatientId = (int)($document['patient_id'] ?? 0);
if (!$isStaff) {
    if ($isPatientSession) {
        $sessionPatientId = (int)getCurrentPatientId();
        if ($sessionPatientId <= 0 || $sessionPatientId !== $documentPatientId) {
            http_response_code(404);
            echo 'Document not found.';
            exit;
        }
    } else {
        if ($tokenPatientId === null || $tokenPatientId <= 0 || $tokenPatientId !== $documentPatientId) {
            http_response_code(404);
            echo 'Document not found.';
            exit;
        }
    }
}

$filePathRelative = (string)($document['file_path'] ?? '');
if (strpos($filePathRelative, 'uploads/patient_documents/') !== 0) {
    http_response_code(400);
    echo 'Invalid document path.';
    exit;
}

$absolutePath = __DIR__ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $filePathRelative);
$baseDir = realpath(__DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'patient_documents');
$fileRealPath = realpath($absolutePath);

if ($baseDir === false || $fileRealPath === false || strpos($fileRealPath, $baseDir) !== 0 || !is_file($fileRealPath)) {
    http_response_code(404);
    echo 'File not found.';
    exit;
}

$downloadName = (string)($document['file_name'] ?? 'document');
$downloadName = preg_replace('/[\r\n]+/', '_', $downloadName);
if ($downloadName === '') {
    $downloadName = 'document';
}

$mimeType = (string)($document['file_mime'] ?? 'application/octet-stream');
$fileSize = filesize($fileRealPath);
if ($fileSize === false) {
    $fileSize = 0;
}

header('Content-Description: File Transfer');
header('Content-Type: ' . $mimeType);
header('Content-Disposition: ' . $disposition . '; filename="' . addslashes($downloadName) . '"');
header('Content-Length: ' . $fileSize);
header('X-Content-Type-Options: nosniff');

readfile($fileRealPath);
exit;
