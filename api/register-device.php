<?php
// ============================================================
//  Endpoint pour enregistrer les tokens de notifications push
//  Appelé automatiquement par l'app iOS au démarrage
// ============================================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$token = trim($input['device_token'] ?? '');

if (empty($token) || strlen($token) < 64) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Token invalide']);
    exit;
}

try {
    $pdo = new PDO(
        'mysql:host=localhost;dbname=dbs9616600;charset=utf8mb4',
        'root',
        'Kookies7*',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // Créer la table si elle n'existe pas
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `push_tokens` (
            `id`           INT AUTO_INCREMENT PRIMARY KEY,
            `device_token` VARCHAR(255) NOT NULL,
            `created_at`   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at`   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY `unique_token` (`device_token`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // Insérer ou rafraîchir le token
    $stmt = $pdo->prepare("
        INSERT INTO `push_tokens` (`device_token`)
        VALUES (?)
        ON DUPLICATE KEY UPDATE `updated_at` = CURRENT_TIMESTAMP
    ");
    $stmt->execute([$token]);

    echo json_encode(['success' => true, 'message' => 'Token enregistré']);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erreur base de données']);
}
