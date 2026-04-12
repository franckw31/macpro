<?php
// ============================================================
//  change-status.php — Changer le statut d'une partie Pro
//  POST https://viendez.com/api/pro/change-status.php
//  Authorization: Bearer <token>
//  Body JSON : { event_id, statut }
//  statuts valides : brouillon | publie | en_cours | termine | annule
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
    $statut  = trim($body['statut']    ?? '');

    $validStatuts = ['brouillon', 'publie', 'en_cours', 'termine', 'annule'];
    if ($eventId <= 0 || !in_array($statut, $validStatuts, true)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'event_id ou statut invalide']);
        exit;
    }

    // ── Vérifier appartenance ─────────────────────────────────
    $stmtCheck = $pdo->prepare("SELECT organizer_id, statut FROM pro_events WHERE id = ? LIMIT 1");
    $stmtCheck->execute([$eventId]);
    $existing = $stmtCheck->fetch();

    if (!$existing) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Partie introuvable']);
        exit;
    }
    if (!$authUser['is_admin'] && (int)$existing['organizer_id'] !== $authUser['member_id']) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Accès refusé']);
        exit;
    }

    // ── Transitions autorisées ────────────────────────────────
    // brouillon → publie | annule
    // publie    → en_cours | brouillon | annule
    // en_cours  → publie | termine | annule
    // termine   → (rien)
    // annule    → publie (réouverture admin seulement)
    $transitions = [
        'brouillon' => ['publie', 'annule'],
        'publie'    => ['en_cours', 'brouillon', 'annule'],
        'en_cours'  => ['publie', 'termine', 'annule'],
        'termine'   => [],
        'annule'    => ['publie'],
    ];
    $allowed = $transitions[$existing['statut']] ?? [];

    if (!$authUser['is_admin'] && !in_array($statut, $allowed, true)) {
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'message' => "Transition {$existing['statut']} → {$statut} non autorisée",
        ]);
        exit;
    }

    // ── Mise à jour ───────────────────────────────────────────
    $pdo->prepare("UPDATE pro_events SET statut = ? WHERE id = ?")
        ->execute([$statut, $eventId]);

    // Log
    $pdo->prepare("INSERT INTO pro_logs (member_id, event_id, action, details, ip) VALUES (?,?,?,?,?)")
        ->execute([
            $authUser['member_id'],
            $eventId,
            'change_status',
            "{$existing['statut']} → $statut",
            $_SERVER['REMOTE_ADDR'] ?? null
        ]);

    echo json_encode([
        'success'  => true,
        'message'  => "Statut mis à jour : $statut",
        'event_id' => $eventId,
        'statut'   => $statut,
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur base de données']);
    error_log('[pro/change-status] ' . $e->getMessage());
}
