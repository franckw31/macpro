<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

try {
    $pdo = new PDO(
        'mysql:host=localhost;dbname=dbs9616600;charset=utf8mb4',
        'root',
        'Kookies7*',
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );

    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;
    if ($limit < 1) $limit = 1;
    if ($limit > 500) $limit = 500;

    $stmt = $pdo->prepare("SELECT `id`, `device_id`, `user_name`, `phone_number`, `ios_identity`, `phone_name`, `icloud_account`, `icloud_id`, `device_name`, `device_model`, `os_version`, `app_version`, `timestamp` FROM `app_usage_logs` ORDER BY `id` DESC LIMIT :limit");
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();

    $logs = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'count' => count($logs),
        'logs' => $logs
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error'
    ]);
}
?>
