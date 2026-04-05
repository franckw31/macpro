<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

try {
    $pdo = new PDO(
        'mysql:host=localhost;dbname=dbs9616600;charset=utf8mb4',
        'root',
        'Kookies7*',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );

    // Get all unique challenges from activities
    // A "challenge" is a grouping/category of activities
    // For now, we'll return activities that have a challenge or special designation
    
    $stmt = $pdo->query("
        SELECT DISTINCT 
            a.`id-activite` AS id,
            a.`titre-activite` AS title
        FROM activite a
        WHERE a.date_depart >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ORDER BY a.date_depart DESC
        LIMIT 20
    ");
    $activities = $stmt->fetchAll();

    // If no activities, return an empty array
    if (empty($activities)) {
        echo json_encode([]);
        exit;
    }

    // Return as array of challenges
    echo json_encode($activities);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'DB error: ' . $e->getMessage()]);
}
?>
