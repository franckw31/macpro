<?php
// ============================================================
//  Consultation des logs de connexion iOS depuis activity_logs
//  GET  ?limit=50&action=login_failure&pseudo=franck
// ============================================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$limit  = min(500, max(1, (int)($_GET['limit']  ?? 100)));
$action = trim($_GET['action'] ?? '');
$pseudo = trim($_GET['pseudo'] ?? '');

try {
    $pdo = new PDO(
        'mysql:host=localhost;dbname=dbs9616600;charset=utf8mb4',
        'root',
        'Kookies7*',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );

    $where  = ["`source` IN ('iOS App','iOS Admin')"];
    $params = [];

    if ($action !== '') {
        $where[]  = '`action` = ?';
        $params[] = $action;
    }
    if ($pseudo !== '') {
        $where[]  = '`username` LIKE ?';
        $params[] = "%$pseudo%";
    }

    $sql = "SELECT `id`, `timestamp`, `action`, `username`, `user_id`, `details`, `ip_address`, `source`
            FROM `activity_logs`"
         . ' WHERE ' . implode(' AND ', $where)
         . " ORDER BY `timestamp` DESC LIMIT $limit";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    // Résumé par action
    $summary = $pdo->query("
        SELECT `action`, COUNT(*) AS total
        FROM `activity_logs`
        WHERE `source` IN ('iOS App','iOS Admin')
        GROUP BY `action`
        ORDER BY total DESC
    ")->fetchAll();

    echo json_encode([
        'success' => true,
        'count'   => count($rows),
        'summary' => $summary,
        'logs'    => $rows,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
