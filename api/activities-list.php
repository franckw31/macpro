<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    $pdo = new PDO(
        'mysql:host=localhost;dbname=dbs9616600;charset=utf8mb4',
        'root',
        'Kookies7*',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );

    $stmt = $pdo->query("
        SELECT a.`id-activite` AS id,
               a.`date_depart` AS date,
               a.`titre-activite` AS title,
               a.`ville` AS city,
               a.`buyin`,
               a.`rake`,
               m.`pseudo` AS organisateur,
               COUNT(p.`id-participation`) AS participants_count
        FROM `activite` a
        LEFT JOIN `membres` m ON m.`id-membre` = a.`id-membre`
        LEFT JOIN `participation` p
            ON p.`id-activite` = a.`id-activite`
            AND COALESCE(p.`option`, 'None') NOT IN ('None', 'Desinscrit')
        WHERE a.`date_depart` >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY a.`id-activite`, a.`date_depart`, a.`titre-activite`, a.`ville`, a.`buyin`, a.`rake`, m.`pseudo`
        ORDER BY a.`date_depart` ASC
    ");

    $rows = $stmt->fetchAll();

    // Trouver l'index de la prochaine activité à venir (ou la dernière passée)
    $currentIndex = max(0, count($rows) - 1);
    $now = new DateTime();
    foreach ($rows as $i => $row) {
        $d = new DateTime($row['date']);
        if ($d >= $now) {
            $currentIndex = $i;
            break;
        }
    }

    $activities = [];
    foreach ($rows as $row) {
        $activities[] = [
            'id'           => (int)$row['id'],
            'date'         => $row['date'],
            'title'        => $row['title'],
            'city'         => $row['city'],
            'buyin'        => (int)$row['buyin'],
            'rake'         => (int)$row['rake'],
            'count'        => (int)$row['participants_count'],
            'organisateur' => $row['organisateur'],
        ];
    }

    echo json_encode([
        'success'       => true,
        'activities'    => $activities,
        'current_index' => $currentIndex,
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
?>
