<?php
// Start session
session_start();

// Include database configuration
require_once 'config/database.php';

echo "<h1>Form Debug</h1>";

// Display form data if submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "<h2>POST Data</h2>";
    echo "<pre>";
    print_r($_POST);
    echo "</pre>";
    
    // Check if medicine_name array exists
    if (isset($_POST['medicine_name']) && is_array($_POST['medicine_name'])) {
        echo "<h3>Medicine Names</h3>";
        echo "<ul>";
        foreach ($_POST['medicine_name'] as $key => $value) {
            echo "<li>$key: " . htmlspecialchars($value) . "</li>";
        }
        echo "</ul>";
    } else {
        echo "<p>No medicine_name array found in POST data</p>";
    }
    
    // Check if dose array exists
    if (isset($_POST['dose']) && is_array($_POST['dose'])) {
        echo "<h3>Doses</h3>";
        echo "<ul>";
        foreach ($_POST['dose'] as $key => $value) {
            echo "<li>$key: " . htmlspecialchars($value) . "</li>";
        }
        echo "</ul>";
    } else {
        echo "<p>No dose array found in POST data</p>";
    }
}
?>

<h2>Test Form</h2>
<form method="post" action="">
    <div>
        <label>Patient ID:</label>
        <input type="text" name="patient_id" value="1">
    </div>
    <div>
        <label>Doctor ID:</label>
        <input type="text" name="doctor_id" value="1">
    </div>
    <div>
        <label>Prescription Date:</label>
        <input type="date" name="prescription_date" value="<?php echo date('Y-m-d'); ?>">
    </div>
    <div>
        <label>Consultation Report:</label>
        <textarea name="consultation_report">Test consultation report</textarea>
    </div>
    
    <h3>Medications</h3>
    <div id="medicationsContainer">
        <div class="medication-row">
            <div>
                <label>Medicine/Product:</label>
                <input type="text" name="medicine_name[]" value="Test Medicine 1">
            </div>
            <div>
                <label>Dose:</label>
                <input type="text" name="dose[]" value="1 tablet daily">
            </div>
        </div>
        <div class="medication-row">
            <div>
                <label>Medicine/Product:</label>
                <input type="text" name="medicine_name[]" value="Test Medicine 2">
            </div>
            <div>
                <label>Dose:</label>
                <input type="text" name="dose[]" value="2 tablets daily">
            </div>
        </div>
    </div>
    
    <button type="submit">Submit</button>
</form>

<h2>Check Database Tables</h2>
<?php
// Check prescription_medications table
$query = "DESCRIBE prescription_medications";
$result = executeQuery($query);

echo "<h3>Prescription Medications Table Structure</h3>";
echo "<table border='1'><tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
while ($row = $result->fetch_assoc()) {
    echo "<tr>";
    echo "<td>{$row['Field']}</td>";
    echo "<td>{$row['Type']}</td>";
    echo "<td>{$row['Null']}</td>";
    echo "<td>{$row['Key']}</td>";
    echo "<td>{$row['Default']}</td>";
    echo "<td>{$row['Extra']}</td>";
    echo "</tr>";
}
echo "</table>";

// Check if there's any data in the table
$query = "SELECT * FROM prescription_medications LIMIT 10";
$result = executeQuery($query);

echo "<h3>Prescription Medications Sample Data</h3>";
if ($result && $result->num_rows > 0) {
    echo "<table border='1'><tr>";
    
    // Get column names
    $fields = $result->fetch_fields();
    foreach ($fields as $field) {
        echo "<th>{$field->name}</th>";
    }
    echo "</tr>";
    
    // Reset pointer
    $result->data_seek(0);
    
    // Display data
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        foreach ($row as $key => $value) {
            echo "<td>" . htmlspecialchars($value) . "</td>";
        }
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No data found in prescription_medications table.</p>";
}
?>
