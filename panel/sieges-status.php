<?php
// Simple status endpoint for sieges background jobs
// Usage: panel/sieges-status.php?act=676 (returns JSON)
header('Content-Type: application/json');
$act = isset($_GET['act']) ? intval($_GET['act']) : 0;
$logfile = __DIR__ . '/tmp/sieges.log';
$runningFile = __DIR__ . '/tmp/sieges-' . $act . '.running';
$doneFile = __DIR__ . '/tmp/sieges-' . $act . '.done';

$result = [
    'act' => $act,
    'running' => false,
    'last_spawn' => null,
    'last_worker_start' => null,
    'last_worker_done' => null,
    'done_summary' => null,
    'tail' => []
];

if ($act <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'missing act parameter']);
    exit;
}

if (file_exists($runningFile)) {
    $result['running'] = true;
    $result['last_spawn'] = trim(@file_get_contents($runningFile));
    // compute age (seconds) and mark stale if too old
    $stat = @stat($runningFile);
    if ($stat && isset($stat['mtime'])) {
        $age = time() - intval($stat['mtime']);
        $result['running_age_seconds'] = $age;
        $result['stale'] = $age > 300; // consider stale after 5 minutes
    }
}
if (file_exists($doneFile)) {
    $result['last_worker_done'] = trim(@file_get_contents($doneFile));
    $result['done_summary'] = trim(@file_get_contents($doneFile));
}

// fetch last ~400 lines from main log and return lines mentioning this activity and spawn diagnostics
if (file_exists($logfile)) {
    $content = file($logfile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $tail = array_slice($content, -400);
    foreach ($tail as $line) {
        // include spawn attempts and worker entries (either the act=.. token or SPAWN_ lines)
        if (strpos($line, 'act=' . $act) !== false || strpos($line, 'SPAWN_') !== false || strpos($line, 'WORKER_') !== false || strpos($line, 'SYNC_') !== false) {
            $result['tail'][] = $line;
        }
    }
    // include only spawn-related subset for quick display
    foreach ($tail as $line) {
        if (strpos($line, 'SPAWN_ATTEMPT') !== false || strpos($line, 'SPAWN_RESULT') !== false || strpos($line, 'SPAWN_EXEC_OUT') !== false || strpos($line, 'AUTO_RETRY') !== false || strpos($line, 'SYNC_') !== false) {
            $result['spawn_tail'][] = $line;
        }
    }
}
// include progress file if present
$progressFile = __DIR__ . '/tmp/sieges-' . $act . '.progress';
if (file_exists($progressFile)) {
    $raw = @file_get_contents($progressFile);
    if ($raw) {
        $json = @json_decode($raw, true);
        if ($json) $result['progress'] = $json; else $result['progress_raw'] = $raw;
    }
}

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
