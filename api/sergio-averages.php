<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

require_once __DIR__ . '/config.php';

$token = trim($_GET['token'] ?? '');
$activityId = (int)($_GET['activity_id'] ?? 0);

if ($token === '') {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Token manquant']);
    exit;
}

try {
    $pdo = get_pdo();

    $stmt = $pdo->prepare('SELECT membre_id FROM sessions WHERE token = ? AND expires_at > NOW() LIMIT 1');
    $stmt->execute([$token]);
    if (!$stmt->fetchColumn()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Session invalide']);
        exit;
    }

    $beforeDate = null;
    if ($activityId > 0) {
        $stmt = $pdo->prepare('SELECT date_depart FROM activite WHERE id = ? LIMIT 1');
        $stmt->execute([$activityId]);
        $beforeDate = $stmt->fetchColumn() ?: null;
    }

    $sql = "
        SELECT
            m.pseudo,
            ROUND(AVG(CAST(p.sergio_score AS DECIMAL(10,2))), 1) AS sergio_score_moyen
        FROM participation p
        INNER JOIN membres m ON m.id = p.membre_id
        INNER JOIN activite a ON a.id = p.activite_id
        WHERE p.sergio_score IS NOT NULL
          AND p.sergio_score <> ''
    ";
    $params = [];

    if ($beforeDate !== null) {
        $sql .= " AND a.date_depart < ? ";
        $params[] = $beforeDate;
    }

    $sql .= " GROUP BY m.id, m.pseudo ORDER BY m.pseudo ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    echo json_encode([
        'success' => true,
        'scores' => $stmt->fetchAll(PDO::FETCH_ASSOC),
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}