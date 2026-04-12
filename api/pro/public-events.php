<?php
// ============================================================
//  public-events.php — Liste des parties Pro publiques
//  Accessible sans authentification (ou avec token optionnel)
//  GET https://viendez.com/api/pro/public-events.php
//  Params optionnels : ?limit=50 &offset=0 &futur=1
// ============================================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

try {
    require_once __DIR__ . '/_db.php';   // $pdo, pas d'auth obligatoire

    $limit  = isset($_GET['limit'])  ? min((int)$_GET['limit'], 200)  : 50;
    $offset = isset($_GET['offset']) ? max(0, (int)$_GET['offset'])   : 0;
    $futur  = isset($_GET['futur'])  ? (bool)(int)$_GET['futur']      : false;

    $dateFilter = $futur ? 'AND e.date_event >= NOW()' : '';

    $stmt = $pdo->prepare("
        SELECT
            a.`id-activite`                                     AS event_id,
            a.`titre-activite`                                  AS titre,
            COALESCE(a.`description`, '')                       AS description,
            a.`ville`                                           AS lieu,
            DATE_FORMAT(a.`date_depart`, '%Y-%m-%d %H:%i:%s')  AS date_event,
            COALESCE(a.`places`, 0)                             AS max_joueurs,
            COALESCE(a.`buyin`, 0)                              AS buy_in,
            COALESCE(a.`devise`, 'EUR')                         AS devise,
            COALESCE(a.`statut`, 'publie')                      AS statut,
            COALESCE(a.`is_public`, 1)                          AS is_public,
            a.`id-membre`                                       AS organizer_id,
            m.`pseudo`                                          AS organizer_pseudo,
            COALESCE(r.nb, 0)                                       AS nb_inscrits,
            a.`created_at`,
            COALESCE(a.`id_structure`, 1)                       AS structure_id,
            COALESCE(a.`rake`, 5)                               AS rake,
            COALESCE(a.`bounty`, 0)                             AS bounty,
            COALESCE(a.`jetons`, 35000)                         AS jetons,
            COALESCE(a.`recave`, 1)                             AS nb_recaves,
            COALESCE(a.`recave_montant`, 10)                    AS recave_montant,
            COALESCE(a.`recave_jetons`, 40000)                  AS recave_jetons,
            COALESCE(a.`bonus`, 0)                              AS bonus,
            COALESCE(a.`nb-tables`, 2)                          AS nb_tables
        FROM `activite` a
        LEFT JOIN `membres` m ON m.`id-membre` = a.`id-membre`
        LEFT JOIN (
            SELECT `id-activite`, COUNT(*) AS nb
            FROM   `participation`
            WHERE  `option` = 'Inscrit'
            GROUP BY `id-activite`
        ) r ON r.`id-activite` = a.`id-activite`
        WHERE COALESCE(a.`is_public`, 1) = 1
          AND COALESCE(a.`statut`, 'publie') IN ('publie', 'en_cours')
          $dateFilter
        ORDER BY a.`date_depart` ASC
        LIMIT $limit OFFSET $offset
    ");
    $stmt->execute();
    $rows = $stmt->fetchAll();

    $events = array_map(function(array $r): array {
        return [
            'id'               => (int)$r['event_id'],
            'titre'            => $r['titre'] ?? '',
            'description'      => $r['description'] ?? '',
            'lieu'             => $r['lieu'] ?? '',
            'date_event'       => $r['date_event'] ?? null,
            'max_joueurs'      => (int)$r['max_joueurs'],
            'buy_in'           => (float)$r['buy_in'],
            'devise'           => $r['devise'] ?? 'EUR',
            'statut'           => $r['statut'] ?? 'publie',
            'is_public'        => (bool)$r['is_public'],
            'organizer_id'     => (int)$r['organizer_id'],
            'organizer_pseudo' => $r['organizer_pseudo'] ?? '',
            'activity_id'      => null,
            'nb_inscrits'      => (int)$r['nb_inscrits'],
            'created_at'       => $r['created_at'] ?? null,
            'structure_id'     => (int)$r['structure_id'],
            'rake'             => (int)$r['rake'],
            'bounty'           => (int)$r['bounty'],
            'jetons'           => (int)$r['jetons'],
            'nb_recaves'       => (int)$r['nb_recaves'],
            'recave_montant'   => (int)$r['recave_montant'],
            'recave_jetons'    => (int)$r['recave_jetons'],
            'bonus'            => (int)$r['bonus'],
            'nb_tables'        => (int)$r['nb_tables'],
        ];
    }, $rows);

    echo json_encode(['success' => true, 'events' => $events, 'count' => count($events)]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur base de données']);
    error_log('[pro/public-events] ' . $e->getMessage());
}
