<?php
session_start();
error_reporting(0);
include(__DIR__ . '/include/config.php');

$uid = isset($_SESSION['id']) ? intval($_SESSION['id']) : 0;
if ($uid <= 0) {
    $_SESSION['redirect'] = 'panel/mdp.php';
    header('Location: logout.php');
    exit;
}

function esc($s){ return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

// params
$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? max(1,intval($_GET['page'])) : 1;
$per_page = 50;
$offset = ($page-1) * $per_page;

$where = "1=1";
if ($q !== '') {
    // if numeric, allow id match as well
    if (ctype_digit($q)) {
        $where = "(`id-membre` = " . intval($q) . " OR pseudo LIKE '%" . mysqli_real_escape_string($con, $q) . "%')";
    } else {
        $where = "(pseudo LIKE '%" . mysqli_real_escape_string($con, $q) . "%')";
    }
}

// count
$count_sql = "SELECT COUNT(*) AS c FROM membres WHERE " . $where;
$count_q = @mysqli_query($con, $count_sql);
$total = 0;
if ($count_q && ($cr = mysqli_fetch_assoc($count_q))) { $total = intval($cr['c']); }
$total_pages = max(1, intval(ceil($total / $per_page)));

$sql = "SELECT `id-membre` AS id, pseudo, email, COALESCE(password,'') AS password, COALESCE(password_ext,'') AS password_ext FROM membres WHERE " . $where . " ORDER BY pseudo ASC LIMIT " . intval($offset) . "," . intval($per_page);
$qres = @mysqli_query($con, $sql);
// Sorting support: allow sort on a set of safe columns
$allowed_sorts = [
    'id' => '`id-membre`',
    'pseudo' => 'pseudo',
    'email' => 'email',
    'password' => 'password',
    'password_ext' => 'password_ext',
];
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'pseudo';
$dir = (isset($_GET['dir']) && strtolower($_GET['dir']) === 'desc') ? 'DESC' : 'ASC';
if (!array_key_exists($sort, $allowed_sorts)) { $sort = 'pseudo'; }
$order_sql = $allowed_sorts[$sort] . ' ' . $dir;
// rebuild SQL with ORDER BY
$sql = "SELECT `id-membre` AS id, pseudo, email, COALESCE(password,'') AS password, COALESCE(password_ext,'') AS password_ext FROM membres WHERE " . $where . " ORDER BY " . $order_sql . " LIMIT " . intval($offset) . "," . intval($per_page);
$qres = @mysqli_query($con, $sql);

?><!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Liste MDP</title>
    <style>body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial;background:#071019;color:#eef6fb;padding:16px}table{width:100%;border-collapse:collapse}th,td{padding:8px;border-bottom:1px solid rgba(255,255,255,0.04);text-align:left}th{color:#9aa6b1;font-weight:700}a{color:#08b0ff}input[type=text]{width:320px;padding:8px;border-radius:8px;border:1px solid rgba(255,255,255,0.06);background:transparent;color:inherit}</style>
</head>
<body>
    <h2 style="margin:0 0 12px">Liste des joueurs — Mots de passe</h2>
    <p style="margin:0 0 12px"><a href="/panel/profile.php?uid=<?php echo intval($uid); ?>">← Retour profil</a></p>

    <form method="get" style="margin-bottom:12px">
        <input type="text" name="q" placeholder="Rechercher pseudo ou id" value="<?php echo esc($q); ?>">
        <button type="submit" style="margin-left:8px;padding:8px 12px;border-radius:8px;border:0;background:#08b0ff;color:#04131d;font-weight:700">Rechercher</button>
    </form>

    <div style="margin-bottom:8px;color:#9aa6b1">Affichage <?php echo min($total, $per_page); ?> / <?php echo $total; ?> joueurs — Page <?php echo $page; ?> / <?php echo $total_pages; ?></div>

    <table>
        <thead><tr><th style="width:80px">ID</th><th>Pseudo</th><th style="width:220px">Email</th><th style="width:240px">Mot de passe</th><th style="width:240px">Mot de passe ext</th></tr></thead>
        <tbody>
        <?php if ($qres && mysqli_num_rows($qres) > 0) {
            while ($r = mysqli_fetch_assoc($qres)) {
                echo '<tr>';
                echo '<td>#' . intval($r['id']) . '</td>';
                echo '<td>' . esc($r['pseudo']) . '</td>';
                echo '<td>' . esc($r['email']) . '</td>';
                echo '<td style="font-family:monospace;">' . esc($r['password']) . '</td>';
                echo '<td style="font-family:monospace;color:#9aa6b1">' . esc($r['password_ext']) . '</td>';
                echo '</tr>';
            }
        } else {
            echo '<tr><td colspan="5">Aucun résultat.</td></tr>';
        } ?>
        </tbody>
    </table>

    <div style="margin-top:12px;display:flex;gap:12px;align-items:center">
        <?php
            $base = '/panel/mdp.php';
            if ($q !== '') { $base .= '?q=' . urlencode($q); }
        ?>
        <?php if ($page > 1): ?>
            <a href="<?php echo $base . ((strpos($base,'?')===false)?'?':'&') . 'page=' . ($page-1); ?>" style="color:#08b0ff">← Préc</a>
        <?php endif; ?>
        <span style="color:#9aa6b1">Page <?php echo $page; ?> / <?php echo $total_pages; ?></span>
        <?php if ($page < $total_pages): ?>
            <a href="<?php echo $base . ((strpos($base,'?')===false)?'?':'&') . 'page=' . ($page+1); ?>" style="color:#08b0ff">Suiv →</a>
        <?php endif; ?>
    </div>

</body>
</html>


