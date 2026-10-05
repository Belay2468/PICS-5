<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'philtrack_db');

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error .
        "<br>Make sure XAMPP MySQL is running and you have imported dist/php/database/philtrack.sql");
}
$conn->set_charset('utf8mb4');
