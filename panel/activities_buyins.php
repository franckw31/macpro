<?php
session_start();
error_reporting(0);
include(__DIR__ . '/include/config.php');
include(__DIR__ . '/../include/functions_logs.php');

$uid = isset($_GET['uid']) && is_numeric($_GET['uid']) ? intval($_GET['uid']) : (isset($_SESSION['id']) ? intval($_SESSION['id']) : 0);
if ($uid <= 0) {
    header('Location: /panel/profile.php');
    exit;
}

// Pagination params
$per_page = 7;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $per_page;

// Determine activities where the participation generated expenses (buyin, rake, recave/addon)
$expr = "(COALESCE(a.buyin,0) + COALESCE(a.rake,0) + (COALESCE(p.recave,0) * COALESCE(a.recave_montant,0)) + (COALESCE(p.addon,0) * COALESCE(a.recave_montant,0)))";
$base_where = "p.`id-membre` = '" . intval($uid) . "' AND COALESCE(p.`option`,'None') NOT IN ('Desinscrit','None') AND " . $expr . " > 0";

// total count for pagination
$count_sql = "SELECT COUNT(*) AS c FROM participation p JOIN activite a ON a.`id-activite` = p.`id-activite` WHERE " . $base_where;
$count_q = @mysqli_query($con, $count_sql);
$total = 0;
if ($count_q && ($cr = mysqli_fetch_assoc($count_q))) { $total = intval($cr['c']); }
$total_pages = max(1, intval(ceil($total / $per_page)));

// Query paginated
$sql = "SELECT a.`id-activite` AS aid, COALESCE(a.`titre-activite`, '') AS title, a.`date_depart` AS dt, COALESCE(a.buyin,0) AS buyin, COALESCE(a.rake,0) AS rake, COALESCE(p.recave,0) AS recave, COALESCE(p.addon,0) AS addon, COALESCE(a.recave_montant,0) AS recave_montant, " . $expr . " AS expense FROM participation p JOIN activite a ON a.`id-activite` = p.`id-activite` WHERE " . $base_where . " ORDER BY a.`date_depart` DESC LIMIT " . intval($offset) . "," . intval($per_page);
$q = @mysqli_query($con, $sql);

?><!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Activités — Dépenses</title>
    <style>
        *{box-sizing:border-box}
        body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial;background:#071019;color:#eef6fb;padding:16px;margin:0}
        h2{margin:0 0 12px;font-size:16px}
        a{color:#08b0ff}
        .back{margin:0 0 12px;display:block}

        /* Desktop table */
        table{width:100%;border-collapse:collapse}
        th,td{padding:8px;border-bottom:1px solid rgba(255,255,255,0.04);text-align:left;white-space:nowrap}
        th{color:#9aa6b1;font-weight:700}
        td.total{text-align:right;color:#9aa6b1;font-weight:800}

        /* Card layout on mobile */
        @media(max-width:600px){
            table thead{display:none}
            table,tbody,tr,td{display:block;width:100%}
            tr{background:rgba(255,255,255,0.03);border-radius:10px;margin-bottom:10px;padding:10px 12px;border:1px solid rgba(255,255,255,0.06)}
            td{padding:4px 0;border:none;white-space:normal;display:flex;justify-content:space-between;align-items:center;gap:8px;font-size:14px}
            td::before{content:attr(data-label);color:#9aa6b1;font-weight:700;font-size:12px;flex-shrink:0}
            td.total{justify-content:space-between;color:#eef6fb}
            td:first-child{font-size:12px;color:#9aa6b1}
        }

        .pagination{margin-top:12px;display:flex;gap:12px;align-items:center}
        .pagination span{color:#9aa6b1}
    </style>
</head>
<body>
    <h2 style="margin:0 0 12px">Activités — Dépenses — <?php echo intval($uid); ?></h2>
    <p style="margin:0 0 12px"><a href="/panel/profile.php">← Retour au profil</a></p>
    <table>
        <thead>
            <tr><th>ID</th><th>Titre</th><th>Date</th><th>Buyin</th><th>Rake</th><th>Recaves</th><th>Addons</th><th style="text-align:right">Total</th><th>Voir</th></tr>
        </thead>
        <tbody>
        <?php if ($q && mysqli_num_rows($q) > 0) {
            while ($r = mysqli_fetch_assoc($q)) {
                $aid = intval($r['aid']);
                $title = htmlspecialchars($r['title']);
                $dt = htmlspecialchars($r['dt']);
                $buyin = number_format(intval($r['buyin']),0,',',' ');
                $rake = number_format(intval($r['rake']),0,',',' ');
                $recave = intval($r['recave']);
                $addon = intval($r['addon']);
                $recave_m = number_format(intval($r['recave_montant']),0,',',' ');
                $expense = number_format(intval($r['expense']),0,',',' ');
                echo "<tr><td>#". $aid ."</td><td>". $title ."</td><td>". $dt ."</td><td>". $buyin ." €</td><td>". $rake ." €</td><td>". $recave ."</td><td>". $addon ."</td><td style='text-align:right;color:#9aa6b1;font-weight:800'>". $expense ." €</td><td><a href=\"/panel/resume.php?uid=". $aid ."\">Voir</a></td></tr>";
            }
        } else {
            echo '<tr><td colspan="9">Aucune activité trouvée.</td></tr>';
        } ?></tbody>
    </table>
    <div style="margin-top:12px;display:flex;gap:12px;align-items:center">
        <?php if ($page > 1): ?>
            <a href="/panel/activities_buyins.php?uid=<?php echo intval($uid); ?>&page=<?php echo $page-1; ?>" style="color:#08b0ff">← Préc</a>
        <?php endif; ?>
        <span style="color:#9aa6b1">Page <?php echo $page; ?> / <?php echo $total_pages; ?></span>
        <?php if ($page < $total_pages): ?>
            <a href="/panel/activities_buyins.php?uid=<?php echo intval($uid); ?>&page=<?php echo $page+1; ?>" style="color:#08b0ff">Suiv →</a>
        <?php endif; ?>
    </div>
</body>
</html>
