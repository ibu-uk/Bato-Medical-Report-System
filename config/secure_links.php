<?php
/**
 * Secure Patient Report Link System
 * Generates cryptographically secure tokens for patient report access
 */

require_once 'database.php';

/**
 * Generate a secure 64-character token
 * Uses cryptographically secure random_bytes()
 * 
 * @return string 64-character hexadecimal token
 */
function generateSecureToken() {
    return bin2hex(random_bytes(32)); // 32 bytes = 64 hex characters
}

/**
 * Create a secure report link
 * 
 * @param int $patientId Patient ID (internal reference only)
 * @param string $reportFile Path to the PDF report file
 * @param int $hoursValid Number of hours until link expires (default: 48)
 * @return string|false Generated token or false on failure
 */
function createSecureReportLink($patientId, $reportFile, $hoursValid = 48) {
    try {
        // Use direct connection like setup script
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        
        if ($conn->connect_error) {
            error_log("Secure Links: Database connection failed: " . $conn->connect_error);
            return false;
        }
        
        // Generate unique token
        $token = generateSecureToken();
        
        // Calculate expiry date
        $expiryDate = date("Y-m-d H:i:s", strtotime("+$hoursValid hours"));
        
        // Insert into database
        $query = "INSERT INTO report_links (patient_id, report_file, token, expiry_date) 
                  VALUES (?, ?, ?, ?)";
        
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            error_log("Secure Links: Prepare failed: " . $conn->error);
            $conn->close();
            return false;
        }
        
        $stmt->bind_param("isss", $patientId, $reportFile, $token, $expiryDate);
        
        if ($stmt->execute()) {
            $stmt->close();
            $conn->close();
            return $token;
        } else {
            error_log("Secure Links: Execute failed: " . $stmt->error);
            $stmt->close();
            $conn->close();
            return false;
        }
    } catch (Exception $e) {
        error_log("Secure Links: Exception: " . $e->getMessage());
        return false;
    }
}

/**
 * Validate a secure report token
 * 
 * @param string $token The token to validate
 * @return array|false Token data or false if invalid
 */
function validateReportToken($token) {
    try {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        
        if ($conn->connect_error) {
            error_log("Secure Links: Database connection failed in validation");
            return false;
        }
        
        $query = "SELECT * FROM report_links 
                  WHERE token = ? AND expiry_date > NOW() 
                  LIMIT 1";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $tokenData = $result->fetch_assoc();
            $stmt->close();
            $conn->close();
            return $tokenData;
        } else {
            $stmt->close();
            $conn->close();
            return false;
        }
    } catch (Exception $e) {
        error_log("Secure Links: Validation exception: " . $e->getMessage());
        return false;
    }
}

/**
 * Mark a token as used (optional one-time access)
 * 
 * @param string $token The token to mark as used
 * @return bool Success status
 */
function markTokenAsUsed($token) {
    try {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        
        if ($conn->connect_error) {
            error_log("Secure Links: Database connection failed in markAsUsed");
            return false;
        }
        
        $query = "UPDATE report_links SET is_used = 1 WHERE token = ?";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $token);
        $success = $stmt->execute();
        
        $stmt->close();
        $conn->close();
        
        return $success;
    } catch (Exception $e) {
        error_log("Secure Links: MarkAsUsed exception: " . $e->getMessage());
        return false;
    }
}

/**
 * Get full secure report URL
 * 
 * @param string $token The generated token
 * @return string Complete URL for the report
 */
function getSecureReportUrl($token) {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $path = dirname($_SERVER['PHP_SELF']);
    
    return $protocol . '://' . $host . $path . '/report.php?token=' . $token;
}

/**
 * Clean up expired tokens
 * Removes tokens that have expired
 * 
 * @return int Number of tokens cleaned up
 */
function cleanupExpiredTokens() {
    try {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        
        if ($conn->connect_error) {
            error_log("Secure Links: Database connection failed in cleanup");
            return 0;
        }
        
        $query = "DELETE FROM report_links WHERE expiry_date <= NOW()";
        
        $stmt = $conn->prepare($query);
        $stmt->execute();
        $affectedRows = $stmt->affected_rows;
        
        $stmt->close();
        $conn->close();
        
        return $affectedRows;
    } catch (Exception $e) {
        error_log("Secure Links: Cleanup exception: " . $e->getMessage());
        return 0;
    }
}

/**
 * Get all active report links for a patient
 * 
 * @param int $patientId Patient ID
 * @return array Array of active links
 */
function getPatientReportLinks($patientId) {
    try {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        
        if ($conn->connect_error) {
            error_log("Secure Links: Database connection failed in getPatientLinks");
            return [];
        }
        
        $query = "SELECT * FROM report_links 
                  WHERE patient_id = ? AND expiry_date > NOW() 
                  ORDER BY created_at DESC";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $patientId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $links = array();
        while ($row = $result->fetch_assoc()) {
            $links[] = $row;
        }
        
        $stmt->close();
        $conn->close();
        
        return $links;
    } catch (Exception $e) {
        error_log("Secure Links: GetPatientLinks exception: " . $e->getMessage());
        return [];
    }
}

/**
 * Revoke a specific report link
 * 
 * @param string $token Token to revoke
 * @return bool Success status
 */
function revokeReportLink($token) {
    try {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        
        if ($conn->connect_error) {
            error_log("Secure Links: Database connection failed in revoke");
            return false;
        }
        
        $query = "DELETE FROM report_links WHERE token = ?";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $token);
        $success = $stmt->execute();
        
        $stmt->close();
        $conn->close();
        
        return $success;
    } catch (Exception $e) {
        error_log("Secure Links: Revoke exception: " . $e->getMessage());
        return false;
    }
}
?>
