<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

try {
    $pdo = new PDO(
        'mysql:host=localhost;dbname=dbs9616600;charset=utf8mb4',
        'root',
        'Kookies7*',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );

    $pseudo = isset($_GET['pseudo']) ? trim($_GET['pseudo']) : '';
    $uid = isset($_GET['uid']) && is_numeric($_GET['uid']) ? intval($_GET['uid']) : 0;
    if ($pseudo === '' && $uid === 0) {
        echo json_encode(['success' => false, 'error' => 'pseudo ou uid requis']);
        exit;
    }

    if ($uid === 0) {
        $stmtM = $pdo->prepare("SELECT `id-membre` FROM membres WHERE pseudo = ? LIMIT 1");
        $stmtM->execute([$pseudo]);
        $m = $stmtM->fetch();
        if (!$m) { echo json_encode(['success' => false, 'error' => 'Membre introuvable']); exit; }
        $uid = (int)$m['id-membre'];
    }

    // detect organizer columns present in activite
    $existing_cols = [];
    $colStmt = $pdo->query("SHOW COLUMNS FROM activite");
    if ($colStmt) { foreach ($colStmt->fetchAll(PDO::FETCH_ASSOC) as $c) { $existing_cols[] = $c['Field']; } }
    $candidates = ['id-membre','id_membre','id_membres','id_membre_organisateur','organisateur'];
    $used = array_values(array_intersect($candidates, $existing_cols));

    $exclude_clause = '';
    $paramsBase = [$uid];
    if (!empty($used)) {
        $parts = [];
        foreach ($used as $col) { $parts[] = "a.`" . $col . "` = ?"; $paramsBase[] = $uid; }
        $exclude_clause = ' AND NOT (' . implode(' OR ', $parts) . ')';
    }

    // pagination
    $per_page = isset($_GET['per_page']) && is_numeric($_GET['per_page']) ? max(1, min(100, intval($_GET['per_page']))) : 50;
    $page = isset($_GET['page']) && is_numeric($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $offset = ($page - 1) * $per_page;

    // Rake contributions: activities with a.rake > 0, exclude organizer activities (match panel)
    $rakeSql = "SELECT a.`id-activite` AS aid, COALESCE(a.`titre-activite`,'') AS title, a.`date_depart` AS date_depart, COALESCE(a.buyin,0) AS buyin, COALESCE(a.rake,0) AS rake, COALESCE(p.gain,0) AS gain, COALESCE(p.`option`,'') AS popt FROM participation p JOIN activite a ON p.`id-activite` = a.`id-activite` WHERE p.`id-membre` = ? AND COALESCE(p.`option`,'None') NOT IN ('Desinscrit','None') AND COALESCE(a.rake,0) > 0" . $exclude_clause . " ORDER BY a.`date_depart` DESC LIMIT " . intval($offset) . "," . intval($per_page);
    $rakeStmt = $pdo->prepare($rakeSql);
    $rakeParams = $paramsBase;
    $rakeStmt->execute($rakeParams);
    $rakeList = $rakeStmt->fetchAll();

    // Non-organizer participations: participations where the player is NOT the organizer (same exclusion)
    $nonOrgSql = "SELECT a.`id-activite` AS aid, COALESCE(a.`titre-activite`,'') AS title, a.`date_depart` AS date_depart, COALESCE(a.buyin,0) AS buyin, COALESCE(a.rake,0) AS rake, COALESCE(p.gain,0) AS gain, COALESCE(p.`option`,'') AS popt FROM participation p JOIN activite a ON p.`id-activite` = a.`id-activite` WHERE p.`id-membre` = ? AND COALESCE(p.`option`,'None') NOT IN ('Desinscrit','None')" . $exclude_clause . " ORDER BY a.`date_depart` DESC LIMIT " . intval($offset) . "," . intval($per_page);
    $nonOrgStmt = $pdo->prepare($nonOrgSql);
    $nonOrgStmt->execute($paramsBase);
    $nonOrgList = $nonOrgStmt->fetchAll();

    // counts
    $countRakeSql = "SELECT COUNT(*) AS c FROM participation p JOIN activite a ON p.`id-activite` = a.`id-activite` WHERE p.`id-membre` = ? AND COALESCE(p.`option`,'None') NOT IN ('Desinscrit','None') AND COALESCE(a.rake,0) > 0" . $exclude_clause;
    $countRakeStmt = $pdo->prepare($countRakeSql);
    $countRakeStmt->execute($paramsBase);
    $rakeCountRow = $countRakeStmt->fetch();
    $rakeTotal = $rakeCountRow ? (int)$rakeCountRow['c'] : 0;

    $countNonOrgSql = "SELECT COUNT(*) AS c FROM participation p JOIN activite a ON p.`id-activite` = a.`id-activite` WHERE p.`id-membre` = ? AND COALESCE(p.`option`,'None') NOT IN ('Desinscrit','None')" . $exclude_clause;
    $countNonOrgStmt = $pdo->prepare($countNonOrgSql);
    $countNonOrgStmt->execute($paramsBase);
    $nonOrgCountRow = $countNonOrgStmt->fetch();
    $nonOrgTotal = $nonOrgCountRow ? (int)$nonOrgCountRow['c'] : 0;

    echo json_encode([
        'success' => true,
        'uid' => $uid,
        'page' => $page,
        'per_page' => $per_page,
        'rake_total' => $rakeTotal,
        'rake_contrib' => $rakeList,
        'non_organizer_total' => $nonOrgTotal,
        'non_organizer' => $nonOrgList
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erreur base de données']);
}
