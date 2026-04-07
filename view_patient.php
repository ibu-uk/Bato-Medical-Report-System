<?php
// Start session
session_start();

// Include configuration files
require_once 'config/database.php';
require_once 'config/auth.php';
require_once 'config/timezone.php';
require_once 'config/helpers.php';
require_once 'config/patient_documents.php';

requireLogin();
if (!canManagePatients()) {
    header('Location: dashboard.php');
    exit;
}

// Check if patient ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: patient_list.php');
    exit();
}

$patient_id = (int)$_GET['id'];

// Create database connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

function bindDynamicParams($stmt, $types, array &$params) {
    if ($types === '' || empty($params)) {
        return true;
    }

    $bindArgs = [];
    $bindArgs[] = &$types;
    foreach ($params as $key => $value) {
        $bindArgs[] = &$params[$key];
    }

    return call_user_func_array([$stmt, 'bind_param'], $bindArgs);
}

// sanitize() function is now in config/helpers.php

// Get patient details
$patient_query = "SELECT * FROM patients WHERE id = ?";
$stmt = $conn->prepare($patient_query);
$stmt->bind_param('i', $patient_id);
$stmt->execute();
$patient_result = $stmt->get_result();

if ($patient_result->num_rows === 0) {
    // Patient not found, redirect to patient list
    header('Location: patient_list.php?error=patient_not_found');
    exit();
}

$patient = $patient_result->fetch_assoc();

if (!ensurePatientDocumentTables($conn)) {
    die('Could not initialize patient documents table.');
}

$documentUploadError = '';
$documentUploadSuccess = isset($_GET['uploaded']) && $_GET['uploaded'] === '1';
$documentDeleteSuccess = isset($_GET['deleted']) && $_GET['deleted'] === '1';
$documentCategoryOptions = patientDocumentCategories($conn, true);
$documentCategoryLookup = patientDocumentCategories($conn, false);
$allowedDocumentMimes = patientDocumentAllowedMimes();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && ($_POST['action'] ?? '') === 'delete_patient_document') {
    if (!canDeletePatients()) {
        $documentUploadError = 'You are not allowed to delete patient documents.';
    } else {
        $documentIdToDelete = isset($_POST['document_id']) ? (int)$_POST['document_id'] : 0;
        if ($documentIdToDelete <= 0) {
            $documentUploadError = 'Invalid document selected for deletion.';
        } else {
            $findQuery = "SELECT id, file_path, document_title FROM patient_documents WHERE id = ? AND patient_id = ? LIMIT 1";
            $findStmt = $conn->prepare($findQuery);
            if (!$findStmt) {
                $documentUploadError = 'Could not prepare delete request.';
            } else {
                $findStmt->bind_param('ii', $documentIdToDelete, $patient_id);
                $findStmt->execute();
                $foundResult = $findStmt->get_result();
                $documentRow = $foundResult ? $foundResult->fetch_assoc() : null;
                $findStmt->close();

                if (!$documentRow) {
                    $documentUploadError = 'Document not found for this patient.';
                } else {
                    $deleteStmt = $conn->prepare("DELETE FROM patient_documents WHERE id = ? AND patient_id = ? LIMIT 1");
                    if (!$deleteStmt) {
                        $documentUploadError = 'Could not prepare document delete operation.';
                    } else {
                        $deleteStmt->bind_param('ii', $documentIdToDelete, $patient_id);
                        $deletedOk = $deleteStmt->execute();
                        $deleteStmt->close();

                        if (!$deletedOk) {
                            $documentUploadError = 'Failed to delete document record.';
                        } else {
                            $relativePath = (string)($documentRow['file_path'] ?? '');
                            if (strpos($relativePath, 'uploads/patient_documents/') === 0) {
                                $fullPath = __DIR__ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
                                if (is_file($fullPath)) {
                                    @unlink($fullPath);
                                }
                            }

                            if (function_exists('logUserActivity')) {
                                logUserActivity('patient_document_delete', $documentIdToDelete, 'Deleted patient document', (string)($documentRow['document_title'] ?? 'Document'));
                            }

                            header('Location: view_patient.php?id=' . $patient_id . '&tab=documents&deleted=1');
                            exit;
                        }
                    }
                }
            }
        }
    }
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && ($_POST['action'] ?? '') === 'upload_patient_document') {
    $documentTitle = trim((string)($_POST['document_title'] ?? ''));
    $documentCategory = trim((string)($_POST['document_category'] ?? 'other'));
    $documentNotes = trim((string)($_POST['document_notes'] ?? ''));
    $documentExpiryDateRaw = trim((string)($_POST['document_expiry_date'] ?? ''));
    $reminderDaysRaw = isset($_POST['reminder_days_before']) ? (int)$_POST['reminder_days_before'] : 0;
    $documentExpiryDate = null;
    $reminderDaysBefore = max(0, min(3650, $reminderDaysRaw));

    if ($documentExpiryDateRaw !== '') {
        $expiryObj = date_create($documentExpiryDateRaw);
        if ($expiryObj === false) {
            $documentUploadError = 'Invalid expiry date format.';
        } else {
            $documentExpiryDate = $expiryObj->format('Y-m-d');
        }
    }

    if ($documentUploadError === '' && $documentTitle === '') {
        $documentUploadError = 'Document title is required.';
    } elseif ($documentUploadError === '' && !isset($documentCategoryOptions[$documentCategory])) {
        $documentUploadError = 'Invalid document category selected.';
    } elseif (
        $documentUploadError === ''
        && (
            !isset($_FILES['document_file'])
            || !is_array($_FILES['document_file'])
            || (int)($_FILES['document_file']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE
        )
    ) {
        $documentUploadError = 'Please choose a file to upload.';
    } else {
        $file = $_FILES['document_file'];
        $uploadError = (int)($file['error'] ?? UPLOAD_ERR_OK);
        if ($uploadError !== UPLOAD_ERR_OK) {
            $documentUploadError = 'File upload failed. Please try again.';
        } else {
            $maxBytes = 15 * 1024 * 1024; // 15MB
            $fileSize = (int)($file['size'] ?? 0);
            if ($fileSize <= 0 || $fileSize > $maxBytes) {
                $documentUploadError = 'File must be between 1 byte and 15MB.';
            } else {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $detectedMime = $finfo ? finfo_file($finfo, (string)$file['tmp_name']) : '';
                if ($finfo) {
                    finfo_close($finfo);
                }

                if (!isset($allowedDocumentMimes[$detectedMime])) {
                    $documentUploadError = 'Only PDF, JPG, PNG, WEBP, GIF, or TIFF files are allowed.';
                } else {
                    $uploadDirFs = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'patient_documents' . DIRECTORY_SEPARATOR . $patient_id;
                    if (!is_dir($uploadDirFs) && !mkdir($uploadDirFs, 0755, true)) {
                        $documentUploadError = 'Could not create patient document folder.';
                    } elseif (!is_writable($uploadDirFs)) {
                        $documentUploadError = 'Patient document folder is not writable.';
                    } else {
                        $safeOriginalName = preg_replace('/[^a-zA-Z0-9_\.-]/', '_', (string)($file['name'] ?? 'document'));
                        if ($safeOriginalName === '') {
                            $safeOriginalName = 'document';
                        }

                        $fileExtension = $allowedDocumentMimes[$detectedMime];
                        $storedFileName = 'patient_' . $patient_id . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $fileExtension;
                        $targetFsPath = $uploadDirFs . DIRECTORY_SEPARATOR . $storedFileName;

                        if (!move_uploaded_file((string)$file['tmp_name'], $targetFsPath)) {
                            $documentUploadError = 'Failed to save uploaded file.';
                        } else {
                            $relativePath = 'uploads/patient_documents/' . $patient_id . '/' . $storedFileName;
                            $uploadedBy = (int)($_SESSION['user_id'] ?? 0);
                            $insertQuery = "INSERT INTO patient_documents (patient_id, uploaded_by, document_title, document_category, notes, expiry_date, reminder_days_before, file_path, file_name, file_mime, file_size)
                                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                            $insertStmt = $conn->prepare($insertQuery);
                            if (!$insertStmt) {
                                @unlink($targetFsPath);
                                $documentUploadError = 'Could not prepare document save operation.';
                            } else {
                                $insertStmt->bind_param(
                                    'iissssisssi',
                                    $patient_id,
                                    $uploadedBy,
                                    $documentTitle,
                                    $documentCategory,
                                    $documentNotes,
                                    $documentExpiryDate,
                                    $reminderDaysBefore,
                                    $relativePath,
                                    $safeOriginalName,
                                    $detectedMime,
                                    $fileSize
                                );
                                $saved = $insertStmt->execute();
                                $insertStmt->close();

                                if (!$saved) {
                                    @unlink($targetFsPath);
                                    $documentUploadError = 'Could not save document details in database.';
                                } else {
                                    if (function_exists('logUserActivity')) {
                                        $entityName = $documentTitle;
                                        logUserActivity('patient_document_upload', (int)$conn->insert_id, 'Uploaded patient document', $entityName);
                                    }
                                    header('Location: view_patient.php?id=' . $patient_id . '&tab=documents&uploaded=1');
                                    exit;
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}

$documentCount = 0;
$docCountStmt = $conn->prepare("SELECT COUNT(*) AS total FROM patient_documents WHERE patient_id = ?");
if ($docCountStmt) {
    $docCountStmt->bind_param('i', $patient_id);
    $docCountStmt->execute();
    $docCountResult = $docCountStmt->get_result();
    if ($docCountResult && $docCountRow = $docCountResult->fetch_assoc()) {
        $documentCount = (int)($docCountRow['total'] ?? 0);
    }
    $docCountStmt->close();
}

$docCategoryFilter = trim((string)($_GET['doc_category'] ?? ''));
$docFileTypeFilter = trim((string)($_GET['doc_file_type'] ?? ''));
$docSearch = trim((string)($_GET['doc_search'] ?? ''));
$docCurrentPage = isset($_GET['doc_page']) ? (int)$_GET['doc_page'] : 1;
if ($docCurrentPage < 1) {
    $docCurrentPage = 1;
}
$docPerPage = 100;

if ($docCategoryFilter !== '' && !isset($documentCategoryLookup[$docCategoryFilter])) {
    $docCategoryFilter = '';
}
if (!in_array($docFileTypeFilter, ['', 'pdf', 'image'], true)) {
    $docFileTypeFilter = '';
}

$docFilteredCountQuery = "SELECT COUNT(*) AS total FROM patient_documents pd WHERE pd.patient_id = ?";
$docFilteredCountParams = [$patient_id];
$docFilteredCountTypes = 'i';

if ($docCategoryFilter !== '') {
    $docFilteredCountQuery .= " AND pd.document_category = ?";
    $docFilteredCountParams[] = $docCategoryFilter;
    $docFilteredCountTypes .= 's';
}
if ($docFileTypeFilter === 'pdf') {
    $docFilteredCountQuery .= " AND pd.file_mime = 'application/pdf'";
} elseif ($docFileTypeFilter === 'image') {
    $docFilteredCountQuery .= " AND pd.file_mime LIKE 'image/%'";
}
if ($docSearch !== '') {
    $docFilteredCountQuery .= " AND (pd.document_title LIKE ? OR pd.file_name LIKE ? OR pd.notes LIKE ?)";
    $docSearchLike = '%' . $docSearch . '%';
    $docFilteredCountParams[] = $docSearchLike;
    $docFilteredCountParams[] = $docSearchLike;
    $docFilteredCountParams[] = $docSearchLike;
    $docFilteredCountTypes .= 'sss';
}

$docFilteredCount = 0;
$docFilteredCountStmt = $conn->prepare($docFilteredCountQuery);
if ($docFilteredCountStmt && bindDynamicParams($docFilteredCountStmt, $docFilteredCountTypes, $docFilteredCountParams)) {
    $docFilteredCountStmt->execute();
    $docFilteredCountResult = $docFilteredCountStmt->get_result();
    if ($docFilteredCountResult && ($docFilteredCountRow = $docFilteredCountResult->fetch_assoc())) {
        $docFilteredCount = (int)($docFilteredCountRow['total'] ?? 0);
    }
    $docFilteredCountStmt->close();
}

$docTotalPages = max(1, (int)ceil($docFilteredCount / $docPerPage));
if ($docCurrentPage > $docTotalPages) {
    $docCurrentPage = $docTotalPages;
}
$docOffset = ($docCurrentPage - 1) * $docPerPage;

$documentsQuery = "SELECT pd.*, u.full_name AS uploaded_by_name
                   FROM patient_documents pd
                   LEFT JOIN users u ON u.id = pd.uploaded_by
                   WHERE pd.patient_id = ?";
$docParams = [$patient_id];
$docTypes = 'i';

if ($docCategoryFilter !== '') {
    $documentsQuery .= " AND pd.document_category = ?";
    $docParams[] = $docCategoryFilter;
    $docTypes .= 's';
}
if ($docFileTypeFilter === 'pdf') {
    $documentsQuery .= " AND pd.file_mime = 'application/pdf'";
} elseif ($docFileTypeFilter === 'image') {
    $documentsQuery .= " AND pd.file_mime LIKE 'image/%'";
}
if ($docSearch !== '') {
    $documentsQuery .= " AND (pd.document_title LIKE ? OR pd.file_name LIKE ? OR pd.notes LIKE ?)";
    $docSearchLike = '%' . $docSearch . '%';
    $docParams[] = $docSearchLike;
    $docParams[] = $docSearchLike;
    $docParams[] = $docSearchLike;
    $docTypes .= 'sss';
}

$documentsQuery .= " ORDER BY pd.created_at DESC LIMIT ? OFFSET ?";
$docParams[] = $docPerPage;
$docParams[] = $docOffset;
$docTypes .= 'ii';

$patientDocuments = [];
$docStmt = $conn->prepare($documentsQuery);
if ($docStmt) {
    if (bindDynamicParams($docStmt, $docTypes, $docParams)) {
        $docStmt->execute();
        $docResult = $docStmt->get_result();
        while ($docResult && $row = $docResult->fetch_assoc()) {
            $patientDocuments[] = $row;
        }
    }
    $docStmt->close();
}

$activeTab = trim((string)($_GET['tab'] ?? 'reports'));
$allowedTabs = ['reports', 'prescriptions', 'treatments', 'documents'];
if (!in_array($activeTab, $allowedTabs, true)) {
    $activeTab = 'reports';
}

// Get patient's reports with doctor information
$reports_query = "SELECT r.*, d.name as doctor_name 
                 FROM reports r 
                 LEFT JOIN doctors d ON r.doctor_id = d.id 
                 WHERE r.patient_id = ? 
                 ORDER BY r.report_date DESC";
$stmt = $conn->prepare($reports_query);
$stmt->bind_param('i', $patient_id);
$stmt->execute();
$reports = $stmt->get_result();

// Get patient's prescriptions
$prescriptions_query = "SELECT p.*, d.name as doctor_name 
                       FROM prescriptions p 
                       LEFT JOIN doctors d ON p.doctor_id = d.id 
                       WHERE p.patient_id = ? 
                       ORDER BY p.prescription_date DESC";
$stmt = $conn->prepare($prescriptions_query);
$stmt->bind_param('i', $patient_id);
$stmt->execute();
$prescriptions = $stmt->get_result();

// Get patient's treatments
$treatments_query = "SELECT * FROM nurse_treatments WHERE patient_id = ? ORDER BY treatment_date DESC";
$stmt = $conn->prepare($treatments_query);
$stmt->bind_param('i', $patient_id);
$stmt->execute();
$treatments = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Patient - Bato Medical Report System</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
            padding: 20px;
        }
        .card {
            border: none;
            border-radius: 8px;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            margin-bottom: 20px;
        }
        .card-header {
            background-color: #f8f9fa;
            border-bottom: 1px solid rgba(0,0,0,.125);
            padding: 1rem 1.5rem;
            border-radius: 8px 8px 0 0 !important;
        }
        .card-body {
            padding: 1.5rem;
        }
        .btn-back {
            margin-right: 10px;
        }
        .form-label {
            font-weight: 500;
            margin-bottom: 0.5rem;
        }
        .patient-info {
            background-color: #fff;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }
        .info-label {
            font-weight: 600;
            color: #6c757d;
        }
        .nav-tabs .nav-link {
            color: #495057;
            font-weight: 500;
        }
        .nav-tabs .nav-link.active {
            font-weight: 600;
            color: #0d6efd;
        }
    </style>
</head>
<body>

    <!-- Main Content -->
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <a href="patient_list.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>
                <span class="d-none d-sm-inline">Back to Patients</span>
            </a>
        </div>
    </div>

    <!-- Patient Information -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0"><i class="fas fa-user me-2"></i>Patient Information</h5>
        </div>
        <div class="card-body">
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="border rounded p-3 h-100">
                        <p class="text-muted mb-2 small"><i class="fas fa-user me-2"></i>Full Name</p>
                        <h5 class="mb-0 fw-bold"><?php echo htmlspecialchars($patient['name']); ?></h5>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="border rounded p-3 h-100">
                        <p class="text-muted mb-2 small"><i class="fas fa-id-card me-2"></i>Civil ID</p>
                        <h5 class="mb-0 fw-bold"><?php echo htmlspecialchars($patient['civil_id']); ?></h5>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="border rounded p-3 h-100">
                        <p class="text-muted mb-2 small"><i class="fas fa-phone me-2"></i>Mobile Number</p>
                        <h5 class="mb-0 fw-bold">
                            <?php 
                            $phone = !empty($patient['mobile']) ? $patient['mobile'] : 
                                    (!empty($patient['phone']) ? $patient['phone'] : 'N/A');
                            echo htmlspecialchars($phone);
                            ?>
                        </h5>
                    </div>
                </div>
            </div>
            <?php if (!empty($patient['address'])): ?>
            <div class="row">
                <div class="col-12">
                    <h6 class="text-muted mb-1">Address</h6>
                    <p class="mb-0"><?php echo nl2br(sanitize($patient['address'])); ?></p>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Medical Information Tabs -->
    <ul class="nav nav-tabs mb-4" id="patientTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link <?php echo $activeTab === 'reports' ? 'active' : ''; ?>" id="reports-tab" data-bs-toggle="tab" data-bs-target="#reports" type="button" role="tab">
                <i class="fas fa-file-medical me-2"></i>Reports
                <span class="badge bg-primary ms-2"><?php echo $reports->num_rows; ?></span>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link <?php echo $activeTab === 'prescriptions' ? 'active' : ''; ?>" id="prescriptions-tab" data-bs-toggle="tab" data-bs-target="#prescriptions" type="button" role="tab">
                <i class="fas fa-prescription me-2"></i>Prescriptions
                <span class="badge bg-success ms-2"><?php echo $prescriptions->num_rows; ?></span>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link <?php echo $activeTab === 'treatments' ? 'active' : ''; ?>" id="treatments-tab" data-bs-toggle="tab" data-bs-target="#treatments" type="button" role="tab">
                <i class="fas fa-user-nurse me-2"></i>Treatments
                <span class="badge bg-warning text-dark ms-2"><?php echo $treatments->num_rows; ?></span>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link <?php echo $activeTab === 'documents' ? 'active' : ''; ?>" id="documents-tab" data-bs-toggle="tab" data-bs-target="#documents" type="button" role="tab">
                <i class="fas fa-folder-open me-2"></i>Documents
                <span class="badge bg-secondary ms-2"><?php echo $documentCount; ?></span>
            </button>
        </li>
    </ul>

    <div class="tab-content" id="patientTabsContent">
        <!-- Reports Tab -->
        <div class="tab-pane fade <?php echo $activeTab === 'reports' ? 'show active' : ''; ?>" id="reports" role="tabpanel" aria-labelledby="reports-tab">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Medical Reports</h5>
                    <a href="add_report.php?patient_id=<?php echo $patient_id; ?>" class="btn btn-sm btn-primary">
                        <i class="fas fa-plus me-1"></i>Add Report
                    </a>
                </div>
                <div class="card-body">
                    <?php if ($reports->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Report Type</th>
                                        <th>Doctor</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($report = $reports->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo date('M j, Y', strtotime($report['report_date'])); ?></td>
                                            <td><?php echo !empty($report['report_type']) ? sanitize($report['report_type']) : 'General'; ?></td>
                                            <td><?php echo !empty($report['doctor_name']) ? sanitize($report['doctor_name']) : 'N/A'; ?></td>
                                            <td>
                                                <a href="view_report.php?id=<?php echo $report['id']; ?>" class="btn btn-sm btn-info" title="View">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info mb-0">
                            <i class="fas fa-info-circle me-2"></i> No medical reports found for this patient.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Prescriptions Tab -->
        <div class="tab-pane fade <?php echo $activeTab === 'prescriptions' ? 'show active' : ''; ?>" id="prescriptions" role="tabpanel" aria-labelledby="prescriptions-tab">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Prescriptions</h5>
                    <a href="add_prescription.php?patient_id=<?php echo $patient_id; ?>" class="btn btn-sm btn-primary">
                        <i class="fas fa-plus me-1"></i>Add Prescription
                    </a>
                </div>
                <div class="card-body">
                    <?php if ($prescriptions->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Doctor</th>
                                        <th>Medication</th>
                                        <th>Dosage</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($prescription = $prescriptions->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo date('M j, Y', strtotime($prescription['prescription_date'])); ?></td>
                                            <td><?php echo !empty($prescription['doctor_name']) ? sanitize($prescription['doctor_name']) : 'N/A'; ?></td>
                                            <td><?php echo sanitize($prescription['medication']); ?></td>
                                            <td><?php echo !empty($prescription['dosage']) ? sanitize($prescription['dosage']) : 'N/A'; ?></td>
                                            <td>
                                                <a href="view_prescription.php?id=<?php echo $prescription['id']; ?>" class="btn btn-sm btn-info" title="View">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info mb-0">
                            <i class="fas fa-info-circle me-2"></i> No prescriptions found for this patient.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Treatments Tab -->
        <div class="tab-pane fade <?php echo $activeTab === 'treatments' ? 'show active' : ''; ?>" id="treatments" role="tabpanel" aria-labelledby="treatments-tab">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Nurse Treatments</h5>
                    <a href="add_treatment.php?patient_id=<?php echo $patient_id; ?>" class="btn btn-sm btn-primary">
                        <i class="fas fa-plus me-1"></i>Add Treatment
                    </a>
                </div>
                <div class="card-body">
                    <?php if ($treatments->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Treatment Type</th>
                                        <th>Nurse</th>
                                        <th>Notes</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($treatment = $treatments->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo date('M j, Y', strtotime($treatment['treatment_date'])); ?></td>
                                            <td><?php echo sanitize($treatment['treatment_type']); ?></td>
                                            <td><?php echo !empty($treatment['nurse_name']) ? sanitize($treatment['nurse_name']) : 'N/A'; ?></td>
                                            <td><?php echo !empty($treatment['notes']) ? substr(sanitize($treatment['notes']), 0, 50) . '...' : 'N/A'; ?></td>
                                            <td>
                                                <a href="view_treatment.php?id=<?php echo $treatment['id']; ?>" class="btn btn-sm btn-info" title="View">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info mb-0">
                            <i class="fas fa-info-circle me-2"></i> No treatments found for this patient.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Documents Tab -->
        <div class="tab-pane fade <?php echo $activeTab === 'documents' ? 'show active' : ''; ?>" id="documents" role="tabpanel" aria-labelledby="documents-tab">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Patient File Documents</h5>
                </div>
                <div class="card-body">
                    <?php if ($documentUploadSuccess): ?>
                        <div class="alert alert-success doc-alert-auto-dismiss">
                            <i class="fas fa-check-circle me-1"></i> Document uploaded successfully.
                        </div>
                    <?php endif; ?>
                    <?php if ($documentDeleteSuccess): ?>
                        <div class="alert alert-success doc-alert-auto-dismiss">
                            <i class="fas fa-trash-alt me-1"></i> Document deleted successfully.
                        </div>
                    <?php endif; ?>
                    <?php if ($documentUploadError !== ''): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle me-1"></i> <?php echo htmlspecialchars($documentUploadError); ?>
                        </div>
                    <?php endif; ?>

                    <div class="alert alert-info border d-flex align-items-start gap-3">
                        <i class="fas fa-print mt-1"></i>
                        <div>
                            <div class="fw-semibold">Quick Scan Workflow</div>
                            <small>
                                1) Scan from clinic scanner to computer (PDF or image) →
                                2) Open this patient's <strong>Documents</strong> tab →
                                3) Upload with category and optional expiry/reminder.
                            </small>
                        </div>
                    </div>

                    <form action="view_patient.php?id=<?php echo $patient_id; ?>&tab=documents" method="POST" enctype="multipart/form-data" class="border rounded p-3 mb-4 bg-light">
                        <input type="hidden" name="action" value="upload_patient_document">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Document Title</label>
                                <input type="text" name="document_title" class="form-control" maxlength="255" placeholder="e.g. Consent Form" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Category</label>
                                <select name="document_category" class="form-select" required>
                                    <?php foreach ($documentCategoryOptions as $categoryValue => $categoryLabel): ?>
                                        <option value="<?php echo htmlspecialchars($categoryValue); ?>"><?php echo htmlspecialchars($categoryLabel); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">File (PDF or Image)</label>
                                <input type="file" name="document_file" class="form-control" accept="application/pdf,image/*" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Expiry Date (Optional)</label>
                                <input type="date" name="document_expiry_date" class="form-control">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Reminder Days Before</label>
                                <input type="number" name="reminder_days_before" class="form-control" min="0" max="3650" value="0">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Notes (Optional)</label>
                                <textarea name="document_notes" class="form-control" rows="2" maxlength="2000" placeholder="Any note about this file"></textarea>
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-upload me-1"></i>Upload Document
                                </button>
                                <small class="text-muted ms-2">Allowed: PDF, JPG, PNG, WEBP, GIF, TIFF (max 15MB)</small>
                            </div>
                        </div>
                    </form>

                    <form action="" method="GET" class="row g-2 mb-3">
                        <input type="hidden" name="id" value="<?php echo $patient_id; ?>">
                        <input type="hidden" name="tab" value="documents">
                        <div class="col-md-3">
                            <select name="doc_category" class="form-select">
                                <option value="">All Categories</option>
                                <?php foreach ($documentCategoryLookup as $categoryValue => $categoryLabel): ?>
                                    <option value="<?php echo htmlspecialchars($categoryValue); ?>" <?php echo $docCategoryFilter === $categoryValue ? 'selected' : ''; ?>><?php echo htmlspecialchars($categoryLabel); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select name="doc_file_type" class="form-select">
                                <option value="">All File Types</option>
                                <option value="pdf" <?php echo $docFileTypeFilter === 'pdf' ? 'selected' : ''; ?>>PDF</option>
                                <option value="image" <?php echo $docFileTypeFilter === 'image' ? 'selected' : ''; ?>>Images</option>
                            </select>
                        </div>
                        <div class="col-md-5">
                            <input type="text" name="doc_search" class="form-control" value="<?php echo htmlspecialchars($docSearch); ?>" placeholder="Search title, file name, or notes">
                        </div>
                        <div class="col-md-2 d-flex gap-2">
                            <button type="submit" class="btn btn-outline-primary w-100">Filter</button>
                            <a href="view_patient.php?id=<?php echo $patient_id; ?>&tab=documents" class="btn btn-outline-secondary">Reset</a>
                        </div>
                    </form>

                    <?php if (!empty($patientDocuments)): ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th>Title</th>
                                        <th>Category</th>
                                        <th>Type</th>
                                        <th>Expiry</th>
                                        <th>Reminder</th>
                                        <th>Uploaded By</th>
                                        <th>Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($patientDocuments as $document): ?>
                                        <?php
                                        $docId = (int)$document['id'];
                                        $docCategory = (string)($document['document_category'] ?? 'other');
                                        $docCategoryLabel = $documentCategoryLookup[$docCategory] ?? ucwords(str_replace('_', ' ', $docCategory));
                                        $docMime = (string)($document['file_mime'] ?? '');
                                        $isImageDoc = patientDocumentIsImageMime($docMime);
                                        $expiryDateRaw = (string)($document['expiry_date'] ?? '');
                                        $reminderDays = (int)($document['reminder_days_before'] ?? 0);
                                        $expiryLabel = 'No expiry';
                                        $expiryBadgeClass = 'bg-secondary';

                                        if ($expiryDateRaw !== '' && $expiryDateRaw !== '0000-00-00') {
                                            $expiryDateObj = date_create($expiryDateRaw);
                                            if ($expiryDateObj) {
                                                $todayDateObj = new DateTime('today');
                                                $daysToExpiry = (int)$todayDateObj->diff($expiryDateObj)->format('%r%a');
                                                if ($daysToExpiry < 0) {
                                                    $expiryLabel = 'Expired ' . abs($daysToExpiry) . ' day(s) ago';
                                                    $expiryBadgeClass = 'bg-danger';
                                                } elseif ($daysToExpiry === 0) {
                                                    $expiryLabel = 'Expires today';
                                                    $expiryBadgeClass = 'bg-warning text-dark';
                                                } else {
                                                    $expiryLabel = 'In ' . $daysToExpiry . ' day(s)';
                                                    $expiryBadgeClass = $reminderDays > 0 && $daysToExpiry <= $reminderDays
                                                        ? 'bg-warning text-dark'
                                                        : 'bg-success';
                                                }
                                            }
                                        }
                                        ?>
                                        <tr>
                                            <td>
                                                <div class="fw-semibold"><?php echo htmlspecialchars((string)$document['document_title']); ?></div>
                                                <small class="text-muted"><?php echo htmlspecialchars((string)$document['file_name']); ?></small>
                                                <?php if (!empty($document['notes'])): ?>
                                                    <div class="small mt-1"><?php echo nl2br(htmlspecialchars((string)$document['notes'])); ?></div>
                                                <?php endif; ?>
                                            </td>
                                            <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($docCategoryLabel); ?></span></td>
                                            <td>
                                                <?php if ($isImageDoc): ?>
                                                    <span class="badge bg-info text-dark">Image</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">PDF</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge <?php echo $expiryBadgeClass; ?>"><?php echo htmlspecialchars($expiryLabel); ?></span>
                                            </td>
                                            <td>
                                                <?php if ($reminderDays > 0): ?>
                                                    <span class="badge bg-light text-dark border"><?php echo $reminderDays; ?> day(s)</span>
                                                <?php else: ?>
                                                    <span class="text-muted small">Not set</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars((string)($document['uploaded_by_name'] ?? 'System')); ?></td>
                                            <td><?php echo date('M j, Y g:i A', strtotime((string)$document['created_at'])); ?></td>
                                            <td>
                                                <a class="btn btn-sm btn-outline-primary" href="patient_document_download.php?id=<?php echo $docId; ?>&mode=inline" target="_blank">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a class="btn btn-sm btn-outline-secondary" href="patient_document_download.php?id=<?php echo $docId; ?>&mode=download">
                                                    <i class="fas fa-download"></i>
                                                </a>
                                                <?php if (canDeletePatients()): ?>
                                                    <form method="POST" action="view_patient.php?id=<?php echo $patient_id; ?>&tab=documents" class="d-inline" onsubmit="return confirm('Delete this document? This action cannot be undone.');">
                                                        <input type="hidden" name="action" value="delete_patient_document">
                                                        <input type="hidden" name="document_id" value="<?php echo $docId; ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete Document">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <?php if ($docTotalPages > 1): ?>
                            <nav aria-label="Documents pagination" class="mt-3">
                                <ul class="pagination pagination-sm mb-0">
                                    <?php
                                    $docPageWindowStart = max(1, $docCurrentPage - 2);
                                    $docPageWindowEnd = min($docTotalPages, $docCurrentPage + 2);
                                    ?>
                                    <li class="page-item <?php echo $docCurrentPage <= 1 ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="view_patient.php?id=<?php echo $patient_id; ?>&tab=documents&doc_page=<?php echo max(1, $docCurrentPage - 1); ?>&doc_category=<?php echo urlencode($docCategoryFilter); ?>&doc_file_type=<?php echo urlencode($docFileTypeFilter); ?>&doc_search=<?php echo urlencode($docSearch); ?>">Previous</a>
                                    </li>
                                    <?php for ($p = $docPageWindowStart; $p <= $docPageWindowEnd; $p++): ?>
                                        <li class="page-item <?php echo $p === $docCurrentPage ? 'active' : ''; ?>">
                                            <a class="page-link" href="view_patient.php?id=<?php echo $patient_id; ?>&tab=documents&doc_page=<?php echo $p; ?>&doc_category=<?php echo urlencode($docCategoryFilter); ?>&doc_file_type=<?php echo urlencode($docFileTypeFilter); ?>&doc_search=<?php echo urlencode($docSearch); ?>"><?php echo $p; ?></a>
                                        </li>
                                    <?php endfor; ?>
                                    <li class="page-item <?php echo $docCurrentPage >= $docTotalPages ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="view_patient.php?id=<?php echo $patient_id; ?>&tab=documents&doc_page=<?php echo min($docTotalPages, $docCurrentPage + 1); ?>&doc_category=<?php echo urlencode($docCategoryFilter); ?>&doc_file_type=<?php echo urlencode($docFileTypeFilter); ?>&doc_search=<?php echo urlencode($docSearch); ?>">Next</a>
                                    </li>
                                </ul>
                            </nav>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="alert alert-info mb-0">
                            <i class="fas fa-info-circle me-2"></i>No patient documents found for current filters.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript Libraries -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    (function handleDocumentSuccessAlerts() {
        var alerts = document.querySelectorAll('.doc-alert-auto-dismiss');
        if (!alerts.length) {
            return;
        }

        setTimeout(function() {
            alerts.forEach(function(alertEl) {
                alertEl.style.transition = 'opacity 0.3s ease';
                alertEl.style.opacity = '0';
                setTimeout(function() {
                    if (alertEl && alertEl.parentNode) {
                        alertEl.parentNode.removeChild(alertEl);
                    }
                }, 300);
            });
        }, 5000);

        try {
            var currentUrl = new URL(window.location.href);
            if (currentUrl.searchParams.has('uploaded') || currentUrl.searchParams.has('deleted')) {
                currentUrl.searchParams.delete('uploaded');
                currentUrl.searchParams.delete('deleted');
                window.history.replaceState({}, document.title, currentUrl.toString());
            }
        } catch (e) {
            // Silently ignore URL API issues on older browsers.
        }
    })();
</script>

<?php
// Function to calculate age from date of birth
function calculateAge($dob) {
    $birthDate = new DateTime($dob);
    $today = new DateTime('today');
    $age = $birthDate->diff($today)->y;
    return $age;
}
?>
