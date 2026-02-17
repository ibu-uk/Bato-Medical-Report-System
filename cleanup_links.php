<?php
/**
 * Automatic Cleanup Script for Expired Secure Links
 * Can be run via cron job or manually
 */

// Include required files
require_once 'config/timezone.php';
require_once 'config/database.php';
require_once 'config/secure_links.php';

echo "Starting cleanup of expired secure links...\n";

// Clean up expired tokens
$cleaned = cleanupExpiredTokens();

echo "Cleanup completed. Removed $cleaned expired links.\n";

// Optional: Clean up old access logs (older than 30 days)
$conn = getDbConnection();
$logCleanupQuery = "DELETE FROM report_access_log WHERE accessed_at < DATE_SUB(NOW(), INTERVAL 30 DAY)";
$stmt = $conn->prepare($logCleanupQuery);
$stmt->execute();
$logCleaned = $stmt->affected_rows;
$stmt->close();
$conn->close();

echo "Cleaned up $logCleaned old access log entries.\n";

echo "All cleanup tasks completed successfully.\n";
?>
