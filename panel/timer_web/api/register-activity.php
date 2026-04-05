<?php
require __DIR__ . '/db.php';

$input = json_decode(file_get_contents('php://input'), true);
if(!$input){ echo json_encode(['success'=>false,'error'=>'invalid json']); exit; }
$activity_id = isset($input['activity_id']) ? (int)$input['activity_id'] : 0;
$anonyme = isset($input['anonyme']) ? (bool)$input['anonyme'] : false;
$is_option = isset($input['is_option']) ? (bool)$input['is_option'] : false;
$latereg = isset($input['latereg']) ? (bool)$input['latereg'] : false;
$pseudo = isset($input['pseudo']) ? trim($input['pseudo']) : null;

if(!$activity_id){ echo json_encode(['success'=>false,'error'=>'missing activity_id']); exit; }

try{
    // If user_id provided, use it; otherwise store pseudo / anonymous
    $user_id = isset($input['user_id']) ? (int)$input['user_id'] : null;

    // Check existing registration (by user_id or pseudo)
    if($user_id){
        $stmt = $pdo->prepare("SELECT id FROM registrations WHERE activity_id = :aid AND user_id = :uid");
        $stmt->execute([':aid'=>$activity_id,':uid'=>$user_id]);
        $exists = $stmt->fetch();
    }else if($pseudo){
        $stmt = $pdo->prepare("SELECT id FROM registrations WHERE activity_id = :aid AND pseudo = :pseudo");
        $stmt->execute([':aid'=>$activity_id,':pseudo'=>$pseudo]);
        $exists = $stmt->fetch();
    }else{
        $exists = false;
    }

    if($exists){
        echo json_encode(['success'=>true,'registered'=>false,'message'=>'already registered']);
        exit;
    }

    $ins = $pdo->prepare("INSERT INTO registrations (activity_id, user_id, pseudo, anonyme, is_option, latereg) VALUES (:aid, :uid, :pseudo, :an, :opt, :lr)");
    $ins->execute([':aid'=>$activity_id, ':uid'=>$user_id, ':pseudo'=>$pseudo, ':an'=>$anonyme?1:0, ':opt'=>$is_option?1:0, ':lr'=>$latereg?1:0]);
    echo json_encode(['success'=>true,'registered'=>true]);
}catch(Exception $e){ http_response_code(500); echo json_encode(['success'=>false,'error'=>$e->getMessage()]); }
?>