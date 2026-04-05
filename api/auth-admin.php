<?php
// ============================================================
//  Admin : gestion des autorisations de l'app iOS
//  Protégé par clé secrète (header X-Admin-Key ou ?key=)
//
//  Actions POST (JSON) :
//    revoke_all          → révoque TOUS les tokens
//    revoke_user         → révoque les tokens d'un pseudo/membre_id
//    revoke_device       → révoque le token d'un device_id
//    revoke_expired      → purge les tokens expirés
//    list_sessions       → liste les sessions actives
//    clear_logs          → vide la table app_auth_logs
//
//  GET ?action=list_sessions → lecture rapide (mêmes accès)
// ============================================================

define('ADMIN_KEY', 'CardEvent@Admin2026!');   // ← change cette clé

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Admin-Key');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

// ── Auth admin ────────────────────────────────────────────────
$keyHeader = $_SERVER['HTTP_X_ADMIN_KEY'] ?? '';
$keyGet    = $_GET['key'] ?? '';
$keyBody   = '';

$rawInput = file_get_contents('php://input');
$input    = json_decode($rawInput, true) ?? [];
$keyBody  = $input['admin_key'] ?? '';

if (!in_array(ADMIN_KEY, [$keyHeader, $keyGet, $keyBody], true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Accès refusé']);
    exit;
}

// ── Connexion DB ──────────────────────────────────────────────
try {
    $pdo = new PDO(
        'mysql:host=localhost;dbname=dbs9616600;charset=utf8mb4',
        'root',
        'Kookies7*',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'DB: ' . $e->getMessage()]);
    exit;
}

// ── Action ───────────────────────────────────────────────────
$action = trim($input['action'] ?? $_GET['action'] ?? 'list_sessions');

try {

    // ── Révoquer TOUS les tokens ──────────────────────────────
    if ($action === 'revoke_all') {

        $count = $pdo->query("SELECT COUNT(*) FROM `app_auth_tokens`")->fetchColumn();
        $pdo->exec("TRUNCATE TABLE `app_auth_tokens`");
        adminLog($pdo, 'revoke_all', "Tous les tokens révoqués ($count sessions)");
        echo json_encode(['success' => true, 'revoked' => (int)$count, 'message' => "Tous les tokens révoqués ($count sessions) — tous les appareils devront se reconnecter"]);

    // ── Révoquer par utilisateur ──────────────────────────────
    } elseif ($action === 'revoke_user') {

        $pseudo   = trim($input['pseudo']    ?? '');
        $membreId = (int)($input['membre_id'] ?? 0);

        if ($pseudo === '' && $membreId === 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'pseudo ou membre_id requis']);
            exit;
        }

        if ($membreId === 0) {
            $row = $pdo->prepare("SELECT `id-membre` FROM `membres` WHERE `pseudo` = ? OR `email` = ? LIMIT 1");
            $row->execute([$pseudo, $pseudo]);
            $found = $row->fetch();
            $membreId = $found ? (int)$found['id-membre'] : 0;
        }

        if ($membreId === 0) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => "Utilisateur introuvable : $pseudo"]);
            exit;
        }

        $stmt = $pdo->prepare("DELETE FROM `app_auth_tokens` WHERE `membre_id` = ?");
        $stmt->execute([$membreId]);
        $count = $stmt->rowCount();
        adminLog($pdo, 'revoke_user', "Tokens révoqués pour membre_id=$membreId ($pseudo) — $count session(s)");
        echo json_encode(['success' => true, 'revoked' => $count, 'membre_id' => $membreId, 'pseudo' => $pseudo]);

    // ── Révoquer par device ───────────────────────────────────
    } elseif ($action === 'revoke_device') {

        $deviceId = trim($input['device_id'] ?? '');
        if ($deviceId === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'device_id requis']);
            exit;
        }

        $stmt = $pdo->prepare("DELETE FROM `app_auth_tokens` WHERE `device_id` = ?");
        $stmt->execute([$deviceId]);
        $count = $stmt->rowCount();
        adminLog($pdo, 'revoke_device', "Token révoqué pour device $deviceId — $count session(s)");
        echo json_encode(['success' => true, 'revoked' => $count, 'device_id' => $deviceId]);

    // ── Purger les tokens expirés ─────────────────────────────
    } elseif ($action === 'revoke_expired') {

        $stmt = $pdo->query("DELETE FROM `app_auth_tokens` WHERE `expires_at` < NOW()");
        $count = $stmt->rowCount();
        adminLog($pdo, 'revoke_expired', "$count token(s) expirés supprimés");
        echo json_encode(['success' => true, 'purged' => $count]);

    // ── Vider les logs ────────────────────────────────────────
    } elseif ($action === 'clear_logs') {

        $count = $pdo->query("SELECT COUNT(*) FROM `app_auth_logs`")->fetchColumn();
        $pdo->exec("TRUNCATE TABLE `app_auth_logs`");
        echo json_encode(['success' => true, 'deleted' => (int)$count]);

    // ── Lister les sessions actives ───────────────────────────
    } elseif ($action === 'list_sessions') {

        $rows = $pdo->query("
            SELECT t.id, m.pseudo, m.`id-membre` AS membre_id,
                   t.device_id, t.created_at, t.last_used_at, t.expires_at
            FROM `app_auth_tokens` t
            JOIN `membres` m ON m.`id-membre` = t.membre_id
            ORDER BY t.last_used_at DESC
        ")->fetchAll();

        $total = count($rows);
        echo json_encode(['success' => true, 'total' => $total, 'sessions' => $rows], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => "Action inconnue : $action"]);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

// ── Helper log admin → écrit dans activity_logs (visible dans logs.php) ──
function adminLog(PDO $pdo, string $event, string $detail): void {
    try {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $pdo->prepare("
            INSERT INTO `activity_logs` (`user_id`, `username`, `action`, `source`, `details`, `ip_address`)
            VALUES (0, 'admin', ?, 'iOS Admin', ?, ?)
        ")->execute(["admin:$event", $detail, $ip]);
    } catch (PDOException $e) { /* silencieux */ }
}
