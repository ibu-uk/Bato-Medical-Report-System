<?php
// Start session
session_start();

// Set JSON response header
header('Content-Type: application/json');

// Check session state
$sessionData = [
    'session_id' => session_id(),
    'session_status' => session_status(),
    'session_data' => $_SESSION,
    'user_id_exists' => isset($_SESSION['user_id']),
    'user_id_value' => $_SESSION['user_id'] ?? 'not_set',
    'all_session_keys' => array_keys($_SESSION)
];

echo json_encode([
    'success' => true,
    'message' => 'Session debug info',
    'session_info' => $sessionData
]);
?>
