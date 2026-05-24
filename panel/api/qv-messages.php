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

// Temporary: capture PHP errors/exceptions to a server-side log for debugging
error_reporting(E_ALL);
set_error_handler(function($errno, $errstr, $errfile, $errline){
    $entry = date('c') . " ERROR [$errno]: $errstr in $errfile:$errline\n";
    @file_put_contents('/tmp/qv-errors.log', $entry, FILE_APPEND);
});
set_exception_handler(function($e){
    $entry = date('c') . " EXCEPTION: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine() . "\n" . $e->getTraceAsString() . "\n";
    @file_put_contents('/tmp/qv-errors.log', $entry, FILE_APPEND);
});
register_shutdown_function(function(){
    $err = error_get_last();
    if ($err) {
        $entry = date('c') . " SHUTDOWN: [{$err['type']}] {$err['message']} in {$err['file']}:{$err['line']}\n";
        @file_put_contents('/tmp/qv-errors.log', $entry, FILE_APPEND);
    }
});

// Debug endpoint to read the collected error log
if (isset($_GET['readerrors'])) {
    header('Content-Type: text/plain; charset=utf-8');
    $c = @file_get_contents('/tmp/qv-errors.log');
    echo $c === false ? "(no log)" : $c;
    exit;
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
    // Ensure session is started so $_SESSION is populated
    if (session_status() !== PHP_SESSION_ACTIVE) {
        // If cookie contains session id, force it before starting session
        $cookieName = session_name();
        if (!empty($_COOKIE[$cookieName])) {
            @session_id($_COOKIE[$cookieName]);
        }
        @session_start();
    }

    $sessId = session_id();
    $sessInfo = ['session_id' => $sessId];
    $savePath = ini_get('session.save_path') ?: '';
    // If save_path contains a prefix like "N;/path", extract the path part
    if (strpos($savePath, ';') !== false) {
        $parts = explode(';', $savePath);
        $savePath = end($parts);
    }
    $sessInfo['session_save_path'] = $savePath;
    $sessFile = $savePath ? rtrim($savePath, '/') . '/sess_' . $sessId : '';
    $sessInfo['session_file'] = $sessFile;
    if ($sessFile && @file_exists($sessFile)) {
        $raw = @file_get_contents($sessFile);
        $sessInfo['session_file_exists'] = true;
        $sessInfo['session_file_size'] = strlen($raw);
        // include a short preview (base64 to be safe)
        $sessInfo['session_file_preview_b64'] = base64_encode(substr($raw, 0, 1024));
    } else {
        $sessInfo['session_file_exists'] = false;
    }

    $out = [
        'ok' => true,
        'request_uri' => $_SERVER['REQUEST_URI'] ?? '',
        'headers' => $hdrs,
        'http_cookie' => $_SERVER['HTTP_COOKIE'] ?? '',
        '_COOKIE' => $_COOKIE,
        '_SESSION' => isset($_SESSION) ? $_SESSION : [],
        'session_info' => $sessInfo
    ];
    echo json_encode($out);
    exit;
}

// Ensure session uses client's session id (if provided) before starting
$cookieName = session_name();
if (!empty($_COOKIE[$cookieName]) && session_status() !== PHP_SESSION_ACTIVE) {
    @session_id($_COOKIE[$cookieName]);
}
@session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

try {
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
    `id_destinataire` INT UNSIGNED NOT NULL DEFAULT 0,
    `lu_to_recipient` TINYINT(1) NOT NULL DEFAULT 0,
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

$id_activite = (int)($body['id_activite'] ?? $_GET['id_activite'] ?? $_REQUEST['uid'] ?? $_GET['uid'] ?? 0);
if (!$id_activite) {
    http_response_code(400);
    echo json_encode(['ok'=>false,'err'=>'no_act','msg'=>'Missing id_activite (try uid or id_activite)']);
    exit;
}

$organizer_id = 0;
// attempt to read activite row and detect organizer column name
$act_res = mysqli_query($con, "SELECT * FROM activite WHERE `id-activite`=".intval($id_activite)." LIMIT 1");
if ($act_res && ($act_row = mysqli_fetch_assoc($act_res))) {
    foreach (array('id-membre','id_membre','id_membre','idmember','id') as $col) {
        if (isset($act_row[$col]) && $act_row[$col] !== '') { $organizer_id = (int)$act_row[$col]; break; }
    }
}
$my_role = ($my_id === $organizer_id) ? 'organisateur' : 'joueur';

// Actions
if ($action === 'fetch') {
    // Only messages where the user is sender or recipient are visible
    $q = mysqli_query($con, "SELECT id, id_expediteur, pseudo_exp, role, message, id_destinataire, lu_to_recipient, created_at FROM qv_messages WHERE id_activite=".intval($id_activite)." AND (id_expediteur=".intval($my_id)." OR id_destinataire=".intval($my_id).") ORDER BY created_at ASC LIMIT 200");
    // mark read for messages where current user is the recipient
    mysqli_query($con, "UPDATE qv_messages SET lu_to_recipient=1 WHERE id_activite=".intval($id_activite)." AND id_destinataire=".intval($my_id).");
    $msgs = array();
    if ($q) while ($r = mysqli_fetch_assoc($q)) {
        $msgs[] = array(
            'id' => (int)$r['id'],
            'from' => htmlspecialchars($r['pseudo_exp'], ENT_QUOTES, 'UTF-8'),
            'from_id' => (int)$r['id_expediteur'],
            'role' => $r['role'],
            'mine' => ((int)$r['id_expediteur'] === $my_id),
            'msg' => htmlspecialchars($r['message'], ENT_QUOTES, 'UTF-8'),
            'at' => $r['created_at'],
            'unread' => !((bool)$r['lu_to_recipient'])
        );
    }
    $unread_row = mysqli_fetch_assoc(mysqli_query($con, "SELECT COUNT(*) AS c FROM qv_messages WHERE id_activite=".intval($id_activite)." AND id_destinataire=".intval($my_id)." AND lu_to_recipient=0"));
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
    // determine destinataire: joueur -> organisateur; organisateur must provide target player id in payload
    if ($my_role === 'joueur') {
        $dest_id = intval($organizer_id);
    } else {
        $dest_id = intval($body['to'] ?? $body['id_destinataire'] ?? 0);
        if (!$dest_id) { echo json_encode(['ok'=>false,'err'=>'no_dest']); exit; }
    }
    // insert with destinataire and unread flag
    mysqli_query($con, "INSERT INTO qv_messages (id_activite,id_expediteur,pseudo_exp,role,message,id_destinataire,lu_to_recipient,lu_orga,lu_joueur) VALUES (".intval($id_activite).",".intval($my_id).",'".$pseudo_esc."','".$role_esc."','".$msg_esc."',".intval($dest_id).",0,".$lu_orga.",".$lu_joueur.")");
    $new_id = (int)mysqli_insert_id($con);
    echo json_encode(['ok'=>true,'id'=>$new_id,'msg'=>['id'=>$new_id,'from'=>$my_pseudo,'from_id'=>$my_id,'role'=>$my_role,'mine'=>true,'msg'=>htmlspecialchars($msg_raw,ENT_QUOTES,'UTF-8'),'at'=>date('Y-m-d H:i:s'),'unread'=>false]]);
    exit;
}

// DELETE a message (allowed only for sender or recipient)
if ($action === 'delete') {
    $mid = intval($body['id'] ?? 0);
    if (!$mid) { echo json_encode(['ok'=>false,'err'=>'no_id']); exit; }
    // fetch message
    $mq = mysqli_query($con, "SELECT id, id_expediteur, id_destinataire, role, id_activite FROM qv_messages WHERE id=".intval($mid)." LIMIT 1");
    if (!$mq || mysqli_num_rows($mq) === 0) { echo json_encode(['ok'=>false,'err'=>'not_found']); exit; }
    $mrow = mysqli_fetch_assoc($mq);
    // permission: organizer for this activity or the original sender
    $can = false;
    if ((int)$mrow['id_expediteur'] === $my_id) $can = true;
    if (!empty($mrow['id_destinataire']) && (int)$mrow['id_destinataire'] === $my_id) $can = true;
    if (!$can) { echo json_encode(['ok'=>false,'err'=>'forbidden']); exit; }
    // delete
    mysqli_query($con, "DELETE FROM qv_messages WHERE id=".intval($mid));
    echo json_encode(['ok'=>true,'id'=>$mid]);
    exit;
}

if ($action === 'mark_read') {
    // mark all messages as read where current user is the recipient for this activity
    mysqli_query($con, "UPDATE qv_messages SET lu_to_recipient=1 WHERE id_activite=".intval($id_activite)." AND id_destinataire=".intval($my_id));
    echo json_encode(['ok'=>true]); exit;
}

    echo json_encode(['ok'=>false,'err'=>'unknown_action']);
    exit;

} catch (Throwable $e) {
    // Log and return JSON error
    @file_put_contents('/tmp/qv-errors.log', date('c') . " CATCH: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine() . "\n" . $e->getTraceAsString() . "\n", FILE_APPEND);
    http_response_code(500);
    echo json_encode(['ok'=>false,'err'=>'exception','msg'=>$e->getMessage()]);
    exit;
}
