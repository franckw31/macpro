<?php
// ============================================================
//  delete-event.php — Supprimer une partie Pro
//  POST https://viendez.com/api/pro/delete-event.php
//  Authorization: Bearer <token>
//  Body JSON : { event_id }
//  Seul l'organisateur propriétaire (ou un admin) peut supprimer.
//  Une partie en_cours ou terminée ne peut PAS être supprimée.
// ============================================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit;
}

try {
    require_once __DIR__ . '/_auth.php';   // → $authUser, $pdo

    $body    = json_decode(file_get_contents('php://input'), true) ?? [];
    $eventId = (int)($body['event_id'] ?? 0);

    if ($eventId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'event_id manquant']);
        exit;
    }

    // ── Vérifier existence et appartenance ────────────────────
    $stmtCheck = $pdo->prepare("SELECT `id-membre` AS organizer_id, COALESCE(`statut`,'publie') AS statut, `titre-activite` AS titre FROM `activite` WHERE `id-activite` = ? LIMIT 1");
    $stmtCheck->execute([$eventId]);
    $existing = $stmtCheck->fetch();

    if (!$existing) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Partie introuvable']);
        exit;
    }
    if (!$authUser['is_admin'] && (int)$existing['organizer_id'] !== $authUser['member_id']) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Vous n\'êtes pas l\'organisateur de cette partie']);
        exit;
    }
    if (in_array($existing['statut'], ['en_cours', 'termine'])) {
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'message' => 'Impossible de supprimer une partie en cours ou terminée. Annulez-la d\'abord.',
        ]);
        exit;
    }

    // ── Suppression (inscriptions en cascade via participation, puis activite) ──
    $pdo->prepare("DELETE FROM `participation` WHERE `id-activite` = ?")->execute([$eventId]);
    $pdo->prepare("DELETE FROM `activite` WHERE `id-activite` = ?")->execute([$eventId]);

    // Log (dans pro_logs, event_id = null car supprimé)
    $pdo->prepare("INSERT INTO pro_logs (member_id, event_id, action, details, ip) VALUES (?,?,?,?,?)")
        ->execute([
            $authUser['member_id'],
            null,
            'delete_event',
            "id=$eventId | titre={$existing['titre']}",
            $_SERVER['REMOTE_ADDR'] ?? null
        ]);

    echo json_encode(['success' => true, 'message' => 'Partie supprimée', 'event_id' => $eventId]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur base de données']);
    error_log('[pro/delete-event] ' . $e->getMessage());
}
