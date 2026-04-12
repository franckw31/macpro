<?php
// ============================================================
//  my-events.php — Liste des parties de l'organisateur connecté
//  GET https://viendez.com/api/pro/my-events.php
//  Authorization: Bearer <token>
// ============================================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

try {
    require_once __DIR__ . '/_auth.php';   // → $authUser, $pdo

    // Filtres optionnels
    $statut = isset($_GET['statut']) ? trim($_GET['statut']) : null;
    $limit  = isset($_GET['limit'])  ? min((int)$_GET['limit'], 200) : 100;

    $where  = 'e.organizer_id = :oid';
    $params = [':oid' => $authUser['member_id']];

    if ($statut) {
        $where .= ' AND e.statut = :statut';
        $params[':statut'] = $statut;
    }

    $stmt = $pdo->prepare("
        SELECT
            e.id,
            e.titre,
            e.description,
            e.lieu,
            DATE_FORMAT(e.date_event, '%Y-%m-%d %H:%i:%s') AS date_event,
            e.max_joueurs,
            e.buy_in,
            e.devise,
            e.statut,
            e.is_public,
            e.organizer_id,
            m.pseudo            AS organizer_pseudo,
            e.activity_id,
            e.created_at,
            COALESCE(r.nb, 0)   AS nb_inscrits
        FROM `pro_events` e
        JOIN `membres` m ON m.`id-membre` = e.organizer_id
        LEFT JOIN (
            SELECT event_id, COUNT(*) AS nb
            FROM   `pro_registrations`
            WHERE  statut IN ('inscrit','confirme','liste_attente')
            GROUP BY event_id
        ) r ON r.event_id = e.id
        WHERE $where
        ORDER BY e.date_event DESC
        LIMIT $limit
    ");
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $events = array_map('formatEvent', $rows);

    echo json_encode(['success' => true, 'events' => $events]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur base de données']);
    error_log('[pro/my-events] ' . $e->getMessage());
}

// ── Helper ────────────────────────────────────────────────────
function formatEvent(array $r): array {
    return [
        'id'               => (int)$r['id'],
        'titre'            => $r['titre'],
        'description'      => $r['description'],
        'lieu'             => $r['lieu'],
        'date_event'       => $r['date_event'],
        'max_joueurs'      => (int)$r['max_joueurs'],
        'buy_in'           => (float)$r['buy_in'],
        'devise'           => $r['devise'],
        'statut'           => $r['statut'],
        'is_public'        => (bool)$r['is_public'],
        'organizer_id'     => (int)$r['organizer_id'],
        'organizer_pseudo' => $r['organizer_pseudo'],
        'activity_id'      => $r['activity_id'] ? (int)$r['activity_id'] : null,
        'nb_inscrits'      => (int)$r['nb_inscrits'],
        'created_at'       => $r['created_at'],
    ];
}
