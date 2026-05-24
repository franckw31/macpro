<?php
// API: mini-messagerie joueur <-> organisateur
// GET  ?action=fetch&id_activite=X
// POST {action:'send', id_activite, message}
// POST {action:'mark_read', id_activite}

ini_set('session.name', 'PHPSESSID');
// Ensure cookie params are set before starting session
if (PHP_VERSION_ID >= 70300) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '.viendez.com',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
}

// Debug endpoint to inspect session/cookies
if (isset($_GET['debugsession'])) {
    header('Content-Type: text/plain; charset=utf-8');
    session_start();
    echo '$_SESSION = ';
    print_r($_SESSION);
    echo "\n\$_COOKIE = ";
    print_r($_COOKIE);
    exit;
}

// Debug: log raw request headers and cookies to /tmp/qv-headers.log
if (isset($_GET['logheaders'])) {
    header('Content-Type: application/json; charset=utf-8');
    $hdrs = function_exists('getallheaders') ? getallheaders() : [];
    $out = [
        'ok' => true,
        'request_uri' => $_SERVER['REQUEST_URI'] ?? '',
        'headers' => $hdrs,
        'http_cookie' => $_SERVER['HTTP_COOKIE'] ?? '',
        '_COOKIE' => $_COOKIE,
        '_SESSION' => isset($_SESSION) ? $_SESSION : []
    ];
    echo json_encode($out);
    exit;
}

session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/../include/config.php';

if (empty($con)) { echo json_encode(['ok'=>false,'err'=>'db']); exit; }

// Create table if missing
mysqli_query($con, "CREATE TABLE IF NOT EXISTS `qv_messages` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `id_activite` INT UNSIGNED NOT NULL,
  `id_expediteur` INT UNSIGNED NOT NULL,
  `pseudo_exp` VARCHAR(80) NOT NULL DEFAULT '',
  `role` ENUM('joueur','organisateur') NOT NULL DEFAULT 'joueur',
  `message` TEXT NOT NULL,
  `lu_orga` TINYINT(1) NOT NULL DEFAULT 0,
  `lu_joueur` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_act (`id_activite`),
  INDEX idx_exp (`id_expediteur`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

// Session check
if (empty($_SESSION['id'])) { echo json_encode(['ok'=>false,'err'=>'not_logged']); exit; }
$my_id     = (int)$_SESSION['id'];
$my_pseudo = htmlspecialchars($_SESSION['login'] ?? 'Joueur', ENT_QUOTES, 'UTF-8');

$body   = [];
$action = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body   = json_decode(file_get_contents('php://input'), true) ?: [];
    $action = trim($body['action'] ?? ($_POST['action'] ?? ''));
} else {
    $action = trim($_GET['action'] ?? 'fetch');
}

$id_activite = (int)($body['id_activite'] ?? $_GET['id_activite'] ?? 0);
if (!$id_activite) { echo json_encode(['ok'=>false,'err'=>'no_act']); exit; }

// get organizer
$org_row = mysqli_fetch_assoc(mysqli_query($con, "SELECT COALESCE(`id-membre`,`id_membre`) AS org_id FROM activite WHERE `id-activite`=".intval($id_activite)." LIMIT 1"));
$organizer_id = $org_row ? (int)$org_row['org_id'] : 0;
$my_role = ($my_id === $organizer_id) ? 'organisateur' : 'joueur';

// Actions
if ($action === 'fetch') {
    if ($my_role === 'organisateur') {
        $q = mysqli_query($con, "SELECT id, id_expediteur, pseudo_exp, role, message, lu_orga, lu_joueur, created_at FROM qv_messages WHERE id_activite=".intval($id_activite)." ORDER BY created_at ASC LIMIT 100");
    } else {
        $q = mysqli_query($con, "SELECT id, id_expediteur, pseudo_exp, role, message, lu_orga, lu_joueur, created_at FROM qv_messages WHERE id_activite=".intval($id_activite)." AND (id_expediteur=".intval($my_id)." OR role='organisateur') ORDER BY created_at ASC LIMIT 50");
        // mark read for player
        mysqli_query($con, "UPDATE qv_messages SET lu_joueur=1 WHERE id_activite=".intval($id_activite)." AND role='organisateur'");
    }
    $msgs = [];
    if ($q) while ($r = mysqli_fetch_assoc($q)) {
        $msgs[] = [
            'id' => (int)$r['id'],
            'from' => htmlspecialchars($r['pseudo_exp'], ENT_QUOTES, 'UTF-8'),
            'role' => $r['role'],
            'mine' => ((int)$r['id_expediteur'] === $my_id),
            'msg' => htmlspecialchars($r['message'], ENT_QUOTES, 'UTF-8'),
            'at' => $r['created_at'],
            'unread' => ($my_role === 'joueur') ? !(bool)$r['lu_joueur'] : !(bool)$r['lu_orga']
        ];
    }
    $unread_row = mysqli_fetch_assoc(mysqli_query($con, "SELECT COUNT(*) AS c FROM qv_messages WHERE id_activite=".intval($id_activite)." AND role='organisateur' AND lu_joueur=0"));
    $unread = $unread_row ? (int)$unread_row['c'] : 0;
    echo json_encode(['ok'=>true,'msgs'=>$msgs,'my_role'=>$my_role,'unread'=>$unread,'organizer_id'=>$organizer_id]);
    exit;
}

if ($action === 'send') {
    $msg_raw = trim($body['message'] ?? $_POST['message'] ?? '');
    if (!$msg_raw || mb_strlen($msg_raw) > 1000) { echo json_encode(['ok'=>false,'err'=>'invalid_msg']); exit; }
    $msg_esc = mysqli_real_escape_string($con, $msg_raw);
    $role_esc = mysqli_real_escape_string($con, $my_role);
    $pseudo_esc = mysqli_real_escape_string($con, $my_pseudo);
    $lu_orga = ($my_role === 'organisateur') ? 1 : 0;
    $lu_joueur = ($my_role === 'joueur') ? 1 : 0;
    mysqli_query($con, "INSERT INTO qv_messages (id_activite,id_expediteur,pseudo_exp,role,message,lu_orga,lu_joueur) VALUES (".intval($id_activite).",".intval($my_id).",'".$pseudo_esc."','".$role_esc."','".$msg_esc."',".$lu_orga.",".$lu_joueur.")");
    $new_id = (int)mysqli_insert_id($con);
    if ($my_role === 'organisateur') mysqli_query($con, "UPDATE qv_messages SET lu_orga=1 WHERE id_activite=".intval($id_activite)." AND role='joueur'");
    echo json_encode(['ok'=>true,'id'=>$new_id,'msg'=>['id'=>$new_id,'from'=>$my_pseudo,'role'=>$my_role,'mine'=>true,'msg'=>htmlspecialchars($msg_raw,ENT_QUOTES,'UTF-8'),'at'=>date('Y-m-d H:i:s'),'unread'=>false]]);
    exit;
}

if ($action === 'mark_read' && $my_role === 'organisateur') {
    mysqli_query($con, "UPDATE qv_messages SET lu_orga=1 WHERE id_activite=".intval($id_activite));
    echo json_encode(['ok'=>true]); exit;
}

echo json_encode(['ok'=>false,'err'=>'unknown_action']);
