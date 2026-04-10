<?php
// Temporary diagnostic endpoint - runs the wrapper synchronously and returns output
session_start();
require_once __DIR__ . '/include/config.php';
if (!isset($_SESSION['id']) || intval($_SESSION['id']) <= 0) {
    http_response_code(403);
    echo "Not authorized";
    exit;
}
$act = isset($_GET['ac']) ? intval($_GET['ac']) : 0;
if (!$act) { echo "Missing act"; exit; }
$bat = __DIR__ . DIRECTORY_SEPARATOR . 'sieges-run.bat';
$outFile = __DIR__ . DIRECTORY_SEPARATOR . 'tmp' . DIRECTORY_SEPARATOR . 'sieges-worker-' . $act . '.out';
$cmd = escapeshellcmd($bat) . ' ' . intval($act);
// run synchronously and capture output
@file_put_contents(__DIR__ . '/tmp/sieges.log', date('c') . ' | TEST_RUN start act=' . $act . ' cmd=' . $cmd . "\n", FILE_APPEND);
$descriptorspec = array(
   0 => array("pipe", "r"),
   1 => array("pipe", "w"),
   2 => array("pipe", "w")
);
$process = proc_open($cmd, $descriptorspec, $pipes, __DIR__);
$output = '';
$path = getenv('PATH');
$wherephp = '';
if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
    $wherephp = trim(shell_exec('where php 2>&1'));
} else {
    $wherephp = trim(shell_exec('which php 2>&1'));
}
if (is_resource($process)) {
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]); fclose($pipes[1]);
    $stderr = stream_get_contents($pipes[2]); fclose($pipes[2]);
    $return_value = proc_close($process);
    $content = "TEST_RUN\nPATH:\n$path\nWHERE_PHP:\n$wherephp\n--- STDOUT ---\n" . $stdout . "\n--- STDERR ---\n" . $stderr . "\n--- RETURN ---\n" . $return_value;
    $output = $content;
    @file_put_contents($outFile, $content);
    @file_put_contents(__DIR__ . '/tmp/sieges.log', date('c') . ' | TEST_RUN done act=' . $act . ' rc=' . intval($return_value) . "\n", FILE_APPEND);
} else {
    $output = "proc_open failed\nPATH:\n$path\nWHERE_PHP:\n$wherephp";
    @file_put_contents($outFile, $output);
    @file_put_contents(__DIR__ . '/tmp/sieges.log', date('c') . ' | TEST_RUN proc_open_failed act=' . $act . "\n", FILE_APPEND);
}
header('Content-Type: text/plain; charset=utf-8');
echo $output;
