<?php
// /C:/Users/MSI/Desktop/www/test.php
// Connects to MySQL database named "dbs9616600" using PDO.
// Replace host, user and pass with real credentials.

$host = 'localhost';
$port = 3306;
$db   = 'dbs9616600';
$user = 'root';
$pass = 'Kookies7*';
$charset = 'utf8mb4';

$dsn = "mysql:host={$host};port={$port};dbname={$db};charset={$charset}";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    echo "Connected to database '{$db}' successfully.";
} catch (PDOException $e) {
    // In production, log the error instead of echoing details.
    http_response_code(500);
    echo "Database connection failed: " . $e->getMessage();
    exit;
}

// Example usage:
// $stmt = $pdo->query('SELECT NOW() as now');
// $row = $stmt->fetch();
// var_dump($row);