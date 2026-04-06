<?php
/**
 * player-action.php - Handle player actions (eliminate, recave)
 * POST endpoint for bust and recave actions
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

    // Validation des paramètres
    $activity_id = intval($data['activity_id'] ?? 0);
    $victim_participation_id = intval($data['victim_participation_id'] ?? 0);
    $eliminator_name = $data['eliminator_name'] ?? '';
    $eliminator_member_id = intval($data['eliminator_member_id'] ?? 0);
    $is_definitive = intval($data['is_definitive'] ?? 0);
    $action = $data['action'] ?? 'eliminate_player'; // 'eliminate_player' ou 'recave_player'

    if (!$activity_id || !$victim_participation_id || !$eliminator_name) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Paramètres manquants']);
        exit;
    }

    // Connexion à la base de données
    $pdo = new PDO(
        'mysql:host=localhost;dbname=dbs9616600;charset=utf8mb4',
        'root',
        'Kookies7*',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // Récupérer les infos de la victime (joueur à éliminer)
    $stmt = $pdo->prepare("
        SELECT m.`id-membre`, p.`nom-membre` 
        FROM `participation` p 
        LEFT JOIN `membres` m ON p.`nom-membre` = m.`pseudo`
        WHERE p.`id-participation` = ?
    ");
    $stmt->execute([$victim_participation_id]);
    $victim_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$victim_data) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Joueur victime non trouvé']);
        exit;
    }

    $victim_member_id = intval($victim_data['id-membre'] ?? 0);
    $victim_name = $victim_data['nom-membre'] ?? '';

    if ($action === 'eliminate_player') {
        // ===== ÉLIMINATION =====
        // Vérifier que le joueur n'est pas déjà éliminé définitivement
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as cnt FROM `eliminations` 
            WHERE `id_participation` = ? AND `is_definitive` = 1
        ");
        $stmt->execute([$victim_participation_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result['cnt'] > 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Joueur déjà éliminé définitivement']);
            exit;
        }

        // Insérer l'élimination
        $stmt = $pdo->prepare("
            INSERT INTO `eliminations` 
            (`id_participation`, `nom_membre`, `id_membre`, `id_membre_victime`, `nom_membre_victime`, `id_activite`, `is_definitive`, `created_at`)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$victim_participation_id, $eliminator_name, $eliminator_member_id, $victim_member_id, $victim_name, $activity_id, $is_definitive]);

        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'Joueur éliminé']);

    } elseif ($action === 'recave_player') {
        // ===== RECAVE =====
        // Incrémenter le compteur de recave dans participation
        $stmt = $pdo->prepare("
            UPDATE `participation` 
            SET `recave` = `recave` + 1 
            WHERE `id-participation` = ?
        ");
        $stmt->execute([$victim_participation_id]);

        // Enregistrer qui a éliminé le joueur (pour traçabilité)
        $stmt = $pdo->prepare("
            INSERT INTO `eliminations` 
            (`id_participation`, `nom_membre`, `id_membre`, `id_membre_victime`, `nom_membre_victime`, `id_activite`, `is_definitive`, `created_at`)
            VALUES (?, ?, ?, ?, ?, ?, 0, NOW())
        ");
        $stmt->execute([$victim_participation_id, $eliminator_name, $eliminator_member_id, $victim_member_id, $victim_name, $activity_id]);

        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'Recave enregistré']);

    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Action inconnue']);
        exit;
    }

} catch (Exception $e) {
    error_log("player-action.php error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
