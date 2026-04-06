<?php
/**
 * undo-player-action.php - Undo last player action (eliminate, recave)
 * POST endpoint to delete the most recent elimination/recave for an activity
 */

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        exit;
    }

    $data = json_decode(file_get_contents('php://input'), true);

    $activity_id = intval($data['activity_id'] ?? 0);

    if (!$activity_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID d\'activité manquant']);
        exit;
    }

    // Connexion à la base de données
    $pdo = new PDO(
        'mysql:host=localhost;dbname=dbs9616600;charset=utf8mb4',
        'root',
        'Kookies7*',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // Récupérer la dernière action pour cette activité
    $stmt = $pdo->prepare("
        SELECT `id`, `id_participation`, `is_definitive`
        FROM `eliminations`
        WHERE `id_activite` = ?
        ORDER BY `created_at` DESC
        LIMIT 1
    ");
    $stmt->execute([$activity_id]);
    $last_action = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$last_action) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Aucune action à annuler']);
        exit;
    }

    $elimination_id = intval($last_action['id']);
    $participation_id = intval($last_action['id_participation']);
    $was_definitive = intval($last_action['is_definitive']);

    // Supprimer l'entrée d'élimination
    $stmt = $pdo->prepare("DELETE FROM `eliminations` WHERE `id` = ?");
    $stmt->execute([$elimination_id]);

    // Si c'était une élimination définitive, réinitialiser le classement
    if ($was_definitive === 1) {
        $stmt = $pdo->prepare("
            UPDATE `participation`
            SET `classement` = 0
            WHERE `id-participation` = ?
        ");
        $stmt->execute([$participation_id]);
    } else {
        // Si c'était une recave, décrémenter le compteur
        $stmt = $pdo->prepare("
            UPDATE `participation`
            SET `recave` = GREATEST(0, `recave` - 1)
            WHERE `id-participation` = ?
        ");
        $stmt->execute([$participation_id]);
    }

    http_response_code(200);
    echo json_encode(['success' => true, 'message' => 'Action annulée avec succès']);

} catch (Exception $e) {
    error_log("undo-player-action.php error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
