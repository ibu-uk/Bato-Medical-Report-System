<?php
session_start();

require_once 'config/database.php';
require_once 'config/auth.php';
require_once 'config/timezone.php';
require_once 'config/patient_documents.php';

requireLogin();
if (!hasRole(['admin'])) {
    header('Location: dashboard.php');
    exit;
}

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

if (!ensurePatientDocumentCategoryTable($conn)) {
    die('Could not initialize document categories table.');
}

function normalizeCategoryKey($text) {
    $key = strtolower(trim((string)$text));
    $key = preg_replace('/[^a-z0-9]+/', '_', $key);
    $key = trim((string)$key, '_');
    if ($key === '') {
        $key = 'category';
    }
    return substr($key, 0, 50);
}

$message = '';
$messageType = 'success';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = trim((string)($_POST['action'] ?? ''));

    if ($action === 'create') {
        $categoryLabel = trim((string)($_POST['category_label'] ?? ''));
        $categoryKeyInput = trim((string)($_POST['category_key'] ?? ''));
        $sortOrder = isset($_POST['sort_order']) ? (int)$_POST['sort_order'] : 0;
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        if ($categoryLabel === '') {
            $message = 'Category label is required.';
            $messageType = 'danger';
        } else {
            $categoryKey = normalizeCategoryKey($categoryKeyInput !== '' ? $categoryKeyInput : $categoryLabel);

            $checkStmt = $conn->prepare('SELECT id FROM document_categories WHERE category_key = ? LIMIT 1');
            $checkStmt->bind_param('s', $categoryKey);
            $checkStmt->execute();
            $exists = $checkStmt->get_result()->num_rows > 0;
            $checkStmt->close();

            if ($exists) {
                $message = 'Category key already exists. Use another key.';
                $messageType = 'danger';
            } else {
                $insertStmt = $conn->prepare('INSERT INTO document_categories (category_key, category_label, is_active, sort_order) VALUES (?, ?, ?, ?)');
                $insertStmt->bind_param('ssii', $categoryKey, $categoryLabel, $isActive, $sortOrder);
                if ($insertStmt->execute()) {
                    $_SESSION['doc_category_flash'] = ['type' => 'success', 'text' => 'Document category added successfully.'];
                    $insertStmt->close();
                    header('Location: manage_document_categories.php');
                    exit;
                }
                $insertStmt->close();
                $message = 'Failed to add document category.';
                $messageType = 'danger';
            }
        }
    }

    if ($action === 'update') {
        $categoryId = isset($_POST['category_id']) ? (int)$_POST['category_id'] : 0;
        $categoryLabel = trim((string)($_POST['category_label'] ?? ''));
        $sortOrder = isset($_POST['sort_order']) ? (int)$_POST['sort_order'] : 0;
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        if ($categoryId <= 0 || $categoryLabel === '') {
            $message = 'Invalid update request.';
            $messageType = 'danger';
        } else {
            $updateStmt = $conn->prepare('UPDATE document_categories SET category_label = ?, sort_order = ?, is_active = ? WHERE id = ?');
            $updateStmt->bind_param('siii', $categoryLabel, $sortOrder, $isActive, $categoryId);
            if ($updateStmt->execute()) {
                $_SESSION['doc_category_flash'] = ['type' => 'success', 'text' => 'Document category updated successfully.'];
                $updateStmt->close();
                header('Location: manage_document_categories.php');
                exit;
            }
            $updateStmt->close();
            $message = 'Failed to update document category.';
            $messageType = 'danger';
        }
    }

    if ($action === 'toggle_status') {
        $categoryId = isset($_POST['category_id']) ? (int)$_POST['category_id'] : 0;
        $newStatus = isset($_POST['new_status']) && (int)$_POST['new_status'] === 1 ? 1 : 0;

        if ($categoryId > 0) {
            $toggleStmt = $conn->prepare('UPDATE document_categories SET is_active = ? WHERE id = ?');
            $toggleStmt->bind_param('ii', $newStatus, $categoryId);
            $toggleStmt->execute();
            $toggleStmt->close();
            $_SESSION['doc_category_flash'] = ['type' => 'success', 'text' => 'Category status updated.'];
            header('Location: manage_document_categories.php');
            exit;
        }
    }

    if ($action === 'delete') {
        $categoryId = isset($_POST['category_id']) ? (int)$_POST['category_id'] : 0;

        if ($categoryId > 0) {
            $findStmt = $conn->prepare('SELECT id, category_key, category_label FROM document_categories WHERE id = ? LIMIT 1');
            $findStmt->bind_param('i', $categoryId);
            $findStmt->execute();
            $categoryRow = $findStmt->get_result()->fetch_assoc();
            $findStmt->close();

            if (!$categoryRow) {
                $_SESSION['doc_category_flash'] = ['type' => 'danger', 'text' => 'Category not found.'];
                header('Location: manage_document_categories.php');
                exit;
            }

            $categoryKey = (string)$categoryRow['category_key'];
            $categoryLabel = (string)$categoryRow['category_label'];

            if ($categoryKey === 'other') {
                $_SESSION['doc_category_flash'] = ['type' => 'danger', 'text' => 'The default "Other" category cannot be deleted.'];
                header('Location: manage_document_categories.php');
                exit;
            }

            $usageStmt = $conn->prepare('SELECT COUNT(*) AS total FROM patient_documents WHERE document_category = ?');
            $usageStmt->bind_param('s', $categoryKey);
            $usageStmt->execute();
            $usageResult = $usageStmt->get_result();
            $usageRow = $usageResult ? $usageResult->fetch_assoc() : null;
            $usageStmt->close();
            $usageCount = (int)($usageRow['total'] ?? 0);

            if ($usageCount > 0) {
                $_SESSION['doc_category_flash'] = [
                    'type' => 'danger',
                    'text' => 'Cannot delete "' . $categoryLabel . '" because it is already used by ' . $usageCount . ' document(s).'
                ];
                header('Location: manage_document_categories.php');
                exit;
            }

            $deleteStmt = $conn->prepare('DELETE FROM document_categories WHERE id = ? LIMIT 1');
            $deleteStmt->bind_param('i', $categoryId);
            $deleted = $deleteStmt->execute();
            $deleteStmt->close();

            if ($deleted) {
                $_SESSION['doc_category_flash'] = ['type' => 'success', 'text' => 'Category deleted successfully.'];
            } else {
                $_SESSION['doc_category_flash'] = ['type' => 'danger', 'text' => 'Failed to delete category.'];
            }

            header('Location: manage_document_categories.php');
            exit;
        }
    }
}

if (isset($_SESSION['doc_category_flash']) && is_array($_SESSION['doc_category_flash'])) {
    $message = (string)($_SESSION['doc_category_flash']['text'] ?? '');
    $messageType = (string)($_SESSION['doc_category_flash']['type'] ?? 'success');
    unset($_SESSION['doc_category_flash']);
}

$editId = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
$editCategory = null;
if ($editId > 0) {
    $editStmt = $conn->prepare('SELECT * FROM document_categories WHERE id = ? LIMIT 1');
    $editStmt->bind_param('i', $editId);
    $editStmt->execute();
    $editCategory = $editStmt->get_result()->fetch_assoc();
    $editStmt->close();
}

$categories = [];
$listResult = $conn->query('SELECT id, category_key, category_label, is_active, sort_order, updated_at FROM document_categories ORDER BY sort_order ASC, category_label ASC');
if ($listResult) {
    while ($row = $listResult->fetch_assoc()) {
        $categories[] = $row;
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Document Categories - Bato Medical Report System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
</head>
<body>
    <?php include_once 'includes/sidebar.php'; ?>

    <nav class="top-navbar">
        <div class="container-fluid">
            <div class="d-flex align-items-center ms-auto">
                <div class="dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-user-circle me-1"></i>
                        <span class="d-none d-md-inline"><?php echo isset($_SESSION['full_name']) ? htmlspecialchars($_SESSION['full_name']) : ''; ?></span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="profile.php"><i class="fas fa-id-card me-2"></i>Profile</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <div class="main-content">
        <div class="container-fluid py-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="mb-0">Manage Document Categories</h2>
                <a href="patient_list.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-1"></i> Back to Patients
                </a>
            </div>

            <?php if ($message !== ''): ?>
                <div class="alert alert-<?php echo htmlspecialchars($messageType); ?> alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <div class="row g-4">
                <div class="col-lg-5">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><?php echo $editCategory ? 'Edit Category' : 'Add Category'; ?></h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="manage_document_categories.php<?php echo $editCategory ? '?edit=' . (int)$editCategory['id'] : ''; ?>">
                                <input type="hidden" name="action" value="<?php echo $editCategory ? 'update' : 'create'; ?>">
                                <?php if ($editCategory): ?>
                                    <input type="hidden" name="category_id" value="<?php echo (int)$editCategory['id']; ?>">
                                <?php endif; ?>

                                <div class="mb-3">
                                    <label class="form-label">Category Label</label>
                                    <input type="text" name="category_label" class="form-control" required maxlength="100" value="<?php echo htmlspecialchars((string)($editCategory['category_label'] ?? '')); ?>">
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Category Key <?php if (!$editCategory): ?><small class="text-muted">(optional)</small><?php endif; ?></label>
                                    <input type="text" name="category_key" class="form-control" maxlength="50" value="<?php echo htmlspecialchars((string)($editCategory['category_key'] ?? '')); ?>" <?php echo $editCategory ? 'readonly' : ''; ?> placeholder="e.g. treatment_contract">
                                    <small class="text-muted">Lowercase letters, numbers, and underscore.</small>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Sort Order</label>
                                    <input type="number" name="sort_order" class="form-control" value="<?php echo htmlspecialchars((string)($editCategory['sort_order'] ?? '0')); ?>">
                                </div>

                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="checkbox" name="is_active" id="isActive" <?php echo !isset($editCategory['is_active']) || (int)$editCategory['is_active'] === 1 ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="isActive">Active</label>
                                </div>

                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save me-1"></i> <?php echo $editCategory ? 'Update' : 'Add'; ?> Category
                                    </button>
                                    <?php if ($editCategory): ?>
                                        <a href="manage_document_categories.php" class="btn btn-outline-secondary">Cancel</a>
                                    <?php endif; ?>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-lg-7">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Category List</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle">
                                    <thead>
                                        <tr>
                                            <th>Label</th>
                                            <th>Key</th>
                                            <th>Order</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!empty($categories)): ?>
                                            <?php foreach ($categories as $category): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars((string)$category['category_label']); ?></td>
                                                    <td><code><?php echo htmlspecialchars((string)$category['category_key']); ?></code></td>
                                                    <td><?php echo (int)$category['sort_order']; ?></td>
                                                    <td>
                                                        <?php if ((int)$category['is_active'] === 1): ?>
                                                            <span class="badge bg-success">Active</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-secondary">Inactive</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <a href="manage_document_categories.php?edit=<?php echo (int)$category['id']; ?>" class="btn btn-sm btn-outline-primary" title="Edit">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                        <form method="POST" action="manage_document_categories.php" class="d-inline">
                                                            <input type="hidden" name="action" value="toggle_status">
                                                            <input type="hidden" name="category_id" value="<?php echo (int)$category['id']; ?>">
                                                            <input type="hidden" name="new_status" value="<?php echo (int)$category['is_active'] === 1 ? '0' : '1'; ?>">
                                                            <button type="submit" class="btn btn-sm <?php echo (int)$category['is_active'] === 1 ? 'btn-outline-warning' : 'btn-outline-success'; ?>" title="<?php echo (int)$category['is_active'] === 1 ? 'Deactivate' : 'Activate'; ?>">
                                                                <i class="fas <?php echo (int)$category['is_active'] === 1 ? 'fa-toggle-off' : 'fa-toggle-on'; ?>"></i>
                                                            </button>
                                                        </form>
                                                        <form method="POST" action="manage_document_categories.php" class="d-inline" onsubmit="return confirm('Delete this category? This action cannot be undone.');">
                                                            <input type="hidden" name="action" value="delete">
                                                            <input type="hidden" name="category_id" value="<?php echo (int)$category['id']; ?>">
                                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="5" class="text-center text-muted">No categories found.</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
