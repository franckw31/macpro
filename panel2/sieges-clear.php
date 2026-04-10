<?php
// Simple endpoint to clear a stuck .running marker for a given activity
session_start();
header('Content-Type: application/json');
$act = isset($_GET['act']) ? intval($_GET['act']) : 0;
if (!$act) { http_response_code(400); echo json_encode(['error'=>'missing act']); exit; }
// require logged-in user for safety
if (!isset($_SESSION['id']) || intval($_SESSION['id']) <= 0) { http_response_code(403); echo json_encode(['error'=>'not authorized']); exit; }
$runningFile = __DIR__ . '/tmp/sieges-' . $act . '.running';
$logfile = __DIR__ . '/tmp/sieges.log';
if (!file_exists($runningFile)) { echo json_encode(['ok'=>true,'msg'=>'no running file']); exit; }
$ok = @unlink($runningFile);
// remove progress file too
@unlink(__DIR__ . '/tmp/sieges-' . $act . '.progress');
$auto = isset($_GET['auto']) && (string)$_GET['auto'] === '1';
@file_put_contents($logfile, date('c') . ' | ' . ($auto ? 'CLEARED_RUNNING_AUTO' : 'CLEARED_RUNNING') . ' act=' . $act . ' by=' . intval($_SESSION['id']) . ( $auto ? ' auto=1' : '' ) . "\n", FILE_APPEND);
if ($ok) echo json_encode(['ok'=>true]); else { http_response_code(500); echo json_encode(['ok'=>false,'error'=>'unlink failed']); }
