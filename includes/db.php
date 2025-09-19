<?php
$db_host = 'database-1.cnzu7qe85aa2.eu-central-1.rds.amazonaws.com';
$db_user = 'root';
$db_password = 'passpass';
$db_name = 'nouriabj_dev';

$conn = new mysqli($db_host, $db_user, $db_password, $db_name);

if ($conn->connect_error) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Database connection failed']);
    exit();
}

$conn->set_charset("utf8mb4");

// Set MySQL timezone to GMT
$conn->query("SET time_zone = '+00:00'");
?> 