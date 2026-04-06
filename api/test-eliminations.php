<?php
/**
 * test-eliminations.php - Test eliminations table and functionality
 */

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

try {
    $pdo = new PDO(
        'mysql:host=localhost;dbname=dbs9616600;charset=utf8mb4',
        'root',
        'Kookies7*',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    $action = $_GET['action'] ?? 'check';

    if ($action === 'check') {
        // Vérifier la structure de la table
        $stmt = $pdo->prepare("DESCRIBE `eliminations`");
        $stmt->execute();
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'message' => 'Table eliminations trouvée',
            'columns' => $columns
        ]);

    } elseif ($action === 'count') {
        // Compter les enregistrements
        $stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM `eliminations`");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'count' => $result['cnt'],
            'message' => 'Total d\'éliminations: ' . $result['cnt']
        ]);

    } elseif ($action === 'sample') {
        // Afficher quelques exemples
        $stmt = $pdo->prepare("SELECT * FROM `eliminations` LIMIT 5");
        $stmt->execute();
        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'records' => $records,
            'count' => count($records)
        ]);

    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Action inconnue']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
