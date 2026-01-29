<?php
error_reporting(E_ALL);
ini_set("display_errors", 1);

// Connect WITHOUT specifying database
$conn = new mysqli("localhost", "root", "");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "Connected to MySQL!\n\n";
echo "Available databases:\n";

$result = $conn->query("SHOW DATABASES");
while ($row = $result->fetch_row()) {
    echo "  - " . $row[0] . "\n";
}

$conn->close();
?>
