<?php
// ============================================================
//  update-event.php — Modifier une partie Pro existante
//  POST https://viendez.com/api/pro/update-event.php
//  Authorization: Bearer <token>
//  Body JSON : { event_id, titre, description, lieu,
//                date_event, max_joueurs, buy_in, devise, is_public,
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

    $body    = json_decode(file_get_contents('php://input'), true) ?? [];
    $eventId = (int)($body['event_id'] ?? 0);

    if ($eventId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'event_id manquant']);
        exit;
    }

    // ── Vérifier que l'event appartient à cet organisateur (ou admin) ──
    $stmtCheck = $pdo->prepare("SELECT `id-membre` AS organizer_id, COALESCE(`statut`,'publie') AS statut FROM `activite` WHERE `id-activite` = ? LIMIT 1");
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

    // ── Récupérer les valeurs actuelles (fallback si champ absent du body) ──
    $stmtOld = $pdo->prepare("SELECT `titre-activite` AS titre, COALESCE(`description`,'') AS description, `ville` AS lieu, `date_depart` AS date_event, COALESCE(`places`,16) AS max_joueurs, COALESCE(`buyin`,0) AS buy_in, COALESCE(`devise`,'EUR') AS devise, COALESCE(`is_public`,1) AS is_public, COALESCE(`id_structure`,1) AS structure_id, COALESCE(`rake`,5) AS rake, COALESCE(`bounty`,0) AS bounty, COALESCE(`jetons`,35000) AS jetons, COALESCE(`recave`,1) AS nb_recaves, COALESCE(`recave_montant`,10) AS recave_montant, COALESCE(`recave_jetons`,40000) AS recave_jetons, COALESCE(`bonus`,0) AS bonus, COALESCE(`nb-tables`,2) AS nb_tables FROM `activite` WHERE `id-activite` = ? LIMIT 1");
    $stmtOld->execute([$eventId]);
    $old = $stmtOld->fetch();

    $titre         = trim($body['titre']         ?? $old['titre']);
    $description   = trim($body['description']   ?? $old['description']);
    $lieu          = trim($body['lieu']          ?? $old['lieu']);
    $maxJoueurs    = isset($body['max_joueurs'])  ? (int)$body['max_joueurs']    : (int)$old['max_joueurs'];
    $buyIn         = isset($body['buy_in'])       ? (float)$body['buy_in']       : (float)$old['buy_in'];
    $devise        = trim($body['devise']         ?? $old['devise']);
    $isPublic      = isset($body['is_public'])    ? (int)(bool)$body['is_public'] : (int)$old['is_public'];
    $structureId   = isset($body['structure_id']) ? (int)$body['structure_id']   : (int)$old['structure_id'];
    $rake          = isset($body['rake'])          ? min(25,max(0,(int)$body['rake']))          : (int)$old['rake'];
    $bounty        = isset($body['bounty'])        ? min(10,max(0,(int)$body['bounty']))        : (int)$old['bounty'];
    $jetons        = isset($body['jetons'])        ? max(0,(int)$body['jetons'])                : (int)$old['jetons'];
    $nbRecaves     = isset($body['nb_recaves'])    ? max(0,(int)$body['nb_recaves'])            : (int)$old['nb_recaves'];
    $recaveMontant = isset($body['recave_montant'])? max(0,(int)$body['recave_montant'])        : (int)$old['recave_montant'];
    $recaveJetons  = isset($body['recave_jetons']) ? max(0,(int)$body['recave_jetons'])         : (int)$old['recave_jetons'];
    $bonus         = isset($body['bonus'])         ? max(0,(int)$body['bonus'])                 : (int)$old['bonus'];
    $nbTables      = isset($body['nb_tables'])     ? max(1,(int)$body['nb_tables'])             : (int)$old['nb_tables'];

    $dateEvent   = trim($body['date_event']  ?? '');
    $parsedDate  = $dateEvent
        ? date('Y-m-d H:i:s', strtotime($dateEvent))
        : $old['date_event'];

    if ($titre === '' || $lieu === '' || $maxJoueurs < 2) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Champs obligatoires manquants ou invalides']);
        exit;
    }

    // ── Mise à jour dans `activite` ───────────────────────────
    $pdo->prepare("
        UPDATE `activite` SET
            `titre-activite`  = :titre,
            `description`     = :desc,
            `ville`           = :lieu,
            `date_depart`     = :date,
            `places`          = :max,
            `buyin`           = :buyin,
            `devise`          = :devise,
            `is_public`       = :pub,
            `id_structure`    = :strucid,
            `rake`            = :rake,
            `bounty`          = :bounty,
            `jetons`          = :jetons,
            `recave`          = :nbrecaves,
            `recave_montant`  = :recavemontant,
            `recave_jetons`   = :recavejetons,
            `bonus`           = :bonus,
            `nb-tables`       = :nbtables
        WHERE `id-activite` = :id
    ")->execute([
        ':titre'         => $titre,
        ':desc'          => $description,
        ':lieu'          => $lieu,
        ':date'          => $parsedDate,
        ':max'           => $maxJoueurs,
        ':buyin'         => $buyIn,
        ':devise'        => $devise,
        ':pub'           => $isPublic,
        ':strucid'       => $structureId,
        ':rake'          => $rake,
        ':bounty'        => $bounty,
        ':jetons'        => $jetons,
        ':nbrecaves'     => $nbRecaves,
        ':recavemontant' => $recaveMontant,
        ':recavejetons'  => $recaveJetons,
        ':bonus'         => $bonus,
        ':nbtables'      => $nbTables,
        ':id'            => $eventId,
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
        SELECT a.*,
               DATE_FORMAT(a.`date_depart`, '%Y-%m-%d %H:%i:%s') AS date_event,
               m.`pseudo` AS organizer_pseudo,
               COALESCE(r.nb, 0) AS nb_inscrits
        FROM `activite` a
        JOIN `membres` m ON m.`id-membre` = a.`id-membre`
        LEFT JOIN (
            SELECT event_id, COUNT(*) AS nb FROM `pro_registrations`
            WHERE statut IN ('inscrit','confirme') GROUP BY event_id
        ) r ON r.event_id = a.`id-activite`
        WHERE a.`id-activite` = ?
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
        'id'               => (int)$r['id-activite'],
        'titre'            => $r['titre-activite'] ?? '',
        'description'      => $r['description'] ?? '',
        'lieu'             => $r['ville'] ?? '',
        'date_event'       => $r['date_event'] ?? null,
        'max_joueurs'      => (int)($r['places'] ?? 0),
        'buy_in'           => (float)($r['buyin'] ?? 0),
        'devise'           => $r['devise'] ?? 'EUR',
        'statut'           => $r['statut'] ?? 'publie',
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
