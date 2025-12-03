<?php
// Start session
session_start();
require_once 'config/database.php';
global $conn;
if (!isset($conn) || !$conn) {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        die("Database connection failed: " . $conn->connect_error);
    }
}
$conn->autocommit(true);
require_once 'config/auth.php';

// Only admin or doctor can access
if (!hasRole(['admin', 'doctor'])) {
    header('Location: reports.php');
    exit;
}

// Get report ID
if (!isset($_GET['id'])) {
    header('Location: reports.php');
    exit;
}
$report_id = intval($_GET['id']);

// Fetch report
$query = "SELECT * FROM reports WHERE id = $report_id";
$result = executeQuery($query);
if (!$result || $result->num_rows == 0) {
    $_SESSION['error'] = "Report not found.";
    header('Location: reports.php');
    exit;
}
$report = $result->fetch_assoc();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // DEBUG: Print POSTed test_row array (remove after debugging)


    $report_date = sanitize($_POST['report_date']);
    $patient_id = intval($_POST['patient_id']);
    $doctor_id = intval($_POST['doctor_id']);
    $conclusion = isset($_POST['conclusion']) ? sanitize($_POST['conclusion']) : null;
    $update_query = "UPDATE reports SET report_date='$report_date', patient_id=$patient_id, doctor_id=$doctor_id, conclusion=" . ($conclusion !== null ? "'" . $conclusion . "'" : "NULL") . " WHERE id=$report_id";
    if (executeQuery($update_query)) {
    // Fetch patient name for logging
    $patient_name = '';
    $stmt_p = $conn->prepare("SELECT name FROM patients WHERE id = ? LIMIT 1");
    if ($stmt_p) {
        $stmt_p->bind_param("i", $patient_id);
        $stmt_p->execute();
        $stmt_p->bind_result($patient_name_result);
        if ($stmt_p->fetch()) {
            $patient_name = $patient_name_result;
        }
        $stmt_p->close();
    }
    // Log activity
    logUserActivity('edit_report', $report_id, null, $patient_name);
        // Handle test results
        $test_rows = isset($_POST['test_row']) ? $_POST['test_row'] : [];
        $existing_ids = [];
        foreach ($test_rows as $row) {
            $test_type_id = intval($row['test_type_id']);
            $test_value = sanitize($row['test_value']);
            $flag = isset($row['flag']) ? sanitize($row['flag']) : '';
            if (!empty($row['report_test_id'])) {
                // Update existing
                $report_test_id = intval($row['report_test_id']);
                $existing_ids[] = $report_test_id;
                $update_sql = "UPDATE report_tests SET test_type_id=$test_type_id, test_value='$test_value', flag='$flag' WHERE id=$report_test_id";
                $conn->autocommit(true);
                if (!executeQuery($update_sql)) {
                    echo '<pre style="color:red;">UPDATE ERROR: '.mysqli_error($conn).'\nSQL: '.$update_sql.'</pre>';
                }
            } else {
                // Insert new
                $insert_sql = "INSERT INTO report_tests (report_id, test_type_id, test_value, flag) VALUES ($report_id, $test_type_id, '$test_value', '$flag')";
                $conn->autocommit(true);
                if (executeQuery($insert_sql)) {
                    $new_id = mysqli_insert_id($conn);
                    
                    if ($new_id) {
                        $existing_ids[] = $new_id;
                    } else {
                        // Fallback: fetch the latest inserted test row for this report and test_type_id
                        $fallback_sql = "SELECT id FROM report_tests WHERE report_id=$report_id AND test_type_id=$test_type_id ORDER BY id DESC LIMIT 1";
                        $fallback_result = executeQuery($fallback_sql);
                        if ($fallback_result && $fallback_result->num_rows > 0) {
                            $fallback_row = $fallback_result->fetch_assoc();
                            $existing_ids[] = intval($fallback_row['id']);
                            
                        } else {
                            
                        }
                    }
                } else {
                    echo '<pre style="color:red;">INSERT ERROR: '.mysqli_error($conn).'\nSQL: '.$insert_sql.'</pre>';
                }
            }
        }
        // Delete removed test results
        if (count($existing_ids) > 0) {
            $ids_str = implode(',', array_map('intval', $existing_ids));
            executeQuery("DELETE FROM report_tests WHERE report_id=$report_id AND id NOT IN ($ids_str)");
        } else {
            executeQuery("DELETE FROM report_tests WHERE report_id=$report_id");
        }
        $_SESSION['success'] = "Report updated successfully.";
        header('Location: view_report.php?id=' . $report_id);
        exit;
    } else {
        $error = "Failed to update report. ";
        $error .= "<br>Submitted date: " . htmlspecialchars($report_date);
        $error .= "<br>SQL: " . htmlspecialchars($update_query);
    }
}

// Fetch patients and doctors for dropdowns
$patients = executeQuery("SELECT id, name FROM patients ORDER BY name");
$doctors = executeQuery("SELECT id, name FROM doctors ORDER BY name");

// Fetch existing test results for this report
$test_results = [];
$tests_query = "SELECT rt.id as report_test_id, rt.test_type_id, rt.test_value, tt.name as test_name, tt.unit, tt.normal_range FROM report_tests rt JOIN test_types tt ON rt.test_type_id = tt.id WHERE rt.report_id = $report_id";
$tests_result = executeQuery($tests_query);
if ($tests_result && $tests_result->num_rows > 0) {
    while ($row = $tests_result->fetch_assoc()) {
        $test_results[] = $row;
    }
} elseif ($tests_result === false) {
    $error = 'Failed to fetch test results.';
}

// Fetch all test types for dropdowns
$all_test_types = [];
$test_types_query = "SELECT id, name, unit, normal_range FROM test_types ORDER BY name";
$test_types_result = executeQuery($test_types_query);
if ($test_types_result && $test_types_result->num_rows > 0) {
    while ($row = $test_types_result->fetch_assoc()) {
        $all_test_types[] = $row;
    }
} elseif ($test_types_result === false) {
    $error = 'Failed to fetch test types.';
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Report</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-4">
    <h2>Edit Medical Report</h2>
    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>
    <form method="POST">
        <div class="mb-3">
            <label for="report_date" class="form-label">Report Date</label>
            <input type="date" class="form-control" id="report_date" name="report_date" value="<?php echo htmlspecialchars($report['report_date']); ?>" required>
        </div>
        <div class="mb-3">
            <label for="patient_id" class="form-label">Patient</label>
            <select class="form-control" id="patient_id" name="patient_id" required>
                <?php while ($p = $patients->fetch_assoc()): ?>
                    <option value="<?php echo $p['id']; ?>" <?php if ($p['id'] == $report['patient_id']) echo 'selected'; ?>><?php echo htmlspecialchars($p['name']); ?></option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="mb-3">
            <label for="doctor_id" class="form-label">Doctor</label>
            <select class="form-control" id="doctor_id" name="doctor_id" required>
                <?php while ($d = $doctors->fetch_assoc()): ?>
                    <option value="<?php echo $d['id']; ?>" <?php if ($d['id'] == $report['doctor_id']) echo 'selected'; ?>><?php echo htmlspecialchars($d['name']); ?></option>
                <?php endwhile; ?>
            </select>
        </div>

        <!-- Test Results Section -->
        <h5 class="mt-4">Test Results</h5>
        <button type="button" id="add-test-row" class="btn btn-success mb-2"><i class="fa fa-plus"></i> Add Test</button>
        <table class="table table-bordered" id="test-results-table">
            <thead>
                <tr>
                    <th style="width: 30%">Test Name</th>
                    <th style="width: 20%">Result</th>
                    <th style="width: 10%">Flag</th>
                    <th style="width: 15%">Unit</th>
                    <th style="width: 25%">Reference Range</th>
                    <th style="width: 10%"></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($test_results as $idx => $tr): ?>
                <tr>
                    <td>
                        <input type="hidden" name="test_row[<?php echo $idx; ?>][report_test_id]" value="<?php echo $tr['report_test_id']; ?>">
                        <select class="form-select test-type-select" name="test_row[<?php echo $idx; ?>][test_type_id]" required>
                            <option value="">Select Test</option>
                            <?php foreach ($all_test_types as $tt): ?>
                                <option value="<?php echo $tt['id']; ?>" data-unit="<?php echo htmlspecialchars($tt['unit']); ?>" data-range="<?php echo htmlspecialchars($tt['normal_range']); ?>" <?php if ((string)$tt['id'] === (string)$tr['test_type_id']) echo 'selected'; ?>><?php echo htmlspecialchars($tt['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td><input type="text" class="form-control" name="test_row[<?php echo $idx; ?>][test_value]" value="<?php echo htmlspecialchars($tr['test_value']); ?>" required></td>
                    <td>
                        <select class="form-select" name="test_row[<?php echo $idx; ?>][flag]">
                            <option value="" <?php if (empty($tr['flag'])) echo 'selected'; ?>>Normal</option>
                            <option value="High" <?php if ($tr['flag'] === 'High') echo 'selected'; ?>>High</option>
                            <option value="Low" <?php if ($tr['flag'] === 'Low') echo 'selected'; ?>>Low</option>
                        </select>
                    </td>
                    <td><input type="text" class="form-control unit-field" value="<?php echo htmlspecialchars($tr['unit']); ?>" readonly></td>
                    <td><input type="text" class="form-control range-field" value="<?php echo htmlspecialchars($tr['normal_range']); ?>" readonly></td>
                    <td><button type="button" class="btn btn-danger btn-sm remove-test-row"><i class="fa fa-trash"></i></button></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <!-- Conclusion Section -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Conclusion / Doctor's Final Notes</h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label for="conclusion" class="form-label">Conclusion / Doctor's Final Notes</label>
                    <textarea class="form-control" id="conclusion" name="conclusion" rows="4" placeholder="Enter summary, interpretation, or final notes for this report..."><?php echo htmlspecialchars($report['conclusion'] ?? ''); ?></textarea>
                </div>
            </div>
        </div>

        <button type="submit" class="btn btn-primary">Save Changes</button>
        <a href="reports.php" class="btn btn-secondary">Cancel</a>
    </form>

    <!-- Template row for JS cloning (hidden) -->
    <table style="display:none"><tbody>
        <tr id="test-row-template">
            <td>
                <input type="hidden" name="test_row[TEMPLATE][report_test_id]" value="">
                <select class="form-select test-type-select" name="test_row[TEMPLATE][test_type_id]" required>
                    <option value="">Select Test</option>
                    <?php foreach ($all_test_types as $tt): ?>
                        <option value="<?php echo $tt['id']; ?>" data-unit="<?php echo htmlspecialchars($tt['unit']); ?>" data-range="<?php echo htmlspecialchars($tt['normal_range']); ?>"><?php echo htmlspecialchars($tt['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </td>
            <td><input type="text" class="form-control" name="test_row[TEMPLATE][test_value]" value="" required></td>
            <td>
                <select class="form-select" name="test_row[TEMPLATE][flag]">
                    <option value="">Normal</option>
                    <option value="High">High</option>
                    <option value="Low">Low</option>
                </select>
            </td>
            <td><input type="text" class="form-control unit-field" value="" readonly></td>
            <td><input type="text" class="form-control range-field" value="" readonly></td>
            <td><button type="button" class="btn btn-danger btn-sm remove-test-row"><i class="fa fa-trash"></i></button></td>
        </tr>
    </tbody></table>

</div>
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
    $(document).ready(function() {
        // Add new test row
        let rowIdx = $('#test-results-table tbody tr').length || <?php echo count($test_results); ?>;
        $('#add-test-row').click(function() {
            let $tpl = $('#test-row-template').clone().removeAttr('id');
let html = $tpl.html().replace(/test_row\[TEMPLATE\]/g, 'test_row['+rowIdx+']');
// Ensure flag select is present
if (html.indexOf('name="test_row['+rowIdx+'][flag]"') === -1) {
    html = html.replace('</td>', '<select class="form-select" name="test_row['+rowIdx+'][flag]"><option value="">Normal</option><option value="High">High</option><option value="Low">Low</option></select></td>');
}
let $row = $('<tr>' + html + '</tr>');
$('#test-results-table tbody').append($row);
            // When user selects a test, autofill unit/range
            $row.find('.test-type-select').on('change', function() {
                var $select = $(this);
                var unit = $select.find('option:selected').data('unit') || '';
                var range = $select.find('option:selected').data('range') || '';
                $select.closest('tr').find('.unit-field').val(unit);
                $select.closest('tr').find('.range-field').val(range);
            });
            // Trigger change to autofill if a value is already selected
            $row.find('.test-type-select').trigger('change');
            rowIdx++;
        });
        // Remove test row
        $(document).on('click', '.remove-test-row', function() {
            $(this).closest('tr').remove();
        });
        // Auto-fill unit and range on test type change (event delegation for all current and future rows)
        $(document).on('change', '.test-type-select', function() {
            var $select = $(this);
            var unit = $select.find('option:selected').data('unit') || '';
            var range = $select.find('option:selected').data('range') || '';
            $select.closest('tr').find('.unit-field').val(unit);
            $select.closest('tr').find('.range-field').val(range);
        });
        // Trigger autofill for existing rows on load
        $('.test-type-select').each(function() {
            $(this).trigger('change');
        });
    });
    </script>
</body>
</html>
