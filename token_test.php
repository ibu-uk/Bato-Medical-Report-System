<?php
require_once 'config/database.php';
require_once 'config/secure_links.php';

// Test with a known good token (you'll need to replace this with an actual token from your database)
$testToken = 'YOUR_ACTUAL_TOKEN_HERE'; // Replace with real token

echo "Testing token validation with token: $testToken\n";

$result = validateReportToken($testToken);

if ($result) {
    echo "Token validation: SUCCESS\n";
    echo "Patient ID: " . $result['patient_id'] . "\n";
    echo "Expiry: " . $result['expiry_date'] . "\n";
} else {
    echo "Token validation: FAILED\n";
}

// Test creating a new token
echo "\nCreating new test token...\n";
$newToken = createSecureReportLink(1234, '', 8760);

if ($newToken) {
    echo "New token created: $newToken\n";
    
    // Test the new token immediately
    $testResult = validateReportToken($newToken);
    if ($testResult) {
        echo "New token validation: SUCCESS\n";
    } else {
        echo "New token validation: FAILED\n";
    }
} else {
    echo "New token creation: FAILED\n";
}
?>
