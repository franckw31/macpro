<?php
header('Content-Type: application/json');
try {
    $pdo = new PDO('mysql:host=localhost;dbname=dbs9616600;charset=utf8mb4', 'root', 'Kookies7*', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $cols = $pdo->query("DESCRIBE `activite`")->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['columns' => $cols]);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
