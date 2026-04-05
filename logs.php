<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/include/functions_logs.php';

define('ADMIN_KEY', 'CardEvent@Admin2026!');

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
if ($tab === 'ios') {
    $query = "SELECT * FROM activity_logs WHERE source IN('iOS App','iOS Admin') ORDER BY timestamp DESC LIMIT 200";
} elseif ($tab === 'auth') {
    $query = "SELECT * FROM activity_logs WHERE source IN('iOS App','iOS Admin') AND action IN('login_success','login_failure','verify_success','verify_failure','logout') ORDER BY timestamp DESC LIMIT 200";
} else {
    $query = "SELECT * FROM activity_logs ORDER BY timestamp DESC LIMIT 200";
}
$result   = mysqli_query($conx, $query);
$statsIos = mysqli_query($conx, "SELECT action, COUNT(*) AS total FROM activity_logs WHERE source IN('iOS App','iOS Admin') GROUP BY action ORDER BY total DESC");
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Logs d'activite</title>
<link rel="stylesheet" href="css/base.css">
<style>
body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
.container { max-width: 1300px; margin: auto; }
a.back { display: inline-block; margin-bottom: 14px; }
h1 { margin-bottom: 10px; }
.tabs { display: flex; gap: 8px; margin-bottom: 16px; flex-wrap: wrap; border-bottom: 2px solid #ddd; padding-bottom: 8px; }
.tabs a { padding: 7px 16px; border-radius: 6px 6px 0 0; text-decoration: none; background: #ddd; color: #333; font-weight: bold; font-size: 14px; }
.tabs a.active { background: #4a90d9; color: #fff; }
.tabs a.tab-ios { background: #e8f4e8; color: #2a7a2a; border: 1px solid #b2d8b2; }
.tabs a.tab-ios.active { background: #2a7a2a; color: #fff; }
.tabs a.tab-admin { background: #fff3cd; color: #856404; border: 1px solid #ffeeba; }
.tabs a.tab-admin.active { background: #856404; color: #fff; }
.stats { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 14px; }
.stats span { padding: 4px 12px; border-radius: 16px; font-size: 12px; font-weight: bold; border: 1px solid #ccc; background: #fff; }
.stats span.sc { background: #d4edda; border-color: #b8dbbe; color: #155724; }
.stats span.fc { background: #f8d7da; border-color: #f5c6cb; color: #721c24; }
.stats span.vc { background: #d1ecf1; border-color: #bee5eb; color: #0c5460; }
.stats span.ac { background: #fff3cd; border-color: #ffeeba; color: #856404; }
.stats span.lc { background: #e2e3e5; border-color: #d6d8db; color: #383d41; }
table { width: 100%; border-collapse: collapse; background: #fff; box-shadow: 0 1px 4px rgba(0,0,0,.1); }
th, td { border-bottom: 1px solid #eee; padding: 7px 10px; text-align: left; font-size: 13px; }
th { background: #f2f2f2; font-size: 11px; text-transform: uppercase; letter-spacing: .4px; }
tr:hover td { background: #fafafa; }
.badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: bold; }
.badge-success { background: #d4edda; color: #155724; }
.badge-failure { background: #f8d7da; color: #721c24; }
.badge-verify  { background: #d1ecf1; color: #0c5460; }
.badge-admin   { background: #fff3cd; color: #856404; }
.badge-ios     { background: #e8f4e8; color: #2a7a2a; }
.badge-other   { background: #e2e3e5; color: #383d41; }
.admin-panel { background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 1px 4px rgba(0,0,0,.1); margin-bottom: 20px; }
.admin-panel h2 { margin: 0 0 16px; font-size: 16px; }
.admin-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 16px; }
.admin-card { border: 1px solid #eee; border-radius: 8px; padding: 16px; background: #fafafa; }
.admin-card h3 { margin: 0 0 8px; font-size: 14px; }
.admin-card p { margin: 0 0 12px; font-size: 12px; color: #666; }
.btn { padding: 8px 14px; border: none; border-radius: 6px; cursor: pointer; font-weight: bold; font-size: 13px; width: 100%; }
.btn-danger { background: #dc3545; color: #fff; }
.btn-danger:hover { background: #c82333; }
.btn-warn { background: #ffc107; color: #333; }
.btn-warn:hover { background: #e0a800; }
.btn-info { background: #17a2b8; color: #fff; }
.btn-info:hover { background: #138496; }
.key-input, .pseudo-input { width: 100%; padding: 6px 8px; border: 1px solid #ccc; border-radius: 4px; font-size: 12px; margin-bottom: 8px; box-sizing: border-box; }
.result-msg { margin-top: 10px; padding: 8px; border-radius: 4px; font-size: 13px; display: none; }
.result-ok  { background: #d4edda; color: #155724; }
.result-err { background: #f8d7da; color: #721c24; }
</style>
</head>
<body>
<div class="container">
<a href="index.php" class="back">&larr; Retour</a>
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

    </div>
</div>

<div class="admin-panel">
    <h2>Sessions actives (<?php echo count($sessions); ?>)</h2>
    <?php if (empty($sessions)): ?>
    <p style="color:#888;font-size:13px">Aucune session active.</p>
    <?php else: ?>
    <table>
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
    </table>
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

<table>
    <thead><tr>
        <th>Date</th>
        <th>Utilisateur</th>
        <th>Action</th>
        <th>Source</th>
        <th>Details</th>
        <th>IP</th>
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
        <td><?php echo getCity($row['ip_address']); ?></td>
    </tr>
    <?php endwhile; ?>
    </tbody>
</table>

<?php endif; ?>

</div>
</body>
</html>
