<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
include __DIR__ . '/include/config.php'; // provides $con (mysqli)

// ── Paramètres ────────────────────────────────────────────────────────────────
$member_id = isset($_GET['mid']) && is_numeric($_GET['mid']) ? intval($_GET['mid']) : null;
// Résolution par pseudo
if (!$member_id && !empty($_GET['pseudo'])) {
    include_once __DIR__ . '/include/config.php';
    $ps = mysqli_real_escape_string($con, trim($_GET['pseudo']));
    $pr = @mysqli_query($con, "SELECT `id-membre` FROM membres WHERE pseudo='$ps' LIMIT 1");
    if ($pr && mysqli_num_rows($pr) > 0) { $member_id = intval(mysqli_fetch_assoc($pr)['id-membre']); }
}
// Fallback : membre connecté
if (!$member_id && isset($_SESSION['id'])) $member_id = intval($_SESSION['id']);

$filter_year  = isset($_GET['y']) && is_numeric($_GET['y'])  ? intval($_GET['y'])  : null;
$filter_month = isset($_GET['m']) && is_numeric($_GET['m'])  ? intval($_GET['m'])  : null;

if (!function_exists('h')) { function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); } }

// ── Données du membre ─────────────────────────────────────────────────────────
$pseudo = '';
$rows   = [];
$years  = [];
$stats  = ['count' => 0, 'avg' => null, 'best' => null, 'worst' => null, 'sum' => 0];
$extra_stats = ['parties' => 0, 'tf' => 0, 'itm' => 0, 'recaves' => 0, 'best_rank' => null, 'worst_rank' => null, 'top1' => 0];
$itm_rows       = [];
$chal_rank      = null;
$chal_total     = null;
$sergio_global_rank  = null;
$sergio_global_total = null;
$chal_title     = '';
$curr_month_avg = null;
$prev_month_avg = null;
$insights       = [];

if ($member_id && !empty($con)) {
    // Pseudo
    $mq = @mysqli_query($con, "SELECT COALESCE(pseudo,'') AS pseudo FROM membres WHERE `id-membre` = '".intval($member_id)."' LIMIT 1");
    if ($mq && mysqli_num_rows($mq) > 0) {
        $mr = mysqli_fetch_assoc($mq);
        $pseudo = $mr['pseudo'];
    }

    // Années disponibles (pour le filtre)
    $yq = @mysqli_query($con, "
        SELECT DISTINCT YEAR(a.date_depart) AS yr
        FROM participation p
        JOIN activite a ON a.`id-activite` = p.`id-activite`
        WHERE p.`id-membre` = '".intval($member_id)."'
          AND p.sergio_score IS NOT NULL
        ORDER BY yr DESC
    ");
    if ($yq) { while ($yr = mysqli_fetch_assoc($yq)) $years[] = intval($yr['yr']); }

    // Construction du WHERE dynamique
    $where_extra = '';
    if ($filter_year)  $where_extra .= " AND YEAR(a.date_depart)  = ".intval($filter_year);
    if ($filter_month) $where_extra .= " AND MONTH(a.date_depart) = ".intval($filter_month);

    // Historique
    $hq = @mysqli_query($con, "
        SELECT
            a.`id-activite`         AS id_activite,
            a.date_depart,
            COALESCE(a.`titre-activite`, 'Partie') AS titre,
            p.`id-participation`    AS id_participation,
            p.sergio_score,
            COALESCE(p.classement, 0) AS classement,
            COALESCE(p.recave, 0)    AS recave_joueur,
            (SELECT COUNT(*) FROM participation p2 WHERE p2.`id-activite` = a.`id-activite`) AS nb_joueurs,
            (SELECT COALESCE(SUM(COALESCE(p3.recave,0)),0) FROM participation p3 WHERE p3.`id-activite` = a.`id-activite`) AS total_recaves
        FROM participation p
        JOIN activite a ON a.`id-activite` = p.`id-activite`
        WHERE p.`id-membre` = '".intval($member_id)."'
          AND p.sergio_score IS NOT NULL
          $where_extra
        ORDER BY a.date_depart DESC
        LIMIT 1000
    ");
    if ($hq) {
        while ($r = mysqli_fetch_assoc($hq)) $rows[] = $r;
    }

    // Stats globales (sur la sélection filtrée)
    $sq = @mysqli_query($con, "
        SELECT
            COUNT(*)        AS cnt,
            AVG(p.sergio_score)  AS avg_score,
            MAX(p.sergio_score)  AS best,
            MIN(p.sergio_score)  AS worst,
            SUM(p.sergio_score)  AS total
        FROM participation p
        JOIN activite a ON a.`id-activite` = p.`id-activite`
        WHERE p.`id-membre` = '".intval($member_id)."'
          AND p.sergio_score IS NOT NULL
          $where_extra
    ");
    if ($sq && ($sr = mysqli_fetch_assoc($sq))) {
        $stats = [
            'count' => intval($sr['cnt']),
            'avg'   => $sr['avg_score'] !== null ? round(floatval($sr['avg_score']), 2) : null,
            'best'  => $sr['best']      !== null ? round(floatval($sr['best']),      2) : null,
            'worst' => $sr['worst']     !== null ? round(floatval($sr['worst']),     2) : null,
            'sum'   => round(floatval($sr['total']), 2),
        ];
    }

    // Moyenne mensuelle (pour le graphe sparkline)
    $monthly = [];
    $mqr = @mysqli_query($con, "
        SELECT
            DATE_FORMAT(a.date_depart,'%Y-%m') AS mois,
            ROUND(AVG(p.sergio_score), 2)      AS avg_score,
            COUNT(*)                           AS cnt
        FROM participation p
        JOIN activite a ON a.`id-activite` = p.`id-activite`
        WHERE p.`id-membre` = '".intval($member_id)."'
          AND p.sergio_score IS NOT NULL
        GROUP BY mois
        ORDER BY mois ASC
        LIMIT 24
    ");
    if ($mqr) { while ($mr2 = mysqli_fetch_assoc($mqr)) $monthly[] = $mr2; }

    // Stats extra : TF, ITM, recaves totales
    $extra_stats = ['parties' => 0, 'tf' => 0, 'itm' => 0, 'recaves' => 0];
    $eq = @mysqli_query($con, "
        SELECT
            COUNT(*)                                                                        AS total_parties,
            SUM(CASE WHEN p.classement < 10 THEN 1 ELSE 0 END)                             AS total_tf,
            SUM(CASE WHEN COALESCE(p.gain,0) > 0 THEN 1 ELSE 0 END)                       AS total_itm,
            COALESCE(SUM(COALESCE(p.recave,0)),0)                                          AS total_recaves,
            MIN(CASE WHEN p.classement > 0 AND p.classement < 50 THEN p.classement END)   AS best_rank,
            MAX(CASE WHEN p.classement > 0 AND p.classement < 50 THEN p.classement END)   AS worst_rank,
            SUM(CASE WHEN p.classement = 1 THEN 1 ELSE 0 END)                             AS top1_count
        FROM participation p
        WHERE p.`id-membre` = '".intval($member_id)."'
          AND p.classement != 0
          AND p.classement != 50
          AND p.sergio_score > 0
    ");
    if ($eq && ($er = mysqli_fetch_assoc($eq))) {
        $extra_stats = [
            'parties'    => intval($er['total_parties']),
            'tf'         => intval($er['total_tf']),
            'itm'        => intval($er['total_itm']),
            'recaves'    => intval($er['total_recaves']),
            'best_rank'  => $er['best_rank']  !== null ? intval($er['best_rank'])  : null,
            'worst_rank' => $er['worst_rank'] !== null ? intval($er['worst_rank']) : null,
            'top1'       => intval($er['top1_count']),
        ];
        // Nombre de joueurs dans la partie où le joueur a eu sa pire place
        $extra_stats['worst_rank_total'] = null;
        if ($extra_stats['worst_rank'] !== null) {
            $wrq = @mysqli_query($con, "
                SELECT COUNT(*) AS nb FROM participation p2
                WHERE p2.`id-activite` = (
                    SELECT p.`id-activite` FROM participation p
                    WHERE p.`id-membre` = '".intval($member_id)."'
                      AND p.classement = '".intval($extra_stats['worst_rank'])."'
                      AND p.sergio_score > 0
                    ORDER BY p.`id-activite` DESC LIMIT 1
                )
                  AND p2.classement > 0 AND p2.classement < 50
            ");
            if ($wrq && ($wr = mysqli_fetch_assoc($wrq))) {
                $extra_stats['worst_rank_total'] = intval($wr['nb']);
            }
        }
    }

    // Détail ITM
    $itm_rows = [];
    $itmq = @mysqli_query($con, "
        SELECT
            a.date_depart,
            COALESCE(a.`titre-activite`,'Partie')  AS titre,
            COALESCE(p.classement,0)               AS classement,
            COALESCE(p.gain,0)                     AS gain
        FROM participation p
        JOIN activite a ON a.`id-activite` = p.`id-activite`
        WHERE p.`id-membre` = '".intval($member_id)."'
          AND COALESCE(p.gain,0) > 0
          AND p.`option` NOT IN ('None','Desinscrit')
        ORDER BY a.date_depart DESC
        LIMIT 1000
    ");
    if ($itmq) { while ($ir = mysqli_fetch_assoc($itmq)) $itm_rows[] = $ir; }

    // Classement challenge en cours
    $chal_rank  = null;
    $chal_total = null;
    $chal_title = '';
    $today_str  = date('Y-m-d');
    $chq = @mysqli_query($con, "SELECT id_challenge, titre_challenge FROM challenge WHERE '$today_str' BETWEEN chal_deb AND chal_fin ORDER BY chal_deb DESC LIMIT 1");
    if ($chq && ($chr = mysqli_fetch_assoc($chq))) {
        $chal_id    = intval($chr['id_challenge']);
        $chal_title = $chr['titre_challenge'];
        // Tous les joueurs classés
        $rkq = @mysqli_query($con, "
            SELECT m.`id-membre` AS mid, COALESCE(SUM(p.points),0) AS pts
            FROM membres m
            JOIN participation p  ON p.`id-membre`   = m.`id-membre`
            JOIN activite a       ON p.`id-activite` = a.`id-activite`
            LEFT JOIN blackliste b ON m.`id-membre`  = b.id_membre
            WHERE a.`id_challenge` = $chal_id
              AND b.id_membre IS NULL
              AND p.`option` NOT IN ('None','Desinscrit')
              AND a.date_depart < '$today_str'
            GROUP BY m.`id-membre`
            HAVING pts > 0
            ORDER BY pts DESC
        ");
        if ($rkq) {
            $rk = 1;
            while ($rkr = mysqli_fetch_assoc($rkq)) {
                if (intval($rkr['mid']) === intval($member_id)) { $chal_rank = $rk; }
                $rk++;
            }
            $chal_total = $rk - 1;
        }
    }

    // Classement global au SergioScore
    $sergio_global_rank  = null;
    $sergio_global_total = null;
    $sgq = @mysqli_query($con, "
        SELECT mid, avg_score FROM (
            SELECT p.`id-membre` AS mid, ROUND(AVG(p.sergio_score),2) AS avg_score
            FROM participation p
            JOIN activite a ON a.`id-activite` = p.`id-activite`
            WHERE p.sergio_score IS NOT NULL AND p.sergio_score > 0
              AND a.date_depart >= DATE_SUB(NOW(), INTERVAL 1 YEAR)
            GROUP BY p.`id-membre`
            HAVING COUNT(*) >= 6
        ) t
        ORDER BY avg_score DESC
    ");
    if ($sgq) {
        $sg_rk = 1;
        while ($sgr = mysqli_fetch_assoc($sgq)) {
            if (intval($sgr['mid']) === intval($member_id)) { $sergio_global_rank = $sg_rk; }
            $sg_rk++;
        }
        $sergio_global_total = $sg_rk - 1;
    }

    // Moyenne mois courant et mois précédent
    $cmq = @mysqli_query($con, "SELECT ROUND(AVG(p.sergio_score),2) AS avg FROM participation p JOIN activite a ON a.`id-activite`=p.`id-activite` WHERE p.`id-membre`='".intval($member_id)."' AND p.sergio_score IS NOT NULL AND DATE_FORMAT(a.date_depart,'%Y-%m')=DATE_FORMAT(NOW(),'%Y-%m')");
    if ($cmq && ($cmr = mysqli_fetch_assoc($cmq))) $curr_month_avg = $cmr['avg'] !== null ? round(floatval($cmr['avg']),2) : null;
    $pmq = @mysqli_query($con, "SELECT ROUND(AVG(p.sergio_score),2) AS avg FROM participation p JOIN activite a ON a.`id-activite`=p.`id-activite` WHERE p.`id-membre`='".intval($member_id)."' AND p.sergio_score IS NOT NULL AND DATE_FORMAT(a.date_depart,'%Y-%m')=DATE_FORMAT(DATE_SUB(NOW(),INTERVAL 1 MONTH),'%Y-%m')");
    if ($pmq && ($pmr = mysqli_fetch_assoc($pmq))) $prev_month_avg = $pmr['avg'] !== null ? round(floatval($pmr['avg']),2) : null;

    // Insights auto-générés
    if ($curr_month_avg !== null && $prev_month_avg !== null && $prev_month_avg > 0) {
        $delta_pct_ins = round(($curr_month_avg - $prev_month_avg) / $prev_month_avg * 100);
        if ($delta_pct_ins >= 0) {
            $insights[] = ['icon'=>'↗', 'color'=>'#4ade80', 'title'=>'En forme !', 'text'=>"Votre score moyen a augmenté de {$delta_pct_ins}% par rapport au mois dernier."];
        } else {
            $insights[] = ['icon'=>'↘', 'color'=>'#f87171', 'title'=>'En progression', 'text'=>"Votre score moyen a baissé de ".abs($delta_pct_ins)."% par rapport au mois dernier."];
        }
    }
    if ($extra_stats['parties'] > 0) {
        $itm_pct_ins = round($extra_stats['itm'] / $extra_stats['parties'] * 100);
        if ($itm_pct_ins >= 40) {
            $insights[] = ['icon'=>'🏆', 'color'=>'#fbbf24', 'title'=>'Régulier', 'text'=>"Vous avez fait l'ITM dans {$itm_pct_ins}% de vos parties."];
        }
    }
    if ($chal_rank !== null) {
        if ($chal_rank === 1) {
            $insights[] = ['icon'=>'🎯', 'color'=>'#a78bfa', 'title'=>'Objectif', 'text'=>"Maintenez ce rythme pour rester en tête du classement."];
        } else {
            $insights[] = ['icon'=>'🎯', 'color'=>'#a78bfa', 'title'=>'Objectif', 'text'=>"Continuez à progresser pour atteindre la 1ère place du classement."];
        }
    }
    if (empty($insights)) {
        $insights[] = ['icon'=>'📊', 'color'=>'#60a5fa', 'title'=>'Statistiques', 'text'=>"Jouez plus de parties pour débloquer des insights personnalisés."];
    }
}

// Couleur d'un score
if (!function_exists('scoreColor')) {
    function scoreColor($s) {
        if ($s === null) return 'var(--muted)';
        if ($s >= 18) return 'var(--gold)';
        if ($s >= 15) return 'var(--green)';
        if ($s >= 10) return 'var(--blue)';
        return 'var(--muted)';
    }
}

$months_fr = [1=>'Janvier',2=>'Février',3=>'Mars',4=>'Avril',5=>'Mai',6=>'Juin',
              7=>'Juillet',8=>'Août',9=>'Septembre',10=>'Octobre',11=>'Novembre',12=>'Décembre'];

if (!function_exists('scoreColorNew')) {
    function scoreColorNew($s) {
        if ($s === null) return '#8b98a6';
        if ($s >= 18)   return '#fbbf24';
        if ($s >= 15)   return '#4ade80';
        if ($s >= 10)   return '#60a5fa';
        return '#8b98a6';
    }
}
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>SergioScore — <?php echo h($pseudo); ?></title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{background:#080d1a;color:#f1f5f9;font-family:Inter,-apple-system,BlinkMacSystemFont,'Segoe UI',Arial,sans-serif;font-size:14px;min-height:100vh;padding-bottom:32px}
a{color:inherit;text-decoration:none}
.sg{max-width:640px;margin:0 auto;padding:0 12px 20px}

/* Nav */
.sg-nav{display:flex;align-items:center;justify-content:space-between;padding:16px 0 4px}
.sg-back{color:#f97316;font-weight:700;font-size:15px}

/* Header */
.sg-header{text-align:center;padding:10px 0 14px}
.sg-avatar{width:68px;height:68px;border-radius:50%;background:rgba(251,191,36,.15);border:2px solid #fbbf24;display:flex;align-items:center;justify-content:center;font-size:30px;margin:0 auto 8px}
.sg-title{font-size:20px;font-weight:900}
.sg-sub{color:#94a3b8;font-size:13px;margin-top:2px}

/* Hero card */
.sg-hero{background:#0f1629;border:1px solid rgba(255,255,255,.08);border-radius:16px;padding:20px 16px;text-align:center;margin-bottom:8px}
.sg-hero-lbl{font-size:10px;color:#94a3b8;letter-spacing:1.5px;text-transform:uppercase;margin-bottom:4px}
.sg-hero-score{font-size:38px;font-weight:900;letter-spacing:-1px;line-height:1.05}
.sg-hero-delta{font-size:12px;font-weight:600;margin-top:6px}

/* Stats row 1 */
.sg-row1{display:grid;grid-template-columns:repeat(4,1fr);gap:6px;margin-bottom:6px}
.sg-card{background:#0f1629;border:1px solid rgba(255,255,255,.07);border-radius:12px;padding:10px 6px 8px;text-align:center;display:flex;flex-direction:column;align-items:center;gap:2px}
.sg-card-icon{font-size:18px;margin-bottom:1px}
.sg-card-val{font-size:19px;font-weight:900;line-height:1}
.sg-card-lbl{font-size:9px;color:#94a3b8;text-transform:uppercase;letter-spacing:.4px;line-height:1.3}
.sg-card-sub{font-size:9px;color:#64748b}

/* Stats row 2 */
.sg-row2{background:#0f1629;border:1px solid rgba(255,255,255,.07);border-radius:12px;padding:10px 8px;display:grid;grid-template-columns:repeat(4,1fr);margin-bottom:10px}
.sg-s2{display:flex;flex-direction:column;align-items:center;gap:2px;padding:0 2px;border-right:1px solid rgba(255,255,255,.06)}
.sg-s2:last-child{border-right:none}
.sg-s2-lbl{font-size:8.5px;color:#94a3b8;text-transform:uppercase;letter-spacing:.3px}
.sg-s2-val{font-size:15px;font-weight:800}
.sg-s2-sub{font-size:8.5px;color:#64748b}

/* Chart */
.sg-chart{background:#0f1629;border:1px solid rgba(255,255,255,.07);border-radius:16px;padding:14px 12px;margin-bottom:10px}
.sg-chart-hdr{display:flex;align-items:center;justify-content:space-between;margin-bottom:8px}
.sg-chart-title{font-size:10px;color:#94a3b8;text-transform:uppercase;letter-spacing:.8px;font-weight:600}
.sg-chart-delta{font-size:10px;font-weight:700;padding:3px 8px;border-radius:20px}
.sg-chart-lbls{display:flex;justify-content:space-between;margin-top:6px}
.sg-lbl{flex:1;text-align:center;display:flex;flex-direction:column;gap:2px;cursor:pointer}
.sg-lbl-m{font-size:10px;font-weight:600;color:#94a3b8}
.sg-lbl-curr .sg-lbl-m{color:#4ade80;font-weight:800}
.sg-lbl-s{font-size:9px;font-weight:700}

/* Filters */
.sg-filters{display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-bottom:10px}
.sg-sel-wrap{position:relative}
.sg-sel{background:#0f1629;border:1px solid rgba(255,255,255,.12);border-radius:20px;color:#f1f5f9;padding:7px 26px 7px 12px;font-size:12px;outline:none;appearance:none;-webkit-appearance:none;cursor:pointer;font-family:inherit;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='9' height='5' viewBox='0 0 9 5'%3E%3Cpath d='M0 0l4.5 5L9 0z' fill='%2394a3b8'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 9px center}
.sg-reset{background:#0f1629;border:1px solid rgba(255,255,255,.12);border-radius:20px;color:#94a3b8;padding:7px 12px;font-size:12px;cursor:pointer;display:inline-flex;align-items:center;gap:5px}

/* Bottom grid */
.sg-bottom{display:grid;grid-template-columns:1fr 1fr;gap:8px}
@media(max-width:360px){.sg-bottom{grid-template-columns:1fr}}

/* Section card */
.sg-sec{background:#0f1629;border:1px solid rgba(255,255,255,.07);border-radius:16px;padding:14px 12px;display:flex;flex-direction:column}
.sg-sec-hdr{display:flex;align-items:center;justify-content:space-between;margin-bottom:10px}
.sg-sec-title{font-size:10px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.8px}
.sg-see-all{font-size:11px;font-weight:600;color:#60a5fa}

/* Game rows */
.sg-games{display:flex;flex-direction:column}
.sg-game{display:flex;align-items:center;gap:8px;padding:7px 0;border-bottom:1px solid rgba(255,255,255,.04)}
.sg-game:last-child{border-bottom:none}
.sg-gdate{display:flex;flex-direction:column;align-items:center;min-width:28px}
.sg-gday{font-size:13px;font-weight:800;line-height:1}
.sg-gmon{font-size:8px;color:#94a3b8;text-transform:uppercase;line-height:1.4}
.sg-ginfo{flex:1;min-width:0}
.sg-gtitle{font-size:11px;font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.sg-gsub{font-size:9.5px;color:#94a3b8;margin-top:1px}
.sg-gscore{text-align:right;min-width:42px}
.sg-gval{font-size:15px;font-weight:900;line-height:1;margin-bottom:3px}
.sg-gbar{background:rgba(255,255,255,.06);border-radius:2px;height:3px;width:42px}
.sg-gbar-fill{height:3px;border-radius:2px;min-width:3px}

/* Insights */
.sg-insights{display:flex;flex-direction:column;gap:8px}
.sg-ins{display:flex;align-items:flex-start;gap:9px;background:rgba(255,255,255,.03);border-radius:10px;padding:9px}
.sg-ins-icon{font-size:17px;min-width:22px;text-align:center;margin-top:1px}
.sg-ins-title{font-size:11px;font-weight:700;margin-bottom:2px}
.sg-ins-text{font-size:10px;color:#94a3b8;line-height:1.5}

/* Compare btn */
.sg-cmp{display:flex;align-items:center;justify-content:center;gap:6px;border:1px solid rgba(96,165,250,.3);color:#60a5fa;border-radius:12px;padding:10px;font-size:11px;font-weight:700;text-align:center;margin-top:12px}
.sg-cmp:hover{background:rgba(96,165,250,.08)}

/* Footer */
.sg-foot{text-align:center;color:#374151;font-size:11px;padding-top:16px}
.sg-nodata{color:#94a3b8;text-align:center;padding:20px 0;font-size:12px}
</style>
</head>
<body>
<div class="sg">

<div class="sg-nav">
  <a href="/panel/quickview.php" class="sg-back">‹ Retour</a>
</div>

<?php if (!$member_id): ?>
  <div style="text-align:center;color:#94a3b8;margin-top:80px">Aucun joueur sélectionné.</div>
<?php else: ?>

<!-- Header -->
<div class="sg-header">
  <h1 class="sg-title">SergioScore ⭐</h1>
  <p class="sg-sub"><?php echo h($pseudo); ?></p>
</div>

<!-- Hero score -->
<div class="sg-hero">
  <div class="sg-hero-lbl">SCORE MOYEN</div>
  <div class="sg-hero-score" style="color:<?php echo scoreColorNew($stats['avg']); ?>"><?php echo $stats['avg'] ?? '—'; ?></div>
  <?php
  if ($curr_month_avg !== null && $prev_month_avg !== null) {
      $dv = round($curr_month_avg - $prev_month_avg, 2);
      $dp = $dv >= 0;
      echo '<div class="sg-hero-delta" style="color:'.($dp?'#4ade80':'#f87171').'">'.($dp?'↗ +':'↘ ').$dv.' vs mois dernier</div>';
  }
  ?>
</div>

<?php
$itm_pct = $extra_stats['parties'] > 0 ? round($extra_stats['itm'] / $extra_stats['parties'] * 100) : 0;
$tf_pct  = $extra_stats['parties'] > 0 ? round($extra_stats['tf']  / $extra_stats['parties'] * 100) : 0;
?>

<!-- Stats row 1 -->
<div class="sg-row1">
  <div class="sg-card">
    <div class="sg-card-icon">📅</div>
    <div class="sg-card-val" style="color:#60a5fa"><?php echo $stats['count']; ?></div>
    <div class="sg-card-lbl">PARTIES</div>
    <div class="sg-card-sub"><?php echo $filter_month ? 'ce mois-ci' : 'au total'; ?></div>
  </div>
  <div class="sg-card" onclick="document.getElementById('itm-modal').style.display='flex'" style="cursor:pointer">
    <div class="sg-card-icon">🏆</div>
    <div class="sg-card-val" style="color:#fbbf24"><?php echo $itm_pct; ?>%</div>
    <div class="sg-card-lbl">ITM</div>
    <div class="sg-card-sub"><?php echo $extra_stats['itm']; ?> / <?php echo $extra_stats['parties']; ?></div>
  </div>
  <div class="sg-card">
    <div class="sg-card-icon">⭐</div>
    <?php if ($sergio_global_rank): ?>
    <div class="sg-card-val" style="color:#fbbf24">#<?php echo $sergio_global_rank; ?></div>
    <div class="sg-card-lbl">SERGIO GLOBAL</div>
    <div class="sg-card-sub">sur <?php echo $sergio_global_total; ?> · min.6 · 1 an</div>
    <?php else: ?>
    <div class="sg-card-val" style="color:#64748b">—</div>
    <div class="sg-card-lbl"Classement Général"</div>
    <div class="sg-card-sub">min. 6 parties · 1 an</div>
    <?php endif; ?>
  </div>
  <div class="sg-card">
    <div class="sg-card-icon">🏅</div>
    <?php if ($chal_rank): ?>
    <div class="sg-card-val" style="color:#a78bfa">#<?php echo $chal_rank; ?></div>
    <div class="sg-card-lbl">CLASSEMENT</div>
    <div class="sg-card-sub">sur <?php echo $chal_total; ?> joueurs</div>
    <?php else: ?>
    <div class="sg-card-val" style="color:#64748b">—</div>
    <div class="sg-card-lbl">CLASSEMENT</div>
    <div class="sg-card-sub">—</div>
    <?php endif; ?>
  </div>
</div>

<!-- Stats row 2 -->
<div class="sg-row2">
  <div class="sg-s2">
    <span class="sg-s2-lbl">TF</span>
    <span class="sg-s2-val"><?php echo $tf_pct; ?>%</span>
    <span class="sg-s2-sub"><?php echo $extra_stats['tf']; ?> / <?php echo $extra_stats['parties']; ?></span>
  </div>
  <div class="sg-s2">
    <span class="sg-s2-lbl">RECAVES</span>
    <span class="sg-s2-val"><?php echo $extra_stats['recaves']; ?></span>
    <span class="sg-s2-sub"><?php echo $extra_stats['recaves']; ?> / <?php echo $extra_stats['parties']; ?></span>
  </div>
  <div class="sg-s2">
    <span class="sg-s2-lbl">PIRE PLACE</span>
    <span class="sg-s2-val"><?php echo $extra_stats['worst_rank'] ?? '—'; ?><?php if (!empty($extra_stats['worst_rank_total'])) echo ' <span style="font-size:0.6em;opacity:0.6">/ ' . $extra_stats['worst_rank_total'] . '</span>'; ?></span>
  </div>
  <div class="sg-s2">
    <span class="sg-s2-lbl">TOP 1</span>
    <span class="sg-s2-val"><?php echo $extra_stats['top1'] ?? 0; ?></span>
    <span class="sg-s2-sub">fois</span>
  </div>
</div>

<!-- SVG Line Chart -->
<?php if (!empty($monthly)):
  $cd = array_slice($monthly, -12);
  $cn = count($cd);
  $cv = array_map(fn($m) => floatval($m['avg_score']), $cd);
  $cmin = min($cv); $cmax = max($cv);
  $crng = max($cmax - $cmin, 0.5);
  $sw=400;$sh=100;$px=14;$py=12;
  $cpts=[];
  for($i=0;$i<$cn;$i++){
    $x=$cn>1?$px+$i*($sw-2*$px)/($cn-1):$sw/2;
    $y=$sh-$py-($cv[$i]-$cmin)/$crng*($sh-2*$py);
    $cpts[]=['x'=>round($x,1),'y'=>round($y,1)];
  }
  $cpd="M {$cpts[0]['x']} {$cpts[0]['y']}";
  for($i=1;$i<$cn;$i++){
    $c1x=round($cpts[$i-1]['x']+($cpts[$i]['x']-$cpts[$i-1]['x'])/3,1);
    $c2x=round($cpts[$i]['x']-($cpts[$i]['x']-$cpts[$i-1]['x'])/3,1);
    $cpd.=" C $c1x {$cpts[$i-1]['y']} $c2x {$cpts[$i]['y']} {$cpts[$i]['x']} {$cpts[$i]['y']}";
  }
  $cpfill=$cpd." L {$cpts[$cn-1]['x']} $sh L {$cpts[0]['x']} $sh Z";
  $cds='';$cdp=true;
  if($cn>=2&&$cv[$cn-2]>0){$cdv=round(($cv[$cn-1]-$cv[$cn-2])/$cv[$cn-2]*100);$cdp=$cdv>=0;$cds=($cdp?'↗ +':'↘ ').$cdv.'% vs mois dernier';}
  $msm=['01'=>'Jan','02'=>'Fév','03'=>'Mar','04'=>'Avr','05'=>'Mai','06'=>'Juin','07'=>'Juil','08'=>'Aoû','09'=>'Sep','10'=>'Oct','11'=>'Nov','12'=>'Déc'];
?>
<div class="sg-chart">
  <div class="sg-chart-hdr">
    <span class="sg-chart-title">PERFORMANCE MENSUELLE (<?php echo $cn; ?> MOIS)</span>
    <?php if($cds): ?><span class="sg-chart-delta" style="color:<?php echo $cdp?'#4ade80':'#f87171'; ?>;background:<?php echo $cdp?'rgba(74,222,128,0.12)':'rgba(248,113,113,0.12)'; ?>"><?php echo $cds; ?></span><?php endif; ?>
  </div>
  <svg viewBox="0 0 <?php echo $sw; ?> <?php echo $sh; ?>" preserveAspectRatio="none" style="width:100%;height:72px;display:block">
    <defs>
      <linearGradient id="sgLg" x1="0" y1="0" x2="1" y2="0">
        <stop offset="0%" stop-color="#fb923c"/>
        <stop offset="100%" stop-color="#4ade80"/>
      </linearGradient>
      <linearGradient id="sgFg" x1="0" y1="0" x2="0" y2="1">
        <stop offset="0%" stop-color="#4ade80" stop-opacity="0.22"/>
        <stop offset="100%" stop-color="#4ade80" stop-opacity="0.01"/>
      </linearGradient>
    </defs>
    <line x1="<?php echo $px;?>" y1="<?php echo $py;?>" x2="<?php echo $sw-$px;?>" y2="<?php echo $py;?>" stroke="rgba(255,255,255,0.05)" stroke-width="1"/>
    <line x1="<?php echo $px;?>" y1="<?php echo $sh/2;?>" x2="<?php echo $sw-$px;?>" y2="<?php echo $sh/2;?>" stroke="rgba(255,255,255,0.05)" stroke-width="1"/>
    <path d="<?php echo $cpfill; ?>" fill="url(#sgFg)"/>
    <path d="<?php echo $cpd; ?>" fill="none" stroke="url(#sgLg)" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
    <?php foreach($cpts as $pi=>$pt): $il=$pi===$cn-1; ?>
    <circle cx="<?php echo $pt['x'];?>" cy="<?php echo $pt['y'];?>" r="<?php echo $il?4.5:3;?>" fill="<?php echo $il?'#4ade80':'rgba(255,255,255,0.45)';?>" <?php echo $il?'stroke="#0f1629" stroke-width="2"':'';?>/>
    <?php endforeach; ?>
  </svg>
  <div class="sg-chart-lbls">
    <?php foreach($cd as $idx=>$mb):
      $mn=substr($mb['mois'],5); $il=$idx===$cn-1;
      $ia=($filter_year==substr($mb['mois'],0,4)&&$filter_month==intval($mn));
      $lu='?mid='.intval($member_id).'&y='.substr($mb['mois'],0,4).'&m='.intval($mn);
    ?>
    <a href="<?php echo $ia?'?mid='.intval($member_id):$lu; ?>" class="sg-lbl <?php echo $il?'sg-lbl-curr':''; ?>">
      <div class="sg-lbl-m"><?php echo $msm[$mn]??$mn; ?></div>
      <div class="sg-lbl-s" style="color:<?php echo scoreColorNew(floatval($mb['avg_score'])); ?>"><?php echo $mb['avg_score']; ?></div>
    </a>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<!-- Filters -->
<div class="sg-filters">
  <form method="get" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
    <input type="hidden" name="mid" value="<?php echo intval($member_id); ?>">
    <div class="sg-sel-wrap">
      <select name="y" onchange="this.form.submit()" class="sg-sel">
        <option value="">Toutes les années</option>
        <?php foreach($years as $yr): ?>
        <option value="<?php echo $yr; ?>" <?php echo $filter_year===$yr?'selected':''; ?>><?php echo $yr; ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="sg-sel-wrap">
      <select name="m" onchange="this.form.submit()" class="sg-sel">
        <option value="">Tous les mois</option>
        <?php foreach($months_fr as $mn=>$ml): ?>
        <option value="<?php echo $mn; ?>" <?php echo $filter_month===$mn?'selected':''; ?>><?php echo $ml; ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <?php if($filter_year||$filter_month): ?>
    <a href="?mid=<?php echo intval($member_id); ?>" class="sg-reset">↺ Réinitialiser</a>
    <?php else: ?>
    <span class="sg-reset" style="opacity:.45;cursor:default">↺ Réinitialiser</span>
    <?php endif; ?>
  </form>
</div>

<!-- Bottom grid -->
<div class="sg-bottom">
  <!-- Dernières parties -->
  <div class="sg-sec">
    <div class="sg-sec-hdr">
      <span class="sg-sec-title">DERNIÈRES PARTIES</span>
      <?php if(count($rows)>5): ?>
      <a href="javascript:void(0)" class="sg-see-all" onclick="document.getElementById('sg-more').style.display='flex';this.style.display='none'">Voir tout ›</a>
      <?php endif; ?>
    </div>
    <?php if(empty($rows)): ?>
    <div class="sg-nodata">Aucune donnée.</div>
    <?php else:
      $mfr=['Jan'=>'JAN','Feb'=>'FÉV','Mar'=>'MAR','Apr'=>'AVR','May'=>'MAI','Jun'=>'JUI','Jul'=>'JUI','Aug'=>'AOÛ','Sep'=>'SEP','Oct'=>'OCT','Nov'=>'NOV','Dec'=>'DÉC'];
    ?>
    <div class="sg-games">
    <?php foreach(array_slice($rows,0,5) as $r):
      $sv=round(floatval($r['sergio_score']),2); $cl=scoreColorNew($sv);
      $dt=strtotime($r['date_depart']); $dy=$dt?date('d',$dt):'—';
      $mfr2=$mfr[$dt?date('M',$dt):'']??strtoupper(date('M',$dt));
      $bp=min(100,round($sv/20*100));
      $titre_clean=trim(preg_replace('/\s*\(.*?\)/','', $r['titre']??''));
    ?>
    <div class="sg-game">
      <div class="sg-gdate"><span class="sg-gday"><?php echo $dy;?></span><span class="sg-gmon"><?php echo $mfr2;?></span></div>
      <div class="sg-ginfo">
        <div class="sg-gtitle"><?php echo h($titre_clean);?></div>
        <div class="sg-gsub"><?php echo intval($r['nb_joueurs']);?> joueurs · <?php echo intval($r['classement'])>0?intval($r['classement']).'e place':'—';?></div>
      </div>
      <div class="sg-gscore">
        <div class="sg-gval" style="color:<?php echo $cl;?>"><?php echo $sv;?></div>
        <div class="sg-gbar"><div class="sg-gbar-fill" style="width:<?php echo $bp;?>%;background:<?php echo $cl;?>"></div></div>
      </div>
    </div>
    <?php endforeach; ?>
    </div>
    <?php if(count($rows)>5): ?>
    <div id="sg-more" style="display:none;flex-direction:column" class="sg-games">
    <?php foreach(array_slice($rows,5) as $r):
      $sv=round(floatval($r['sergio_score']),2); $cl=scoreColorNew($sv);
      $dt=strtotime($r['date_depart']); $dy=$dt?date('d',$dt):'—';
      $mfr2=$mfr[$dt?date('M',$dt):'']??strtoupper(date('M',$dt));
      $bp=min(100,round($sv/20*100));
      $titre_clean=trim(preg_replace('/\s*\(.*?\)/','', $r['titre']??''));
    ?>
    <div class="sg-game">
      <div class="sg-gdate"><span class="sg-gday"><?php echo $dy;?></span><span class="sg-gmon"><?php echo $mfr2;?></span></div>
      <div class="sg-ginfo">
        <div class="sg-gtitle"><?php echo h($titre_clean);?></div>
        <div class="sg-gsub"><?php echo intval($r['nb_joueurs']);?> joueurs · <?php echo intval($r['classement'])>0?intval($r['classement']).'e place':'—';?></div>
      </div>
      <div class="sg-gscore">
        <div class="sg-gval" style="color:<?php echo $cl;?>"><?php echo $sv;?></div>
        <div class="sg-gbar"><div class="sg-gbar-fill" style="width:<?php echo $bp;?>%;background:<?php echo $cl;?>"></div></div>
      </div>
    </div>
    <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>
  </div>

  <!-- Insights -->
  <div class="sg-sec">
    <div class="sg-sec-hdr">
      <span class="sg-sec-title">INSIGHTS</span>
    </div>
    <div class="sg-insights">
      <?php foreach($insights as $ins): ?>
      <div class="sg-ins">
        <div class="sg-ins-icon" style="color:<?php echo $ins['color'];?>"><?php echo $ins['icon'];?></div>
        <div>
          <div class="sg-ins-title" style="color:<?php echo $ins['color'];?>"><?php echo h($ins['title']);?></div>
          <div class="sg-ins-text"><?php echo h($ins['text']);?></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <a href="/panel/classement.php" class="sg-cmp">📊 Comparer avec d'autres joueurs</a>
  </div>
</div>

<?php endif; // member_id ?>
</div>

<!-- Modal ITM -->
<div id="itm-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.75);z-index:999;align-items:flex-end;justify-content:center" onclick="if(event.target===this)this.style.display='none'">
  <div style="background:#0f1629;border-radius:16px 16px 0 0;width:100%;max-width:520px;max-height:80vh;display:flex;flex-direction:column;overflow:hidden;border-top:1px solid rgba(255,255,255,.1)">
    <div style="display:flex;align-items:center;justify-content:space-between;padding:14px 16px;border-bottom:1px solid rgba(255,255,255,.07)">
      <span style="font-weight:800;font-size:15px;color:#fbbf24">🏆 ITM — <?php echo h($pseudo); ?></span>
      <button onclick="document.getElementById('itm-modal').style.display='none'" style="background:none;border:none;color:#94a3b8;font-size:22px;cursor:pointer;line-height:1">×</button>
    </div>
    <div style="overflow-y:auto;padding:0 0 16px">
    <?php if(empty($itm_rows)): ?>
      <div style="text-align:center;color:#94a3b8;padding:30px">Aucun ITM enregistré.</div>
    <?php else: ?>
      <table style="width:100%;border-collapse:collapse;font-size:13px">
        <thead>
          <tr style="border-bottom:1px solid rgba(255,255,255,.07)">
            <th style="padding:8px 14px;color:#94a3b8;font-weight:600;text-align:left;font-size:11px;text-transform:uppercase">Date</th>
            <th style="padding:8px 8px;color:#94a3b8;font-weight:600;text-align:left;font-size:11px;text-transform:uppercase">Partie</th>
            <th style="padding:8px 8px;color:#94a3b8;font-weight:600;text-align:center;font-size:11px;text-transform:uppercase">Place</th>
            <th style="padding:8px 14px;color:#94a3b8;font-weight:600;text-align:right;font-size:11px;text-transform:uppercase">Gain</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach($itm_rows as $ir):
          $idt=strtotime($ir['date_depart']); $ids=$idt?date('d/m/Y',$idt):h($ir['date_depart']); $gain=intval($ir['gain']);
        ?>
          <tr style="border-bottom:1px solid rgba(255,255,255,.04)">
            <td style="padding:8px 14px;color:#94a3b8;white-space:nowrap;font-size:12px"><?php echo $ids;?></td>
            <td style="padding:8px 8px;font-weight:600;max-width:140px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?php echo h($ir['titre']);?></td>
            <td style="padding:8px 8px;text-align:center;color:#60a5fa"><?php echo $ir['classement']>0?'#'.intval($ir['classement']):'—';?></td>
            <td style="padding:8px 14px;text-align:right;color:#fbbf24;font-weight:700"><?php echo '+'.number_format($gain,2,',',' ').' €';?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
    </div>
  </div>
</div>
</body>
</html>
