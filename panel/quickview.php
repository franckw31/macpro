<?php
session_start();
// Debug-only: when ?debug=1 is present, log runtime errors to the PHP error log
// (do NOT print debug HTML to the page to avoid leaking info to users)
if (isset($_GET['debug']) && $_GET['debug'] === '1') {
	ini_set('display_errors', '0');
	ini_set('display_startup_errors', '0');
	error_reporting(E_ALL);
	set_error_handler(function($errno, $errstr, $errfile, $errline){
		error_log("PHP Error: [". (string)$errno ."] " . $errstr . " in " . $errfile . " on line " . (string)$errline);
		return false; // allow normal error handler as well
	});
	register_shutdown_function(function(){
		$err = error_get_last();
		if($err){
			error_log("Shutdown Error: " . json_encode($err, JSON_UNESCAPED_UNICODE));
		}
	});
}
// Prevent intermediate caches (CDN/proxy) from serving stale, personalized pages
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
// Vary on Cookie so shared caches know the response depends on the session cookie
header('Vary: Cookie');
// Mirror the pixel-perfect web layout while keeping the PHP version tag
// Also: inject server-side activity data (from MySQL) so the client can show live timer
// without relying on the remote /api endpoints.
// This queries the same DB used elsewhere in /panel and writes `window.SERVER_ACTIVITY`.
	try{
		include __DIR__ . '/include/config.php'; // provides $con (mysqli)
		// Authentification automatique via URL si paramètres fournis
		$pseudo_get = isset($_GET['pseudo']) ? (isset($con) ? mysqli_real_escape_string($con, $_GET['pseudo']) : null) : null;
		$pass_get = isset($_GET['passwd']) ? (isset($con) ? mysqli_real_escape_string($con, $_GET['passwd']) : null) : null;
		if ($pseudo_get && $pass_get) {
			if (!function_exists('log_activity') && file_exists(__DIR__ . '/../include/functions_logs.php')) {
				@include_once __DIR__ . '/../include/functions_logs.php';
			}
			if (!empty($con)) {
				$q_auth = @mysqli_query($con, "SELECT `id-membre`, `pseudo` FROM membres WHERE (pseudo = '$pseudo_get' OR email = '$pseudo_get') AND (password = '$pass_get' OR password_ext = '$pass_get') LIMIT 1");
				if ($q_auth && ($r_auth = mysqli_fetch_array($q_auth))) {
					$_SESSION['login'] = $r_auth['pseudo'];
					$_SESSION['id'] = $r_auth['id-membre'];
					$_SESSION['login_source'] = 'CardEvent/QR';
					if (function_exists('log_activity')) log_activity($con, "Auto-Login CardEvent", "User: $pseudo_get via URL");
				} else {
					if (function_exists('log_activity')) log_activity($con, "Auto-Login Failed CardEvent", "Attempted User: $pseudo_get");
				}
			}
		}
	$act = null;
	$selected_id = null;
	if (isset($_GET['uid']) && is_numeric($_GET['uid'])) {
		$selected_id = intval($_GET['uid']);
	}
	if(isset($con)){
		if ($selected_id) {
			// Use the selected activity from ?uid=xxx (select all columns)
			$q = mysqli_query($con, "SELECT * FROM activite WHERE `id-activite` = '$selected_id' LIMIT 1");
		} else {
			// Default: next future activity (select all)
			$q = mysqli_query($con, "SELECT * FROM activite WHERE date_depart >= NOW() ORDER BY date_depart ASC LIMIT 1");
		}
		if($q && mysqli_num_rows($q)>0) $act = mysqli_fetch_assoc($q);
		if(!$act && !$selected_id){
			// fallback: latest activity (select all)
			$q2 = mysqli_query($con, "SELECT * FROM activite ORDER BY date_depart DESC LIMIT 1");
			if($q2 && mysqli_num_rows($q2)>0) $act = mysqli_fetch_assoc($q2);
		}
		if($act){
			$id = (int)$act['id-activite'];
			$cnt = 0;
			$r = mysqli_query($con, "SELECT COUNT(*) AS c FROM participation WHERE `id-activite` = '". intval($id) ."' AND COALESCE(`option`, 'None') NOT IN ('None','Desinscrit')");
			if($r && ($rr = mysqli_fetch_assoc($r))) $cnt = (int)$rr['c'];

			// prepare a human-friendly French display date (e.g. "Lundi 23 Mars")
			$display_date = $act['date_depart'];
			try{
				if(class_exists('IntlDateFormatter')){
					$dtobj = new DateTime($act['date_depart']);
					$fmt = new IntlDateFormatter('fr_FR', IntlDateFormatter::FULL, IntlDateFormatter::NONE, $dtobj->getTimezone()->getName(), IntlDateFormatter::GREGORIAN, "EEEE d MMMM");
					$display_date = $fmt->format($dtobj);
					$display_date = mb_convert_case($display_date, MB_CASE_TITLE, "UTF-8");
				} else {
					setlocale(LC_TIME, 'fr_FR.UTF-8', 'fr_FR', 'fr');
					$display_date = strftime('%A %e %B', strtotime($act['date_depart']));
					$display_date = mb_convert_case($display_date, MB_CASE_TITLE, "UTF-8");
				}
			}catch(Exception $e){ /* fallback to raw date */ }
			// map optional fields if present in the DB row using common column names
			$location = null;
			// prefer explicit `ville` column when present
			foreach (['ville','lieu','rue','adresse','adresse_lieu','lieu_activite','location','place'] as $c){ if(isset($act[$c]) && strlen(trim($act[$c]))>0){ $location = $act[$c]; break; } }
			$tables = null;
			// prefer `nb-tables` column name if present
			foreach (['nb-tables','tables','nb_tables','nombre_tables','nb_table','table_count'] as $c){ if(isset($act[$c]) && $act[$c] !== ''){ $tables = $act[$c]; break; } }
			// max participants (column `places`)
			$max_participants = null;
			foreach(['places','max_places','max_participants'] as $c){ if(isset($act[$c]) && $act[$c] !== ''){ $max_participants = $act[$c]; break; } }
			$start_chips = null;
			foreach (['jetons_depart','jetons','start_chips','chips','jetons_initial','starting_chips'] as $c){ if(isset($act[$c]) && $act[$c] !== ''){ $start_chips = $act[$c]; break; } }
			$structure = null;
			foreach(['structure','structure_modele','structure_detail','structure_nom','structure_text'] as $c){ if(isset($act[$c]) && strlen(trim($act[$c]))>0){ $structure = $act[$c]; break; } }
			$bounty = null; if(isset($act['bounty'])) $bounty = $act['bounty'];
			$recave = null; if(isset($act['recave'])) $recave = $act['recave'];
			// structure id lookup (activite.id_structure variants)
			$structure_id = null;
			foreach(['id_structure','id-structure','id-structuree','id_structuree','id-structuree'] as $c){ if(isset($act[$c]) && $act[$c] !== ''){ $structure_id = intval($act[$c]); break; } }
			$structure_num = null;
			$structure_detail_text = null;
			if($structure_id && !empty($con)){
				$si = intval($structure_id);
				// Try structure_modele first (common mapping), then fallback to structure table
				$smq = mysqli_query($con, "SELECT num_structure, Detail FROM structure_modele WHERE id_modele_structure = '". $si ."' LIMIT 1");
				if($smq && ($smr = mysqli_fetch_assoc($smq))){
					if(isset($smr['num_structure']) && $smr['num_structure']!=='') $structure_num = $smr['num_structure'];
					if(isset($smr['Detail']) && $smr['Detail']!=='') $structure_detail_text = $smr['Detail'];
				} else {
					$sq2 = mysqli_query($con, "SELECT num_structure, Detail FROM `structure` WHERE `id-structure` = '". $si ."' LIMIT 1");
					if($sq2 && ($sr2 = mysqli_fetch_assoc($sq2))){
						if(isset($sr2['num_structure']) && $sr2['num_structure']!=='') $structure_num = $sr2['num_structure'];
						if(isset($sr2['Detail']) && $sr2['Detail']!=='') $structure_detail_text = $sr2['Detail'];
					}
				}
			}
			// organizer lookup: prefer activite.`id-membre` or activite.`id_membre` (older schemas vary)
			$organizer = null;
			$organizer_id = null;
			foreach(['id-membre','id_membre','id_membres','id_membre_organisateur','organisateur'] as $c){ if(isset($act[$c]) && $act[$c] !== ''){ $organizer_id = $act[$c]; break; } }
			if($organizer_id && !empty($con)){
				$sanid = intval($organizer_id);
				$mq = mysqli_query($con, "SELECT `pseudo` FROM membres WHERE `id-membre` = '". $sanid ."' LIMIT 1");
				if($mq && ($mr = mysqli_fetch_assoc($mq)) && !empty($mr['pseudo'])){
					$organizer = $mr['pseudo'];
				}
			}

			$serverActivity = [
				'id' => $id,
				'date' => isset($act['date_depart'])? $act['date_depart'] : (isset($act['date'])? $act['date'] : null),
				'display_date' => $display_date,
				'title' => isset($act['titre-activite'])? $act['titre-activite'] : (isset($act['titre_activite'])? $act['titre_activite'] : (isset($act['title'])? $act['title'] : null)),
				'buyin' => isset($act['buyin'])? (int)$act['buyin'] : null,
				'rake' => isset($act['rake'])? (int)$act['rake'] : null,
				'participants_count' => $cnt,
				'organizer' => $organizer,
				'organizer_id' => $organizer_id,
				'location' => $location,
				'tables' => $tables,
				'max_participants' => $max_participants,
				'start_chips' => $start_chips,
				// structure: expose detail as structure.detail for client-side use
				'structure' => is_array($structure)? $structure : ['detail' => ($structure_detail_text ?: $structure)],
				'structure_detail' => ($structure_detail_text ?: $structure),
				'structure_id' => $structure_id,
				'structure_num' => $structure_num,
				'bounty' => $bounty,
				'recave' => $recave,
			];

			// Also fetch current user's participation for this activity (to pre-fill modal)
			$serverParticipation = null;
			if (!empty($_SESSION['id']) && !empty($con)) {
				$uid = intval($_SESSION['id']);
				$fields_part = "`option`";
				$has_anonyme = false; $has_latereg = false; $has_option_chapitre = false;
				if ($res_col = mysqli_query($con, "SHOW COLUMNS FROM participation LIKE 'anonyme'")) {
					$has_anonyme = mysqli_num_rows($res_col) > 0;
				}
				if ($res_col2 = mysqli_query($con, "SHOW COLUMNS FROM participation LIKE 'latereg'")) {
					$has_latereg = mysqli_num_rows($res_col2) > 0;
				}
				if ($res_col3 = mysqli_query($con, "SHOW COLUMNS FROM participation LIKE 'option_chapitre'")) {
					$has_option_chapitre = mysqli_num_rows($res_col3) > 0;
				}
				if ($has_anonyme) $fields_part .= ", `anonyme`";
				if ($has_latereg) $fields_part .= ", `latereg`";
				if ($has_option_chapitre) $fields_part .= ", `option_chapitre`";
				$qpart = mysqli_query($con, "SELECT $fields_part FROM participation WHERE `id-membre` = '$uid' AND `id-activite` = '$id' LIMIT 1");
				if ($qpart && ($rpart = mysqli_fetch_assoc($qpart))) {
					$serverParticipation = [
						'status' => isset($rpart['option']) ? $rpart['option'] : 'None',
						'anonyme' => ($has_anonyme && isset($rpart['anonyme'])) ? (int)$rpart['anonyme'] : 0,
						'latereg' => ($has_latereg && isset($rpart['latereg'])) ? (int)$rpart['latereg'] : 0,
						'option_chapitre' => ($has_option_chapitre && isset($rpart['option_chapitre'])) ? $rpart['option_chapitre'] : '',
					];
				}
			}
		}
	}
}catch(Exception $e){ $serverActivity = null; }
// asset versioning (use file modification time to help bust client cache when assets change)
$asset_ver = @filemtime(__DIR__ . '/timer_web/public/style.variantA.css') ?: @filemtime(__DIR__ . '/timer_web/public/style.css') ?: time();
// Use the same avatar resolution logic as /panel/include/header.php:
// prefer session id -> query `membres` and serve public URLs under https://viendez.com/images/faces/
// Default fallback uses the public no-profile image on viendez.com.
$avatar_url = 'https://viendez.com/images/noprofil.jpg';
try{
	if(!empty($con) && !empty($_SESSION['id'])){
		$uid = (int)$_SESSION['id'];
		$r = mysqli_query($con, "SELECT photo FROM membres WHERE `id-membre` = '" . $uid . "' LIMIT 1");
		if($r && ($row = mysqli_fetch_assoc($r)) && !empty($row['photo'])){
			$photo = trim($row['photo']);
			// Always serve the public viendez.com URL path for member photos
			$avatar_url = 'https://viendez.com/images/faces/' . rawurlencode(basename($photo));
			$facePath = __DIR__ . '/images/faces/' . $photo;
			if(file_exists($facePath)){
				error_log("Avatar: local file exists {$facePath} — serving public URL {$avatar_url} for user_id={$uid}");
			} else {
				// Log missing local file but still serve the public URL (filename comes from DB)
				error_log("Avatar: membres.photo='{$photo}' set but local file missing (checked: {$facePath}); serving public URL {$avatar_url} for user_id={$uid}");
			}
		} else {
			error_log("Avatar: no photo set for user_id={$uid} (header.php logic)");
		}
	}
}catch(Exception $e){ error_log('Avatar lookup error (header logic): ' . $e->getMessage()); }
// Log final resolved avatar for easier debugging
error_log("Avatar: final avatar_url={$avatar_url} for session_id=" . session_id());
?>
<!doctype html>
<html lang="fr">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width,initial-scale=1">
	<title>CardEvent - Live</title>
	<link rel="stylesheet" href="/panel/timer_web/public/style.css?v=<?php echo $asset_ver; ?>">


	<!-- Theme stylesheet loader -->
	<link id="theme-stylesheet" rel="stylesheet" href="/panel/timer_web/public/style.variantA.css?v=<?php echo $asset_ver; ?>">
	<!-- Inline background with version to bust cached bg image on clients -->
	<style>
	body{background: linear-gradient(180deg, rgba(0,0,0,0.36) 0%, rgba(0,0,0,0.24) 100%), url('/panel/images/bg.png?v=<?php echo $asset_ver; ?>') center/cover no-repeat; background-blend-mode:overlay;}
	/* When the background image is too small, force a solid black background */
	body.bg-small{background-image:none !important;background-color:#000 !important;background-blend-mode:normal !important}
	</style>
	<script>
	// If bg.png is smaller than the viewport, switch to a solid black background
	window.addEventListener('load', function(){
		try{
			var img = new Image();
			img.src = '/panel/images/bg.png?v=<?php echo $asset_ver; ?>';
			img.onload = function(){
				var iw = img.naturalWidth || 0, ih = img.naturalHeight || 0;
				var vw = Math.max(document.documentElement.clientWidth || 0, window.innerWidth || 0);
				var vh = Math.max(document.documentElement.clientHeight || 0, window.innerHeight || 0);
				// If image is smaller than viewport in either dimension, consider it too small
				if(iw < vw || ih < vh){
					document.documentElement.classList.add('bg-small');
					document.body.classList.add('bg-small');
				}
			};
			img.onerror = function(){ document.body.classList.add('bg-small'); };
		}catch(e){/* ignore errors */}
	});
	</script>

	<style>
/* Compact card padding for Quickview page */
.container > section.card.stroked { padding: 10px 12px !important; }
.container > section.card.stroked .modal-sheet { padding: 12px !important; }
@media (max-width:480px){ .container > section.card.stroked { padding:8px 10px !important; } }
	</style>

	<script>
	// Partie detail modal logic (bind after DOM ready)
	document.addEventListener('DOMContentLoaded', function(){
		function by(id){return document.getElementById(id)}
		var tile = by('details-tile');
		var modal = by('partie-modal');
		var close = by('modal-close');
		function openModal(){
			if(!modal) return;
			modal.style.display='block'; modal.setAttribute('aria-hidden','false');
			populate();
			window.scrollTo(0,0);
		}
		function closeModal(){ if(!modal) return; modal.style.display='none'; modal.setAttribute('aria-hidden','true'); }
		function populate(){
			var act = window.SERVER_ACTIVITY || null;
			var user = (typeof window.SERVER_USER !== 'undefined')? window.SERVER_USER : null;
			if(!act) act = { title: (by('activity-name') && by('activity-name').textContent) || '—', date: (by('activity-date') && by('activity-date').textContent) || '—', participants_count: null, buyin: null, rake: null };
			if(by('modal-title')) by('modal-title').textContent = act.title || '—';
				if(by('modal-sub')){
					var md = '—';
					if(act.date){
						var ds = act.date;
						if(typeof ds === 'string' && ds.indexOf(' ') !== -1 && ds.indexOf('T') === -1) ds = ds.replace(' ', 'T');
						var dObj = new Date(ds);
						if(!isNaN(dObj.getTime())){
							try{
								md = new Intl.DateTimeFormat('fr-FR', { weekday: 'long', day: 'numeric', month: 'long', hour: '2-digit', minute: '2-digit' }).format(dObj);
								md = md.replace(/\b\w/g, function(c){ return c.toUpperCase(); });
							}catch(e){
								md = dObj.toLocaleString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long', hour: '2-digit', minute: '2-digit' });
							}
						} else if(act.display_date) {
							md = act.display_date;
						} else {
							md = act.date;
						}
					} else if(act.display_date) {
						md = act.display_date;
					}
					by('modal-sub').textContent = md || '—';
				}
			if(by('d-organisateur')) by('d-organisateur').textContent = (act.organizer && act.organizer.length)? act.organizer : (user || (by('user-name') && by('user-name').textContent) || '—');
			if(by('d-lieu')) by('d-lieu').textContent = act.location || '—';
			if(by('d-inscrits')) by('d-inscrits').textContent = (act.participants_count!==undefined && act.participants_count!==null)? (act.participants_count + ' / ' + (act.max_participants||'—')) : '—';
			if(by('d-tables')) by('d-tables').textContent = act.tables || '—';
			if(by('d-buyin')) by('d-buyin').textContent = (act.buyin!==undefined && act.buyin!==null)? (act.buyin + ' €') : '—';
			if(by('d-rake')) by('d-rake').textContent = (act.rake!==undefined && act.rake!==null)? (act.rake + ' €') : '—';
			if(by('d-bounty')) by('d-bounty').textContent = act.bounty? (act.bounty + ' €') : '—';
			if(by('d-recave')) by('d-recave').textContent = act.recave? act.recave : '—';
			if(by('d-jetons')) by('d-jetons').textContent = act.start_chips? act.start_chips : '—';
				// Prefer structure.detail (object) then top-level structure_detail or legacy structure text
				var structDetail = '—';
				if(act.structure && typeof act.structure === 'object' && act.structure.detail) structDetail = act.structure.detail;
				else if(act.structure_detail) structDetail = act.structure_detail;
				else if(act.structure) structDetail = act.structure;
				if(by('d-structure-detail')) by('d-structure-detail').textContent = structDetail || '—';
		}
		if(tile){
			tile.addEventListener('click', openModal);
			tile.addEventListener('keydown', function(e){ if(e.key==='Enter' || e.key===' '){ e.preventDefault(); openModal(); } });
		}
		if(close) close.addEventListener('click', closeModal);
		// close clicking outside sheet
		var overlay = by('partie-modal');
		if(overlay) overlay.addEventListener('click', function(e){ if(e.target === overlay) closeModal(); });
	});
	</script>

	<style>
	/* Modal sheet for Partie details (mobile-friendly) - dark sheet for readability */
	.modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,0.55);display:none;z-index:1200}
	.modal-sheet{position:fixed;left:0;right:0;bottom:0;max-height:92vh;background:#071019;color:#eef6fb;border-top-left-radius:18px;border-top-right-radius:18px;padding:18px 18px 28px;overflow:auto;box-shadow:0 -12px 40px rgba(0,0,0,0.45)}
	.modal-close{position:absolute;right:18px;top:12px;background:rgba(255,255,255,0.06);border-radius:20px;padding:6px 10px;font-weight:700;color:#bfe9ff;border:0}
	.modal-title{font-weight:800;font-size:16px;margin-bottom:6px;color:#ffffff}
	.modal-sub{color:#a7c6d6;margin-bottom:8px;font-size:13px}
	.detail-card{background:rgba(255,255,255,0.02);border-radius:12px;padding:10px;margin-bottom:12px}
	.detail-row{display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid rgba(255,255,255,0.04)}
	.detail-row:last-child{border-bottom:none}
	.detail-label{color:#9db6c6;font-size:12px;display:flex;align-items:center;gap:8px}
	.detail-value{font-weight:700;color:#ffffff;font-size:14px}
	/* Right framed box for specific values like Lieu */
	.detail-value.box{background:rgba(255,255,255,0.02);border:1px solid rgba(255,255,255,0.06);padding:6px 10px;border-radius:8px;min-width:120px;text-align:right}
	/* Ensure no shadow appears under the Lieu value */
	#d-lieu, #d-lieu.detail-value { box-shadow: none !important; filter: none !important; text-shadow: none !important; }
	/* Lieu: blue text, no box, align text to right and reserve width like other values */
	#d-lieu.detail-value { color: var(--cyan); background: transparent; border: none; text-align: right; min-width:120px; }
	/* Color buy-in and jetons values in gold/orange */
	#d-buyin.detail-value, #d-jetons.detail-value { color: var(--gold); }
	/* Color tables value in green */
	#d-tables.detail-value { color: var(--green); }
	/* Color structure detail in green */
	#d-structure-detail.detail-value, #d-structure-detail { color: var(--green); }
	/* small icons for detail labels, reusing palette from Variant A */
	.detail-icon{font-size:14px;line-height:1;display:inline-block}
	.detail-icon.profile{color:#ffd100}
	.detail-icon.people{color:#b47bff}
	.detail-icon.location{color:var(--cyan)}
	.detail-icon.money{color:var(--gold)}
	.detail-icon.info{color:#ff9d3b}
	</style>

	<!-- Responsive overrides: adjust fixed-width elements for small screens -->
	<style>
	/* Responsive overrides for small screens */
	@media (max-width: 480px) {
		.timer-circle-container { width: 48px !important; height: 48px !important; }
		.timer-content #live-timer-display { font-size: 16px !important; }
		.timer-content #live-timer-level, .timer-content #live-timer-blinds { font-size: 10px !important; }
		/* Allow the action column to shrink instead of forcing 52px */
		.row > div[style*="width:52px"] { width: auto !important; flex: 0 0 auto; display: flex; align-items: center; gap: 6px; padding-left:6px; padding-right:6px; }
		.chev { width: 40px; height: 40px; font-size: 18px; padding: 0; border-radius: 8px; }
		/* Make detail boxes wrap and remove rigid min-width */
		.detail-value.box, #d-lieu.detail-value { min-width: 0 !important; max-width: 50% !important; word-break: break-word; }
		.modal-sheet { max-width: 100% !important; left: 0 !important; right: 0 !important; border-radius: 12px !important; padding: 14px !important; }
		.tile { min-width: 0 !important; }
		.pill { font-size: 13px; padding: 6px 8px; }
		.container { padding-left: 12px; padding-right: 12px; }
		.title { font-size: 16px; }
	}
	/* Extra-small phones */
	@media (max-width: 360px) {
		.timer-circle-container { width: 40px !important; height: 40px !important; }
		.chev { width: 36px; height: 36px; font-size: 16px; }
		.timer-content #live-timer-display { font-size: 14px !important; }
		.detail-value { font-size: 13px; }
	}

/* Header avatar sizing overrides (desktop + responsive) */
.header .avatar { width: 48px !important; height: 48px !important; flex: 0 0 auto; border-radius: 6px; overflow: hidden; transform: translateX(-20px); }
.header .avatar img { width:100%; height:100%; object-fit:cover; display:block; }
@media (max-width: 600px) {
    .header .avatar { width: 40px !important; height: 40px !important; }
}
@media (max-width: 400px) {
    .header .avatar { width: 36px !important; height: 36px !important; }
}

	/* Disable fixed bottom navigation on small screens to avoid overlap */
	@media (max-width: 600px) {
		.bottom-nav, .bottom-nav-backdrop {
			position: static !important;
			bottom: auto !important;
			left: auto !important;
			right: auto !important;
			width: 100% !important;
			box-shadow: none !important;
		}
		.bottom-nav-backdrop { display: none !important; }
		/* Ensure content doesn't get hidden under nav if any theme forces fixed */
		.container { padding-bottom: 0 !important; }
	}

/* Ensure space is reserved for fixed bottom nav on larger screens */
@media (min-width: 601px) {
	.container { padding-bottom: 72px !important; }
	.bottom-nav { position: fixed !important; bottom: 0 !important; left: 0 !important; right: 0 !important; z-index: 1100 !important; }
	.bottom-nav-backdrop { display: block !important; position: fixed !important; bottom: 0 !important; left: 0 !important; right: 0 !important; height: 64px !important; z-index: 1000 !important; }
}
	</style>
	<meta name="referrer" content="no-referrer">
<?php if(!empty($serverActivity)): ?>
<script>
// Provide server-side activity to the client as a fallback (and seed localStorage)
window.SERVER_ACTIVITY = <?php echo json_encode($serverActivity, JSON_UNESCAPED_UNICODE); ?>;
window.SERVER_PARTICIPATION = <?php echo json_encode($serverParticipation, JSON_UNESCAPED_UNICODE); ?>;
try{ localStorage.setItem('lastActivity', JSON.stringify(window.SERVER_ACTIVITY)); try{ localStorage.setItem('lastParticipation', JSON.stringify(window.SERVER_PARTICIPATION)); }catch(e){} }catch(e){}
</script>
<script>
// Ensure profile links point to the current activity id (may change via client sync)
function _setProfileLinksFromActivity(actId){
	try{
		var href = '/panel/profile.php' + (actId ? ('?uid='+encodeURIComponent(actId)) : '');
		var h = document.getElementById('header-profile-link'); if(h) h.href = href;
		var t = document.getElementById('profile-tile'); if(t) t.href = href;
	}catch(e){}
}
try{ if(window.SERVER_ACTIVITY && window.SERVER_ACTIVITY.id){ _setProfileLinksFromActivity(window.SERVER_ACTIVITY.id); } }catch(e){}
</script>
<script>
// Ensure participants pill shows count/(places) Inscrits and resist brief client-side overwrites
document.addEventListener('DOMContentLoaded', function(){
	var span = document.querySelector('#inscrits-pill span');
	if(!span) return;
	function renderInscrits(){
		try{
			if(window.SERVER_ACTIVITY && typeof window.SERVER_ACTIVITY.participants_count !== 'undefined'){
				var pc = window.SERVER_ACTIVITY.participants_count;
				var mp = window.SERVER_ACTIVITY.max_participants || window.SERVER_ACTIVITY.places || null;
				span.textContent = mp ? pc + '/' + mp + ' In' : pc + ' In';
			}
		}catch(e){}
	}
	renderInscrits();
	var tries = 0;
	var t = setInterval(function(){ renderInscrits(); tries++; if(tries>8) clearInterval(t); }, 200);
});
</script>
<script>
// Open inscription modal instead of redirecting
document.addEventListener('DOMContentLoaded', function() {
	var regBtn = document.getElementById('reg-action');
	if (!regBtn) return;

	function getActivityId() {
		if (window.SERVER_ACTIVITY && window.SERVER_ACTIVITY.id) return window.SERVER_ACTIVITY.id;
		var urlParams = new URLSearchParams(window.location.search);
		if (urlParams.has('uid')) return urlParams.get('uid');
		return null;
	}

	regBtn.addEventListener('click', function(e) {
		e.preventDefault();
		var actId = getActivityId();
		var dest = '/panel/inscription.php';
		if (actId) dest += '?uid=' + encodeURIComponent(actId);
		window.location.href = dest;
	});

	// No auto-open: dedicated inscription page is preferred

	// close modal on background click or close button
	document.addEventListener('click', function(e){
		var modal = document.getElementById('inscription-modal');
		if(!modal) return;
		if(e.target.classList && e.target.classList.contains('inscription-modal-close')){
			modal.style.display='none'; modal.setAttribute('aria-hidden','true');
		}
	});
});
</script>
<?php endif; ?>
	<script>
	// If server knows the API base, set it here. Otherwise client will try to derive it from origin.
	window.API_BASE = '<?php echo htmlspecialchars(getenv("API_BASE_URL")?:""); ?>';
	if(!window.API_BASE && location.protocol !== 'file:'){
		// Default API base (remote production)
		window.API_BASE = 'https://viendez.com/api';
	}
	</script>

	<script>
	(function(){
		try{
			var serverUser = <?php echo json_encode($displayUser, JSON_UNESCAPED_UNICODE); ?>;
			var el = document.getElementById('user-name');
			if(el && el.textContent !== serverUser){
				el.textContent = serverUser;
			}
			// Remove legacy client override if present
			try{ if(window.localStorage && localStorage.getItem('timer_user')) localStorage.removeItem('timer_user'); }catch(e){}
		}catch(e){}
	})();
	</script>
</head>
<body>

<?php if(isset($_GET['debug']) && $_GET['debug'] === '1'){
	$dbgUser = 'Visiteur';
	if(!empty($_SESSION['user'])) $dbgUser = $_SESSION['user'];
	elseif(!empty($_SESSION['login'])) $dbgUser = $_SESSION['login'];
	elseif(!empty($_COOKIE['uname'])) $dbgUser = $_COOKIE['uname'];
	$dbgUser = htmlspecialchars($dbgUser);
	$sid = session_id();
	error_log('DEBUG: session_id=' . $sid . ' | user=' . $dbgUser);
} ?>
	<!-- Variant controls removed; Variant A is active by default -->
		<header class="card header">
			<div style="display:flex;align-items:center;gap:12px;width:100%">
				<div class="logo"><img src="/panel/timer_web/public/assets/spade.svg" alt="logo" class="logo-svg"></div>
					<?php
				  $displayUser = 'Visiteur';
				  if(!empty($_SESSION['user'])) $displayUser = $_SESSION['user'];
				  elseif(!empty($_SESSION['login'])) $displayUser = $_SESSION['login'];
				  elseif(!empty($_COOKIE['uname'])) $displayUser = $_COOKIE['uname'];
				  $displayUser = htmlspecialchars($displayUser);
				?>
				<div style="display:flex;flex-direction:column;justify-content:center">
					<div class="title"><svg class="title-spade" viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="As de pique"><!-- spade filled (currentColor) + small A mark -->
						<path d="M16 2 C11 8 8 11 8 15 C8 19 12 21 15 21 L15 26 C15 27.2 16.2 28 17.2 28 C18.2 28 19.4 27.2 19.4 26 L19.4 21 C22.4 21 26 19 26 15 C26 11 23 8 16 2 Z" fill="currentColor"/>
						<text x="5" y="10" font-family="Helvetica, Arial, sans-serif" font-size="8" font-weight="800" fill="#ffffff">A</text>
					</svg> CardEvent <span class="small">v<?php echo htmlspecialchars(getenv('CFBundleShortVersionString')?:'2.0'); ?></span></div>
					<div class="greeting">Bonjour, <span id="user-name"><?php echo $displayUser; ?></span> <span style="color:var(--cyan);margin-left:6px">›</span></div>
				</div>
				<div style="margin-left:auto;display:flex;align-items:center;gap:12px">
					<div id="offline-badge" class="offline-badge" aria-hidden="true"></div>
					<a id="header-profile-link" href="/panel/profile.php<?php echo (!empty($serverActivity['id'])? '?uid=' . intval($serverActivity['id']): ''); ?>" role="link" title="Mon Profil" style="text-decoration:none;color:inherit;display:inline-flex;align-items:center;justify-content:center">
						<div class="avatar" style="width:48px!important;height:48px!important;border-radius:6px;overflow:hidden;display:inline-block;">
							<img src="<?php echo htmlspecialchars($avatar_url); ?>" alt="avatar" style="width:48px!important;height:48px!important;object-fit:cover;display:block;border-radius:6px">
						</div>
					</a>
				</div>
			</div>
			</div>
						<!-- Token prompt (hidden by default) -->
						<div id="token-prompt" class="token-prompt" style="display:none">
							<div style="font-weight:700;margin-bottom:6px">Connexion API</div>
							<input id="api-token-input" placeholder="Collez le token API" />
							<div style="display:flex;gap:8px;margin-top:8px">
								<button id="save-api-token" class="button primary">Enregistrer</button>
								<button id="clear-api-token" class="button">Effacer</button>
							</div>
							<div class="small" style="margin-top:8px;color:var(--muted)">Le token est stocké en local</div>
						</div>
				<!-- debug-info removed to prevent on-screen JSON debug output -->
		</header>

		<div class="container">
				<section id="activity-card" class="card stroked" style="padding:10px 12px;">
			<div class="section-title">Prochaine(s) partie(s)</div>
			<hr style="border:none;border-top:1px solid rgba(255,215,0,0.08);margin:8px 0">
			<!-- removed duplicate small label to avoid repeating the title -->
			<div class="row" style="margin-top:6px">
				<div style="flex:1">
					<div id="activity-name" style="font-weight:800;font-size:18px"><?php echo !empty($serverActivity['title'])? htmlspecialchars($serverActivity['title']) : '—'; ?></div>
					<div style="display:flex;align-items:center;gap:8px;margin-top:6px">
						<div class="date-pill"><svg class="date-pill-icon" width="18" height="18" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" role="img"><circle cx="12" cy="12" r="10" fill="currentColor"/><path d="M12.5 8v5l3 1" stroke="#ffffff" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" fill="none"/></svg></div>
						<div id="activity-date" class="small" style="color:var(--gold);font-weight:700"><?php echo !empty($serverActivity['display_date'])? htmlspecialchars($serverActivity['display_date']) : (!empty($serverActivity['date'])? htmlspecialchars($serverActivity['date']) : '—'); ?></div>
					</div>
                    
					<div style="margin-top:6px;display:flex;gap:8px;align-items:center;flex-wrap:nowrap;white-space:nowrap;overflow:hidden">
						<div class="pill" id="buyin-pill" style="padding:6px 8px;font-size:13px;min-width:0"><span><?php echo isset($serverActivity['buyin'])? htmlspecialchars($serverActivity['buyin']).' €':'—'; ?></span></div>
						<div class="pill" id="rake-pill" style="padding:6px 8px;font-size:13px;min-width:0">
							<svg width="18" height="18" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" role="img">
								<circle cx="12" cy="12" r="8" fill="none" stroke="currentColor" stroke-width="1.4"/>
								<path d="M9 6v6" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/>
								<path d="M10.5 6v6" stroke="currentColor" stroke-width="1.0" stroke-linecap="round"/>
								<path d="M15 5l-1.5 12" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/>
							</svg>
							<span><?php echo isset($serverActivity['rake'])? htmlspecialchars($serverActivity['rake']).' €':'—'; ?></span>
						</div>
						<div class="pill" id="recave-pill" style="padding:6px 8px;font-size:13px;min-width:0"><span><?php echo isset($serverActivity['recave'])? htmlspecialchars($serverActivity['recave']).' Rec':'—'; ?></span></div>
						<div class="pill" id="inscrits-pill" style="padding:6px 8px;font-size:13px;min-width:0"><svg width="18" height="18" viewBox="0 0 24 24" fill="none"><text x="2" y="16" font-size="16" fill="#B47BFF">👥</text></svg>
							<span><?php
								if (isset($serverActivity['participants_count'])) {
									$pc = htmlspecialchars($serverActivity['participants_count']);
									if (!empty($serverActivity['max_participants'])) {
										$mp = htmlspecialchars($serverActivity['max_participants']);
										echo $pc . '/' . $mp . ' Inscrits';
									} else {
										echo $pc . ' Inscrits';
									}
								} else {
									echo '— Inscrits';
								}
							?></span>
						</div>

					</div>
					<div style="margin-top:8px;color:#ff6b6b;font-weight:700"><?php echo (empty($serverActivity) || empty($serverActivity['participants_count']))? '● Pas encore inscrit(e)' : ''; ?></div>

				</div>
					<div style="width:110px;display:flex;flex-direction:column;gap:6px;align-items:center;justify-content:center">
					   <div style="display:flex;align-items:center;gap:8px">
						   <button class="chev" id="next-act" onclick="navigateActivity(1)">›</button>
						   <div class="chev-label" style="font-size:12px;color:var(--muted);">Suiv</div>
					   </div>
					   <div style="display:flex;align-items:center;gap:8px">
						   <button class="chev" id="prev-act" onclick="navigateActivity(-1)">‹</button>
						   <div class="chev-label" style="font-size:12px;color:var(--muted);">Prec</div>
					   </div>
					</div>
			</div>
		</section>

		<section id="shortcuts-card" class="card stroked">
			   <div class="section-title">Raccourcis</div>
			   <hr style="border:none;border-top:1px solid rgba(255,215,0,0.08);margin:8px 0">
			   <style>
				   /* Force uniform tile sizing and alignment */
				   .shortcuts-grid { align-items:stretch; }
				   .shortcuts-grid .tile { height:70px !important; display:flex !important; flex-direction:column !important; justify-content:space-between !important; align-items:center !important; box-sizing:border-box !important; }
                   /* Reduce fonts for compact shortcuts view */
                   .shortcuts-grid .tile { font-size: 13px; }
                   .shortcuts-grid .tile-top { font-size: 13px; }
                   .shortcuts-grid .tile-bottom { font-size: 12px; }
                   .shortcuts-grid .tile .icon-circle { width:38px; height:38px; }
				   /* Fixed zones so tops and bottoms align across tiles */
				   .shortcuts-grid .tile-top{height:44px;flex:0 0 44px;display:flex;align-items:center;justify-content:center;padding:0;margin:0}
				   .shortcuts-grid .tile-bottom{height:26px;flex:0 0 26px;display:flex;align-items:center;justify-content:center;padding:0;margin:0;width:100%;box-sizing:border-box}
				   /* Ensure icons are vertically centered inside the top zone */
				   .shortcuts-grid .tile .icon-circle, .shortcuts-grid .timer-circle-container{margin:0;align-self:center}
				   .shortcuts-grid .tile .icon-circle{margin:0}
				   .shortcuts-grid .timer-circle-container{margin:0;}
			   </style>
			   <div class="shortcuts-grid">
	<?php
	// --- ADVANCED TIMER LOGIC (fullscreen-timer.php style, with JS sync) ---
	$timer_level = '--';
	$timer_blinds = '-- / --';
	$timer_duration = 0;
	$timer_seconds_left = 0;
	$timer_end = null;
	$timer_start = null;
	$timer_ordre = 1;
	$blinds_json = '[]';
	if (!empty($serverActivity['id'])) {
		$id = intval($serverActivity['id']);
		$now = time();
		$q = mysqli_query($con, "SELECT * FROM `blindes-live` WHERE `id-activite` = '$id' ORDER BY `ordre` ASC");
		$blinds = [];
		while($row = mysqli_fetch_assoc($q)) { $blinds[] = $row; }
		$blinds_json = json_encode($blinds, JSON_UNESCAPED_UNICODE);
		$currentIndex = -1;
		foreach($blinds as $k => $b) {
			if (strtotime($b['fin']) > $now) {
				$currentIndex = $k;
				break;
			}
		}
		if ($currentIndex === -1 && count($blinds) > 0) {
			// All levels finished, show last
			$currentIndex = count($blinds) - 1;
		}
		if ($currentIndex !== -1) {
			$b = $blinds[$currentIndex];
			$timer_level = 'Niveau ' . htmlspecialchars($b['ordre']);
			$timer_blinds = htmlspecialchars($b['sb']) . ' / ' . htmlspecialchars($b['bb']);
			$timer_end = $b['fin'];
			$timer_start = $b['debut'];
			$timer_duration = max(1, strtotime($b['fin']) - strtotime($b['debut']));
			$timer_seconds_left = max(0, strtotime($b['fin']) - $now);
			$timer_ordre = intval($b['ordre']);
		}
	}
	?>
		<div class="tile" id="qs-timer-tile" style="height:70px;display:none;flex-direction:column;justify-content:space-between;">
		       <div class="tile-top" style="padding-top:0;">
				   <div class="timer-circle-container" style="width:56px;height:56px;position:relative;margin:0 auto;">
					       <svg class="timer-svg" viewBox="0 0 80 80" style="width:100%;height:100%;position:absolute;top:0;left:0;">
						       <circle class="timer-bg" cx="40" cy="40" r="36" style="stroke-width:4;"></circle>
						       <circle class="timer-progress" id="qs-timer-progress" cx="40" cy="40" r="36" style="stroke-width:4;"></circle>
				       </svg>
					       <div class="timer-content" style="position:absolute;top:0;left:0;width:100%;height:100%;display:flex;flex-direction:column;align-items:center;justify-content:center;z-index:2;">
							       <div id="qs-timer-level" style="font-size:10px;font-weight:600;color:#fff;letter-spacing:1px;text-transform:uppercase;"></div>
							       <div id="qs-timer-display" style="font-size:18px;font-weight:900;color:#00d2ff;line-height:1;"></div>
							       <div id="qs-timer-blinds" style="font-size:10px;color:#ffc107;font-weight:700;margin-top:2px;"></div>
					       </div>
			       </div>
					       <!-- <div class="count-label" id="live-timer-label" style="margin-top:8px;">Live Timer</div> -->
		</div>
						<div class="tile-bottom" id="live-timer-title"></div>
					   <!-- <div class="tile-bottom" id="live-timer-status">—</div> -->
	</div>
	<script>
	(function(){
		var display = document.getElementById('qs-timer-display');
		var progressCircle = document.getElementById('qs-timer-progress');
		var levelEl = document.getElementById('qs-timer-level');
		var blindsEl = document.getElementById('qs-timer-blinds');
		var seconds = 0;
		var total = 0;
		var timerPaused = false;

		var activityStartTs = <?php echo (isset($serverActivity['date']) && @strtotime($serverActivity['date']) !== false) ? intval(strtotime($serverActivity['date'])) : '0'; ?>;
		var countdownInterval = null;

		function showCountdown() {
			var tile = document.getElementById('qs-timer-tile');
			var nowTs = Math.floor(Date.now() / 1000);
			var diff = activityStartTs - nowTs;
			if(diff <= 0 || activityStartTs === 0) {
				// Partie démarrée : arrêter le compte à rebours
				if(countdownInterval) { clearInterval(countdownInterval); countdownInterval = null; }
				if(tile) tile.style.display = 'none';
				return;
			}
			var h = Math.floor(diff / 3600).toString().padStart(2,'0');
			var m = Math.floor((diff % 3600) / 60).toString().padStart(2,'0');
			var s = (diff % 60).toString().padStart(2,'0');
			if(tile) tile.style.display = 'flex';
			display.textContent = h+':'+m+':'+s;
			display.style.color = '#00d2ff';
			display.style.fontSize = '14px';
			progressCircle.style.strokeDashoffset = 0;
			progressCircle.style.stroke = '#00d2ff';
			progressCircle.style.filter = 'drop-shadow(0 0 6px #00d2ff)';
			if(levelEl) levelEl.textContent = '';
			if(blindsEl) blindsEl.textContent = 'Démarre dans';
		}

		function updateDisplay() {
			var tile = document.getElementById('qs-timer-tile');
			// Masquer si pas de timer ou si valeur aberrante (> 2h = timer pas encore démarré)
			var timerValid = (seconds > 0 && seconds <= 7200);
			if(!timerValid) {
				if(tile) tile.style.display = 'none';
				return;
			}
			// Timer live actif : arrêter le compte à rebours
			if(countdownInterval) { clearInterval(countdownInterval); countdownInterval = null; }
			display.style.fontSize = '';
			if(blindsEl && blindsEl.textContent === 'Démarre dans') blindsEl.textContent = '';
			var m = Math.floor(seconds/60).toString().padStart(2,'0');
			var s = (seconds%60).toString().padStart(2,'0');
			display.textContent = m+':'+s;
			if(total > 0){
				var elapsed = total - seconds;
				var progress = Math.max(0, Math.min(1, elapsed/total));
				var circumference = 2 * Math.PI * 50;
				var offset = circumference * (1 - progress);
				progressCircle.style.strokeDashoffset = offset;
				if(seconds <= 120){
					display.style.color = '#ff0000';
					progressCircle.style.stroke = '#ff0000';
					progressCircle.style.filter = 'drop-shadow(0 0 6px #ff0000)';
				} else {
					display.style.color = '#00d2ff';
					progressCircle.style.stroke = '#00d2ff';
					progressCircle.style.filter = 'drop-shadow(0 0 6px #00d2ff)';
				}
			}
		}

		function tick() {
			if(!timerPaused && seconds > 0){ seconds--; updateDisplay(); }
		}

		function syncTimer() {
			var params = new URLSearchParams(window.location.search);
			var uid = params.get('uid');
			if(!uid) return;
			fetch('/panel/timer-api.php?uid='+encodeURIComponent(uid)+'&_='+Date.now())
			.then(r=>r.json())
			.then(function(data){
				if(data.status!=='success') return;
			var sec = parseInt(data.seconds_remaining)||0;
			// Ignorer si valeur aberrante (> 2h = timer pas encore démarré)
			if(sec <= 0 || sec > 7200) return;
			seconds = sec;
			total = parseInt(data.duration_seconds)||0;
				if(levelEl){
					var txt = data.level_name ? data.level_name.replace(/^Niveau\s*/i,'').trim() : '--';
					levelEl.textContent = txt;
				}
				if(blindsEl) blindsEl.textContent = data.blinds_text || '-- / --';
				timerPaused = !!data.is_paused;
				updateDisplay();
				// Refresh profile links to point to the active activity id (use URL uid or fallback to lastActivity)
				try{
					var actId = null;
					if(window.SERVER_ACTIVITY && window.SERVER_ACTIVITY.id) actId = window.SERVER_ACTIVITY.id;
					var params = new URLSearchParams(window.location.search);
					if(!actId && params.has('uid')) actId = params.get('uid');
					if(!actId){ try{ var la = localStorage.getItem('lastActivity'); if(la){ var obj = JSON.parse(la); if(obj && obj.id) actId = obj.id; } }catch(e){}
					}
					_setProfileLinksFromActivity(actId);
				}catch(e){}
		       });
	       }
		   setInterval(tick, 1000);
		   setInterval(syncTimer, 5000);
		   syncTimer();
		   // Force full page reload every 30 seconds for robustness
		   setInterval(function(){
			   // Force cache refresh by appending a random query param
			   var url = new URL(window.location.href);
			   url.searchParams.set('cachebust', Math.floor(Math.random()*1e8));
			   window.location.replace(url.toString());
		   }, 300000);
	})();
	<?php
	// If timer_sync=1, return JSON for JS sync
	if (isset($_GET['timer_sync']) && $_GET['timer_sync'] == 1 && !empty($serverActivity['id'])) {
		header('Content-Type: application/json');
		echo json_encode(['blinds' => $blinds], JSON_UNESCAPED_UNICODE);
		exit;
	}
	?>
	</script>
				<div class="tile" id="details-tile" role="button" tabindex="0" style="cursor:pointer;height:70px;display:flex;flex-direction:column;justify-content:space-between;">
					<div class="tile-top"><div class="icon-circle info">i</div></div>
					<div class="tile-bottom">Détails Partie</div>
				</div>
				<a id="profile-tile" class="tile" role="link" href="/panel/profile.php<?php echo (!empty($serverActivity['id'])? '?uid=' . intval($serverActivity['id']): ''); ?>" style="text-decoration:none;color:inherit;height:70px;display:flex;flex-direction:column;justify-content:space-between;">
					<div class="tile-top"><div class="icon-circle profile">👤</div></div>
					<div class="tile-bottom">Mon Profil / Traker</div>
				</a>
				<?php
					// If the activity date is in the past, link to results; otherwise link to participants
					$participants_href = '/panel/participants.php';
					if (!empty($serverActivity['id'])) {
						$uid_q = '?uid=' . intval($serverActivity['id']);
					} else {
						$uid_q = '';
					}
					if (!empty($serverActivity['date']) && @strtotime($serverActivity['date']) !== false && strtotime($serverActivity['date']) < time()) {
						$participants_href = '/panel/resultats.php' . $uid_q;
					} else {
						$participants_href = '/panel/participants.php' . $uid_q;
					}
				?>
				<a id="participants-tile" class="tile" role="link" href="<?php echo htmlspecialchars($participants_href); ?>" style="text-decoration:none;color:inherit;height:70px;display:flex;flex-direction:column;justify-content:space-between;">
					<div class="tile-top"><div class="icon-circle people">👥</div></div>
					<div class="tile-bottom"><?php echo (strpos($participants_href, 'resultats.php') !== false) ? 'Classement' : 'Liste participants'; ?></div>
				</a>
			</div>
		</section>

		<style>
		/* Reduce podium font size for compact display */
		#podium-section { font-size: 13px; }
		#podium-section .podium-item { display:flex; justify-content:space-between; gap:12px; font-size:13px; }
		#podium-section .podium-item div { line-height:1.1; }
		@media (max-width:480px){ #podium-section { font-size:12px; } }
		</style>
		<section id="podium-section" class="card stroked" style="display:none" aria-hidden="true">
			<div style="font-weight:700;color:var(--gold);text-transform:uppercase;font-size:12px">Podium payés</div>
			<hr style="border:none;border-top:1px solid rgba(255,215,0,0.08);margin:8px 0">
			<div id="podium-list">
						<?php
						// Server-side fallback: render podium entries if any players have a positive gain
						if (!empty($con) && !empty($serverActivity) && !empty($serverActivity['id'])) {
							$aid = intval($serverActivity['id']);
							$podq = mysqli_query($con, "SELECT COALESCE(p.classement,999) AS classement, COALESCE(p.gain,0) AS gain, COALESCE(p.`nom-membre`, m.pseudo) AS pseudo FROM participation p JOIN membres m ON p.`id-membre` = m.`id-membre` WHERE p.`id-activite` = '". $aid ."' AND COALESCE(p.gain,0) > 0 ORDER BY classement ASC, gain DESC LIMIT 20");
							if ($podq && mysqli_num_rows($podq) > 0) {
								while ($prow = mysqli_fetch_assoc($podq)) {
									$ps = htmlspecialchars($prow['pseudo']);
									$g = intval($prow['gain']);
									echo "<div class=\"podium-item\"><div style=\"font-weight:700\">{$ps}</div><div style=\"color:var(--green);font-weight:700\">" . number_format($g, 0, ',', ' ') . " €</div></div>";
								}
							} else {
								echo '<div class="small">Aucun joueur payé</div>';
							}
						} else {
							echo '<div class="small">Chargement...</div>';
						}
						?>
			</div>
		</section>

		   <section id="reg-section" class="card quick-action">
						 <div style="display:flex;align-items:center;justify-content:space-between">
							 <div id="reg-text" style="font-weight:600;font-size:14px">Votre Inscription : </div>
							<?php
								$is_registered = (!empty($serverParticipation) && isset($serverParticipation['status']) && !in_array($serverParticipation['status'], array('None','Desinscrit')));
								$reg_label = $is_registered ? 'Modifier' : 'S Inscrire';
								$reg_style = 'padding:8px 12px;border-radius:10px;font-weight:700';
								if ($is_registered) { $reg_style .= ';background:#ff9d00;color:#04131d'; }
							?>
							<button id="reg-action" class="button primary" style="<?php echo $reg_style; ?>"><?php echo $reg_label; ?></button>
						</div>

				<!-- Partie Detail modal -->
				<div id="partie-modal" class="modal-overlay" aria-hidden="true">
					<div class="modal-sheet" role="dialog" aria-modal="true">
						<button id="modal-close" class="modal-close">Fermer</button>
						<div class="modal-title" id="modal-title">Titre activité</div>
						<div class="modal-sub" id="modal-sub">—</div>

						<div class="detail-card">
							<div style="font-weight:700;margin-bottom:8px">Infos Partie</div>
							<div class="detail-row"><div class="detail-label"><span class="detail-icon profile">👤</span>Organisateur</div><div class="detail-value" id="d-organisateur">—</div></div>
							<div class="detail-row"><div class="detail-label"><span class="detail-icon location">📍</span>Lieu</div><div class="detail-value" id="d-lieu">—</div></div>
							<div class="detail-row"><div class="detail-label"><span class="detail-icon people">👥</span>Inscrits / Max</div><div class="detail-value" id="d-inscrits">—</div></div>
							<div class="detail-row"><div class="detail-label"><span class="detail-icon info">▦</span>Tables</div><div class="detail-value" id="d-tables">—</div></div>
						</div>

						<div class="detail-card">
							<div style="font-weight:700;margin-bottom:8px">Financier</div>
							<div class="detail-row"><div class="detail-label"><span class="detail-icon money">💶</span>Buy-in</div><div class="detail-value" id="d-buyin">—</div></div>
							<div class="detail-row"><div class="detail-label"><span class="detail-icon money">✦</span>Rake (Mini)</div><div class="detail-value" id="d-rake">—</div></div>
							<div class="detail-row"><div class="detail-label"><span class="detail-icon money">🎯</span>Bounty</div><div class="detail-value" id="d-bounty">—</div></div>
							<div class="detail-row"><div class="detail-label"><span class="detail-icon money">🔁</span>Recave (×2)</div><div class="detail-value" id="d-recave">—</div></div>
							<div class="detail-row"><div class="detail-label"><span class="detail-icon location">🎲</span>Jetons départ</div><div class="detail-value" id="d-jetons">—</div></div>
						</div>

						<div class="detail-card">
							<div style="font-weight:700;margin-bottom:8px">Structure Semaine</div>
							<div class="detail-row" style="justify-content:flex-end"><div class="detail-value box" id="d-structure-detail">—</div></div>
						</div>
					</div>
				</div>

				<!-- Inscription modal (mobile-style switches + actions) -->
				<div id="inscription-modal" class="modal-overlay" aria-hidden="true" style="display:none">
					<div class="modal-sheet" role="dialog" aria-modal="true" style="max-width:420px;padding:18px;">
						<button class="modal-close inscription-modal-close" style="float:right">Fermer</button>
						<div style="font-weight:700;color:var(--gold);margin-bottom:6px">Options</div>
						<form id="ins-form" method="post" action="/panel/inscription.php" style="margin-top:8px">
							<input type="hidden" name="quick_reg" value="1">
							<input type="hidden" name="uid" value="">
							<input type="hidden" name="status" value="Inscrit">
							<input type="hidden" name="anonyme" value="0">
							<input type="hidden" name="latereg" value="0">
							<div style="display:flex;flex-direction:column;gap:12px">
								<!-- Anonyme -->
								<label style="display:flex;align-items:center;justify-content:space-between">
									<div style="display:flex;align-items:center;gap:12px">
										<span style="opacity:0.9">👁️</span>
										<div>
											<div style="font-weight:700">Anonyme</div>
											<div class="small" style="color:var(--muted)">Votre nom ne sera pas affiché publiquement</div>
										</div>
									</div>
									<input id="ins-anon" type="checkbox" />
								</label>

								<!-- Option -->
								<label style="display:flex;align-items:center;justify-content:space-between">
									<div style="display:flex;align-items:center;gap:12px">
										<span style="color:var(--gold);">★</span>
										<div>
											<div style="font-weight:700">Option</div>
											<div class="small" style="color:var(--muted)">Inscription sous réserve de confirmation</div>
										</div>
									</div>
									<input id="ins-opt" type="checkbox" />
								</label>

								<!-- Latereg -->
								<label style="display:flex;align-items:center;justify-content:space-between">
									<div style="display:flex;align-items:center;gap:12px">
										<span style="opacity:0.7">⏱️</span>
										<div>
											<div style="font-weight:700">Latereg</div>
											<div class="small" style="color:var(--muted)">Inscription tardive</div>
										</div>
									</div>
									<input id="ins-late" type="checkbox" />
								</label>

								<!-- Option / chapitre text -->
								<div>
									<input type="text" name="option_chapitre" placeholder="Option / Chapitre" style="width:100%;padding:8px;border-radius:8px;border:1px solid rgba(255,255,255,0.06);background:transparent;color:inherit">
								</div>

								<!-- Actions -->
								<div style="display:flex;gap:12px;margin-top:6px">
									<button type="submit" id="ins-validate" class="button" style="flex:1;background:#17a34a;color:#fff;border-radius:10px;padding:12px 14px;font-weight:700">Valider</button>
									<button type="button" id="ins-unregister" class="button" style="flex:1;background:#c92b2b;color:#fff;border-radius:10px;padding:12px 14px;font-weight:700">Désinscrire</button>
								</div>
							</div>
						</form>
					</div>
				</div>

				<script>
				// Modal behavior: sync toggles with hidden inputs and handle unregister
				document.addEventListener('DOMContentLoaded', function(){
					var modal = document.getElementById('inscription-modal');
					if(!modal) return;
					var form = document.getElementById('ins-form');
					var inAnon = modal.querySelector('#ins-anon');
					var inOpt = modal.querySelector('#ins-opt');
					var inLate = modal.querySelector('#ins-late');
					var hidAnon = form.querySelector('input[name="anonyme"]');
					var hidLate = form.querySelector('input[name="latereg"]');
					var hidStatus = form.querySelector('input[name="status"]');

					function syncHidden(){
						hidAnon.value = inAnon && inAnon.checked ? '1' : '0';
						hidLate.value = inLate && inLate.checked ? '1' : '0';
						hidStatus.value = inOpt && inOpt.checked ? 'Option' : 'Inscrit';
					}
					[inAnon,inOpt,inLate].forEach(function(el){ if(el) el.addEventListener('change', syncHidden); });
					syncHidden();

					// Desinscrire button
					var btnUn = document.getElementById('ins-unregister');
					if(btnUn){
						btnUn.addEventListener('click', function(){
							// set status to None (server maps None -> Desinscrit)
							form.querySelector('input[name="status"]').value = 'None';
							// clear flags
							form.querySelector('input[name="anonyme"]').value = '0';
							form.querySelector('input[name="latereg"]').value = '0';
							form.submit();
						});
					}
					// Close modal when clicking close button
					var closeBtns = modal.querySelectorAll('.inscription-modal-close');
					closeBtns.forEach(function(b){ b.addEventListener('click', function(){ modal.style.display='none'; modal.setAttribute('aria-hidden','true'); }); });
				});
				</script>



				<script src="/panel/timer_web/public/app.js"></script>
			   </div>
		   </section>

	</div>

	<!-- Bottom navigation backdrop to ensure a solid black background under the nav -->
	<div class="bottom-nav-backdrop" aria-hidden="true"></div>
	<!-- Bottom navigation (mobile) matching simulator: Accueil, Local Timer, Répartition -->
	<style>
	.bottom-nav { margin-top: 20px !important; }
	</style>
	<nav class="bottom-nav" role="navigation" aria-label="Main navigation">
		<button id="nav-home" class="" title="Accueil" onclick="window.location.href='/panel/quickview.php';">
			<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M3 11.5L12 4l9 7.5"/><path d="M5 21h14a1 1 0 0 0 1-1v-7H4v7a1 1 0 0 0 1 1z"/></svg>
			<div class="nav-label">Accueil</div>
		</button>
		<button id="nav-local" class="active" title="Local Timer" onclick="window.location.href='/newtimer/index.php';">
			<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v6l4 2"/></svg>
			<div class="nav-label">Local Timer</div>
		</button>
		<button id="nav-split" class="" title="Répartition" onclick="window.location.href='/panel/repartition.php';">
			<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
			<div class="nav-label">Répartition</div>
		</button>
	</nav>


	   <script src="/panel/timer_web/public/app.js?v=<?php echo $asset_ver . '-' . rand(100000,999999); ?>"></script>

	   <script>
	   // Navigation: reload with ?uid=xxx for selected activity
	   function navigateActivity(delta) {
		   if (!window.activitiesList || !window.currentActivity) return;
		   let idx = window.activitiesList.findIndex(a => String(a.id) === String(window.currentActivity.id));
		   if (idx === -1) idx = 0;
		   let newIdx = idx + delta;
		   if (newIdx < 0 || newIdx >= window.activitiesList.length) return;
		   let newId = window.activitiesList[newIdx].id;
		   // Reload with ?uid=xxx
		   const url = new URL(window.location.href);
		   url.searchParams.set('uid', newId);
		   window.location.href = url.toString();
	   }
	   </script>

	<script>
	// Toggle podium vs registration: show podium when paid players exist
	document.addEventListener('DOMContentLoaded', function(){
		var podiumSection = document.getElementById('podium-section');
		var regSection = document.getElementById('reg-section');
		var podiumList = document.getElementById('podium-list');
		function updateVisibility(){
			if(!podiumList) return;
			// consider podium present when an element with class .podium-item exists
			var hasItems = !!podiumList.querySelector('.podium-item');
			// also accept table rows or non-empty paid content
			if(!hasItems){
				// trim text and check for known empty messages
				var txt = (podiumList.textContent||'').trim();
				if(txt && !/chargement|aucun joueur payé|erreur réseau/i.test(txt)) hasItems = true;
			}
			if(hasItems){
				if(podiumSection){ podiumSection.style.display='block'; podiumSection.removeAttribute('aria-hidden'); }
				if(regSection){ regSection.style.display='none'; regSection.setAttribute('aria-hidden','true'); }
			} else {
				if(podiumSection){ podiumSection.style.display='none'; podiumSection.setAttribute('aria-hidden','true'); }
				if(regSection){ regSection.style.display='block'; regSection.removeAttribute('aria-hidden'); }
			}
		}
		if(podiumList){
			var mo = new MutationObserver(function(){ setTimeout(updateVisibility, 50); });
			mo.observe(podiumList, { childList: true, subtree: true, characterData: true });
		}
		// initial check in case app.js already populated podium
		setTimeout(updateVisibility, 700);
	});
	</script>


	<script>
		(function(){
			const link = document.getElementById('theme-stylesheet');
			const apply = v=>{ link.href = (v==='B')? '/panel/timer_web/public/style.variantB.css':'/panel/timer_web/public/style.variantA.css'; localStorage.setItem('uiVariant', v); };
			const variantABtn = document.getElementById('variantA');
			if(variantABtn) variantABtn.addEventListener('click', ()=>apply('A'));
			const variantBBtn = document.getElementById('variantB');
			if(variantBBtn) variantBBtn.addEventListener('click', ()=>apply('B'));
			const saved = localStorage.getItem('uiVariant') || 'A'; apply(saved);
		})();
	</script>
</body>
</html>
