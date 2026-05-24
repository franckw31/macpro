<?php
// qv-messages.php - Production private messaging API
ini_set('session.name','PHPSESSID');
if (PHP_VERSION_ID >= 70300 && session_status() !== PHP_SESSION_ACTIVE) session_set_cookie_params(['lifetime'=>0,'path'=>'/','domain'=>'.viendez.com','secure'=>true,'httponly'=>true,'samesite'=>'Lax']);
if (!empty($_COOKIE[session_name()]) && session_status() !== PHP_SESSION_ACTIVE) @session_id($_COOKIE[session_name()]);
@session_start();
header('Content-Type: application/json; charset=utf-8');

try {
    require_once __DIR__ . '/../include/config.php';
    if (empty($con)) { echo json_encode(array('ok'=>false,'err'=>'db')); exit; }
    if (empty($_SESSION['id'])) { echo json_encode(array('ok'=>false,'err'=>'not_logged')); exit; }

    $me = (int) $_SESSION['id'];
    $me_pseudo = isset($_SESSION['login']) ? $_SESSION['login'] : 'Joueur';

    // Detect whether qv_messages has recipient/read columns in this schema
    $has_dest = false;
    $has_lu = false;
    $hc = mysqli_query($con, "SHOW COLUMNS FROM `qv_messages` LIKE 'id_destinataire'");
    if ($hc && mysqli_num_rows($hc)) $has_dest = true;
    $hl = mysqli_query($con, "SHOW COLUMNS FROM `qv_messages` LIKE 'lu_to_recipient'");
    if ($hl && mysqli_num_rows($hl)) $has_lu = true;

    $raw = file_get_contents('php://input');
    $body = json_decode($raw, true) ?: array();
    $action = trim($body['action'] ?? $_POST['action'] ?? $_GET['action'] ?? 'fetch');
    $id_activite = (int) ($body['id_activite'] ?? $_GET['id_activite'] ?? 0);

    // find organizer
    $organizer = 0;
    if ($id_activite) {
        $res = mysqli_query($con, 'SELECT * FROM activite WHERE `id-activite`=' . intval($id_activite) . ' LIMIT 1');
        if ($res && ($r = mysqli_fetch_assoc($res))) {
            foreach (array('id-membre','id_membre','idmember','id') as $c) {
                if (!empty($r[$c])) { $organizer = (int)$r[$c]; break; }
            }
        }
    }
    $my_role = ($me === $organizer) ? 'organisateur' : 'joueur';

    if ($action === 'fetch') {
        if (!$id_activite) { http_response_code(400); echo json_encode(array('ok'=>false,'err'=>'no_act')); exit; }
        $msgs = array();
        $unread = 0;
        if ($has_dest) {
            $sql = 'SELECT id,id_expediteur,pseudo_exp,role,message,id_destinataire,lu_to_recipient,created_at FROM qv_messages '
                 . 'WHERE id_activite=' . intval($id_activite) . ' AND (id_expediteur=' . $me . ' OR id_destinataire=' . $me . ') '
                 . 'ORDER BY created_at ASC LIMIT 500';
            $q = mysqli_query($con, $sql);
            if ($has_lu) mysqli_query($con, 'UPDATE qv_messages SET lu_to_recipient=1 WHERE id_activite=' . intval($id_activite) . ' AND id_destinataire=' . $me);
            if ($q) while ($row = mysqli_fetch_assoc($q)) $msgs[] = array('id'=>(int)$row['id'],'from'=>htmlspecialchars($row['pseudo_exp'],ENT_QUOTES,'UTF-8'),'from_id'=>(int)$row['id_expediteur'],'role'=>$row['role'],'mine'=>((int)$row['id_expediteur']=== $me),'msg'=>htmlspecialchars($row['message'],ENT_QUOTES,'UTF-8'),'at'=>$row['created_at'],'unread'=>($has_lu ? !((bool)$row['lu_to_recipient']) : false));
            if ($has_lu) {
                $ur = mysqli_query($con, 'SELECT COUNT(*) AS c FROM qv_messages WHERE id_activite=' . intval($id_activite) . ' AND id_destinataire=' . $me . ' AND lu_to_recipient=0');
                if ($ur && ($urrow = mysqli_fetch_assoc($ur))) $unread = (int)$urrow['c'];
            }
        } else {
            // no recipient column: return messages where user is sender only
            $sql = 'SELECT id,id_expediteur,pseudo_exp,role,message,created_at FROM qv_messages '
                 . 'WHERE id_activite=' . intval($id_activite) . ' AND id_expediteur=' . $me . ' '
                 . 'ORDER BY created_at ASC LIMIT 500';
            $q = mysqli_query($con, $sql);
            if ($q) while ($row = mysqli_fetch_assoc($q)) $msgs[] = array('id'=>(int)$row['id'],'from'=>htmlspecialchars($row['pseudo_exp'],ENT_QUOTES,'UTF-8'),'from_id'=>(int)$row['id_expediteur'],'role'=>$row['role'],'mine'=>true,'msg'=>htmlspecialchars($row['message'],ENT_QUOTES,'UTF-8'),'at'=>$row['created_at'],'unread'=>false);
        }
        echo json_encode(array('ok'=>true,'msgs'=>$msgs,'my_role'=>$my_role,'unread'=>$unread,'organizer_id'=>$organizer,'has_dest'=>$has_dest)); exit;
    }

    if ($action === 'send') {
        $msg = trim($body['message'] ?? $_POST['message'] ?? '');
        if (!$msg || mb_strlen($msg) > 2000) { echo json_encode(array('ok'=>false,'err'=>'invalid_msg')); exit; }
        $msg_esc = mysqli_real_escape_string($con,$msg);
        if ($my_role === 'joueur') $dest = intval($organizer); else $dest = intval($body['to'] ?? $body['id_destinataire'] ?? 0);
        if ($has_dest && !$dest) { echo json_encode(array('ok'=>false,'err'=>'no_dest')); exit; }
        $pseudo = mysqli_real_escape_string($con,$me_pseudo);
        if ($has_dest) {
            $ins = 'INSERT INTO qv_messages (id_activite,id_expediteur,pseudo_exp,role,message,id_destinataire' . ($has_lu ? ',lu_to_recipient' : '') . ') VALUES (' . intval($id_activite) . ',' . $me . ',"' . $pseudo . '","' . mysqli_real_escape_string($con,$my_role) . '","' . $msg_esc . '",' . intval($dest) . ($has_lu ? ',0' : '') . ')';
        } else {
            $ins = 'INSERT INTO qv_messages (id_activite,id_expediteur,pseudo_exp,role,message) VALUES (' . intval($id_activite) . ',' . $me . ',"' . $pseudo . '","' . mysqli_real_escape_string($con,$my_role) . '","' . $msg_esc . '")';
        }
        mysqli_query($con,$ins);
        echo json_encode(array('ok'=>true,'id'=>(int)mysqli_insert_id($con))); exit;
    }

    if ($action === 'delete') {
        $mid = intval($body['id'] ?? $_POST['id'] ?? 0);
        if (!$mid) { echo json_encode(array('ok'=>false,'err'=>'no_id')); exit; }
        if ($has_dest) {
            $mq = mysqli_query($con,'SELECT id,id_expediteur,id_destinataire FROM qv_messages WHERE id='.intval($mid).' LIMIT 1');
            if (!$mq || mysqli_num_rows($mq)===0) { echo json_encode(array('ok'=>false,'err'=>'not_found')); exit; }
            $m = mysqli_fetch_assoc($mq);
            if ((int)$m['id_expediteur'] !== $me && (int)$m['id_destinataire'] !== $me) { echo json_encode(array('ok'=>false,'err'=>'forbidden')); exit; }
        } else {
            $mq = mysqli_query($con,'SELECT id,id_expediteur FROM qv_messages WHERE id='.intval($mid).' LIMIT 1');
            if (!$mq || mysqli_num_rows($mq)===0) { echo json_encode(array('ok'=>false,'err'=>'not_found')); exit; }
            $m = mysqli_fetch_assoc($mq);
            if ((int)$m['id_expediteur'] !== $me) { echo json_encode(array('ok'=>false,'err'=>'forbidden')); exit; }
        }
        mysqli_query($con,'DELETE FROM qv_messages WHERE id=' . intval($mid));
        echo json_encode(array('ok'=>true,'id'=>$mid)); exit;
    }

    if ($action === 'mark_read') {
        if ($has_dest && $has_lu) {
            mysqli_query($con,'UPDATE qv_messages SET lu_to_recipient=1 WHERE id_activite=' . intval($id_activite) . ' AND id_destinataire=' . $me);
        }
        echo json_encode(array('ok'=>true)); exit;
    }

    echo json_encode(array('ok'=>false,'err'=>'unknown_action'));
    exit;

} catch (Throwable $e) {
    $log = date('c') . ' CATCH: ' . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine() . "\n" . $e->getTraceAsString() . "\n";
    @file_put_contents('/tmp/qv-errors.log', $log, FILE_APPEND);
    http_response_code(500);
    echo json_encode(array('ok'=>false,'err'=>'exception','msg'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine()));
    exit;
}
<?php
// Production private messaging API
ini_set('session.name','PHPSESSID');
if (PHP_VERSION_ID >= 70300 && session_status() !== PHP_SESSION_ACTIVE) session_set_cookie_params(['lifetime'=>0,'path'=>'/','domain'=>'.viendez.com','secure'=>true,'httponly'=>true,'samesite'=>'Lax']);
if (!empty($_COOKIE[session_name()]) && session_status() !== PHP_SESSION_ACTIVE) @session_id($_COOKIE[session_name()]);
@session_start();
header('Content-Type: application/json; charset=utf-8');

try {
    require_once __DIR__ . '/../include/config.php';
    if (empty($con)) { echo json_encode(array('ok'=>false,'err'=>'db')); exit; }
    if (empty($_SESSION['id'])) { echo json_encode(array('ok'=>false,'err'=>'not_logged')); exit; }

    $me = (int) $_SESSION['id'];
    $me_pseudo = isset($_SESSION['login']) ? $_SESSION['login'] : 'Joueur';

    // Detect whether qv_messages has recipient/read columns in this schema
    $has_dest = false;
    $has_lu = false;
    $hc = mysqli_query($con, "SHOW COLUMNS FROM `qv_messages` LIKE 'id_destinataire'");
    if ($hc && mysqli_num_rows($hc)) $has_dest = true;
    $hl = mysqli_query($con, "SHOW COLUMNS FROM `qv_messages` LIKE 'lu_to_recipient'");
    if ($hl && mysqli_num_rows($hl)) $has_lu = true;

    $raw = file_get_contents('php://input');
    $body = json_decode($raw, true) ?: array();
    $action = trim($body['action'] ?? $_POST['action'] ?? $_GET['action'] ?? 'fetch');
    $id_activite = (int) ($body['id_activite'] ?? $_GET['id_activite'] ?? 0);

    // find organizer
    $organizer = 0;
    if ($id_activite) {
        $res = mysqli_query($con, 'SELECT * FROM activite WHERE `id-activite`=' . intval($id_activite) . ' LIMIT 1');
        if ($res && ($r = mysqli_fetch_assoc($res))) {
            foreach (array('id-membre','id_membre','idmember','id') as $c) {
                if (!empty($r[$c])) { $organizer = (int)$r[$c]; break; }
            }
        }
    }
    $my_role = ($me === $organizer) ? 'organisateur' : 'joueur';

    if ($action === 'fetch') {
        if (!$id_activite) { http_response_code(400); echo json_encode(array('ok'=>false,'err'=>'no_act')); exit; }
        $msgs = array();
        $unread = 0;
        if ($has_dest) {
            $sql = 'SELECT id,id_expediteur,pseudo_exp,role,message,id_destinataire,lu_to_recipient,created_at FROM qv_messages '
                 . 'WHERE id_activite=' . intval($id_activite) . ' AND (id_expediteur=' . $me . ' OR id_destinataire=' . $me . ') '
                 . 'ORDER BY created_at ASC LIMIT 500';
            $q = mysqli_query($con, $sql);
            if ($has_lu) mysqli_query($con, 'UPDATE qv_messages SET lu_to_recipient=1 WHERE id_activite=' . intval($id_activite) . ' AND id_destinataire=' . $me);
            if ($q) while ($row = mysqli_fetch_assoc($q)) $msgs[] = array('id'=>(int)$row['id'],'from'=>htmlspecialchars($row['pseudo_exp'],ENT_QUOTES,'UTF-8'),'from_id'=>(int)$row['id_expediteur'],'role'=>$row['role'],'mine'=>((int)$row['id_expediteur']=== $me),'msg'=>htmlspecialchars($row['message'],ENT_QUOTES,'UTF-8'),'at'=>$row['created_at'],'unread'=>($has_lu ? !((bool)$row['lu_to_recipient']) : false));
            if ($has_lu) {
                $ur = mysqli_query($con, 'SELECT COUNT(*) AS c FROM qv_messages WHERE id_activite=' . intval($id_activite) . ' AND id_destinataire=' . $me . ' AND lu_to_recipient=0');
                if ($ur && ($urrow = mysqli_fetch_assoc($ur))) $unread = (int)$urrow['c'];
            }
        } else {
            // no recipient column: return messages where user is sender only
            $sql = 'SELECT id,id_expediteur,pseudo_exp,role,message,created_at FROM qv_messages '
                 . 'WHERE id_activite=' . intval($id_activite) . ' AND id_expediteur=' . $me . ' '
                 . 'ORDER BY created_at ASC LIMIT 500';
            $q = mysqli_query($con, $sql);
            if ($q) while ($row = mysqli_fetch_assoc($q)) $msgs[] = array('id'=>(int)$row['id'],'from'=>htmlspecialchars($row['pseudo_exp'],ENT_QUOTES,'UTF-8'),'from_id'=>(int)$row['id_expediteur'],'role'=>$row['role'],'mine'=>true,'msg'=>htmlspecialchars($row['message'],ENT_QUOTES,'UTF-8'),'at'=>$row['created_at'],'unread'=>false);
        }
        echo json_encode(array('ok'=>true,'msgs'=>$msgs,'my_role'=>$my_role,'unread'=>$unread,'organizer_id'=>$organizer,'has_dest'=>$has_dest)); exit;
    }

    if ($action === 'send') {
        $msg = trim($body['message'] ?? $_POST['message'] ?? '');
        if (!$msg || mb_strlen($msg) > 2000) { echo json_encode(array('ok'=>false,'err'=>'invalid_msg')); exit; }
        $msg_esc = mysqli_real_escape_string($con,$msg);
        if ($my_role === 'joueur') $dest = intval($organizer); else $dest = intval($body['to'] ?? $body['id_destinataire'] ?? 0);
        if ($has_dest && !$dest) { echo json_encode(array('ok'=>false,'err'=>'no_dest')); exit; }
        $pseudo = mysqli_real_escape_string($con,$me_pseudo);
        if ($has_dest) {
            $ins = 'INSERT INTO qv_messages (id_activite,id_expediteur,pseudo_exp,role,message,id_destinataire' . ($has_lu ? ',lu_to_recipient' : '') . ') VALUES (' . intval($id_activite) . ',' . $me . ',"' . $pseudo . '","' . mysqli_real_escape_string($con,$my_role) . '","' . $msg_esc . '",' . intval($dest) . ($has_lu ? ',0' : '') . ')';
        } else {
            $ins = 'INSERT INTO qv_messages (id_activite,id_expediteur,pseudo_exp,role,message) VALUES (' . intval($id_activite) . ',' . $me . ',"' . $pseudo . '","' . mysqli_real_escape_string($con,$my_role) . '","' . $msg_esc . '")';
        }
        mysqli_query($con,$ins);
        echo json_encode(array('ok'=>true,'id'=>(int)mysqli_insert_id($con))); exit;
    }

    if ($action === 'delete') {
        $mid = intval($body['id'] ?? $_POST['id'] ?? 0);
        if (!$mid) { echo json_encode(array('ok'=>false,'err'=>'no_id')); exit; }
        if ($has_dest) {
            $mq = mysqli_query($con,'SELECT id,id_expediteur,id_destinataire FROM qv_messages WHERE id='.intval($mid).' LIMIT 1');
            if (!$mq || mysqli_num_rows($mq)===0) { echo json_encode(array('ok'=>false,'err'=>'not_found')); exit; }
            $m = mysqli_fetch_assoc($mq);
            if ((int)$m['id_expediteur'] !== $me && (int)$m['id_destinataire'] !== $me) { echo json_encode(array('ok'=>false,'err'=>'forbidden')); exit; }
        } else {
            $mq = mysqli_query($con,'SELECT id,id_expediteur FROM qv_messages WHERE id='.intval($mid).' LIMIT 1');
            if (!$mq || mysqli_num_rows($mq)===0) { echo json_encode(array('ok'=>false,'err'=>'not_found')); exit; }
            $m = mysqli_fetch_assoc($mq);
            if ((int)$m['id_expediteur'] !== $me) { echo json_encode(array('ok'=>false,'err'=>'forbidden')); exit; }
        }
        mysqli_query($con,'DELETE FROM qv_messages WHERE id=' . intval($mid));
        echo json_encode(array('ok'=>true,'id'=>$mid)); exit;
    }

    if ($action === 'mark_read') {
        if ($has_dest && $has_lu) {
            mysqli_query($con,'UPDATE qv_messages SET lu_to_recipient=1 WHERE id_activite=' . intval($id_activite) . ' AND id_destinataire=' . $me);
        }
        echo json_encode(array('ok'=>true)); exit;
    }

    echo json_encode(array('ok'=>false,'err'=>'unknown_action'));
    exit;

} catch (Throwable $e) {
    $log = date('c') . ' CATCH: ' . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine() . "\n" . $e->getTraceAsString() . "\n";
    @file_put_contents('/tmp/qv-errors.log', $log, FILE_APPEND);
    http_response_code(500);
    echo json_encode(array('ok'=>false,'err'=>'exception','msg'=>$e->getMessage(),'file'=>$e->getFile(),'line'=>$e->getLine()));
    exit;
}
        if (!empty($_COOKIE[$cookie]) && session_status() !== PHP_SESSION_ACTIVE) {
            @session_id($_COOKIE[$cookie]);
        }
        @session_start();
        header('Content-Type: application/json; charset=utf-8');

        try {
            require_once __DIR__ . '/../include/config.php';
            if (empty($con)) { echo json_encode(array('ok'=>false,'err'=>'db')); exit; }

            if (empty($_SESSION['id'])) { echo json_encode(array('ok'=>false,'err'=>'not_logged')); exit; }
            $me = (int) $_SESSION['id'];
            $me_pseudo = isset($_SESSION['login']) ? $_SESSION['login'] : 'Joueur';

            $raw = file_get_contents('php://input');
            $body = json_decode($raw, true) ?: array();
            $action = trim($body['action'] ?? $_POST['action'] ?? $_GET['action'] ?? 'fetch');
            $id_activite = (int) ($body['id_activite'] ?? $_GET['id_activite'] ?? 0);

            // find organizer
            $organizer = 0;
            if ($id_activite) {
                $res = mysqli_query($con, "SELECT * FROM activite WHERE `id-activite`=" . intval($id_activite) . " LIMIT 1");
                if ($res && ($r = mysqli_fetch_assoc($res))) {
                    foreach (array('id-membre','id_membre','idmember','id') as $c) {
                        if (!empty($r[$c])) { $organizer = (int)$r[$c]; break; }
                    }
                }
            }
            $my_role = ($me === $organizer) ? 'organisateur' : 'joueur';

            if ($action === 'fetch') {
                if (!$id_activite) { http_response_code(400); echo json_encode(array('ok'=>false,'err'=>'no_act')); exit; }
                $sql = 'SELECT id,id_expediteur,pseudo_exp,role,message,id_destinataire,lu_to_recipient,created_at FROM qv_messages '
                     . 'WHERE id_activite=' . intval($id_activite) . ' AND (id_expediteur=' . $me . ' OR id_destinataire=' . $me . ') '
                     . 'ORDER BY created_at ASC LIMIT 500';
                $q = mysqli_query($con, $sql);
                mysqli_query($con, 'UPDATE qv_messages SET lu_to_recipient=1 WHERE id_activite=' . intval($id_activite) . ' AND id_destinataire=' . $me);
                $msgs = array();
                if ($q) {
                    while ($row = mysqli_fetch_assoc($q)) {
                        $msgs[] = array(
                            'id' => (int)$row['id'],
                            'from' => htmlspecialchars($row['pseudo_exp'], ENT_QUOTES, 'UTF-8'),
                            'from_id' => (int)$row['id_expediteur'],
                            'role' => $row['role'],
                            'mine' => ((int)$row['id_expediteur'] === $me),
                            'msg' => htmlspecialchars($row['message'], ENT_QUOTES, 'UTF-8'),
                            'at' => $row['created_at'],
                            'unread' => !((bool)$row['lu_to_recipient'])
                        );
                    }
                }
                $ur = mysqli_query($con, 'SELECT COUNT(*) AS c FROM qv_messages WHERE id_activite=' . intval($id_activite) . ' AND id_destinataire=' . $me . ' AND lu_to_recipient=0');
                $unread = 0; if ($ur && ($urrow = mysqli_fetch_assoc($ur))) $unread = (int)$urrow['c'];
                echo json_encode(array('ok'=>true,'msgs'=>$msgs,'my_role'=>$my_role,'unread'=>$unread,'organizer_id'=>$organizer));
                exit;
            }

            if ($action === 'send') {
                $msg = trim($body['message'] ?? $_POST['message'] ?? '');
                if (!$msg || mb_strlen($msg) > 2000) { echo json_encode(array('ok'=>false,'err'=>'invalid_msg')); exit; }
                $msg_esc = mysqli_real_escape_string($con, $msg);
                if ($my_role === 'joueur') {
                    $dest = intval($organizer);
                } else {
                    $dest = intval($body['to'] ?? $body['id_destinataire'] ?? 0);
                    if (!$dest) { echo json_encode(array('ok'=>false,'err'=>'no_dest')); exit; }
                }
                $pseudo = mysqli_real_escape_string($con, $me_pseudo);
                $ins = 'INSERT INTO qv_messages (id_activite,id_expediteur,pseudo_exp,role,message,id_destinataire,lu_to_recipient) VALUES ('
                     . intval($id_activite) . ',' . $me . ',"' . $pseudo . '","' . mysqli_real_escape_string($con,$my_role) . '","' . $msg_esc . '",' . intval($dest) . ',0)';
                mysqli_query($con, $ins);
                $nid = (int) mysqli_insert_id($con);
                echo json_encode(array('ok'=>true,'id'=>$nid));
                exit;
            }

            if ($action === 'delete') {
                $mid = intval($body['id'] ?? $_POST['id'] ?? 0);
                if (!$mid) { echo json_encode(array('ok'=>false,'err'=>'no_id')); exit; }
                $mq = mysqli_query($con, 'SELECT id,id_expediteur,id_destinataire FROM qv_messages WHERE id=' . intval($mid) . ' LIMIT 1');
                if (!$mq || mysqli_num_rows($mq) === 0) { echo json_encode(array('ok'=>false,'err'=>'not_found')); exit; }
                $m = mysqli_fetch_assoc($mq);
                if ((int)$m['id_expediteur'] !== $me && (int)$m['id_destinataire'] !== $me) { echo json_encode(array('ok'=>false,'err'=>'forbidden')); exit; }
                mysqli_query($con, 'DELETE FROM qv_messages WHERE id=' . intval($mid));
                echo json_encode(array('ok'=>true,'id'=>$mid));
                exit;
            }

            if ($action === 'mark_read') {
                if (!$id_activite) { echo json_encode(array('ok'=>false,'err'=>'no_act')); exit; }
                mysqli_query($con, 'UPDATE qv_messages SET lu_to_recipient=1 WHERE id_activite=' . intval($id_activite) . ' AND id_destinataire=' . $me);
                echo json_encode(array('ok'=>true));
                exit;
            }

            echo json_encode(array('ok'=>false,'err'=>'unknown_action'));
            exit;

        } catch (Throwable $e) {
            @file_put_contents('/tmp/qv-errors.log', date('c') . 'CATCH: ' . $e->getMessage() . "\n", FILE_APPEND);
            http_response_code(500);
            echo json_encode(array('ok'=>false,'err'=>'exception','msg'=>$e->getMessage()));
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
