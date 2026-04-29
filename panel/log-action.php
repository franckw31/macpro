<?php
session_start();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
    http_response_code(400);
    exit;
}

$action  = isset($data['action'])  ? trim((string)$data['action'])  : '';
$details = isset($data['details']) ? trim((string)$data['details'])  : '';

if ($action === '') {
    http_response_code(400);
    exit;
}

// Whitelist des actions autorisees
$allowed = ['vue_liste_participants', 'vue_classement_challenge', 'vue_quickview'];
if (!in_array($action, $allowed, true)) {
    http_response_code(403);
    exit;
}

if (!function_exists('log_activity')) {
    @include_once __DIR__ . '/../include/functions_logs.php';
}

if (function_exists('log_activity')) {
    include_once __DIR__ . '/include/config.php';
    $db = $con ?? $conn ?? null;
    if ($db) {
        log_activity($db, $action, $details);
    }
}

http_response_code(200);
echo json_encode(['success' => true]);
