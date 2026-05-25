<?php
session_start();
include __DIR__ . '/include/config.php'; // provides $con (mysqli)
// Enable debug output when ?debug=1 is present (development only)
if (isset($_GET['debug']) && $_GET['debug'] === '1'){
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
}
// asset version
$asset_ver = @filemtime(__DIR__ . '/timer_web/public/style.variantA.css') ?: time();

$activity = null;
$activity_id = null;
if (isset($_GET['uid']) && is_numeric($_GET['uid'])) {
    $activity_id = intval($_GET['uid']);
}

if (!empty($con)){
    if ($activity_id){
        $q = mysqli_query($con, "SELECT * FROM activite WHERE `id-activite` = '".intval($activity_id)."' LIMIT 1");
    } else {
        $q = mysqli_query($con, "SELECT * FROM activite WHERE date_depart >= NOW() ORDER BY date_depart ASC LIMIT 1");
    }
    if($q && mysqli_num_rows($q)>0) $activity = mysqli_fetch_assoc($q);
    if(!$activity && !$activity_id){
        $q2 = mysqli_query($con, "SELECT * FROM activite ORDER BY date_depart DESC LIMIT 1");
        if($q2 && mysqli_num_rows($q2)>0) $activity = mysqli_fetch_assoc($q2);
    }
}

$participants = array();
if($activity){
    $aid = intval($activity['id-activite']);
    if(!empty($con)){
        // DEBUG: find join column name in participation
        $col_check = mysqli_query($con, "SHOW COLUMNS FROM participation");
        $pcols = [];
        if($col_check) while($c=mysqli_fetch_assoc($col_check)) $pcols[]=$c['Field'];
        $join_col = in_array('id-membre',$pcols) ? '`id-membre`' : (in_array('id_membre',$pcols) ? '`id_membre`' : (in_array('membre_id',$pcols) ? '`membre_id`' : null));
        if(!$join_col){ /* fallback, try direct */ $join_col='`id-membre`'; }
        $pq = mysqli_query($con, "SELECT p.*, COALESCE(p.`jetons_bonus_ins`, p.`jetons`, 0) AS jetons, COALESCE(m.pseudo, '') AS pseudo, COALESCE(m.`photo`, '') AS photo, a.date_depart AS activity_date FROM participation p LEFT JOIN membres m ON m.`id-membre` = p.$join_col LEFT JOIN activite a ON a.`id-activite` = p.`id-activite` WHERE p.`id-activite` = '".$aid."' ORDER BY COALESCE(p.`ds`, a.`date_depart`) ASC");
        if($pq){
            while($row = mysqli_fetch_assoc($pq)){
                $participants[] = array(
                    'id' => isset($row['id-participation'])? $row['id-participation'] : (isset($row['id'])? $row['id'] : null),
                    'mid' => isset($row['id-membre'])? intval($row['id-membre']) : (isset($row['id_membre'])? intval($row['id_membre']) : null),
                    'pseudo' => isset($row['pseudo'])? $row['pseudo'] : '',
                    'option' => isset($row['option'])? $row['option'] : '',
                    'date' => isset($row['ds'])? $row['ds'] : (isset($row['activity_date'])? $row['activity_date'] : null),
                    'jetons' => isset($row['jetons'])? $row['jetons'] : 0,
                    'latereg' => isset($row['latereg']) ? $row['latereg'] : 0,
                    'anonyme' => isset($row['anonyme']) ? $row['anonyme'] : 0,
                    'photo_url' => (!empty($row['photo']) ? 'https://viendez.com/images/faces/' . rawurlencode(basename($row['photo'])) : '')
                );
            }
        }
    }
}

function h($s){ return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
$display_title = 'Participants';
if($activity){
    $display_title = (isset($activity['titre-activite'])? $activity['titre-activite'] : (isset($activity['titre_activite'])? $activity['titre_activite'] : (isset($activity['title'])? $activity['title'] : 'Participants')));
}
// determine max places from possible column names
$max_places = null;
if ($activity) {
    foreach (['places','max_places','max_participants','places_max','nb_places','places_total'] as $c) {
        if (isset($activity[$c]) && $activity[$c] !== '') { $max_places = intval($activity[$c]); break; }
    }
}
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?php echo h($display_title); ?> — Participants</title>
    <link rel="stylesheet" href="/panel/timer_web/public/style.variantA.css?v=<?php echo $asset_ver; ?>">
    <style>
    body{background:#071019;color:#eef6fb;font-family:Inter, system-ui, -apple-system, Arial; font-size:14px;}
    .page{max-width:720px;margin:10px auto;padding:12px}
    .header{display:flex;align-items:center;gap:12px}
    .title{font-weight:800;font-size:16px}
    .sub{color:var(--muted)}
    .controls{display:flex;gap:8px;align-items:center;margin-top:12px}
    .search{flex:1;padding:8px 10px;border-radius:10px;border:1px solid rgba(255,255,255,0.04);background:rgba(255,255,255,0.02);color:var(--white)}
    .btn{padding:8px 12px;border-radius:10px;background:linear-gradient(90deg,#2ecc71,#00d2ff);color:#041011;border:none;cursor:pointer}
    .list{margin-top:8px;border-radius:8px;overflow:hidden;background:rgba(255,255,255,0.02);border:1px solid rgba(255,255,255,0.04)}
    .item{display:flex;align-items:center;justify-content:space-between;padding:1px 10px;border-bottom:1px solid rgba(255,255,255,0.03);line-height:1.15}
    .item .left{display:flex;align-items:center;gap:12px}
    .p-avatar{width:34px;height:34px;border-radius:50%;object-fit:cover;border:1.5px solid rgba(255,255,255,0.12);flex-shrink:0;background:#1a2a35}
    .item .pseudo{font-weight:700; font-size:14px; color: var(--green, #2ecc71)}
    .muted{color:var(--muted)}
    .accent{color:var(--cyan);font-weight:800}
    </style>
</head>
<body>
<div class="page">
    <div class="header">
        
        <div style="flex:1">
            <div class="title"><?php echo h($display_title); ?></div>
            <?php if($activity && !empty($activity['date_depart'])): ?>
                <div class="sub"><?php echo h($activity['date_depart']); ?></div>
            <?php endif; ?>
                <div class="muted">
                <span id="participants-count" style="color:#ff7a45;font-weight:700"><?php echo count($participants); ?></span> inscrits
                <?php if(!is_null($max_places)){ ?> sur <span style="color:#ff7a45;font-weight:700"><?php echo intval($max_places); ?></span> max<?php } ?>
            </div>
        </div>
        <a href="/panel/quickview.php" class="button ghost" style="color:#ff7a45;text-decoration:none;font-weight:700">Fermer</a>
    </div>

    <div class="controls">
        <input id="search" class="search" placeholder="Rechercher (nom)" />
        <select id="sort" style="padding:8px;border-radius:10px;background:rgba(255,255,255,0.02);border:1px solid rgba(255,255,255,0.04);color:var(--white)">
            <option value="idx">Ordre</option>
            <option value="name" selected>Nom</option>
            <option value="date">Date</option>
            <option value="jetons">Jetons</option>
        </select>
    </div>

    <div id="list" class="list" role="list" aria-label="Liste des participants">
        <!-- rendered by JS -->
    </div>
</div>

<script>
window.PAGE_PARTICIPANTS = <?php echo json_encode($participants, JSON_UNESCAPED_UNICODE); ?>;
(function(){
    const listEl = document.getElementById('list');
    const search = document.getElementById('search');
    const sort = document.getElementById('sort');
    let data = window.PAGE_PARTICIPANTS || [];

    function render(arr){
        if(!arr || !arr.length){ listEl.innerHTML = '<div class="item small">Aucun participant</div>'; return; }
        listEl.innerHTML = arr.map((p,i)=>{
            // skip participants marked as Desinscrit
            const optVal = (p.option||'').toString();
            if(optVal === 'Desinscrit') return '';
            const date = p.date? p.date : '';
            const formattedDate = formatShortDate(date);
            const jet = p.jetons? ('+'+p.jetons) : '';
            // build pseudo with suffixes
            let pseudo = p.pseudo || '(inconnu)';
            // if participant chose anonymity, hide pseudo
            if(p.anonyme && String(p.anonyme) === '1') pseudo = 'Anonyme';
            const suffix = [];
            if(optVal === 'Option') suffix.push('(Opt)');
            if(p.latereg && String(p.latereg) === '1') suffix.push('(Late)');
            if(suffix.length) pseudo += ' ' + suffix.join(' ');
            const avatarSrc = (p.photo_url && String(p.anonyme) !== '1') ? p.photo_url : 'https://viendez.com/images/noprofil.jpg';
            const trakBtn = (String(p.anonyme) !== '1') ? `<button onclick="openTrak('${escapeHtml(p.pseudo||'')}')" style="background:none;border:0;cursor:pointer;font-size:16px;padding:0 4px" title="Notes Trak">📝</button>` : '';
            const pseudoEl = (p.mid && String(p.anonyme) !== '1')
                ? `<a href="/panel/sergio.php?mid=${p.mid}" style="font-weight:700;font-size:14px;color:var(--green,#2ecc71);text-decoration:none">${escapeHtml(pseudo)}</a>`
                : `<div class="pseudo">${escapeHtml(pseudo)}</div>`;
            return `<div class="item" role="listitem"><div class="left"><img class="p-avatar" src="${avatarSrc}" alt="" onerror="this.src='https://viendez.com/images/noprofil.jpg'">${pseudoEl}</div><div style="display:flex;align-items:center;gap:8px"><div class="muted">${escapeHtml(formattedDate)}</div><div class="accent">${escapeHtml(jet)}</div>${trakBtn}</div></div>`;
        }).join('');
    }

        function formatShortDate(ds){
          if(!ds) return '';
          // Accept "YYYY-MM-DD HH:MM:SS" or ISO; convert space to T for Date parsing
          var d = new Date(String(ds).replace(' ', 'T'));
          if (isNaN(d)) return ds;
          var day = d.getDate();
          var month = d.getMonth() + 1;
          var hour = d.getHours();
          var minute = String(d.getMinutes()).padStart(2,'0');
          return day + '-' + month + ' ' + hour + 'h' + minute;
        }

    function escapeHtml(s){ if(!s) return ''; return String(s).replace(/[&<>\"]/g, function(c){ return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]; }); }

    function applyFilters(){
        const q = (search.value||'').trim().toLowerCase();
        let out = data.slice();
        if(q){ out = out.filter(p=> (p.pseudo||'').toLowerCase().indexOf(q) !== -1); }
        // remove Desinscrit from display
        out = out.filter(p => ((p.option||'') !== 'Desinscrit'));
        const s = sort.value;
        if(s === 'name') out.sort((a,b)=> String(a.pseudo||'').localeCompare(String(b.pseudo||'')) );
        else if(s === 'date') out.sort((a,b)=> String(a.date||'').localeCompare(String(b.date||'')) );
        else if(s === 'jetons') out.sort((a,b)=> (Number(b.jetons||0) - Number(a.jetons||0)) );
        // update visible count
        try{ var cntEl = document.getElementById('participants-count'); if(cntEl) cntEl.textContent = out.length; }catch(e){}
        render(out);
    }

    search.addEventListener('input', applyFilters);
    sort.addEventListener('change', applyFilters);

    // Export CSV removed by user request

    // initial render (apply default filters/sort)
    applyFilters();
})();
</script>

<!-- ══════════ TRAK MODAL ══════════ -->
<div id="trak-overlay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.6);z-index:999;align-items:flex-end;justify-content:center" onclick="if(event.target===this)closeTrak()">
  <div style="background:#0d1f2d;border-radius:20px 20px 0 0;width:100%;max-width:600px;max-height:85vh;display:flex;flex-direction:column;padding-bottom:env(safe-area-inset-bottom)">
    <div style="width:40px;height:4px;background:rgba(255,255,255,0.2);border-radius:2px;margin:10px auto 0"></div>
    <div style="display:flex;align-items:center;justify-content:space-between;padding:12px 16px 4px">
      <div id="trak-title" style="font-weight:800;font-size:16px">Notes – joueur</div>
      <button onclick="closeTrak()" style="background:rgba(255,255,255,0.06);padding:6px 14px;border-radius:20px;border:0;color:#ff9d3b;font-weight:700;font-size:14px;cursor:pointer">Fermer</button>
    </div>
    <div style="display:flex;gap:8px;padding:8px 16px 4px">
      <button id="trak-btn-ecrites" onclick="trakSetMode('auteur')" style="flex:1;padding:7px;border-radius:10px;border:0;font-weight:700;font-size:13px;cursor:pointer;background:#17a34a;color:#fff">✏️ Écrites</button>
      <button id="trak-btn-recues"  onclick="trakSetMode('cible')"  style="flex:1;padding:7px;border-radius:10px;border:0;font-weight:700;font-size:13px;cursor:pointer;background:rgba(255,255,255,0.07);color:#8fa0b0">📥 Reçues</button>
    </div>
    <div id="trak-list" style="flex:1;overflow-y:auto;padding:8px 16px;min-height:80px;color:#eef6fb"></div>
    <div style="padding:10px 14px;border-top:1px solid rgba(255,255,255,0.07);display:flex;gap:8px;align-items:flex-end">
      <textarea id="trak-input" placeholder="Ajouter une note…" rows="2" style="flex:1;padding:10px 12px;border-radius:10px;border:1px solid rgba(255,255,255,0.1);background:rgba(255,255,255,0.04);color:#eef6fb;font-size:14px;resize:none;font-family:inherit"></textarea>
      <button onclick="trakSend()" style="padding:10px 14px;border-radius:10px;background:#17a34a;color:#fff;font-weight:700;border:0;cursor:pointer;font-size:18px">➤</button>
    </div>
  </div>
</div>

<script>
var trakS = { pseudo:'', actId:<?php echo intval($activity_id ?: ($activity['id-activite'] ?? 0)); ?>, mode:'auteur', notes:[], myId:<?php echo intval($_SESSION['id'] ?? 0); ?>, token:'<?php echo addslashes($_SESSION["token"] ?? ""); ?>' };

function openTrak(pseudo) {
  trakS.pseudo = pseudo;
  trakS.mode = 'auteur';
  document.getElementById('trak-title').textContent = 'Notes – ' + pseudo;
  document.getElementById('trak-input').value = '';
  document.getElementById('trak-overlay').style.display = 'flex';
  trakSetMode('auteur');
  trakLoad();
}
function closeTrak() { document.getElementById('trak-overlay').style.display = 'none'; }

function trakSetMode(mode) {
  trakS.mode = mode;
  var bE = document.getElementById('trak-btn-ecrites'), bR = document.getElementById('trak-btn-recues');
  bE.style.background = mode==='auteur'?'#17a34a':'rgba(255,255,255,0.07)'; bE.style.color = mode==='auteur'?'#fff':'#8fa0b0';
  bR.style.background = mode==='cible' ?'#17a34a':'rgba(255,255,255,0.07)'; bR.style.color = mode==='cible' ?'#fff':'#8fa0b0';
  trakRenderNotes();
}

function trakLoad() {
  document.getElementById('trak-list').innerHTML = '<div style="text-align:center;padding:20px;color:#8fa0b0">Chargement…</div>';
  fetch('/api/trak-notes.php?pseudo='+encodeURIComponent(trakS.pseudo), { credentials:'include', headers:{'Authorization':'Bearer '+trakS.token} })
  .then(r=>r.json()).then(d => {
    trakS.notes = d.success ? (d.notes||[]) : [];
    trakS.idCible = d.id_cible || 0;
    trakRenderNotes();
  }).catch(()=>{ document.getElementById('trak-list').innerHTML='<div style="color:#ff6b6b;padding:12px">Erreur réseau</div>'; });
}

function trakRenderNotes() {
  var mode = trakS.mode, myId = trakS.myId;
  var notes = trakS.notes.filter(n => mode==='auteur' ? n.id_auteur===myId : n.id_cible===myId);
  if (!notes.length) { document.getElementById('trak-list').innerHTML = '<div style="color:#8fa0b0;padding:12px;text-align:center">'+(trakS.notes.length?'Aucun résultat':'Aucune note pour ce joueur')+'</div>'; return; }
  document.getElementById('trak-list').innerHTML = notes.map(n => {
    var dp = mode==='auteur' ? n.cible_pseudo : n.auteur_pseudo;
    var al = n.date_activite ? n.date_activite+(n.titre_activite?' — '+n.titre_activite:'') : n.titre_activite;
    var canDel = n.id_auteur===myId;
    return '<div style="padding:10px 0;border-bottom:1px solid rgba(255,255,255,0.05)">'
      +'<div style="display:flex;justify-content:space-between;margin-bottom:4px">'
        +'<span style="font-size:12px;font-weight:700;color:#4dd0e1">'+tEsc(dp)+'</span>'
        +'<div style="display:flex;gap:8px;align-items:center">'
          +'<span style="font-size:11px;color:#8fa0b0">'+tFmt(n.created_at)+'</span>'
          +(canDel?'<button onclick="trakDel('+n.id+')" style="background:none;border:0;color:#ff6b6b;cursor:pointer;font-size:13px;padding:0">🗑</button>':'')
        +'</div>'
      +'</div>'
      +'<div style="font-size:14px;line-height:1.5">'+tEsc(n.note)+'</div>'
      +(al?'<div style="font-size:11px;color:#8fa0b0;margin-top:4px">📅 '+tEsc(al)+'</div>':'')
    +'</div>';
  }).join('');
}

function trakSend() {
  var text=(document.getElementById('trak-input').value||'').trim(); if(!text)return;
  fetch('/api/trak-notes.php',{method:'POST',credentials:'include',headers:{'Content-Type':'application/json','Authorization':'Bearer '+trakS.token},
    body:JSON.stringify({action:'add',pseudo_cible:trakS.pseudo,note:text,id_activite:trakS.actId})})
  .then(r=>r.json()).then(d=>{ if(d.success&&d.note){ trakS.notes.unshift(d.note); document.getElementById('trak-input').value=''; trakSetMode('auteur'); trakRenderNotes(); } });
}

function trakDel(id) {
  if(!confirm('Supprimer ?')) return;
  fetch('/api/trak-notes.php',{method:'POST',credentials:'include',headers:{'Content-Type':'application/json','Authorization':'Bearer '+trakS.token},
    body:JSON.stringify({action:'delete',id:id})})
  .then(r=>r.json()).then(d=>{ if(d.success){ trakS.notes=trakS.notes.filter(n=>n.id!==id); trakRenderNotes(); } });
}

function tFmt(s){ if(!s)return''; var d=new Date(s.replace(' ','T')); if(isNaN(d))return s; return('0'+d.getDate()).slice(-2)+'/'+('0'+(d.getMonth()+1)).slice(-2)+'/'+String(d.getFullYear()).slice(-2)+' '+('0'+d.getHours()).slice(-2)+':'+('0'+d.getMinutes()).slice(-2); }
function tEsc(s){ if(!s)return''; return String(s).replace(/[&<>"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c])); }
</script>
</body>
</html>

