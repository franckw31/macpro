<?php
session_start();
error_reporting(0);
include(__DIR__ . '/include/config.php');
include(__DIR__ . '/../include/functions_logs.php');
$id = isset($_GET['id']) ? intval($_GET['id']) : intval($_GET['uid']);
$pseudo_get = isset($_GET['pseudo']) ? mysqli_real_escape_string($con, $_GET['pseudo']) : null;
$pass_get = isset($_GET['passwd']) ? mysqli_real_escape_string($con, $_GET['passwd']) : null;

// Authentification automatique via URL si paramètres fournis
if ($pseudo_get && $pass_get) {
	$q_auth = mysqli_query($con, "SELECT `id-membre`, `pseudo` FROM membres WHERE (pseudo = '$pseudo_get' OR email = '$pseudo_get') AND (password = '$pass_get' OR password_ext = '$pass_get')");
	if ($r_auth = mysqli_fetch_array($q_auth)) {
		$_SESSION['login'] = $r_auth['pseudo'];
		$_SESSION['id'] = $r_auth['id-membre'];
		$_SESSION['login_source'] = 'Quickview/QR';
		log_activity($con, "Auto-Login Quickview", "User: $pseudo_get via URL");
	} else {
		log_activity($con, "Auto-Login Failed Quickview", "Attempted User: $pseudo_get");
	}
}

// Si aucun ID n'est fourni, on cherche en priorité une activité "en cours" ou très récente
// (démarrée dans les 48 dernières heures), puis la prochaine activité future, puis à défaut la dernière passée.
if ($id == 0) {
	// 1) Activité en cours ou très récente (max 2 jours de durée)
	$q_current = mysqli_query($con, "SELECT `id-activite` FROM activite WHERE date_depart <= NOW() AND date_depart >= (NOW() - INTERVAL 2 DAY) ORDER BY date_depart DESC LIMIT 1");
	if ($q_current && mysqli_num_rows($q_current) > 0) {
		$r_current = mysqli_fetch_array($q_current);
		$id = $r_current['id-activite'];
	} else {
		// 2) Prochaine activité future
		$q_next = mysqli_query($con, "SELECT `id-activite` FROM activite WHERE date_depart >= NOW() ORDER BY date_depart ASC LIMIT 1");
		if ($q_next && mysqli_num_rows($q_next) > 0) {
			$r_next = mysqli_fetch_array($q_next);
			$id = $r_next['id-activite'];
		} else {
			// 3) Aucune future : on prend la dernière passée
			$q_last = mysqli_query($con, "SELECT `id-activite` FROM activite ORDER BY date_depart DESC LIMIT 1");
			if ($q_last && mysqli_num_rows($q_last) > 0) {
				$r_last = mysqli_fetch_array($q_last);
				$id = $r_last['id-activite'];
			}
		}
	}

$query_act = mysqli_query($con, "SELECT * FROM activite WHERE `id-activite` = '$id'");
$row_act = mysqli_fetch_array($query_act);
		exit;
					// Toujours mettre à jour le mode anonyme et latereg si colonnes disponibles
					if ($has_anonyme) {
						$update_fields[] = "`anonyme` = '$anonyme'";
					}
					if ($has_latereg) {
						$update_fields[] = "`latereg` = '$latereg'";
					}
					
					if(!empty($update_fields)) {
						$update_fields[] = "`ds` = NOW()";
						$sql = "UPDATE participation SET " . implode(", ", $update_fields) . " WHERE `id-membre` = '$user_id' AND `id-activite` = '$act_id'";
						mysqli_query($con, $sql);
						log_activity($con, "Quick Participation Update", "Activity ID: $act_id, Status: $status");
					}
				} else {
					// Création de la participation
					$m_q = mysqli_query($con, "SELECT pseudo FROM membres WHERE `id-membre` = '$user_id'");
					$m_r = mysqli_fetch_array($m_q);
					$m_name = mysqli_real_escape_string($con, $m_r['pseudo']);
					
					$q_ordre = mysqli_query($con, "SELECT MAX(ordre) as max_o FROM participation WHERE `id-activite` = '$act_id'");
					$r_ordre = mysqli_fetch_array($q_ordre);
					$next_ordre = intval($r_ordre['max_o']) + 1;
					
					$final_status = ($status !== null && $status != 'None') ? $status : 'None';
					$final_rake = ($id_rake !== null) ? $id_rake : 1;
					
					// Construction dynamique de la requête INSERT selon les colonnes disponibles
					$cols = "`id-membre`, `id-activite`, `nom-membre`, `option`, `id-challenge`, `ordre`, `valide`, `classement`, `id_rake`";
					$vals = "'$user_id', '$act_id', '$m_name', '$final_status', '$challenge_id', '$next_ordre', 'Actif', '1', '$final_rake'";
					if ($has_anonyme) {
						$cols .= ", `anonyme`";
						$vals .= ", '$anonyme'";
					}
					if ($has_latereg) {
						$cols .= ", `latereg`";
						$vals .= ", '$latereg'";
					}
					$cols .= ", `ds`";
					$vals .= ", NOW()";
					$sql_insert = "INSERT INTO participation ($cols) VALUES ($vals)";
					mysqli_query($con, $sql_insert);
					log_activity($con, "Quick Participation Create", "Activity ID: $act_id, Status: $final_status");
				}
			}
		}
		header("Location: quickview.php?uid=".$id);
		exit;
	}
	?>
	<!DOCTYPE html>

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
					<a id="header-profile-link" href="/panel/profile.php<?php echo (!empty($serverActivity['id'])? '?uid=' . intval($serverActivity['id']): ''); ?>" role="link" title="Mon Profil" style="text-decoration:none;color:inherit">
						<div class="avatar"><img src="<?php echo htmlspecialchars($avatar_url); ?>" alt="avatar" style="width:100%;height:100%;object-fit:cover"></div>
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
				<section id="activity-card" class="card stroked">
			<div class="section-title">Prochaine partie</div>
			<hr style="border:none;border-top:1px solid rgba(255,215,0,0.08);margin:8px 0">
			<!-- removed duplicate small label to avoid repeating the title -->
			<div class="row" style="margin-top:6px">
				<div style="flex:1">
					<div id="activity-name" style="font-weight:800;font-size:18px"><?php echo !empty($serverActivity['title'])? htmlspecialchars($serverActivity['title']) : '—'; ?></div>
					<div style="display:flex;align-items:center;gap:8px;margin-top:6px">
						<div class="date-pill"><svg class="date-pill-icon" width="18" height="18" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" role="img"><circle cx="12" cy="12" r="10" fill="currentColor"/><path d="M12.5 8v5l3 1" stroke="#ffffff" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" fill="none"/></svg></div>
						<div id="activity-date" class="small" style="color:var(--gold);font-weight:700"><?php echo !empty($serverActivity['display_date'])? htmlspecialchars($serverActivity['display_date']) : (!empty($serverActivity['date'])? htmlspecialchars($serverActivity['date']) : '—'); ?></div>
					</div>
					<div style="margin-top:8px;display:flex;gap:1px;align-items:center">
						<div class="pill" id="buyin-pill"><span><?php echo isset($serverActivity['buyin'])? htmlspecialchars($serverActivity['buyin']).' €':'—'; ?></span></div>
						<div class="pill" id="rake-pill">
							<svg width="18" height="18" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" role="img">
								<circle cx="12" cy="12" r="8" fill="none" stroke="currentColor" stroke-width="1.4"/>
								<path d="M9 6v6" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/>
								<path d="M10.5 6v6" stroke="currentColor" stroke-width="1.0" stroke-linecap="round"/>
								<path d="M15 5l-1.5 12" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/>
							</svg>
							<span><?php echo isset($serverActivity['rake'])? htmlspecialchars($serverActivity['rake']).' €':'—'; ?></span>
						</div>
						<div class="pill" id="recave-pill"><span><?php echo isset($serverActivity['recave'])? htmlspecialchars($serverActivity['recave']).' Rec':'—'; ?></span></div>
						<div class="pill" id="inscrits-pill"><svg width="18" height="18" viewBox="0 0 24 24" fill="none"><text x="2" y="16" font-size="16" fill="#B47BFF">👥</text></svg>
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
				<div style="width:52px;display:flex;flex-direction:column;gap:8px;align-items:center;justify-content:center">
				   <button class="chev" id="next-act" onclick="navigateActivity(1)">›</button>
				   <button class="chev" id="prev-act" onclick="navigateActivity(-1)">‹</button>
				</div>
			</div>
		</section>

		<section id="shortcuts-card" class="card stroked">
			   <div class="section-title">Raccourcis</div>
			   <hr style="border:none;border-top:1px solid rgba(255,215,0,0.08);margin:8px 0">
			   <div class="shortcuts-grid">
	<?php
	// --- ADVANCED TIMER LOGIC (fullscreen-cardevent.php style, with JS sync) ---
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
	       <div class="tile" id="live-cardevent-tile">
		       <div class="tile-top" style="padding-top:0;">
			       <div class="cardevent-circle-container" style="width:80px;height:80px;position:relative;margin:0 auto;">
					       <svg class="cardevent-svg" viewBox="0 0 80 80" style="width:100%;height:100%;position:absolute;top:0;left:0;">
						       <circle class="cardevent-bg" cx="40" cy="40" r="36" style="stroke-width:4;"></circle>
						       <circle class="cardevent-progress" id="live-cardevent-progress" cx="40" cy="40" r="36" style="stroke-width:4;"></circle>
				       </svg>
				       <div class="cardevent-content" style="position:absolute;top:0;left:0;width:100%;height:100%;display:flex;flex-direction:column;align-items:center;justify-content:center;z-index:2;">
									   <div id="live-cardevent-level" style="font-size:10px;font-weight:600;color:#fff;letter-spacing:1px;text-transform:uppercase;"></div>
									   <div id="live-cardevent-display" style="font-size:18px;font-weight:900;color:#00d2ff;line-height:1;">--:--</div>
									   <div id="live-cardevent-blinds" style="font-size:10px;color:#ffc107;font-weight:700;margin-top:2px;"></div>
				       </div>
			       </div>
					   <!-- <div class="count-label" id="live-cardevent-label" style="margin-top:8px;">Live Timer</div> -->
		</div>
					   <!-- <div class="tile-bottom" id="live-cardevent-status">—</div> -->
	</div>
	<script>
	(function(){
	       var display = document.getElementById('live-cardevent-display');
	       var progressCircle = document.getElementById('live-cardevent-progress');
	       var levelEl = document.getElementById('live-cardevent-level');
	       var blindsEl = document.getElementById('live-cardevent-blinds');
	       var seconds = 0;
	       var total = 0;
	       var timerPaused = false;
	       var statusEl = document.getElementById('live-cardevent-status');
		       // Get activity start time from PHP
		       var activityStart = null;
		       try {
			   activityStart = <?php echo isset($serverActivity['date']) ? '"'.addslashes($serverActivity['date']).'"' : 'null'; ?>;
		       } catch(e) { activityStart = null; }


				   // (activityStart is provided above) — avoid duplicate declaration

				   function updateDisplay() {
				       var now = new Date();
				       var showCountdown = false;
				       var countdownText = '';
				       var beforeStart = false;
				       if(activityStart) {
					   var startDate = new Date(activityStart.replace(/-/g,'/'));
					   if(startDate > now) {
					       // Show countdown
					       var diff = Math.floor((startDate - now) / 1000);
					       if(diff > 0) {
						   var h = Math.floor(diff/3600).toString().padStart(2,'0');
						   var m = Math.floor((diff%3600)/60).toString().padStart(2,'0');
						   countdownText = h+':'+m;
						   showCountdown = true;
						   beforeStart = true;
					       }
					   }
				       }
				       if(showCountdown) {
					   display.textContent = countdownText;
					   display.style.color = '#00d2ff';
					   progressCircle.style.strokeDashoffset = 0;
					   progressCircle.style.stroke = '#00d2ff';
					   progressCircle.style.filter = 'drop-shadow(0 0 6px #00d2ff)';
					   if(statusEl) statusEl.textContent = 'A venir';
					   if(levelEl) levelEl.textContent = '';
					   if(blindsEl) blindsEl.textContent = '';
					   return;
				       }
				       var m = Math.floor(seconds/60).toString().padStart(2,'0');
				       var s = (seconds%60).toString().padStart(2,'0');
				       display.textContent = m+':'+s;
				       // Progress
				       if(total > 0){
					       var elapsed = total - seconds;
					       var progress = Math.max(0, Math.min(1, elapsed/total));
					       var circumference = 2 * Math.PI * 50;
					       var offset = circumference * (1 - progress);
					       progressCircle.style.strokeDashoffset = offset;
					       if(seconds <= 120 && seconds > 0){
						       display.style.color = '#ff0000';
						       progressCircle.style.stroke = '#ff0000';
						       progressCircle.style.filter = 'drop-shadow(0 0 6px #ff0000)';
					       }else{
						       display.style.color = '#00d2ff';
						       progressCircle.style.stroke = '#00d2ff';
						       progressCircle.style.filter = 'drop-shadow(0 0 6px #00d2ff)';
					       }
				       }
				       // Update status label
						   if(statusEl){
							   var now = new Date();
							   var startDate = null;
							   if(activityStart){
								   var parts = String(activityStart).split(/[- :]/);
								   startDate = new Date(
									   parseInt(parts[0]||'0',10),
									   Math.max(0, (parseInt(parts[1]||'1',10)-1)),
									   parseInt(parts[2]||'1',10),
									   parseInt(parts[3]||'0',10),
									   parseInt(parts[4]||'0',10),
									   parseInt(parts[5]||'0',10)
								   );
							   }
							   // If activity has started and there is no remaining seconds -> mark as finished
							   if(startDate && now > startDate && seconds === 0){
								   statusEl.textContent = 'Terminée';
								   if(levelEl) levelEl.textContent = '';
								   if(blindsEl) blindsEl.textContent = '';
								   display.textContent = '--:--';
								   display.style.color = '#00d2ff';
								   progressCircle.style.strokeDashoffset = 0;
								   progressCircle.style.stroke = '#00d2ff';
								   progressCircle.style.filter = 'drop-shadow(0 0 6px #00d2ff)';
								   return;
							   }
							   // If activity is in the future
							   if(startDate && now < startDate){
								   statusEl.textContent = 'A venir';
								   return;
							   }
							   // Fallback based on available cardevent data
							   if(total === 0){
								   statusEl.textContent = '—';
							   } else if(seconds === 0){
								   statusEl.textContent = 'Terminé';
							   } else {
								   statusEl.textContent = 'Live';
							   }
						   }
			       }
	       function tick() {
		       if(!timerPaused && seconds > 0){
			       seconds--;
			       updateDisplay();
		       }
	       }
	       function syncTimer() {
		       var params = new URLSearchParams(window.location.search);
		       var uid = params.get('uid');
		       if(!uid) return;
		       fetch('/panel/cardevent-api.php?uid='+encodeURIComponent(uid))
		       .then(r=>r.json())
		       .then(function(data){
			       if(data.status!=='success') return;
			       seconds = parseInt(data.seconds_remaining)||0;
			       total = parseInt(data.duration_seconds)||0;
					   if(levelEl) {
						   // Remove 'Niveau' and only show 'x / y'
						   if(data.level_name){
							   var txt = data.level_name.replace(/^Niveau\s*/i, '').trim();
							   levelEl.textContent = txt;
						   } else {
							   levelEl.textContent = '--';
						   }
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
	<!DOCTYPE html>
	<html lang="en">
	<head>
		<title>Admin | Dashboard</title>
		<meta charset="utf-8" />
		<meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0, minimum-scale=1.0, maximum-scale=1.0">
		<link href="http://fonts.googleapis.com/css?family=Lato:300,400,400italic,600,700|Raleway:300,400,500,600,700|Crete+Round:400italic" rel="stylesheet" type="text/css" />
		<link rel="stylesheet" href="vendor/bootstrap/css/bootstrap.min.css">
		<link rel="stylesheet" href="vendor/fontawesome/css/font-awesome.min.css">
		<link rel="stylesheet" href="vendor/themify-icons/themify-icons.min.css">
		<link href="vendor/animate.css/animate.min.css" rel="stylesheet" media="screen">
		<link href="vendor/perfect-scrollbar/perfect-scrollbar.min.css" rel="stylesheet" media="screen">
		<link href="vendor/switchery/switchery.min.css" rel="stylesheet" media="screen">
		<link href="vendor/bootstrap-touchspin/jquery.bootstrap-touchspin.min.css" rel="stylesheet" media="screen">
		<link href="vendor/select2/select2.min.css" rel="stylesheet" media="screen">
		<link href="vendor/bootstrap-datepicker/bootstrap-datepicker3.standalone.min.css" rel="stylesheet" media="screen">
		<link href="vendor/bootstrap-timepicker/bootstrap-timepicker.min.css" rel="stylesheet" media="screen">
		<link rel="stylesheet" href="vendor/sweetalert/sweet-alert.css">
		<link rel="stylesheet" href="assets/css/styles.css">
		<link rel="stylesheet" href="assets/css/plugins.css?v=<?php echo time(); ?>">
		<link rel="stylesheet" href="assets/css/themes/theme-1.css?v=<?php echo time(); ?>" id="skin_color" />
		
		<!-- Modern Dashboard CSS -->
		<link rel="stylesheet" href="assets/css/modern-dashboard.css?v=<?php echo time(); ?>">
		<link rel="stylesheet" href="assets/css/card-bg.css?v=<?php echo time(); ?>">
		<style>
			.clip-radio.radio-square label:before, 
			.clip-radio.radio-square label:after {
				border-radius: 0 !important;
			}
			.radio-lightred input[type="radio"]:checked + label:after {
				background-color: #ff6666 !important;
			}
			.radio-lightred label:before {
				border-color: #ff6666 !important;
			}

			/* Motif déplacé vers assets/css/card-bg.css pour réutilisation */
		</style>
	</head>

	<body>
		<div id="app">
			<?php
			include('include/sidebar.php');
			?>
								</div>
								<!-- <ol class="breadcrumb">
									<li><span>Admin</span></li>
									<li class="active"><span>Dashboard</span></li>
								</ol> -->
							</div>
						</section>

						<!-- Stats Overview -->
						<div class="row">
							<div class="col-sm-4">
								<a href="prochaines-activites.php" class="dashboard-card card-blue">
									<div class="card-icon"><i class="fa fa-rocket" style="background: linear-gradient(45deg, #FF512F 0%, #DD2476 50%, #FF512F 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; display: inline-block;"></i></div>
									<div class="card-title"> Rdv: <?php echo date('d/m/Y H:i', strtotime($row_act['date_depart'])); ?></div>
									<div class="card-stat" style="font-size: 18px;">Buy-in: <?php echo htmlentities($row_act['buyin']); ?>€ , Rake: <?php echo htmlentities($row_act['rake']); ?>€</div>
									<div class="card-stat" style="font-size: 18px;">Recave(s): <?php echo htmlentities($row_act['recave']); ?> (<?php echo htmlentities($row_act['recave_montant']); ?>€)</div>
									<div class="card-stat" style="font-size: 18px;">Bounty: <?php echo htmlentities($row_act['bounty']); ?>€</div>
								</a>
							</div>
							<div class="col-sm-4">
								<a href="voir-activite.php?uid=<?php echo $id_act; ?>" class="dashboard-card card-orange">
									<div class="card-icon"><i class="fa fa-table"></i></div>
									<div class="card-title">Configuration</div>
									<div class="card-stat" style="font-size: 18px;">Nombre de tables: <?php echo htmlentities($row_act['nb-tables']); ?></div>
									<div class="card-stat" style="font-size: 18px;">Nombre de places: <?php echo htmlentities($row_act['places']); ?></div>
								</a>
							</div>
							<div class="col-sm-4">
								<a href="fullscreen.php?uid=<?php echo $id_act; ?>" class="dashboard-card card-green">
									<div class="card-icon"><i class="fa fa-clock-o"></i></div>
									<div class="card-title">Horaires / Voir Live</div>
									<div class="card-stat" style="font-size: 18px;">Départ: <?php 
										$start_ts = strtotime($row_act['date_depart']);
										echo date('H:i', $start_ts);

										echo " , Fin Estimée: ";
										$q_total_min = mysqli_query($con, "SELECT SUM(minutes) as total FROM `blindes-live` WHERE `id-activite` = '$id_act'");
										$r_total_min = mysqli_fetch_array($q_total_min);
										$total_min = intval($r_total_min['total']);
										if ($total_min > 0) {
											echo date('H:i', $start_ts + ($total_min * 60));
										} else {
											echo "N/A";
										}
									?></div>
									<div class="card-stat" style="font-size: 18px;">Pause vers : <?php 
										$q_pause = mysqli_query($con, "SELECT `ordre` FROM `blindes-live` WHERE `id-activite` = '$id_act' AND (`sb` = 0 OR `nom` LIKE '%Pause%' OR `nom` LIKE '%Break%') ORDER BY `ordre` ASC LIMIT 1");
										if($r_pause = mysqli_fetch_array($q_pause)) {
											$p_ordre = $r_pause['ordre'];
											$q_min_pause = mysqli_query($con, "SELECT SUM(minutes) as total FROM `blindes-live` WHERE `id-activite` = '$id_act' AND `ordre` < $p_ordre");
											$r_min_pause = mysqli_fetch_array($q_min_pause);
											$min_pause = intval($r_min_pause['total']);
											echo date('H:i', $start_ts + ($min_pause * 60));
										} else {
											echo "N/A";
										}
									?></div>
								</a>
							</div>
						</div>

						<!-- Main Navigation Sections -->
						
						<!-- Gestion -->
						<div class="row">
							<div class="col-sm-4">
								<a href="voir-blindes.php?uid=<?php echo $id_act; ?>&tab=t3" class="dashboard-card">
									<div class="card-icon"><i class="fa fa-list-ol"></i></div>
									<div class="card-title" style="color: black;">Structure du Tournoi</div>
									<div class="card-stat" style="font-size: 14px; font-weight: normal; line-height: 1.2; color: black;">
										<?php 
                                        
										$id_str = $row_act['id_structure'];
										$q_str = mysqli_query($con, "SELECT nom, Detail FROM structure_modele WHERE id_modele_structure = '$id_str' LIMIT 1");
										if($r_str = mysqli_fetch_array($q_str)) {
											/* echo "<strong>" . htmlentities($r_str['nom']) . "</strong><br>"; */
											echo nl2br(htmlentities($r_str['Detail']));
										} else {
											echo "Aucune structure définie";
										}
										?>
									</div>
								</a>
							</div>
							<div class="col-sm-4">
								<a href="liste-participants-activite.php?id_activite=<?php echo $id_act; ?>" class="dashboard-card card-purple">
									<div class="card-icon"><i class="fa fa-users"></i></div>
									<div class="card-title">Inscriptions</div>
									<div class="card-stat" style="font-size: 18px;">
										<?php 
										// Compte tous les joueurs réellement inscrits à cette activité :
										// on exclut seulement les statuts d'annulation/désinscription et "Option".
										// Cela rend le comptage robuste même si les libellés exacts
										// des statuts varient (avec ou sans accents, etc.).
										$q_ins = mysqli_query(
											$con,
											"SELECT COUNT(*) as total FROM participation
											 WHERE `id-activite` = '$id_act'
											   AND (
											     `option` IS NULL
											      OR `option` NOT IN ('Annule', 'Desinscrit', 'None', 'Option')
											   )"
										);
										$r_ins = mysqli_fetch_array($q_ins);
										$nb_inscrits = intval($r_ins['total']);
										
										$q_opt = mysqli_query($con, "SELECT COUNT(*) as total FROM participation WHERE `id-activite` = '$id_act' AND `option` = 'Option'");
										$r_opt = mysqli_fetch_array($q_opt);
										$nb_options = intval($r_opt['total']);
										
										$places_dispo = intval($row_act['places']) - $nb_inscrits;
										
										echo "Inscrits: " . $nb_inscrits . "<br>";
										echo "Option: " . $nb_options . "<br>";
										echo "Places Libres: " . ($places_dispo > 0 ? $places_dispo : 0);
										?>
									</div>
								</a>
							</div>
							<div class="col-sm-4">
								<div class="dashboard-card card-orange">
									<div class="card-icon"><i class="fa fa-cutlery"></i></div>
									<div class="card-title">PAF Obligatoire</div>
									<div class="card-stat" style="font-size: 14px; font-weight: normal; text-align: left; padding-left: 20px;">
										<form method="post">
											<input type="hidden" name="quick_reg" value="1">
											<?php 
											$user_id = intval($_SESSION['id']);
											$id_act = intval($row_act['id-activite']);
											$q_rake = mysqli_query($con, "SELECT * FROM rake ORDER BY id_rake ASC");
											$q_p_rake = mysqli_query($con, "SELECT `id_rake` FROM activite WHERE `id-activite` = '$id_act'");
											$r_p_rake = mysqli_fetch_array($q_p_rake);
											$current_rake = $r_p_rake ? $r_p_rake['id_rake'] : 1;

											while($row_rake = mysqli_fetch_array($q_rake)) {
												?>
												<div class="radio clip-radio radio-primary radio-square" style="margin-bottom: 15px;">
													<input type="radio" id="rake_<?php echo $row_rake['id_rake']; ?>" name="id_rake" value="<?php echo $row_rake['id_rake']; ?>" <?php echo ($current_rake == $row_rake['id_rake']) ? 'checked' : ''; ?> onchange="this.form.submit()">
													<label for="rake_<?php echo $row_rake['id_rake']; ?>" style="color: brown; font-weight: bold;">
														<?php echo htmlentities($row_rake['nom']); ?> (<?php echo $row_rake['montant']; ?>€)
													</label>
												</div>
												<?php
											}
											?>
										</form>
									</div>
								</div>
							</div>
						</div>

						<div class="row">
							<div class="col-sm-4">
								<a href="voir-membre.php?id=<?php echo $user_id; ?>&tab=col" class="dashboard-card card-yellow">
									<div class="card-icon" style="color: orange !important;"><i class="fa fa-star"></i></div>
									<div class="card-title">Ticket(s) de Tombola</div>
									<div class="card-stat" style="font-size: 24px; color:orange !important;">
										<?php 
										// Tickets de tombola du mois en cours pour ce membre (tickets non affectés au rake)
										$current_month = date('m');
										$current_year  = date('Y');
										$q_pts = mysqli_query($con, "SELECT COUNT(*) AS nb_tickets FROM `collections-individu` WHERE `id-indiv` = '$user_id' AND MONTH(`date`) = $current_month AND YEAR(`date`) = $current_year AND (`aff_rake` = 0 OR `aff_rake` IS NULL)");
										$r_pts = mysqli_fetch_array($q_pts);
										$nb_tickets = intval($r_pts['nb_tickets']);
										echo $nb_tickets." Ticket".($nb_tickets > 1 ? 's' : '')." ce mois";
										?>
									</div>
								</a>
							</div>
							<div class="col-sm-4">
								<a href="voir-membre.php?id=<?php echo $user_id; ?>&tab=portefeuille" class="dashboard-card card-green">
									<div class="card-icon"><i class="fa fa-eur"></i></div>
									<div class="card-title">Solde Carte Virtuelle</div>
									<div class="card-stat" style="font-size: 24px;">
										<?php 
										$q_solde = mysqli_query($con, "SELECT 
											COALESCE(SUM(CASE WHEN id_type_mvt = 4 THEN montant ELSE 0 END), 0) + COALESCE(SUM(CASE WHEN id_type_mvt = 5 THEN montant ELSE 0 END), 0) -
											COALESCE(SUM(CASE WHEN id_type_mvt = 1 THEN montant ELSE 0 END), 0) - COALESCE(SUM(CASE WHEN id_type_mvt = 2 THEN montant ELSE 0 END), 0) - COALESCE(SUM(CASE WHEN id_type_mvt = 3 THEN montant ELSE 0 END), 0) as balance
											FROM portefeuille 
											WHERE id_mvt_membre = '$user_id'");
										$r_solde = mysqli_fetch_array($q_solde);
										$solde = floatval($r_solde['balance']);
										echo number_format($solde, 2, ',', ' ') . " €";
										?>
									</div>
								</a>
							</div>
							<div class="col-sm-4">
								<a href="voir-membre.php?id=<?php echo $user_id; ?>&tab=ks" class="dashboard-card card-blue">
									<div class="card-icon"><i class="fa fa-line-chart"></i></div>
									<div class="card-title">Statistiques</div>
									<div class="card-stat" style="font-size: 14px; font-weight: normal; text-align: left; padding-left: 20px;">
										<?php 
										$q_stats = mysqli_query($con, "
											SELECT 
												COUNT(*) as nb_parties,
												SUM(p.gain) as total_gains,
												SUM(CASE WHEN p.gain > 0 THEN 1 ELSE 0 END) as nb_gains,
												SUM(COALESCE(a.buyin, 0) + COALESCE(a.rake, 0) + (p.recave * COALESCE(a.recave_montant, 0)) + (p.addon * COALESCE(a.recave_montant, 0))) as total_buyins
											FROM participation p
											JOIN activite a ON p.`id-activite` = a.`id-activite`
											WHERE p.`id-membre` = '$user_id' 
											AND p.`option` NOT IN ('Desinscrit', 'None')
										");
										$r_stats = mysqli_fetch_array($q_stats);
										?>
										<div style="margin-bottom: 5px;">Buy-ins : <strong><?php echo number_format(floatval($r_stats['total_buyins']), 2, ',', ' '); ?> € </strong><strong><?php echo " (".intval($r_stats['nb_parties']).")"; ?></strong></div>
										<div style="margin-bottom: 5px;">Gains : <strong><?php echo number_format(floatval($r_stats['total_gains']), 2, ',', ' '); ?> €</strong><strong><?php echo " (".intval($r_stats['nb_gains']).")"; ?></strong></div>
									</div>
								</a>
							</div>
						</div>

						<!-- Inscription & Options -->
						<div class="row">
							<div class="col-sm-4">
								<div class="dashboard-card">
									<div class="card-stat" style="font-size: 16px; font-weight: normal; padding-top: 10px;">
										<strong>Lieu:</strong> 
										<a href="map-location.php?id_act=<?php echo $row_act['id-activite']; ?>&lat=<?php echo $row_act['lat']; ?>&lng=<?php echo $row_act['lng']; ?>" style="color: inherit; text-decoration: underline;">
											<?php echo htmlentities($row_act['rue']) . ", " . htmlentities($row_act['ville']); ?>
										</a>
										<a href="map-location.php?id_act=<?php echo $row_act['id-activite']; ?>&lat=<?php echo $row_act['lat']; ?>&lng=<?php echo $row_act['lng']; ?>" style="display: block; margin-top: 10px; height: 220px; border-radius: 8px; overflow: hidden; border: 1px solid #ddd; position: relative;">
											<iframe src="map-location.php?id_act=<?php echo $row_act['id-activite']; ?>&lat=<?php echo $row_act['lat']; ?>&lng=<?php echo $row_act['lng']; ?>&mini=1" width="100%" height="100%" frameborder="0" style="border:0; pointer-events: none;"></iframe>
											<div style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; z-index: 5;"></div>
										</a>
									</div>
								</div>
							</div>
							<div class="col-sm-4">
								<div class="dashboard-card card-red animated pulse infinite" style="animation-duration: 2s;">
									<div class="card-icon"><i class="fa fa-pencil-square-o"></i></div>
									<div class="card-title">Ma Participation</div>
									<div class="card-stat" style="font-size: 16px; font-weight: normal; text-align: left; padding-left: 20px;">
										<form method="post">
											<input type="hidden" name="quick_reg" value="1">
											<?php 
											$user_id = intval($_SESSION['id']);
											$id_act = intval($row_act['id-activite']);
											$q_p = mysqli_query($con, "SELECT `option` FROM participation WHERE `id-membre` = '$user_id' AND `id-activite` = '$id_act'");
											$r_p = mysqli_fetch_array($q_p);
											$current_status = $r_p ? $r_p['option'] : 'None';
											// Vérifier si le joueur est inscrit (incluant Réservation et Présent),
											// en tenant compte des variantes avec/sans accents.
											$is_registered = in_array($current_status, [
												'Inscrit',
												'Réservation', 'Reservation',
												'Présent', 'Present',
												'Confirmé', 'Confirme',
												'Eliminé', 'Elimine'
											]);

											// Compter les messages non lus (privés)
											$q_unread = mysqli_query($con, "SELECT COUNT(*) as total FROM chat_messages WHERE receiver_id = '$user_id' AND is_read = 0 AND group_id IS NULL");
											$r_unread = mysqli_fetch_array($q_unread);
											$unread_count = intval($r_unread['total']);

											// Compter les messages non lus (groupes)
											$q_unread_groups = mysqli_query($con, "
												SELECT COUNT(*) as total 
												FROM chat_messages m
												JOIN chat_group_members gm ON m.group_id = gm.group_id
												WHERE gm.member_id = '$user_id' 
												AND m.sender_id != '$user_id'
												AND m.timestamp > gm.last_read_at
											");
											$r_unread_groups = mysqli_fetch_array($q_unread_groups);
											$unread_count += intval($r_unread_groups['total']);

											// Trouver le groupe de chat correspondant à l'activité
											$months = ["", "Janvier", "Février", "Mars", "Avril", "Mai", "Juin", "Juillet", "Août", "Septembre", "Octobre", "Novembre", "Décembre"];
											$d_obj = strtotime($row_act['date_depart']);
											$formatted_date = date('j', $d_obj) . ' ' . $months[intval(date('n', $d_obj))];
											$id_orga = $row_act['id-membre'];
											$q_orga_pseudo = mysqli_query($con, "SELECT pseudo FROM membres WHERE `id-membre` = '$id_orga' LIMIT 1");
											$r_orga_pseudo = mysqli_fetch_array($q_orga_pseudo);
											$expected_group_name = $formatted_date . " " . ($r_orga_pseudo ? $r_orga_pseudo['pseudo'] : "");

											$q_target_grp = mysqli_query($con, "SELECT id FROM chat_groups WHERE name = '".mysqli_real_escape_string($con, $expected_group_name)."' ORDER BY id DESC LIMIT 1");
											$r_target_grp = mysqli_fetch_array($q_target_grp);
											$target_group_id = $r_target_grp ? $r_target_grp['id'] : null;
											?>
											<input type="hidden" name="anonyme" id="anonyme_input" value="<?php echo $current_anonyme; ?>">
											<input type="hidden" name="latereg" id="latereg_input" value="<?php echo $current_latereg; ?>">
											<div class="radio clip-radio radio-primary" style="margin-bottom: 15px;">
												<input type="radio" id="reg_inscrit" name="status" value="Inscrit" <?php echo ($is_registered) ? 'checked' : ''; ?> onchange="handleRegistration(this)">
												<label for="reg_inscrit" style="color: #529d18; font-weight: bold; font-size: 18px;">INSCRIPTION</label>
											</div>
											<div class="radio clip-radio radio-primary" style="margin-bottom: 15px;">
												<input type="radio" id="reg_option" name="status" value="Option" <?php echo ($current_status == 'Option') ? 'checked' : ''; ?> onchange="this.form.submit()">
												<label for="reg_option" style="color: orange; font-weight: bold; font-size: 18px;">OPTION</label>
											</div>
											<div class="radio clip-radio radio-primary radio-lightred" style="margin-bottom: 5px;">
												<input type="radio" id="reg_none" name="status" value="None" <?php echo ($current_status == 'None' || $current_status == 'Desinscrit') ? 'checked' : ''; ?> onchange="this.form.submit()">
												<label for="reg_none" style="color: #f91919ff; font-weight: bold; font-size: 18px;">DÉSINCRIPTION</label>
											</div>
											<div style="margin-top: 10px; text-align: center;">
												<a href="chat.php<?php echo $target_group_id ? '?group_id='.$target_group_id : ''; ?>" style="color: #007bff; text-decoration: underline !important; font-weight: bold; font-size: 16px;">
													<i class="fa fa-comments"></i> Consulter les messages
													<?php if ($unread_count > 0): ?>
														<span class="badge badge-danger" style="background-color: #d9534f;"><?php echo $unread_count; ?></span>
													<?php endif; ?>
												</a>
											</div>
										</form>
									</div>
								</div>
							</div>
							<div class="col-sm-4">
								<div class="dashboard-card card-blue">
									<div class="card-icon"><i class="fa fa-forward"></i></div>
									<?php 
									$current_date = $row_act['date_depart'];
									$q_next_acts = mysqli_query($con, "SELECT * FROM activite WHERE date_depart > '$current_date' ORDER BY date_depart ASC LIMIT 1");
									$first_next_id = "";
									if(mysqli_num_rows($q_next_acts) > 0) {
										$r_temp = mysqli_fetch_array($q_next_acts);
										$first_next_id = $r_temp['id-activite'];
										mysqli_data_seek($q_next_acts, 0); // Reset pointer for the loop below
									}
									?>
									<div class="card-title">
										<a href="<?php echo $first_next_id ? "quickview.php?uid=$first_next_id" : "#"; ?>" style="color: #007bff; text-decoration: underline !important;">Prochaine Activité</a>
									</div>
									<div class="card-stat" style="font-size: 14px; font-weight: normal; text-align: left; padding-left: 20px; color: black;">
										<?php 
										if(mysqli_num_rows($q_next_acts) > 0) {
											while($r_next_act = mysqli_fetch_array($q_next_acts)) {
												$next_act_id = $r_next_act['id-activite'];
												$q_count = mysqli_query($con, "SELECT COUNT(*) as total FROM participation WHERE `id-activite` = '$next_act_id' AND `option` IN ('Reservation', 'Inscrit', 'Confirme', 'Elimine')");
												$r_count = mysqli_fetch_array($q_count);
												$nb_ins = intval($r_count['total']);
												$total_p = intval($r_next_act['places']);
												$libres = $total_p - $nb_ins;
												?>
												<div style="margin-bottom: 25px; line-height: 1.6;">
													<a href="quickview.php?uid=<?php echo $r_next_act['id-activite']; ?>" style="color: black; text-decoration: none;">
														<strong><?php echo date('d/m/Y H:i', strtotime($r_next_act['date_depart'])); ?></strong><br>
														Buy-in: <?php echo $r_next_act['buyin']; ?>€ + <?php echo $r_next_act['rake']; ?>€<br>
														Recaves: <?php echo $r_next_act['recave']; ?><br>
														Libres: <?php echo ($libres > 0 ? $libres : 0); ?> / <?php echo $total_p; ?>
													</a>
												</div>
												<?php
											}
										} else {
											echo "<span style='color: black;'>Aucune autre activité prévue</span>";
										}
										?>
									</div>
								</div>
							</div>
						</div>

					</div>
				</div>
			</div>
			
			<?php include('include/footer.php'); ?>
			<?php include('include/setting.php'); ?>
		</div>

		<!-- Scripts -->
		<script src="vendor/jquery/jquery.min.js"></script>
		<script src="vendor/bootstrap/js/bootstrap.min.js"></script>
		<script src="vendor/modernizr/modernizr.js"></script>
		<script src="vendor/jquery-cookie/jquery.cookie.js"></script>
		<script src="vendor/perfect-scrollbar/perfect-scrollbar.min.js"></script>
		<script src="vendor/switchery/switchery.min.js"></script>
		<!-- end: MAIN JAVASCRIPTS -->
		<!-- start: JAVASCRIPTS REQUIRED FOR THIS PAGE ONLY -->
		<script src="vendor/maskedinput/jquery.maskedinput.min.js"></script>
		<script src="vendor/bootstrap-touchspin/jquery.bootstrap-touchspin.min.js"></script>
		<script src="vendor/autosize/autosize.min.js"></script>
		<script src="vendor/selectFx/classie.js"></script>
		<script src="vendor/selectFx/selectFx.js"></script>
		<script src="vendor/select2/select2.min.js"></script>
		<script src="vendor/bootstrap-datepicker/bootstrap-datepicker.min.js"></script>
		<script src="vendor/bootstrap-timepicker/bootstrap-timepicker.min.js"></script>
		<script src="vendor/sweetalert/sweet-alert.min.js"></script>
		<!-- end: JAVASCRIPTS REQUIRED FOR THIS PAGE ONLY -->
		<!-- start: CLIP-TWO JAVASCRIPTS -->
		<script src="assets/js/main.js"></script>
		<script src="assets/js/card-bg.js"></script>
		<!-- start: JavaScript Event Handlers for this page -->
		<script src="assets/js/form-elements.js"></script>
		<script>
			jQuery(document).ready(function () {
				Main.init();
				FormElements.init();
			});

			// Étape 2 et 3 : Anonyme puis Latereg
			function askAnonymeAndLatereg(radio) {
				swal({
					title: "Anonyme ?",
					text: "Souhaitez-vous activer le mode Anonyme pour cette inscription ?",
					type: "info",
					showCancelButton: true,
					confirmButtonColor: "#007AFF",
					confirmButtonText: "Non",
					cancelButtonText: "Oui",
					closeOnConfirm: false,
					closeOnCancel: false
				}, function(isConfirm) {
					// isConfirm est vrai si on a cliqué sur "Non" (le bouton de confirmation)
					document.getElementById('anonyme_input').value = isConfirm ? '0' : '1';
					
					setTimeout(function() {
						swal({
							title: "Latereg ?",
							text: "Etes vous en Latereg ?",
							type: "info",
							showCancelButton: true,
							confirmButtonColor: "#007AFF",
							confirmButtonText: "Non",
							cancelButtonText: "Oui",
							closeOnConfirm: true,
							closeOnCancel: true
						}, function(isConfirmLatereg) {
							// isConfirmLatereg est vrai si on a cliqué sur "Non"
							document.getElementById('latereg_input').value = isConfirmLatereg ? '0' : '1';
							radio.form.submit();
						});
					}, 200);
				});
			}

			// Étape 1 : demander s'il veut plutôt se mettre en Option
			function handleRegistration(radio) {
				if (radio.value === 'Inscrit') {
					swal({
						title: "Option ?",
						text: "Souhaitez-vous plutôt vous mettre en Option ?",
						type: "info",
						showCancelButton: true,
						confirmButtonColor: "#007AFF",
						confirmButtonText: "Non",
						cancelButtonText: "Oui",
						closeOnConfirm: false,
						closeOnCancel: false
					}, function(isConfirmOption) {
						// isConfirmOption vrai = clic sur "Non" (on reste en Inscrit)
						if (!isConfirmOption) {
							// L'utilisateur choisit Option
							var optRadio = document.getElementById('reg_option');
							if (optRadio) {
								optRadio.checked = true;
								radio.checked = false;
								// On pose quand même les questions Anonyme / Latereg avant d'envoyer
								askAnonymeAndLatereg(optRadio);
							} else {
								// fallback : on reste sur le radio initial
								askAnonymeAndLatereg(radio);
							}
						} else {
							// On continue le flux normal Inscrit : Anonyme puis Latereg
							askAnonymeAndLatereg(radio);
						}
					});
				} else {
					radio.form.submit();
				}
			}
		</script>
		<!-- end: JavaScript Event Handlers for this page -->
		<!-- end: CLIP-TWO JAVASCRIPTS -->
		<script>
		  // Initialize reusable card background with defaults
		  window.CardBackground && window.CardBackground.init({
		    spacing: 60,
		    rowHeight: 80,
		    fontSize: 60,
		    opacity: 0.18,
		    alternateColors: true,
		    colors: { even: 'white', odd: 'red' },
		    suits: ['♠','♣','♥','♦'],
		    staggerCycle: 4
		  });
		</script>
	</body>

<<<<<<< Updated upstream
	</html>
<?php } ?>
=======

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
		<button id="nav-home" class="" title="Accueil" onclick="window.location.href='/panel/index.php';">
			<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M3 11.5L12 4l9 7.5"/><path d="M5 21h14a1 1 0 0 0 1-1v-7H4v7a1 1 0 0 0 1 1z"/></svg>
			<div class="nav-label">Accueil</div>
		</button>
		<button id="nav-local" class="active" title="Local Timer" onclick="window.location.href='/panel/quickview.php';">
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
>>>>>>> Stashed changes
