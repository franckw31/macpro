<?php
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

try {
    $pdo = new PDO(
        'mysql:host=localhost;dbname=dbs9616600;charset=utf8mb4',
        'root',
        'Kookies7*',
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );

    $token = null;
    $headers = function_exists('apache_request_headers') ? apache_request_headers() : [];
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $headers['Authorization'] ?? $headers['authorization'] ?? '';
    
    if (preg_match('/Bearer\s+(.+)/i', $authHeader, $m)) {
        $token = trim($m[1]);
    }
    if (!$token && isset($_GET['token'])) {
        $token = trim($_GET['token']);
    }

    $body = file_get_contents('php://input');
    $input = json_decode($body, true) ?: $_POST;
    
    if (!$token) {
        $token = $input['token'] ?? null;
    }

    if (!$token) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Token manquant']);
        exit;
    }

    $stmt = $pdo->prepare("
        SELECT at.membre_id, m.pseudo, m.droits
        FROM app_auth_tokens at
        JOIN membres m ON m.`id-membre` = at.membre_id
        WHERE at.token = ? AND (at.expires_at IS NULL OR at.expires_at > NOW())
    ");
    $stmt->execute([$token]);
    $user = $stmt->fetch();

    if (!$user) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Token invalide ou expiré']);
        exit;
    }

    $actId = isset($input['activity_id']) ? (int)$input['activity_id'] : 0;
    $targetPseudo = isset($input['pseudo']) ? trim($input['pseudo']) : '';

    if (!$actId || !$targetPseudo) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Paramètres manquants']);
        exit;
    }

    $stmt = $pdo->prepare("SELECT `id-membre` AS owner_id FROM activite WHERE `id-activite` = ?");
    $stmt->execute([$actId]);
    $act = $stmt->fetch();

    if (!$act) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Activité introuvable']);
        exit;
    }

    $membreId = (int)$user['membre_id'];
    $droits = (int)$user['droits'];
    $isAdmin = ($droits === 2 || $membreId === 265);
    $isOrganizer = ((int)$act['owner_id'] === $membreId);

    if (!$isAdmin && !$isOrganizer) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => "Vous n'êtes pas autorisé à modifier ce participant"]);
        exit;
    }

    $stmt = $pdo->prepare("SELECT `id-membre` FROM membres WHERE pseudo = ? LIMIT 1");
    $stmt->execute([$targetPseudo]);
    $targetMembre = $stmt->fetch();

    if (!$targetMembre) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Participant introuvable']);
        exit;
    }

    $targetId = $targetMembre['id-membre'];

    $stmt = $pdo->prepare("SELECT `option` FROM participation WHERE `id-activite` = ? AND `id-membre` = ?");
    $stmt->execute([$actId, $targetId]);
    $part = $stmt->fetch();

    if (!$part) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Participation introuvable']);
        exit;
    }

    $newStatus = ($part['option'] === 'Option') ? 'Inscrit' : 'Option';

    $stmt = $pdo->prepare("UPDATE participation SET `option` = ? WHERE `id-activite` = ? AND `id-membre` = ?");
    if ($stmt->execute([$newStatus, $actId, $targetId])) {
        echo json_encode(['success' => true, 'newStatus' => $newStatus]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Erreur interne']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
}
