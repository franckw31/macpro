<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/include/functions_logs.php';

define('ADMIN_KEY', 'Cardevent');

function getCity($ip) {
    if (in_array($ip, array('127.0.0.1','::1','localhost'))) return 'Local';
    static $cache = array();
    if (isset($cache[$ip])) return $cache[$ip];
    $ctx = stream_context_create(array(
        'http' => array('timeout' => 2, 'user_agent' => 'Mozilla/5.0'),
        'ssl'  => array('verify_peer' => false, 'verify_peer_name' => false)
    ));
    $r = @file_get_contents('https://get.geojs.io/v1/ip/geo/'.urlencode($ip).'.json', false, $ctx);
    $city = 'N/A';
    if ($r) {
        $d = json_decode($r, true);
        if (!empty($d['city'])) {
            $city = $d['city'];
            if (!empty($d['country'])) $city .= ' ('.$d['country'].')';
        }
    }
    return $cache[$ip] = $city;
}

function actionBadge($a, $s) {
    if (strpos($a,'success') !== false) return 'badge-success';
    if (strpos($a,'failure') !== false) return 'badge-failure';
    if (strpos($a,'verify')  !== false) return 'badge-verify';
    if (strpos($a,'admin')   !== false) return 'badge-admin';
    if (in_array($s, array('iOS App','iOS Admin'))) return 'badge-ios';
    return 'badge-other';
}

function statClass($a) {
    if (strpos($a,'success') !== false) return 'sc';
    if (strpos($a,'failure') !== false) return 'fc';
    if (strpos($a,'verify')  !== false) return 'vc';
    if (strpos($a,'admin')   !== false) return 'ac';
    return 'lc';
}

// Actions admin POST (AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $key    = trim($_POST['admin_key'] ?? '');
    $action = trim($_POST['action']    ?? '');
    header('Content-Type: application/json');
    if ($key !== ADMIN_KEY) {
        echo json_encode(array('success' => false, 'error' => 'Cle incorrecte'));
        exit;
    }
    try {
        $pdo = new PDO(
            'mysql:host=localhost;dbname=dbs9616600;charset=utf8mb4',
            'root', 'Kookies7*',
            array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION)
        );
        if ($action === 'revoke_all') {
            $n = $pdo->query("SELECT COUNT(*) FROM app_auth_tokens")->fetchColumn();
            $pdo->exec("TRUNCATE TABLE app_auth_tokens");
            $pdo->prepare("INSERT INTO activity_logs (user_id,username,action,source,details,ip_address) VALUES(0,'admin','admin:revoke_all','iOS Admin',?,?)")
                ->execute(array("$n sessions revoquees", $_SERVER['REMOTE_ADDR'] ?? ''));
            echo json_encode(array('success' => true, 'message' => "$n sessions revoquees - tous les appareils devront se reconnecter"));
        } elseif ($action === 'revoke_expired') {
            $st = $pdo->query("DELETE FROM app_auth_tokens WHERE expires_at < NOW()");
            echo json_encode(array('success' => true, 'message' => $st->rowCount()." token(s) expires supprimes"));
        } elseif ($action === 'revoke_user') {
            $pseudo = trim($_POST['pseudo'] ?? '');
            if ($pseudo === '') {
                echo json_encode(array('success' => false, 'error' => 'Pseudo requis'));
                exit;
            }
            $row = $pdo->prepare("SELECT `id-membre` FROM membres WHERE pseudo=? OR email=? LIMIT 1");
            $row->execute(array($pseudo, $pseudo));
            $u = $row->fetch(PDO::FETCH_ASSOC);
            if (!$u) {
                echo json_encode(array('success' => false, 'error' => "Utilisateur '$pseudo' introuvable"));
                exit;
            }
            $st = $pdo->prepare("DELETE FROM app_auth_tokens WHERE membre_id=?");
            $st->execute(array($u['id-membre']));
            echo json_encode(array('success' => true, 'message' => $st->rowCount()." session(s) revoquee(s) pour $pseudo"));
        } elseif ($action === 'clear_logs') {
            $n = $pdo->query("SELECT COUNT(*) FROM activity_logs WHERE source IN('iOS App','iOS Admin')")->fetchColumn();
            $pdo->exec("DELETE FROM activity_logs WHERE source IN('iOS App','iOS Admin')");
            echo json_encode(array('success' => true, 'message' => "$n entrees supprimees"));
        } elseif ($action === 'clear_all_logs') {
            $n = $pdo->query("SELECT COUNT(*) FROM activity_logs")->fetchColumn();
            $pdo->exec("TRUNCATE TABLE activity_logs");
            echo json_encode(array('success' => true, 'message' => "Table videe : $n entrees supprimees"));
        } else {
            echo json_encode(array('success' => false, 'error' => 'Action inconnue'));
        }
    } catch (PDOException $e) {
        echo json_encode(array('success' => false, 'error' => $e->getMessage()));
    }
    exit;
}

// Sessions actives
$sessions = array();
try {
    $pdo2 = new PDO(
        'mysql:host=localhost;dbname=dbs9616600;charset=utf8mb4',
        'root', 'Kookies7*',
        array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC)
    );
    $pdo2->exec("CREATE TABLE IF NOT EXISTS app_auth_tokens (
        id INT AUTO_INCREMENT PRIMARY KEY,
        membre_id INT NOT NULL,
        token VARCHAR(64) NOT NULL,
        device_id VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        last_used_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        expires_at TIMESTAMP NOT NULL,
        UNIQUE KEY unique_token(token))");
    $sessions = $pdo2->query(
        "SELECT t.id, m.pseudo, t.device_id, t.created_at, t.last_used_at, t.expires_at
         FROM app_auth_tokens t JOIN membres m ON m.`id-membre`=t.membre_id
         ORDER BY t.last_used_at DESC"
    )->fetchAll();
} catch (Exception $e) {}

// Onglet & requete logs
$tab = isset($_GET['tab']) ? $_GET['tab'] : 'all';
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 9;
$exclude_ids = 'user_id NOT IN(2, 265)';

// Tri
$allowed_sort = array('timestamp','username','action','source','details','ip_address');
$sort = isset($_GET['sort']) && in_array($_GET['sort'], $allowed_sort) ? $_GET['sort'] : 'timestamp';
$dir  = (isset($_GET['dir']) && strtoupper($_GET['dir']) === 'ASC') ? 'ASC' : 'DESC';

if ($tab === 'ios') {
    $where = "source IN('iOS App','iOS Admin') AND $exclude_ids";
} elseif ($tab === 'auth') {
    $where = "source IN('iOS App','iOS Admin') AND action IN('login_success','login_failure','verify_success','verify_failure','logout') AND $exclude_ids";
} else {
    $where = $exclude_ids;
}

$total_rows = (int)mysqli_fetch_row(mysqli_query($conx, "SELECT COUNT(*) FROM activity_logs WHERE $where"))[0];
$total_pages = max(1, ceil($total_rows / $per_page));
$page = min($page, $total_pages);
$offset = ($page - 1) * $per_page;

$query = "SELECT * FROM activity_logs WHERE $where ORDER BY `$sort` $dir LIMIT $per_page OFFSET $offset";
$result   = mysqli_query($conx, $query);
$statsIos = mysqli_query($conx, "SELECT action, COUNT(*) AS total FROM activity_logs WHERE source IN('iOS App','iOS Admin') AND $exclude_ids GROUP BY action ORDER BY total DESC");
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Logs d'activite</title>
<link rel="stylesheet" href="css/base.css">
<style>
:root {
    --bg: #0f1117;
    --bg2: #1a1d27;
    --bg3: #22263a;
    --border: #2e3347;
    --text: #e2e6f0;
    --muted: #7a839a;
    --accent: #4a90d9;
    --green: #2a7a2a;
    --green-bg: #0d2b0d;
    --red: #c0392b;
    --red-bg: #2b0d0d;
    --yellow: #856404;
    --yellow-bg: #2b2200;
    --cyan: #0c5460;
    --cyan-bg: #0a2028;
}
* { box-sizing: border-box; }
body { font-family: Arial, sans-serif; padding: 16px; background: var(--bg); color: var(--text); margin: 0; }
.container { max-width: 1300px; margin: auto; }
a { color: var(--accent); }
a.back { display: inline-block; color: var(--accent); text-decoration: none; font-weight: bold; }
a.back:hover { text-decoration: underline; }
h1 { margin: 0 0 16px; font-size: 20px; color: var(--text); }

/* Tabs */
.tabs { display: flex; gap: 6px; margin-bottom: 16px; flex-wrap: wrap; border-bottom: 2px solid var(--border); padding-bottom: 8px; }
.tabs a { padding: 6px 14px; border-radius: 6px 6px 0 0; text-decoration: none; background: var(--bg3); color: var(--muted); font-weight: bold; font-size: 13px; border: 1px solid var(--border); }
.tabs a.active { background: var(--accent); color: #fff; border-color: var(--accent); }
.tabs a.tab-ios { background: var(--green-bg); color: #5cb85c; border-color: #1a4a1a; }
.tabs a.tab-ios.active { background: var(--green); color: #fff; }
.tabs a.tab-admin { background: var(--yellow-bg); color: #f0c040; border-color: #3a2e00; }
.tabs a.tab-admin.active { background: var(--yellow); color: #fff; }

/* Stats pills */
.stats { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 14px; }
.stats span { padding: 3px 10px; border-radius: 14px; font-size: 11px; font-weight: bold; border: 1px solid var(--border); background: var(--bg3); color: var(--muted); }
.stats span.sc { background: var(--green-bg); border-color: #1a4a1a; color: #5cb85c; }
.stats span.fc { background: var(--red-bg);   border-color: #4a1a1a; color: #e57373; }
.stats span.vc { background: var(--cyan-bg);  border-color: #0a3040; color: #4fc3d4; }
.stats span.ac { background: var(--yellow-bg);border-color: #3a2e00; color: #f0c040; }
.stats span.lc { background: var(--bg3);      border-color: var(--border); color: var(--muted); }

/* Table responsive */
.table-wrap { width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; border-radius: 8px; border: 1px solid var(--border); }
table { width: 100%; border-collapse: collapse; background: var(--bg2); min-width: 600px; }
th, td { border-bottom: 1px solid var(--border); padding: 8px 10px; text-align: left; font-size: 13px; }
th { background: var(--bg3); font-size: 11px; text-transform: uppercase; letter-spacing: .4px; color: var(--muted); }
tr:hover td { background: var(--bg3); }
td { color: var(--text); }

/* Badges */
.badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: bold; }
.badge-success { background: var(--green-bg); color: #5cb85c; }
.badge-failure { background: var(--red-bg);   color: #e57373; }
.badge-verify  { background: var(--cyan-bg);  color: #4fc3d4; }
.badge-admin   { background: var(--yellow-bg);color: #f0c040; }
.badge-ios     { background: var(--green-bg); color: #5cb85c; }
.badge-other   { background: var(--bg3);      color: var(--muted); }

/* Admin panel */
.admin-panel { background: var(--bg2); padding: 18px; border-radius: 8px; border: 1px solid var(--border); margin-bottom: 20px; }
.admin-panel h2 { margin: 0 0 14px; font-size: 15px; color: var(--text); }
.admin-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 14px; }
.admin-card { border: 1px solid var(--border); border-radius: 8px; padding: 14px; background: var(--bg3); }
.admin-card h3 { margin: 0 0 6px; font-size: 13px; color: var(--text); }
.admin-card p { margin: 0 0 10px; font-size: 12px; color: var(--muted); }
.btn { padding: 8px 14px; border: none; border-radius: 6px; cursor: pointer; font-weight: bold; font-size: 13px; width: 100%; }
.btn-danger { background: #c82333; color: #fff; }
.btn-danger:hover { background: #a71d2a; }
.btn-warn { background: #e0a800; color: #1a1a1a; }
.btn-warn:hover { background: #c69500; }
.btn-info { background: #138496; color: #fff; }
.btn-info:hover { background: #0f6674; }
.key-input, .pseudo-input { width: 100%; padding: 6px 8px; border: 1px solid var(--border); border-radius: 4px; font-size: 12px; margin-bottom: 8px; background: var(--bg); color: var(--text); }
.result-msg { margin-top: 10px; padding: 8px; border-radius: 4px; font-size: 13px; display: none; }
.result-ok  { background: var(--green-bg); color: #5cb85c; border: 1px solid #1a4a1a; }
.result-err { background: var(--red-bg);   color: #e57373; border: 1px solid #4a1a1a; }

/* Sessions table */
.admin-panel table { min-width: 0; }

/* Tri */
.th-sort { cursor:pointer; user-select:none; white-space:nowrap; }
.th-sort:hover { background: #2a2f46; }
.th-sort .sort-icon { margin-left:4px; color: var(--muted); font-size:10px; }
.th-sort.active .sort-icon { color: var(--accent); }
.th-sort a { color: var(--muted); }
.th-sort.active a { color: var(--accent); }

/* Pagination */
.pagination { display:flex; align-items:center; gap:6px; margin-top:14px; flex-wrap:wrap; }
.pagination a { padding:5px 10px; border-radius:5px; background:var(--bg3); border:1px solid var(--border); text-decoration:none; color:var(--text); font-size:13px; }
.pagination a:hover { background: var(--bg); }
.pagination a.current { background: var(--accent); border-color: var(--accent); color:#fff; font-weight:bold; }
.pagination .info { font-size:13px; color:var(--muted); margin-left:auto; }

@media (max-width: 600px) {
    body { padding: 10px; }
    h1 { font-size: 16px; }
    .tabs a { font-size: 12px; padding: 5px 10px; }
    .admin-grid { grid-template-columns: 1fr; }
    th, td { font-size: 12px; padding: 6px 8px; }
}
</style>
</head>
<body>
<div class="container">
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px">
    <a href="index.php" class="back" style="margin-bottom:0">&larr; Retour</a>
    <button onclick="location.reload()" style="padding:7px 16px;border-radius:6px;border:1px solid #ccc;background:#fff;cursor:pointer;font-size:13px;font-weight:bold">&#x21bb; Actualiser</button>
</div>
<h1>Logs d'activite</h1>

<div class="tabs">
    <a href="?tab=all"   class="<?php echo ($tab==='all'   ? 'active' : ''); ?>">Tous</a>
    <a href="?tab=ios"   class="tab-ios <?php echo ($tab==='ios'   ? 'active' : ''); ?>">iOS App</a>
    <a href="?tab=auth"  class="tab-ios <?php echo ($tab==='auth'  ? 'active' : ''); ?>">Auth iOS</a>
    <a href="?tab=admin" class="tab-admin <?php echo ($tab==='admin' ? 'active' : ''); ?>">Gestion acces</a>
</div>

<?php if ($tab === 'admin'): ?>
<div class="admin-panel">
    <h2>Gestion des autorisations iOS</h2>
    <p style="margin:0 0 16px;font-size:13px;color:#555">Cle admin requise pour toutes les actions.</p>
    <div class="admin-grid">

        <div class="admin-card">
            <h3>Tout revoquer</h3>
            <p>Deconnecte tous les appareils. Ils devront se reconnecter manuellement.</p>
            <input class="key-input" type="password" placeholder="Cle admin" id="key1">
            <button class="btn btn-danger" onclick="adminAction('revoke_all','key1','msg1')">Revoquer toutes les sessions</button>
            <div class="result-msg" id="msg1"></div>
        </div>

        <div class="admin-card">
            <h3>Purger les tokens expires</h3>
            <p>Supprime les tokens dont la date d'expiration est depassee.</p>
            <input class="key-input" type="password" placeholder="Cle admin" id="key2">
            <button class="btn btn-warn" onclick="adminAction('revoke_expired','key2','msg2')">Purger</button>
            <div class="result-msg" id="msg2"></div>
        </div>

        <div class="admin-card">
            <h3>Revoquer un utilisateur</h3>
            <p>Deconnecte tous les appareils d'un utilisateur specifique.</p>
            <input class="key-input" type="password" placeholder="Cle admin" id="key3">
            <input class="pseudo-input" type="text" placeholder="Pseudo ou e-mail" id="pseudo3">
            <button class="btn btn-danger" onclick="adminAction('revoke_user','key3','msg3')">Revoquer</button>
            <div class="result-msg" id="msg3"></div>
        </div>

        <div class="admin-card">
            <h3>Vider les logs iOS</h3>
            <p>Supprime toutes les entrees iOS de la table activity_logs.</p>
            <input class="key-input" type="password" placeholder="Cle admin" id="key4">
            <button class="btn btn-info" onclick="adminAction('clear_logs','key4','msg4')">Vider les logs iOS</button>
            <div class="result-msg" id="msg4"></div>
        </div>

        <div class="admin-card">
            <h3>Vider toute la table des logs</h3>
            <p>Supprime TOUTES les entrees de la table activity_logs (TRUNCATE).</p>
            <input class="key-input" type="password" placeholder="Cle admin" id="key5">
            <button class="btn btn-danger" onclick="adminAction('clear_all_logs','key5','msg5')">Vider toute la table</button>
            <div class="result-msg" id="msg5"></div>
        </div>

    </div>
</div>

<div class="admin-panel">
    <h2>Sessions actives (<?php echo count($sessions); ?>)</h2>
    <?php if (empty($sessions)): ?>
    <p style="color:#888;font-size:13px">Aucune session active.</p>
    <?php else: ?>
    <div class="table-wrap"><table>
        <thead><tr>
            <th>Utilisateur</th><th>Device ID</th><th>Creee le</th><th>Dernier usage</th><th>Expire le</th>
        </tr></thead>
        <tbody>
        <?php foreach ($sessions as $s): ?>
        <tr>
            <td><strong><?php echo htmlspecialchars($s['pseudo']); ?></strong></td>
            <td style="font-size:11px;color:#666"><?php echo htmlspecialchars(substr($s['device_id'], 0, 20)).'...'; ?></td>
            <td><?php echo htmlspecialchars($s['created_at']); ?></td>
            <td><?php echo htmlspecialchars($s['last_used_at']); ?></td>
            <td><?php echo htmlspecialchars($s['expires_at']); ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <?php endif; ?>
</div>

<script>
function adminAction(action, keyId, msgId) {
    var key    = document.getElementById(keyId).value;
    var pseudo = (action === 'revoke_user') ? document.getElementById('pseudo3').value : '';
    var msgEl  = document.getElementById(msgId);
    if (!key) { showMsg(msgEl, 'Cle admin requise', 'err'); return; }
    var body = 'action=' + encodeURIComponent(action) + '&admin_key=' + encodeURIComponent(key);
    if (pseudo) body += '&pseudo=' + encodeURIComponent(pseudo);
    fetch(window.location.pathname + '?tab=admin', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: body
    })
    .then(function(r) { return r.json(); })
    .then(function(d) {
        showMsg(msgEl, d.message || d.error || 'OK', d.success ? 'ok' : 'err');
        if (d.success) setTimeout(function() { location.reload(); }, 1800);
    })
    .catch(function() { showMsg(msgEl, 'Erreur reseau', 'err'); });
}
function showMsg(el, text, type) {
    el.textContent   = text;
    el.className     = 'result-msg result-' + (type === 'ok' ? 'ok' : 'err');
    el.style.display = 'block';
}
</script>

<?php else: ?>

<?php if ($statsIos && mysqli_num_rows($statsIos) > 0): ?>
<div class="stats">
    <?php while ($s = mysqli_fetch_assoc($statsIos)): ?>
    <span class="<?php echo statClass($s['action']); ?>"><?php echo htmlspecialchars($s['action']); ?> : <?php echo (int)$s['total']; ?></span>
    <?php endwhile; ?>
</div>
<?php endif; ?>

<div class="table-wrap"><table>
    <?php
    function sort_url($col, $current_sort, $current_dir, $tab, $page) {
        $new_dir = ($current_sort === $col && $current_dir === 'DESC') ? 'ASC' : 'DESC';
        return '?tab='.urlencode($tab).'&sort='.urlencode($col).'&dir='.$new_dir.'&page=1';
    }
    function sort_icon($col, $current_sort, $current_dir) {
        if ($current_sort !== $col) return '<span class="sort-icon">⇕</span>';
        return '<span class="sort-icon">'.($current_dir === 'ASC' ? '▲' : '▼').'</span>';
    }
    ?>
    <thead><tr>
        <th class="th-sort <?php echo $sort==='timestamp'?'active':''; ?>"><a href="<?php echo sort_url('timestamp',$sort,$dir,$tab,$page); ?>" style="text-decoration:none;color:inherit">Date<?php echo sort_icon('timestamp',$sort,$dir); ?></a></th>
        <th class="th-sort <?php echo $sort==='username'?'active':''; ?>"><a href="<?php echo sort_url('username',$sort,$dir,$tab,$page); ?>" style="text-decoration:none;color:inherit">Utilisateur<?php echo sort_icon('username',$sort,$dir); ?></a></th>
        <th class="th-sort <?php echo $sort==='action'?'active':''; ?>"><a href="<?php echo sort_url('action',$sort,$dir,$tab,$page); ?>" style="text-decoration:none;color:inherit">Action<?php echo sort_icon('action',$sort,$dir); ?></a></th>
        <th class="th-sort <?php echo $sort==='source'?'active':''; ?>"><a href="<?php echo sort_url('source',$sort,$dir,$tab,$page); ?>" style="text-decoration:none;color:inherit">Source<?php echo sort_icon('source',$sort,$dir); ?></a></th>
        <th class="th-sort <?php echo $sort==='details'?'active':''; ?>"><a href="<?php echo sort_url('details',$sort,$dir,$tab,$page); ?>" style="text-decoration:none;color:inherit">Details<?php echo sort_icon('details',$sort,$dir); ?></a></th>
        <th class="th-sort <?php echo $sort==='ip_address'?'active':''; ?>"><a href="<?php echo sort_url('ip_address',$sort,$dir,$tab,$page); ?>" style="text-decoration:none;color:inherit">IP<?php echo sort_icon('ip_address',$sort,$dir); ?></a></th>
        <th>Ville</th>
    </tr></thead>
    <tbody>
    <?php while ($row = mysqli_fetch_assoc($result)): ?>
    <tr>
        <td><?php echo htmlspecialchars($row['timestamp']); ?></td>
        <td><?php echo htmlspecialchars($row['username']); ?><?php echo ($row['user_id'] ? ' (#'.(int)$row['user_id'].')' : ''); ?></td>
        <td><span class="badge <?php echo actionBadge($row['action'], $row['source']); ?>"><?php echo htmlspecialchars($row['action']); ?></span></td>
        <td><strong><?php echo htmlspecialchars($row['source']); ?></strong></td>
        <td><?php echo htmlspecialchars($row['details']); ?></td>
        <td><?php echo htmlspecialchars($row['ip_address']); ?></td>
        <td><?php
            $ip = trim($row['ip_address']);
            // Afficher IP brute + résultat pour diagnostic
            $city = getCity($ip);
            echo '<span title="IP: '.htmlspecialchars($ip).'">';
            echo ($city !== 'N/A') ? htmlspecialchars($city) : '<span style="color:#aaa">N/A ('.$ip.')</span>';
            echo '</span>';
        ?></td>
    </tr>
    <?php endwhile; ?>
    </tbody>
</table></div>

<?php
$base_url = '?tab='.urlencode($tab).'&sort='.urlencode($sort).'&dir='.urlencode($dir);
if ($total_pages > 1):
?>
<div class="pagination">
    <span class="info"><?php echo $total_rows; ?> entr&eacute;es &mdash; page <?php echo $page; ?>/<?php echo $total_pages; ?></span>
    <?php if ($page > 1): ?>
        <a href="<?php echo $base_url.'&page=1'; ?>">&laquo;</a>
        <a href="<?php echo $base_url.'&page='.($page-1); ?>">&lsaquo; Préc</a>
    <?php endif; ?>
    <?php
    $start = max(1, $page - 3);
    $end   = min($total_pages, $page + 3);
    for ($p = $start; $p <= $end; $p++):
    ?>
        <a href="<?php echo $base_url.'&page='.$p; ?>" class="<?php echo ($p===$page)?'current':''; ?>"><?php echo $p; ?></a>
    <?php endfor; ?>
    <?php if ($page < $total_pages): ?>
        <a href="<?php echo $base_url.'&page='.($page+1); ?>">Suiv &rsaquo;</a>
        <a href="<?php echo $base_url.'&page='.$total_pages; ?>">&raquo;</a>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php endif; ?>

</div>
</body>
</html>
