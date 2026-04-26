<?php
session_start();
include __DIR__ . '/include/config.php'; // provides $con (mysqli)
// Debug flag
if (isset($_GET['debug']) && $_GET['debug'] === '1'){
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
}

$asset_ver = @filemtime(__DIR__ . '/timer_web/public/style.variantA.css') ?: time();

$activity = null;
$activity_id = null;
if (isset($_GET['uid']) && is_numeric($_GET['uid'])) $activity_id = intval($_GET['uid']);

if (!empty($con)){
    if ($activity_id){
        $q = mysqli_query($con, "SELECT * FROM activite WHERE `id-activite` = '".intval($activity_id)."' LIMIT 1");
    } else {
        // default: latest past activity
        $q = mysqli_query($con, "SELECT * FROM activite WHERE date_depart <= NOW() ORDER BY date_depart DESC LIMIT 1");
    }
    if($q && mysqli_num_rows($q)>0) $activity = mysqli_fetch_assoc($q);
}

$rows = [];
$winners_count = 0;
$total_count = 0;
// determine max places from possible column names (reuse participants.php logic)
$max_places = null;
if($activity){
    $aid = intval($activity['id-activite']);
    if(!empty($con)){
        // select raw participation rows and compute gains/server-side fallbacks in PHP
        $sql = "SELECT p.*, COALESCE(m.pseudo, '') AS pseudo FROM participation p LEFT JOIN membres m ON m.`id-membre` = p.`id-membre` WHERE p.`id-activite` = '".$aid."'";
        $pq = mysqli_query($con, $sql);
        if($pq){
            while($r = mysqli_fetch_assoc($pq)){
                // compute gains from multiple possible column names without referencing them in SQL
                $gains = 0.0;
                foreach(['gains','gain','prize','prix','winnings','amount','montant'] as $col){
                    if(isset($r[$col]) && $r[$col] !== ''){ $gains = (float)$r[$col]; break; }
                }

                // place / challenge / recave / bounty fallbacks
                $place = null;
                foreach(['classement','place','position','rank','rang'] as $c){ if(isset($r[$c]) && $r[$c] !== '' && intval($r[$c]) > 0){ $place = $r[$c]; break; } }
                $challenge = '';
                foreach(['challenge','chall','numero','ticket'] as $c){ if(isset($r[$c]) && $r[$c] !== ''){ $challenge = $r[$c]; break; } }
                $recave = 0; foreach(['recave','rebuys','r'] as $c){ if(isset($r[$c]) && $r[$c] !== ''){ $recave = (int)$r[$c]; break; } }
                $bounty = 0; foreach(['bounty','bounties'] as $c){ if(isset($r[$c]) && $r[$c] !== ''){ $bounty = (int)$r[$c]; break; } }

                    // If eliminations table exists, compute bounty as number of eliminations by this pseudo for the activity
                    $elimCount = 0;
                    $name_for_elim = null;
                    foreach(['pseudo','nom-membre','nom_membre','name','pseudo_membre'] as $c){ if(isset($r[$c]) && $r[$c] !== ''){ $name_for_elim = $r[$c]; break; } }
                    if($name_for_elim && !empty($con)){
                        $esc = mysqli_real_escape_string($con, $name_for_elim);
                        $eq = "SELECT COUNT(*) AS cnt FROM `eliminations` e JOIN `participation` p2 ON e.`id_participation` = p2.`id-participation` WHERE p2.`id-activite` = '".$aid."' AND e.`nom_membre` = '".$esc."'";
                        $erc = @mysqli_query($con, $eq);
                        if($erc && ($erow = mysqli_fetch_assoc($erc))){ $elimCount = intval($erow['cnt']); }
                        if($elimCount > 0) $bounty = $elimCount;
                    }

                $rows[] = [
                    'id' => isset($r['id-participation'])? $r['id-participation'] : (isset($r['id'])? $r['id'] : null),
                    'pseudo' => $r['pseudo'] ?? '',
                    'place' => $place,
                    'challenge' => $challenge,
                    'recave' => $recave,
                    'bounty' => $bounty,
                    'gains' => $gains,
                ];
            }

            // sort full list: place asc (if present), else by gains desc, then pseudo
            usort($rows, function($a,$b){
                $pa = $a['place']; $pb = $b['place'];
                $hasA = ($pa !== null && $pa !== '');
                $hasB = ($pb !== null && $pb !== '');
                if($hasA && $hasB){
                    if((int)$pa !== (int)$pb) return ((int)$pa < (int)$pb)? -1 : 1;
                } else if($hasA && !$hasB){
                    return -1;
                } else if($hasB && !$hasA){
                    return 1;
                }
                // fallback to gains desc
                if($a['gains'] != $b['gains']) return ($b['gains'] <=> $a['gains']);
                // final tie-breaker: pseudo
                return strcmp($a['pseudo'] ?? '', $b['pseudo'] ?? '');
            });

            $total_count = count($rows);
            $winners_count = count(array_filter($rows, function($r){ return ($r['gains'] ?? 0) > 0; }));
        }
    }
}

function h($s){ return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
$title = 'Résultats';
if($activity){
    $title = isset($activity['titre-activite'])? $activity['titre-activite'] : (isset($activity['titre_activite'])? $activity['titre_activite'] : $title);
}
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?php echo h($title); ?> — Résultats</title>
    <link rel="stylesheet" href="/panel/timer_web/public/style.variantA.css?v=<?php echo $asset_ver; ?>">
     <style>
     :root{--muted:#8b98a6;--gold:#ffb400;--orange:#ff7a45;--green:#18b041;--purple:#9b59ff;--blue:#00b6ff}
     body{background:#071019;color:#eef6fb;font-family:Inter, system-ui, -apple-system, Arial; font-size:14px}
     .page{max-width:720px;margin:10px auto;padding:12px;position:relative}
     .header{display:flex;align-items:center;gap:12px}
     .title{font-weight:800;font-size:16px}
     .sub{color:var(--muted)}
     .btn-left{color:var(--orange);font-weight:700;text-decoration:none}
     .close-btn{position:absolute;top:12px;right:12px;padding:6px 10px;border-radius:8px;background:transparent;border:1px solid rgba(255,255,255,0.04);color:var(--orange);text-decoration:none;font-weight:700}
    .table{margin-top:12px;border-top:1px solid rgba(255,255,255,0.02);overflow-x:auto;-webkit-overflow-scrolling:touch}

    /* configurable column sizes */
    :root{--col-num:56px;--col-recave:36px;--col-bounty:36px;--col-gains:64px}

    /* use CSS grid for consistent column alignment */
    /* cap the pseudo column max so it doesn't push Gains too far right */
    .table .row{display:grid;grid-template-columns:var(--col-num) minmax(80px,260px) var(--col-bounty) var(--col-recave) var(--col-gains);grid-column-gap:6px;align-items:center;padding:8px 6px;border-bottom:1px solid rgba(255,255,255,0.02);line-height:1.15}
    .row.header-row{font-size:13px;color:var(--muted);border-bottom:1px solid rgba(255,255,255,0.06);background:rgba(255,255,255,0.01);font-weight:700}

    /* column alignment */
    .col-num{color:var(--muted);font-weight:700;text-align:center}
    .col-pseudo{font-weight:700;color:var(--green);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;padding-left:6px}
    /* header should show full label even if column is narrow */
    .row.header-row > .col-pseudo{white-space:normal;overflow:visible;text-overflow:none;color:var(--muted);font-weight:700}
    .col-recave{display:flex;justify-content:center;color:var(--orange)}
    .col-bounty{display:flex;justify-content:center;color:var(--purple)}
    .col-gains{display:flex;justify-content:flex-end;color:var(--green);font-weight:800}

    /* header-specific alignment tweaks */
    .row.header-row > .col-num{justify-self:center}
    .row.header-row > .col-pseudo{justify-self:start;padding-left:6px}
    .row.header-row > .col-gains{justify-self:end}
    .row.header-row > .col-bounty{justify-self:end;padding-right:6px;text-align:right}
    .row.header-row > .col-recave{justify-self:end;text-align:right}

     .rank-1{color:var(--gold)}
     .rank-top3{color:var(--orange)}
     .pseudo-highlight{color:var(--blue)}
     .small-muted{color:var(--muted);font-size:13px}

    @media (max-width:520px){
        .page{padding:8px;margin:6px;padding-right:12px}
        /* ensure pseudo column remains visible on very small screens; cap its width */
        .row{grid-template-columns:40px minmax(80px,160px) 32px 28px 60px;padding:8px}
        .col-pseudo{white-space:normal;line-height:1.1}
        .title{font-size:15px}
    }
     </style>
</head>
<body>
<div class="page" role="main">
    <a href="/panel/cardevent.php" class="close-btn">Fermer</a>
    <div class="header">
        <div style="flex:1">
            <div class="title"><?php echo h($title); ?></div>
            <?php if($activity && !empty($activity['date_depart'])): ?>
                <div class="sub"><?php echo h($activity['date_depart']); ?></div>
            <?php endif; ?>
            <div class="muted">
                <span style="color:#ff7a45;font-weight:700"><?php echo intval($total_count); ?></span> inscrits
                <?php if(!is_null($max_places)){ ?> sur <span style="color:#ff7a45;font-weight:700"><?php echo intval($max_places); ?></span> max<?php } ?>
            </div>
        </div>
        
    </div>

    <div class="table" role="list" aria-label="Résultats">
        <?php if(empty($rows)): ?>
            <div class="row"><div class="col-pseudo">Aucun résultat disponible</div></div>
        <?php else: ?>
            <div class="row header-row" role="row">
                <div class="col-num">#</div>
                <div class="col-pseudo">Pseudo</div>
                <div class="col-bounty">Bounty</div>
                <div class="col-recave">Rebuy</div>
                <div class="col-gains">Gains</div>
            </div>
            <?php foreach($rows as $i=>$r):
                $rank = (isset($r['place']) && intval($r['place'])>0) ? intval($r['place']) : ($i+1);
                $rankClass = ($rank == 1)? 'rank-1' : (($rank<=3)? 'rank-top3' : '');
            ?>
                <div class="row" role="listitem">
                    <div class="col-num <?php echo $rankClass; ?>">#<?php echo h($rank); ?></div>
                    <div class="col-pseudo <?php echo ($i===0? 'pseudo-highlight':''); ?>"><?php echo h($r['pseudo']); ?></div>
                    <div class="col-bounty"><?php echo $r['bounty']? h($r['bounty']) : '-'; ?></div>
                    <div class="col-recave"><?php echo $r['recave']? h($r['recave']) : '-'; ?></div>
                    <div class="col-gains"><?php echo ($r['gains']>0)? number_format($r['gains'], 0, ',', ' ') . '€' : '-'; ?></div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
</body>
</html>
