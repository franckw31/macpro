<?php
// Endpoint to run a synchronous assignment invoked by the progress page via fetch
session_start();
// capture any stray output to avoid breaking JSON
if (!ob_get_level()) ob_start();
require_once __DIR__ . '/include/config.php';
header('Content-Type: application/json');
$act = isset($_GET['ac']) ? intval($_GET['ac']) : 0;
if (!$act) { http_response_code(400); echo json_encode(['error'=>'missing act']); exit; }
// simple auth: require logged-in session
if (!isset($_SESSION['id']) || intval($_SESSION['id']) <= 0) { http_response_code(403); echo json_encode(['error'=>'not authorized']); exit; }
// create running marker and progress file
@file_put_contents(__DIR__ . '/tmp/sieges-' . $act . '.running', date('c') . ' | SYNC_BY=' . intval($_SESSION['id']) . "\n", FILE_APPEND);
@file_put_contents(__DIR__ . '/tmp/sieges-' . $act . '.progress', json_encode(['activity'=>$act,'processed'=>0,'percent'=>0,'status'=>'starting']));
@file_put_contents(__DIR__ . '/tmp/sieges.log', date('c') . ' | SYNC_RUN act=' . $act . ' by=' . intval($_SESSION['id']) . "\n", FILE_APPEND);
// use assign_sieges from worker file
require_once __DIR__ . '/sieges-worker.php';
// set long time limit
@set_time_limit(0);
try {
    $res = assign_sieges($act);
    @file_put_contents(__DIR__ . '/tmp/sieges.log', date('c') . ' | SYNC_FINISHED act=' . $act . ' processed=' . intval($res['processed']) . "\n", FILE_APPEND);
    $extra = '';
    // capture any stray buffered output
    if (ob_get_level()) { $extra = ob_get_clean(); }
    if ($extra) {
        @file_put_contents(__DIR__ . '/tmp/sieges.log', date('c') . ' | SYNC_OUTPUT act=' . $act . ' output=' . substr($extra,0,1000) . "\n", FILE_APPEND);
    }
    $payload = ['ok'=>true,'processed'=>intval($res['processed']),'positions'=>intval($res['positions'])];
    if ($extra) $payload['server_output'] = substr($extra,0,2000);
    echo json_encode($payload);
} catch (Throwable $e) {
    // log and return a JSON error payload to keep client-side JSON.parse happy
    if (ob_get_level()) { $extra = ob_get_clean(); } else { $extra=''; }
    @file_put_contents(__DIR__ . '/tmp/sieges.log', date('c') . ' | SYNC_ERROR act=' . $act . ' err=' . $e->getMessage() . ' output=' . substr($extra,0,1000) . "\n", FILE_APPEND);
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>'internal','msg'=>substr($e->getMessage(),0,200),'server_output'=>substr($extra,0,2000)]);
}

