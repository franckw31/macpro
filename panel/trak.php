<?php
session_start();
include __DIR__ . '/include/config.php';

if (empty($_SESSION['id'])) {
    header('Location: /panel/login.php');
    exit;
}

$my_id     = intval($_SESSION['id']);
$my_pseudo = $_SESSION['pseudo'] ?? '';

// Récupérer tous les membres pour la recherche
$membres = [];
if (!empty($con)) {
    $mq = mysqli_query($con, "SELECT `id-membre`, pseudo, photo FROM membres WHERE pseudo != '' ORDER BY pseudo ASC");
    if ($mq) while ($r = mysqli_fetch_assoc($mq)) $membres[] = $r;
}

$asset_ver = @filemtime(__DIR__ . '/timer_web/public/style.variantA.css') ?: time();
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title>Traker — Notes joueurs</title>
<link rel="stylesheet" href="/panel/timer_web/public/style.variantA.css?v=<?php echo $asset_ver; ?>">
<style>
*{box-sizing:border-box;-webkit-tap-highlight-color:transparent}
body{background:#071019;color:#eef6fb;font-family:Inter,system-ui,-apple-system,Arial;font-size:14px;margin:0;padding:0;min-height:100dvh}

/* ── Header ── */
.hdr{display:flex;align-items:center;gap:12px;padding:16px 16px 10px;padding-top:max(16px,env(safe-area-inset-top,16px))}
.hdr-back{background:rgba(255,255,255,0.06);border:0;color:#ff9d3b;font-weight:700;font-size:14px;padding:7px 14px;border-radius:20px;cursor:pointer}
.hdr-title{flex:1;font-weight:800;font-size:18px}

/* ── Search ── */
.search-wrap{padding:0 16px 12px}
.search-box{width:100%;padding:10px 14px 10px 38px;border-radius:12px;border:1px solid rgba(255,255,255,0.08);background:rgba(255,255,255,0.04);color:#eef6fb;font-size:14px;outline:none;font-family:inherit;text-align:center}

/* Centrer le placeholder dans tous les navigateurs */
.search-box::placeholder {
    text-align: center;
}
.search-box::-webkit-input-placeholder {
    text-align: center;
}
.search-box:-moz-placeholder {
    text-align: center;
}
.search-box::-moz-placeholder {
    text-align: center;
}
.search-box:-ms-input-placeholder {
    text-align: center;
}
.search-icon{position:absolute;left:26px;top:50%;transform:translateY(-50%);color:#8fa0b0;font-size:15px;pointer-events:none}
.search-rel{position:relative}
.suggestions{position:absolute;top:calc(100% + 4px);left:0;right:0;background:#0d1f2d;border:1px solid rgba(255,255,255,0.1);border-radius:12px;z-index:100;max-height:220px;overflow-y:auto;display:none}
.suggestions.open{display:block}
.sug-item{display:flex;align-items:center;gap:10px;padding:10px 14px;cursor:pointer;border-bottom:1px solid rgba(255,255,255,0.04)}
.sug-item:last-child{border-bottom:0}
.sug-item:hover,.sug-item:active{background:rgba(255,255,255,0.05)}
.sug-avatar{width:30px;height:30px;border-radius:50%;object-fit:cover;border:1.5px solid rgba(255,255,255,0.1);flex-shrink:0}
.sug-pseudo{font-weight:600;font-size:13px}

/* ── Player header ── */
.player-hdr{display:flex;align-items:center;gap:14px;padding:14px 16px;background:rgba(255,255,255,0.03);border-bottom:1px solid rgba(255,255,255,0.06);display:none}
.player-hdr.visible{display:flex}
.player-avatar{width:48px;height:48px;border-radius:50%;object-fit:cover;border:2px solid rgba(255,255,255,0.15)}
.player-name{font-weight:800;font-size:16px}
.player-sub{font-size:12px;color:#8fa0b0;margin-top:2px}

/* ── Tabs ── */
.tabs{display:flex;gap:8px;padding:10px 16px}
.tab-btn{flex:1;padding:9px;border-radius:10px;border:0;font-weight:700;font-size:13px;cursor:pointer;transition:all .2s}
.tab-btn.active{background:#17a34a;color:#fff}
.tab-btn.inactive{background:rgba(255,255,255,0.06);color:#8fa0b0}

/* ── Notes list ── */
.notes-wrap{padding:0 16px;flex:1;overflow-y:auto}
.note-item{padding:12px 0;border-bottom:1px solid rgba(255,255,255,0.05)}
.note-item:last-child{border-bottom:0}
.note-meta{display:flex;justify-content:space-between;align-items:center;margin-bottom:6px}
.note-pseudo{font-size:12px;font-weight:700;color:#4dd0e1}
.note-date{font-size:11px;color:#8fa0b0}
.note-text{font-size:14px;line-height:1.55}
.note-act{font-size:11px;color:#8fa0b0;margin-top:5px}
.note-del{background:none;border:0;color:#ff6b6b;cursor:pointer;font-size:14px;padding:0 4px}
.empty-msg{text-align:center;color:#8fa0b0;padding:32px 16px}

/* ── Add note ── */
.add-area{padding:10px 14px;border-top:1px solid rgba(255,255,255,0.07);display:flex;gap:8px;align-items:flex-end;background:#0d1f2d;margin-top:auto;padding-bottom:max(10px,env(safe-area-inset-bottom,10px))}
.add-textarea{flex:1;padding:10px 12px;border-radius:10px;border:1px solid rgba(255,255,255,0.1);background:rgba(255,255,255,0.04);color:#eef6fb;font-size:14px;resize:none;font-family:inherit;outline:none}
.add-btn{padding:10px 16px;border-radius:10px;background:#17a34a;color:#fff;font-weight:700;border:0;cursor:pointer;font-size:18px;align-self:flex-end}
.add-btn:disabled{opacity:.4;cursor:default}

.content-area{display:none}
.content-area.visible{display:flex;flex-direction:column;min-height:calc(100dvh - 160px)}

.spinner{text-align:center;padding:24px;color:#8fa0b0}
.spinner{text-align:center;padding:24px;color:#8fa0b0}

/* ── Responsive ── */
@media (max-width: 600px) {
    .hdr, .search-wrap, .player-hdr, .tabs, .notes-wrap, .add-area {
        padding-left: 6px !important;
        padding-right: 6px !important;
    }
    .player-avatar { width: 38px; height: 38px; }
    .player-name { font-size: 15px; }
    .player-sub { font-size: 11px; }
    .tab-btn { font-size: 12px; padding: 7px; }
    .note-item { padding: 8px 0; }
    .note-text { font-size: 13px; }
    .add-textarea { font-size: 13px; padding: 8px 8px; }
    .add-btn { font-size: 16px; padding: 8px 10px; }
}
@media (max-width: 400px) {
    .hdr-title { font-size: 15px; }
    .player-name { font-size: 13px; }
    .tab-btn { font-size: 11px; }
    .add-btn { font-size: 14px; }
}

@media (min-width: 700px) {
    body { font-size: 15px; }
    .content-area.visible { max-width: 520px; margin: 0 auto; border-radius: 18px; box-shadow: 0 2px 16px 0 #0002; background: #0d1f2d; }
    .hdr, .search-wrap, .player-hdr, .tabs, .notes-wrap, .add-area { max-width: 520px; margin-left: auto; margin-right: auto; }
}
</style>
</head>
<body>

<div class="hdr">
  <button class="hdr-back" onclick="window.location.href='/panel/quickview.php'">‹ Retour</button>
  <div class="hdr-title">📝 Traker</div>
</div>

<div class="search-wrap">
  <div class="search-rel">
    <span class="search-icon">🔍</span>
    <input class="search-box" id="search-input" placeholder="Choisir un joueur cible ici …" autocomplete="off">
    <div class="suggestions" id="suggestions"></div>
  </div>
</div>

<div class="player-hdr" id="player-hdr">
    <img class="player-avatar" id="player-avatar" src="https://viendez.com/images/noprofil.jpg" alt="">
    <div>
        <div style="display:flex;align-items:center;gap:8px;">
            <div class="player-name" id="player-name">—</div>
            <button id="reset-filter-btn" onclick="resetFilter()" style="display:none;margin-left:4px;background:rgba(255,255,255,0.08);border:0;color:#ff6b6b;font-size:15px;padding:2px 10px;border-radius:16px;cursor:pointer;">×</button>
        </div>
        <div class="player-sub" id="player-sub" style="text-align:center;width:100%">Sélectionnez un joueur</div>
    </div>
</div>

<!-- raw API debug removed -->


<div class="content-area" id="content-area">
    <div class="tabs">
        <?php if ($my_id === 2 || $my_id === 265): ?>
            <button class="tab-btn active" id="tab-ecrites" onclick="setTab('auteur')">✏️ Écrites</button>
            <button class="tab-btn inactive" id="tab-recues"  onclick="setTab('cible')">📥 Reçues</button>
        <?php endif; ?>
    </div>
    <div class="notes-wrap" id="notes-wrap">
        <div class="spinner">Chargement…</div>
    </div>
    <div class="add-area">
        <textarea class="add-textarea" id="note-input" placeholder="Ajouter une note…" rows="2"></textarea>
        <button class="add-btn" id="send-btn" onclick="sendNote()">➤</button>
    </div>
</div>

<script>
// ── Search ──
var searchEl  = document.getElementById('search-input');
var sugEl     = document.getElementById('suggestions');

// Supprimer le placeholder quand le champ est focus
searchEl.addEventListener('focus', function() {
    this.dataset.placeholder = this.getAttribute('placeholder');
    this.setAttribute('placeholder', '');
});
searchEl.addEventListener('blur', function() {
    this.setAttribute('placeholder', this.dataset.placeholder || 'Choisir un joueur cible ici …');
    // Si le champ correspond à un pseudo existant, activer le filtre joueur
    var val = this.value.trim();
    var m = membres.find(m=>m.pseudo.toLowerCase()===val.toLowerCase());
    if (m) selectPlayer(m.pseudo, m.photo || 'https://viendez.com/images/noprofil.jpg');
});
// Entrée dans le champ recherche = sélection pseudo si correspond
searchEl.addEventListener('keydown', function(e) {
    if (e.key==='Enter') {
        var val = this.value.trim();
        var m = membres.find(m=>m.pseudo.toLowerCase()===val.toLowerCase());
        if (m) {
            selectPlayer(m.pseudo, m.photo || 'https://viendez.com/images/noprofil.jpg');
            sugEl.classList.remove('open');
            e.preventDefault();
        }
    }
});
// Réinitialiser le filtre (retour à mes notes)
function resetFilter() {
    trak.allMode = true;
    trak.pseudo = '';
    document.getElementById('search-input').value = '';
    document.getElementById('player-hdr').classList.remove('visible');
    loadNotes();
}
var membres = <?php echo json_encode(array_map(function($m){
    return [
        'id'     => (int)$m['id-membre'],
        'pseudo' => $m['pseudo'],
        'photo'  => !empty($m['photo']) ? 'https://viendez.com/images/faces/' . rawurlencode(basename($m['photo'])) : ''
    ];
}, $membres), JSON_UNESCAPED_UNICODE); ?>;

var trak = {
    pseudo: '',
    mode: 'auteur',
    notes: [],
    myId: <?php echo $my_id; ?>,
    actId: 0,
    allMode: true, // true = vue "mes notes" (toutes), false = vue sur un joueur
    canSeeRecues: <?php echo ($my_id === 2 || $my_id === 265) ? 'true' : 'false'; ?>
};
// request sequencing to avoid race between initial all=1 load and later selection fetches
trak.requestId = 0;

// ── Search ──
var searchEl  = document.getElementById('search-input');
var sugEl     = document.getElementById('suggestions');

searchEl.addEventListener('input', function(){
    var q = this.value.trim().toLowerCase();
    if (!q) { sugEl.innerHTML=''; sugEl.classList.remove('open'); return; }
    var matches = membres.filter(m => m.pseudo.toLowerCase().includes(q)).slice(0,10);
    if (!matches.length) { sugEl.innerHTML=''; sugEl.classList.remove('open'); return; }
    sugEl.innerHTML = matches.map(m =>
        '<div class="sug-item" onclick="selectPlayer(\''+escH(m.pseudo)+'\',\''+escH(m.photo || 'https://viendez.com/images/noprofil.jpg')+'\')">' +
        '<img class="sug-avatar" src="'+(m.photo||'https://viendez.com/images/noprofil.jpg')+'" onerror="this.src=\'https://viendez.com/images/noprofil.jpg\'">' +
        '<span class="sug-pseudo">'+escH(m.pseudo)+'</span></div>'
    ).join('');
    sugEl.classList.add('open');
});

document.addEventListener('click', function(e){
    if (!sugEl.contains(e.target) && e.target !== searchEl) sugEl.classList.remove('open');
});

function selectPlayer(pseudo, photo) {
        // selection utilisateur
    trak.pseudo = pseudo;
    trak.mode = 'auteur';
    trak.allMode = false;
    // Ne pas vider le champ si déjà rempli (pour reload)
    if (searchEl.value !== pseudo) searchEl.value = pseudo;
    sugEl.classList.remove('open');

    // Update player header
    document.getElementById('player-avatar').src = photo || 'https://viendez.com/images/noprofil.jpg';
    document.getElementById('player-name').textContent = pseudo;
    document.getElementById('player-sub').textContent = 'Notes sur ' + pseudo;
    document.getElementById('player-hdr').classList.add('visible');
    document.getElementById('content-area').classList.add('visible');
    document.getElementById('reset-filter-btn').style.display = '';

    setTab('auteur');
    // Force fetch by id_cible to avoid any race with initial load
    var msel = membres.find(m=>m.pseudo===pseudo);
    var url2 = msel && msel.id ? ('/api/trak-notes.php?id_cible=' + encodeURIComponent(msel.id)) : ('/api/trak-notes.php?pseudo=' + encodeURIComponent(pseudo));
    // mark pending selection so initial load does not overwrite
    trak.pendingSelection = true;
    var reqIdSel = ++trak.requestId;
    // Clear current notes and show spinner to avoid displaying stale results
    trak.notes = [];
    var nw = document.getElementById('notes-wrap'); if (nw) nw.innerHTML = '<div class="spinner">Chargement…</div>';
    fetch(url2, { credentials:'include' })
    .then(r=>r.json()).then(d=>{
        if (reqIdSel !== trak.requestId) return; // stale response
        trak.pendingSelection = false;
        trak.notes = d.success ? (d.notes||[]) : [];
        renderNotes();
    }).catch(()=>{ document.getElementById('notes-wrap').innerHTML='<div class="empty-msg">Erreur réseau</div>'; });
}

// ── Tabs ──
function setTab(mode) {
    trak.mode = mode;
    document.getElementById('tab-ecrites').className = 'tab-btn ' + (mode==='auteur'?'active':'inactive');
    if (trak.canSeeRecues && document.getElementById('tab-recues')) {
        document.getElementById('tab-recues').className  = 'tab-btn ' + (mode==='cible' ?'active':'inactive');
    }
    renderNotes();
}

// ── Load ──
function loadNotes() {
    document.getElementById('notes-wrap').innerHTML = '<div class="spinner">Chargement…</div>';
    var url;
    // Si un pseudo est sélectionné on l'utilise en priorité (id_cible si possible)
    if (trak.pseudo) {
        var m = membres.find(m=>m.pseudo===trak.pseudo);
        if (m && m.id) {
            url = '/api/trak-notes.php?id_cible=' + encodeURIComponent(m.id);
        } else {
            url = '/api/trak-notes.php?pseudo=' + encodeURIComponent(trak.pseudo);
        }
    } else if (trak.allMode) {
        url = '/api/trak-notes.php?all=1';
    } else {
        url = '/api/trak-notes.php?all=1';
    }
    var reqId = ++trak.requestId;
    fetch(url, { credentials:'include' })
    .then(r=>r.json()).then(d=>{
        if (reqId !== trak.requestId) return; // stale response
        if (trak.pendingSelection) return; // don't overwrite selection-in-progress
        // debug display removed

        trak.notes = d.success ? (d.notes||[]) : [];
        
        // --- FILTRAGE STRICT CÔTÉ CLIENT ---
        if (!trak.allMode && trak.pseudo) {
            var mSelClient = membres.find(m=>m.pseudo.toLowerCase()===trak.pseudo.toLowerCase());
            var idSelClient = mSelClient ? mSelClient.id : null;
            
            if (idSelClient) {
                trak.notes = trak.notes.filter(n => {
                    var id_a = parseInt(n.id_auteur);
                    var id_c = parseInt(n.id_cible);
                    var my_id_int = parseInt(trak.myId);
                    
                    // Si Admin : voit tout ce qui concerne le joueur (émis OU reçu par lui)
                    if (trak.canSeeRecues) {
                        return (id_a === idSelClient || id_c === idSelClient);
                    } 
                    // Si Joueur Normal : ne voit que ses PROPRES notes vers ce joueur
                    else {
                        return (id_a === my_id_int && id_c === idSelClient);
                    }
                });
            } else {
                trak.notes = [];
            }
        }

        if (trak.allMode) {
            document.getElementById('player-hdr').classList.remove('visible');
            document.getElementById('content-area').classList.add('visible');
            document.getElementById('player-name').textContent = 'Toutes les notes';
            document.getElementById('player-sub').textContent = trak.notes.length + ' note' + (trak.notes.length>1?'s':'') + ' chargées';
            document.getElementById('reset-filter-btn').style.display = 'none';
        } else {
            var m = membres.find(m=>m.pseudo===trak.pseudo);
            var cnt = trak.notes.length;
            document.getElementById('player-sub').textContent = cnt + ' note' + (cnt>1?'s':'') + ' au total';
            document.getElementById('reset-filter-btn').style.display = '';
        }
        renderNotes();
    }).catch(()=>{ document.getElementById('notes-wrap').innerHTML='<div class="empty-msg">Erreur réseau</div>'; });
}

// ── Render ──
function renderNotes() {
    var myId = parseInt(trak.myId);
    var notes = trak.notes; // Les notes sont déjà filtrées dans loadNotes()
    
    if (!notes.length) {
        document.getElementById('notes-wrap').innerHTML = '<div class="empty-msg">'+(trak.allMode ? 'Aucune note disponible.' : 'Aucune note pour ce joueur.')+'</div>';
        return;
    }
    
    document.getElementById('notes-wrap').innerHTML = notes.map(n => {
        var dp;
        var id_a = parseInt(n.id_auteur);
        var id_c = parseInt(n.id_cible);
        
        if (trak.allMode) {
            // Dans la vue globale, on affiche "De [Auteur] à [Cible]"
            dp = 'De ' + escH(n.auteur_pseudo) + ' à ' + escH(n.cible_pseudo);
        } else {
            // Dans la vue joueur, on affiche l'autre partie
            dp = (id_a === myId) ? n.cible_pseudo : n.auteur_pseudo;
        }
        
        var dpPhoto = '';
        var refPseudo = (id_a === myId) ? n.cible_pseudo : n.auteur_pseudo;
        var m_ref = membres.find(m=>m.pseudo===refPseudo);
        if(m_ref) dpPhoto = m_ref.photo || 'https://viendez.com/images/noprofil.jpg';
        
        var al = n.date_activite ? n.date_activite+(n.titre_activite?' — '+n.titre_activite:'') : n.titre_activite;
        var canDel = (id_a === myId);
        
        return '<div class="note-item">'
            +'<div class="note-meta">'
                +'<span class="note-pseudo" style="cursor:pointer;text-decoration:underline" onclick="selectPlayer(\''+escH(refPseudo)+'\',\''+escH(dpPhoto)+'\')">'+escH(dp)+'</span>'
                +'<div style="display:flex;align-items:center;gap:6px">'
                    +'<span class="note-date">'+fmtDate(n.created_at)+'</span>'
                    +(canDel?'<button class="note-del" onclick="delNote('+n.id+')">🗑</button>':'')
                +'</div>'
            +'</div>'
            +'<div class="note-text">'+escH(n.note)+'</div>'
            +(al?'<div class="note-act">📅 '+escH(al)+'</div>':'')
        +'</div>';
    }).join('');
}

// ── Send ──
function sendNote() {
    var text = (document.getElementById('note-input').value||'').trim();
    if (!text || !trak.pseudo) return;
    var btn = document.getElementById('send-btn');
    btn.disabled = true;
    fetch('/api/trak-notes.php', {
        method:'POST', credentials:'include',
        headers:{'Content-Type':'application/json'},
        body:JSON.stringify({action:'add', pseudo_cible:trak.pseudo, note:text, id_activite:trak.actId})
    }).then(r=>r.json()).then(d=>{
        if(d.success && d.note){
            // Instead of reloading, we just refresh the notes list
            document.getElementById('note-input').value = '';
            loadNotes();
            btn.disabled=false;
            return;
        }
        btn.disabled=false;
    }).catch(()=>{ btn.disabled=false; });
}

// ── Delete ──
function delNote(id) {
    if (!confirm('Supprimer cette note ?')) return;
    fetch('/api/trak-notes.php', {
        method:'POST', credentials:'include',
        headers:{'Content-Type':'application/json'},
        body:JSON.stringify({action:'delete', id:id})
    }).then(r=>r.json()).then(d=>{
        if(d.success){ trak.notes=trak.notes.filter(n=>n.id!==id); renderNotes(); }
    });
}

// ── Helpers ──
function fmtDate(s){ if(!s)return''; var d=new Date(s.replace(' ','T')); if(isNaN(d))return s; return('0'+d.getDate()).slice(-2)+'/'+('0'+(d.getMonth()+1)).slice(-2)+'/'+String(d.getFullYear()).slice(-2)+' '+('0'+d.getHours()).slice(-2)+':'+('0'+d.getMinutes()).slice(-2); }
function escH(s){ if(!s)return''; return String(s).replace(/[&<>"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c])); }

// Enter to send
document.getElementById('note-input').addEventListener('keydown', function(e){
    if(e.key==='Enter' && !e.shiftKey){ e.preventDefault(); sendNote(); }
});

// Par défaut : afficher toutes les notes du joueur connecté
(function(){
    // Eviter double chargement : marquer si on a déjà lancé loadNotes()
    var autoLoaded = false;
    // N'exécute l'auto-init que si aucune sélection utilisateur n'a eu lieu
    if (trak.pseudo) return;
    var params = new URLSearchParams(window.location.search);
    var p = params.get('pseudo');
    if (p) {
        var m = membres.find(m=>m.pseudo===p);
        if(m) {
            trak.pseudo = m.pseudo;
            trak.allMode = false;
            var searchEl = document.getElementById('search-input');
            if (searchEl) searchEl.value = m.pseudo;
            // Mettre à jour l'UI header joueur
            document.getElementById('player-avatar').src = m.photo || 'https://viendez.com/images/noprofil.jpg';
            document.getElementById('player-name').textContent = m.pseudo;
            document.getElementById('player-sub').textContent = 'Notes sur ' + m.pseudo;
            document.getElementById('player-hdr').classList.add('visible');
            document.getElementById('content-area').classList.add('visible');
            document.getElementById('reset-filter-btn').style.display = '';
            trak.mode = 'auteur';
            loadNotes();
            autoLoaded = true;
        } else {
            // si pseudo dans l'URL invalide, ne rien charger
            trak.allMode = true;
            trak.pseudo = '';
        }
    } else {
        trak.allMode = true;
        trak.pseudo = '';
    }
    // Charger par défaut les notes du membre connecté (vue "Mes notes")
    if (!autoLoaded) loadNotes();
})();
</script>
</body>
</html>
