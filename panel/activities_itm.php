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

// detect existing organizer columns in activite to safely build exclusion
$existing_cols = [];
$col_q = @mysqli_query($con, "SHOW COLUMNS FROM activite");
if ($col_q) {
    while ($c = mysqli_fetch_assoc($col_q)) { $existing_cols[] = $c['Field']; }
}
$candidates = ['id-membre','id_membre','id_membres','id_membre_organisateur','organisateur'];
$used = array_values(array_intersect($candidates, $existing_cols));
$exclude_clause = '';
if (!empty($used)) {
    $parts = [];
    foreach ($used as $col) { $parts[] = "a.`" . $col . "` = '" . $uid . "'"; }
    $exclude_clause = ' AND NOT (' . implode(' OR ', $parts) . ')';
}

// Query activities where this member had ITM (classement 1..3), exclude Desinscrit/None and organizer activities
$sql = "SELECT a.`id-activite` AS aid, COALESCE(a.`titre-activite`, '') AS title, a.`date_depart` AS dt, COALESCE(a.buyin,0) AS buyin, COALESCE(p.classement,0) AS classement FROM participation p JOIN activite a ON a.`id-activite` = p.`id-activite` WHERE p.`id-membre` = '".intval($uid)."' AND COALESCE(p.`option`,'None') NOT IN ('Desinscrit','None') AND COALESCE(p.classement,999) > 0 AND COALESCE(p.classement,999) <= 3" . $exclude_clause . " ORDER BY a.`date_depart` DESC LIMIT 500";
$q = @mysqli_query($con, $sql);

?><!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Activités — ITM</title>
    <style>body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial;background:#071019;color:#eef6fb;padding:16px}table{width:100%;border-collapse:collapse}th,td{padding:8px;border-bottom:1px solid rgba(255,255,255,0.04);text-align:left}th{color:#9aa6b1;font-weight:700}a{color:#08b0ff}</style>
</head>
<body>
    <h2 style="margin:0 0 12px">Activités — ITM — <?php echo intval($uid); ?></h2>
    <p style="margin:0 0 12px"><a href="/panel/profile.php">← Retour au profil</a></p>
    <table>
        <thead>
            <tr><th>ID</th><th>Titre</th><th>Date</th><th>Buyin</th><th style="text-align:right">Classement</th><th>Voir</th></tr>
        </thead>
        <tbody>
        <?php if ($q && mysqli_num_rows($q) > 0) {
            while ($r = mysqli_fetch_assoc($q)) {
                $aid = intval($r['aid']);
                $title = htmlspecialchars($r['title']);
                $dt = htmlspecialchars($r['dt']);
                $buyin = number_format(intval($r['buyin']),0,',',' ');
                $classe = intval($r['classement']);
                echo "<tr><td>#". $aid ."</td><td>". $title ."</td><td>". $dt ."</td><td>". $buyin ." €</td><td style='text-align:right;color:#ff9d3b;font-weight:800'>". $classe ."</td><td><a href=\"/panel/voir-activite.php?uid=". $aid ."\">Voir</a></td></tr>";
            }
        } else {
            echo '<tr><td colspan="6">Aucune activité trouvée.</td></tr>';
        } ?></tbody>
    </table>
</body>
</html>

