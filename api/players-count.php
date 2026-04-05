<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
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

    // Compter les inscrits de la prochaine activité (et non tous les membres)
    $stmt = $pdo->query("
        SELECT COUNT(p.`id-participation`) AS count
        FROM `activite` a
        LEFT JOIN `participation` p ON p.`id-activite` = a.`id-activite`
        WHERE a.`date_depart` >= NOW()
        ORDER BY a.`date_depart` ASC
        LIMIT 1
    ");
    $result = $stmt->fetch();
    
    echo json_encode([
        'success' => true,
        'count' => (int)$result['count'],
        'timestamp' => date('Y-m-d H:i:s')
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error'
    ]);
}
?>
