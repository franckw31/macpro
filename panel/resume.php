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

                    // If eliminations table exists, compute bounty as number of eliminations by this player for the activity
                    // Use participation.nom-membre first (this is what player-action.php stores in eliminations.nom_membre)
                    $elimCount = 0;
                    $name_for_elim = (isset($r['nom-membre']) && $r['nom-membre'] !== '') ? $r['nom-membre'] : null;
                    if(!$name_for_elim){ foreach(['pseudo','nom_membre','name','pseudo_membre'] as $c){ if(isset($r[$c]) && $r[$c] !== ''){ $name_for_elim = $r[$c]; break; } } }
                    if($name_for_elim && !empty($con)){
                        $esc = mysqli_real_escape_string($con, $name_for_elim);
                        $eq = "SELECT COUNT(*) AS cnt FROM `eliminations` e WHERE e.`id_activite` = '".$aid."' AND e.`nom_membre` = '".$esc."'";
                        $erc = @mysqli_query($con, $eq);
                        if($erc && ($erow = mysqli_fetch_assoc($erc))){ $elimCount = intval($erow['cnt']); }
                        if($elimCount > 0) $bounty = $elimCount;
                    }

                $rows[] = [
                    'id' => isset($r['id-participation'])? $r['id-participation'] : (isset($r['id'])? $r['id'] : null),
                    'pseudo' => $r['pseudo'] ?? '',
                    'member_id' => isset($r['id-membre']) ? intval($r['id-membre']) : (isset($r['id_membre']) ? intval($r['id_membre']) : null),
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

            // keep a copy of full result set for lookups (so we can show summaries
            // for players not in the top 9)
            $all_rows = $rows;
            $total_count = count($all_rows);
            $winners_count = count(array_filter($all_rows, function($r){ return ($r['gains'] ?? 0) > 0; }));
            // Limit display to top 9 (table finale)
            $rows = array_slice($all_rows, 0, 9);
            // activity financial defaults for per-player calculations
            $activity_buyin = isset($activity['buyin'])? floatval($activity['buyin']) : 0.0;
            $activity_recave_montant = 0.0;
            if(isset($activity['recave_montant']) && $activity['recave_montant'] !== ''){
                $activity_recave_montant = floatval($activity['recave_montant']);
            } else {
                // fallback: use activity buyin as recave amount when specific recave amount missing
                $activity_recave_montant = $activity_buyin;
            }
            $activity_rake = isset($activity['rake'])? floatval($activity['rake']) : 0.0;
            // detect activity organizer id from possible column names
            $activity_organizer_id = null;
            foreach(['id-membre','id_membre','id_membres','id_membre_organisateur','organisateur','id-organisateur','id_organisateur'] as $c){
                if(isset($activity[$c]) && $activity[$c] !== ''){ $activity_organizer_id = intval($activity[$c]); break; }
            }
            // activity-level bounty amount (per elimination)
            $activity_bounty = 0.0;
            if(isset($activity['bounty']) && $activity['bounty'] !== ''){
                $activity_bounty = floatval($activity['bounty']);
            } else if(isset($activity['bounties']) && $activity['bounties'] !== ''){
                $activity_bounty = floatval($activity['bounties']);
            }
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

    /* summary grid (no benefit column) */
    .summary .row{display:grid;grid-template-columns:var(--col-num) minmax(80px,260px) 72px 96px var(--col-gains);grid-column-gap:6px;align-items:center;padding:8px 6px;border-bottom:1px solid rgba(255,255,255,0.02);line-height:1.15}

    /* use CSS grid for consistent column alignment */
    /* cap the pseudo column max so it doesn't push Gains too far right */
    .table .row{display:grid;grid-template-columns:var(--col-num) minmax(80px,260px) var(--col-bounty) var(--col-recave) var(--col-gains);grid-column-gap:6px;align-items:center;padding:4px 4px;border-bottom:1px solid rgba(255,255,255,0.02);line-height:1.1}
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
    <a href="/panel/quickview.php" class="close-btn">Fermer</a>
    <div class="header">
        <div style="flex:1">
            <div class="title"><?php echo h($title); ?></div>
            <?php if($activity && !empty($activity['date_depart'])): ?>
                <?php $dt = strtotime($activity['date_depart']); ?>
                <div class="sub"><?php echo $dt ? h(date('Y-m-d H:i', $dt)) : h($activity['date_depart']); ?></div>
            <?php endif; ?>
            <div class="muted">
                <span style="color:#ff7a45;font-weight:700"><?php echo intval($total_count); ?></span> inscrits
                <?php if(!is_null($max_places)){ ?> sur <span style="color:#ff7a45;font-weight:700"><?php echo intval($max_places); ?></span> max<?php } ?>
            </div>
        </div>
        
    </div>

    <div style="margin-top:12px;text-align:center;color:var(--blue);font-size:20px;font-weight:900">Table Finale avec ITM</div>
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
                    <div class="col-num <?php echo $rankClass; ?>"><?php echo h($rank); ?></div>
                    <div class="col-pseudo <?php echo ($i===0? 'pseudo-highlight':''); ?>"><?php echo h($r['pseudo']); ?></div>
                    <div class="col-bounty"><?php echo $r['bounty']? h($r['bounty']) : '-'; ?></div>
                    <div class="col-recave"><?php echo $r['recave']? h($r['recave']) : '-'; ?></div>
                    <div class="col-gains"><?php echo ($r['gains']>0)? number_format($r['gains'], 0, ',', ' ') . '€' : '-'; ?></div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <?php if(!empty($rows) && $activity): ?>
        <div class="table summary" role="list" aria-label="Synthèse joueurs" style="margin-top:12px">
            
            <?php
            $current_member_id = isset($_SESSION['id']) ? intval($_SESSION['id']) : null;
            $foundRow = null;
            if($current_member_id !== null){
                // search displayed rows first
                foreach($rows as $i=>$r){
                    if(isset($r['member_id']) && intval($r['member_id']) === $current_member_id){ $foundRow = ['row'=>$r,'index'=>$i]; break; }
                }
                // if not found in top9, search the full list
                if($foundRow === null && isset($all_rows) && is_array($all_rows)){
                    foreach($all_rows as $i=>$r){
                        if(isset($r['member_id']) && intval($r['member_id']) === $current_member_id){ $foundRow = ['row'=>$r,'index'=>$i]; break; }
                    }
                }
            }
            if($foundRow !== null){
                $r = $foundRow['row']; $i = $foundRow['index'];
                $rank = (isset($r['place']) && intval($r['place'])>0) ? intval($r['place']) : ($i+1);
                if($current_member_id !== null && $activity_organizer_id !== null && intval($current_member_id) === intval($activity_organizer_id)){
                    // organizer: do not take rake into account
                    $effective_buyin = $activity_buyin;
                } else {
                    $effective_buyin = max(0.0, $activity_buyin + $activity_rake);
                }
                $depenses = $effective_buyin + (intval($r['recave'] ?? 0) * $activity_recave_montant);
                $gains = floatval($r['gains'] ?? 0.0);
                // bounty-specific values (only for display when activity defines a bounty)
                $bounty_depense = 0.0;
                $bounty_gains = 0.0;
                if(!empty($activity_bounty) && floatval($activity_bounty) > 0){
                    $bounty_depense = floatval($activity_bounty) + floatval($activity_bounty) * intval($r['recave'] ?? 0);
                    $bounty_gains = floatval($activity_bounty) * intval($r['bounty'] ?? 0);
                }
                // total amounts include bounty gains as positive gains
                $total_gains = $gains + $bounty_gains;
                $total_depenses = $depenses + $bounty_depense;
                $benef = $total_gains - $total_depenses;
                // recave count and color: green when 0, red otherwise
                $recave_count = intval($r['recave'] ?? 0);
                $recave_color = ($recave_count === 0) ? 'var(--green)' : '#ff6b6b';
                // fetch list of players eliminated by current player
                $eliminated_players = [];
                if(!empty($r['pseudo']) && !empty($con)){
                    $esc_cur = mysqli_real_escape_string($con, $r['pseudo']);
                    $elim_list_q = "SELECT COALESCE(m.pseudo,'') AS elim_pseudo FROM `eliminations` e JOIN `participation` p2 ON e.`id_participation` = p2.`id-participation` LEFT JOIN `membres` m ON m.`id-membre` = p2.`id-membre` WHERE p2.`id-activite` = '".$aid."' AND e.`nom_membre` = '".$esc_cur."' ORDER BY e.`created_at` ASC";
                    $elim_list_r = @mysqli_query($con, $elim_list_q);
                    if($elim_list_r){ while($er = mysqli_fetch_assoc($elim_list_r)){ if($er['elim_pseudo'] !== '') $eliminated_players[] = $er['elim_pseudo']; } }
                }
                // fetch list of players who eliminated the current player (killers)
                $eliminated_by = [];
                $part_id_for_elim = $r['id'] ?? null;
                if($part_id_for_elim && !empty($con)){
                    $eby_q = "SELECT e.`nom_membre` FROM `eliminations` e WHERE e.`id_participation` = '".intval($part_id_for_elim)."' ORDER BY e.`created_at` ASC";
                    $eby_r = @mysqli_query($con, $eby_q);
                    if($eby_r){ while($er2 = mysqli_fetch_assoc($eby_r)){ if($er2['nom_membre'] !== '') $eliminated_by[] = $er2['nom_membre']; } }
                }
                // compute duration in game: activity start → player's last elimination
                $duree_en_jeu = null;
                $duree_label = '';
                $part_id = $r['id'] ?? null;
                $activity_start = !empty($activity['date_depart']) ? strtotime($activity['date_depart']) : null;
                if($part_id && !empty($con)){
                    $dq = @mysqli_query($con, "SELECT MAX(created_at) AS last_elim FROM `eliminations` WHERE `id_participation` = '".intval($part_id)."'");
                    if($dq && ($dr = mysqli_fetch_assoc($dq)) && !empty($dr['last_elim'])){
                        $elim_ts = strtotime($dr['last_elim']);
                        if($elim_ts && $activity_start){
                            $diff = max(0, $elim_ts - $activity_start - 1200); // -20min pause
                            $h_dur = intdiv($diff, 3600);
                            $m_dur = intdiv($diff % 3600, 60);
                            $duree_label = ($h_dur > 0) ? $h_dur.' Heure'.($h_dur > 1 ? 's' : '').' '.$m_dur.' Minute'.($m_dur > 1 ? 's' : '') : $m_dur.' Minute'.($m_dur > 1 ? 's' : '');
                            $elim_time_label = date('H:i', $elim_ts);
                        }
                    } else {
                        // no elimination = still alive / winner
                        $duree_label = ($gains > 0) ? 'Vainqueur 🏆' : 'En jeu';
                    }
                }
                // position color depends on PricePool gains (green when >0, red otherwise)
                $position_color = ($gains > 0) ? 'var(--green)' : '#ff6b6b';
                ?>
                <div class="summary-lines" role="list" aria-label="Synthèse joueur" style="margin-top:12px">
                    <?php
                        // Build header: "Stats de <Pseudo> chez <Organisateur> Le <date>"
                        $organizer_name = '';
                        // try common activity fields that may contain organizer name or id
                        foreach(['organisateur','organizer','organizer_name'] as $c){ if(isset($activity[$c]) && $activity[$c] !== ''){ $organizer_name = $activity[$c]; break; } }
                        // if activity only stores organizer id, try to lookup member pseudo
                        if(empty($organizer_name) && !empty($activity_organizer_id) && !empty($con)){
                            $oq = @mysqli_query($con, "SELECT COALESCE(pseudo,'') AS pseudo FROM membres WHERE `id-membre` = '".intval($activity_organizer_id)."' LIMIT 1");
                            if($oq && mysqli_num_rows($oq)>0){ $or = mysqli_fetch_assoc($oq); $organizer_name = $or['pseudo']; }
                        }
                        // format date as '15 Avril' (French month)
                        $dateLabel = '';
                        if(!empty($activity['date_depart'])){
                            $dt2 = strtotime($activity['date_depart']);
                            if($dt2){
                                $months = [1=>'janvier',2=>'février',3=>'mars',4=>'avril',5=>'mai',6=>'juin',7=>'juillet',8=>'août',9=>'septembre',10=>'octobre',11=>'novembre',12=>'décembre'];
                                $day = date('j', $dt2);
                                $monthName = isset($months[intval(date('n',$dt2))]) ? ucfirst($months[intval(date('n',$dt2))]) : '';
                                $dateLabel = $day . ' ' . $monthName;
                            }
                        }
                        $statsLine = 'Stats de ' . '<span class="pseudo-highlight">' . h($r['pseudo']) . '</span>' . ' chez ' . h($organizer_name) . ($dateLabel ? ' <span style="color:var(--blue)">Le ' . h($dateLabel) . '</span>' : '');
                    ?>
                    <div class="line" style="display:flex;justify-content:center;padding:6px 6px;border-bottom:1px solid rgba(255,255,255,0.02)">
                        <div class="value" style="margin-top:6px;text-align:center;color:var(--orange);font-size:18px;font-weight:900"><?php echo $statsLine; ?></div>
                    </div>
                    <!-- Place line removed as requested -->
                    <div class="line" style="display:flex;justify-content:space-between;padding:8px 6px;border-bottom:1px solid rgba(255,255,255,0.02)">
                        <div class="label">Position / Inscrits</div>
                        <div class="value"><span style="color:<?php echo $position_color; ?>"><?php echo h($rank); ?></span> / <?php echo intval($total_count); ?></div>
                    </div>
                    <?php if(!empty($eliminated_by)): ?>
                    <div class="line" style="display:flex;justify-content:space-between;align-items:flex-start;padding:8px 6px;border-bottom:1px solid rgba(255,255,255,0.02)">
                        <div class="label">Éliminé par :</div>
                        <div class="value" style="text-align:right;max-width:60%;word-break:break-word">
                            <?php
                            $last_idx = count($eliminated_by) - 1;
                            foreach($eliminated_by as $ei => $ep):
                                $is_last = ($ei === $last_idx);
                            ?>
                            <span style="color:<?php echo $is_last ? '#ff6b6b' : 'var(--purple)'; ?>;display:block"><?php echo ($last_idx > 0 ? ($ei+1).'. ' : '') . h($ep); ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    <div class="line" style="display:flex;justify-content:space-between;align-items:flex-start;padding:8px 6px;border-bottom:1px solid rgba(255,255,255,0.02)">
                        <div class="label">A éliminé</div>
                        <div class="value" style="text-align:right;max-width:60%;word-break:break-word">
                            <?php if(empty($eliminated_players)): ?>
                                <span style="color:var(--muted)">—</span>
                            <?php else: foreach($eliminated_players as $ei => $ep): ?>
                                <span style="color:var(--purple);display:block"><?php echo (count($eliminated_players) > 1 ? ($ei+1).'. ' : '') . h($ep); ?></span>
                            <?php endforeach; endif; ?>
                        </div>
                    </div>
                    <?php if(!empty($duree_label)): ?>
                    <div class="line" style="display:flex;justify-content:space-between;padding:8px 6px;border-bottom:1px solid rgba(255,255,255,0.02)">
                        <div class="label">Durée de jeu<?php if(!empty($elim_time_label)): ?> <span class="small-muted">(OUT à <?php echo h($elim_time_label); ?>)</span><?php endif; ?></div>
                        <div class="value" style="color:var(--blue)"><?php echo h($duree_label); ?></div>
                    </div>
                    <?php endif; ?>
                    <div class="line" style="display:flex;justify-content:space-between;padding:8px 6px;border-bottom:1px solid rgba(255,255,255,0.02)">
                        <div class="label">ReCave(s)</div>
                        <div class="value" style="color:<?php echo $recave_color; ?>"><?php echo $recave_count; ?></div>
                    </div>
                    <?php
                        // prepare a small inline calculation example: (buyin + recave_total + rake)
                        $buyin_disp = intval($activity_buyin);
                        $recave_total = intval($recave_count) * $activity_recave_montant;
                        // if current member is organizer, rake isn't counted in expenses
                        $display_rake = ($current_member_id !== null && $activity_organizer_id !== null && intval($current_member_id) === intval($activity_organizer_id)) ? 0 : $activity_rake;
                        $rake_disp = intval($display_rake);
                        // cast recave_total to int for display if it's effectively integer-like
                        $recave_disp = intval($recave_total);
                        $calc_example = '(' . $buyin_disp . '+' . $recave_disp . '+' . $rake_disp . ')';
                    ?>
                    <div class="line" style="display:flex;justify-content:space-between;padding:8px 6px;border-bottom:1px solid rgba(255,255,255,0.02)">
                        <div class="label">Dépenses Buyin+Recave(s)+Rake <span class="small-muted"><?php echo $calc_example; ?></span></div>
                        <div class="value" style="color:#ff6b6b"><?php echo number_format($depenses,0,',',' ') . '€'; ?></div>
                    </div>
                    <?php if(!empty($activity_bounty) && floatval($activity_bounty) > 0): ?>
                    <div class="line" style="display:flex;justify-content:space-between;padding:8px 6px;border-bottom:1px solid rgba(255,255,255,0.02)">
                        <div class="label">Dépenses bounty</div>
                        <div class="value" style="color:#ff6b6b"><?php echo number_format($bounty_depense,0,',',' ') . '€'; ?></div>
                    </div>
                    <div class="line" style="display:flex;justify-content:space-between;padding:8px 6px;border-bottom:1px solid rgba(255,255,255,0.02)">
                        <div class="label">Gains bounty</div>
                        <div class="value"><?php echo ($bounty_gains>0)? number_format($bounty_gains,0,',',' ') . '€' : '-'; ?></div>
                    </div>
                    <?php endif; ?>
                    <div class="line" style="display:flex;justify-content:space-between;padding:8px 6px;border-bottom:1px solid rgba(255,255,255,0.02)">
                        <div class="label">Gains PricePool</div>
                        <div class="value"><?php echo ($gains>0)? number_format($gains,0,',',' ') . '€' : '-'; ?></div>
                    </div>
                    
                    <?php $labelText = ($benef >= 0) ? 'Bénéfice Net' : 'Perte Net'; $labelColor = ($benef > 0) ? 'var(--green)' : '#ff6b6b'; ?>
                    <div class="line" style="display:flex;justify-content:space-between;padding:8px 6px;font-weight:700">
                        <div class="label" style="color:<?php echo $labelColor; ?>"><?php echo $labelText; ?></div>
                        <div class="value" style="color:<?php echo ($benef >= 0) ? 'var(--green)' : '#ff6b6b'; ?>"><?php echo ($benef >= 0) ? number_format($benef,0,',',' ') . '€' : number_format(abs($benef),0,',',' ') . '€'; ?></div>
                    </div>
                </div>
            <?php } ?>
        </div>
    <?php endif; ?>
</div>
</body>
</html>
