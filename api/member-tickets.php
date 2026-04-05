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

    $memberId = isset($_GET['member_id']) ? (int)$_GET['member_id'] : 0;
    if ($memberId === 0 && isset($_GET['pseudo'])) {
        $stmtM = $pdo->prepare("SELECT `id-membre` FROM membres WHERE pseudo = ? LIMIT 1");
        $stmtM->execute([trim($_GET['pseudo'])]);
        $m = $stmtM->fetch();
        $memberId = $m ? (int)$m['id-membre'] : 0;
    }

    if ($memberId === 0) {
        echo json_encode(['success' => false, 'error' => 'member_id or pseudo required']);
        exit;
    }

    // allow optional sorting direction: ?sort=asc or ?sort=desc (default desc)
    $sort = 'DESC';
    if (isset($_GET['sort']) && strtolower($_GET['sort']) === 'asc') { $sort = 'ASC'; }

    $stmt = $pdo->prepare("SELECT ci.`id-indiv` AS id_indiv, ci.id_col AS id_col, ci.`aff_rake` AS aff_rake, ci.`date` AS date, c.nom AS name, c.valeur AS value
                           FROM `collections-individu` ci
                           LEFT JOIN `collections` c ON ci.id_col = c.`id_collection`
                           WHERE ci.`id-indiv` = ?
                           ORDER BY ci.`date` $sort
                           LIMIT 500");
    $stmt->execute([$memberId]);
    $rows = $stmt->fetchAll();

    $tickets = array_map(function($r){
        return [
            'id_indiv' => isset($r['id_indiv']) ? (int)$r['id_indiv'] : 0,
            'collection_id' => isset($r['id_col']) ? (int)$r['id_col'] : 0,
            'name' => isset($r['name']) ? $r['name'] : '',
            'value' => isset($r['value']) ? (float)$r['value'] : 0,
            'date' => isset($r['date']) ? $r['date'] : '',
            'aff_rake' => isset($r['aff_rake']) ? (int)$r['aff_rake'] : 0,
        ];
    }, $rows);

    // compute monthly totals (YYYY-MM) for months present in this member's tickets
    $months = [];
    foreach ($tickets as $t) {
        if (!empty($t['date'])) {
            $ym = substr($t['date'], 0, 7); // YYYY-MM
            if ($ym && !in_array($ym, $months)) { $months[] = $ym; }
        }
    }

    $monthly_totals = new stdClass();
    if (!empty($months)) {
        $placeholders = implode(',', array_fill(0, count($months), '?'));
        $sql = "SELECT DATE_FORMAT(`date`, '%Y-%m') AS ym, COUNT(*) AS total FROM `collections-individu` WHERE DATE_FORMAT(`date`, '%Y-%m') IN ($placeholders) GROUP BY ym";
        $stmt2 = $pdo->prepare($sql);
        $stmt2->execute($months);
        $res = $stmt2->fetchAll();
        foreach ($res as $r) {
            $monthly_totals->{$r['ym']} = (int)$r['total'];
        }
    }

    echo json_encode(['success' => true, 'member_id' => $memberId, 'count' => count($tickets), 'tickets' => $tickets, 'monthly_totals' => $monthly_totals]);

} catch (PDOException $e) {
    http_response_code(500);
    // Log full exception server-side but do not expose details to the client
    error_log('api/member-tickets.php PDOException: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'internal_server_error']);
}

?>
