<?php
// Include database configuration
require_once 'config/database.php';

// Start session if needed
session_start();

// Check if form is submitted for adding or updating test type
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Sanitize input data
    $name = sanitize($_POST['name']);
    $unit = sanitize($_POST['unit']);
    $normal_range = sanitize($_POST['normal_range']);
    
    // Check if we're updating an existing test type or adding a new one
    if (isset($_POST['test_id']) && !empty($_POST['test_id'])) {
        // Update existing test type
        $test_id = sanitize($_POST['test_id']);
        $query = "UPDATE test_types SET name = '$name', unit = '$unit', normal_range = '$normal_range' WHERE id = '$test_id'";
        $result = executeQuery($query);
        
        $message = $result ? "Test type updated successfully" : "Error updating test type";
    } else {
        // Add new test type
        $query = "INSERT INTO test_types (name, unit, normal_range) VALUES ('$name', '$unit', '$normal_range')";
        $result = executeInsert($query);
        
        $message = $result ? "Test type added successfully" : "Error adding test type";
    }
}

// Check if edit request is made
$edit_mode = false;
$test_to_edit = null;
if (isset($_GET['edit']) && !empty($_GET['edit'])) {
    $edit_mode = true;
    $test_id = sanitize($_GET['edit']);
    
    // Get test details
    $query = "SELECT id, name, unit, normal_range FROM test_types WHERE id = '$test_id'";
    $result = executeQuery($query);
    
    if ($result && $result->num_rows > 0) {
        $test_to_edit = $result->fetch_assoc();
    }
}

// Check if delete request is made
if (isset($_GET['delete']) && !empty($_GET['delete'])) {
    $test_id = sanitize($_GET['delete']);
    
    // Delete test type
    $query = "DELETE FROM test_types WHERE id = '$test_id'";
    $result = executeQuery($query);
    
    $message = $result ? "Test type deleted successfully" : "Error deleting test type";
}

// Handle search functionality
$search_term = '';
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search_term = sanitize($_GET['search']);
    $query = "SELECT id, name, unit, normal_range FROM test_types 
             WHERE name LIKE '%$search_term%' 
             OR unit LIKE '%$search_term%' 
             OR normal_range LIKE '%$search_term%' 
             ORDER BY name";
} else {
    // Get all test types if no search
    $query = "SELECT id, name, unit, normal_range FROM test_types ORDER BY name";
}
$result = executeQuery($query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Test Types - Bato Medical Report System</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            line-height: 1.6;
        }
        h1, h2 {
            color: #0066cc;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border: 1px solid transparent;
            border-radius: 4px;
        }
        .alert-info {
            color: #31708f;
            background-color: #d9edf7;
            border-color: #bce8f1;
        }
        .card {
            border: 1px solid #ddd;
            border-radius: 4px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .card-body {
            padding: 20px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-control {
            display: block;
            width: 100%;
            padding: 8px 12px;
            font-size: 14px;
            line-height: 1.42857143;
            color: #555;
            background-color: #fff;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
        }
        .btn {
            display: inline-block;
            margin-bottom: 0;
            font-weight: 400;
            text-align: center;
            white-space: nowrap;
            vertical-align: middle;
            cursor: pointer;
            background-image: none;
            border: 1px solid transparent;
            padding: 6px 12px;
            font-size: 14px;
            line-height: 1.42857143;
            border-radius: 4px;
            text-decoration: none;
        }
        .btn-primary {
            color: #fff;
            background-color: #0066cc;
            border-color: #0052a3;
        }
        .btn-secondary {
            color: #fff;
            background-color: #6c757d;
            border-color: #6c757d;
        }
        .btn-danger {
            color: #fff;
            background-color: #dc3545;
            border-color: #dc3545;
        }
        .btn-sm {
            padding: 5px 10px;
            font-size: 12px;
            line-height: 1.5;
            border-radius: 3px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        table, th, td {
            border: 1px solid #ddd;
        }
        th, td {
            padding: 10px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
        }
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        /* Search form styles */
        .form-inline {
            display: flex;
        }
        .input-group {
            display: flex;
            width: 100%;
        }
        .input-group-append {
            display: flex;
        }
        .w-100 {
            width: 100%;
        }
        .mb-3 {
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>

<div class="container">
    <h1>Bato Medical Report System</h1>
    <p><a href="index.php" class="btn btn-secondary">Back to Home</a></p>
    
    <div class="card">
        <div class="card-body">
            <h2><?php echo $edit_mode ? 'Edit Test Type' : 'Add New Test Type'; ?></h2>
            
            <?php if (isset($message)): ?>
                <div class="alert alert-info alert-dismissible fade show" role="alert">
                    <?php echo $message; ?>
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            <?php endif; ?>
            
            <div class="card mb-4">
                <div class="card-body">
                    <form method="post" action="manage_test_types.php">
                        <?php if ($edit_mode && $test_to_edit): ?>
                            <input type="hidden" name="test_id" value="<?php echo $test_to_edit['id']; ?>">
                        <?php endif; ?>
                        
                        <div class="form-group">
                            <label for="name">Test Name</label>
                            <input type="text" class="form-control" id="name" name="name" required
                                value="<?php echo $edit_mode && $test_to_edit ? $test_to_edit['name'] : ''; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="unit">Unit</label>
                            <input type="text" class="form-control" id="unit" name="unit"
                                value="<?php echo $edit_mode && $test_to_edit ? $test_to_edit['unit'] : ''; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="normal_range">Normal Range</label>
                            <input type="text" class="form-control" id="normal_range" name="normal_range"
                                value="<?php echo $edit_mode && $test_to_edit ? $test_to_edit['normal_range'] : ''; ?>">
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            <?php echo $edit_mode ? 'Update Test Type' : 'Add Test Type'; ?>
                        </button>
                        
                        <?php if ($edit_mode): ?>
                            <a href="manage_test_types.php" class="btn btn-secondary">Cancel</a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
            
            <h2>Existing Test Types</h2>
            
            <!-- Search Form -->
            <div class="card mb-3">
                <div class="card-body">
                    <form method="get" action="manage_test_types.php" class="form-inline">
                        <div class="input-group w-100">
                            <input type="text" class="form-control" name="search" placeholder="Search by name, unit or normal range..." value="<?php echo htmlspecialchars($search_term ?? ''); ?>">
                            <div class="input-group-append">
                                <button type="submit" class="btn btn-primary">Search</button>
                                <?php if (isset($_GET['search']) && !empty($_GET['search'])): ?>
                                    <a href="manage_test_types.php" class="btn btn-secondary">Clear</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Unit</th>
                            <th>Normal Range</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result && $result->num_rows > 0): ?>
                            <?php while ($row = $result->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $row['name']; ?></td>
                                    <td><?php echo $row['unit']; ?></td>
                                    <td><?php echo $row['normal_range']; ?></td>
                                    <td>
                                        <a href="manage_test_types.php?edit=<?php echo $row['id']; ?>" class="btn btn-sm btn-primary">Edit</a>
                                        <a href="manage_test_types.php?delete=<?php echo $row['id']; ?>" class="btn btn-sm btn-danger" 
                                           onclick="return confirm('Are you sure you want to delete this test type?')">Delete</a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="text-center">No test types found</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

</body>
</html>
