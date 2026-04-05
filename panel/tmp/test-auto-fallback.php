<?php
// Test helper to simulate the AUTO fallback actions: create marker, run assign_sieges, log results
chdir(__DIR__ . '/..');
require_once __DIR__ . '/../include/config.php';
$act = 676;
$log = __DIR__ . '/sieges.log';
$autoMarker = __DIR__ . '/sieges-' . intval($act) . '.autofallback';
file_put_contents($log, date('c') . ' | TEST_AUTO_INVOKE act=' . $act . "\n", FILE_APPEND);
if (!file_exists($autoMarker)) {
    file_put_contents($autoMarker, date('c'));
    file_put_contents($log, date('c') . ' | TEST_AUTO_CREATE_MARKER act=' . $act . "\n", FILE_APPEND);
    require_once __DIR__ . '/../sieges-worker.php';
    file_put_contents(__DIR__ . '/sieges-' . intval($act) . '.running', date('c') . ' | TEST_RUN' . "\n", FILE_APPEND);
    $res = assign_sieges(intval($act));
    file_put_contents($log, date('c') . ' | TEST_AUTO_DONE act=' . intval($act) . ' processed=' . intval($res['processed']) . "\n", FILE_APPEND);
    @unlink(__DIR__ . '/sieges-' . intval($act) . '.running');
} else {
    file_put_contents($log, date('c') . ' | TEST_AUTO_SKIPPING_ALREADY_MARKED act=' . $act . "\n", FILE_APPEND);
}
echo "done\n";