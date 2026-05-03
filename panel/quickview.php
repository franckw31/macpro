<?php
session_start();
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
header('Vary: Cookie');

try {
	include __DIR__ . '/include/config.php';
	$pseudo_get = isset($_GET['pseudo']) ? (isset($con) ? mysqli_real_escape_string($con, $_GET['pseudo']) : null) : null;
	$pass_get   = isset($_GET['passwd']) ? (isset($con) ? mysqli_real_escape_string($con, $_GET['passwd']) : null) : null;
	if ($pseudo_get && $pass_get && !empty($con)) {
		if (!function_exists('log_activity') && file_exists(__DIR__ . '/../include/functions_logs.php'))
			@include_once __DIR__ . '/../include/functions_logs.php';
		$q_auth = @mysqli_query($con, "SELECT `id-membre`, `pseudo` FROM membres WHERE (pseudo='$pseudo_get' OR email='$pseudo_get') AND (password='$pass_get' OR password_ext='$pass_get') LIMIT 1");
		if ($q_auth && ($r_auth = mysqli_fetch_array($q_auth))) {
			$_SESSION['login'] = $r_auth['pseudo'];
			$_SESSION['id']    = $r_auth['id-membre'];
		}
	}

	$act = null;
	$selected_id = null;
	if (isset($_GET['uid']) && is_numeric($_GET['uid'])) $selected_id = intval($_GET['uid']);

	if (isset($con)) {
		if ($selected_id) {
			$q = mysqli_query($con, "SELECT * FROM activite WHERE `id-activite`='$selected_id' LIMIT 1");
		} else {
			$q = mysqli_query($con, "SELECT * FROM activite WHERE date_depart >= NOW() ORDER BY date_depart ASC LIMIT 1");
		}
		if ($q && mysqli_num_rows($q) > 0) $act = mysqli_fetch_assoc($q);
		if (!$act && !$selected_id) {
			$q2 = mysqli_query($con, "SELECT * FROM activite ORDER BY date_depart DESC LIMIT 1");
			if ($q2 && mysqli_num_rows($q2) > 0) $act = mysqli_fetch_assoc($q2);
		}
		if ($act) {
			$id = (int)$act['id-activite'];
			$cnt = 0;
			$r = mysqli_query($con, "SELECT COUNT(*) AS c FROM participation WHERE `id-activite`='".intval($id)."' AND COALESCE(`option`,'None') NOT IN ('None','Desinscrit')");
			if ($r && ($rr = mysqli_fetch_assoc($r))) $cnt = (int)$rr['c'];

			$display_date = $act['date_depart'];
			try {
				if (class_exists('IntlDateFormatter')) {
					$dtobj = new DateTime($act['date_depart']);
					$fmt = new IntlDateFormatter('fr_FR', IntlDateFormatter::FULL, IntlDateFormatter::NONE, 'Europe/Paris', IntlDateFormatter::GREGORIAN, "EEEE d MMMM");
					$display_date = $fmt->format($dtobj);
					$display_date = mb_convert_case($display_date, MB_CASE_TITLE, "UTF-8");
				}
			} catch (Exception $e) {}

			$location = null;
			foreach (['ville','lieu','adresse','location','place'] as $c) { if (isset($act[$c]) && strlen(trim($act[$c])) > 0) { $location = $act[$c]; break; } }
			$phone = null;
			foreach (['telephone','tel','phone','tel_lieu','contact_tel','num_tel'] as $c) { if (isset($act[$c]) && strlen(trim($act[$c])) > 0) { $phone = $act[$c]; break; } }
			$tables = null;
			foreach (['nb-tables','tables','nb_tables'] as $c) { if (isset($act[$c]) && $act[$c] !== '') { $tables = $act[$c]; break; } }
			$max_participants = null;
			foreach (['places','max_places','max_participants'] as $c) { if (isset($act[$c]) && $act[$c] !== '') { $max_participants = $act[$c]; break; } }
			$start_chips = null;
			foreach (['jetons_depart','jetons','start_chips'] as $c) { if (isset($act[$c]) && $act[$c] !== '') { $start_chips = $act[$c]; break; } }
			$bounty  = isset($act['bounty'])  ? $act['bounty']  : null;
			$recave  = isset($act['recave'])  ? $act['recave']  : null;

			$structure_id = null;
			foreach (['id_structure','id-structure'] as $c) { if (isset($act[$c]) && $act[$c] !== '') { $structure_id = intval($act[$c]); break; } }
			$structure_detail_text = null;
			if ($structure_id && !empty($con)) {
				$smq = mysqli_query($con, "SELECT Detail FROM structure_modele WHERE id_modele_structure='".intval($structure_id)."' LIMIT 1");
				if ($smq && ($smr = mysqli_fetch_assoc($smq))) $structure_detail_text = $smr['Detail'];
			}

			$organizer = null;
			$organizer_id = null;
			foreach (['id-membre','id_membre'] as $c) { if (isset($act[$c]) && $act[$c] !== '') { $organizer_id = $act[$c]; break; } }
			if ($organizer_id && !empty($con)) {
				$mq = mysqli_query($con, "SELECT pseudo FROM membres WHERE `id-membre`='".intval($organizer_id)."' LIMIT 1");
				if ($mq && ($mr = mysqli_fetch_assoc($mq))) $organizer = $mr['pseudo'];
			}

			$serverActivity = [
				'id'                 => $id,
				'date'               => $act['date_depart'] ?? null,
				'display_date'       => $display_date,
				'date_heure'         => null,
				'title'              => $act['titre-activite'] ?? ($act['titre_activite'] ?? null),
				'buyin'              => isset($act['buyin']) ? (int)$act['buyin'] : null,
				'rake'               => isset($act['rake'])  ? (int)$act['rake']  : null,
				'participants_count' => $cnt,
				'max_participants'   => $max_participants,
				'organizer'         => $organizer,
				'location'          => $location,
				'phone'             => $phone,
				'tables'            => $tables,
				'start_chips'       => $start_chips,
				'bounty'            => $bounty,
				'recave'            => $recave,
				'structure_detail'  => $structure_detail_text,
				'structure_id'      => $structure_id,
				'structure_levels'  => [],
			];

			// Fetch blind levels for the structure table
			$structure_levels = [];
			if (!empty($con)) {
				$blq = mysqli_query($con, "SELECT `ordre`,`nom`,`sb`,`bb`,`ante`,`minutes` FROM `blindes-live` WHERE `id-activite`='".intval($id)."' ORDER BY `ordre` ASC");
				if ($blq) {
					while ($blr = mysqli_fetch_assoc($blq)) {
						$structure_levels[] = [
							'ordre'   => (int)$blr['ordre'],
							'nom'     => $blr['nom'] ?? null,
							'sb'      => (int)$blr['sb'],
							'bb'      => (int)$blr['bb'],
							'ante'    => (int)$blr['ante'],
							'minutes' => (int)$blr['minutes'],
						];
					}
				}
			}
			$serverActivity['structure_levels'] = $structure_levels;

			$serverParticipation = null;
			if (!empty($_SESSION['id']) && !empty($con)) {
				$uid = intval($_SESSION['id']);
				$qpart = mysqli_query($con, "SELECT `option`, COALESCE(`anonyme`,0) AS anonyme, COALESCE(`latereg`,0) AS latereg FROM participation WHERE `id-membre`='$uid' AND `id-activite`='$id' LIMIT 1");
				if ($qpart && ($rpart = mysqli_fetch_assoc($qpart))) {
					$serverParticipation = ['status' => $rpart['option'], 'anonyme' => (int)$rpart['anonyme'], 'latereg' => (int)$rpart['latereg']];
				}
			}
		}
	}
} catch (Exception $e) { $serverActivity = null; }

$displayUser = 'Visiteur';
if (!empty($_SESSION['login'])) $displayUser = $_SESSION['login'];
elseif (!empty($_SESSION['user'])) $displayUser = $_SESSION['user'];
elseif (!empty($_COOKIE['uname'])) $displayUser = $_COOKIE['uname'];
$displayUser = htmlspecialchars($displayUser);

$avatar_url = 'https://viendez.com/images/noprofil.jpg';
try {
	if (!empty($con) && !empty($_SESSION['id'])) {
		$uid = (int)$_SESSION['id'];
		$r = mysqli_query($con, "SELECT photo FROM membres WHERE `id-membre`='$uid' LIMIT 1");
		if ($r && ($row = mysqli_fetch_assoc($r)) && !empty($row['photo']))
			$avatar_url = 'https://viendez.com/images/faces/' . rawurlencode(basename($row['photo']));
	}
} catch (Exception $e) {}

$is_registered = (!empty($serverParticipation) && isset($serverParticipation['status']) && !in_array($serverParticipation['status'], ['None','Desinscrit']));

// Date display helpers
$_jours = ['Monday'=>'Lundi','Tuesday'=>'Mardi','Wednesday'=>'Mercredi','Thursday'=>'Jeudi','Friday'=>'Vendredi','Saturday'=>'Samedi','Sunday'=>'Dimanche'];
$_mois  = ['January'=>'Janvier','February'=>'Février','March'=>'Mars','April'=>'Avril','May'=>'Mai','June'=>'Juin','July'=>'Juillet','August'=>'Août','September'=>'Septembre','October'=>'Octobre','November'=>'Novembre','December'=>'Décembre'];
$date_str = '—';
$time_str = '';
$date_only_str = '—';
if (!empty($serverActivity['date'])) {
	$_d = new DateTime($serverActivity['date'], new DateTimeZone('Europe/Paris'));
	$date_only_str = $_jours[$_d->format('l')] . ' ' . $_d->format('j') . ' ' . $_mois[$_d->format('F')];
	$time_str = $_d->format('H:i');
	$date_str = $date_only_str . ' ' . $time_str;
	$serverActivity['date_heure'] = $time_str;
}

// Fetch ALL activities for the calendar picker (past + future, limit 500)
$allActivities = [];
try {
	if (!empty($con)) {
		$_jours_all = ['Monday'=>'Lundi','Tuesday'=>'Mardi','Wednesday'=>'Mercredi','Thursday'=>'Jeudi','Friday'=>'Vendredi','Saturday'=>'Samedi','Sunday'=>'Dimanche'];
		$_mois_all  = ['January'=>'Janvier','February'=>'Février','March'=>'Mars','April'=>'Avril','May'=>'Mai','June'=>'Juin','July'=>'Juillet','August'=>'Août','September'=>'Septembre','October'=>'Octobre','November'=>'Novembre','December'=>'Décembre'];
		// SELECT * to avoid errors on column name variations (titre-activite vs titre_activite)
		$qa = mysqli_query($con, "SELECT * FROM activite ORDER BY date_depart DESC LIMIT 500");
		if ($qa) {
			while ($ra = mysqli_fetch_assoc($qa)) {
				$_dp = $ra['date_depart'] ?? null;
				if (!$_dp) continue;
				$_da = new DateTime($_dp, new DateTimeZone('Europe/Paris'));
				// Resolve title: try multiple column name variants
				$_titre = '';
				foreach (['titre-activite','titre_activite','title','titre'] as $_tc) {
					if (isset($ra[$_tc]) && strlen(trim($ra[$_tc])) > 0) { $_titre = $ra[$_tc]; break; }
				}
				// Resolve buyin
				$_buyin = 0;
				foreach (['buyin','buy_in','buy-in'] as $_bc) {
					if (isset($ra[$_bc]) && $ra[$_bc] !== '') { $_buyin = (int)$ra[$_bc]; break; }
				}
				$allActivities[] = [
					'id'    => (int)$ra['id-activite'],
					'date'  => $_dp,
					'label' => $_jours_all[$_da->format('l')] . ' ' . $_da->format('j') . ' ' . $_mois_all[$_da->format('F')] . ' – ' . $_da->format('H:i'),
					'day'   => (int)$_da->format('j'),
					'month' => (int)$_da->format('n'),
					'year'  => (int)$_da->format('Y'),
					'ts'    => (int)$_da->getTimestamp(),
					'titre' => $_titre,
					'buyin' => $_buyin,
					'past'  => ($_dp < date('Y-m-d H:i:s')),
				];
			}
		}
	}
} catch (Exception $e) { error_log('allActivities fetch error: ' . $e->getMessage()); }

$uid_q = !empty($serverActivity['id']) ? '?uid=' . intval($serverActivity['id']) : '';
$is_past = !empty($serverActivity['date']) && strtotime($serverActivity['date']) < time();
// Determine "live" status using date+time: consider a game "en cours" when
// now is after the start time and within a reasonable window (12 hours).
$is_today = false;
if (!empty($serverActivity['date'])) {
  $start_ts = strtotime($serverActivity['date']);
  if ($start_ts !== false) {
    $now = time();
    // Live if started and not older than 8 hours (28800 seconds)
    $is_today = ($now >= $start_ts) && (($now - $start_ts) <= 28800);
  }
}
$participants_href = $is_past ? '/panel/resultats.php' . $uid_q : '/panel/participants.php' . $uid_q;
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title>CardEvent</title>
<style>
/* ─── RESET & BASE ─── */
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --bg:#0a0d14;
  --card:#111822;
  --card2:#141e2b;
  --border:rgba(255,255,255,0.06);
  --green:#34c759;
  --orange:#ff9f0a;
  --blue:#0a84ff;
  --cyan:#30d5c8;
  --muted:#6b7a8f;
  --text:#ffffff;
  --text2:#c8d6e5;
  --label:#8e9bae;
  --radius:16px;
  --radius-sm:12px;
}
html,body{height:100%;background:var(--bg);color:var(--text);font-family:-apple-system,BlinkMacSystemFont,'SF Pro Display','Helvetica Neue',Arial,sans-serif;-webkit-font-smoothing:antialiased;overflow-x:hidden}
button{cursor:pointer;border:none;background:none;font:inherit;color:inherit}
a{color:inherit;text-decoration:none}

/* ─── LAYOUT ─── */
.page{max-width:440px;margin:0 auto;padding:0 0 90px;min-height:100vh;display:flex;flex-direction:column;gap:0}

/* ─── HEADER ─── */
.v2-header{display:flex;align-items:center;justify-content:space-between;padding:16px 20px 12px;gap:12px}
.v2-header-left{display:flex;align-items:center;gap:12px}
.v2-logo{width:44px;height:44px;background:linear-gradient(135deg,#0a84ff 0%,#0062cc 100%);border-radius:12px;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.v2-logo svg{width:26px;height:26px;fill:#fff}
.v2-app-name{font-size:18px;font-weight:800;letter-spacing:-0.3px}
.v2-app-name span{color:var(--blue)}
.v2-version{background:rgba(10,132,255,0.18);color:var(--blue);font-size:11px;font-weight:700;padding:2px 7px;border-radius:20px;margin-left:6px;vertical-align:middle}
.v2-greeting{font-size:13px;color:var(--muted);margin-top:2px;display:flex;align-items:center;gap:4px}
.v2-greeting .name{color:var(--text2);font-weight:600}
.v2-greeting .chev{color:var(--blue);font-weight:700}
.v2-avatar{width:80px;height:80px;border-radius:50%;overflow:hidden;border:3px solid rgba(255,255,255,0.18);flex-shrink:0}
.v2-avatar img{width:100%;height:100%;object-fit:cover;display:block}

/* ─── CARD BASE ─── */
.v2-card{background:var(--card);border-radius:var(--radius);padding:18px 18px 16px;margin:0 16px 14px}

/* ─── NEXT GAME CARD ─── */
.v2-next-label{display:flex;align-items:center;gap:6px;font-size:11px;font-weight:700;letter-spacing:1.2px;color:var(--green);text-transform:uppercase;margin-bottom:10px}
.v2-next-label svg{flex-shrink:0}
.v2-date-row{display:flex;align-items:flex-start;justify-content:space-between;gap:8px;margin-bottom:16px}
.v2-date-big{font-size:26px;font-weight:800;letter-spacing:-0.5px;line-height:1.1}
.v2-cal-btn{width:46px;height:46px;background:rgba(255,255,255,0.05);border-radius:12px;display:flex;align-items:center;justify-content:center;flex-shrink:0;position:relative;border:none;box-shadow:0 0 5px rgba(10,132,255,0.12);isolation:isolate;overflow:hidden}
.v2-cal-btn::before{content:'';position:absolute;inset:0;border-radius:inherit;padding:1px;background:conic-gradient(from 0deg,rgba(10,132,255,0.08) 0deg,rgba(10,132,255,0.08) 250deg,rgba(126,219,255,0.55) 305deg,rgba(10,132,255,0.6) 330deg,rgba(10,132,255,0.08) 360deg);-webkit-mask:linear-gradient(#000 0 0) content-box,linear-gradient(#000 0 0);mask:linear-gradient(#000 0 0) content-box,linear-gradient(#000 0 0);-webkit-mask-composite:xor;mask-composite:exclude;animation:v2-cal-chenillard 3.4s linear infinite;pointer-events:none}
.v2-cal-btn svg{width:22px;height:22px;stroke:var(--text2);fill:none;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round}
@keyframes v2-cal-chenillard{from{transform:rotate(0deg)}to{transform:rotate(360deg)}}

/* Stats row */
.v2-stats{display:grid;grid-template-columns:66% 33%;gap:1px;background:var(--border);border-radius:12px;overflow:hidden;margin-bottom:14px}
.v2-stat{background:var(--card2);padding:12px 14px;display:flex;align-items:center;gap:10px;min-width:0}
#v2-countdown{font-size:clamp(12px,3.5vw,18px);font-weight:900;color:var(--green);letter-spacing:0;line-height:1;font-variant-numeric:tabular-nums;font-family:'SF Mono',SFMono-Regular,ui-monospace,Menlo,monospace;white-space:nowrap;display:inline-block}
.v2-stat{background:var(--card2);padding:12px 14px;display:flex;align-items:center;gap:10px}
.v2-stat-icon{width:34px;height:34px;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.v2-stat-icon.green{background:rgba(52,199,89,0.12)}
.v2-stat-icon.blue{background:rgba(10,132,255,0.12)}
.v2-stat-label{font-size:10px;font-weight:600;letter-spacing:.8px;color:var(--muted);text-transform:uppercase;margin-bottom:3px}
.v2-stat-val{font-size:20px;font-weight:800;letter-spacing:-0.5px;line-height:1}
.v2-stat-val.green{color:var(--green)}
.v2-stat-val.blue{color:var(--text)}
.v2-stat-val small{font-size:13px;font-weight:600;color:var(--muted);margin-left:1px}

/* Financial row */
.v2-fin{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:1px;background:var(--border);border-radius:12px;overflow:hidden;margin-bottom:18px}
.v2-fin-item{background:var(--card2);padding:10px 10px 10px 12px;display:flex;align-items:center;gap:8px}
.v2-fin-ico{width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.v2-fin-ico.gold{background:rgba(255,159,10,0.12)}
.v2-fin-ico.orange{background:rgba(255,69,58,0.12)}
.v2-fin-ico.teal{background:rgba(48,213,200,0.12)}
.v2-fin-lbl{font-size:9px;font-weight:600;letter-spacing:.7px;color:var(--muted);text-transform:uppercase;margin-bottom:2px}
.v2-fin-val{font-size:15px;font-weight:800}

/* Action buttons */
.v2-actions{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:14px}
.v2-btn{padding:13px 10px;border-radius:var(--radius-sm);font-size:13px;font-weight:700;display:flex;align-items:center;justify-content:center;gap:6px;transition:opacity .15s}
.v2-btn:active{opacity:.75}
.v2-btn.outline{background:rgba(255,255,255,0.05);border:1px solid rgba(255,255,255,0.12);color:var(--text)}
.v2-btn.filled{background:var(--orange);color:#04131d}

/* Inscrit banner */
.v2-inscrit{display:flex;align-items:center;justify-content:center;gap:8px;background:rgba(52,199,89,0.08);border:1px solid rgba(52,199,89,0.2);border-radius:12px;padding:12px;color:var(--green);font-weight:700;font-size:14px}

/* ─── ACTIONS RAPIDES ─── */
.v2-section-title{font-size:11px;font-weight:700;letter-spacing:1.2px;color:var(--muted);text-transform:uppercase;padding:4px 20px 8px;margin-top:4px}
.v2-list{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:7px;margin:0 12px 10px}
.v2-list-item{position:relative;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:4px;min-height:82px;padding:2px 6px 6px;background:#1f1f22;border:1px solid rgba(255,255,255,0.04);border-radius:18px;text-decoration:none;color:#f4f4f6;transition:transform .16s ease,background .16s ease,border-color .16s ease,box-shadow .16s ease;text-align:center;overflow:hidden}
.v2-list-item:active{transform:scale(.985);background:#252529}
.v2-list-item:hover{border-color:rgba(255,255,255,0.08);box-shadow:0 12px 28px rgba(0,0,0,0.22)}
.v2-list-item.tile-open{grid-column:1 / -1;align-items:stretch;justify-content:flex-start;min-height:unset;text-align:left;padding:12px}
.v2-list-item.tile-open .v2-list-chev{display:none}
.v2-list-item.tile-open .v2-list-body{text-align:left}
.v2-list-item.tile-open .v2-list-title-row{justify-content:flex-start}
.v2-list-item.tile-blue{--tile-accent:#2893ff}
.v2-list-item.tile-orange{--tile-accent:#ff9f38}
.v2-list-item.tile-purple{--tile-accent:#d25cff}
.v2-list-item.tile-gold{--tile-accent:#ffd234}
.v2-list-item.tile-green{--tile-accent:#42de68}
.v2-list-item.tile-red{--tile-accent:#ff534f}
.v2-list-item.tile-cyan{--tile-accent:#39d4d1}
.v2-list-item::after{content:"";position:absolute;inset:auto 16px 0 16px;height:1px;background:linear-gradient(90deg,transparent,rgba(255,255,255,0.06),transparent);opacity:.6}
.v2-list-item.tile-open::after{display:none}
.v2-list-icon{width:28px;height:28px;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:15px;color:var(--tile-accent,#fff)}
.v2-list-icon svg{width:18px;height:18px;stroke:currentColor}
.v2-list-body{min-width:0;width:100%}
.v2-list-title-row{display:flex;align-items:center;justify-content:center;gap:5px;width:100%}
.v2-list-name{font-size:13px;line-height:1.02;font-weight:800;letter-spacing:-0.02em;margin:0;color:#fff}
.v2-list-sub{font-size:9px;line-height:1.05;color:rgba(255,255,255,0.48);margin-top:1px;font-weight:500}
.v2-list-chev{display:none}
.v2-score-collapsed{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:4px;width:100%}
.v2-score-expanded{display:none;width:100%;flex-direction:column;gap:5px}
.v2-score-form{display:flex;gap:5px;position:relative}
.v2-score-input{flex:1;background:rgba(255,255,255,0.07);border:1px solid rgba(255,210,52,0.45);border-radius:8px;padding:7px 8px;color:#fff;font-size:12px;outline:none}
.v2-score-submit{background:#ffd234;color:#111;font-weight:800;font-size:10px;padding:7px 9px;border-radius:8px;white-space:nowrap;flex-shrink:0}
.v2-score-ac{display:none;background:#1c2333;border:1px solid rgba(255,255,255,0.1);border-radius:12px;overflow:hidden;max-height:180px;overflow-y:auto}
@media(min-width:390px){.v2-list-name{font-size:14px}.v2-list-sub{font-size:9.5px}}
@media(max-width:360px){.v2-list{grid-template-columns:1fr}.v2-list-item.tile-open{grid-column:auto}}

/* ─── BOTTOM NAV ─── */
.v2-bottom-nav{position:fixed;bottom:0;left:0;right:0;z-index:200;background:rgba(10,13,20,0.92);backdrop-filter:blur(20px);-webkit-backdrop-filter:blur(20px);border-top:1px solid var(--border);display:flex;justify-content:space-around;align-items:center;padding:8px 0 max(8px,env(safe-area-inset-bottom));max-width:440px;margin:0 auto}
/* fix: center on wide screens */
@media(min-width:441px){.v2-bottom-nav{left:50%;right:auto;transform:translateX(-50%);width:440px}}
.v2-nav-btn{display:flex;flex-direction:column;align-items:center;gap:4px;padding:4px 16px;color:var(--muted);font-size:11px;font-weight:600;transition:color .15s}
.v2-nav-btn.active{color:var(--blue)}
.v2-nav-btn svg{width:22px;height:22px;stroke:currentColor;fill:none;stroke-width:1.7;stroke-linecap:round;stroke-linejoin:round}

/* ─── MODAL ─── */
.v2-modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:500;display:none;align-items:flex-end;justify-content:center;padding-bottom:90px}
.v2-modal-overlay.open{display:flex}
.v2-modal-sheet{background:#0d1520;border-top-left-radius:20px;border-top-right-radius:20px;padding:20px 20px 16px;width:100%;max-width:440px;max-height:85vh;overflow-y:auto;position:relative}
.v2-modal-handle{width:36px;height:4px;background:rgba(255,255,255,0.15);border-radius:4px;margin:0 auto 16px}
.v2-modal-title{font-size:22px;font-weight:800;margin-bottom:4px;color:var(--green)}
.v2-modal-sub{font-size:20px;font-weight:700;color:var(--green);margin-bottom:16px}
.v2-modal-close{position:absolute;top:14px;right:16px;background:var(--orange);border-radius:20px;padding:5px 12px;font-size:13px;font-weight:700;color:#000}
.v2-detail-section{margin-bottom:14px}
.v2-detail-section-title{font-size:11px;font-weight:700;letter-spacing:1px;color:var(--muted);text-transform:uppercase;margin-bottom:8px}
.v2-detail-row{display:flex;align-items:center;justify-content:space-between;padding:9px 0;border-bottom:1px solid var(--border)}
.v2-detail-row:last-child{border-bottom:none}
.v2-detail-label{font-size:13px;color:var(--muted);display:flex;align-items:center;gap:8px}
.v2-detail-value{font-size:14px;font-weight:700}

/* ─── STRUCTURE TABLE ─── */
#dd-structure-wrap{width:100%;margin-top:4px;position:relative}
#dd-structure-wrap::after{content:'';position:absolute;bottom:22px;left:0;right:0;height:28px;background:linear-gradient(to bottom,transparent,rgba(13,21,32,0.92));pointer-events:none}
.v2-scroll-hint{text-align:center;font-size:11px;color:rgba(255,255,255,0.35);padding:3px 0;letter-spacing:.5px;user-select:none}
.v2-scroll-hint.hidden{visibility:hidden}
.v2-blind-table{width:100%;border-collapse:collapse;font-size:12px;table-layout:fixed}
.v2-blind-table thead tr{display:table;width:100%;table-layout:fixed}
.v2-blind-table tbody{display:block;max-height:140px;overflow-y:scroll;overflow-x:hidden;-webkit-overflow-scrolling:touch;overscroll-behavior:contain;touch-action:pan-y}
.v2-blind-table tbody tr{display:table;width:100%;table-layout:fixed}
.v2-blind-table th{color:var(--muted);font-weight:600;text-transform:uppercase;letter-spacing:.5px;font-size:10px;padding:5px 6px;border-bottom:1px solid var(--border);text-align:center}
.v2-blind-table td{padding:6px 6px;text-align:center;border-bottom:1px solid rgba(255,255,255,0.04);font-weight:600}
.v2-blind-table tr:last-child td{border-bottom:none}
.v2-blind-table td.lvl-num{color:var(--muted);font-size:11px;font-weight:400}
.v2-blind-table td.lvl-sb{color:#fff}
.v2-blind-table td.lvl-bb{color:var(--orange)}
.v2-blind-table td.lvl-ante{color:var(--green)}
.v2-blind-table td.lvl-min{color:var(--muted)}
.v2-blind-table tr.lvl-pause td{color:var(--muted);font-style:italic;font-weight:400}
.v2-blind-table tbody::-webkit-scrollbar{width:4px}
.v2-blind-table tbody::-webkit-scrollbar-track{background:rgba(255,255,255,0.06);border-radius:4px}
.v2-blind-table tbody::-webkit-scrollbar-thumb{background:rgba(255,255,255,0.30);border-radius:4px}
.v2-blind-table tbody{scrollbar-width:thin;scrollbar-color:rgba(255,255,255,0.30) rgba(255,255,255,0.06)}

/* ─── INSCRIPTION MODAL ─── */
.v2-ins-row{display:flex;align-items:center;justify-content:space-between;padding:12px 0;border-bottom:1px solid var(--border)}
.v2-ins-row:last-child{border-bottom:none}
.v2-ins-left{display:flex;align-items:center;gap:12px}
.v2-ins-icon{font-size:18px;width:28px;text-align:center}
.v2-ins-title{font-size:14px;font-weight:700}
.v2-ins-sub{font-size:12px;color:var(--muted);margin-top:1px}
/* toggle switch */
.v2-toggle{position:relative;width:44px;height:26px;flex-shrink:0}
.v2-toggle input{opacity:0;width:0;height:0;position:absolute}
.v2-toggle-track{position:absolute;inset:0;background:rgba(255,255,255,0.1);border-radius:13px;transition:background .2s}
.v2-toggle-thumb{position:absolute;top:3px;left:3px;width:20px;height:20px;background:#fff;border-radius:50%;transition:transform .2s;box-shadow:0 1px 4px rgba(0,0,0,.4)}
.v2-toggle input:checked + .v2-toggle-track{background:var(--green)}
.v2-toggle input:checked ~ .v2-toggle-thumb{transform:translateX(18px)}

/* Countdown display */
#v2-countdown{font-size:20px;font-weight:900;color:var(--green);letter-spacing:1px;line-height:1}

/* ─── CALENDAR PICKER MODAL ─── */
.v2-cal-modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.65);z-index:600;display:none;align-items:center;justify-content:center;padding:20px}
.v2-cal-modal-overlay.open{display:flex}
.v2-cal-sheet{background:#0d1520;border-radius:22px;width:100%;max-width:440px;max-height:85vh;overflow:hidden;display:flex;flex-direction:column}
.v2-cal-header{padding:12px 14px 0;flex-shrink:0}
.v2-cal-handle{width:28px;height:3px;background:rgba(255,255,255,.15);border-radius:4px;margin:0 auto 10px}
.v2-cal-title{font-size:15px;font-weight:800;margin-bottom:10px;text-align:center}
/* Month nav */
.v2-cal-nav{display:flex;align-items:center;justify-content:space-between;margin-bottom:8px}
.v2-cal-nav-btn{width:28px;height:28px;border-radius:8px;background:rgba(255,255,255,.06);display:flex;align-items:center;justify-content:center;font-size:16px;color:var(--text2)}
.v2-cal-month-label{font-size:13px;font-weight:700;color:var(--text)}
/* Day grid */
.v2-cal-grid-wrap{padding:0 14px;flex-shrink:0}
.v2-cal-dow{display:grid;grid-template-columns:repeat(7,1fr);margin-bottom:2px}
.v2-cal-dow span{text-align:center;font-size:9px;font-weight:700;letter-spacing:.5px;color:var(--muted);padding:2px 0}
.v2-cal-days{display:grid;grid-template-columns:repeat(7,1fr);gap:2px}
.v2-cal-day{aspect-ratio:1;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:600;color:var(--muted);position:relative;cursor:default;max-width:28px;max-height:28px;justify-self:center;width:100%}
.v2-cal-day.has-event{color:var(--text);cursor:pointer;background:rgba(10,132,255,.15)}
.v2-cal-day.has-event:hover,.v2-cal-day.has-event:active{background:rgba(10,132,255,.3)}
.v2-cal-day.is-next{background:var(--green) !important;color:#04180a !important;font-weight:900;box-shadow:0 0 0 2px var(--green)}
.v2-cal-day.is-selected{box-shadow:0 0 0 2px var(--orange);color:var(--orange)}
.v2-cal-day.is-past.has-event{color:var(--green);background:rgba(255,69,58,.10);box-shadow:0 0 0 2px #ff453a}
/* Event list below grid */
.v2-cal-list{overflow-y:scroll;padding:8px 6px 8px 14px;max-height:320px;min-height:0;scrollbar-width:none}
.v2-cal-list::-webkit-scrollbar{display:none}
.v2-cal-list-container{position:relative;padding-right:10px}
.v2-cal-scrollbar-track{position:absolute;top:0;right:0;width:5px;height:100%;background:#1a2535;border-radius:4px}
.v2-cal-scrollbar-thumb{position:absolute;right:0;width:5px;background:#ff9f0a;border-radius:4px;min-height:20px;transition:top .05s}
.v2-cal-list-wrap{position:relative}
.v2-cal-list-wrap::after{content:'';position:absolute;bottom:0;left:0;right:0;height:40px;background:linear-gradient(to bottom,transparent,#0d1520);pointer-events:none;transition:opacity .2s}
.v2-cal-list-wrap.at-bottom::after{opacity:0}
.v2-cal-list-title{font-size:10px;font-weight:700;letter-spacing:1px;color:var(--muted);text-transform:uppercase;margin-bottom:8px;margin-top:4px}
.v2-cal-event{display:flex;align-items:center;justify-content:space-between;gap:8px;padding:6px 10px;border-radius:8px;margin-bottom:3px;cursor:pointer;border:1px solid var(--border);transition:background .15s}
.v2-cal-event:active{background:rgba(255,255,255,.04)}
.v2-cal-event.is-next-ev{border-color:var(--green);background:rgba(52,199,89,.08)}
.v2-cal-event.is-selected-ev{border-color:var(--orange);background:rgba(255,159,10,.08)}
.v2-cal-event.is-past-ev{opacity:.55}
.v2-cal-ev-left{display:flex;align-items:center;gap:10px}
.v2-cal-ev-dot{width:8px;height:8px;border-radius:50%;flex-shrink:0;background:var(--blue)}
.v2-cal-ev-dot.next{background:var(--green)}
.v2-cal-ev-dot.past{background:var(--muted)}
.v2-cal-ev-label{font-size:13px;font-weight:700;line-height:1.3}
.v2-cal-ev-sub{font-size:11px;color:var(--muted);margin-top:2px}
.v2-cal-ev-right{font-size:12px;font-weight:700;color:var(--orange);white-space:nowrap}

/* Responsive */
@media(max-width:360px){
  .v2-date-big{font-size:22px}
  .v2-stat-val{font-size:18px}
  .v2-fin-val{font-size:14px}
  .v2-btn{font-size:12px;padding:11px 8px}
}
</style>
</head>
<body>

<?php if (!empty($serverActivity)): ?>
<script>
window.SERVER_ACTIVITY = <?php echo json_encode($serverActivity, JSON_UNESCAPED_UNICODE); ?>;
window.SERVER_PARTICIPATION = <?php echo json_encode($serverParticipation ?? null, JSON_UNESCAPED_UNICODE); ?>;
window.ALL_ACTIVITIES = <?php echo json_encode($allActivities, JSON_UNESCAPED_UNICODE); ?>;
try{ localStorage.setItem('lastActivity', JSON.stringify(window.SERVER_ACTIVITY)); }catch(e){}
</script>
<?php endif; ?>

<div class="page">

  <!-- ══════════ HEADER ══════════ -->
  <header class="v2-header">
    <div class="v2-header-left">
      <div class="v2-logo">
        <svg viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg">
          <path d="M16 2C11 8 8 11 8 15c0 4 4 6 7 6v5c0 1.2 1.2 2 2.2 2s2.2-.8 2.2-2v-5c3 0 6.6-2 6.6-6 0-4-3-7-10-13z"/>
        </svg>
      </div>
      <div>
        <div class="v2-app-name">Card<span>Event</span><span class="v2-version">V 3.0</span></div>
        <div class="v2-greeting">Bonjour, <span class="name"><?php echo $displayUser; ?></span> <span class="chev">›</span></div>
      </div>
    </div>
    <a href="/panel/profile.php<?php echo $uid_q; ?>">
      <div class="v2-avatar">
        <img src="<?php echo htmlspecialchars($avatar_url); ?>" alt="avatar">
      </div>
    </a>
  </header>

  <!-- ══════════ PROCHAINE PARTIE ══════════ -->
  <div class="v2-card">

    <!-- Label -->
    <div class="v2-next-label">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
        <rect x="3" y="4" width="18" height="18" rx="3"/><path d="M16 2v4M8 2v4M3 10h18"/>
      </svg>
      Prochaine(S) Partie(s)
    </div>

    <!-- Date + Calendar button -->
    <div class="v2-date-row">
      <div class="v2-date-big"><?php echo htmlspecialchars($date_only_str); ?><?php if($time_str): ?> <span style="font-size:19px;font-weight:700;color:var(--green)"><?php echo htmlspecialchars($time_str); ?></span><?php endif; ?></div>
      <button class="v2-cal-btn" id="v2-cal-open" title="Choisir une partie" aria-haspopup="dialog" style="width:46px;height:46px;border-radius:12px;background:rgba(10,132,255,0.18);display:flex;align-items:center;justify-content:center;flex-shrink:0;margin-top:-18px">
        <svg width="26" height="26" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <rect x="3" y="4" width="18" height="17" rx="3" fill="rgba(255,255,255,0.06)" stroke="rgba(255,255,255,0.5)" stroke-width="1.4"/>
          <path d="M3 9h18" stroke="rgba(255,255,255,0.5)" stroke-width="1.4"/>
          <path d="M8 2v4M16 2v4" stroke="rgba(255,255,255,0.5)" stroke-width="1.6" stroke-linecap="round"/>
          <rect x="6" y="12" width="3" height="2.5" rx="0.6" fill="rgba(255,255,255,0.6)"/>
          <rect x="10.5" y="12" width="3" height="2.5" rx="0.6" fill="rgba(255,255,255,0.6)"/>
          <rect x="15" y="12" width="3" height="2.5" rx="0.6" fill="rgba(255,255,255,0.3)"/>
          <rect x="6" y="16" width="3" height="2.5" rx="0.6" fill="rgba(255,255,255,0.3)"/>
          <rect x="10.5" y="16" width="3" height="2.5" rx="0.6" fill="rgba(255,255,255,0.3)"/>
          <!-- point rouge animé -->
          <circle cx="19" cy="5" r="3.5" fill="#e03030"/>
          <circle cx="19" cy="5" r="3.5" fill="#ff4444" opacity="0.6">
            <animate attributeName="r" values="3.5;5;3.5" dur="1.8s" repeatCount="indefinite"/>
            <animate attributeName="opacity" values="0.6;0;0.6" dur="1.8s" repeatCount="indefinite"/>
          </circle>
        </svg>
      </button>
    </div>

    <!-- DÉMARRE DANS / JOUEURS -->
    <div class="v2-stats">
      <div class="v2-stat">
        <div class="v2-stat-icon green">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--green)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v6l3 2"/></svg>
        </div>
        <div>
          <div class="v2-stat-label">Démarre dans</div>
          <div style="display:flex;align-items:baseline;gap:6px">
            <div id="v2-countdown" class="v2-stat-val green">--:--:--</div>
            <span id="v2-countdown-bonus" style="font-size:11px;font-weight:700;color:#60a5fa;letter-spacing:0.5px"></span>
          </div>
        </div>
      </div>
      <div class="v2-stat">
        <div class="v2-stat-icon blue">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--blue)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        </div>
        <div>
          <div class="v2-stat-label">Joueurs</div>
          <div class="v2-stat-val blue">
            <span id="v2-joueurs-count"><?php echo htmlspecialchars($serverActivity['participants_count'] ?? '—'); ?></span><?php if (!empty($serverActivity['max_participants'])): ?> <small>/ <?php echo htmlspecialchars($serverActivity['max_participants']); ?></small><?php endif; ?>
          </div>
        </div>
      </div>
    </div>

    <!-- BUY-IN / RAKE / RE-ENTRIES -->
    <div class="v2-fin">
      <div class="v2-fin-item">
        <div class="v2-fin-ico gold">
          <!-- stack of coins -->
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#ff9f0a" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><ellipse cx="12" cy="8" rx="8" ry="3"/><path d="M4 8v5c0 1.66 3.58 3 8 3s8-1.34 8-3V8"/><path d="M4 13v4c0 1.66 3.58 3 8 3s8-1.34 8-3v-4"/></svg>
        </div>
        <div>
          <div class="v2-fin-lbl">BuyIn</div>
          <div class="v2-fin-val" style="color:var(--orange)"><?php echo isset($serverActivity['buyin']) ? htmlspecialchars($serverActivity['buyin']).' €' : '—'; ?></div>
        </div>
      </div>
      <div class="v2-fin-item">
        <div class="v2-fin-ico orange">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#ff453a" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M9 6v6"/><path d="M10.5 6v6"/><path d="M15 5l-1.5 12"/></svg>
        </div>
        <div>
          <div class="v2-fin-lbl">PAF</div>
          <div class="v2-fin-val" style="color:#ff453a"><?php echo isset($serverActivity['rake']) ? htmlspecialchars($serverActivity['rake']).' €' : '—'; ?></div>
        </div>
      </div>
      <div class="v2-fin-item">
        <div class="v2-fin-ico teal">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--cyan)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 4v6h6"/><path d="M23 20v-6h-6"/><path d="M20.49 9A9 9 0 0 0 5.64 5.64L1 10m22 4-4.64 4.36A9 9 0 0 1 3.51 15"/></svg>
        </div>
        <div>
          <div class="v2-fin-lbl">ReBuy</div>
          <div class="v2-fin-val" style="color:var(--cyan)"><?php echo isset($serverActivity['recave']) ? htmlspecialchars($serverActivity['recave']) : '—'; ?></div>
        </div>
      </div>
      <div class="v2-fin-item">
        <div class="v2-fin-ico blue" style="background:rgba(10,132,255,0.12)">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--blue)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="7" height="7" rx="1.5"/><rect x="14" y="4" width="7" height="7" rx="1.5"/><rect x="3" y="13" width="7" height="7" rx="1.5"/><rect x="14" y="13" width="7" height="7" rx="1.5"/></svg>
        </div>
        <div>
          <div class="v2-fin-lbl">Tables</div>
          <div class="v2-fin-val" style="color:var(--blue)"><?php echo isset($serverActivity['tables']) ? htmlspecialchars($serverActivity['tables']) : '—'; ?></div>
        </div>
      </div>
    </div>

    <!-- Buttons -->
    <div class="v2-actions">
      <button class="v2-btn outline" id="v2-details-btn">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        Consulter Infos partie
      </button>
<?php
$_is_finished = !empty($serverActivity['date']) && (time() - strtotime($serverActivity['date'])) > 43200;
$_resume_url  = '/panel/resume.php' . $uid_q;
?>
      <?php if ($_is_finished): ?>
      <a href="<?php echo htmlspecialchars($_resume_url); ?>" class="v2-btn filled" id="v2-resume-btn" style="background:var(--blue);color:#fff;text-decoration:none">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
        Résumé Partie
      </a>
      <?php else: ?>
      <button class="v2-btn filled" id="v2-reg-btn">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5z"/></svg>
        <?php echo $is_registered ? 'Modifier mon inscription' : "S'inscrire"; ?>
      </button>
      <?php endif; ?>
    </div>



  </div><!-- /v2-card -->

  <!-- ══════════ ACTIONS RAPIDES ══════════ -->
  <div class="v2-list">

    <a class="v2-list-item tile-blue" href="/panel/livetimer.php<?php echo $uid_q; ?>">
      <div class="v2-list-icon">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v6l4 2"/></svg>
      </div>
      <div class="v2-list-body">
        <div class="v2-list-name"><?php echo $is_today ? '🟢 Partie en Cours' : 'Live Timer'; ?></div>
        <div class="v2-list-sub"><?php echo $is_today ? 'Accéder au Live Timer' : 'Accéder au timer en direct'; ?></div>
      </div>
      <div class="v2-list-chev">›</div>
    </a>

    <a class="v2-list-item tile-orange" href="<?php echo htmlspecialchars($participants_href); ?>">
      <div class="v2-list-icon">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
      </div>
      <div class="v2-list-body">
        <div class="v2-list-name"><?php echo $is_past ? 'Résultat de la partie' : 'Liste des participants'; ?></div>
        <div class="v2-list-sub"><?php echo $is_past ? 'Voir les résultats' : 'Voir les joueurs inscrits'; ?></div>
      </div>
      <div class="v2-list-chev">›</div>
    </a>

    <a class="v2-list-item tile-purple" href="/panel/profile.php<?php echo $uid_q; ?>">
      <div class="v2-list-icon">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
      </div>
      <div class="v2-list-body">
        <div class="v2-list-name">Mon profil / Historique / Stats</div>
        <div class="v2-list-sub">Voir mes stats et historique</div>
      </div>
      <div class="v2-list-chev">›</div>
    </a>

    <a class="v2-list-item tile-cyan" href="/panel/trak.php" style="text-decoration:none;color:inherit">
      <div class="v2-list-icon">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/></svg>
      </div>
      <div class="v2-list-body">
        <div class="v2-list-name">Traker</div>
        <div class="v2-list-sub">Notes sur les joueurs</div>
      </div>
      <div class="v2-list-chev">›</div>
    </a>

    <!-- Score lookup -->
    <?php
      $score_pseudos = [];
      $spq = @mysqli_query($con, "SELECT pseudo FROM membres WHERE pseudo IS NOT NULL AND pseudo != '' ORDER BY pseudo ASC");
      while ($spq && $spr = mysqli_fetch_assoc($spq)) { $score_pseudos[] = $spr['pseudo']; }
    ?>
    <div id="score-block" class="v2-list-item tile-gold" onclick="openScoreSearch(event)" style="cursor:pointer;transition:background .15s">
      <!-- Vue compacte (par défaut) -->
      <div id="score-collapsed" class="v2-score-collapsed">
        <div class="v2-list-icon">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
        </div>
        <div class="v2-list-body">
          <div class="v2-list-name">Scoring Joueur</div>
          <div class="v2-list-sub">Historique SergioScore d'un joueur</div>
        </div>
        <div class="v2-list-chev">›</div>
      </div>
      <!-- Vue formulaire (cachée par défaut) -->
      <div id="score-expanded" class="v2-score-expanded">
        <div class="v2-list-title-row">
          <div class="v2-list-icon">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
          </div>
          <div class="v2-list-body">
            <div class="v2-list-name">Scoring Joueur</div>
            <div class="v2-list-sub">Historique SergioScore d'un joueur</div>
          </div>
        </div>
        <form id="score-form" onsubmit="goScore(event)" class="v2-score-form">
          <input id="score-pseudo" type="text" placeholder="Pseudo du joueur…"
            autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false"
            oninput="scoreAC(this.value)" onkeydown="scoreACKey(event)"
            class="v2-score-input" />
          <button type="submit" class="v2-score-submit">Voir ›</button>
        </form>
        <div id="score-ac-list" class="v2-score-ac"></div>
      </div>
    </div>

    <a class="v2-list-item tile-red" href="/newtimer/index.php" style="text-decoration:none;color:inherit">
      <div class="v2-list-icon">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v6l4 2"/></svg>
      </div>
      <div class="v2-list-body">
        <div class="v2-list-name">Votre Timer</div>
        <div class="v2-list-sub">Ouvrir le nouveau timer</div>
      </div>
      <div class="v2-list-chev">›</div>
    </a>

    <a class="v2-list-item tile-green" href="/panel/repartition.php" style="text-decoration:none;color:inherit">
      <div class="v2-list-icon">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7h18"/><path d="M6 3v8"/><path d="M18 3v18"/><path d="M3 17h12"/></svg>
      </div>
      <div class="v2-list-body">
        <div class="v2-list-name">Répartition</div>
        <div class="v2-list-sub">Voir la répartition des tables</div>
      </div>
      <div class="v2-list-chev">›</div>
    </a>

    <script>
    var SCORE_PSEUDOS = <?php echo json_encode($score_pseudos, JSON_UNESCAPED_UNICODE); ?>;
    var scoreACIdx = -1;

    function openScoreSearch(e) {
      var block = document.getElementById('score-block');
      block.classList.add('tile-open');
      document.getElementById('score-collapsed').style.display = 'none';
      var exp = document.getElementById('score-expanded');
      exp.style.display = 'flex';
      block.style.cursor = 'default';
      document.getElementById('score-pseudo').focus();
    }

    function scoreAC(val) {
      var list = document.getElementById('score-ac-list');
      scoreACIdx = -1;
      if (!val.trim()) { list.style.display='none'; list.innerHTML=''; return; }
      var q = val.toLowerCase();
      var matches = SCORE_PSEUDOS.filter(function(p){ return p.toLowerCase().indexOf(q) !== -1; }).slice(0,10);
      if (!matches.length) { list.style.display='none'; list.innerHTML=''; return; }
      list.innerHTML = matches.map(function(p,i){
        return '<div data-idx="'+i+'" onmousedown="scoreACPick(\''+p.replace(/'/g,"\\'")+'\')" style="padding:10px 14px;font-size:14px;color:#e0e8f0;border-bottom:1px solid rgba(255,255,255,0.06);cursor:pointer">'+p+'</div>';
      }).join('');
      list.style.display = 'block';
    }

    function scoreACPick(pseudo) {
      document.getElementById('score-pseudo').value = pseudo;
      document.getElementById('score-ac-list').style.display = 'none';
      goScore(null);
    }

    function scoreACKey(e) {
      var list = document.getElementById('score-ac-list');
      var items = list.querySelectorAll('div');
      if (!items.length) return;
      if (e.key === 'ArrowDown') { e.preventDefault(); scoreACIdx = Math.min(scoreACIdx+1, items.length-1); scoreACHighlight(items); }
      else if (e.key === 'ArrowUp') { e.preventDefault(); scoreACIdx = Math.max(scoreACIdx-1, 0); scoreACHighlight(items); }
      else if (e.key === 'Enter' && scoreACIdx >= 0) { e.preventDefault(); scoreACPick(items[scoreACIdx].textContent); }
      else if (e.key === 'Escape') { list.style.display='none'; }
    }

    function scoreACHighlight(items) {
      items.forEach(function(el,i){ el.style.background = i===scoreACIdx ? 'rgba(255,180,0,0.15)' : ''; });
      if (scoreACIdx >= 0) items[scoreACIdx].scrollIntoView({block:'nearest'});
    }

    function goScore(e) {
      if (e) e.preventDefault();
      var pseudo = document.getElementById('score-pseudo').value.trim();
      // Si un seul choix en autocomplétion, utiliser ce choix
      var list = document.getElementById('score-ac-list');
      var items = list.querySelectorAll('div');
      if (items.length === 1) { pseudo = items[0].textContent.trim(); }
      if (!pseudo) { document.getElementById('score-pseudo').focus(); return; }
      window.location.href = '/panel/sergio.php?pseudo=' + encodeURIComponent(pseudo);
    }
    </script>

  </div>

</div><!-- /page -->

<!-- ══════════ BOTTOM NAV ══════════ -->
<nav class="v2-bottom-nav">
  <button class="v2-nav-btn active" onclick="window.location.href='/panel/quickview_v2.php'">
    <svg viewBox="0 0 24 24"><path d="M3 11.5L12 4l9 7.5"/><path d="M5 21h14a1 1 0 0 0 1-1v-7H4v7a1 1 0 0 0 1 1z"/></svg>
    Accueil
  </button>
  <button class="v2-nav-btn" onclick="window.location.href='/newtimer/index.php'">
    <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v6l4 2"/></svg>
    Votre Timer
  </button>
  <button class="v2-nav-btn" onclick="window.location.href='/panel/repartition.php'">
    <svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
    Répartition
  </button>
</nav>

<!-- ══════════ CALENDAR PICKER MODAL ══════════ -->
<div class="v2-cal-modal-overlay" id="v2-cal-modal" aria-hidden="true">
  <div class="v2-cal-sheet" role="dialog" aria-modal="true" aria-label="Choisir une partie">
    <div class="v2-cal-header">
      <div class="v2-cal-handle"></div>
      <div class="v2-cal-title">Choisir une partie</div>
      <div class="v2-cal-nav">
        <button class="v2-cal-nav-btn" id="v2-cal-prev">‹</button>
        <div class="v2-cal-month-label" id="v2-cal-month-label">—</div>
        <button class="v2-cal-nav-btn" id="v2-cal-next">›</button>
      </div>
    </div>
    <div class="v2-cal-grid-wrap">
      <div class="v2-cal-dow">
        <span>Lun</span><span>Mar</span><span>Mer</span><span>Jeu</span><span>Ven</span><span>Sam</span><span>Dim</span>
      </div>
      <div class="v2-cal-days" id="v2-cal-days"></div>
    </div>
    <div class="v2-cal-list-container">
      <div class="v2-cal-list" id="v2-cal-list">
        <div class="v2-cal-list-title">Parties du mois</div>
        <div class="v2-cal-list-wrap" id="v2-cal-list-wrap">
          <div id="v2-cal-events"></div>
        </div>
      </div>
      <div class="v2-cal-scrollbar-track" id="v2-cal-sb-track"><div class="v2-cal-scrollbar-thumb" id="v2-cal-sb-thumb"></div></div>
    </div>
  </div>
</div>

<!-- ══════════ DETAILS MODAL ══════════ -->
<div class="v2-modal-overlay" id="v2-details-modal" aria-hidden="true">
  <div class="v2-modal-sheet" role="dialog" aria-modal="true" style="position:relative">
    <button class="v2-modal-close" id="v2-details-close" style="position:absolute;top:14px;right:14px;background:rgba(255,255,255,0.06);padding:6px 14px;border-radius:20px;border:0;color:#ff9d3b;font-weight:700;font-size:14px;cursor:pointer;z-index:10">Fermer</button>
    <div class="v2-modal-handle"></div>
    <div class="v2-modal-sub" id="v2-modal-sub" style="display:flex;align-items:center;gap:8px">
      <span id="v2-modal-time" style="font-size:14px;font-weight:700;color:rgba(255,255,255,0.7)"></span>
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="flex-shrink:0;opacity:0.85">
        <rect x="3" y="4" width="18" height="17" rx="3" fill="rgba(255,255,255,0.06)" stroke="rgba(255,255,255,0.5)" stroke-width="1.4"/>
        <path d="M3 9h18" stroke="rgba(255,255,255,0.5)" stroke-width="1.4"/>
        <path d="M8 2v4M16 2v4" stroke="rgba(255,255,255,0.5)" stroke-width="1.6" stroke-linecap="round"/>
        <rect x="6" y="12" width="3" height="2.5" rx="0.6" fill="rgba(255,255,255,0.6)"/>
        <rect x="10.5" y="12" width="3" height="2.5" rx="0.6" fill="rgba(255,255,255,0.6)"/>
        <rect x="15" y="12" width="3" height="2.5" rx="0.6" fill="rgba(255,255,255,0.3)"/>
        <rect x="6" y="16" width="3" height="2.5" rx="0.6" fill="rgba(255,255,255,0.3)"/>
        <rect x="10.5" y="16" width="3" height="2.5" rx="0.6" fill="rgba(255,255,255,0.3)"/>
      </svg>
      <span id="v2-modal-sub-text">—</span>
    </div>

    <div class="v2-detail-section">
      <div class="v2-detail-section-title" style="display:none">Infos Partie</div>
      <div class="v2-detail-row"><div class="v2-detail-label">👤 Organisateur</div><div class="v2-detail-value" id="dd-organisateur">—</div></div>
      <div class="v2-detail-row" id="dd-lieu-row" style="cursor:pointer" onclick="document.getElementById('dd-maps-link').click()"><div class="v2-detail-label"><a id="dd-maps-link" href="#" target="_blank" style="text-decoration:none;color:inherit">📍 Lieu</a></div><div class="v2-detail-value" style="color:var(--cyan);text-align:right" id="dd-lieu-wrap"><span id="dd-lieu">—</span><span id="dd-tel-wrap" style="display:none"><br><a id="dd-tel" href="#" style="font-size:12px;color:var(--muted);font-weight:500;text-decoration:none"></a></span></div></div>
      <div class="v2-detail-row"><div class="v2-detail-label">👥 Inscrits / Max</div><div class="v2-detail-value" id="dd-inscrits">—</div></div>
      <div class="v2-detail-row"><div class="v2-detail-label">▦ Tables</div><div class="v2-detail-value" style="color:var(--green)" id="dd-tables">—</div></div>
    </div>

    <div class="v2-detail-section">
      <div class="v2-detail-section-title" style="text-align:center;color:#4cd964">Infos Financières</div>
      <div class="v2-detail-row"><div class="v2-detail-label">💶 Buy-in</div><div class="v2-detail-value" style="color:var(--orange)" id="dd-buyin">—</div></div>
      <div class="v2-detail-row"><div class="v2-detail-label">🍽️ Participation Aux Frais</div><div class="v2-detail-value" id="dd-rake">—</div></div>
      <div class="v2-detail-row" id="dd-bounty-row"><div class="v2-detail-label">🎯 Bounty</div><div class="v2-detail-value" id="dd-bounty">—</div></div>
      <div class="v2-detail-row"><div class="v2-detail-label">🔁 Re-entries</div><div class="v2-detail-value" id="dd-recave">—</div></div>
      <div class="v2-detail-row"><div class="v2-detail-label">▦ Nb Tables</div><div class="v2-detail-value" id="dd-nb-tables">—</div></div>
      <div class="v2-detail-row"><div class="v2-detail-label">🎲 Jetons départ Hors Bonus 1 & 2</div><div class="v2-detail-value" style="color:var(--orange)" id="dd-jetons">—</div></div>
    </div>

    <div class="v2-detail-section" style="margin-bottom:0">
      <div id="dd-structure-wrap"><div style="color:var(--muted);font-size:13px;padding:8px 0" id="dd-structure-empty">—</div></div>
      <div class="v2-scroll-hint hidden" id="dd-structure-hint">▼ défiler</div>
    </div>
  </div>
</div>

<!-- ══════════ INSCRIPTION MODAL ══════════ -->
<div class="v2-modal-overlay" id="v2-ins-modal" aria-hidden="true">
  <div class="v2-modal-sheet" role="dialog" aria-modal="true">
    <div class="v2-modal-handle"></div>
    <button class="v2-modal-close" id="v2-ins-close">Fermer</button>
    <div class="v2-modal-title">Options d'inscription</div>
    <div class="v2-modal-sub" style="margin-bottom:20px">Configurez votre inscription</div>

    <form id="v2-ins-form" method="post" action="/panel/inscription.php">
      <input type="hidden" name="quick_reg" value="1">
      <input type="hidden" name="ajax" value="1">
      <input type="hidden" name="uid" value="">
      <input type="hidden" name="status" value="Inscrit">
      <input type="hidden" name="anonyme" value="0">
      <input type="hidden" name="latereg" value="0">

      <div class="v2-ins-row">
        <div class="v2-ins-left">
          <div class="v2-ins-icon">👁️</div>
          <div><div class="v2-ins-title">Anonyme</div><div class="v2-ins-sub">Nom masqué publiquement</div></div>
        </div>
        <label class="v2-toggle">
          <input type="checkbox" id="v2-anon">
          <div class="v2-toggle-track"></div>
          <div class="v2-toggle-thumb"></div>
        </label>
      </div>

      <div class="v2-ins-row">
        <div class="v2-ins-left">
          <div class="v2-ins-icon" style="color:var(--orange)">★</div>
          <div><div class="v2-ins-title">Option</div><div class="v2-ins-sub">Inscription sous réserve</div></div>
        </div>
        <label class="v2-toggle">
          <input type="checkbox" id="v2-opt">
          <div class="v2-toggle-track"></div>
          <div class="v2-toggle-thumb"></div>
        </label>
      </div>

      <div class="v2-ins-row">
        <div class="v2-ins-left">
          <div class="v2-ins-icon">⏱️</div>
          <div><div class="v2-ins-title">Latereg</div><div class="v2-ins-sub">Inscription tardive</div></div>
        </div>
        <label class="v2-toggle">
          <input type="checkbox" id="v2-late">
          <div class="v2-toggle-track"></div>
          <div class="v2-toggle-thumb"></div>
        </label>
      </div>

      <div class="v2-ins-row" style="border-bottom:none;padding-top:14px">
        <input type="text" name="option_chapitre" placeholder="Option / Chapitre" style="width:100%;padding:11px 14px;border-radius:10px;border:1px solid rgba(255,255,255,0.1);background:rgba(255,255,255,0.04);color:var(--text);font-size:14px">
      </div>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:20px">
        <button type="submit" id="v2-ins-validate" style="padding:14px;border-radius:12px;background:#17a34a;color:#fff;font-weight:800;font-size:15px">Valider</button>
        <button type="button" id="v2-ins-unregister" style="padding:14px;border-radius:12px;background:#c92b2b;color:#fff;font-weight:800;font-size:15px">Désinscrire</button>
      </div>
    </form>
  </div>
</div>

<!-- ══════════ TRAK MODAL ══════════ -->
<div class="v2-modal-overlay" id="v2-trak-modal" aria-hidden="true">
  <div class="v2-modal-sheet" role="dialog" aria-modal="true" style="position:relative;display:flex;flex-direction:column;max-height:85vh">
    <button onclick="document.getElementById('v2-trak-modal').setAttribute('aria-hidden','true')" style="position:absolute;top:14px;right:14px;background:rgba(255,255,255,0.06);padding:6px 14px;border-radius:20px;border:0;color:#ff9d3b;font-weight:700;font-size:14px;cursor:pointer;z-index:10">Fermer</button>
    <div class="v2-modal-handle"></div>
    <div class="v2-modal-title" id="v2-trak-title" style="padding-right:70px">Notes – joueur</div>

    <!-- Filtre mode Écrites/Reçues -->
    <div style="display:flex;gap:8px;padding:8px 16px 4px">
      <button id="v2-trak-btn-ecrites" onclick="trakSetMode('auteur')" style="flex:1;padding:7px;border-radius:10px;border:0;font-weight:700;font-size:13px;cursor:pointer;background:#17a34a;color:#fff">✏️ Écrites</button>
      <button id="v2-trak-btn-recues"  onclick="trakSetMode('cible')"  style="flex:1;padding:7px;border-radius:10px;border:0;font-weight:700;font-size:13px;cursor:pointer;background:rgba(255,255,255,0.07);color:var(--muted)">📥 Reçues</button>
    </div>

    <!-- Liste des notes -->
    <div id="v2-trak-list" style="flex:1;overflow-y:auto;padding:8px 16px;min-height:100px">
      <div id="v2-trak-loading" style="text-align:center;padding:20px;color:var(--muted)">Chargement…</div>
    </div>

    <!-- Zone saisie -->
    <div style="padding:10px 14px;border-top:1px solid rgba(255,255,255,0.07);display:flex;gap:8px;align-items:flex-end">
      <textarea id="v2-trak-input" placeholder="Ajouter une note…" rows="2" style="flex:1;padding:10px 12px;border-radius:10px;border:1px solid rgba(255,255,255,0.1);background:rgba(255,255,255,0.04);color:var(--text);font-size:14px;resize:none;font-family:inherit"></textarea>
      <button id="v2-trak-send" onclick="trakSend()" style="padding:10px 14px;border-radius:10px;background:#17a34a;color:#fff;font-weight:700;border:0;cursor:pointer;font-size:18px;align-self:flex-end">➤</button>
    </div>
  </div>
</div>

<script>
// ─── TRAK MODAL ───
var trakState = { pseudo:'', activityId:0, mode:'auteur', notes:[], myPseudo:'<?php echo addslashes($_SESSION["pseudo"] ?? ""); ?>' };

function trakOpen(pseudo, activityId) {
  trakState.pseudo = pseudo;
  trakState.activityId = activityId || 0;
  trakState.mode = 'auteur';
  document.getElementById('v2-trak-title').textContent = 'Notes – ' + pseudo;
  document.getElementById('v2-trak-input').value = '';
  trakSetMode('auteur');
  document.getElementById('v2-trak-modal').setAttribute('aria-hidden','false');
  trakLoad();
}

function trakSetMode(mode) {
  trakState.mode = mode;
  var btnE = document.getElementById('v2-trak-btn-ecrites');
  var btnR = document.getElementById('v2-trak-btn-recues');
  btnE.style.background = mode==='auteur' ? '#17a34a' : 'rgba(255,255,255,0.07)';
  btnE.style.color      = mode==='auteur' ? '#fff' : 'var(--muted)';
  btnR.style.background = mode==='cible'  ? '#17a34a' : 'rgba(255,255,255,0.07)';
  btnR.style.color      = mode==='cible'  ? '#fff' : 'var(--muted)';
  trakRender();
}

function trakLoad() {
  document.getElementById('v2-trak-list').innerHTML = '<div style="text-align:center;padding:20px;color:var(--muted)">Chargement…</div>';
  fetch('/api/trak-notes.php?pseudo=' + encodeURIComponent(trakState.pseudo), {
    credentials: 'include',
    headers: { 'Authorization': 'Bearer <?php echo addslashes($_SESSION["token"] ?? ""); ?>' }
  })
  .then(r => r.json())
  .then(data => {
    if (data.success) {
      trakState.notes    = data.notes || [];
      trakState.isAdmin  = data.is_admin || false;
      trakState.idCible  = data.id_cible || 0;
    } else {
      trakState.notes = [];
    }
    trakRender();
  })
  .catch(() => {
    document.getElementById('v2-trak-list').innerHTML = '<div style="color:#ff6b6b;padding:12px">Erreur réseau</div>';
  });
}

function trakRender() {
  var mode  = trakState.mode;
  var myId  = <?php echo intval($_SESSION['id'] ?? 0); ?>;
  var notes = trakState.notes.filter(n =>
    mode === 'auteur' ? n.id_auteur === myId : n.id_cible === myId
  );
  if (!notes.length) {
    document.getElementById('v2-trak-list').innerHTML =
      '<div style="color:var(--muted);padding:12px;text-align:center">' +
      (trakState.notes.length ? 'Aucun résultat' : 'Aucune note pour ce joueur') + '</div>';
    return;
  }
  var html = notes.map(n => {
    var displayPseudo = mode === 'auteur' ? n.cible_pseudo : n.auteur_pseudo;
    var actLabel = n.date_activite
      ? n.date_activite + (n.titre_activite ? ' — ' + n.titre_activite : '')
      : n.titre_activite;
    var canDelete = n.id_auteur === myId;
    return '<div style="padding:10px 0;border-bottom:1px solid rgba(255,255,255,0.05)">' +
      '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px">' +
        '<span style="font-size:12px;font-weight:700;color:var(--cyan)">' + escTrak(displayPseudo) + '</span>' +
        '<div style="display:flex;gap:8px;align-items:center">' +
          '<span style="font-size:11px;color:var(--muted)">' + escTrak(trakFormatDate(n.created_at)) + '</span>' +
          (canDelete ? '<button onclick="trakDelete('+n.id+')" style="background:none;border:0;color:#ff6b6b;cursor:pointer;font-size:13px;padding:0">🗑</button>' : '') +
        '</div>' +
      '</div>' +
      '<div style="font-size:14px;line-height:1.5">' + escTrak(n.note) + '</div>' +
      (actLabel ? '<div style="font-size:11px;color:var(--muted);margin-top:4px">📅 ' + escTrak(actLabel) + '</div>' : '') +
    '</div>';
  }).join('');
  document.getElementById('v2-trak-list').innerHTML = html;
}

function trakSend() {
  var text = (document.getElementById('v2-trak-input').value || '').trim();
  if (!text) return;
  var btn = document.getElementById('v2-trak-send');
  btn.disabled = true;
  fetch('/api/trak-notes.php', {
    method: 'POST',
    credentials: 'include',
    headers: { 'Content-Type':'application/json', 'Authorization':'Bearer <?php echo addslashes($_SESSION["token"] ?? ""); ?>' },
    body: JSON.stringify({ action:'add', pseudo_cible: trakState.pseudo, note: text, id_activite: trakState.activityId })
  })
  .then(r => r.json())
  .then(data => {
    if (data.success && data.note) {
      trakState.notes.unshift(data.note);
      document.getElementById('v2-trak-input').value = '';
      trakSetMode('auteur');
      trakRender();
    }
    btn.disabled = false;
  })
  .catch(() => { btn.disabled = false; });
}

function trakDelete(id) {
  if (!confirm('Supprimer cette note ?')) return;
  fetch('/api/trak-notes.php', {
    method: 'POST',
    credentials: 'include',
    headers: { 'Content-Type':'application/json', 'Authorization':'Bearer <?php echo addslashes($_SESSION["token"] ?? ""); ?>' },
    body: JSON.stringify({ action:'delete', id: id })
  })
  .then(r => r.json())
  .then(data => {
    if (data.success) {
      trakState.notes = trakState.notes.filter(n => n.id !== id);
      trakRender();
    }
  });
}

function trakFormatDate(str) {
  if (!str) return '';
  var d = new Date(str.replace(' ', 'T'));
  if (isNaN(d)) return str;
  return ('0'+d.getDate()).slice(-2)+'/'+('0'+(d.getMonth()+1)).slice(-2)+'/'+String(d.getFullYear()).slice(-2)+' '+('0'+d.getHours()).slice(-2)+':'+('0'+d.getMinutes()).slice(-2);
}

function escTrak(s) {
  if (!s) return '';
  return String(s).replace(/[&<>"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));
}
</script>

<script>
// ─── CALENDAR PICKER ───
(function(){
  var overlay  = document.getElementById('v2-cal-modal');
  var openBtn  = document.getElementById('v2-cal-open');
  var daysEl   = document.getElementById('v2-cal-days');
  var eventsEl = document.getElementById('v2-cal-events');
  var listEl   = document.getElementById('v2-cal-list');
  var listWrap = document.getElementById('v2-cal-list-wrap');
  var monthLbl = document.getElementById('v2-cal-month-label');
  var prevBtn  = document.getElementById('v2-cal-prev');
  var nextBtn  = document.getElementById('v2-cal-next');

  function updateScrollFade(){
    if(!listEl || !listWrap) return;
    var atBottom = listEl.scrollTop + listEl.clientHeight >= listEl.scrollHeight - 4;
    listWrap.classList.toggle('at-bottom', atBottom);
    var track = document.getElementById('v2-cal-sb-track');
    var thumb = document.getElementById('v2-cal-sb-thumb');
    if(track && thumb && listEl.scrollHeight > listEl.clientHeight){
      var ratio = listEl.clientHeight / listEl.scrollHeight;
      var thumbH = Math.max(20, track.clientHeight * ratio);
      var thumbTop = (listEl.scrollTop / (listEl.scrollHeight - listEl.clientHeight)) * (track.clientHeight - thumbH);
      thumb.style.height = thumbH + 'px';
      thumb.style.top = thumbTop + 'px';
      track.style.display = 'block';
    } else if(track){
      track.style.display = 'none';
    }
  }
  if(listEl) listEl.addEventListener('scroll', updateScrollFade);

  var acts = window.ALL_ACTIVITIES || [];
  var current = window.SERVER_ACTIVITY || {};
  var nowTs = Math.floor(Date.now()/1000);

  // Find next activity (smallest future ts)
  var nextAct = null;
  acts.forEach(function(a){ if(!a.past && (!nextAct || a.ts < nextAct.ts)) nextAct = a; });

  var moisNames = ['Janvier','Février','Mars','Avril','Mai','Juin','Juillet','Août','Septembre','Octobre','Novembre','Décembre'];

  // State: current displayed month/year
  var viewDate = nextAct ? new Date(nextAct.ts*1000) : new Date();
  var viewMonth = viewDate.getMonth(); // 0-based
  var viewYear  = viewDate.getFullYear();

  function getActsForMonth(m, y){
    return acts.filter(function(a){ return a.month-1===m && a.year===y; });
  }

  function render(){
    monthLbl.textContent = moisNames[viewMonth] + ' ' + viewYear;

    // Build day grid
    var firstDay = new Date(viewYear, viewMonth, 1);
    var dow = firstDay.getDay(); // 0=Sun
    // Convert to Mon-first (0=Mon … 6=Sun)
    var offset = (dow === 0) ? 6 : dow - 1;
    var daysInMonth = new Date(viewYear, viewMonth+1, 0).getDate();

    var monthActs = getActsForMonth(viewMonth, viewYear);
    // Build map day -> array of acts
    var dayMap = {};
    monthActs.forEach(function(a){ (dayMap[a.day] = dayMap[a.day]||[]).push(a); });

    var html = '';
    // Empty cells
    for(var i=0;i<offset;i++) html += '<div class="v2-cal-day"></div>';
    for(var d=1;d<=daysInMonth;d++){
      var cls = 'v2-cal-day';
      var hasActs = dayMap[d] && dayMap[d].length;
      if(hasActs) cls += ' has-event';
      // Check if any act on this day is past
      if(hasActs && dayMap[d].every(function(a){ return a.past; })) cls += ' is-past';
      // Focus: is-next
      if(hasActs && nextAct && dayMap[d].some(function(a){ return a.id===nextAct.id; })) cls += ' is-next';
      // Selected
      if(hasActs && current.id && dayMap[d].some(function(a){ return a.id===current.id; })) cls += ' is-selected';
      var dataIds = hasActs ? 'data-day="'+d+'"' : '';
      html += '<div class="'+cls+'" '+dataIds+'">'+d+'</div>';
    }
    daysEl.innerHTML = html;

    // Click on day cell
    daysEl.querySelectorAll('.v2-cal-day.has-event').forEach(function(el){
      el.addEventListener('click', function(){
        var day = parseInt(el.getAttribute('data-day'));
        var dayActs = dayMap[day] || [];
        if(dayActs.length === 1){
          navigate(dayActs[0].id);
        } else {
          // scroll to events list
          el.closest('.v2-cal-sheet').querySelector('.v2-cal-list').scrollIntoView({behavior:'smooth'});
        }
      });
    });

    // Render event list
    var listHtml = '';
    if(monthActs.length === 0){
      listHtml = '<div style="color:var(--muted);font-size:13px;text-align:center;padding:16px 0">Aucune partie ce mois-ci</div>';
    } else {
      // Sort ascending
      var sorted = monthActs.slice().sort(function(a,b){ return a.ts-b.ts; });
      sorted.forEach(function(a){
        var isNext = nextAct && a.id === nextAct.id;
        var isSel  = current.id && a.id === current.id;
        var cls2 = 'v2-cal-event';
        if(isNext) cls2 += ' is-next-ev';
        if(isSel)  cls2 += ' is-selected-ev';
        if(a.past) cls2 += ' is-past-ev';
        var dotCls = a.past ? 'past' : (isNext ? 'next' : '');
        var tag = isNext ? ' <span style="background:var(--green);color:#04180a;font-size:9px;font-weight:800;padding:2px 6px;border-radius:20px;vertical-align:middle;margin-left:4px">PROCHAIN</span>' : '';
        listHtml += '<div class="'+cls2+'" data-id="'+a.id+'">';
        listHtml += '<div class="v2-cal-ev-left"><div class="v2-cal-ev-dot '+dotCls+'"></div><div>';
        listHtml += '<div class="v2-cal-ev-label">'+escHtml(a.label)+tag+'</div>';
        listHtml += '</div></div>';
        listHtml += '<div class="v2-cal-ev-right">'+(a.buyin ? a.buyin+' €' : '')+'</div>';
        listHtml += '</div>';
      });
    }
    eventsEl.innerHTML = listHtml;
    updateScrollFade();
    eventsEl.querySelectorAll('.v2-cal-event[data-id]').forEach(function(el){
      el.addEventListener('click', function(){ navigate(parseInt(el.getAttribute('data-id'))); });
    });
    // Auto-scroll list to selected/next
    setTimeout(function(){
      var focused = eventsEl.querySelector('.is-next-ev, .is-selected-ev');
      if(focused) focused.scrollIntoView({block:'nearest',behavior:'smooth'});
    }, 80);
  }

  function navigate(id){
    var url = new URL(window.location.href);
    url.searchParams.set('uid', id);
    window.location.href = url.toString();
  }

  function escHtml(s){ return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

  function open(){
    // Jump to month of next activity by default, or current selected
    var target = acts.find(function(a){ return current.id && a.id===current.id; }) || nextAct;
    if(target){ viewMonth = target.month-1; viewYear = target.year; }
    render();
    overlay.classList.add('open'); overlay.setAttribute('aria-hidden','false');
  }
  function close(){ overlay.classList.remove('open'); overlay.setAttribute('aria-hidden','true'); }

  if(openBtn) openBtn.addEventListener('click', open);
  if(overlay) overlay.addEventListener('click', function(e){ if(e.target===overlay) close(); });
  if(prevBtn) prevBtn.addEventListener('click', function(){ viewMonth--; if(viewMonth<0){viewMonth=11;viewYear--;} render(); });
  if(nextBtn) nextBtn.addEventListener('click', function(){ viewMonth++; if(viewMonth>11){viewMonth=0;viewYear++;} render(); });
})();
</script>

<script>
// ─── COUNTDOWN ───
(function(){
  var actStr = <?php echo (!empty($serverActivity['date'])) ? '"'.addslashes($serverActivity['date']).'"' : 'null'; ?>;
  var el = document.getElementById('v2-countdown');
  if(!el || !actStr) return;
  var ts = Math.floor(new Date(actStr.replace(' ','T')).getTime() / 1000);
  function tick(){
    var diff = ts - Math.floor(Date.now()/1000);
    if(diff <= 0){
      if(diff < -43200){ el.textContent = 'Terminée'; el.style.color = 'var(--muted)'; }
      else { el.textContent = 'En cours'; el.style.color = 'var(--orange)'; }
      el.nextElementSibling && (el.nextElementSibling.textContent = '');
      return;
    }
    var h = Math.floor(diff/3600).toString().padStart(2,'0');
    var m = Math.floor((diff%3600)/60).toString().padStart(2,'0');
    var s = (diff%60).toString().padStart(2,'0');
    el.textContent = h+'h'+m+'m'+s+'s';
    // Bonus jetons selon temps restant
    var bonusEl = document.getElementById('v2-countdown-bonus');
    if (bonusEl) {
      var diffH = diff / 3600;
      var bonus = Math.min(5000, (Math.floor(diffH) + 1) * 200);
      bonusEl.textContent = '(+' + bonus + ')';
    }
  }
  tick();
  setInterval(tick, 1000);
})();

// ─── DETAILS MODAL ───
(function(){
  var btn    = document.getElementById('v2-details-btn');
  var modal  = document.getElementById('v2-details-modal');
  var close  = document.getElementById('v2-details-close');
  function open(){
    var act = window.SERVER_ACTIVITY || {};
    var f = function(id,v){ var e=document.getElementById(id); if(e) e.textContent = v||'—'; };
    f('v2-modal-title', act.title || '—');
    var dateEl = document.getElementById('v2-modal-sub-text');
    if (dateEl) {
      var heureHtml = act.date_heure ? ' <span style="font-size:11px;font-weight:600;opacity:0.6">(' + act.date_heure.replace(':', 'h') + ')</span>' : '';
      dateEl.innerHTML = (act.display_date || '—') + heureHtml;
    }
    var timeEl = document.getElementById('v2-modal-time');
    if (timeEl) timeEl.textContent = '';
    f('dd-organisateur', act.organizer || '—');
    f('dd-lieu', act.location || '—');
    // Mise à jour lien maps
    var mapsLink = document.getElementById('dd-maps-link');
    if (mapsLink && act.location) {
      var mapsQuery = encodeURIComponent(act.location);
      // Sur iOS ouvre Plans, sur Android Google Maps, sinon Google Maps web
      mapsLink.href = 'https://maps.apple.com/?q=' + mapsQuery;
    }
    var telWrap = document.getElementById('dd-tel-wrap');
    var telEl = document.getElementById('dd-tel');
    if (telWrap && telEl) {
      var loc = act.location || '';
      var telMatch = loc.match(/(?:^|\s|[,\-\/])\s*((?:0|\+33)[0-9\s\.\-]{8,14}[0-9])/);
      var phone = null;
      if (telMatch) {
        phone = telMatch[1].replace(/[\s\.\-]/g,'');
        if (phone.length === 10 || (phone.startsWith('+33') && phone.length === 12)) {
          // affiche le lieu sans le numéro
          var cleanLoc = loc.replace(telMatch[0], '').replace(/[,\-\/\s]+$/, '').trim();
          var lieuEl = document.getElementById('dd-lieu');
          if (lieuEl) lieuEl.textContent = cleanLoc || loc;
          telEl.textContent = '📞 ' + phone;
          telEl.href = 'tel:' + phone;
          telWrap.style.display = 'inline';
        } else {
          telWrap.style.display = 'none';
        }
      } else {
        telWrap.style.display = 'none';
      }
    }
    f('dd-inscrits', (act.participants_count!=null) ? act.participants_count+(act.max_participants?' / '+act.max_participants:'') : '—');
    f('dd-tables', act.tables || '—');
    f('dd-buyin', act.buyin!=null ? act.buyin+' €' : '—');
    f('dd-rake', act.rake!=null ? act.rake+' €' : '—');
    var bountyNum = Number(act.bounty || 0);
    var bountyRow = document.getElementById('dd-bounty-row');
    if (bountyRow) bountyRow.style.display = bountyNum > 0 ? 'flex' : 'none';
    f('dd-bounty', bountyNum > 0 ? bountyNum+' €' : '—');
    f('dd-recave', act.recave || '—');
    f('dd-jetons', act.start_chips || '—');
    // Build blind levels table
    var structWrap = document.getElementById('dd-structure-wrap');
    var structEmpty = document.getElementById('dd-structure-empty');
    var levels = act.structure_levels || [];
    if (structWrap && levels.length > 0) {
      var html = '<table class="v2-blind-table"><thead><tr>';
      html += '<th>#</th><th>SB</th><th>BB</th><th>Ante</th><th>Min</th>';
      html += '</tr></thead><tbody>';
      for (var li = 0; li < levels.length; li++) {
        var lv = levels[li];
        var isPause = lv.sb === 0 || (lv.nom && /pause|break/i.test(lv.nom));
        if (isPause) {
          html += '<tr class="lvl-pause"><td colspan="5">' + (lv.nom || 'Pause') + ' — ' + (lv.minutes ? lv.minutes + ' min' : '') + '</td></tr>';
        } else {
          html += '<tr>';
          html += '<td class="lvl-num">' + lv.ordre + '</td>';
          html += '<td class="lvl-sb">' + lv.sb + '</td>';
          html += '<td class="lvl-bb">' + lv.bb + '</td>';
          html += '<td class="lvl-ante">' + (lv.ante || '—') + '</td>';
          html += '<td class="lvl-min">' + (lv.minutes || '—') + '</td>';
          html += '</tr>';
        }
      }
      html += '</tbody></table>';
      structWrap.innerHTML = html;
      // Show scroll hint if content overflows
      var hint = document.getElementById('dd-structure-hint');
      var tbody = structWrap.querySelector('tbody');
      if (hint && tbody) {
        if (tbody.scrollHeight > tbody.clientHeight) {
          hint.classList.remove('hidden');
          tbody.addEventListener('scroll', function(){
            if (tbody.scrollTop + tbody.clientHeight >= tbody.scrollHeight - 4) {
              hint.classList.add('hidden');
            } else {
              hint.classList.remove('hidden');
            }
          });
        }
      }
    } else if (structEmpty) {
      structEmpty.textContent = act.structure_detail || '—';
    }
    modal.classList.add('open'); modal.setAttribute('aria-hidden','false');
  }
  if(btn) btn.addEventListener('click', open);
  if(close) close.addEventListener('click', function(){ modal.classList.remove('open'); modal.setAttribute('aria-hidden','true'); });
  if(modal) modal.addEventListener('click', function(e){ if(e.target===modal){ modal.classList.remove('open'); modal.setAttribute('aria-hidden','true'); } });
})();

// ─── INSCRIPTION MODAL ───
(function(){
  var btn    = document.getElementById('v2-reg-btn');
  var modal  = document.getElementById('v2-ins-modal');
  var close  = document.getElementById('v2-ins-close');
  var form   = document.getElementById('v2-ins-form');
  var inAnon = document.getElementById('v2-anon');
  var inOpt  = document.getElementById('v2-opt');
  var inLate = document.getElementById('v2-late');

  function syncHidden(){
    form.querySelector('[name=anonyme]').value = inAnon&&inAnon.checked ? '1':'0';
    form.querySelector('[name=latereg]').value  = inLate&&inLate.checked ? '1':'0';
    form.querySelector('[name=status]').value   = inOpt&&inOpt.checked  ? 'Option':'Inscrit';
  }
  [inAnon,inOpt,inLate].forEach(function(el){ if(el) el.addEventListener('change', syncHidden); });

  function openModal(){
    var act = window.SERVER_ACTIVITY || {};
    var uid = act.id || (new URLSearchParams(window.location.search).get('uid'))||'';
    form.querySelector('[name=uid]').value = uid;
    var p = window.SERVER_PARTICIPATION;
    if(p){
      if(inAnon) inAnon.checked = (p.anonyme=='1'||p.anonyme===true);
      if(inOpt)  inOpt.checked  = (p.status==='Option');
      if(inLate) inLate.checked = (p.latereg=='1'||p.latereg===true);
    }
    syncHidden();
    modal.classList.add('open'); modal.setAttribute('aria-hidden','false');
  }
  if(btn) btn.addEventListener('click', openModal);
  if(close) close.addEventListener('click', function(){ modal.classList.remove('open'); modal.setAttribute('aria-hidden','true'); });
  if(modal) modal.addEventListener('click', function(e){ if(e.target===modal){ modal.classList.remove('open'); modal.setAttribute('aria-hidden','true'); } });

  // Unregister
  var unBtn = document.getElementById('v2-ins-unregister');
  if(unBtn) unBtn.addEventListener('click', function(){
    form.querySelector('[name=status]').value='None';
    form.querySelector('[name=anonyme]').value='0';
    form.querySelector('[name=latereg]').value='0';
    submitAjax();
  });

  form && form.addEventListener('submit', function(e){ e.preventDefault(); syncHidden(); submitAjax(); });

  function submitAjax(){
    var data = new URLSearchParams(new FormData(form));
    fetch('/panel/inscription.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:data.toString()})
    .then(function(r){return r.json();})
    .then(function(d){
      modal.classList.remove('open'); modal.setAttribute('aria-hidden','true');
      // Refresh page to update status
      setTimeout(function(){ window.location.reload(); }, 400);
    })
    .catch(function(){ alert('Erreur réseau, veuillez réessayer.'); });
  }
})();
</script>
</body>
</html>
