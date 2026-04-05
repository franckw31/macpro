<?php
session_start();
$displayUser = 'Visiteur';
// prefer the `user` session key (set by /index.php login flow)
if(!empty($_SESSION['user'])) $displayUser = $_SESSION['user'];
elseif(!empty($_SESSION['login'])) $displayUser = $_SESSION['login'];
elseif(!empty($_COOKIE['uname'])) $displayUser = $_COOKIE['uname'];
$displayUser = htmlspecialchars($displayUser);
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Timer Web</title>
  <!-- Theme stylesheet loader: variant is persisted in localStorage ('uiVariant') -->
  <link id="theme-stylesheet" rel="stylesheet" href="style.variantA.css">
  </head>
<body>
<div class="container">
  <header class="card header">
    <div style="display:flex;align-items:center;gap:12px">
      <div class="logo"><img src="assets/spade.svg" alt="logo" class="logo-svg"></div>
      <div>
        <div class="title">CardEvent <span class="small">v2.0</span></div>
        <!-- Variant switcher removed; Variant A is active by default -->
        <div id="activity-title" class="subtitle">Chargement activité...</div>
      </div>
    </div>
    <div style="display:flex;align-items:center;gap:12px;margin-left:auto">
      <div class="greeting">Bonjour, <span id="user-name"><?php echo $displayUser; ?></span></div>
      <div id="offline-badge" class="offline-badge" aria-hidden="true"></div>
      <div class="avatar"><img src="assets/avatar-real.svg" alt="avatar" style="width:100%;height:100%;object-fit:cover"></div>
    </div>
    <!-- Token prompt (hidden by default) -->
    <div id="token-prompt" class="token-prompt" style="display:none">
      <div style="font-weight:700;margin-bottom:6px">Connexion API</div>
      <input id="api-token-input" placeholder="Collez le token API" />
      <div style="display:flex;gap:8px;margin-top:8px">
        <button id="save-api-token" class="button primary">Enregistrer</button>
        <button id="clear-api-token" class="button">Effacer</button>
      </div>
      <pre id="debug-info" class="debug-info"></pre>
      <div class="small" style="margin-top:8px;color:var(--muted)">Le token est stocké en local</div>
    </div>
  </header>

  <section id="activity-card" class="card stroked">
    <div id="section-label" class="section-label"><img src="assets/cardevent.svg" alt="" style="width:18px;height:18px;margin-right:8px"><span id="section-label-text">PROCHAINE PARTIE / EN COURS</span></div>
    <div class="row" style="margin-top:8px">
      <div style="flex:1">
        <div id="activity-name" style="font-weight:800;font-size:20px">—</div>
        <div style="display:flex;align-items:center;gap:10px;margin-top:8px">
          <div class="date-pill"><svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M12 8v5l3 1" stroke="#FFB84D" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg></div>
          <div id="activity-date" class="small" style="color:var(--gold);font-weight:700">—</div>
        </div>
        <div style="margin-top:12px;display:flex;gap:12px;align-items:center">
          <div class="pill" id="buyin-pill"><svg width="18" height="18" viewBox="0 0 24 24" fill="none"><text x="3" y="16" font-size="16" fill="#2ECC71">$</text></svg> <span>—</span></div>
          <div class="pill" id="rake-pill"><svg width="18" height="18" viewBox="0 0 24 24" fill="none"><text x="3" y="16" font-size="16" fill="#FFB84D">%</text></svg> <span>—</span></div>
          <div class="pill" id="inscrits-pill"><svg width="18" height="18" viewBox="0 0 24 24" fill="none"><text x="2" y="16" font-size="16" fill="#B47BFF">👥</text></svg> <span>— inscrits</span></div>
        </div>
        <div style="margin-top:12px;color:#ff6b6b;font-weight:700">● Pas encore inscrit(e)</div>
      </div>
      <div style="width:64px;display:flex;flex-direction:column;gap:12px;align-items:center;justify-content:center">
        <button class="chev" id="next-act">›</button>
        <button class="chev" id="prev-act">‹</button>
      </div>
    </div>
  </section>

  <section class="card">
    <div class="shortcuts-grid">
      <div class="tile">
        <div class="tile-top"><div class="count" id="countdown">--:--</div><div class="count-label">Démarre dans</div></div>
        <div class="tile-bottom">Live Timer</div>
      </div>
      <div class="tile">
        <div class="tile-top"><div class="icon-circle info">i</div></div>
        <div class="tile-bottom">Détails Partie</div>
      </div>
      <div class="tile">
        <div class="tile-top"><div class="icon-circle profile">👤</div></div>
        <div class="tile-bottom">Mon Profil / Traker</div>
      </div>
      <div class="tile">
        <div class="tile-top"><div class="icon-circle people">👥</div></div>
        <div class="tile-bottom">Liste participants</div>
      </div>
    </div>
  </section>

  <section class="card stroked">
    <div style="font-weight:700;color:var(--gold);text-transform:uppercase;font-size:12px">Podium payés</div>
    <hr style="border:none;border-top:1px solid rgba(255,215,0,0.08);margin:8px 0">
    <div id="podium-list">
      <div class="small">Chargement...</div>
    </div>
  </section>

  <section class="card quick-action">
    <div class="quick-top"><div class="flash">⚡</div><div style="font-weight:700;color:var(--gold);text-transform:uppercase">Action rapide</div></div>
    <hr style="border:none;border-top:1px solid rgba(255,255,255,0.04);margin:8px 0">
    <div style="display:flex;align-items:center;justify-content:space-between">
      <div id="reg-text" style="font-weight:700;font-size:20px">Rejoindre la partie ?</div>
      <div>
        <button class="button primary" id="reg-action" style="padding:12px 18px;border-radius:12px">S'inscrire</button>
      </div>
    </div>
  </section>
  </div>
  <script src="app.js"></script>

  <nav class="bottom-nav" role="navigation" aria-label="Main">
    <button class="active" id="nav-home"><img src="assets/home.svg" aria-hidden="true"><span class="nav-label">Accueil</span></button>
    <button id="nav-local"><img src="assets/cardevent.svg" aria-hidden="true"><span class="nav-label">Local Timer</span></button>
    <button id="nav-prize"><img src="assets/euro.svg" aria-hidden="true"><span class="nav-label">Répartition</span></button>
  </nav>
</body>
</html>