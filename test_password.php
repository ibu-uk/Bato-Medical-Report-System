<?php
// Test password verification
$stored_hash = '$2y$10$8Ux8OXwR0RVp9Ug5aZ/ZWOUFCNQuiQJwpVGz4OlFPQXEKJW5m5KMa';
$password = 'admin123';

echo "Testing password verification:<br>";
echo "Password: " . $password . "<br>";
echo "Hash: " . $stored_hash . "<br>";
echo "Result: " . (password_verify($password, $stored_hash) ? 'MATCH' : 'NO MATCH') . "<br>";

// Generate a new hash for comparison
echo "<br>Generating new hash for the same password:<br>";
$new_hash = password_hash($password, PASSWORD_DEFAULT);
echo "New hash: " . $new_hash . "<br>";
echo "Verification with new hash: " . (password_verify($password, $new_hash) ? 'MATCH' : 'NO MATCH') . "<br>";
?>
