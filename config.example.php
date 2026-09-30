<?php

$host = "localhost";
$user = "YOUR_DB_USERNAME";
$password = "YOUR_DB_PASSWORD";
$database = "queue_system";
$conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Start session on every page that includes this file
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
