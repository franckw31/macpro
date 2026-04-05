<?php
header('Content-Type: application/json; charset=utf-8');
$host = getenv('DB_HOST') ?: '127.0.0.1';
$db   = 'dbs9616600';
$user = 'root';
$pass = 'Kookies7*';
$dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];
try{
    $pdo = new PDO($dsn, $user, $pass, $options);
}catch(PDOException $e){
    http_response_code(500);
    echo json_encode(['success'=>false,'error'=>'DB connection failed: '.$e->getMessage()]);
    exit;
}
?>