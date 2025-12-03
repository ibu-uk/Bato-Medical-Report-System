<?php
// Include database configuration
require_once 'config/database.php';

echo "<h1>Fix Prescriptions</h1>";

// Get all prescriptions
$query = "SELECT * FROM prescriptions";
$result = executeQuery($query);

if ($result && $result->num_rows > 0) {
    echo "<h2>Found " . $result->num_rows . " prescriptions</h2>";
    
    echo "<table border='1'>";
    echo "<tr><th>ID</th><th>Patient ID</th><th>Doctor ID</th><th>Date</th><th>Consultation Report</th><th>Action</th></tr>";
    
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td>" . $row['patient_id'] . "</td>";
        echo "<td>" . $row['doctor_id'] . "</td>";
        echo "<td>" . $row['prescription_date'] . "</td>";
        echo "<td>" . htmlspecialchars($row['consultation_report']) . "</td>";
        echo "<td><a href='?fix_id=" . $row['id'] . "'>Fix</a></td>";
        echo "</tr>";
    }
    echo "</table>";
}

// Fix a specific prescription
if (isset($_GET['fix_id'])) {
    $prescription_id = sanitize($_GET['fix_id']);
    
    // Get prescription details
    $query = "SELECT * FROM prescriptions WHERE id = '$prescription_id'";
    $result = executeQuery($query);
    
    if ($result && $result->num_rows > 0) {
        $prescription = $result->fetch_assoc();
        echo "<h2>Fixing Prescription ID: " . $prescription_id . "</h2>";
        
        // Process form submission
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Begin transaction
            executeQuery("START TRANSACTION");
            
            // Delete existing medications
            $query = "DELETE FROM prescription_medications WHERE prescription_id = '$prescription_id'";
            executeQuery($query);
            
            // Insert new medications
            if (isset($_POST['medicine_name']) && is_array($_POST['medicine_name'])) {
                for ($i = 0; $i < count($_POST['medicine_name']); $i++) {
                    if (!empty($_POST['medicine_name'][$i])) {
                        $medicine_name = sanitize($_POST['medicine_name'][$i]);
                        $dose = sanitize($_POST['dose'][$i]);
                        
                        $query = "INSERT INTO prescription_medications (prescription_id, medicine_name, dose) 
                                  VALUES ('$prescription_id', '$medicine_name', '$dose')";
                        executeQuery($query);
                    }
                }
            }
            
            // Commit transaction
            executeQuery("COMMIT");
            
            echo "<p style='color: green;'>Medications updated successfully!</p>";
        }
        
        // Get current medications
        $query = "SELECT * FROM prescription_medications WHERE prescription_id = '$prescription_id'";
        $result = executeQuery($query);
        $medications = [];
        
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $medications[] = $row;
            }
        }
        
        // Display form to add/edit medications
        ?>
        <form method="post" action="">
            <h3>Edit Medications</h3>
            <div id="medicationsContainer">
                <?php if (!empty($medications)): ?>
                    <?php foreach ($medications as $index => $medication): ?>
                        <div class="medication-row" style="margin-bottom: 10px;">
                            <div style="display: flex; gap: 10px;">
                                <div style="flex: 2;">
                                    <label>Medicine/Product:</label>
                                    <input type="text" name="medicine_name[]" value="<?php echo htmlspecialchars($medication['medicine_name']); ?>" style="width: 100%;">
                                </div>
                                <div style="flex: 1;">
                                    <label>Dose:</label>
                                    <input type="text" name="dose[]" value="<?php echo htmlspecialchars($medication['dose']); ?>" style="width: 100%;">
                                </div>
                                <div>
                                    <label>&nbsp;</label>
                                    <button type="button" class="remove-medication" style="display: block; margin-top: 22px;">Remove</button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="medication-row" style="margin-bottom: 10px;">
                        <div style="display: flex; gap: 10px;">
                            <div style="flex: 2;">
                                <label>Medicine/Product:</label>
                                <input type="text" name="medicine_name[]" value="<?php echo htmlspecialchars($prescription['consultation_report']); ?>" style="width: 100%;">
                            </div>
                            <div style="flex: 1;">
                                <label>Dose:</label>
                                <input type="text" name="dose[]" value="As directed" style="width: 100%;">
                            </div>
                            <div>
                                <label>&nbsp;</label>
                                <button type="button" class="remove-medication" style="display: block; margin-top: 22px;">Remove</button>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            
            <button type="button" id="addMedicationBtn" style="margin-top: 10px;">Add Medication</button>
            <button type="submit" style="margin-top: 10px;">Save Medications</button>
        </form>
        
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Add medication row
            document.getElementById('addMedicationBtn').addEventListener('click', function() {
                const container = document.getElementById('medicationsContainer');
                const newRow = document.createElement('div');
                newRow.className = 'medication-row';
                newRow.style.marginBottom = '10px';
                
                newRow.innerHTML = `
                    <div style="display: flex; gap: 10px;">
                        <div style="flex: 2;">
                            <label>Medicine/Product:</label>
                            <input type="text" name="medicine_name[]" style="width: 100%;">
                        </div>
                        <div style="flex: 1;">
                            <label>Dose:</label>
                            <input type="text" name="dose[]" style="width: 100%;">
                        </div>
                        <div>
                            <label>&nbsp;</label>
                            <button type="button" class="remove-medication" style="display: block; margin-top: 22px;">Remove</button>
                        </div>
                    </div>
                `;
                
                container.appendChild(newRow);
                
                // Add event listener to the new remove button
                newRow.querySelector('.remove-medication').addEventListener('click', function() {
                    container.removeChild(newRow);
                });
            });
            
            // Remove medication row
            document.querySelectorAll('.remove-medication').forEach(function(button) {
                button.addEventListener('click', function() {
                    const row = this.closest('.medication-row');
                    row.parentNode.removeChild(row);
                });
            });
        });
        </script>
        <?php
    } else {
        echo "<p>Prescription not found.</p>";
    }
}

echo "<p><a href='prescriptions.php'>Back to Prescriptions</a></p>";
?>
