<?php
/**
 * Patient document management helpers.
 */

require_once __DIR__ . '/database.php';

function patientDocumentDefaultCategories() {
    return [
        'medical_record' => 'Medical Record',
        'patient_info' => 'Patient Info',
        'signed_document' => 'Signed Document',
        'treatment_contract' => 'Treatment Contract',
        'lab_result' => 'Lab Result',
        'insurance' => 'Insurance',
        'other' => 'Other'
    ];
}

function ensurePatientDocumentTables($conn) {
    $createSql = "CREATE TABLE IF NOT EXISTS patient_documents (
        id INT AUTO_INCREMENT PRIMARY KEY,
        patient_id INT NOT NULL,
        uploaded_by INT NOT NULL DEFAULT 0,
        document_title VARCHAR(255) NOT NULL,
        document_category VARCHAR(50) NOT NULL DEFAULT 'other',
        notes TEXT NULL,
        expiry_date DATE NULL,
        reminder_days_before INT NOT NULL DEFAULT 0,
        file_path VARCHAR(255) NOT NULL,
        file_name VARCHAR(255) NOT NULL,
        file_mime VARCHAR(100) NOT NULL,
        file_size INT NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_patient_documents_patient_id (patient_id),
        INDEX idx_patient_documents_category (document_category),
        INDEX idx_patient_documents_created_at (created_at),
        INDEX idx_patient_documents_expiry_date (expiry_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    if ($conn->query($createSql) !== true) {
        return false;
    }

    $columns = [];
    $result = $conn->query("SHOW COLUMNS FROM patient_documents");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $columns[] = $row['Field'];
        }
    }

    $alterParts = [];
    if (!in_array('expiry_date', $columns, true)) {
        $alterParts[] = "ADD COLUMN expiry_date DATE NULL AFTER notes";
    }
    if (!in_array('reminder_days_before', $columns, true)) {
        $alterParts[] = "ADD COLUMN reminder_days_before INT NOT NULL DEFAULT 0 AFTER expiry_date";
    }

    if (!empty($alterParts)) {
        $alterSql = "ALTER TABLE patient_documents " . implode(', ', $alterParts);
        if ($conn->query($alterSql) !== true) {
            return false;
        }
    }

    $indexes = [];
    $indexResult = $conn->query("SHOW INDEX FROM patient_documents");
    if ($indexResult) {
        while ($row = $indexResult->fetch_assoc()) {
            $indexes[] = $row['Key_name'];
        }
    }

    if (!in_array('idx_patient_documents_expiry_date', $indexes, true)) {
        if ($conn->query("CREATE INDEX idx_patient_documents_expiry_date ON patient_documents (expiry_date)") !== true) {
            return false;
        }
    }

    if (!ensurePatientDocumentCategoryTable($conn)) {
        return false;
    }

    return true;
}

function ensurePatientDocumentCategoryTable($conn) {
    $createSql = "CREATE TABLE IF NOT EXISTS document_categories (
        id INT AUTO_INCREMENT PRIMARY KEY,
        category_key VARCHAR(50) NOT NULL UNIQUE,
        category_label VARCHAR(100) NOT NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        sort_order INT NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_document_categories_active_order (is_active, sort_order)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    if ($conn->query($createSql) !== true) {
        return false;
    }

    $countResult = $conn->query("SELECT COUNT(*) AS total FROM document_categories");
    $total = 0;
    if ($countResult && ($row = $countResult->fetch_assoc())) {
        $total = (int)($row['total'] ?? 0);
    }

    if ($total === 0) {
        $defaults = patientDocumentDefaultCategories();
        $insertSql = "INSERT INTO document_categories (category_key, category_label, is_active, sort_order) VALUES (?, ?, 1, ?)";
        $insertStmt = $conn->prepare($insertSql);
        if (!$insertStmt) {
            return false;
        }

        $sortOrder = 10;
        foreach ($defaults as $categoryKey => $categoryLabel) {
            $insertStmt->bind_param('ssi', $categoryKey, $categoryLabel, $sortOrder);
            if (!$insertStmt->execute()) {
                $insertStmt->close();
                return false;
            }
            $sortOrder += 10;
        }
        $insertStmt->close();
    }

    return true;
}

function patientDocumentCategories($conn = null, $activeOnly = true) {
    $localConn = null;
    if (!$conn) {
        $localConn = getDbConnection();
        $conn = $localConn;
    }

    if (!$conn || !ensurePatientDocumentCategoryTable($conn)) {
        if ($localConn) {
            $localConn->close();
        }
        return patientDocumentDefaultCategories();
    }

    $query = "SELECT category_key, category_label
              FROM document_categories";
    if ($activeOnly) {
        $query .= " WHERE is_active = 1";
    }
    $query .= " ORDER BY sort_order ASC, category_label ASC";

    $result = $conn->query($query);
    $categories = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $categories[(string)$row['category_key']] = (string)$row['category_label'];
        }
    }

    if ($localConn) {
        $localConn->close();
    }

    if (empty($categories)) {
        return patientDocumentDefaultCategories();
    }

    return $categories;
}

function patientDocumentAllowedMimes() {
    return [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
        'image/tiff' => 'tiff'
    ];
}

function patientDocumentIsImageMime($mime) {
    return strpos((string)$mime, 'image/') === 0;
}
