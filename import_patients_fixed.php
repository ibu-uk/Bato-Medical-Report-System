<?php
// Database configuration
$db_host = 'localhost';
$db_user = 'root';
$db_pass = 'TheSild@2025b';
$db_name = 'bato_medical';

// SQL file to import
$sql_file = 'patients-24july.sql';

echo "<h1>Patient Data Import Process</h1>";

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
    
    // Extract INSERT statements for patients table
    preg_match_all('/INSERT INTO `patients` \(`id`, `date`, `time12`, `time24`, `increment_number`, `patient_code`, `civil_id`, `full_name`, `dob`, `mobile_number`, `address`, `description`, `allergy`, `history`, `note`, `image`, `gender`, `current_status`, `status`\) VALUES\s*\((.*?)\);/s', $sql_content, $matches);
    
    if (empty($matches[1])) {
        die("<p>Error: No patient data found in the SQL file.</p>");
    }
    
    // First check if our target table exists and its structure
    $table_check = $conn->query("DESCRIBE patients");
    if (!$table_check) {
        die("<p>Error: The patients table does not exist in the database.</p>");
    }
    
    // Get the columns from the patients table
    $columns = [];
    while ($row = $table_check->fetch_assoc()) {
        $columns[] = $row['Field'];
    }
    
    echo "<p>Found " . count($matches[1]) . " patient records in the SQL file.</p>";
    
    // Ask user if they want to clear existing data
    echo "<form method='post'>";
    echo "<p>Do you want to clear existing patient data before importing?</p>";
    echo "<input type='hidden' name='confirm_import' value='1'>";
    echo "<input type='radio' name='clear_data' value='yes' id='clear_yes'> <label for='clear_yes'>Yes, clear existing data</label><br>";
    echo "<input type='radio' name='clear_data' value='no' id='clear_no' checked> <label for='clear_no'>No, add to existing data</label><br><br>";
    echo "<input type='submit' value='Start Import'>";
    echo "</form>";
    
    // Process the import if confirmed
    if (isset($_POST['confirm_import'])) {
	$conn->query("SET FOREIGN_KEY_CHECKS = 0");
        // Clear existing data if requested
        if ($_POST['clear_data'] === 'yes') {
            $conn->query("TRUNCATE TABLE patients");
            echo "<p>Existing patient data cleared.</p>";
        }

	$conn->query("SET FOREIGN_KEY_CHECKS = 1");        

        $success_count = 0;
        $error_count = 0;
        
        // Process each patient record
        foreach ($matches[1] as $values) {
            // Parse the values
            preg_match_all("/'([^']*)'|(\d+)/", $values, $parsed_values);
            $patient_data = array_map(function($val) {
                return trim($val, "'");
            }, array_filter($parsed_values[0], function($val) {
                return $val !== '';
            }));
            
            // Map the fields from the SQL file to our database structure
            // Assuming our database has: id, name, civil_id, mobile, file_number, created_at
            if (count($patient_data) >= 19) { // Ensure we have all fields
                $id = $patient_data[0];
                $full_name = $conn->real_escape_string($patient_data[7]);
                $civil_id = $conn->real_escape_string($patient_data[6]);
                $mobile = $conn->real_escape_string($patient_data[9]);
                $file_number = 'N-' . $conn->real_escape_string($patient_data[4]); // Using increment_number as file_number
                $created_at = date('Y-m-d H:i:s'); // Current timestamp
                
                // Check if the patient already exists
                $check_query = "SELECT id FROM patients WHERE civil_id = '$civil_id' OR file_number = '$file_number' LIMIT 1";
                $check_result = $conn->query($check_query);
                
                if ($check_result && $check_result->num_rows > 0) {
                    // Patient exists, update
                    $existing_patient = $check_result->fetch_assoc();
                    $update_query = "UPDATE patients SET 
                                    name = '$full_name',
                                    mobile = '$mobile',
                                    file_number = '$file_number'
                                    WHERE id = {$existing_patient['id']}";
                    
                    if ($conn->query($update_query)) {
                        $success_count++;
                        echo "<p>Updated patient: $full_name (ID: {$existing_patient['id']})</p>";
                    } else {
                        $error_count++;
                        echo "<p>Error updating patient $full_name: " . $conn->error . "</p>";
                    }
                } else {
                    // Patient doesn't exist, insert
                    $insert_query = "INSERT INTO patients (name, civil_id, mobile, file_number, created_at) 
                                    VALUES ('$full_name', '$civil_id', '$mobile', '$file_number', '$created_at')";
                    
                    if ($conn->query($insert_query)) {
                        $success_count++;
                        echo "<p>Imported patient: $full_name</p>";
                    } else {
                        $error_count++;
                        echo "<p>Error importing patient $full_name: " . $conn->error . "</p>";
                    }
                }
            } else {
                $error_count++;
                echo "<p>Error: Invalid data format for a patient record.</p>";
            }
        }
        
        echo "<h2>Import Results</h2>";
        echo "<p>Successfully processed $success_count patient records.</p>";
        echo "<p>Failed to process $error_count patient records.</p>";
        
        if ($error_count == 0) {
            echo "<p style='color: green;'>Import completed successfully!</p>";
        } else {
            echo "<p style='color: red;'>Import completed with errors.</p>";
        }
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
