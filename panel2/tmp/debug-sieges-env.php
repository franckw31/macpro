<?php
// Minimal debug endpoint - writes env info to sieges-debug-env.log
$log = __DIR__ . '/sieges-debug-env.log';
file_put_contents($log, date('c') . " | HIT\n", FILE_APPEND);
file_put_contents($log, date('c') . " | PHP_BINARY=" . PHP_BINARY . "\n", FILE_APPEND);
file_put_contents($log, date('c') . " | PATH=" . getenv('PATH') . "\n", FILE_APPEND);
$file = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'sieges-run.bat';
file_put_contents($log, date('c') . " | sieges-run.bat exists=" . (file_exists($file)?'1':'0') . "\n", FILE_APPEND);
file_put_contents($log, date('c') . " | where php: " . trim(shell_exec('where php 2>&1')) . "\n", FILE_APPEND);
echo 'ok';