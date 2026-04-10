<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

$user = null;
if(!empty($_SESSION['user'])) $user = $_SESSION['user'];
elseif(!empty($_SESSION['login'])) $user = $_SESSION['login'];
elseif(!empty($_COOKIE['uname'])) $user = $_COOKIE['uname'];

// sanitize simple display value
if($user) $user = htmlspecialchars($user, ENT_QUOTES, 'UTF-8');

$out = [
	'user' => $user,
	'has_session_user' => (bool)!empty($_SESSION['user']),
	'session_id' => session_id(),
	'cookie_uname' => isset($_COOKIE['uname']) ? htmlspecialchars($_COOKIE['uname'], ENT_QUOTES, 'UTF-8') : null,
];

// If debug explicitly requested, include additional session diagnostics (keys only by default)
if(isset($_GET['debug']) && $_GET['debug'] == '1'){
	$out['session_keys'] = array_keys($_SESSION);
	// optionally include full session values if debug_full=1 (useful for dev only)
	if(isset($_GET['debug_full']) && $_GET['debug_full']=='1'){
		// sanitize values for JSON output
		$san = [];
		foreach($_SESSION as $k=>$v){
			if(is_scalar($v)) $san[$k] = htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
			else $san[$k] = json_encode($v, JSON_UNESCAPED_UNICODE);
		}
		$out['session_values'] = $san;
	}
	$out['remote_addr'] = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : null;
}

echo json_encode($out, JSON_UNESCAPED_UNICODE);
