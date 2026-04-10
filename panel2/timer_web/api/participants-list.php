<?php
require __DIR__ . '/db.php';
$activity_id = isset($_GET['activity_id']) ? (int)$_GET['activity_id'] : 0;
if(!$activity_id){ echo json_encode(['success'=>false,'error'=>'missing activity_id']); exit; }
try{
    $stmt = $pdo->prepare("SELECT r.id as reg_id, r.pseudo, r.user_id, res.gain, res.classement FROM registrations r LEFT JOIN results res ON res.activity_id = r.activity_id AND res.user_id = r.user_id AND res.user_id IS NOT NULL WHERE r.activity_id = :aid");
    $stmt->execute([':aid'=>$activity_id]);
    $rows = $stmt->fetchAll();
    $participants = [];
    foreach($rows as $r){
        $participants[] = [
            'pseudo' => $r['pseudo']?:'(inconnu)',
            'gain' => isset($r['gain'])?(int)$r['gain']:0,
            'classement' => isset($r['classement'])?$r['classement']:null
        ];
    }
    echo json_encode(['success'=>true,'participants'=>$participants]);
}catch(Exception $e){ http_response_code(500); echo json_encode(['success'=>false,'error'=>$e->getMessage()]); }
?>