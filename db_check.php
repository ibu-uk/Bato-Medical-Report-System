<?php
$connection = new mysqli('localhost', 'root', '', 'bato_medical');
if ($connection->connect_error) {
    die('Connection failed: ' . $connection->connect_error);
}

echo 'Reports table structure:' . PHP_EOL;
$result = $connection->query('DESCRIBE reports');
while ($row = $result->fetch_assoc()) {
    echo '- ' . $row['Field'] . ' (' . $row['Type'] . ')' . PHP_EOL;
}

$connection->close();
?>
