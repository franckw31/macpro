<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

try {
    $pdo = new PDO(
        'mysql:host=localhost;dbname=dbs9616600;charset=utf8mb4',
        'root',
        'Kookies7*',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );

    // Parse headers robustly
    $headers = function_exists('apache_request_headers') ? apache_request_headers() : [];
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $headers['Authorization'] ?? $headers['authorization'] ?? '';
    $token = '';
    if (preg_match('/Bearer\s+(\S+)/', $authHeader, $m)) { $token = $m[1]; }
    // fallback: form field or query
    if (empty($token) && isset($_POST['token'])) { $token = trim($_POST['token']); }
    if (empty($token) && isset($_GET['token'])) { $token = trim($_GET['token']); }

    if (empty($token)) {
        echo json_encode(['success' => false, 'error' => 'token manquant']); exit;
    }

    // validate token
    $tstmt = $pdo->prepare("SELECT membre_id, expires_at FROM app_auth_tokens WHERE token = ? LIMIT 1");
    $tstmt->execute([$token]);
    $trow = $tstmt->fetch();
    if (!$trow) { echo json_encode(['success'=>false,'error'=>'token invalide']); exit; }
    if (!empty($trow['expires_at']) && strtotime($trow['expires_at']) <= time()) { echo json_encode(['success'=>false,'error'=>'token expiré']); exit; }
    $memberId = intval($trow['membre_id']);

    // Read input (accept JSON or form)
    $new = '';
    $confirm = '';
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($contentType, 'application/json') !== false) {
        $raw = file_get_contents('php://input');
        $obj = json_decode($raw, true);
        if (is_array($obj)) {
            $new = isset($obj['new_password']) ? trim($obj['new_password']) : '';
            $confirm = isset($obj['confirm_password']) ? trim($obj['confirm_password']) : '';
        }
    } else {
        $new = isset($_POST['new_password']) ? trim($_POST['new_password']) : '';
        $confirm = isset($_POST['confirm_password']) ? trim($_POST['confirm_password']) : '';
    }

    if ($new === '' || $confirm === '') { echo json_encode(['success'=>false,'error'=>'tous les champs requis']); exit; }
    if ($new !== $confirm) { echo json_encode(['success'=>false,'error'=>'mots de passe différents']); exit; }

    // update raw password to remain compatible with existing auth
    $ustmt = $pdo->prepare("UPDATE membres SET password = ? WHERE `id-membre` = ?");
    $ok = $ustmt->execute([$new, $memberId]);
    if ($ok) {
        // ── Log changement mot de passe ──────────────────────────
        $mstmt = $pdo->prepare("SELECT pseudo FROM membres WHERE `id-membre` = ? LIMIT 1");
        $mstmt->execute([$memberId]);
        $mrow = $mstmt->fetch();
        $mpseudo = $mrow['pseudo'] ?? 'Inconnu';
        $logIp = $_SERVER['HTTP_CLIENT_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
        $pdo->prepare("INSERT INTO activity_logs (user_id, username, action, source, details, ip_address) VALUES (?, ?, 'change_password', 'iOS App', 'Mot de passe modifie', ?)")
            ->execute([$memberId, $mpseudo, $logIp]);
        echo json_encode(['success'=>true,'message'=>'Mot de passe mis à jour']);
    } else {
        echo json_encode(['success'=>false,'error'=>'impossible de mettre à jour']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success'=>false,'error'=>'server error','detail'=>$e->getMessage()]);
}
