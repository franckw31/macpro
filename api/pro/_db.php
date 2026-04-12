<?php
// ============================================================
//  _db.php — Connexion PDO partagée pour tous les endpoints Pro
//  Usage : require_once __DIR__ . '/_db.php';  → $pdo disponible
// ============================================================

$pdo = new PDO(
    'mysql:host=localhost;dbname=dbs9616600;charset=utf8mb4',
    'root',
    'Kookies7*',
    [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]
);
