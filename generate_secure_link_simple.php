<?php
// Enable error reporting for debugging (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);  // Changed to 1 to show errors
ob_start();

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include required files
require_once 'config/timezone.php';
require_once 'config/database.php';
require_once 'config/auth.php';
require_once 'config/secure_links.php';

// Set JSON content type
header('Content-Type: application/json');

// Handle only POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method. Only POST is allowed.'
    ]);
    exit;
}

// Check if user has permission to generate links
if (!canGenerateLinks()) {
    http_response_code(403); // Forbidden
    echo json_encode([
        'success' => false,
        'message' => 'You do not have permission to generate patient links.'
    ]);
    exit;
}

// Get and validate input
$patientId = isset($_POST['patient_id']) ? (int)$_POST['patient_id'] : 0;
$reportId = isset($_POST['report_id']) ? (int)$_POST['report_id'] : 0;

if ($patientId <= 0) {
    http_response_code(400); // Bad Request
    echo json_encode([
        'success' => false,
        'message' => 'Invalid patient ID provided.'
    ]);
    exit;
}

// Check if user is logged in
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    http_response_code(401); // Unauthorized
    echo json_encode([
        'success' => false,
        'message' => 'Authentication required. Please log in.'
    ]);
    exit;
}

try {
    // Get database connection
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($conn->connect_error) {
        throw new Exception('Database connection failed: ' . $conn->connect_error);
    }

    // Verify patient exists and get name for logging
    $stmt = $conn->prepare("SELECT id, name FROM patients WHERE id = ?");
    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }
    
    $stmt->bind_param('i', $patientId);
    if (!$stmt->execute()) {
        throw new Exception('Execute failed: ' . $stmt->error);
    }
    
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        throw new Exception('Patient not found.');
    }

    $patientRow = $result->fetch_assoc();
    $patientName = $patientRow['name'] ?? '';

    // Generate token and URL
    $token = generateSecureToken();  // Using the function from secure_links.php
    
    // Store the token in the database - now valid for 90 days
    $expiryDate = date("Y-m-d H:i:s", strtotime('+90 days'));
    $stmt = $conn->prepare("
        INSERT INTO report_links (patient_id, token, expiry_date) 
        VALUES (?, ?, ?)
    ");
    
    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }
    
    $stmt->bind_param('iss', $patientId, $token, $expiryDate);
    if (!$stmt->execute()) {
        throw new Exception('Failed to store token: ' . $stmt->error);
    }

    // Generate a cleaner, shorter patient URL.
    // Allow overriding host/path with PUBLIC_APP_URL (recommended for domain-based sharing).
    $publicBaseUrl = getenv('PUBLIC_APP_URL');
    if ($publicBaseUrl) {
        $baseUrl = rtrim($publicBaseUrl, '/');
    } else {
        $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http');
        $host = $_SERVER['HTTP_HOST'];
        $scriptName = dirname($_SERVER['SCRIPT_NAME']);
        $basePath = rtrim(str_replace('\\', '/', $scriptName), '/');
        $baseUrl = "$protocol://$host$basePath";
    }

    // Keep full token in DB; expose shorter URL-safe token in query string.
    $shortToken = encodeTokenForUrl($token);
    $url = $baseUrl . '/patient_dashboard.php?t=' . urlencode($shortToken);

    // Branded share text for staff to send to patients.
    $shareTitle = 'Bato Clinic - Medical Reports';
    $shareMessage = $shareTitle . "\n"
        . 'Patient: ' . $patientName . "\n"
        . 'Secure Link: ' . $url;

    // Log the action into user_activity_log so it appears in activity_logs.php
    // activity_type: generate_link, entity_id: patient ID, entity_name: patient name
    logUserActivity('generate_link', $patientId, 'Generated patient dashboard link', $patientName);

    // Return success response
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Secure link generated successfully.',
        'token' => $token,
        'short_token' => $shortToken,
        'url' => $url,
        'expires' => $expiryDate,
        'patient_name' => $patientName,
        'share_title' => $shareTitle,
        'share_message' => $shareMessage
    ]);

} catch (Exception $e) {
    // Log error
    error_log("Secure Link Error: " . $e->getMessage());
    
    // Return error response
    http_response_code(500); // Internal Server Error
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
} finally {
    // Close database connection if it exists
    if (isset($conn)) {
        $conn->close();
    }
    ob_end_flush();
}