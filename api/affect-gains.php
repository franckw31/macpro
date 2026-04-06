<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    $pdo = new PDO(
        'mysql:host=localhost;dbname=dbs9616600;charset=utf8mb4',
        'root',
        'Kookies7*',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );

    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (!$data || !isset($data['activity_id']) || !isset($data['gains'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Paramètres manquants']);
        exit;
    }

    $actId = (int)$data['activity_id'];
    $gains = $data['gains'];

    $pdo->beginTransaction();
    $updated = 0;
    foreach ($gains as $rank => $gain) {
        $stmt = $pdo->prepare("UPDATE `participation` SET `gain` = ? WHERE `id-activite` = ? AND `classement` = ? AND `gain` = 0");
        $stmt->execute([(int)$gain, $actId, (int)$rank]);
        if ($stmt->rowCount() > 0) {
            $updated++;
        }
    }
    $pdo->commit();

    echo json_encode(['success' => true, 'updated' => $updated]);
} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erreur de base de données']);
}
