<?php
chdir(__DIR__);
$_GET['ac'] = intval($argv[1] ?? 676);
$_GET['sou'] = '/panel/voir-activite.php';
$_GET['auto'] = '1';
// optional debug second arg: 'debug' will enable debug_mode and force_fail
if (isset($argv[2]) && $argv[2] === 'debug') { $_GET['debug_mode'] = '1'; $_GET['force_fail'] = '1'; }
$_SERVER['REQUEST_URI'] = '/panel/sieges.php?ac=' . $_GET['ac'] . '&auto=1';
// emulate web server env minimally
$_SERVER['HTTP_HOST'] = 'localhost';
require_once __DIR__ . '/sieges.php';
echo "invoked\n";