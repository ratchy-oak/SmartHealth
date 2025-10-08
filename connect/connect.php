<?php
$config = require_once __DIR__ . '/../../config/env.php';

$host     = $config['DB_HOST'];
$username = $config['DB_USERNAME'];
$password = $config['DB_PASSWORD'];
$database = $config['DB_DATABASE'];

$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    error_log("Database connection failed: " . $conn->connect_error);

    die("A database error occurred. Please try again later.");
}

$conn->set_charset("utf8mb4");

$conn->query("DELETE FROM login_attempts WHERE attempt_time < (NOW() - INTERVAL 1 DAY)");

?>