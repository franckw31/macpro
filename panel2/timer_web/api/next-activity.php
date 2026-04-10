<?php
require __DIR__ . '/db.php';

try{
    $stmt = $pdo->prepare("SELECT a.*, (SELECT COUNT(*) FROM registrations r WHERE r.activity_id=a.id) AS participants_count FROM activities a WHERE a.date >= NOW() ORDER BY a.date ASC LIMIT 1");
    $stmt->execute();
    $activity = $stmt->fetch();
    if(!$activity){
        // fallback: latest activity
        $stmt = $pdo->prepare("SELECT a.*, (SELECT COUNT(*) FROM registrations r WHERE r.activity_id=a.id) AS participants_count FROM activities a ORDER BY a.date DESC LIMIT 1");
        $stmt->execute();
        $activity = $stmt->fetch();
    }
    if(!$activity){
        echo json_encode(['success'=>false,'message'=>'no activity']);
        exit;
    }
    // map fields similar to existing JS
    $out = [
        'success' => true,
        'id' => (int)$activity['id'],
        'titre-activite' => $activity['titre_activite'],
        'date' => $activity['date'],
        'buyin' => (int)$activity['buyin'],
        'rake' => (int)$activity['rake'],
        'participants_count' => (int)$activity['participants_count']
    ];
    echo json_encode($out);
}catch(Exception $e){
    http_response_code(500);
    echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}
?>