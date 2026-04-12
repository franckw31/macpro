<?php
// ============================================================
//  update-event.php — Modifier une partie Pro existante
//  POST https://viendez.com/api/pro/update-event.php
//  Authorization: Bearer <token>
//  Body JSON : { event_id, titre, description, lieu,
//                date_event, max_joueurs, buy_in, devise, is_public }
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

    // ── Vérifier que l'event appartient à cet organisateur (ou admin) ──
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
        echo json_encode(['success' => false, 'message' => 'Vous n\'êtes pas l\'organisateur de cette partie']);
        exit;
    }
    if (in_array($existing['statut'], ['termine', 'annule'])) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Impossible de modifier une partie terminée ou annulée']);
        exit;
    }

    // ── Récupérer les nouvelles valeurs (fallback sur existantes) ──
    $stmtOld = $pdo->prepare("SELECT * FROM pro_events WHERE id = ? LIMIT 1");
    $stmtOld->execute([$eventId]);
    $old = $stmtOld->fetch();

    $titre       = trim($body['titre']       ?? $old['titre']);
    $description = trim($body['description'] ?? $old['description']);
    $lieu        = trim($body['lieu']        ?? $old['lieu']);
    $maxJoueurs  = isset($body['max_joueurs']) ? (int)$body['max_joueurs'] : (int)$old['max_joueurs'];
    $buyIn       = isset($body['buy_in'])     ? (float)$body['buy_in']    : (float)$old['buy_in'];
    $devise      = trim($body['devise']       ?? $old['devise']);
    $isPublic    = isset($body['is_public'])  ? (int)(bool)$body['is_public'] : (int)$old['is_public'];

    $dateEvent   = trim($body['date_event']  ?? '');
    $parsedDate  = $dateEvent
        ? date('Y-m-d H:i:s', strtotime($dateEvent))
        : $old['date_event'];

    if ($titre === '' || $lieu === '' || $maxJoueurs < 2) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Champs obligatoires manquants ou invalides']);
        exit;
    }

    // ── Mise à jour ───────────────────────────────────────────
    $pdo->prepare("
        UPDATE `pro_events` SET
            titre       = :titre,
            description = :desc,
            lieu        = :lieu,
            date_event  = :date,
            max_joueurs = :max,
            buy_in      = :buyin,
            devise      = :devise,
            is_public   = :pub
        WHERE id = :id
    ")->execute([
        ':titre'  => $titre,
        ':desc'   => $description,
        ':lieu'   => $lieu,
        ':date'   => $parsedDate,
        ':max'    => $maxJoueurs,
        ':buyin'  => $buyIn,
        ':devise' => $devise,
        ':pub'    => $isPublic,
        ':id'     => $eventId,
    ]);

    // Log
    $pdo->prepare("INSERT INTO pro_logs (member_id, event_id, action, details, ip) VALUES (?,?,?,?,?)")
        ->execute([
            $authUser['member_id'],
            $eventId,
            'update_event',
            "titre: $titre | date: $parsedDate",
            $_SERVER['REMOTE_ADDR'] ?? null
        ]);

    // ── Retourner la partie mise à jour ───────────────────────
    $stmtNew = $pdo->prepare("
        SELECT e.*,
               DATE_FORMAT(e.date_event, '%Y-%m-%d %H:%i:%s') AS date_event,
               m.pseudo AS organizer_pseudo,
               COALESCE(r.nb, 0) AS nb_inscrits
        FROM `pro_events` e
        JOIN `membres` m ON m.`id-membre` = e.organizer_id
        LEFT JOIN (
            SELECT event_id, COUNT(*) AS nb FROM pro_registrations
            WHERE statut IN ('inscrit','confirme','liste_attente') GROUP BY event_id
        ) r ON r.event_id = e.id
        WHERE e.id = ?
    ");
    $stmtNew->execute([$eventId]);
    $row = $stmtNew->fetch();

    echo json_encode([
        'success' => true,
        'message' => 'Partie mise à jour',
        'event'   => formatProEvent($row),
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur base de données']);
    error_log('[pro/update-event] ' . $e->getMessage());
}

function formatProEvent(array $r): array {
    return [
        'id'               => (int)$r['id'],
        'titre'            => $r['titre'],
        'description'      => $r['description'] ?? '',
        'lieu'             => $r['lieu'],
        'date_event'       => $r['date_event'],
        'max_joueurs'      => (int)$r['max_joueurs'],
        'buy_in'           => (float)$r['buy_in'],
        'devise'           => $r['devise'],
        'statut'           => $r['statut'],
        'is_public'        => (bool)$r['is_public'],
        'organizer_id'     => (int)$r['organizer_id'],
        'organizer_pseudo' => $r['organizer_pseudo'] ?? '',
        'activity_id'      => isset($r['activity_id']) ? (int)$r['activity_id'] : null,
        'nb_inscrits'      => (int)($r['nb_inscrits'] ?? 0),
        'created_at'       => $r['created_at'] ?? '',
    ];
}
