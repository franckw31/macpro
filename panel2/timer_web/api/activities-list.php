<?php
require __DIR__ . '/db.php';

try{
    $limit = 30;
    $stmt = $pdo->prepare("
        SELECT a.`id-activite` AS id, a.`titre-activite` AS title, a.`date_depart` AS date, a.buyin, a.rake,
        (
            SELECT COUNT(*) FROM participation p
            WHERE p.`id-activite` = a.`id-activite`
              AND COALESCE(p.`option`, 'None') NOT IN ('None','Desinscrit')
        ) AS participants_count
        FROM activite a
        WHERE a.`date_depart` >= NOW()
        ORDER BY a.`date_depart` ASC
        LIMIT :lim
    ");
    $stmt->bindValue(':lim', (int)$limit, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll();
    $out = array_map(function($a){
        return [
            'id' => (int)$a['id'],
            'title' => $a['title'],
            'date' => $a['date'],
            'display_date' => null, // à calculer côté client si besoin
            'buyin' => isset($a['buyin']) ? (int)$a['buyin'] : null,
            'rake' => isset($a['rake']) ? (int)$a['rake'] : null,
            'participants_count' => isset($a['participants_count']) ? (int)$a['participants_count'] : 0
        ];
    }, $rows);
    echo json_encode(['success'=>true,'activities'=>$out]);
}catch(Exception $e){
    http_response_code(500);
    echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}

?>
