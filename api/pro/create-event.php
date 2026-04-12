<?php
// ============================================================
//  create-event.php — Créer une nouvelle partie Pro
//  POST https://viendez.com/api/pro/create-event.php
//  Authorization: Bearer <token>
//  Body JSON : { titre, description, lieu, date_event,
//                max_joueurs, buy_in, devise, is_public,
//                structure_id? }
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

    // Seuls les organisateurs et admins peuvent créer des parties
    if (!$authUser['is_organizer']) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Accès réservé aux organisateurs']);
        exit;
    }

    $body  = json_decode(file_get_contents('php://input'), true) ?? [];

    // ── Validation ────────────────────────────────────────────
    $titre      = trim($body['titre']       ?? '');
    $lieu       = trim($body['lieu']        ?? '');
    $dateEvent  = trim($body['date_event']  ?? '');
    $maxJoueurs = (int)($body['max_joueurs'] ?? 0);
    $buyIn      = (float)($body['buy_in']   ?? 0);
    $devise     = trim($body['devise']       ?? 'EUR');
    $description = trim($body['description'] ?? '');
    $isPublic   = isset($body['is_public'])  ? (int)(bool)$body['is_public'] : 1;
    $structureId = isset($body['structure_id']) ? (int)$body['structure_id'] : null;

    if ($titre === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Le titre est obligatoire']);
        exit;
    }
    if ($lieu === '') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Le lieu est obligatoire']);
        exit;
    }
    if ($maxJoueurs < 2) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Nombre de joueurs min : 2']);
        exit;
    }

    // Normaliser la date (ISO8601 → MySQL DATETIME)
    $parsedDate = date('Y-m-d H:i:s', strtotime($dateEvent));
    if (!$parsedDate || $parsedDate === '1970-01-01 00:00:00') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Date invalide']);
        exit;
    }

    // ── Insertion ─────────────────────────────────────────────
    $stmt = $pdo->prepare("
        INSERT INTO `pro_events`
            (titre, description, lieu, date_event, max_joueurs, buy_in, devise,
             statut, is_public, organizer_id, activity_id)
        VALUES
            (:titre, :desc, :lieu, :date, :max, :buyin, :devise,
             'brouillon', :pub, :orgid, :actid)
    ");
    $stmt->execute([
        ':titre'  => $titre,
        ':desc'   => $description,
        ':lieu'   => $lieu,
        ':date'   => $parsedDate,
        ':max'    => $maxJoueurs,
        ':buyin'  => $buyIn,
        ':devise' => $devise,
        ':pub'    => $isPublic,
        ':orgid'  => $authUser['member_id'],
        ':actid'  => $structureId,   // activity_id (peut être null ou un id_structure)
    ]);

    $newId = (int)$pdo->lastInsertId();

    // Log
    $pdo->prepare("INSERT INTO pro_logs (member_id, event_id, action, details, ip) VALUES (?,?,?,?,?)")
        ->execute([
            $authUser['member_id'],
            $newId,
            'create_event',
            "titre: $titre | lieu: $lieu | date: $parsedDate",
            $_SERVER['REMOTE_ADDR'] ?? null
        ]);

    // ── Retourner la partie créée ─────────────────────────────
    $newEvent = $pdo->prepare("
        SELECT e.*,
               DATE_FORMAT(e.date_event, '%Y-%m-%d %H:%i:%s') AS date_event,
               m.pseudo AS organizer_pseudo,
               0 AS nb_inscrits
        FROM `pro_events` e
        JOIN `membres` m ON m.`id-membre` = e.organizer_id
        WHERE e.id = ?
    ");
    $newEvent->execute([$newId]);
    $row = $newEvent->fetch();

    echo json_encode([
        'success'  => true,
        'message'  => 'Partie créée avec succès',
        'event_id' => $newId,
        'event'    => formatProEvent($row),
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur base de données']);
    error_log('[pro/create-event] ' . $e->getMessage());
}

// ── Helper ────────────────────────────────────────────────────
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
        'created_at'       => $r['created_at'] ?? date('Y-m-d H:i:s'),
    ];
}
