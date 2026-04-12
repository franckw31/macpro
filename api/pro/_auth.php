<?php
// ============================================================
//  _auth.php — Validation du Bearer token pour les endpoints Pro
//
//  Usage :
//    require_once __DIR__ . '/_auth.php';
//    → $authUser = ['member_id' => int, 'pseudo' => string,
//                   'is_admin' => bool, 'is_organizer' => bool]
//
//  En cas d'échec, répond 401 et exit() directement.
// ============================================================

require_once __DIR__ . '/_db.php';

// ---- Extraire le Bearer token ----
$_proToken = null;

$_headers = function_exists('apache_request_headers') ? apache_request_headers() : [];
$_authHeader = $_SERVER['HTTP_AUTHORIZATION']
    ?? $_headers['Authorization']
    ?? $_headers['authorization']
    ?? '';

if (preg_match('/Bearer\s+(\S+)/i', $_authHeader, $_m)) {
    $_proToken = trim($_m[1]);
}

// Fallback : token dans le body JSON ou en GET
if (!$_proToken) {
    $_body  = file_get_contents('php://input');
    $_input = json_decode($_body, true) ?? [];
    if (!empty($_input['token'])) {
        $_proToken = $_input['token'];
    } elseif (!empty($_GET['token'])) {
        $_proToken = $_GET['token'];
    }
}

if (!$_proToken) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Token manquant']);
    exit;
}

// ---- Vérifier le token en base ----
$_stmt = $pdo->prepare("
    SELECT t.membre_id,
           m.pseudo,
           m.droits,
           t.expires_at
    FROM   app_auth_tokens t
    JOIN   membres m ON m.`id-membre` = t.membre_id
    WHERE  t.token = ?
      AND  (t.expires_at IS NULL OR t.expires_at > NOW())
    LIMIT 1
");
$_stmt->execute([$_proToken]);
$_row = $_stmt->fetch();

if (!$_row) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Token invalide ou expiré']);
    exit;
}

// Rafraîchir last_used_at
$pdo->prepare("UPDATE app_auth_tokens SET last_used_at = NOW() WHERE token = ?")
    ->execute([$_proToken]);

// ---- Vérifier le rôle organisateur dans pro_organizers ----
$_orgStmt = $pdo->prepare("
    SELECT is_verified FROM pro_organizers WHERE member_id = ? LIMIT 1
");
$_orgStmt->execute([(int)$_row['membre_id']]);
$_orgRow = $_orgStmt->fetch();

$_isAdmin     = ((int)$_row['droits'] === 2 || (int)$_row['membre_id'] === 265);
$_isOrganizer = $_isAdmin || ($_orgRow && (int)$_orgRow['is_verified'] === 1);

$authUser = [
    'member_id'    => (int)$_row['membre_id'],
    'pseudo'       => $_row['pseudo'],
    'is_admin'     => $_isAdmin,
    'is_organizer' => $_isOrganizer,
];

unset($_proToken, $_headers, $_authHeader, $_m, $_body, $_input, $_stmt, $_row, $_orgStmt, $_orgRow);
