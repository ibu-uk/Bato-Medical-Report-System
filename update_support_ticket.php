<?php
session_start();

require_once 'config/timezone.php';
require_once 'config/database.php';
require_once 'config/auth.php';
require_once 'config/support.php';

requireLogin();

if (!supportCanManageTickets()) {
    $_SESSION['error'] = 'You do not have permission to update support tickets.';
    header('Location: support_center.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error'] = 'Invalid request method.';
    header('Location: support_center.php');
    exit;
}

$ticketId = (int)($_POST['ticket_id'] ?? 0);
$status = trim($_POST['status'] ?? '');

$allowedStatuses = ['open', 'in_progress', 'resolved', 'closed'];
if ($ticketId <= 0 || !in_array($status, $allowedStatuses, true)) {
    $_SESSION['error'] = 'Invalid ticket update request.';
    header('Location: support_center.php');
    exit;
}

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    $_SESSION['error'] = 'Database connection failed.';
    header('Location: support_center.php');
    exit;
}

ensureSupportTables($conn);

$resolvedAt = null;
if ($status === 'resolved' || $status === 'closed') {
    $resolvedAt = date('Y-m-d H:i:s');
}

$query = "UPDATE support_tickets
          SET status = ?,
              assigned_to = ?,
              resolved_at = CASE WHEN ? IS NULL THEN resolved_at ELSE ? END,
              updated_at = NOW()
          WHERE id = ?";
$stmt = $conn->prepare($query);
$userId = (int)$_SESSION['user_id'];
$stmt->bind_param('sissi', $status, $userId, $resolvedAt, $resolvedAt, $ticketId);

if ($stmt->execute()) {
    $_SESSION['success'] = 'Support ticket #' . $ticketId . ' updated.';
    logUserActivity('support_ticket_update', $ticketId, 'Support ticket status changed to ' . $status, 'Support Ticket');
} else {
    $_SESSION['error'] = 'Failed to update support ticket.';
}

$stmt->close();
$conn->close();

header('Location: support_center.php');
exit;
