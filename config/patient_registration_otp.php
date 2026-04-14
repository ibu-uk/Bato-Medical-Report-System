<?php
/**
 * Email OTP helpers for patient portal registration.
 */

function ensurePatientRegistrationOtpTable($conn) {
    $sql = "CREATE TABLE IF NOT EXISTS patient_registration_otp (
        id INT AUTO_INCREMENT PRIMARY KEY,
        patient_id INT NOT NULL,
        email VARCHAR(255) NOT NULL,
        purpose VARCHAR(30) NOT NULL DEFAULT 'registration',
        otp_hash VARCHAR(255) NOT NULL,
        expires_at DATETIME NOT NULL,
        attempts INT NOT NULL DEFAULT 0,
        max_attempts INT NOT NULL DEFAULT 5,
        is_verified TINYINT(1) NOT NULL DEFAULT 0,
        verified_at DATETIME NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_patient_registration_otp_patient_purpose (patient_id, purpose),
        INDEX idx_patient_registration_otp_expires (expires_at),
        INDEX idx_patient_registration_otp_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    return $conn->query($sql) === true;
}

function generatePatientRegistrationOtpCode() {
    return (string)random_int(100000, 999999);
}

function sendPatientRegistrationOtpEmail($toEmail, $patientName, $otpCode) {
    $toEmail = trim((string)$toEmail);
    if ($toEmail === '') {
        return false;
    }

    $subject = 'BATO CLINIC - Your Registration OTP';
    $safeName = trim((string)$patientName) !== '' ? (string)$patientName : 'Patient';
    $message = "Hello {$safeName},\n\n"
        . "Your One-Time Password (OTP) for patient portal registration is: {$otpCode}\n\n"
        . "This code expires in 10 minutes.\n"
        . "If you did not request this, please contact BATO CLINIC immediately.\n\n"
        . "BATO CLINIC";

    $headers = [];
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'Content-Type: text/plain; charset=UTF-8';
    $headers[] = 'From: BATO CLINIC <no-reply@bato-clinic.local>';

    return @mail($toEmail, $subject, $message, implode("\r\n", $headers));
}

function createPatientRegistrationOtp($conn, $patientId, $email, $purpose, &$errorMessage) {
    $patientId = (int)$patientId;
    $email = trim((string)$email);
    $purpose = trim((string)$purpose);

    if ($patientId <= 0 || $email === '' || $purpose === '') {
        $errorMessage = 'Invalid OTP request data.';
        return false;
    }

    if (!ensurePatientRegistrationOtpTable($conn)) {
        $errorMessage = 'OTP table initialization failed.';
        return false;
    }

    // Basic rate limit: max 3 sends per 10 minutes per patient/purpose.
    $rateStmt = $conn->prepare("SELECT COUNT(*) AS total
                                FROM patient_registration_otp
                                WHERE patient_id = ?
                                  AND purpose = ?
                                  AND created_at >= (NOW() - INTERVAL 10 MINUTE)");
    if (!$rateStmt) {
        $errorMessage = 'Failed to validate OTP rate limit.';
        return false;
    }

    $rateStmt->bind_param('is', $patientId, $purpose);
    $rateStmt->execute();
    $rateResult = $rateStmt->get_result();
    $rateRow = $rateResult ? $rateResult->fetch_assoc() : null;
    $rateStmt->close();

    if (((int)($rateRow['total'] ?? 0)) >= 3) {
        $errorMessage = 'Too many OTP requests. Please wait 10 minutes and try again.';
        return false;
    }

    $otpCode = generatePatientRegistrationOtpCode();
    $otpHash = password_hash($otpCode, PASSWORD_DEFAULT);
    $insertStmt = $conn->prepare("INSERT INTO patient_registration_otp
        (patient_id, email, purpose, otp_hash, expires_at, attempts, max_attempts, is_verified)
        VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE), 0, 5, 0)");

    if (!$insertStmt) {
        $errorMessage = 'Failed to create OTP request.';
        return false;
    }

    $insertStmt->bind_param('isss', $patientId, $email, $purpose, $otpHash);
    $saved = $insertStmt->execute();
    $otpId = (int)$conn->insert_id;
    $insertStmt->close();

    if (!$saved) {
        $errorMessage = 'Failed to store OTP request.';
        return false;
    }

    return [
        'otp_id' => $otpId,
        'otp_code' => $otpCode
    ];
}

function verifyPatientRegistrationOtp($conn, $patientId, $otpCode, $purpose, &$errorMessage) {
    $patientId = (int)$patientId;
    $otpCode = trim((string)$otpCode);
    $purpose = trim((string)$purpose);

    if ($patientId <= 0 || $otpCode === '' || $purpose === '') {
        $errorMessage = 'Invalid OTP verification request.';
        return false;
    }

    if (!ensurePatientRegistrationOtpTable($conn)) {
        $errorMessage = 'OTP table initialization failed.';
        return false;
    }

    $stmt = $conn->prepare("SELECT id, otp_hash, attempts, max_attempts, expires_at
                            FROM patient_registration_otp
                            WHERE patient_id = ?
                              AND purpose = ?
                              AND is_verified = 0
                            ORDER BY id DESC
                            LIMIT 1");
    if (!$stmt) {
        $errorMessage = 'Failed to load OTP request.';
        return false;
    }

    $stmt->bind_param('is', $patientId, $purpose);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    if (!$row) {
        $errorMessage = 'No active OTP found. Please request a new OTP.';
        return false;
    }

    $otpId = (int)$row['id'];
    $attempts = (int)($row['attempts'] ?? 0);
    $maxAttempts = (int)($row['max_attempts'] ?? 5);
    $expiresAt = (string)($row['expires_at'] ?? '');

    if ($attempts >= $maxAttempts) {
        $errorMessage = 'OTP attempts exceeded. Please request a new OTP.';
        return false;
    }

    if ($expiresAt === '' || strtotime($expiresAt) < time()) {
        $errorMessage = 'OTP expired. Please request a new OTP.';
        return false;
    }

    $hash = (string)($row['otp_hash'] ?? '');
    $valid = $hash !== '' && password_verify($otpCode, $hash);

    if (!$valid) {
        $updateAttemptStmt = $conn->prepare("UPDATE patient_registration_otp
                                             SET attempts = attempts + 1
                                             WHERE id = ?");
        if ($updateAttemptStmt) {
            $updateAttemptStmt->bind_param('i', $otpId);
            $updateAttemptStmt->execute();
            $updateAttemptStmt->close();
        }
        $errorMessage = 'Invalid OTP code.';
        return false;
    }

    $verifyStmt = $conn->prepare("UPDATE patient_registration_otp
                                  SET is_verified = 1, verified_at = NOW()
                                  WHERE id = ?");
    if (!$verifyStmt) {
        $errorMessage = 'Failed to finalize OTP verification.';
        return false;
    }

    $verifyStmt->bind_param('i', $otpId);
    $ok = $verifyStmt->execute();
    $verifyStmt->close();

    if (!$ok) {
        $errorMessage = 'Failed to finalize OTP verification.';
        return false;
    }

    return true;
}
