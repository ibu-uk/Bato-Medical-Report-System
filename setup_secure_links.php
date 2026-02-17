<?php
/**
 * Setup script for secure links database tables
 * Run this script once to create the required database tables
 */

// Include database configuration
require_once 'config/database.php';

// Establish database connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die("<div class='alert alert-danger'>❌ Connection failed: " . $conn->connect_error . "</div>");
}

echo "<h2>Setting up Secure Links Database Tables</h2>";

// Create report_links table
echo "<h3>Creating report_links table...</h3>";

$createLinksTable = "
CREATE TABLE IF NOT EXISTS report_links (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    report_file VARCHAR(255) NOT NULL,
    token VARCHAR(64) UNIQUE NOT NULL,
    expiry_date DATETIME NOT NULL,
    is_used TINYINT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_token (token),
    INDEX idx_expiry (expiry_date),
    INDEX idx_patient (patient_id)
)";

if ($conn->query($createLinksTable)) {
    echo "<div class='alert alert-success'>✅ report_links table created successfully</div>";
} else {
    echo "<div class='alert alert-danger'>❌ Error creating report_links table: " . $conn->error . "</div>";
}

// Create report_access_log table
echo "<h3>Creating report_access_log table...</h3>";

$createAccessLogTable = "
CREATE TABLE IF NOT EXISTS report_access_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    token_id INT NOT NULL,
    patient_id INT NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    accessed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_token_id (token_id),
    INDEX idx_patient_id (patient_id),
    INDEX idx_accessed_at (accessed_at)
)";

if ($conn->query($createAccessLogTable)) {
    echo "<div class='alert alert-success'>✅ report_access_log table created successfully</div>";
} else {
    echo "<div class='alert alert-danger'>❌ Error creating report_access_log table: " . $conn->error . "</div>";
}

// Add foreign key constraint if not exists
echo "<h3>Adding foreign key constraints...</h3>";

// First, check if constraint already exists
$checkConstraint = "
SELECT COUNT(*) as count 
FROM information_schema.table_constraints 
WHERE table_schema = DATABASE() 
AND table_name = 'report_access_log' 
AND constraint_name = 'fk_report_access_log_token'
";

$result = $conn->query($checkConstraint);
$row = $result->fetch_assoc();

if ($row['count'] == 0) {
    // Constraint doesn't exist, add it
    $addForeignKey = "
    ALTER TABLE report_access_log 
    ADD CONSTRAINT fk_report_access_log_token 
    FOREIGN KEY (token_id) REFERENCES report_links(id) ON DELETE CASCADE";
    
    if ($conn->query($addForeignKey)) {
        echo "<div class='alert alert-success'>✅ Foreign key constraint added successfully</div>";
    } else {
        echo "<div class='alert alert-warning'>⚠️ Foreign key constraint failed: " . $conn->error . "</div>";
        echo "<div class='alert alert-info'>ℹ️ The system will still work without this constraint</div>";
    }
} else {
    echo "<div class='alert alert-info'>ℹ️ Foreign key constraint already exists</div>";
}

// Test the secure_links functions
echo "<h3>Testing secure links functions...</h3>";

try {
    require_once 'config/secure_links.php';
    
    // Test token generation
    $testToken = generateSecureToken();
    if (strlen($testToken) === 64) {
        echo "<div class='alert alert-success'>✅ Token generation working (64 characters)</div>";
    } else {
        echo "<div class='alert alert-danger'>❌ Token generation failed</div>";
    }
    
    // Test URL generation
    $testUrl = getSecureReportUrl($testToken);
    if (strpos($testUrl, 'report.php?token=') !== false) {
        echo "<div class='alert alert-success'>✅ URL generation working</div>";
    } else {
        echo "<div class='alert alert-danger'>❌ URL generation failed</div>";
    }
    
} catch (Exception $e) {
    echo "<div class='alert alert-danger'>❌ Error testing functions: " . $e->getMessage() . "</div>";
}

echo "<h3>Setup Complete!</h3>";
echo "<p>You can now use the secure links system:</p>";
echo "<ul>";
echo "<li>✅ Generate secure links from any report page</li>";
echo "<li>✅ Manage links at <a href='manage_secure_links.php'>manage_secure_links.php</a></li>";
echo "<li>✅ Patients can access reports via secure URLs</li>";
echo "</ul>";

echo "<div class='mt-4'>";
echo "<a href='index.php' class='btn btn-primary'>Go to Dashboard</a> ";
echo "<a href='manage_secure_links.php' class='btn btn-info'>Manage Links</a>";
echo "</div>";

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup Secure Links - Bato Medical Report System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-4">
        <!-- Content will be displayed above -->
    </div>
</body>
</html>
