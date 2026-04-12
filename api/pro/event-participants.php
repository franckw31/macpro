<?php
// ============================================================
//  event-participants.php — Liste des joueurs inscrits à une partie Pro
//  GET https://viendez.com/api/pro/event-participants.php?event_id=XX
//  Authorization: Bearer <token>
// ============================================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

try {
    require_once __DIR__ . '/_auth.php';   // → $authUser, $pdo

    $eventId = isset($_GET['event_id']) ? (int)$_GET['event_id'] : 0;
    if ($eventId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'event_id manquant']);
        exit;
    }

    // ── Vérifier que la partie existe et que l'utilisateur a le droit de la voir ──
    $stmtEvent = $pdo->prepare("
        SELECT `id-membre` AS organizer_id, COALESCE(`statut`,'publie') AS statut,
               COALESCE(`is_public`,1) AS is_public, `titre-activite` AS titre
        FROM `activite` WHERE `id-activite` = ? LIMIT 1
    ");
    $stmtEvent->execute([$eventId]);
    $event = $stmtEvent->fetch();

    if (!$event) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Partie introuvable']);
        exit;
    }

    // Partie privée : seul l'organisateur ou un admin peut voir les inscrits
    $isOwner = (int)$event['organizer_id'] === $authUser['member_id'];
    if (!$event['is_public'] && !$isOwner && !$authUser['is_admin']) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Accès refusé à cette partie privée']);
        exit;
    }

    // Correspondance participation.option → statut Pro
    $optMap = [
        'Inscrit'  => 'inscrit',
        'Option'   => 'liste_attente',
        'Annulé'   => 'absent',
    ];

    // ── Récupérer les inscriptions ────────────────────────────
    $stmtReg = $pdo->prepare("
        SELECT
            p.`id-participation`                            AS id,
            p.`id-activite`                                 AS event_id,
            p.`id-membre`                                   AS member_id,
            m.`pseudo`,
            COALESCE(m.`photo`, 'avatar.png')               AS photo,
            p.`option`,
            COALESCE(p.`anonyme`, 0)                        AS is_private,
            DATE_FORMAT(p.`ds`, '%Y-%m-%d %H:%i:%s')        AS inscrit_le
        FROM `participation` p
        JOIN `membres` m ON m.`id-membre` = p.`id-membre`
        WHERE p.`id-activite` = ?
          AND p.`option` IN ('Inscrit','Option','Annulé')
        ORDER BY
            FIELD(p.`option`, 'Inscrit', 'Option', 'Annulé'),
            p.`ds` ASC
    ");
    $stmtReg->execute([$eventId]);
    $rows = $stmtReg->fetchAll();

    $registrations = array_map(function(array $r) use ($isOwner, $authUser, $optMap): array {
        return [
            'id'         => (int)$r['id'],
            'event_id'   => (int)$r['event_id'],
            'member_id'  => (int)$r['member_id'],
            'pseudo'     => $r['pseudo'],
            'photo_url'  => 'https://viendez.com/images/faces/' . $r['photo'],
            'statut'     => $optMap[$r['option']] ?? 'inscrit',
            'is_private' => (bool)$r['is_private'],
            'inscrit_le' => $r['inscrit_le'],
        ];
    }, $rows);

    // Compteurs par statut
    $counts = array_count_values(array_column($registrations, 'statut'));

    echo json_encode([
        'success'       => true,
        'event_id'      => $eventId,
        'event_titre'   => $event['titre'],
        'registrations' => $registrations,
        'total'         => count($registrations),
        'counts'        => [
            'inscrits'       => $counts['inscrit']       ?? 0,
            'confirmes'      => $counts['confirme']      ?? 0,
            'liste_attente'  => $counts['liste_attente'] ?? 0,
            'absents'        => $counts['absent']        ?? 0,
        ],
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur base de données']);
    error_log('[pro/event-participants] ' . $e->getMessage());
}
