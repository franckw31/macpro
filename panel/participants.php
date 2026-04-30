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
    .item{display:flex;align-items:center;justify-content:space-between;padding:8px 10px;border-bottom:1px solid rgba(255,255,255,0.03);line-height:1.15}
    .item .left{display:flex;align-items:center;gap:8px}
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
            return `<div class="item" role="listitem"><div class="left"><img class="p-avatar" src="${avatarSrc}" alt="" onerror="this.src='https://viendez.com/images/noprofil.jpg'"><div class="pseudo">${escapeHtml(pseudo)}</div></div><div style="display:flex;align-items:center;gap:12px"><div class="muted">${escapeHtml(date)}</div><div class="accent">${escapeHtml(jet)}</div></div></div>`;
        }).join('');
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
</body>
</html>

