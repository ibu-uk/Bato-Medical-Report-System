<?php
// Database configuration
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'bato_medical';

// SQL file to import
$sql_file = 'patients (3).sql'; // Change this to your SQL file name

echo "<h1>SQL File Import Process</h1>";

try {
    // Connect to the database
    $conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
    
    // Check connection
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    
    echo "<p>Database connection successful.</p>";
    
    // Read the SQL file
    $sql_path = __DIR__ . '/' . $sql_file;
    
    if (!file_exists($sql_path)) {
        die("<p>Error: SQL file not found at: $sql_path</p>");
    }
    
    echo "<p>Reading SQL file: $sql_file</p>";
    
    $sql_content = file_get_contents($sql_path);
    
    if ($sql_content === false) {
        die("<p>Error: Unable to read SQL file.</p>");
    }
    
    // Split SQL file into individual queries
    // Remove comments and keep only SQL statements
    $sql_content = preg_replace('/\/\*.*?\*\/|--.*?\n|#.*?\n/', '', $sql_content);
    
    // Split into individual queries
    $queries = explode(';', $sql_content);
    
    $success_count = 0;
    $error_count = 0;
    
    // Execute each query
    foreach ($queries as $query) {
        $query = trim($query);
        
        if (empty($query)) {
            continue;
        }
        
        // Execute the query
        if ($conn->query($query)) {
            $success_count++;
        } else {
            $error_count++;
            echo "<p>Error executing query: " . $conn->error . "</p>";
            echo "<p>Query: " . htmlspecialchars(substr($query, 0, 300)) . "...</p>";
        }
    }
    
    echo "<h2>Import Results</h2>";
    echo "<p>Successfully executed $success_count queries.</p>";
    echo "<p>Failed to execute $error_count queries.</p>";
    
    if ($error_count == 0) {
        echo "<p style='color: green;'>Import completed successfully!</p>";
    } else {
        echo "<p style='color: red;'>Import completed with errors.</p>";
    }
    
    // Close connection
    $conn->close();
    
} catch (Exception $e) {
    echo "<p>Error: " . $e->getMessage() . "</p>";
}
?>

<div style="margin-top: 20px;">
    <a href="index.php" style="padding: 10px 15px; background-color: #4CAF50; color: white; text-decoration: none; border-radius: 4px;">Return to Medical Report System</a>
</div>
