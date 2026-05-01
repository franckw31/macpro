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
$extra_stats = ['parties' => 0, 'tf' => 0, 'itm' => 0, 'recaves' => 0];
$itm_rows   = [];
$chal_rank  = null;
$chal_total = null;
$chal_title = '';

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
            COALESCE(SUM(COALESCE(p.recave,0)),0)                                          AS total_recaves
        FROM participation p
        WHERE p.`id-membre` = '".intval($member_id)."'
          AND p.classement != 0
          AND p.classement != 50
          AND p.sergio_score > 0
    ");
    if ($eq && ($er = mysqli_fetch_assoc($eq))) {
        $extra_stats = [
            'parties' => intval($er['total_parties']),
            'tf'      => intval($er['total_tf']),
            'itm'     => intval($er['total_itm']),
            'recaves' => intval($er['total_recaves']),
        ];
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
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>SergioScore — <?php echo h($pseudo); ?></title>
    <style>
    :root{--muted:#8b98a6;--gold:#ffb400;--orange:#ff7a45;--green:#18b041;--purple:#9b59ff;--blue:#00b6ff;--bg:#071019;--card:#0d1d2b}
    *{box-sizing:border-box;margin:0;padding:0}
    body{background:var(--bg);color:#eef6fb;font-family:Inter,system-ui,-apple-system,Arial;font-size:14px;padding-bottom:40px}
    a{color:var(--orange);text-decoration:none}
    a:hover{text-decoration:underline}

    .page{max-width:720px;margin:0 auto;padding:14px 12px 14px;position:relative}
    .close-btn{position:absolute;top:14px;right:12px;padding:6px 10px;border-radius:8px;background:transparent;border:1px solid rgba(255,255,255,0.08);color:var(--orange);font-weight:700;font-size:13px}

    /* Header */
    .hero{margin-top:10px;text-align:center}
    .hero h1{font-size:22px;font-weight:900;color:var(--gold)}
    .hero .sub{color:var(--muted);margin-top:4px;font-size:13px}

    /* Stats cards */
    .stat-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:8px;margin-top:16px}
    .stat-card{background:var(--card);border-radius:10px;padding:12px 8px;text-align:center;border:1px solid rgba(255,255,255,0.05)}
    .stat-card .val{font-size:22px;font-weight:900;margin-bottom:4px}
    .stat-card .lbl{font-size:11px;color:var(--muted);text-transform:uppercase;letter-spacing:.5px}

    /* Sparkline */
    .chart-wrap{background:var(--card);border-radius:10px;padding:14px 12px;margin-top:12px;border:1px solid rgba(255,255,255,0.05);overflow-x:auto}
    .chart-title{font-size:12px;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:10px}
    .sparkline{display:flex;align-items:flex-end;gap:4px;height:60px;min-width:0}
    .spark-bar{flex:1;border-radius:3px 3px 0 0;min-width:18px;position:relative;cursor:default;transition:opacity .15s}
    .spark-bar:hover{opacity:.8}
    .spark-bar .tip{display:none;position:absolute;bottom:calc(100% + 4px);left:50%;transform:translateX(-50%);background:#1a2d3d;border:1px solid rgba(255,255,255,.1);border-radius:6px;padding:4px 7px;font-size:11px;white-space:nowrap;pointer-events:none;z-index:10}
    .spark-bar:hover .tip{display:block}
    .spark-labels{display:flex;gap:4px;margin-top:4px}
    .spark-lbl{flex:1;font-size:9px;color:var(--muted);text-align:center;overflow:hidden;white-space:nowrap;text-overflow:ellipsis;line-height:1.4;min-width:18px}

    /* Filters */
    .filters{display:flex;flex-wrap:wrap;gap:8px;margin-top:14px;align-items:center}
    .filters select,.filters a.pill{background:var(--card);border:1px solid rgba(255,255,255,0.08);border-radius:20px;color:#eef6fb;padding:5px 14px;font-size:13px;cursor:pointer;outline:none;appearance:none;-webkit-appearance:none}
    .filters select{padding-right:24px;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6' viewBox='0 0 10 6'%3E%3Cpath d='M0 0l5 6 5-6z' fill='%238b98a6'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 8px center}
    .filters a.pill{color:var(--muted)}
    .filters a.pill.active,.filters a.pill:hover{color:var(--orange);border-color:var(--orange)}

    /* Table */
    .section-title{font-size:13px;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;margin:16px 0 8px}
    .table-wrap{overflow-x:auto;-webkit-overflow-scrolling:touch;border-radius:8px}
    .hist-table{width:100%;border-collapse:collapse;min-width:480px}
    .hist-table th{font-size:11px;color:var(--muted);text-align:left;padding:6px 8px;border-bottom:1px solid rgba(255,255,255,.06);font-weight:600;text-transform:uppercase;letter-spacing:.3px;white-space:nowrap}
    .hist-table th.r,.hist-table td.r{text-align:right}
    .hist-table th.c,.hist-table td.c{text-align:center}
    .hist-table tbody tr{border-bottom:1px solid rgba(255,255,255,.03);transition:background .1s}
    .hist-table tbody tr:hover{background:rgba(255,255,255,.025)}
    .hist-table td{padding:7px 8px;vertical-align:middle}
    .td-date{color:var(--muted);font-size:12px;white-space:nowrap}
    .td-titre{font-weight:600;max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
    .badge{display:inline-block;padding:2px 8px;border-radius:12px;font-size:12px;font-weight:700;background:rgba(255,255,255,.06)}
    .no-data{color:var(--muted);text-align:center;padding:30px 0;font-size:13px}

    /* ── Responsive ─────────────────────────────────────────── */
    @media(max-width:520px){
        .page{padding:10px 8px}
        .close-btn{font-size:12px;padding:5px 8px;top:10px;right:8px}
        .hero{margin-top:36px}
        .hero h1{font-size:18px}
        .stat-grid{grid-template-columns:repeat(4,1fr);gap:4px}
        .stat-card .val{font-size:15px}
        .stat-card .lbl{font-size:9px}
        .stat-card{padding:8px 4px}
        .filters select,.filters a.pill{font-size:12px;padding:5px 10px}
        .td-titre{max-width:100px}
        .hist-table{min-width:420px}
        .hist-table th,.hist-table td{padding:6px 5px;font-size:11px}
        .badge{padding:2px 5px;font-size:11px}
    }
    </style>
</head>
<body>
<div class="page">
    <a href="/panel/quickview.php" class="close-btn">← Retour</a>

    <?php if (!$member_id): ?>
        <div style="margin-top:60px;text-align:center;color:var(--muted)">Aucun joueur sélectionné.</div>
    <?php else: ?>

    <div class="hero">
        <h1>⭐ SergioScore</h1>
        <div class="sub"><?php echo h($pseudo); ?></div>
    </div>

    <!-- Stats cards -->
    <div class="stat-grid">
        <div class="stat-card">
            <div class="val" style="color:var(--blue)"><?php echo $stats['count']; ?></div>
            <div class="lbl">Parties</div>
        </div>
        <div class="stat-card">
            <div class="val" style="color:<?php echo scoreColor($stats['avg']); ?>"><?php echo $stats['avg'] ?? '—'; ?></div>
            <div class="lbl">Moyenne</div>
        </div>
        <div class="stat-card">
            <div class="val" style="color:var(--gold)"><?php echo $stats['best'] ?? '—'; ?></div>
            <div class="lbl">La Meilleure</div>
        </div>
        <div class="stat-card">
            <div class="val" style="color:#ff6b6b"><?php echo $stats['worst'] ?? '—'; ?></div>
            <div class="lbl">La Pire</div>
        </div>
    </div>

    <!-- Stats extra row 2 -->
    <div class="stat-grid" style="margin-top:6px">
        <div class="stat-card">
            <div class="val" style="color:var(--green);font-size:17px"><?php echo $extra_stats['tf']; ?><span style="color:var(--muted);font-size:12px;font-weight:500"> / <?php echo $extra_stats['parties']; ?></span></div>
            <div class="lbl">TF</div>
        </div>
        <div class="stat-card" onclick="document.getElementById('itm-modal').style.display='flex'" style="cursor:pointer">
            <div class="val" style="color:var(--gold);font-size:17px;text-decoration:underline dotted"><?php echo $extra_stats['itm']; ?><span style="color:var(--muted);font-size:12px;font-weight:500"> / <?php echo $extra_stats['parties']; ?></span></div>
            <div class="lbl">ITM</div>
        </div>
        <div class="stat-card">
            <div class="val" style="color:var(--orange);font-size:17px"><?php echo $extra_stats['recaves']; ?><span style="color:var(--muted);font-size:12px;font-weight:500"> / <?php echo $extra_stats['parties']; ?></span></div>
            <div class="lbl">Recaves</div>
        </div>
        <div class="stat-card" title="<?php echo h($chal_title); ?>">
            <?php if ($chal_rank): ?>
            <div class="val" style="color:var(--purple)">#<?php echo $chal_rank; ?></div>
            <div class="lbl" style="font-size:9px">Challenge</div>
            <?php else: ?>
            <div class="val" style="color:var(--muted)">—</div>
            <div class="lbl">Challenge</div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Sparkline mensuelle -->
    <?php if (!empty($monthly)): ?>
    <div class="chart-wrap">
        <div class="chart-title">Moyenne mensuelle (<?php echo count($monthly); ?> mois)</div>
        <?php
        $max_val = max(array_map(function($m){ return floatval($m['avg_score']); }, $monthly));
        $max_val = max($max_val, 1);
        ?>
        <div class="sparkline">
            <?php foreach ($monthly as $mb):
                $pct = max(8, min(85, round((floatval($mb['avg_score']) / $max_val) * 85)));
                $col = scoreColor(floatval($mb['avg_score']));
                $mb_y = substr($mb['mois'], 0, 4);
                $mb_m = intval(substr($mb['mois'], 5));
                $is_active = ($filter_year == $mb_y && $filter_month == $mb_m);
                $bar_url = '?mid='.intval($member_id).'&y='.$mb_y.'&m='.$mb_m;
                $bar_url_clear = ($is_active) ? '?mid='.intval($member_id) : $bar_url;
            ?>
            <a href="<?php echo $bar_url_clear; ?>" class="spark-bar" style="height:<?php echo $pct; ?>%;background:<?php echo $col; ?>;opacity:<?php echo $is_active ? '1' : '.85'; ?>;text-decoration:none;outline:<?php echo $is_active ? '2px solid #fff' : 'none'; ?>;outline-offset:2px">
                <div class="tip"><?php echo h($mb['mois']); ?><br>Moy : <?php echo $mb['avg_score']; ?><br><?php echo $mb['cnt']; ?> partie(s)<br><?php echo $is_active ? '✕ Annuler filtre' : 'Cliquer pour filtrer'; ?></div>
            </a>
            <?php endforeach; ?>
        </div>
        <div class="spark-labels">
            <?php foreach ($monthly as $mb): ?>
            <?php
                $months_short = ['01'=>'Jan','02'=>'Fév','03'=>'Mar','04'=>'Avr','05'=>'Mai','06'=>'Juin','07'=>'Juil','08'=>'Aoû','09'=>'Sep','10'=>'Oct','11'=>'Nov','12'=>'Déc'];
                $mois_num = substr($mb['mois'], 5);
                $col_lbl  = scoreColor(floatval($mb['avg_score']));
            ?>
            <a href="<?php echo ($filter_year == substr($mb['mois'],0,4) && $filter_month == intval(substr($mb['mois'],5))) ? '?mid='.intval($member_id) : '?mid='.intval($member_id).'&y='.substr($mb['mois'],0,4).'&m='.intval(substr($mb['mois'],5)); ?>" class="spark-lbl" style="text-decoration:none;cursor:pointer;<?php echo ($filter_year == substr($mb['mois'],0,4) && $filter_month == intval(substr($mb['mois'],5))) ? 'background:rgba(255,255,255,.12);border-radius:6px;' : ''; ?>">
                <div style="font-weight:700;color:#eef6fb"><?php echo $months_short[$mois_num] ?? $mois_num; ?></div>
                <div style="font-size:8px;color:var(--muted)"><?php echo $mb['cnt']; ?> ptie<?php echo $mb['cnt'] > 1 ? 's' : ''; ?></div>
                <div style="font-size:8px;color:<?php echo $col_lbl; ?>;font-weight:700"><?php echo $mb['avg_score']; ?></div>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Filtres -->
    <div class="filters">
        <form method="get" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
            <input type="hidden" name="mid" value="<?php echo intval($member_id); ?>">

            <select name="y" onchange="this.form.submit()">
                <option value="">Toutes les années</option>
                <?php foreach ($years as $yr): ?>
                <option value="<?php echo $yr; ?>" <?php echo ($filter_year === $yr ? 'selected' : ''); ?>><?php echo $yr; ?></option>
                <?php endforeach; ?>
            </select>

            <select name="m" onchange="this.form.submit()">
                <option value="">Tous les mois</option>
                <?php foreach ($months_fr as $mn => $ml): ?>
                <option value="<?php echo $mn; ?>" <?php echo ($filter_month === $mn ? 'selected' : ''); ?>><?php echo $ml; ?></option>
                <?php endforeach; ?>
            </select>

            <?php if ($filter_year || $filter_month): ?>
            <a href="?mid=<?php echo intval($member_id); ?>" class="pill">✕ Réinitialiser</a>
            <?php endif; ?>
        </form>

        <?php if ($filter_year || $filter_month): ?>
        <span style="color:var(--muted);font-size:12px">
            <?php echo $stats['count']; ?> partie(s) — moy. <strong style="color:<?php echo scoreColor($stats['avg']); ?>"><?php echo $stats['avg'] ?? '—'; ?></strong>
        </span>
        <?php endif; ?>
    </div>

    <!-- Historique -->
    <div class="section-title">Historique des parties</div>

    <?php if (empty($rows)): ?>
        <div class="no-data">Aucune donnée SergioScore enregistrée.<br><span style="font-size:12px">Les scores sont sauvegardés à chaque consultation de la page résultats.</span></div>
    <?php else: ?>
    <div class="table-wrap">
    <table class="hist-table">
        <thead>
            <tr>
                <th>Date</th>
                <th>Partie</th>
                <th class="c">Place</th>
                <th class="c" title="Recaves du joueur">R.J</th>
                <th class="c">Joueurs</th>
                <th class="c" title="Total recaves de la partie">R.T</th>
                <th class="r">Score</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $row):
            $score_val = round(floatval($row['sergio_score']), 2);
            $col       = scoreColor($score_val);
            $dt        = strtotime($row['date_depart']);
            $date_str  = $dt ? date('d/m/Y', $dt) : h($row['date_depart']);
            $place     = intval($row['classement']);
        ?>
            <tr>
                <td class="td-date"><?php echo $date_str; ?></td>
                <td class="td-titre" title="<?php echo h($row['titre']); ?>"><?php echo h($row['titre']); ?></td>
                <td class="c"><span class="badge"><?php echo $place > 0 ? $place : '—'; ?></span></td>
                <td class="c" style="color:<?php echo intval($row['recave_joueur']) > 0 ? 'var(--orange)' : 'var(--muted)'; ?>"><?php echo intval($row['recave_joueur']) ?: '—'; ?></td>
                <td class="c" style="color:var(--muted)"><?php echo intval($row['nb_joueurs']); ?></td>
                <td class="c" style="color:var(--muted)"><?php echo intval($row['total_recaves']) ?: '—'; ?></td>
                <td class="r"><span class="badge" style="color:<?php echo $col; ?>;background:rgba(255,255,255,.05)"><?php echo $score_val; ?></span></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>

    <?php endif; // member_id ?>
</div>

<!-- ── Modal ITM ─────────────────────────────────────────────────────── -->
<div id="itm-modal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.7);z-index:999;align-items:flex-end;justify-content:center" onclick="if(event.target===this)this.style.display='none'">
  <div style="background:#0d1d2b;border-radius:16px 16px 0 0;width:100%;max-width:520px;max-height:80vh;display:flex;flex-direction:column;overflow:hidden">
    <div style="display:flex;align-items:center;justify-content:space-between;padding:14px 16px;border-bottom:1px solid rgba(255,255,255,.07)">
      <span style="font-weight:800;font-size:15px;color:var(--gold)">⭐ ITM — <?php echo h($pseudo); ?></span>
      <button onclick="document.getElementById('itm-modal').style.display='none'" style="background:none;border:none;color:var(--muted);font-size:20px;cursor:pointer;line-height:1">×</button>
    </div>
    <div style="overflow-y:auto;padding:0 0 16px">
    <?php if (empty($itm_rows)): ?>
      <div style="text-align:center;color:var(--muted);padding:30px">Aucun ITM enregistré.</div>
    <?php else: ?>
      <table style="width:100%;border-collapse:collapse;font-size:13px">
        <thead>
          <tr style="border-bottom:1px solid rgba(255,255,255,.07)">
            <th style="padding:8px 14px;color:var(--muted);font-weight:600;text-align:left;font-size:11px;text-transform:uppercase">Date</th>
            <th style="padding:8px 8px;color:var(--muted);font-weight:600;text-align:left;font-size:11px;text-transform:uppercase">Partie</th>
            <th style="padding:8px 8px;color:var(--muted);font-weight:600;text-align:center;font-size:11px;text-transform:uppercase">Place</th>
            <th style="padding:8px 14px;color:var(--muted);font-weight:600;text-align:right;font-size:11px;text-transform:uppercase">Gain</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($itm_rows as $ir):
            $idt  = strtotime($ir['date_depart']);
            $ids  = $idt ? date('d/m/Y', $idt) : h($ir['date_depart']);
            $gain = intval($ir['gain']);
        ?>
          <tr style="border-bottom:1px solid rgba(255,255,255,.04)">
            <td style="padding:8px 14px;color:var(--muted);white-space:nowrap;font-size:12px"><?php echo $ids; ?></td>
            <td style="padding:8px 8px;font-weight:600;max-width:140px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?php echo h($ir['titre']); ?></td>
            <td style="padding:8px 8px;text-align:center;color:var(--blue)"><?php echo $ir['classement'] > 0 ? '#'.intval($ir['classement']) : '—'; ?></td>
            <td style="padding:8px 14px;text-align:right;color:var(--gold);font-weight:700"><?php echo '+'.number_format($gain, 2, ',', ' ').' €'; ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
    </div>
  </div>
</div>
</html>
