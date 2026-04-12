<?php
// ============================================================
//  create-event.php — Créer une nouvelle partie Pro
//  POST https://viendez.com/api/pro/create-event.php
//  Authorization: Bearer <token>
//  Body JSON : { titre, description, lieu, date_event,
//                max_joueurs, buy_in, devise, is_public,
//                structure_id, rake, bounty, jetons, nb_recaves,
//                recave_montant, recave_jetons, bonus, nb_tables }
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
    $structureId   = isset($body['structure_id']) ? (int)$body['structure_id'] : 1;
    $rake          = min(25, max(0, (int)($body['rake']           ?? 5)));
    $bounty        = min(10, max(0, (int)($body['bounty']         ?? 0)));
    $jetons        = max(0,        (int)($body['jetons']          ?? 35000));
    $nbRecaves     = max(0,        (int)($body['nb_recaves']      ?? 1));
    $recaveMontant = max(0,        (int)($body['recave_montant']  ?? 10));
    $recaveJetons  = max(0,        (int)($body['recave_jetons']   ?? 40000));
    $bonus         = in_array((int)($body['bonus'] ?? 0), [0, 5000]) ? (int)$body['bonus'] : 0;
    $nbTables      = max(1,        (int)($body['nb_tables']       ?? 2));
    $idRake        = $rake > 0 ? (int)($rake / 5) : 1;

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

    // ── Insertion dans `activite` ─────────────────────────────
    $stmt = $pdo->prepare("
        INSERT INTO `activite`
            (`titre-activite`, `description`, `ville`, `date_depart`, `places`, `buyin`, `devise`,
             `statut`, `is_public`, `id-membre`, `id_structure`,
             `rake`, `id_rake`, `bounty`, `jetons`, `recave`, `recave_montant`, `recave_jetons`, `bonus`, `nb-tables`)
        VALUES
            (:titre, :desc, :lieu, :date, :max, :buyin, :devise,
             'brouillon', :pub, :orgid, :strucid,
             :rake, :idrake, :bounty, :jetons, :nbrecaves, :recavemontant, :recavejetons, :bonus, :nbtables)
    ");
    $stmt->execute([
        ':titre'         => $titre,
        ':desc'          => $description,
        ':lieu'          => $lieu,
        ':date'          => $parsedDate,
        ':max'           => $maxJoueurs,
        ':buyin'         => $buyIn,
        ':devise'        => $devise,
        ':pub'           => $isPublic,
        ':orgid'         => $authUser['member_id'],
        ':strucid'       => $structureId,
        ':rake'          => $rake,
        ':idrake'        => $idRake,
        ':bounty'        => $bounty,
        ':jetons'        => $jetons,
        ':nbrecaves'     => $nbRecaves,
        ':recavemontant' => $recaveMontant,
        ':recavejetons'  => $recaveJetons,
        ':bonus'         => $bonus,
        ':nbtables'      => $nbTables,
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
        SELECT a.*,
               DATE_FORMAT(a.`date_depart`, '%Y-%m-%d %H:%i:%s') AS date_event,
               m.`pseudo` AS organizer_pseudo,
               0 AS nb_inscrits
        FROM `activite` a
        JOIN `membres` m ON m.`id-membre` = a.`id-membre`
        WHERE a.`id-activite` = ?
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
        'id'               => (int)$r['id-activite'],
        'titre'            => $r['titre-activite'] ?? '',
        'description'      => $r['description'] ?? '',
        'lieu'             => $r['ville'] ?? '',
        'date_event'       => $r['date_event'] ?? null,
        'max_joueurs'      => (int)($r['places'] ?? 0),
        'buy_in'           => (float)($r['buyin'] ?? 0),
        'devise'           => $r['devise'] ?? 'EUR',
        'statut'           => $r['statut'] ?? 'brouillon',
        'is_public'        => (bool)($r['is_public'] ?? 1),
        'organizer_id'     => (int)($r['id-membre'] ?? 0),
        'organizer_pseudo' => $r['organizer_pseudo'] ?? '',
        'activity_id'      => null,
        'nb_inscrits'      => (int)($r['nb_inscrits'] ?? 0),
        'created_at'       => $r['created_at'] ?? null,
        'structure_id'     => (int)($r['id_structure'] ?? 1),
        'rake'             => (int)($r['rake'] ?? 5),
        'bounty'           => (int)($r['bounty'] ?? 0),
        'jetons'           => (int)($r['jetons'] ?? 35000),
        'nb_recaves'       => (int)($r['recave'] ?? 1),
        'recave_montant'   => (int)($r['recave_montant'] ?? 10),
        'recave_jetons'    => (int)($r['recave_jetons'] ?? 40000),
        'bonus'            => (int)($r['bonus'] ?? 0),
        'nb_tables'        => (int)($r['nb-tables'] ?? 2),
    ];
}
