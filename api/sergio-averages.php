<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

register_shutdown_function(function () {
    $error = error_get_last();
    if ($error === null) {
        return;
    }
    $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR];
    if (!in_array($error['type'], $fatalTypes, true)) {
        return;
    }
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode([
        'success' => false,
        'error' => 'Erreur PHP: ' . $error['message'],
        'file' => basename($error['file'] ?? ''),
        'line' => $error['line'] ?? null,
    ], JSON_UNESCAPED_UNICODE);
});

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

require_once __DIR__ . '/config.php';

$token = trim($_GET['token'] ?? '');
$activityId = (int)($_GET['activity_id'] ?? 0);

if ($token === '') {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Token manquant']);
    exit;
}

function fetch_scalar(PDO $pdo, string $sql, array $params = [])
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchColumn();
}

function try_query(PDO $pdo, string $sql, array $params, array &$errors): ?array
{
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $errors[] = $e->getMessage();
        return null;
    }
}

try {
    $pdo = get_pdo();
    $authErrors = [];
    $authenticated = false;

    foreach ([
        'SELECT membre_id FROM sessions WHERE token = ? AND expires_at > NOW() LIMIT 1',
        'SELECT user_id FROM sessions WHERE token = ? AND expires_at > NOW() LIMIT 1',
        'SELECT id_membre FROM sessions WHERE token = ? AND expires_at > NOW() LIMIT 1',
        'SELECT membre_id FROM sessions WHERE token = ? LIMIT 1',
        'SELECT user_id FROM sessions WHERE token = ? LIMIT 1',
        'SELECT id_membre FROM sessions WHERE token = ? LIMIT 1',
        'SELECT membre_id FROM api_sessions WHERE token = ? LIMIT 1',
        'SELECT user_id FROM api_sessions WHERE token = ? LIMIT 1',
    ] as $authSql) {
        try {
            if (fetch_scalar($pdo, $authSql, [$token])) {
                $authenticated = true;
                break;
            }
        } catch (Throwable $e) {
            $authErrors[] = $e->getMessage();
        }
    }

    if (!$authenticated) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'error' => 'Session invalide',
            'debug' => array_slice(array_values(array_unique($authErrors)), 0, 3),
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $beforeDate = null;
    if ($activityId > 0) {
        foreach ([
            'SELECT date_depart FROM activite WHERE id = ? LIMIT 1',