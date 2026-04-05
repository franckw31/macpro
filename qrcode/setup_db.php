<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/../config.php';

$sql = "CREATE TABLE IF NOT EXISTS qrcodes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    content TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if (mysqli_query($conx, $sql)) {
    echo "Table qrcodes created successfully";
} else {
    echo "Error creating table: " . mysqli_error($conx);
}
?>
