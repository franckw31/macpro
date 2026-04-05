<?php
try {
    $pdo = new PDO('mysql:host=localhost;dbname=dbs9616600;charset=utf8mb4', 'root', 'Kookies7*');
    $stmt = $pdo->query("DESCRIBE participation");
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (Exception $e) { echo $e->getMessage(); }
