<?php
/**
 * Debug script to test secure link generation
 */

echo "<h2>Testing Secure Link Generation</h2>";

// Include required files
require_once 'config/timezone.php';
require_once 'config/database.php';
require_once 'config/secure_links.php';

echo "<h3>Step 1: Testing Token Generation</h3>";
try {
    $token = generateSecureToken();
    echo "<div class='alert alert-success'>✅ Token generated: " . substr($token, 0, 16) . "...</div>";
    echo "<div class='alert alert-info'>📏 Token length: " . strlen($token) . " characters</div>";
} catch (Exception $e) {
    echo "<div class='alert alert-danger'>❌ Token generation failed: " . $e->getMessage() . "</div>";
}

echo "<h3>Step 2: Testing Database Connection</h3>";
try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        echo "<div class='alert alert-danger'>❌ Database connection failed: " . $conn->connect_error . "</div>";
    } else {
        echo "<div class='alert alert-success'>✅ Database connection successful</div>";
        
        // Test if report_links table exists
        $result = $conn->query("SHOW TABLES LIKE 'report_links'");
        if ($result->num_rows > 0) {
            echo "<div class='alert alert-success'>✅ report_links table exists</div>";
        } else {
            echo "<div class='alert alert-danger'>❌ report_links table does not exist</div>";
        }
        
        $conn->close();
    }
} catch (Exception $e) {
    echo "<div class='alert alert-danger'>❌ Database test failed: " . $e->getMessage() . "</div>";
}

echo "<h3>Step 3: Testing URL Generation</h3>";
try {
    $testToken = "1234567890abcdef1234567890abcdef1234567890abcdef1234567890abcdef";
    $url = getSecureReportUrl($testToken);
    echo "<div class='alert alert-success'>✅ URL generated: " . $url . "</div>";
} catch (Exception $e) {
    echo "<div class='alert alert-danger'>❌ URL generation failed: " . $e->getMessage() . "</div>";
}

echo "<h3>Step 4: Testing Complete Link Creation</h3>";
try {
    // Test with dummy data
    $patientId = 1;
    $reportFile = 'reports/test_report.pdf';
    
    echo "<div class='alert alert-info'>📝 Testing with: Patient ID = $patientId, File = $reportFile</div>";
    
    $token = createSecureReportLink($patientId, $reportFile, 48);
    
    if ($token) {
        echo "<div class='alert alert-success'>✅ Secure link created successfully!</div>";
        echo "<div class='alert alert-info'>🔑 Token: " . $token . "</div>";
        
        $url = getSecureReportUrl($token);
        echo "<div class='alert alert-info'>🔗 URL: " . $url . "</div>";
        
        // Check if it was saved to database
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        $stmt = $conn->prepare("SELECT * FROM report_links WHERE token = ?");
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            echo "<div class='alert alert-success'>✅ Token saved to database</div>";
            echo "<div class='alert alert-info'>📅 Expires: " . $row['expiry_date'] . "</div>";
        } else {
            echo "<div class='alert alert-warning'>⚠️ Token not found in database</div>";
        }
        
        $stmt->close();
        $conn->close();
        
    } else {
        echo "<div class='alert alert-danger'>❌ Failed to create secure link</div>";
    }
} catch (Exception $e) {
    echo "<div class='alert alert-danger'>❌ Link creation failed: " . $e->getMessage() . "</div>";
}

echo "<h3>Step 5: Check Error Logs</h3>";
$errorLog = error_get_last();
if ($errorLog) {
    echo "<div class='alert alert-warning'>⚠️ Last PHP Error: " . $errorLog['message'] . "</div>";
    echo "<div class='alert alert-info'>📁 File: " . $errorLog['file'] . " Line: " . $errorLog['line'] . "</div>";
} else {
    echo "<div class='alert alert-success'>✅ No recent PHP errors</div>";
}

echo "<div class='mt-4'>";
echo "<a href='index.php' class='btn btn-primary'>Back to Dashboard</a> ";
echo "<a href='view_report.php?id=1' class='btn btn-info'>Test on Report Page</a>";
echo "</div>";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Secure Link Test - Bato Medical Report System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-4">
        <!-- Content will be displayed above -->
    </div>
</body>
</html>
