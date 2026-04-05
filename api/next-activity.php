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

    $stmt = $pdo->query("
        SELECT a.`id-activite`, a.`date_depart`, a.`titre-activite`, a.`ville`,
               a.`buyin`, a.`rake`,
               COUNT(p.`id-participation`) AS participants_count
        FROM `activite` a
        LEFT JOIN `participation` p 
            ON p.`id-activite` = a.`id-activite`
            AND COALESCE(p.`option`, 'None') NOT IN ('None', 'Desinscrit')
        WHERE a.`date_depart` >= NOW()
        GROUP BY a.`id-activite`, a.`date_depart`, a.`titre-activite`, a.`ville`, a.`buyin`, a.`rake`
        ORDER BY a.`date_depart` ASC
        LIMIT 1
    ");
    
    $result = $stmt->fetch();
    
    if ($result) {
        echo json_encode([
            'success' => true,
            'id' => (int)$result['id-activite'],
            'date' => $result['date_depart'],
            'title' => $result['titre-activite'],
            'city' => $result['ville'],
            'participants_count' => (int)$result['participants_count'],
            'buyin' => (int)$result['buyin'],
            'rake' => (int)$result['rake'],
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'date' => null,
            'message' => 'Aucune activité prévue'
        ]);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error'
    ]);
}
?>
