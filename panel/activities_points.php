<?php
session_start();
error_reporting(0);
include(__DIR__ . '/include/config.php');

$mid = isset($_GET['uid']) && is_numeric($_GET['uid']) ? intval($_GET['uid']) : (isset($_SESSION['id']) ? intval($_SESSION['id']) : 0);
if ($mid <= 0) { header('Location: /panel/profile.php'); exit; }

// optional scope filters
$activity_aid = isset($_GET['aid']) && is_numeric($_GET['aid']) ? intval($_GET['aid']) : 0;
$provided_challenge = isset($_GET['challenge']) && is_numeric($_GET['challenge']) ? intval($_GET['challenge']) : 0;
$provided_challenge_col = isset($_GET['challenge_col']) ? $_GET['challenge_col'] : '';

// pagination
$per_page = 7;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? max(1,intval($_GET['page'])) : 1;
$offset = ($page-1) * $per_page;

// Build scope WHERE for activities (reuse simple challenge column filtering when provided)
$where_scope = "p.`id-membre` = '" . intval($mid) . "' AND COALESCE(p.`option`,'None') NOT IN ('Desinscrit','None') AND COALESCE(p.points,0) > 0";

if ($provided_challenge && $provided_challenge_col) {
    // sanitize provided column name to avoid injection (allow only letters, numbers, underscore and dash)
    if (preg_match('/^[a-zA-Z0-9_\-]+$/', $provided_challenge_col)) {
        $col = $provided_challenge_col;
        $where_scope .= " AND EXISTS (SELECT 1 FROM activite a2 WHERE a2.`id-activite` = p.`id-activite` AND COALESCE(a2.`".$col."`,0) = " . intval($provided_challenge) . ")";
    }
} elseif ($activity_aid) {
    // try to detect the challenge column from the activity row and filter by it
    $actq = @mysqli_query($con, "SELECT * FROM activite WHERE `id-activite` = '" . intval($activity_aid) . "' LIMIT 1");
    if ($actq && ($ar = mysqli_fetch_assoc($actq))) {
        // try to find an id_challenge column
        $activity_cols = [];
        $colres = @mysqli_query($con, "SHOW COLUMNS FROM activite");
        if ($colres) { while ($cr = mysqli_fetch_assoc($colres)) { $activity_cols[] = $cr['Field']; } }
        $candidates = array('id_challenge','id-challenge','challenge_id','idchall','id_chall');
        $found = null;
        foreach ($candidates as $c) { if (in_array($c, $activity_cols) && isset($ar[$c]) && $ar[$c] !== '') { $found = $c; $val = intval($ar[$c]); break; } }
        if ($found && isset($val)) {
            $where_scope .= " AND EXISTS (SELECT 1 FROM activite a3 WHERE a3.`id-activite` = p.`id-activite` AND COALESCE(a3.`".$found."`,0) = " . intval($val) . ")";
        }
    }
}

// total count
$count_sql = "SELECT COUNT(*) AS c FROM participation p JOIN activite a ON a.`id-activite` = p.`id-activite` WHERE " . $where_scope;
$count_q = @mysqli_query($con, $count_sql);
$total = 0;
if ($count_q && ($cr = mysqli_fetch_assoc($count_q))) { $total = intval($cr['c']); }
$total_pages = max(1, intval(ceil($total / $per_page)));

// fetch rows
$sql = "SELECT a.`id-activite` AS aid, COALESCE(a.`titre-activite`,'') AS title, a.`date_depart` AS dt, COALESCE(p.points,0) AS points FROM participation p JOIN activite a ON a.`id-activite` = p.`id-activite` WHERE " . $where_scope . " ORDER BY a.`date_depart` DESC LIMIT " . intval($offset) . "," . intval($per_page);
$q = @mysqli_query($con, $sql);

function esc($s){ return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
?><!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Activités — Points</title>
    <style>body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial;background:#071019;color:#eef6fb;padding:16px}table{width:100%;border-collapse:collapse}th,td{padding:8px;border-bottom:1px solid rgba(255,255,255,0.04);text-align:left}th{color:#9aa6b1;font-weight:700}a{color:#08b0ff}</style>
</head>
<body>
    <h2 style="margin:0 0 12px">Activités — Points pour <?php echo intval($mid); ?></h2>
    <p style="margin:0 0 12px"><a href="/panel/challenge_rank.php">← Retour au classement</a></p>
    <table>
        <thead>
            <tr><th>ID</th><th>Titre</th><th>Date</th><th style="text-align:right">Points</th><th>Voir</th></tr>
        </thead>
        <tbody>
        <?php if ($q && mysqli_num_rows($q) > 0) {
            while ($r = mysqli_fetch_assoc($q)) {
                $aid = intval($r['aid']);
                $title = esc($r['title']);
                $dt = esc($r['dt']);
                $points = intval($r['points']);
                echo "<tr><td>#". $aid ."</td><td>". $title ."</td><td>". $dt ."</td><td style='text-align:right;color:#08b0ff;font-weight:800'>". number_format($points,0,',',' ') ."</td><td><a href=\"/panel/voir-activite.php?uid=". $aid ."\">Voir</a></td></tr>";
            }
        } else {
            echo '<tr><td colspan="5">Aucune activité trouvée.</td></tr>';
        } ?></tbody>
    </table>
    <div style="margin-top:12px;display:flex;gap:12px;align-items:center">
        <?php if ($page > 1): ?>
            <a href="/panel/activities_points.php?uid=<?php echo intval($mid); ?>&page=<?php echo $page-1; ?>" style="color:#08b0ff">← Préc</a>
        <?php endif; ?>
        <span style="color:#9aa6b1">Page <?php echo $page; ?> / <?php echo $total_pages; ?></span>
        <?php if ($page < $total_pages): ?>
            <a href="/panel/activities_points.php?uid=<?php echo intval($mid); ?>&page=<?php echo $page+1; ?>" style="color:#08b0ff">Suiv →</a>
        <?php endif; ?>
    </div>
</body>
</html>
