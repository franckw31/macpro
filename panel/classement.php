<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();
include __DIR__ . '/include/config.php';

if (!function_exists('h')) { function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); } }

// Joueur cible
$member_id = isset($_GET['mid']) && is_numeric($_GET['mid']) ? intval($_GET['mid']) : null;
if (!$member_id && isset($_SESSION['id'])) $member_id = intval($_SESSION['id']);

$pseudo = '';
$my = null; // stats du joueur
$grp = null; // stats du groupe

if ($member_id && !empty($con)) {

    // Pseudo
    $mq = @mysqli_query($con, "SELECT COALESCE(pseudo,'') AS pseudo FROM membres WHERE `id-membre`=".intval($member_id)." LIMIT 1");
    if ($mq && ($mr = mysqli_fetch_assoc($mq))) $pseudo = $mr['pseudo'];

    // Stats du joueur
    $sq = @mysqli_query($con, "
        SELECT
            COUNT(*)                                                                AS cnt,
            ROUND(AVG(p.sergio_score),2)                                           AS avg_score,
            MAX(p.sergio_score)                                                    AS best,
            MIN(CASE WHEN p.classement>0 AND p.classement<50 THEN p.classement END) AS best_rank,
            SUM(CASE WHEN COALESCE(p.gain,0)>0 THEN 1 ELSE 0 END)                 AS itm,
            SUM(CASE WHEN p.classement=1 THEN 1 ELSE 0 END)                        AS top1,
            COALESCE(SUM(COALESCE(p.recave,0)),0)                                  AS recaves
        FROM participation p
        WHERE p.`id-membre`=".intval($member_id)."
          AND p.sergio_score IS NOT NULL
          AND p.classement != 0 AND p.classement != 50
    ");
    if ($sq && ($sr = mysqli_fetch_assoc($sq))) $my = $sr;

    // Stats groupe (tous les joueurs ayant un sergio_score, min 3 parties)
    $gq = @mysqli_query($con, "
        SELECT
            ROUND(AVG(sub.avg_score),2)   AS grp_avg,
            MAX(sub.avg_score)             AS grp_best_avg,
            ROUND(AVG(sub.itm_pct),1)      AS grp_itm_pct,
            ROUND(AVG(sub.recaves),1)      AS grp_recaves,
            COUNT(*)                       AS grp_players
        FROM (
            SELECT
                p.`id-membre`,
                ROUND(AVG(p.sergio_score),2)                             AS avg_score,
                ROUND(SUM(CASE WHEN COALESCE(p.gain,0)>0 THEN 1 ELSE 0 END)/COUNT(*)*100,1) AS itm_pct,
                COALESCE(SUM(COALESCE(p.recave,0)),0)                    AS recaves
            FROM participation p
            WHERE p.sergio_score IS NOT NULL
              AND p.classement != 0 AND p.classement != 50
            GROUP BY p.`id-membre`
            HAVING COUNT(*) >= 7
        ) sub
    ");
    if ($gq && ($gr = mysqli_fetch_assoc($gq))) $grp = $gr;

    // Rang du joueur parmi tous les joueurs (par avg_score, min 3 parties)
    $rank = null; $rank_total = null;
    $rq = @mysqli_query($con, "
        SELECT p.`id-membre`, ROUND(AVG(p.sergio_score),2) AS avg_score
        FROM participation p
        WHERE p.sergio_score IS NOT NULL AND p.classement != 0 AND p.classement != 50
        GROUP BY p.`id-membre`
        HAVING COUNT(*) >= 7
        ORDER BY avg_score DESC
    ");
    if ($rq) {
        $rk = 1;
        while ($rr = mysqli_fetch_assoc($rq)) {
            if (intval($rr['id-membre']) === $member_id) $rank = $rk;
            $rk++;
        }
        $rank_total = $rk - 1;
    }

    // Mois dernier vs mois courant
    $cm = @mysqli_query($con, "SELECT ROUND(AVG(p.sergio_score),2) AS avg FROM participation p JOIN activite a ON a.`id-activite`=p.`id-activite` WHERE p.`id-membre`=".intval($member_id)." AND p.sergio_score IS NOT NULL AND DATE_FORMAT(a.date_depart,'%Y-%m')=DATE_FORMAT(NOW(),'%Y-%m')");
    $curr_avg = ($cm && ($cr = mysqli_fetch_assoc($cm))) ? $cr['avg'] : null;
    $pm = @mysqli_query($con, "SELECT ROUND(AVG(p.sergio_score),2) AS avg FROM participation p JOIN activite a ON a.`id-activite`=p.`id-activite` WHERE p.`id-membre`=".intval($member_id)." AND p.sergio_score IS NOT NULL AND DATE_FORMAT(a.date_depart,'%Y-%m')=DATE_FORMAT(DATE_SUB(NOW(),INTERVAL 1 MONTH),'%Y-%m')");
    $prev_avg = ($pm && ($pr2 = mysqli_fetch_assoc($pm))) ? $pr2['avg'] : null;
}

function bar($val, $ref, $max, $color='#4ade80') {
    $pct = $max > 0 ? min(100, round($val / $max * 100)) : 0;
    $rpct = $max > 0 ? min(100, round($ref / $max * 100)) : 0;
    return ['val'=>$pct, 'ref'=>$rpct, 'color'=>$color];
}

function scoreColor($s) {
    if ($s === null) return '#8b98a6';
    if ($s >= 18) return '#fbbf24';
    if ($s >= 15) return '#4ade80';
    if ($s >= 10) return '#60a5fa';
    return '#8b98a6';
}
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Comparaison — <?php echo h($pseudo); ?></title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{background:#080d1a;color:#f1f5f9;font-family:Inter,-apple-system,BlinkMacSystemFont,'Segoe UI',Arial,sans-serif;font-size:14px;min-height:100vh;padding-bottom:32px}
.sg{max-width:560px;margin:0 auto;padding:0 12px 20px}
.sg-nav{display:flex;align-items:center;padding:16px 0 4px}
.sg-back{color:#f97316;font-weight:700;font-size:15px}
.sg-header{text-align:center;padding:10px 0 14px}
.sg-title{font-size:18px;font-weight:900}
.sg-sub{color:#94a3b8;font-size:12px;margin-top:2px}

/* Rank hero */
.sg-rank{background:#0f1629;border:1px solid rgba(255,255,255,.08);border-radius:16px;padding:16px;text-align:center;margin-bottom:10px}
.sg-rank-lbl{font-size:10px;color:#94a3b8;text-transform:uppercase;letter-spacing:1px;margin-bottom:4px}
.sg-rank-val{font-size:40px;font-weight:900;color:#a78bfa;line-height:1}
.sg-rank-sub{font-size:12px;color:#64748b;margin-top:4px}

/* Compare cards */
.cmp-card{background:#0f1629;border:1px solid rgba(255,255,255,.07);border-radius:14px;padding:14px 14px 10px;margin-bottom:8px}
.cmp-title{font-size:10px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.8px;margin-bottom:12px}
.cmp-row{display:flex;align-items:center;gap:10px;margin-bottom:10px}
.cmp-label{font-size:11px;color:#94a3b8;min-width:90px;flex-shrink:0}
.cmp-bars{flex:1;display:flex;flex-direction:column;gap:3px}
.cmp-bar-wrap{position:relative;height:8px;background:rgba(255,255,255,.05);border-radius:4px;overflow:hidden}
.cmp-bar-fill{height:8px;border-radius:4px;transition:width .6s ease}
.cmp-bar-ref{position:absolute;top:0;height:8px;border-radius:4px;opacity:.35}
.cmp-bar-lbl{display:flex;justify-content:space-between;font-size:9.5px;margin-top:1px}
.cmp-you{font-weight:800}
.cmp-avg{color:#64748b}
.cmp-legend{display:flex;gap:14px;margin-top:10px;padding-top:10px;border-top:1px solid rgba(255,255,255,.05)}
.cmp-dot{width:8px;height:8px;border-radius:2px;flex-shrink:0;margin-top:2px}
.cmp-leg-txt{font-size:10px;color:#64748b}

/* Delta badge */
.delta{display:inline-flex;align-items:center;gap:3px;font-size:11px;font-weight:700;padding:2px 8px;border-radius:12px}

/* Nodata */
.nodata{text-align:center;color:#64748b;padding:40px 0;font-size:13px}
</style>
</head>
<body>
<div class="sg">

<div class="sg-nav">
  <a href="/panel/sergio.php?mid=<?php echo intval($member_id); ?>" class="sg-back">‹ Retour</a>
</div>

<?php if (!$member_id || !$my || !$grp): ?>
  <div class="nodata">Données insuffisantes pour la comparaison.<br><small>Il faut au minimum 3 parties avec un SergioScore.</small></div>
<?php else:
  $my_avg  = floatval($my['avg_score']);
  $grp_avg = floatval($grp['grp_avg']);
  $grp_best= floatval($grp['grp_best_avg']);
  $my_itm  = $my['cnt'] > 0 ? round($my['itm'] / $my['cnt'] * 100, 1) : 0;
  $grp_itm = floatval($grp['grp_itm_pct']);
  $my_rec  = floatval($my['recaves']);
  $grp_rec = floatval($grp['grp_recaves']);
?>

<div class="sg-header">
  <h1 class="sg-title">📊 Comparaison</h1>
  <p class="sg-sub"><?php echo h($pseudo); ?> vs groupe (<?php echo intval($grp['grp_players']); ?> joueurs)</p>
</div>

<!-- Rang global -->
<?php if ($rank): ?>
<div class="sg-rank">
  <div class="sg-rank-lbl">Classement SergioScore global</div>
  <div class="sg-rank-val">#<?php echo $rank; ?></div>
  <div class="sg-rank-sub">sur <?php echo $rank_total; ?> joueurs (min. 3 parties)</div>
</div>
<?php endif; ?>

<!-- Score moyen -->
<div class="cmp-card">
  <div class="cmp-title">Score moyen</div>
  <?php
    $max_avg = max($grp_best, $my_avg, 1);
    $my_pct  = round($my_avg  / $max_avg * 100);
    $grp_pct = round($grp_avg / $max_avg * 100);
    $diff_avg = round($my_avg - $grp_avg, 2);
    $diff_pos = $diff_avg >= 0;
  ?>
  <div class="cmp-row">
    <div class="cmp-label">Vous</div>
    <div class="cmp-bars">
      <div class="cmp-bar-wrap">
        <div class="cmp-bar-fill" style="width:<?php echo $my_pct; ?>%;background:<?php echo scoreColor($my_avg); ?>"></div>
        <div class="cmp-bar-ref" style="left:0;width:<?php echo $grp_pct; ?>%;background:#60a5fa"></div>
      </div>
      <div class="cmp-bar-lbl">
        <span class="cmp-you" style="color:<?php echo scoreColor($my_avg); ?>"><?php echo $my_avg; ?></span>
        <span class="cmp-avg">moy. groupe : <?php echo $grp_avg; ?></span>
      </div>
    </div>
    <span class="delta" style="color:<?php echo $diff_pos?'#4ade80':'#f87171';?>;background:<?php echo $diff_pos?'rgba(74,222,128,.12)':'rgba(248,113,113,.12)';?>"><?php echo ($diff_pos?'+':'').$diff_avg; ?></span>
  </div>
  <div class="cmp-legend">
    <div style="display:flex;gap:5px;align-items:center"><div class="cmp-dot" style="background:<?php echo scoreColor($my_avg);?>"></div><span class="cmp-leg-txt">Vous</span></div>
    <div style="display:flex;gap:5px;align-items:center"><div class="cmp-dot" style="background:#60a5fa;opacity:.5"></div><span class="cmp-leg-txt">Moyenne du groupe</span></div>
  </div>
</div>

<!-- ITM -->
<div class="cmp-card">
  <div class="cmp-title">Taux ITM</div>
  <?php
    $max_itm = max($my_itm, $grp_itm, 1);
    $myitm_pct  = round($my_itm  / $max_itm * 100);
    $grpitm_pct = round($grp_itm / $max_itm * 100);
    $diff_itm = round($my_itm - $grp_itm, 1);
    $diff_itm_pos = $diff_itm >= 0;
  ?>
  <div class="cmp-row">
    <div class="cmp-label">Vous</div>
    <div class="cmp-bars">
      <div class="cmp-bar-wrap">
        <div class="cmp-bar-fill" style="width:<?php echo $myitm_pct; ?>%;background:#fbbf24"></div>
        <div class="cmp-bar-ref" style="left:0;width:<?php echo $grpitm_pct; ?>%;background:#60a5fa"></div>
      </div>
      <div class="cmp-bar-lbl">
        <span class="cmp-you" style="color:#fbbf24"><?php echo $my_itm; ?>%</span>
        <span class="cmp-avg">moy. groupe : <?php echo $grp_itm; ?>%</span>
      </div>
    </div>
    <span class="delta" style="color:<?php echo $diff_itm_pos?'#4ade80':'#f87171';?>;background:<?php echo $diff_itm_pos?'rgba(74,222,128,.12)':'rgba(248,113,113,.12)';?>"><?php echo ($diff_itm_pos?'+':'').$diff_itm; ?>%</span>
  </div>
</div>

<!-- Recaves -->
<div class="cmp-card">
  <div class="cmp-title">Recaves moyennes</div>
  <?php
    $max_rec = max($my_rec, $grp_rec, 1);
    $myrec_pct  = round($my_rec  / $max_rec * 100);
    $grprec_pct = round($grp_rec / $max_rec * 100);
    $diff_rec = round($my_rec - $grp_rec, 1);
    $diff_rec_pos = $diff_rec <= 0; // moins de recaves = mieux
  ?>
  <div class="cmp-row">
    <div class="cmp-label">Vous</div>
    <div class="cmp-bars">
      <div class="cmp-bar-wrap">
        <div class="cmp-bar-fill" style="width:<?php echo $myrec_pct; ?>%;background:#f97316"></div>
        <div class="cmp-bar-ref" style="left:0;width:<?php echo $grprec_pct; ?>%;background:#60a5fa"></div>
      </div>
      <div class="cmp-bar-lbl">
        <span class="cmp-you" style="color:#f97316"><?php echo $my_rec; ?></span>
        <span class="cmp-avg">moy. groupe : <?php echo $grp_rec; ?></span>
      </div>
    </div>
    <span class="delta" style="color:<?php echo $diff_rec_pos?'#4ade80':'#f87171';?>;background:<?php echo $diff_rec_pos?'rgba(74,222,128,.12)':'rgba(248,113,113,.12)';?>"><?php echo ($diff_rec>=0?'+':'').$diff_rec; ?></span>
  </div>
  <div style="font-size:9.5px;color:#64748b;margin-top:4px">Moins de recaves = meilleure gestion du tapis</div>
</div>

<!-- Évolution mensuelle -->
<?php if ($curr_avg !== null || $prev_avg !== null): ?>
<div class="cmp-card">
  <div class="cmp-title">Évolution mensuelle</div>
  <div style="display:flex;gap:8px">
    <?php if ($prev_avg !== null): ?>
    <div style="flex:1;background:rgba(255,255,255,.03);border-radius:10px;padding:10px;text-align:center">
      <div style="font-size:9px;color:#64748b;text-transform:uppercase;margin-bottom:4px">Mois dernier</div>
      <div style="font-size:22px;font-weight:900;color:<?php echo scoreColor(floatval($prev_avg));?>"><?php echo $prev_avg;?></div>
    </div>
    <?php endif; ?>
    <?php if ($curr_avg !== null): ?>
    <div style="flex:1;background:rgba(255,255,255,.04);border-radius:10px;padding:10px;text-align:center;border:1px solid rgba(74,222,128,.2)">
      <div style="font-size:9px;color:#4ade80;text-transform:uppercase;margin-bottom:4px">Ce mois-ci</div>
      <div style="font-size:22px;font-weight:900;color:<?php echo scoreColor(floatval($curr_avg));?>"><?php echo $curr_avg;?></div>
    </div>
    <?php endif; ?>
    <?php if ($curr_avg !== null && $prev_avg !== null): ?>
    <div style="flex:1;background:rgba(255,255,255,.03);border-radius:10px;padding:10px;text-align:center;display:flex;flex-direction:column;align-items:center;justify-content:center">
      <?php $dv=round($curr_avg-$prev_avg,2); $dp=$dv>=0; ?>
      <div style="font-size:9px;color:#64748b;text-transform:uppercase;margin-bottom:4px">Évolution</div>
      <div style="font-size:18px;font-weight:900;color:<?php echo $dp?'#4ade80':'#f87171';?>"><?php echo ($dp?'↗ +':'↘ ').$dv;?></div>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

<!-- Tableau debug classement complet -->
<?php
$all = [];
$aq = @mysqli_query($con, "
    SELECT p.`id-membre`, COALESCE(m.pseudo,'?') AS pseudo,
           ROUND(AVG(p.sergio_score),2) AS avg_score,
           COUNT(*) AS nb_parties,
           SUM(CASE WHEN COALESCE(p.gain,0)>0 THEN 1 ELSE 0 END) AS itm
    FROM participation p
    LEFT JOIN membres m ON m.`id-membre` = p.`id-membre`
    WHERE p.sergio_score IS NOT NULL AND p.classement != 0 AND p.classement != 50
    GROUP BY p.`id-membre`
    HAVING COUNT(*) >= 7
    ORDER BY avg_score DESC
");
if ($aq) { while ($ar = mysqli_fetch_assoc($aq)) $all[] = $ar; }
// position du joueur dans ce classement
$table_pos = null;
foreach ($all as $i => $ar2) {
    if (intval($ar2['id-membre']) === intval($member_id)) { $table_pos = $i + 1; break; }
}
$all_count = count($all);
?>
<?php if (!empty($all)): ?>
  
<div style="max-width:560px;margin:16px auto 0;padding:0 12px">
  <div style="font-size:10px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.8px;margin-bottom:8px">Classement complet (≥6 parties)</div>
  <div style="background:#0f1629;border:1px solid rgba(255,255,255,.07);border-radius:14px;overflow:hidden">
  <table style="width:100%;border-collapse:collapse;font-size:12px">
    <thead>
      <tr style="border-bottom:1px solid rgba(255,255,255,.07)">
        <th style="padding:8px 10px;color:#64748b;text-align:center;font-size:10px">#</th>
        <th style="padding:8px 10px;color:#64748b;text-align:left;font-size:10px">Joueur</th>
        <th style="padding:8px 10px;color:#64748b;text-align:center;font-size:10px">Parties</th>
        <th style="padding:8px 10px;color:#64748b;text-align:center;font-size:10px">ITM</th>
        <th style="padding:8px 10px;color:#64748b;text-align:right;font-size:10px">Moy.</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($all as $i => $ar):
      $isMe = intval($ar['id-membre']) === $member_id;
      $col = $ar['avg_score'] >= 18 ? '#fbbf24' : ($ar['avg_score'] >= 15 ? '#4ade80' : ($ar['avg_score'] >= 10 ? '#60a5fa' : '#8b98a6'));
    ?>
    <tr style="border-bottom:1px solid rgba(255,255,255,.04);<?php echo $isMe ? 'background:rgba(167,139,250,.08);' : ''; ?>">
      <td style="padding:7px 10px;text-align:center;color:<?php echo $i===0?'#fbbf24':($i===1?'#94a3b8':($i===2?'#c47a3a':'#4b5563'));?>;font-weight:700"><?php echo $i+1; ?></td>
      <td style="padding:7px 10px;font-weight:<?php echo $isMe?'800':'600';?>;color:<?php echo $isMe?'#a78bfa':'#f1f5f9';?>"><?php echo h($ar['pseudo']); ?><?php echo $isMe?' ←':''; ?></td>
      <td style="padding:7px 10px;text-align:center;color:#64748b"><?php echo $ar['nb_parties']; ?></td>
      <td style="padding:7px 10px;text-align:center;color:#fbbf24"><?php echo $ar['itm']; ?></td>
      <td style="padding:7px 10px;text-align:right;font-weight:800;color:<?php echo $col;?>"><?php echo $ar['avg_score']; ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</div>
<?php endif; ?>

<?php endif; ?>

</div>
</body>
</html>
