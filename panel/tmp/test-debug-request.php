<?php
// Helper to simulate an admin request to sieges-debug-tail.php from CLI
session_start();
$_SESSION['id'] = 265;
$_GET['ac'] = 676;
chdir(__DIR__ . '/../');
require_once __DIR__ . '/../panel/sieges-debug-tail.php';
