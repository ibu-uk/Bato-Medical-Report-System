<?php
/**
 * Staff chat helper functions
 */

require_once __DIR__ . '/database.php';

function ensureStaffChatTables($conn) {
    $createSql = "CREATE TABLE IF NOT EXISTS staff_chat_messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NULL,
        sender_name VARCHAR(255) NOT NULL,
        message_text TEXT NOT NULL,
        is_bot TINYINT(1) NOT NULL DEFAULT 0,
        attachment_path VARCHAR(255) NULL,
        attachment_name VARCHAR(255) NULL,
        attachment_type VARCHAR(100) NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_staff_chat_created_at (created_at),
        INDEX idx_staff_chat_user_id (user_id),
        INDEX idx_staff_chat_is_bot (is_bot)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    if ($conn->query($createSql) !== true) {
        return false;
    }

    $columns = [];
    $result = $conn->query("SHOW COLUMNS FROM staff_chat_messages");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $columns[] = $row['Field'];
        }
    }

    $alterParts = [];
    if (!in_array('is_bot', $columns, true)) {
        $alterParts[] = "ADD COLUMN is_bot TINYINT(1) NOT NULL DEFAULT 0 AFTER message_text";
    }
    if (!in_array('attachment_path', $columns, true)) {
        $alterParts[] = "ADD COLUMN attachment_path VARCHAR(255) NULL AFTER is_bot";
    }
    if (!in_array('attachment_name', $columns, true)) {
        $alterParts[] = "ADD COLUMN attachment_name VARCHAR(255) NULL AFTER attachment_path";
    }
    if (!in_array('attachment_type', $columns, true)) {
        $alterParts[] = "ADD COLUMN attachment_type VARCHAR(100) NULL AFTER attachment_name";
    }

    if (!empty($alterParts)) {
        $alterSql = "ALTER TABLE staff_chat_messages " . implode(', ', $alterParts);
        if ($conn->query($alterSql) !== true) {
            return false;
        }
    }

    return true;
}

function staffChatResolveSenderName($conn, $userId) {
    if ($userId <= 0) {
        return 'Unknown User';
    }

    $query = "SELECT full_name FROM users WHERE id = ? LIMIT 1";
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        return 'Staff';
    }

    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $name = 'Staff';

    if ($result && $row = $result->fetch_assoc()) {
        $name = trim((string)$row['full_name']) !== '' ? $row['full_name'] : 'Staff';
    }

    $stmt->close();
    return $name;
}

function staffChatInsertMessage($conn, $userId, $senderName, $messageText, $isBot = 0, $attachmentPath = null, $attachmentName = null, $attachmentType = null) {
    $query = "INSERT INTO staff_chat_messages (user_id, sender_name, message_text, is_bot, attachment_path, attachment_name, attachment_type)
              VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        return 0;
    }

    $nullableUserId = $userId > 0 ? $userId : null;
    $stmt->bind_param('ississs', $nullableUserId, $senderName, $messageText, $isBot, $attachmentPath, $attachmentName, $attachmentType);

    $ok = $stmt->execute();
    $insertId = $ok ? (int)$conn->insert_id : 0;
    $stmt->close();

    return $insertId;
}

function staffChatGenerateBotReply($conn, $rawMessage) {
    $message = trim((string)$rawMessage);
    if ($message === '') {
        return '';
    }

    $isBotCommand = false;
    $commandText = $message;

    if (stripos($message, '/bot') === 0) {
        $isBotCommand = true;
        $commandText = trim(substr($message, 4));
    } elseif (stripos($message, '@bot') === 0) {
        $isBotCommand = true;
        $commandText = trim(substr($message, 4));
    }

    if (!$isBotCommand) {
        return '';
    }

    if ($commandText === '') {
        return "How to use Clinic Assistant Bot:\n"
            . "1) Type /bot help to see full menu\n"
            . "2) Choose using number: /bot 1 to /bot 11\n"
            . "3) Or type direct command, e.g. /bot today summary\n"
            . "\nTip: for patient search use /bot find patient F-1023";
    }

    $lowerMessage = strtolower($commandText);

    if (preg_match('/^\d+$/', $lowerMessage)) {
        switch ((int)$lowerMessage) {
            case 1:
                $lowerMessage = 'today reports';
                break;
            case 2:
                $lowerMessage = 'find patient';
                break;
            case 3:
                $lowerMessage = 'how to create report';
                break;
            case 4:
                $lowerMessage = 'how to create prescription';
                break;
            case 5:
                $lowerMessage = 'how to create nurse treatment';
                break;
            case 6:
                $lowerMessage = 'how to add patient';
                break;
            case 7:
                $lowerMessage = 'today reports total';
                break;
            case 8:
                $lowerMessage = 'today patients total';
                break;
            case 9:
                $lowerMessage = 'today nurse treatments total';
                break;
            case 10:
                $lowerMessage = 'today prescriptions total';
                break;
            case 11:
                $lowerMessage = 'today summary';
                break;
            default:
                return "Invalid option number.\nUse /bot help to see options 1-11.";
        }

        $commandText = $lowerMessage;
    }

    if ($lowerMessage === 'find patient') {
        return "To find a patient, use:\n"
            . "/bot find patient <file_number>\n"
            . "Example: /bot find patient F-1023";
    }

    if (
        strpos($lowerMessage, 'today reports') !== false ||
        strpos($lowerMessage, 'todays reports') !== false ||
        strpos($lowerMessage, 'show today reports') !== false
    ) {
        if (strpos($lowerMessage, 'total') !== false) {
            // Let the dedicated totals handler answer this variant.
        } else {
        $count = 0;
        $countResult = $conn->query("SELECT COUNT(*) AS total FROM reports WHERE report_date = CURDATE()");
        if ($countResult && $row = $countResult->fetch_assoc()) {
            $count = (int)$row['total'];
        }

        $reply = "Today's reports: " . $count;

        if ($count > 0) {
            $detailsQuery = "SELECT r.report_date, p.name AS patient_name, d.name AS doctor_name
                            FROM reports r
                            LEFT JOIN patients p ON p.id = r.patient_id
                            LEFT JOIN doctors d ON d.id = r.doctor_id
                            WHERE r.report_date = CURDATE()
                            ORDER BY r.id DESC
                            LIMIT 5";
            $detailsResult = $conn->query($detailsQuery);
            $lines = [];

            if ($detailsResult) {
                while ($row = $detailsResult->fetch_assoc()) {
                    $patient = trim((string)$row['patient_name']) !== '' ? $row['patient_name'] : 'Unknown patient';
                    $doctor = trim((string)$row['doctor_name']) !== '' ? $row['doctor_name'] : 'Unknown doctor';
                    $lines[] = '- ' . $patient . ' | Dr: ' . $doctor;
                }
            }

            if (!empty($lines)) {
                $reply .= "\nLatest reports today:\n" . implode("\n", $lines);
            }
        }

        return $reply;
        }
    }

    if (preg_match('/(?:find\s+patient\s+by\s+file\s*number|find\s+patient|patient\s+file)\s*[:#-]?\s*([a-z0-9\-]+)/i', $commandText, $matches)) {
        $fileNumber = trim($matches[1]);
        if ($fileNumber === '') {
            return 'Please provide a file number. Example: "Find patient F-1023"';
        }

        $query = "SELECT name, file_number, mobile FROM patients WHERE file_number = ? LIMIT 1";
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            return 'I could not search right now. Please try again.';
        }

        $stmt->bind_param('s', $fileNumber);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $row = $result->fetch_assoc()) {
            $mobile = trim((string)$row['mobile']) !== '' ? $row['mobile'] : 'N/A';
            $reply = "Patient found:\n";
            $reply .= '- Name: ' . $row['name'] . "\n";
            $reply .= '- File No: ' . $row['file_number'] . "\n";
            $reply .= '- Mobile: ' . $mobile;
            $stmt->close();
            return $reply;
        }

        $stmt->close();
        return 'No patient found with file number: ' . $fileNumber;
    }

    if (
        strpos($lowerMessage, 'create report') !== false ||
        strpos($lowerMessage, 'how to create report') !== false ||
        strpos($lowerMessage, '/help report') !== false
    ) {
        return "To create a report:\n"
            . "1) Open Create Report from sidebar\n"
            . "2) Search/select patient\n"
            . "3) Fill Report Date, Appointment Date, and Appointment Time\n"
            . "4) Enter test results and conclusion\n"
            . "5) Click Generate Report";
    }

    if (
        strpos($lowerMessage, 'create prescription') !== false ||
        strpos($lowerMessage, 'how to create prescription') !== false ||
        strpos($lowerMessage, 'how to add prescription') !== false
    ) {
        return "To create a prescription:\n"
            . "1) Open Prescriptions from sidebar\n"
            . "2) Click Add/New Prescription\n"
            . "3) Select patient and doctor\n"
            . "4) Add medicine details and instructions\n"
            . "5) Save prescription";
    }

    if (
        strpos($lowerMessage, 'create nurse treatment') !== false ||
        strpos($lowerMessage, 'how to create nurse treatment') !== false ||
        strpos($lowerMessage, 'add nurse treatment') !== false
    ) {
        return "To create a nurse treatment:\n"
            . "1) Open Nurse Treatments from sidebar\n"
            . "2) Click New Treatment Record\n"
            . "3) Select patient and fill treatment details\n"
            . "4) Nurse Name is auto-filled from logged-in staff\n"
            . "5) Save treatment";
    }

    if (
        strpos($lowerMessage, 'add patient') !== false ||
        strpos($lowerMessage, 'how to add patient') !== false ||
        strpos($lowerMessage, 'create patient') !== false
    ) {
        return "To add a patient:\n"
            . "1) Open Patients > Add Patient\n"
            . "2) Fill patient profile details\n"
            . "3) Verify file number and contact\n"
            . "4) Click Save/Add Patient";
    }

    if (
        strpos($lowerMessage, 'today reports total') !== false ||
        strpos($lowerMessage, 'today report total') !== false ||
        strpos($lowerMessage, 'today reports') !== false
    ) {
        $count = 0;
        $result = $conn->query("SELECT COUNT(*) AS total FROM reports WHERE report_date = CURDATE()");
        if ($result && $row = $result->fetch_assoc()) {
            $count = (int)$row['total'];
        }
        return "Today's reports total: " . $count;
    }

    if (
        strpos($lowerMessage, 'today patients total') !== false ||
        strpos($lowerMessage, 'today patients') !== false ||
        strpos($lowerMessage, 'today patient total') !== false
    ) {
        $count = 0;
        $result = $conn->query("SELECT COUNT(*) AS total FROM patients WHERE DATE(created_at) = CURDATE()");
        if ($result && $row = $result->fetch_assoc()) {
            $count = (int)$row['total'];
        }
        return "Today's patients total: " . $count;
    }

    if (
        strpos($lowerMessage, 'today nurse treatment total') !== false ||
        strpos($lowerMessage, 'today nurse treatments total') !== false ||
        strpos($lowerMessage, 'today nurse treatments') !== false ||
        strpos($lowerMessage, 'todays nurse treatments') !== false
    ) {
        $count = 0;
        $result = $conn->query("SELECT COUNT(*) AS total FROM nurse_treatments WHERE treatment_date = CURDATE()");
        if ($result && $row = $result->fetch_assoc()) {
            $count = (int)$row['total'];
        }
        return "Today's nurse treatments total: " . $count;
    }

    if (
        strpos($lowerMessage, 'today prescription total') !== false ||
        strpos($lowerMessage, 'today prescriptions total') !== false ||
        strpos($lowerMessage, 'today prescriptions') !== false ||
        strpos($lowerMessage, 'todays prescriptions') !== false
    ) {
        $count = 0;
        $result = $conn->query("SELECT COUNT(*) AS total FROM prescriptions WHERE prescription_date = CURDATE()");
        if ($result && $row = $result->fetch_assoc()) {
            $count = (int)$row['total'];
        }
        return "Today's prescriptions total: " . $count;
    }

    if (strpos($lowerMessage, 'today summary') !== false || strpos($lowerMessage, 'today totals') !== false) {
        $reports = 0;
        $patients = 0;
        $treatments = 0;
        $prescriptions = 0;

        $result = $conn->query("SELECT COUNT(*) AS total FROM reports WHERE report_date = CURDATE()");
        if ($result && $row = $result->fetch_assoc()) {
            $reports = (int)$row['total'];
        }

        $result = $conn->query("SELECT COUNT(*) AS total FROM patients WHERE DATE(created_at) = CURDATE()");
        if ($result && $row = $result->fetch_assoc()) {
            $patients = (int)$row['total'];
        }

        $result = $conn->query("SELECT COUNT(*) AS total FROM nurse_treatments WHERE treatment_date = CURDATE()");
        if ($result && $row = $result->fetch_assoc()) {
            $treatments = (int)$row['total'];
        }

        $result = $conn->query("SELECT COUNT(*) AS total FROM prescriptions WHERE prescription_date = CURDATE()");
        if ($result && $row = $result->fetch_assoc()) {
            $prescriptions = (int)$row['total'];
        }

        return "Today's summary:\n"
            . "- Reports: " . $reports . "\n"
            . "- New Patients: " . $patients . "\n"
            . "- Nurse Treatments: " . $treatments . "\n"
            . "- Prescriptions: " . $prescriptions;
    }

    if (strpos($lowerMessage, 'help') !== false || strpos($lowerMessage, '/help') !== false || strpos($lowerMessage, 'commands') !== false) {
        return "Clinic Assistant - Staff Guide\n"
            . "Step 1: Choose by number or type command\n"
            . "Step 2: Send command as /bot <option>\n"
            . "\nMenu:\n"
            . "1) Today reports\n"
            . "2) Find patient <file_number>\n"
            . "3) How to create report\n"
            . "4) How to create prescription\n"
            . "5) How to create nurse treatment\n"
            . "6) How to add patient\n"
            . "7) Today reports total\n"
            . "8) Today patients total\n"
            . "9) Today nurse treatments total\n"
            . "10) Today prescriptions total\n"
            . "11) Today summary\n"
            . "\nUsage:\n"
            . "- /bot 1\n"
            . "- /bot 10\n"
            . "- /bot find patient F-1023\n"
            . "- /bot how to create prescription";
    }

    return "I did not understand that command.\n"
        . "Use /bot help for the full staff guide and numbered options.\n"
        . "Examples:\n"
        . "- /bot 1\n"
        . "- /bot today summary\n"
        . "- /bot find patient F-1023";
}
