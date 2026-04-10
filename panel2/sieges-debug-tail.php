<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/include/config.php';
if (!isset($_SESSION['id']) || intval($_SESSION['id']) !== 265) {
    http_response_code(403);
    echo json_encode(['ok'=>false,'error'=>'Not authorized']);
    exit;
}
$act = isset($_GET['ac']) ? intval($_GET['ac']) : 0;
if (!$act) { echo json_encode(['ok'=>false,'error'=>'Missing ac']); exit; }
$debugFile = __DIR__ . '/tmp/sieges-debug-' . $act . '.log';
$siegesLog = __DIR__ . '/tmp/sieges.log';
$debug = '';
$logTail = '';
if (file_exists($debugFile)) {
    $debug = file_get_contents($debugFile);
}
if (file_exists($siegesLog)) {
    $data = file_get_contents($siegesLog);
    $logTail = mb_substr($data, -3000);
}
if ($debug === '' && $logTail === '') {
    http_response_code(404);
    echo json_encode(['ok'=>false,'error'=>'No debug output yet']);
    exit;
}
echo json_encode(['ok'=>true,'debug'=>$debug,'sieges_log_tail'=>$logTail]);
