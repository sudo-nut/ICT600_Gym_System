<?php
$host = "100.93.101.105";
$username = "dbuser";
$password = "dbuser@123";
$database = "gym_system";

$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");

error_reporting(E_ALL);
ini_set("display_errors", 1);

function sanitize_input($data, $conn)
{
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    $data = $conn->real_escape_string($data);
    return $data;
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
