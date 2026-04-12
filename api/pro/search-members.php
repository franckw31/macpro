<?php
// ============================================================
//  search-members.php — Recherche de membres pour inscription
//  GET ?q=terme&event_id=X
//  Authorization: Bearer <token>
//  Retourne les membres correspondant au pseudo / prénom / nom / email
//  avec un flag is_registered si event_id fourni
// ============================================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

try {
    require_once __DIR__ . '/_auth.php';   // → $authUser, $pdo

    $q       = trim($_GET['q']        ?? '');
    $eventId = isset($_GET['event_id']) ? (int)$_GET['event_id'] : 0;

    if (mb_strlen($q) < 2) {
        echo json_encode(['success' => true, 'members' => [], 'total' => 0]);
        exit;
    }

    $like = '%' . $q . '%';

    $stmt = $pdo->prepare("
        SELECT
            m.`id-membre`                              AS member_id,
            m.`pseudo`,
            COALESCE(m.`fname`,  '')                   AS fname,
            COALESCE(m.`lname`,  '')                   AS lname,
            COALESCE(m.`email`,  '')                   AS email,
            COALESCE(m.`photo`,  'avatar.png')         AS photo,
            CASE WHEN p.`id-participation` IS NOT NULL THEN 1 ELSE 0 END AS is_registered,
            COALESCE(p.`option`, '')                    AS reg_option
        FROM `membres` m
        LEFT JOIN `participation` p
               ON p.`id-membre` = m.`id-membre` AND p.`id-activite` = ?
        WHERE (m.`pseudo` LIKE ?
            OR m.`fname`  LIKE ?
            OR m.`lname`  LIKE ?
            OR m.`email`  LIKE ?)
        ORDER BY m.`pseudo` ASC
        LIMIT 30
    ");
    $stmt->execute([$eventId, $like, $like, $like, $like]);
    $rows = $stmt->fetchAll();

    $optMap = [
        'Inscrit'  => 'inscrit',
        'Option'   => 'liste_attente',
        'Annulé'   => 'absent',
    ];

    $members = array_map(fn($r) => [
        'member_id'     => (int)$r['member_id'],
        'pseudo'        => $r['pseudo'],
        'fname'         => $r['fname'],
        'lname'         => $r['lname'],
        'email'         => $r['email'],
        'photo_url'     => 'https://viendez.com/images/faces/' . $r['photo'],
        'is_registered' => (bool)$r['is_registered'],
        'reg_statut'    => $optMap[$r['reg_option']] ?? '',
    ], $rows);

    echo json_encode([
        'success' => true,
        'members' => $members,
        'total'   => count($members),
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur base de données']);
    error_log('[pro/search-members] ' . $e->getMessage());
}
