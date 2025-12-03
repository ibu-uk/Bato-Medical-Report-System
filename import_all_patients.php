<?php
// Set higher execution time and memory limits to handle large files
ini_set('max_execution_time', 600); // 10 minutes
ini_set('memory_limit', '1024M');   // 1GB memory limit

// Database configuration
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'bato_medical';

// SQL file to import
$sql_file = 'patients (3).sql';

// Start output buffering to improve performance
ob_start();
echo "<h1>Full Patient Data Import Process</h1>";

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
    
    // Process the file line by line instead of loading it all into memory
    $file = fopen($sql_path, 'r');
    if (!$file) {
        die("<p>Error: Unable to open SQL file.</p>");
    }
    
    // First, get the structure of our target table
    $table_check = $conn->query("DESCRIBE patients");
    if (!$table_check) {
        die("<p>Error: The patients table does not exist in the database.</p>");
    }
    
    // Get the columns from the patients table
    $columns = [];
    while ($row = $table_check->fetch_assoc()) {
        $columns[] = $row['Field'];
    }
    
    echo "<p>Target table structure: " . implode(", ", $columns) . "</p>";
    
    // Check for action type
    if (!isset($_POST['action'])) {
        echo "<form method='post'>";
        echo "<h2>Import Options</h2>";
        echo "<p><strong>Choose an action:</strong></p>";
        echo "<input type='radio' name='action' value='import' id='action_import' checked> <label for='action_import'>Import patient data</label><br>";
        echo "<input type='radio' name='action' value='verify' id='action_verify'> <label for='action_verify'>Verify for duplicates only (no import)</label><br><br>";
        
        echo "<div id='import_options' style='margin-left: 20px; padding: 10px; border-left: 2px solid #ccc;'>";
        echo "<p><strong>Import Options:</strong></p>";
        echo "<input type='radio' name='clear_data' value='yes' id='clear_yes'> <label for='clear_yes'>Clear existing data before importing</label><br>";
        echo "<input type='radio' name='clear_data' value='no' id='clear_no' checked> <label for='clear_no'>Add to existing data (skip duplicates)</label><br>";
        echo "</div><br>";
        
        echo "<input type='submit' value='Start Process' style='padding: 10px 20px; background-color: #4CAF50; color: white; border: none; cursor: pointer;'>";
        echo "</form>";
        
        // Add JavaScript to show/hide import options
        echo "<script>
            document.getElementById('action_import').addEventListener('change', function() {
                document.getElementById('import_options').style.display = 'block';
            });
            document.getElementById('action_verify').addEventListener('change', function() {
                document.getElementById('import_options').style.display = 'none';
            });
        </script>";
    } else {
        // Determine if we're importing or just verifying
        $is_verifying = ($_POST['action'] === 'verify');
        
        if (!$is_verifying && $_POST['clear_data'] === 'yes') {
            $conn->query("TRUNCATE TABLE patients");
            echo "<p>Existing patient data cleared.</p>";
        }
        
        // Create a temporary table to track civil IDs we've seen in the file
        $conn->query("CREATE TEMPORARY TABLE IF NOT EXISTS temp_civil_ids (
            civil_id VARCHAR(50) PRIMARY KEY,
            patient_name VARCHAR(200),
            count INT DEFAULT 1
        )");
        
        // Variables to collect INSERT statements
        $insert_buffer = '';
        $in_insert = false;
        $success_count = 0;
        $error_count = 0;
        $duplicate_count = 0;
        $total_found = 0;
        $duplicates_in_file = 0;
        
        // Process the file line by line
        while (($line = fgets($file)) !== false) {
            // Skip comments and empty lines
            if (empty(trim($line)) || strpos(trim($line), '--') === 0 || strpos(trim($line), '/*') === 0) {
                continue;
            }
            
            // Check if this is the start of an INSERT statement for patients
            if (strpos($line, 'INSERT INTO `patients`') === 0) {
                $in_insert = true;
                $insert_buffer = $line;
                $total_found++;
                continue;
            }
            
            // If we're in an INSERT statement, add this line to the buffer
            if ($in_insert) {
                $insert_buffer .= $line;
                
                // Check if this line completes the INSERT statement
                if (strpos($line, ');') !== false) {
                    $in_insert = false;
                    
                    // Extract values from the INSERT statement
                    if (preg_match_all('/\((.*?)\)(?:,|;)/', $insert_buffer, $matches)) {
                        foreach ($matches[1] as $values_str) {
                            // Parse the values
                            preg_match_all("/\'([^\']*)\'|(\d+)/", $values_str, $parsed_values);
                            $patient_data = array_map(function($val) {
                                return trim($val, "'");
                            }, array_filter($parsed_values[0], function($val) {
                                return $val !== '';
                            }));
                            
                            // Map the fields from the SQL file to our database structure
                            if (count($patient_data) >= 19) { // Ensure we have all fields
                                $id = $patient_data[0];
                                $full_name = $conn->real_escape_string($patient_data[7]);
                                $civil_id = $conn->real_escape_string($patient_data[6]);
                                $mobile = $conn->real_escape_string($patient_data[9]);
                                $file_number = 'N-' . $conn->real_escape_string($patient_data[4]); // Using increment_number as file_number
                                $created_at = date('Y-m-d H:i:s'); // Current timestamp
                                
                                // Check for duplicates within the file itself
                                $check_temp = $conn->query("SELECT count FROM temp_civil_ids WHERE civil_id = '$civil_id'");
                                if ($check_temp && $check_temp->num_rows > 0) {
                                    // This civil ID appears more than once in the file
                                    $duplicates_in_file++;
                                    $row = $check_temp->fetch_assoc();
                                    $count = $row['count'] + 1;
                                    $conn->query("UPDATE temp_civil_ids SET count = $count WHERE civil_id = '$civil_id'");
                                } else {
                                    // First time seeing this civil ID in the file
                                    $conn->query("INSERT INTO temp_civil_ids (civil_id, patient_name) VALUES ('$civil_id', '$full_name')");
                                }
                                
                                if (!$is_verifying) {
                                    // Check if the patient already exists in the database
                                    $check_query = "SELECT id FROM patients WHERE civil_id = '$civil_id' LIMIT 1";
                                    $check_result = $conn->query($check_query);
                                    
                                    if ($check_result && $check_result->num_rows > 0) {
                                        // Patient exists, count as duplicate
                                        $duplicate_count++;
                                    } else {
                                        // Patient doesn't exist, insert
                                        $insert_query = "INSERT INTO patients (name, civil_id, mobile, file_number, created_at) 
                                                        VALUES ('$full_name', '$civil_id', '$mobile', '$file_number', '$created_at')";
                                        
                                        if ($conn->query($insert_query)) {
                                            $success_count++;
                                        } else {
                                            $error_count++;
                                        }
                                    }
                                }
                                
                                // Show progress every 100 records
                                if (($success_count + $error_count + $duplicate_count) % 100 === 0) {
                                    echo "<p>Processed " . ($success_count + $error_count + $duplicate_count) . " records so far...</p>";
                                    ob_flush();
                                    flush();
                                }
                            } else {
                                $error_count++;
                            }
                        }
                    }
                    
                    $insert_buffer = '';
                }
            }
        }
        
        fclose($file);
        
        // Get duplicates within the file
        $duplicates_result = $conn->query("SELECT civil_id, patient_name, count FROM temp_civil_ids WHERE count > 1 ORDER BY count DESC LIMIT 100");
        $duplicates_in_file_detail = [];
        if ($duplicates_result) {
            while ($row = $duplicates_result->fetch_assoc()) {
                $duplicates_in_file_detail[] = $row;
            }
        }
        
        echo "<h2>Results</h2>";
        echo "<p>Found $total_found INSERT statements in the SQL file.</p>";
        
        if ($is_verifying) {
            echo "<h3>Verification Results:</h3>";
            echo "<p>Found " . count($duplicates_in_file_detail) . " patients with duplicate civil IDs within the file.</p>";
            
            if (!empty($duplicates_in_file_detail)) {
                echo "<h4>Duplicates in File:</h4>";
                echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
                echo "<tr><th>Civil ID</th><th>Patient Name</th><th>Count</th></tr>";
                foreach ($duplicates_in_file_detail as $dup) {
                    echo "<tr><td>{$dup['civil_id']}</td><td>{$dup['patient_name']}</td><td>{$dup['count']}</td></tr>";
                }
                echo "</table>";
            }
            
            // Check for duplicates between file and existing database
            $existing_count = 0;
            $result = $conn->query("SELECT COUNT(*) as count FROM patients");
            if ($result) {
                $row = $result->fetch_assoc();
                $existing_count = $row['count'];
            }
            
            echo "<p>Current patients in database: $existing_count</p>";
            
            // Sample check for a few civil IDs
            $sample_check = $conn->query("
                SELECT t.civil_id, t.patient_name, p.name AS db_name 
                FROM temp_civil_ids t
                JOIN patients p ON t.civil_id = p.civil_id
                LIMIT 10
            ");
            
            $overlap_count = 0;
            $result = $conn->query("
                SELECT COUNT(*) as count 
                FROM temp_civil_ids t
                JOIN patients p ON t.civil_id = p.civil_id
            ");
            if ($result) {
                $row = $result->fetch_assoc();
                $overlap_count = $row['count'];
            }
            
            echo "<p>Found $overlap_count patients that already exist in the database.</p>";
            
            if ($sample_check && $sample_check->num_rows > 0) {
                echo "<h4>Sample of Existing Patients:</h4>";
                echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
                echo "<tr><th>Civil ID</th><th>Name in File</th><th>Name in Database</th></tr>";
                while ($row = $sample_check->fetch_assoc()) {
                    echo "<tr><td>{$row['civil_id']}</td><td>{$row['patient_name']}</td><td>{$row['db_name']}</td></tr>";
                }
                echo "</table>";
            }
        } else {
            echo "<h3>Import Results:</h3>";
            echo "<p>Successfully imported $success_count new patient records.</p>";
            echo "<p>Skipped $duplicate_count duplicate patient records (already in database).</p>";
            echo "<p>Failed to process $error_count patient records due to errors.</p>";
            
            if ($duplicates_in_file > 0) {
                echo "<p style='color: orange;'>Found $duplicates_in_file records with duplicate civil IDs within the import file.</p>";
            }
            
            if ($error_count == 0) {
                echo "<p style='color: green;'>Import completed successfully!</p>";
            } else {
                echo "<p style='color: red;'>Import completed with some errors.</p>";
            }
        }
        
        // Clean up temporary table
        $conn->query("DROP TEMPORARY TABLE IF EXISTS temp_civil_ids");
    }
    
    // Close connection
    $conn->close();
    
} catch (Exception $e) {
    echo "<p>Error: " . $e->getMessage() . "</p>";
}

// End output buffering
ob_end_flush();
?>

<div style="margin-top: 20px;">
    <a href="index.php" style="padding: 10px 15px; background-color: #4CAF50; color: white; text-decoration: none; border-radius: 4px;">Return to Medical Report System</a>
</div>
