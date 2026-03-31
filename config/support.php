<?php
/**
 * Support center helper functions
 */

require_once __DIR__ . '/database.php';

/**
 * Ensure support ticket table exists.
 * This avoids manual migration failures on deployment.
 */
function ensureSupportTables($conn) {
    $sql = "CREATE TABLE IF NOT EXISTS support_tickets (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        assigned_to INT NULL,
        issue_type VARCHAR(50) NOT NULL DEFAULT 'general',
        priority VARCHAR(20) NOT NULL DEFAULT 'medium',
        status VARCHAR(20) NOT NULL DEFAULT 'open',
        subject VARCHAR(255) NOT NULL,
        details TEXT NOT NULL,
        attachment_path VARCHAR(255) NULL,
        attachment_name VARCHAR(255) NULL,
        attachment_type VARCHAR(100) NULL,
        current_page VARCHAR(255) NULL,
        ip_address VARCHAR(45) NULL,
        user_agent TEXT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
        resolved_at DATETIME NULL,
        INDEX idx_support_user_id (user_id),
        INDEX idx_support_assigned_to (assigned_to),
        INDEX idx_support_status (status),
        INDEX idx_support_created_at (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    if ($conn->query($sql) !== true) {
        return false;
    }

    // Backward-compatible schema upgrade for existing support_tickets tables.
    $columns = [];
    $result = $conn->query("SHOW COLUMNS FROM support_tickets");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $columns[] = $row['Field'];
        }
    }

    $alterParts = [];
    if (!in_array('attachment_path', $columns, true)) {
        $alterParts[] = "ADD COLUMN attachment_path VARCHAR(255) NULL AFTER details";
    }
    if (!in_array('attachment_name', $columns, true)) {
        $alterParts[] = "ADD COLUMN attachment_name VARCHAR(255) NULL AFTER attachment_path";
    }
    if (!in_array('attachment_type', $columns, true)) {
        $alterParts[] = "ADD COLUMN attachment_type VARCHAR(100) NULL AFTER attachment_name";
    }

    if (!empty($alterParts)) {
        $alterSql = "ALTER TABLE support_tickets " . implode(', ', $alterParts);
        if ($conn->query($alterSql) !== true) {
            return false;
        }
    }

    return true;
}

function supportCanManageTickets() {
    return function_exists('canManageUsers') && canManageUsers();
}

function supportCreateTicket($conn, $userId, $subject, $details, $issueType, $priority, $currentPage, $ipAddress, $userAgent, $attachmentPath = null, $attachmentName = null, $attachmentType = null) {
    $query = "INSERT INTO support_tickets
              (user_id, subject, details, issue_type, priority, status, attachment_path, attachment_name, attachment_type, current_page, ip_address, user_agent)
              VALUES (?, ?, ?, ?, ?, 'open', ?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($query);
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param(
        'issssssssss',
        $userId,
        $subject,
        $details,
        $issueType,
        $priority,
        $attachmentPath,
        $attachmentName,
        $attachmentType,
        $currentPage,
        $ipAddress,
        $userAgent
    );

    $ok = $stmt->execute();
    $ticketId = $ok ? $conn->insert_id : 0;
    $stmt->close();

    return $ticketId;
}
