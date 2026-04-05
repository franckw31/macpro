<?php
chdir(__DIR__ . '/..');
$act = 676;
$log = __DIR__ . '/sieges.log';
file_put_contents($log, date('c') . ' | MANUAL_AUTO_FALLBACK_START act=' . $act . "\n", FILE_APPEND);
$marker = __DIR__ . '/sieges-' . intval($act) . '.autofallback';
if (!file_exists($marker)) {
    file_put_contents($marker, date('c'));
    require_once __DIR__ . '/../panel/sieges-worker.php';
    file_put_contents(__DIR__ . '/sieges-' . intval($act) . '.running', date('c') . ' | MANUAL_TEST' . "\n", FILE_APPEND);
    $res = assign_sieges(intval($act));
    file_put_contents($log, date('c') . ' | MANUAL_AUTO_FALLBACK_DONE act=' . intval($act) . ' processed=' . intval($res['processed']) . "\n", FILE_APPEND);
    @unlink(__DIR__ . '/sieges-' . intval($act) . '.running');
} else {
    file_put_contents($log, date('c') . ' | MANUAL_AUTO_FALLBACK_SKIPPED act=' . $act . "\n", FILE_APPEND);
}
echo "OK\n";