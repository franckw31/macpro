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
$per_page = 6; // reduced to minimize scrolling per request
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $per_page;

// Base WHERE clause for reuse
$base_where = "p.`id-membre` = '" . intval($uid) . "' AND COALESCE(p.`option`,'None') NOT IN ('Desinscrit','None') AND COALESCE(p.gain,0) > 0";

// total count for pagination
$count_sql = "SELECT COUNT(*) AS c FROM participation p JOIN activite a ON a.`id-activite` = p.`id-activite` WHERE " . $base_where;
$count_q = @mysqli_query($con, $count_sql);
$total = 0;
if ($count_q && ($cr = mysqli_fetch_assoc($count_q))) { $total = intval($cr['c']); }
$total_pages = max(1, intval(ceil($total / $per_page)));

// Query activities where this member participated and had gain > 0 (paginated)
$sql = "SELECT a.`id-activite` AS aid, COALESCE(a.`titre-activite`, '') AS title, a.`date_depart` AS dt, COALESCE(p.gain,0) AS gain, COALESCE(a.buyin,0) AS buyin FROM participation p JOIN activite a ON a.`id-activite` = p.`id-activite` WHERE " . $base_where . " ORDER BY a.`date_depart` DESC LIMIT " . intval($offset) . "," . intval($per_page);
$q = @mysqli_query($con, $sql);

?><!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Activités — Gains</title>
    <style>body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial;background:#071019;color:#eef6fb;padding:16px}table{width:100%;border-collapse:collapse}th,td{padding:8px;border-bottom:1px solid rgba(255,255,255,0.04);text-align:left}th{color:#9aa6b1;font-weight:700}a{color:#08b0ff}</style>
</head>
<body>
    <h2 style="margin:0 0 12px">Activités avec gains — <?php echo intval($uid); ?></h2>
    <p style="margin:0 0 12px"><a href="/panel/profile.php">← Retour au profil</a></p>
    <table>
        <thead>
            <tr><th>ID</th><th>Titre</th><th>Date</th><th>Buyin</th><th style="text-align:right">Gain</th><th>Voir</th></tr>
        </thead>
        <tbody>
        <?php if ($q && mysqli_num_rows($q) > 0) {
            while ($r = mysqli_fetch_assoc($q)) {
                $aid = intval($r['aid']);
                $title = htmlspecialchars($r['title']);
                $dt = htmlspecialchars($r['dt']);
                $buyin = number_format(intval($r['buyin']),0,',',' ');
                $gain = number_format(intval($r['gain']),0,',',' ');
                echo "<tr><td>#". $aid ."</td><td>". $title ."</td><td>". $dt ."</td><td>". $buyin ." €</td><td style='text-align:right;color:#16a34a;font-weight:800'>". $gain ." €</td><td><a href=\"/panel/voir-activite.php?uid=". $aid ."\">Voir</a></td></tr>";
            }
        } else {
            echo '<tr><td colspan="6">Aucune activité trouvée.</td></tr>';
        } ?></tbody>
    </table>
    <div style="margin-top:12px;display:flex;gap:12px;align-items:center">
        <?php if ($page > 1): ?>
            <a href="/panel/activities_wins.php?uid=<?php echo intval($uid); ?>&page=<?php echo $page-1; ?>" style="color:#08b0ff">← Préc</a>
        <?php endif; ?>
        <span style="color:#9aa6b1">Page <?php echo $page; ?> / <?php echo $total_pages; ?></span>
        <?php if ($page < $total_pages): ?>
            <a href="/panel/activities_wins.php?uid=<?php echo intval($uid); ?>&page=<?php echo $page+1; ?>" style="color:#08b0ff">Suiv →</a>
        <?php endif; ?>
    </div>
</body>
</html>
