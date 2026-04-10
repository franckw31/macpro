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

    $uid = isset($_GET['uid']) ? (int)$_GET['uid'] : 0;
    
    if ($uid === 0) {
        echo json_encode([
            'status' => 'idle',
            'seconds_remaining' => 0,
            'duration_seconds' => 1,
            'blinds_text' => '--/--',
            'level_name' => 'N/A',
            'is_paused' => false
        ]);
        exit;
    }

    // Get activity information
    $stmt = $pdo->prepare("
        SELECT 
            a.`id-activite`,
            a.`titre-activite`,
            a.`date_depart`,
            a.`id_structure`
        FROM activite a
        WHERE a.`id-activite` = ?
    ");
    $stmt->execute([$uid]);
    $activity = $stmt->fetch();

    if (!$activity) {
        echo json_encode([
            'status' => 'not_found',
            'seconds_remaining' => 0,
            'duration_seconds' => 1,
            'blinds_text' => '--/--',
            'level_name' => 'Activité introuvable',
            'is_paused' => false
        ]);
        exit;
    }

    // Check if activity is running by looking for timer state
    // For now, return default idle state
    // This would need more complex logic to track actual timer state
    
    echo json_encode([
        'status' => 'idle',
        'seconds_remaining' => 0,
        'duration_seconds' => 1,
        'blinds_text' => '--/--',
        'level_name' => 'Prêt à démarrer',
        'is_paused' => false
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'error' => 'DB error: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'error' => $e->getMessage()
    ]);
}
?>
