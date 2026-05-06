<?php
session_start();
error_reporting(0);
include(__DIR__ . '/include/config.php');

// ── Déconnexion ───────────────────────────────────────────────
if (!empty($_GET['logout'])) {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 3600, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
    setcookie('qv_pseudo', '', time() - 3600, '/', '', false, true);
    setcookie('qv_passwd', '', time() - 3600, '/', '', false, true);
    setcookie('uname',     '', time() - 3600, '/');
    header('Location: /panel/mdp.php');
    exit;
}

// ── Auto-login depuis cookie si pas de session ────────────────────────
if (empty($_SESSION['id']) && !empty($_COOKIE['qv_pseudo']) && !empty($_COOKIE['qv_passwd']) && !empty($con)) {
    $cp = mysqli_real_escape_string($con, $_COOKIE['qv_pseudo']);
    $cw = mysqli_real_escape_string($con, $_COOKIE['qv_passwd']);
    $qa = @mysqli_query($con, "SELECT `id-membre`, `pseudo` FROM membres WHERE (pseudo='$cp' OR email='$cp') AND (password='$cw' OR password_ext='$cw') LIMIT 1");
    if ($qa && ($ra = mysqli_fetch_array($qa))) {
        $_SESSION['login'] = $ra['pseudo'];
        $_SESSION['id']    = $ra['id-membre'];
        // Renouveler les cookies
        $exp = time() + (30 * 24 * 3600);
        setcookie('qv_pseudo', $ra['pseudo'], $exp, '/', '', false, true);
        setcookie('qv_passwd', $cw,           $exp, '/', '', false, true);
        setcookie('uname',     $ra['pseudo'], $exp, '/');
    }
}

$uid = isset($_SESSION['id']) ? intval($_SESSION['id']) : 0;
if ($uid <= 0) {
    $_SESSION['redirect'] = 'panel/mdp.php';
    header('Location: logout.php');
    exit;
}

function esc($s){ return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

// params
$q = isset($_GET['q']) ? trim($_GET['q']) : '';

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
// rebuild SQL with ORDER BY (no pagination)
$sql = "SELECT `id-membre` AS id, pseudo, email, COALESCE(password,'') AS password, COALESCE(password_ext,'') AS password_ext FROM membres WHERE " . $where . " ORDER BY " . $order_sql;
$qres = @mysqli_query($con, $sql);

?><!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Liste MDP</title>
    <style>
        body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial;background:#071019;color:#eef6fb;padding:16px}
        .container{max-width:1100px;margin:0 auto}
        .toolbar{display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-bottom:12px}
        input[type=text]{width:320px;padding:8px;border-radius:8px;border:1px solid rgba(255,255,255,0.06);background:transparent;color:inherit}
        button{padding:8px 12px;border-radius:8px;border:0;background:#08b0ff;color:#04131d;font-weight:700;cursor:pointer}
        .table-wrap{overflow-x:auto}
        table{width:100%;border-collapse:collapse;min-width:720px}
        th,td{padding:8px;border-bottom:1px solid rgba(255,255,255,0.04);text-align:left}
        th{color:#9aa6b1;font-weight:700;white-space:nowrap}
        td{vertical-align:top}
        a{color:#08b0ff}

        @media (max-width:800px){
            input[type=text]{width:100%}
            .toolbar{flex-direction:column;align-items:stretch}
            table{min-width:600px}
        }

        /* Portrait orientation: colonnes compactes */
        @media (orientation:portrait) and (max-width:900px){
            body{padding:10px}
            table{min-width:0;width:100%;font-size:11px;table-layout:fixed}
            th,td{padding:5px 4px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;text-align:center}
            th:nth-child(1),td:nth-child(1){width:36px}
            th:nth-child(2),td:nth-child(2){width:18%}
            th:nth-child(3),td:nth-child(3){width:24%}
            th:nth-child(4),td:nth-child(4){width:25%}
            th:nth-child(5),td:nth-child(5){width:25%}
            td:nth-child(4),td:nth-child(5){font-size:10px;font-family:monospace}
        }

        /* Small screens portrait: tableau compact comme en paysage */
        @media (max-width:480px){
            .table-wrap{overflow-x:auto}
            table{min-width:0;width:100%;font-size:10px;table-layout:fixed}
            th,td{padding:4px 3px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;text-align:center}
            th:nth-child(1),td:nth-child(1){width:30px}
            th:nth-child(2),td:nth-child(2){width:18%}
            th:nth-child(3),td:nth-child(3){width:24%}
            th:nth-child(4),td:nth-child(4){width:25%}
            th:nth-child(5),td:nth-child(5){width:25%}
            td:nth-child(4),td:nth-child(5){font-size:9px;font-family:monospace}
        }
    </style>
</head>
<body>
    <h2 style="margin:0 0 12px">Liste des joueurs — Mots de passe</h2>
    <p style="margin:0 0 12px"><a href="/panel/profile.php?uid=<?php echo intval($uid); ?>">← Retour profil</a></p>

    <form method="get" style="margin-bottom:12px">
        <input type="text" name="q" placeholder="Rechercher pseudo ou id" value="<?php echo esc($q); ?>">
        <button type="submit" style="margin-left:8px;padding:8px 12px;border-radius:8px;border:0;background:#08b0ff;color:#04131d;font-weight:700">Rechercher</button>
    </form>

    <div style="margin-bottom:8px;color:#9aa6b1">Total <?php echo $total; ?> joueurs</div>

    <div class="table-wrap">
    <table>
        <thead>
            <tr>
                <?php
                    // helper to build header sort links
                    function hdr_link($key, $label, $current_sort, $current_dir, $q){
                        $dir = 'asc';
                        $arrow = '';
                        if ($current_sort === $key) {
                            if (strtolower($current_dir) === 'asc') { $dir = 'desc'; $arrow = ' ▲'; }
                            else { $dir = 'asc'; $arrow = ' ▼'; }
                        }
                        $params = [];
                        if ($q !== '') $params['q'] = $q;
                        $params['sort'] = $key;
                        $params['dir'] = $dir;
                        $url = '/panel/mdp.php?' . http_build_query($params);
                        return '<th><a href="' . htmlspecialchars($url) . '" style="color:#08b0ff">' . htmlspecialchars($label . $arrow) . '</a></th>';
                    }
                    echo hdr_link('id','ID',$sort,$dir,$q);
                    echo hdr_link('pseudo','Pseudo',$sort,$dir,$q);
                    echo hdr_link('email','Email',$sort,$dir,$q);
                    echo hdr_link('password','Mot de passe',$sort,$dir,$q);
                    echo hdr_link('password_ext','Mot de passe ext',$sort,$dir,$q);
                ?>
            </tr>
        </thead>
        <tbody>
        <?php if ($qres && mysqli_num_rows($qres) > 0) {
            while ($r = mysqli_fetch_assoc($qres)) {
                echo '<tr>';
                echo '<td data-label="ID">#' . intval($r['id']) . '</td>';
                echo '<td data-label="Pseudo">' . esc($r['pseudo']) . '</td>';
                echo '<td data-label="Email">' . esc($r['email']) . '</td>';
                echo '<td data-label="Mot de passe" style="font-family:monospace;">' . esc($r['password']) . '</td>';
                echo '<td data-label="Mot de passe ext" style="font-family:monospace;color:#9aa6b1">' . esc($r['password_ext']) . '</td>';
                echo '</tr>';
            }
        } else {
            echo '<tr><td colspan="5">Aucun résultat.</td></tr>';
        } ?>
        </tbody>
    </table>
    </div>

</body>
</html>


