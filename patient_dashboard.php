<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

// Include required files
require_once 'config/database.php';
require_once 'config/auth.php';
require_once 'config/secure_links.php';
require_once 'config/patient_documents.php';

// Access modes:
// 1) Logged-in patient session (new patient portal)
// 2) Secure token link (backward-compatible)
$token = '';
$accessMode = 'session';
$patientId = null;

if (isPatientLoggedIn()) {
    $patientId = getCurrentPatientId();
} else {
    if (isset($_GET['token']) && !empty(trim($_GET['token']))) {
        $token = trim($_GET['token']);
    } elseif (isset($_GET['t']) && !empty(trim($_GET['t']))) {
        $token = decodeUrlToken(trim($_GET['t']));
    }

    if (empty($token)) {
        header('Location: patient_login.php');
        exit;
    }

    $patientData = validateReportToken($token);
    if (!$patientData) {
        die('Error: Invalid or expired token. Please request a new link.');
    }

    $patientId = (int)$patientData['patient_id'];
    $accessMode = 'token';
}

// Get database connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get patient information
$stmt = $conn->prepare("SELECT * FROM patients WHERE id = ?");
if ($stmt === false) {
    die("Error preparing patient query: " . $conn->error);
}

$stmt->bind_param('i', $patientId);
if (!$stmt->execute()) {
    die("Error executing patient query: " . $stmt->error);
}

$result = $stmt->get_result();
if ($result->num_rows === 0) {
    die('Error: Patient not found.');
}
$patient = $result->fetch_assoc();
$hasPortalAccount = !empty($patient['portal_username']) && !empty($patient['portal_password_hash']);
$stmt->close();

// Dashboard filters and pagination
$allowedRecordTypes = ['all', 'documents', 'reports', 'prescriptions', 'treatments'];
$filterType = isset($_GET['record_type']) ? strtolower(trim((string)$_GET['record_type'])) : 'all';
if (!in_array($filterType, $allowedRecordTypes, true)) {
    $filterType = 'all';
}

$dateFrom = isset($_GET['date_from']) ? trim((string)$_GET['date_from']) : '';
$dateTo = isset($_GET['date_to']) ? trim((string)$_GET['date_to']) : '';
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
    $dateFrom = '';
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
    $dateTo = '';
}

$searchQuery = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
if (strlen($searchQuery) > 120) {
    $searchQuery = substr($searchQuery, 0, 120);
}

$perPageOptions = [5, 10, 20];
$perPage = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 10;
if (!in_array($perPage, $perPageOptions, true)) {
    $perPage = 10;
}

$pageDocuments = max(1, isset($_GET['page_documents']) ? (int)$_GET['page_documents'] : 1);
$pageReports = max(1, isset($_GET['page_reports']) ? (int)$_GET['page_reports'] : 1);
$pagePrescriptions = max(1, isset($_GET['page_prescriptions']) ? (int)$_GET['page_prescriptions'] : 1);
$pageTreatments = max(1, isset($_GET['page_treatments']) ? (int)$_GET['page_treatments'] : 1);

$showDocuments = ($filterType === 'all' || $filterType === 'documents');
$showReports = ($filterType === 'all' || $filterType === 'reports');
$showPrescriptions = ($filterType === 'all' || $filterType === 'prescriptions');
$showTreatments = ($filterType === 'all' || $filterType === 'treatments');

$countSingle = static function(mysqli $conn, string $sql, string $types, array $params = []): int {
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        return 0;
    }

    if ($types !== '' && !empty($params)) {
        $bindParams = [$types];
        foreach ($params as $k => $value) {
            $bindParams[] = &$params[$k];
        }
        call_user_func_array([$stmt, 'bind_param'], $bindParams);
    }

    if (!$stmt->execute()) {
        $stmt->close();
        return 0;
    }

    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();
    return $row ? (int)$row['total'] : 0;
};

$fetchRows = static function(mysqli $conn, string $sql, string $types, array $params = []): array {
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        return [];
    }

    if ($types !== '' && !empty($params)) {
        $bindParams = [$types];
        foreach ($params as $k => $value) {
            $bindParams[] = &$params[$k];
        }
        call_user_func_array([$stmt, 'bind_param'], $bindParams);
    }

    if (!$stmt->execute()) {
        $stmt->close();
        return [];
    }

    $result = $stmt->get_result();
    $rows = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
    }
    $stmt->close();
    return $rows;
};

// Unfiltered totals for dashboard overview cards
$totalReports = $countSingle(
    $conn,
    "SELECT COUNT(*) AS total FROM reports WHERE patient_id = ?",
    'i',
    [$patientId]
);
$totalPrescriptions = $countSingle(
    $conn,
    "SELECT COUNT(*) AS total FROM prescriptions WHERE patient_id = ?",
    'i',
    [$patientId]
);
$totalTreatments = $countSingle(
    $conn,
    "SELECT COUNT(*) AS total FROM nurse_treatments WHERE patient_id = ?",
    'i',
    [$patientId]
);

$totalDocuments = 0;
$documentsTableReady = false;
try {
    $documentsTableReady = ensurePatientDocumentTables($conn);
    if ($documentsTableReady) {
        $totalDocuments = $countSingle(
            $conn,
            "SELECT COUNT(*) AS total FROM patient_documents WHERE patient_id = ?",
            'i',
            [$patientId]
        );
    }
} catch (Exception $e) {
    $documentsTableReady = false;
    $totalDocuments = 0;
    error_log("Patient documents total query error: " . $e->getMessage());
}

$totalRecords = $totalDocuments + $totalReports + $totalPrescriptions + $totalTreatments;

// Filtered totals and paginated data
$filteredDocumentsTotal = 0;
$filteredReportsTotal = 0;
$filteredPrescriptionsTotal = 0;
$filteredTreatmentsTotal = 0;

$documents = [];
$reports = [];
$prescriptions = [];
$treatments = [];

if ($showReports) {
    $filteredReportsTotal = $countSingle(
        $conn,
        "SELECT COUNT(*) AS total
         FROM reports
         WHERE patient_id = ?
           AND (? = '' OR report_date >= ?)
           AND (? = '' OR report_date <= ?)
           AND (? = '' OR report_title LIKE CONCAT('%', ?, '%'))",
        'issssss',
        [$patientId, $dateFrom, $dateFrom, $dateTo, $dateTo, $searchQuery, $searchQuery]
    );

    $totalPagesReports = max(1, (int)ceil($filteredReportsTotal / $perPage));
    $pageReports = min($pageReports, $totalPagesReports);
    $offsetReports = ($pageReports - 1) * $perPage;

    $reports = $fetchRows(
        $conn,
        "SELECT *
         FROM reports
         WHERE patient_id = ?
           AND (? = '' OR report_date >= ?)
           AND (? = '' OR report_date <= ?)
           AND (? = '' OR report_title LIKE CONCAT('%', ?, '%'))
         ORDER BY report_date DESC
         LIMIT ? OFFSET ?",
        'issssssii',
        [$patientId, $dateFrom, $dateFrom, $dateTo, $dateTo, $searchQuery, $searchQuery, $perPage, $offsetReports]
    );
} else {
    $totalPagesReports = 1;
}

if ($showPrescriptions) {
    $filteredPrescriptionsTotal = $countSingle(
        $conn,
        "SELECT COUNT(*) AS total
         FROM prescriptions
         WHERE patient_id = ?
           AND (? = '' OR prescription_date >= ?)
           AND (? = '' OR prescription_date <= ?)
           AND (? = '' OR prescription_title LIKE CONCAT('%', ?, '%'))",
        'issssss',
        [$patientId, $dateFrom, $dateFrom, $dateTo, $dateTo, $searchQuery, $searchQuery]
    );

    $totalPagesPrescriptions = max(1, (int)ceil($filteredPrescriptionsTotal / $perPage));
    $pagePrescriptions = min($pagePrescriptions, $totalPagesPrescriptions);
    $offsetPrescriptions = ($pagePrescriptions - 1) * $perPage;

    $prescriptions = $fetchRows(
        $conn,
        "SELECT *
         FROM prescriptions
         WHERE patient_id = ?
           AND (? = '' OR prescription_date >= ?)
           AND (? = '' OR prescription_date <= ?)
           AND (? = '' OR prescription_title LIKE CONCAT('%', ?, '%'))
         ORDER BY prescription_date DESC
         LIMIT ? OFFSET ?",
        'issssssii',
        [$patientId, $dateFrom, $dateFrom, $dateTo, $dateTo, $searchQuery, $searchQuery, $perPage, $offsetPrescriptions]
    );
} else {
    $totalPagesPrescriptions = 1;
}

if ($showTreatments) {
    try {
        $filteredTreatmentsTotal = $countSingle(
            $conn,
            "SELECT COUNT(*) AS total
             FROM nurse_treatments
             WHERE patient_id = ?
               AND (? = '' OR treatment_date >= ?)
               AND (? = '' OR treatment_date <= ?)
               AND (? = '' OR treatment_type LIKE CONCAT('%', ?, '%') OR nurse_name LIKE CONCAT('%', ?, '%'))",
            'isssssss',
            [$patientId, $dateFrom, $dateFrom, $dateTo, $dateTo, $searchQuery, $searchQuery, $searchQuery]
        );

        $totalPagesTreatments = max(1, (int)ceil($filteredTreatmentsTotal / $perPage));
        $pageTreatments = min($pageTreatments, $totalPagesTreatments);
        $offsetTreatments = ($pageTreatments - 1) * $perPage;

        $treatments = $fetchRows(
            $conn,
            "SELECT *
             FROM nurse_treatments
             WHERE patient_id = ?
               AND (? = '' OR treatment_date >= ?)
               AND (? = '' OR treatment_date <= ?)
               AND (? = '' OR treatment_type LIKE CONCAT('%', ?, '%') OR nurse_name LIKE CONCAT('%', ?, '%'))
             ORDER BY treatment_date DESC
             LIMIT ? OFFSET ?",
            'isssssssii',
            [$patientId, $dateFrom, $dateFrom, $dateTo, $dateTo, $searchQuery, $searchQuery, $searchQuery, $perPage, $offsetTreatments]
        );
    } catch (Exception $e) {
        $treatments = [];
        $filteredTreatmentsTotal = 0;
        $totalPagesTreatments = 1;
        error_log("Nurse treatments filtered query error: " . $e->getMessage());
    }
} else {
    $totalPagesTreatments = 1;
}

if ($showDocuments && $documentsTableReady) {
    try {
        $filteredDocumentsTotal = $countSingle(
            $conn,
            "SELECT COUNT(*) AS total
             FROM patient_documents
             WHERE patient_id = ?
               AND (? = '' OR DATE(created_at) >= ?)
               AND (? = '' OR DATE(created_at) <= ?)
               AND (? = '' OR document_title LIKE CONCAT('%', ?, '%') OR document_category LIKE CONCAT('%', ?, '%'))",
            'isssssss',
            [$patientId, $dateFrom, $dateFrom, $dateTo, $dateTo, $searchQuery, $searchQuery, $searchQuery]
        );

        $totalPagesDocuments = max(1, (int)ceil($filteredDocumentsTotal / $perPage));
        $pageDocuments = min($pageDocuments, $totalPagesDocuments);
        $offsetDocuments = ($pageDocuments - 1) * $perPage;

        $documents = $fetchRows(
            $conn,
            "SELECT id, document_title, document_category, file_mime, created_at
             FROM patient_documents
             WHERE patient_id = ?
               AND (? = '' OR DATE(created_at) >= ?)
               AND (? = '' OR DATE(created_at) <= ?)
               AND (? = '' OR document_title LIKE CONCAT('%', ?, '%') OR document_category LIKE CONCAT('%', ?, '%'))
             ORDER BY created_at DESC, id DESC
             LIMIT ? OFFSET ?",
            'isssssssii',
            [$patientId, $dateFrom, $dateFrom, $dateTo, $dateTo, $searchQuery, $searchQuery, $searchQuery, $perPage, $offsetDocuments]
        );
    } catch (Exception $e) {
        $documents = [];
        $filteredDocumentsTotal = 0;
        $totalPagesDocuments = 1;
        error_log("Patient documents filtered query error: " . $e->getMessage());
    }
} else {
    $totalPagesDocuments = 1;
}

$documentsDisplayStart = $filteredDocumentsTotal > 0 ? (($pageDocuments - 1) * $perPage) + 1 : 0;
$documentsDisplayEnd = $filteredDocumentsTotal > 0 ? min($filteredDocumentsTotal, $pageDocuments * $perPage) : 0;
$reportsDisplayStart = $filteredReportsTotal > 0 ? (($pageReports - 1) * $perPage) + 1 : 0;
$reportsDisplayEnd = $filteredReportsTotal > 0 ? min($filteredReportsTotal, $pageReports * $perPage) : 0;
$prescriptionsDisplayStart = $filteredPrescriptionsTotal > 0 ? (($pagePrescriptions - 1) * $perPage) + 1 : 0;
$prescriptionsDisplayEnd = $filteredPrescriptionsTotal > 0 ? min($filteredPrescriptionsTotal, $pagePrescriptions * $perPage) : 0;
$treatmentsDisplayStart = $filteredTreatmentsTotal > 0 ? (($pageTreatments - 1) * $perPage) + 1 : 0;
$treatmentsDisplayEnd = $filteredTreatmentsTotal > 0 ? min($filteredTreatmentsTotal, $pageTreatments * $perPage) : 0;

$buildDashboardUrl = static function(array $overrides = []) use (
    $accessMode,
    $token,
    $filterType,
    $dateFrom,
    $dateTo,
    $searchQuery,
    $perPage,
    $pageDocuments,
    $pageReports,
    $pagePrescriptions,
    $pageTreatments
): string {
    $params = [
        'record_type' => $filterType,
        'date_from' => $dateFrom,
        'date_to' => $dateTo,
        'q' => $searchQuery,
        'per_page' => $perPage,
        'page_documents' => $pageDocuments,
        'page_reports' => $pageReports,
        'page_prescriptions' => $pagePrescriptions,
        'page_treatments' => $pageTreatments,
    ];

    if ($accessMode === 'token') {
        $params['token'] = $token;
    }

    foreach ($overrides as $key => $value) {
        if ($value === null || $value === '') {
            unset($params[$key]);
        } else {
            $params[$key] = $value;
        }
    }

    return 'patient_dashboard.php?' . http_build_query($params);
};

$renderPagination = static function(int $currentPage, int $totalPages, string $pageParam) use ($buildDashboardUrl): string {
    if ($totalPages <= 1) {
        return '';
    }

    $start = max(1, $currentPage - 1);
    $end = min($totalPages, $currentPage + 1);

    $html = '<nav aria-label="Section pagination"><ul class="pagination pagination-sm mb-0">';

    $prevDisabled = $currentPage <= 1 ? ' disabled' : '';
    $prevUrl = htmlspecialchars($buildDashboardUrl([$pageParam => max(1, $currentPage - 1)]));
    $html .= '<li class="page-item' . $prevDisabled . '"><a class="page-link" href="' . $prevUrl . '">&laquo;</a></li>';

    for ($i = $start; $i <= $end; $i++) {
        $active = $i === $currentPage ? ' active' : '';
        $url = htmlspecialchars($buildDashboardUrl([$pageParam => $i]));
        $html .= '<li class="page-item' . $active . '"><a class="page-link" href="' . $url . '">' . $i . '</a></li>';
    }

    $nextDisabled = $currentPage >= $totalPages ? ' disabled' : '';
    $nextUrl = htmlspecialchars($buildDashboardUrl([$pageParam => min($totalPages, $currentPage + 1)]));
    $html .= '<li class="page-item' . $nextDisabled . '"><a class="page-link" href="' . $nextUrl . '">&raquo;</a></li>';

    $html .= '</ul></nav>';
    return $html;
};

$resetFilterUrl = $buildDashboardUrl([
    'record_type' => 'all',
    'date_from' => null,
    'date_to' => null,
    'q' => null,
    'page_documents' => 1,
    'page_reports' => 1,
    'page_prescriptions' => 1,
    'page_treatments' => 1,
]);

$documentsColClass = $filterType === 'all' ? 'col-12 col-md-6 col-lg-4' : 'col-12';
$reportsColClass = $filterType === 'all' ? 'col-12 col-md-6 col-lg-4' : 'col-12';
$prescriptionsColClass = $filterType === 'all' ? 'col-12 col-md-6 col-lg-4' : 'col-12';
$treatmentsColClass = $filterType === 'all' ? 'col-12 col-md-6 col-lg-4' : 'col-12';

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Dashboard - Bato Medical Report System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        :root {
            --primary-color: #0f766e;
            --secondary-color: #526070;
            --light-color: #f3f7f9;
            --dark-color: #1f2937;
            --border-radius: 12px;
            --box-shadow: 0 3px 10px rgba(31, 41, 51, 0.08);
            --transition: all 0.25s ease;
        }

        body {
            background-color: #edf3f6;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            color: #253142;
            line-height: 1.6;
            font-size: 1.03rem;
            padding: 18px;
            overflow-x: hidden;
        }

        .card {
            border: none;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            margin-bottom: 16px;
            transition: var(--transition);
            overflow: hidden;
            background: #fff;
        }

        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(31, 41, 51, 0.1);
        }

        .card-header {
            background: linear-gradient(135deg, #f8fcfd 0%, #edf6f8 100%);
            border-bottom: 1px solid #d7e7ea;
            color: var(--dark-color);
            font-size: 1rem;
            font-weight: 700;
            padding: 0.95rem 1.15rem;
        }

        .card-body {
            padding: 1.1rem;
        }

        .clinic-brand-banner {
            background: linear-gradient(135deg, #f7fcfc 0%, #eaf5f5 100%);
            color: #1f2933;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            padding: 16px 20px;
            margin-bottom: 16px;
            border: 1px solid #cfe3e6;
        }

        .clinic-brand-title {
            font-size: 1.22rem;
            font-weight: 700;
            letter-spacing: 0.2px;
        }

        .clinic-brand-subtitle {
            font-size: 0.98rem;
            color: #4f6672;
            margin-top: 2px;
        }

        .clinic-trust-note {
            background: #f8fcfd;
            color: #375261;
            border: 1px solid #cfe3e6;
            border-radius: 10px;
            padding: 12px 15px;
            font-size: 0.96rem;
            margin-bottom: 18px;
        }

        .section-heading {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 10px;
        }

        .section-heading h6 {
            margin: 0;
            font-weight: 700;
            color: #1f2d3d;
            letter-spacing: 0.2px;
            font-size: 1.08rem;
        }

        .section-heading small {
            color: #526070;
            font-weight: 500;
            font-size: 0.95rem;
        }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
            gap: 12px;
            margin-bottom: 22px;
        }

        .summary-card {
            border-radius: 12px;
            padding: 14px;
            border: 1px solid #d7e7ea;
            background: #ffffff;
            box-shadow: 0 2px 6px rgba(31, 41, 51, 0.06);
            height: 100%;
            transition: var(--transition);
        }

        .summary-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 18px rgba(31, 41, 51, 0.1);
        }

        .summary-icon {
            width: 42px;
            height: 42px;
            border-radius: 10px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-right: 10px;
            font-size: 1rem;
        }

        .summary-title {
            font-size: 0.93rem;
            color: #4f6171;
            margin-bottom: 2px;
        }

        .summary-value {
            font-size: 1.62rem;
            font-weight: 700;
            line-height: 1;
            color: #1e293b;
        }

        .patient-info-card {
            margin-bottom: 18px;
            border-left: 4px solid var(--primary-color);
        }

        .patient-profile-strip {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 14px;
            border-radius: 12px;
            background: linear-gradient(135deg, rgba(15, 118, 110, 0.08) 0%, rgba(15, 118, 110, 0.03) 100%);
            border: 1px solid rgba(15, 118, 110, 0.16);
            margin-bottom: 12px;
        }

        .patient-avatar {
            width: 58px;
            height: 58px;
            border-radius: 50%;
            background: rgba(15, 118, 110, 0.13);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: var(--primary-color);
            font-size: 1.35rem;
            flex-shrink: 0;
        }

        .patient-name {
            margin: 0;
            font-size: 1.26rem;
            font-weight: 700;
            color: #162333;
            line-height: 1.2;
        }

        .patient-meta {
            margin-top: 4px;
            color: #4d6172;
            font-size: 0.95rem;
            font-weight: 500;
        }

        .info-item {
            margin-bottom: 0;
            padding: 11px 12px;
            background: #f8fcfd;
            border: 1px solid #d7e7ea;
            border-radius: 10px;
            transition: var(--transition);
        }

        .info-item:hover {
            background: #f2f8fa;
            border-color: #bed7dc;
            transform: translateY(-1px);
        }

        .info-item i {
            width: 22px;
            text-align: center;
            margin-right: 8px;
            color: var(--primary-color);
        }

        .info-label {
            color: #617282;
            font-size: 0.88rem;
            font-weight: 600;
            line-height: 1.1;
            margin-bottom: 4px;
        }

        .info-value {
            color: #1f2f40;
            font-size: 1.03rem;
            font-weight: 700;
            line-height: 1.2;
        }

        .document-card {
            height: 100%;
            display: flex;
            flex-direction: column;
        }

        .document-list {
            flex: 1;
        }

        .document-item {
            padding: 12px 15px;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            transition: var(--transition);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .document-item:last-child {
            border-bottom: none;
        }

        .document-item:hover {
            background: rgba(15, 118, 110, 0.05);
        }

        .document-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: rgba(15, 118, 110, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 12px;
            color: var(--primary-color);
            font-size: 18px;
        }

        .document-info {
            flex: 1;
        }

        .document-date {
            font-size: 0.92rem;
            color: var(--secondary-color);
        }

        .badge-count {
            background: var(--primary-color);
            color: white;
            border-radius: 20px;
            padding: 5px 11px;
            font-weight: 600;
            font-size: 0.85rem;
        }

        .empty-message {
            padding: 28px 15px;
            text-align: center;
            color: var(--secondary-color);
            font-style: italic;
            background: rgba(0, 0, 0, 0.02);
            border-radius: 8px;
            margin: 10px;
        }

        .filter-card {
            border: 1px solid #d7e7ea;
            margin-bottom: 18px;
        }

        .filter-card .form-label {
            font-size: 0.82rem;
            color: #60707f;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            margin-bottom: 4px;
        }

        .records-meta {
            font-size: 0.84rem;
            color: #60707f;
            font-weight: 600;
        }

        .section-pagination {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            padding: 10px 12px 12px;
        }

        .pagination .page-link {
            color: #0f766e;
            border-color: #d7e7ea;
        }

        .pagination .page-item.active .page-link {
            background: #0f766e;
            border-color: #0f766e;
        }

        @media (max-width: 768px) {
            body {
                padding: 10px;
                font-size: 1rem;
            }

            .card {
                margin-bottom: 12px;
            }

            .clinic-brand-banner {
                padding: 12px 14px;
            }

            .clinic-brand-title {
                font-size: 1.05rem;
            }

            .clinic-brand-subtitle {
                font-size: 0.88rem;
            }

            .section-heading {
                flex-direction: column;
                align-items: flex-start;
                gap: 2px;
            }

            .patient-profile-strip {
                padding: 10px 11px;
                gap: 10px;
            }

            .patient-avatar {
                width: 46px;
                height: 46px;
                font-size: 1.15rem;
            }

            .patient-name {
                font-size: 1.08rem;
            }

            .patient-meta {
                font-size: 0.88rem;
            }

            .info-item {
                padding: 9px 10px;
            }

            .summary-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 10px;
                margin-bottom: 18px;
            }

            .summary-card {
                padding: 12px;
            }

            .summary-icon {
                width: 34px;
                height: 34px;
                margin-right: 8px;
                font-size: 0.9rem;
            }

            .summary-title {
                font-size: 0.8rem;
            }

            .summary-value {
                font-size: 1.22rem;
            }

            .section-pagination {
                flex-direction: column;
                align-items: flex-start;
            }
        }

        @media (max-width: 450px) {
            .summary-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="clinic-brand-banner d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <div class="clinic-brand-title"><i class="fas fa-clinic-medical me-2"></i>BATO CLINIC</div>
                <div class="clinic-brand-subtitle">Official Patient Portal</div>
            </div>
            <span class="badge text-bg-dark fw-semibold px-3 py-2"><?php echo $accessMode === 'session' ? 'Secure Patient Login' : 'Secure Medical Link'; ?></span>
        </div>

        <div class="clinic-trust-note">
            <i class="fas fa-shield-alt me-2"></i>
            This secure page belongs to <strong>BATO CLINIC</strong>. Do not share this link with anyone else.
        </div>

        <?php if ($accessMode === 'token'): ?>
        <div class="alert alert-light border d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2">
            <div>
                <strong>Patient Portal Access:</strong>
                <?php if ($hasPortalAccount): ?>
                    You already have an account. Please sign in.
                <?php else: ?>
                    New patient? Register first using your Civil ID.
                <?php endif; ?>
            </div>
            <div class="d-flex gap-2">
                <?php if (!$hasPortalAccount): ?>
                <a href="patient_register.php?token=<?php echo urlencode($token); ?>" class="btn btn-sm btn-primary">
                    <i class="fas fa-user-plus me-1"></i> Register
                </a>
                <?php endif; ?>
                <a href="patient_login.php?token=<?php echo urlencode($token); ?>" class="btn btn-sm btn-outline-primary">
                    <i class="fas fa-sign-in-alt me-1"></i> Sign In
                </a>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($accessMode === 'session'): ?>
        <div class="mb-3">
            <a href="patient_logout.php" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-sign-out-alt me-1"></i> Logout
            </a>
        </div>
        <?php endif; ?>

        <div class="section-heading">
            <h6><i class="fas fa-chart-pie me-2 text-primary"></i>Dashboard Overview</h6>
            <small>All totals below are from your own patient portal records.</small>
        </div>

        <!-- Patient Portal Summary Totals -->
        <div class="summary-grid">
            <div>
                <div class="summary-card">
                    <div class="d-flex align-items-center">
                        <span class="summary-icon" style="background: rgba(15, 118, 110, 0.14); color: #0f766e;">
                            <i class="fas fa-layer-group"></i>
                        </span>
                        <div>
                            <div class="summary-title">Total Records</div>
                            <div class="summary-value"><?php echo $totalRecords; ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <div>
                <div class="summary-card">
                    <div class="d-flex align-items-center">
                        <span class="summary-icon" style="background: rgba(71, 85, 105, 0.14); color: #475569;">
                            <i class="fas fa-folder-open"></i>
                        </span>
                        <div>
                            <div class="summary-title">Documents</div>
                            <div class="summary-value"><?php echo $totalDocuments; ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <div>
                <div class="summary-card">
                    <div class="d-flex align-items-center">
                        <span class="summary-icon" style="background: rgba(14, 116, 144, 0.14); color: #0e7490;">
                            <i class="fas fa-file-medical"></i>
                        </span>
                        <div>
                            <div class="summary-title">Medical Reports</div>
                            <div class="summary-value"><?php echo $totalReports; ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <div>
                <div class="summary-card">
                    <div class="d-flex align-items-center">
                        <span class="summary-icon" style="background: rgba(5, 150, 105, 0.14); color: #059669;">
                            <i class="fas fa-prescription"></i>
                        </span>
                        <div>
                            <div class="summary-title">Prescriptions</div>
                            <div class="summary-value"><?php echo $totalPrescriptions; ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <div>
                <div class="summary-card">
                    <div class="d-flex align-items-center">
                        <span class="summary-icon" style="background: rgba(217, 119, 6, 0.14); color: #b45309;">
                            <i class="fas fa-heartbeat"></i>
                        </span>
                        <div>
                            <div class="summary-title">Treatments</div>
                            <div class="summary-value"><?php echo $totalTreatments; ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Patient Information Card -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card patient-info-card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-user-circle me-2"></i>Patient Information</h5>
                        <span class="badge" style="background: var(--primary-color);">Patient ID: <?php echo $patientId; ?></span>
                    </div>
                    <div class="card-body">
                        <div class="patient-profile-strip">
                            <div class="patient-avatar">
                                <i class="fas fa-user-tie"></i>
                            </div>
                            <div>
                                <h5 class="patient-name">
                                    <?php 
                                        echo !empty($patient['name']) ? htmlspecialchars($patient['name']) : 
                                             (!empty($patient['first_name']) ? 
                                                 htmlspecialchars($patient['first_name'] . ' ' . ($patient['last_name'] ?? '')) : 
                                                 'N/A'); 
                                    ?>
                                </h5>
                                <?php if (!empty($patient['file_number'])): ?>
                                <div class="patient-meta">File #<?php echo htmlspecialchars($patient['file_number']); ?></div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="row g-2">
                            <?php if (!empty($patient['civil_id'])): ?>
                            <div class="col-12 col-md-6">
                                <div class="info-item d-flex align-items-center">
                                    <i class="fas fa-id-card"></i>
                                    <div>
                                        <div class="info-label">Civil ID</div>
                                        <div class="info-value"><?php echo htmlspecialchars($patient['civil_id']); ?></div>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>

                            <?php if (!empty($patient['mobile'])): ?>
                            <div class="col-12 col-md-6">
                                <div class="info-item d-flex align-items-center">
                                    <i class="fas fa-mobile-alt"></i>
                                    <div>
                                        <div class="info-label">Mobile</div>
                                        <div class="info-value"><?php echo htmlspecialchars($patient['mobile']); ?></div>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>

                            <?php if (!empty($patient['phone'])): ?>
                            <div class="col-12 col-md-6">
                                <div class="info-item d-flex align-items-center">
                                    <i class="fas fa-phone-alt"></i>
                                    <div>
                                        <div class="info-label">Phone</div>
                                        <div class="info-value"><?php echo htmlspecialchars($patient['phone']); ?></div>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>

                            <?php if (!empty($patient['email'])): ?>
                            <div class="col-12 col-md-6">
                                <div class="info-item d-flex align-items-center">
                                    <i class="fas fa-envelope"></i>
                                    <div class="text-truncate">
                                        <div class="info-label">Email</div>
                                        <div class="info-value text-truncate"><?php echo htmlspecialchars($patient['email']); ?></div>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card filter-card">
            <div class="card-body">
                <form method="GET" action="patient_dashboard.php" class="row g-3 align-items-end">
                    <?php if ($accessMode === 'token'): ?>
                    <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                    <?php endif; ?>

                    <div class="col-12 col-md-3">
                        <label class="form-label">Record Type</label>
                        <select class="form-select" name="record_type">
                            <option value="all" <?php echo $filterType === 'all' ? 'selected' : ''; ?>>All Records</option>
                            <option value="documents" <?php echo $filterType === 'documents' ? 'selected' : ''; ?>>Documents</option>
                            <option value="reports" <?php echo $filterType === 'reports' ? 'selected' : ''; ?>>Medical Reports</option>
                            <option value="prescriptions" <?php echo $filterType === 'prescriptions' ? 'selected' : ''; ?>>Prescriptions</option>
                            <option value="treatments" <?php echo $filterType === 'treatments' ? 'selected' : ''; ?>>Treatments</option>
                        </select>
                    </div>

                    <div class="col-12 col-md-3">
                        <label class="form-label">Search</label>
                        <input type="text" class="form-control" name="q" value="<?php echo htmlspecialchars($searchQuery); ?>" placeholder="Title, category, treatment...">
                    </div>

                    <div class="col-6 col-md-2">
                        <label class="form-label">From Date</label>
                        <input type="date" class="form-control" name="date_from" value="<?php echo htmlspecialchars($dateFrom); ?>">
                    </div>

                    <div class="col-6 col-md-2">
                        <label class="form-label">To Date</label>
                        <input type="date" class="form-control" name="date_to" value="<?php echo htmlspecialchars($dateTo); ?>">
                    </div>

                    <div class="col-6 col-md-1">
                        <label class="form-label">Per Page</label>
                        <select class="form-select" name="per_page">
                            <option value="5" <?php echo $perPage === 5 ? 'selected' : ''; ?>>5</option>
                            <option value="10" <?php echo $perPage === 10 ? 'selected' : ''; ?>>10</option>
                            <option value="20" <?php echo $perPage === 20 ? 'selected' : ''; ?>>20</option>
                        </select>
                    </div>

                    <div class="col-6 col-md-1 d-grid gap-2">
                        <button type="submit" class="btn btn-primary btn-sm">Apply</button>
                        <a href="<?php echo htmlspecialchars($resetFilterUrl); ?>" class="btn btn-outline-secondary btn-sm">Reset</a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Documents Section -->
        <div class="row g-4">
            <?php if ($showDocuments): ?>
            <!-- Documents -->
            <div class="<?php echo $documentsColClass; ?>">
                <div class="card document-card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center">
                            <div class="me-2" style="width: 36px; height: 36px; background: rgba(71, 85, 105, 0.14); border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                                <i class="fas fa-folder-open" style="color: #475569;"></i>
                            </div>
                            <span>Documents</span>
                        </div>
                        <span class="badge-count" style="background: #475569;"><?php echo $filteredDocumentsTotal; ?></span>
                    </div>
                    <div class="document-list p-3">
                        <?php if (!empty($documents)): ?>
                            <?php foreach ($documents as $document): ?>
                                <?php
                                $docUrl = 'patient_document_download.php?id=' . (int)$document['id'] . '&mode=inline';
                                if ($accessMode === 'token') {
                                    $docUrl .= '&token=' . urlencode($token);
                                }
                                $docTypeLabel = (strpos((string)($document['file_mime'] ?? ''), 'image/') === 0) ? 'Image' : 'PDF';
                                ?>
                                <a href="<?php echo $docUrl; ?>"
                                   class="text-decoration-none text-dark document-item" target="_blank">
                                    <div class="d-flex align-items-center">
                                        <div class="document-icon" style="background: rgba(71, 85, 105, 0.14); color: #475569;">
                                            <i class="fas fa-file"></i>
                                        </div>
                                        <div class="document-info">
                                            <div class="fw-medium"><?php echo htmlspecialchars($document['document_title'] ?? 'Patient Document'); ?></div>
                                            <div class="document-date">
                                                <?php echo date('M d, Y', strtotime($document['created_at'])); ?>
                                                <span class="ms-2">• <?php echo htmlspecialchars($docTypeLabel); ?></span>
                                            </div>
                                        </div>
                                    </div>
                                    <i class="fas fa-chevron-right text-muted"></i>
                                </a>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-message">
                                <i class="fas fa-inbox display-4 text-muted mb-2"></i>
                                <p class="mb-0"><?php echo ($searchQuery !== '' || $dateFrom !== '' || $dateTo !== '') ? 'No documents match your filters' : 'No documents found'; ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="section-pagination">
                        <small class="records-meta">Showing <?php echo $documentsDisplayStart; ?>-<?php echo $documentsDisplayEnd; ?> of <?php echo $filteredDocumentsTotal; ?></small>
                        <?php echo $renderPagination($pageDocuments, $totalPagesDocuments, 'page_documents'); ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($showReports): ?>
            <!-- Medical Reports -->
            <div class="<?php echo $reportsColClass; ?>">
                <div class="card document-card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center">
                            <div class="me-2" style="width: 36px; height: 36px; background: rgba(14, 116, 144, 0.14); border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                                <i class="fas fa-file-medical" style="color: #0e7490;"></i>
                            </div>
                            <span>Medical Reports</span>
                        </div>
                        <span class="badge-count"><?php echo $filteredReportsTotal; ?></span>
                    </div>
                    <div class="document-list p-3">
                        <?php if (!empty($reports)): ?>
                            <?php foreach ($reports as $report): ?>
                                <?php $docParam = base64_encode($report['id'] . '_' . $patientId); ?>
                                <a href="<?php echo $accessMode === 'token'
                                    ? ('view_report.php?token=' . urlencode($token) . '&doc=' . urlencode($docParam))
                                    : ('view_report.php?id=' . (int)$report['id']); ?>" 
                                   class="text-decoration-none text-dark document-item" target="_blank">
                                    <div class="d-flex align-items-center">
                                        <div class="document-icon" style="background: rgba(14, 116, 144, 0.14); color: #0e7490;">
                                            <i class="fas fa-file-alt"></i>
                                        </div>
                                        <div class="document-info">
                                            <div class="fw-medium"><?php echo htmlspecialchars($report['report_title'] ?? 'Untitled Report'); ?></div>
                                            <div class="document-date"><?php echo date('M d, Y', strtotime($report['report_date'])); ?></div>
                                        </div>
                                    </div>
                                    <i class="fas fa-chevron-right text-muted"></i>
                                </a>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-message">
                                <i class="fas fa-inbox display-4 text-muted mb-2"></i>
                                <p class="mb-0"><?php echo ($searchQuery !== '' || $dateFrom !== '' || $dateTo !== '') ? 'No medical reports match your filters' : 'No medical reports found'; ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="section-pagination">
                        <small class="records-meta">Showing <?php echo $reportsDisplayStart; ?>-<?php echo $reportsDisplayEnd; ?> of <?php echo $filteredReportsTotal; ?></small>
                        <?php echo $renderPagination($pageReports, $totalPagesReports, 'page_reports'); ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($showPrescriptions): ?>
            <!-- Prescriptions -->
            <div class="<?php echo $prescriptionsColClass; ?>">
                <div class="card document-card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center">
                            <div class="me-2" style="width: 36px; height: 36px; background: rgba(5, 150, 105, 0.14); border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                                <i class="fas fa-prescription" style="color: #059669;"></i>
                            </div>
                            <span>Prescriptions</span>
                        </div>
                        <span class="badge-count" style="background: #059669;"><?php echo $filteredPrescriptionsTotal; ?></span>
                    </div>
                    <div class="document-list p-3">
                        <?php if (!empty($prescriptions)): ?>
                            <?php foreach ($prescriptions as $prescription): ?>
                                <?php $docParam = base64_encode($prescription['id'] . '_' . $patientId); ?>
                                <a href="<?php echo $accessMode === 'token'
                                    ? ('view_prescription.php?token=' . urlencode($token) . '&doc=' . urlencode($docParam))
                                    : ('view_prescription.php?id=' . (int)$prescription['id']); ?>" 
                                   class="text-decoration-none text-dark document-item" target="_blank">
                                    <div class="d-flex align-items-center">
                                        <div class="document-icon" style="background: rgba(5, 150, 105, 0.14); color: #059669;">
                                            <i class="fas fa-pills"></i>
                                        </div>
                                        <div class="document-info">
                                            <div class="fw-medium"><?php echo htmlspecialchars($prescription['prescription_title'] ?? 'Untitled Prescription'); ?></div>
                                            <div class="document-date"><?php echo date('M d, Y', strtotime($prescription['prescription_date'])); ?></div>
                                        </div>
                                    </div>
                                    <i class="fas fa-chevron-right text-muted"></i>
                                </a>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-message">
                                <i class="fas fa-inbox display-4 text-muted mb-2"></i>
                                <p class="mb-0"><?php echo ($searchQuery !== '' || $dateFrom !== '' || $dateTo !== '') ? 'No prescriptions match your filters' : 'No prescriptions found'; ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="section-pagination">
                        <small class="records-meta">Showing <?php echo $prescriptionsDisplayStart; ?>-<?php echo $prescriptionsDisplayEnd; ?> of <?php echo $filteredPrescriptionsTotal; ?></small>
                        <?php echo $renderPagination($pagePrescriptions, $totalPagesPrescriptions, 'page_prescriptions'); ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($showTreatments): ?>
            <!-- Treatments -->
            <div class="<?php echo $treatmentsColClass; ?>">
                <div class="card document-card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center">
                            <div class="me-2" style="width: 36px; height: 36px; background: rgba(217, 119, 6, 0.14); border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                                <i class="fas fa-heartbeat" style="color: #b45309;"></i>
                            </div>
                            <span>Treatments</span>
                        </div>
                        <span class="badge-count" style="background: #b45309;"><?php echo $filteredTreatmentsTotal; ?></span>
                    </div>
                    <div class="document-list p-3">
                        <?php if (!empty($treatments)): ?>
                            <?php foreach ($treatments as $treatment): ?>
                                <?php $docParam = base64_encode($treatment['id'] . '_' . $patientId); ?>
                                <a href="<?php echo $accessMode === 'token'
                                    ? ('view_treatment.php?token=' . urlencode($token) . '&doc=' . urlencode($docParam))
                                    : ('view_treatment.php?id=' . (int)$treatment['id']); ?>" 
                                   class="text-decoration-none text-dark document-item" target="_blank">
                                    <div class="d-flex align-items-center">
                                        <div class="document-icon" style="background: rgba(217, 119, 6, 0.14); color: #b45309;">
                                            <i class="fas fa-stethoscope"></i>
                                        </div>
                                        <div class="document-info">
                                            <div class="fw-medium"><?php echo htmlspecialchars($treatment['treatment_type'] ?? 'Treatment'); ?></div>
                                            <div class="document-date">
                                                <?php echo date('M d, Y', strtotime($treatment['treatment_date'])); ?>
                                                <?php if (!empty($treatment['nurse_name'])): ?>
                                                    <span class="ms-2">• <?php echo htmlspecialchars($treatment['nurse_name']); ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                    <i class="fas fa-chevron-right text-muted"></i>
                                </a>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-message">
                                <i class="fas fa-inbox display-4 text-muted mb-2"></i>
                                <p class="mb-0"><?php echo ($searchQuery !== '' || $dateFrom !== '' || $dateTo !== '') ? 'No treatments match your filters' : 'No treatments found'; ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="section-pagination">
                        <small class="records-meta">Showing <?php echo $treatmentsDisplayStart; ?>-<?php echo $treatmentsDisplayEnd; ?> of <?php echo $filteredTreatmentsTotal; ?></small>
                        <?php echo $renderPagination($pageTreatments, $totalPagesTreatments, 'page_treatments'); ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Footer -->
        <footer class="mt-5 pt-4 border-top">
            <div class="container">
                <div class="row">
                    <div class="col-12 text-center">
                        <p class="text-muted mb-0">
                            <small> <?php echo date('Y'); ?> Bato Medical Report System. All rights reserved.</small>
                        </p>
                        <p class="text-muted mt-2">
                            <small>Secure Patient Portal - Last updated: <?php echo date('M d, Y h:i A'); ?></small>
                        </p>
                    </div>
                </div>
            </div>
        </footer>
    </div>

    <?php if ($accessMode === 'token' && $hasPortalAccount): ?>
    <div class="modal fade" id="existingAccountModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-user-check me-2 text-success"></i>Account Already Created</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    This patient already has a portal account. Please sign in using your username and password.
                </div>
                <div class="modal-footer">
                    <a href="patient_login.php?token=<?php echo urlencode($token); ?>" class="btn btn-primary">
                        <i class="fas fa-sign-in-alt me-1"></i> Go to Sign In
                    </a>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- JavaScript Libraries -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Add any necessary JavaScript here
        document.addEventListener('DOMContentLoaded', function() {
            // Add any initialization code here
            <?php if ($accessMode === 'token' && $hasPortalAccount): ?>
            var existingAccountModalEl = document.getElementById('existingAccountModal');
            if (existingAccountModalEl) {
                var existingAccountModal = new bootstrap.Modal(existingAccountModalEl);
                existingAccountModal.show();
            }
            <?php endif; ?>
        });
    </script>
</body>
</html>