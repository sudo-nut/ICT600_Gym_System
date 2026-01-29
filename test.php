<?php
error_reporting(E_ALL);
ini_set("display_errors", 1);
ini_set("display_startup_errors", 1);

echo "Testing database connection...\n";

$host = "localhost";
$username = "root";
$password = "";
$database = "membership_system";

$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "Connected successfully!\n";

// Check if users table exists
$result = $conn->query("SHOW TABLES LIKE 'users'");
if ($result && $result->num_rows > 0) {
    echo "✓ Users table found\n";
} else {
    echo "✗ Users table NOT found\n";
    echo "Available tables:\n";
    $tables = $conn->query("SHOW TABLES");
    while ($row = $tables->fetch_row()) {
        echo "  - " . $row[0] . "\n";
    }
}

$conn->close();
?>
