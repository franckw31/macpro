


<?php
if(function_exists('ob_clean')) @ob_clean();
@flush();
echo "[register-activity.php] start\n";
@flush();
error_log('[register-activity.php] called '.date('c'));

// Catch-all pour erreurs fatales : affiche en JSON
set_exception_handler(function($e){
    echo "[EXCEPTION] ".$e->getMessage()."\n";
    exit;
});
set_error_handler(function($errno, $errstr, $errfile, $errline){
    echo "[PHP ERROR $errno] $errstr ($errfile:$errline)\n";
    exit;
});

echo "[headers]...\n";
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
echo "[headers OK]\n";
@flush();

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    echo "[OPTIONS] exit\n";
    http_response_code(200);
    exit;
}

echo "[PDO]...\n";
@flush();
try {
    $pdo = new PDO(
        'mysql:host=localhost;dbname=dbs9616600;charset=utf8mb4',
        'root',
        'Kookies7*',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
    echo "[PDO OK]\n";
    @flush();

    // Local debug log file (activated with ?debug=1)
    $debug_log_file = __DIR__ . '/register-activity.debug.log';
    function write_debug_file($msg){
        global $debug_log_file;
        $ts = date('c');
        @file_put_contents($debug_log_file, "[".$ts."] " . $msg . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
    echo "[write_debug_file OK]\n";
    @flush();

    // ... (on peut continuer à ajouter des echo avant/après chaque bloc clé si besoin)
    // continue to main logic (debug test removed)

} catch (Exception $e) {
    echo "[CATCH] ".$e->getMessage()."\n";
    exit;
}

try {
    $pdo = new PDO(
        'mysql:host=localhost;dbname=dbs9616600;charset=utf8mb4',
        'root',
        'Kookies7*',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );

    // Local debug log file (activated with ?debug=1)
    $debug_log_file = __DIR__ . '/register-activity.debug.log';
    function write_debug_file($msg){
        global $debug_log_file;
        $ts = date('c');
        @file_put_contents($debug_log_file, "[".$ts."] " . $msg . PHP_EOL, FILE_APPEND | LOCK_EX);
    }

    // ── Authentification Bearer token ────────────────────────────
    $token = null;
    $headers = function_exists('apache_request_headers') ? apache_request_headers() : [];
    // Normalize headers array keys to allow case-insensitive lookup
    $normHeaders = [];
    foreach($headers as $k => $v){ $normHeaders[strtolower($k)] = $v; }
    $authHeader = '';
    if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
    } elseif (!empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        $authHeader = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
    } elseif (!empty($normHeaders['authorization'])) {
        $authHeader = $normHeaders['authorization'];
    } elseif (!empty($normHeaders['Authorization'])) {
        $authHeader = $normHeaders['Authorization'];
    }
    if (isset($_GET['debug']) && $_GET['debug'] === '1') {
        error_log('[register-activity DEBUG] $_SERVER[HTTP_AUTHORIZATION]=' . (isset($_SERVER['HTTP_AUTHORIZATION'])? $_SERVER['HTTP_AUTHORIZATION'] : '(none)'));
        error_log('[register-activity DEBUG] $_SERVER[REDIRECT_HTTP_AUTHORIZATION]=' . (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] : '(none)'));
        error_log('[register-activity DEBUG] apache_request_headers=' . json_encode($headers, JSON_UNESCAPED_UNICODE));
        error_log('[register-activity DEBUG] normalized_headers=' . json_encode($normHeaders, JSON_UNESCAPED_UNICODE));
        error_log('[register-activity DEBUG] authHeader(final)=' . $authHeader);
        // Authentification : priorité à la session PHP (comme quickview.php)
        if(session_status() !== PHP_SESSION_ACTIVE) session_start();
        $userId = null;
        $pseudo = null;
        if(!empty($_SESSION['id'])){
            $sessId = intval($_SESSION['id']);
            $mq = $pdo->prepare("SELECT `id-membre`, `pseudo` FROM membres WHERE `id-membre` = ? LIMIT 1");
            $mq->execute([$sessId]);
            $mrow = $mq->fetch();
            if($mrow){
                $userId = (int)$mrow['id-membre'];
                $pseudo = $mrow['pseudo'];
                if (isset($_GET['debug']) && $_GET['debug'] === '1') error_log('[register-activity DEBUG] authenticated via PHP session id=' . $userId);
            }
        }
        // Si pas de session, on tente le token (API pure)
        if (empty($userId)) {
            $token = null;
            $headers = function_exists('apache_request_headers') ? apache_request_headers() : [];
            $normHeaders = [];
            foreach($headers as $k => $v){ $normHeaders[strtolower($k)] = $v; }
            $authHeader = '';
            if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
                $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
            } elseif (!empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
                $authHeader = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
            } elseif (!empty($normHeaders['authorization'])) {
                $authHeader = $normHeaders['authorization'];
            } elseif (!empty($normHeaders['Authorization'])) {
                $authHeader = $normHeaders['Authorization'];
            }
            if (preg_match('/Bearer\s+(.+)/i', $authHeader, $m)) {
                $token = trim($m[1]);
            }
            if (!$token && isset($_POST['token'])) {
                $token = trim($_POST['token']);
            }
            if ($token) {
                $stmt = $pdo->prepare(""
                    SELECT at.membre_id, m.pseudo
                    FROM app_auth_tokens at
                    JOIN membres m ON m.`id-membre` = at.membre_id
                    WHERE at.token = ?
                      AND (at.expires_at IS NULL OR at.expires_at > NOW())
                ""
                );
                $stmt->execute([$token]);
                $user = $stmt->fetch();
                if ($user){
                    $userId   = (int)$user['membre_id'];
                    $pseudo   = $user['pseudo'];
                }
            }
        }
        // Si toujours pas d'utilisateur authentifié, erreur
        if (empty($userId) || empty($pseudo)){
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Non authentifié (session ou token)']);
            exit;
        }
                $userId   = (int)$user['membre_id'];
                $pseudo   = $user['pseudo'];
            } else {
                http_response_code(401);
                echo json_encode(['success' => false, 'error' => 'Token invalide ou expiré']);
                exit;
            }
        }
        // If we reach here and still have no authenticated user, reject
        if (empty($userId) || empty($pseudo)){
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Token manquant']);
            exit;
        }

    // ── Lire le body JSON une seule fois (POST) ──────────────────
    $input = [];
    $raw = null;
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $raw = file_get_contents('php://input');
        if (isset($_GET['debug']) && $_GET['debug'] === '1') {
            write_debug_file('raw_post=' . var_export($raw, true));
            write_debug_file('content_type=' . ($_SERVER['CONTENT_TYPE'] ?? '(none)'));
        }
        if (empty($raw)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'POST body vide']);
            exit;
        }
        if (strpos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') === false) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Content-Type doit être application/json']);
            exit;
        }
        $input = json_decode($raw, true);
        if (!is_array($input)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Corps JSON invalide', 'raw'=>$raw]);
            exit;
        }
    }

    // ── Trouver l'activité : paramètre explicite ou auto-détection
    $requestedId = 0;
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $requestedId = isset($_GET['activity_id']) ? (int)$_GET['activity_id'] : 0;
    } else {
        $requestedId = isset($input['activity_id']) ? (int)$input['activity_id'] : 0;
    }

    if ($requestedId > 0) {
        $actStmt = $pdo->prepare("
            SELECT `id-activite`, `titre-activite`, `date_depart`, `heure_depart`, `id_challenge`, COALESCE(`jetons`, 0) as jetons
            FROM activite WHERE `id-activite` = ?
        ");
        $actStmt->execute([$requestedId]);
        $activity = $actStmt->fetch();
    } else {
        // 1) En cours ou très récente (max 2 jours)
        $actStmt = $pdo->query("
            SELECT `id-activite`, `titre-activite`, `date_depart`, `heure_depart`, `id_challenge`, COALESCE(`jetons`, 0) as jetons
            FROM activite
            WHERE date_depart >= (NOW() - INTERVAL 2 DAY)
            ORDER BY date_depart ASC LIMIT 1
        ");
        $activity = $actStmt->fetch();

        if (!$activity) {
            // 2) Fallback : dernière activité passée
            $actStmt2 = $pdo->query("
                SELECT `id-activite`, `titre-activite`, `date_depart`, `heure_depart`, `id_challenge`, COALESCE(`jetons`, 0) as jetons
                FROM activite ORDER BY date_depart DESC LIMIT 1
            ");
            $activity = $actStmt2->fetch();
        }
    }

    if (!$activity) {
        echo json_encode(['success' => false, 'error' => 'Aucune activité trouvée']);
        exit;
    }

    $actId       = (int)$activity['id-activite'];
    $challengeId = (int)($activity['id_challenge'] ?? 0);
    $actJetons   = (int)($activity['jetons'] ?? 0);

    // ── Créer les colonnes bonus si elles n'existent pas ─────────
    $pdo->exec("ALTER TABLE participation
        ADD COLUMN IF NOT EXISTS `jetons_bonus_ins`    int(11) NOT NULL DEFAULT 0,
        ADD COLUMN IF NOT EXISTS `jetons_bonus_arrivee` int(11) NOT NULL DEFAULT 0,
        ADD COLUMN IF NOT EXISTS `jetons_total`        int(11) NOT NULL DEFAULT 0
    ");

    // ── Calcul jetons_bonus_ins via MySQL (timezone cohérente) ──────
    // +200 jetons par heure entière avant heure_depart, max 5000
    $bonusStmt = $pdo->prepare("
        SELECT LEAST(5000, GREATEST(0,
            FLOOR(TIMESTAMPDIFF(SECOND, NOW(), `date_depart`) / 3600) * 200
        )) AS bonus_ins
        FROM activite WHERE `id-activite` = ?
    ");
    $bonusStmt->execute([$actId]);
    $bonusRow = $bonusStmt->fetch();
    $bonusIns = (int)($bonusRow['bonus_ins'] ?? 0);

    // ── Statuts considérés comme "inscrit" ───────────────────────
    $registeredStatuses = ['Inscrit', 'Option', 'Réservation', 'Reservation', 'Présent', 'Present', 'Confirmé', 'Confirme', 'Eliminé', 'Elimine'];

    // Participation actuelle
    $pStmt = $pdo->prepare("
        SELECT `option`, COALESCE(`jetons_bonus_arrivee`, 0) as jetons_bonus_arrivee
        FROM participation
        WHERE `id-membre` = ? AND `id-activite` = ?
    ");
    $pStmt->execute([$userId, $actId]);
    $part              = $pStmt->fetch();
    $currentStatus     = $part ? $part['option'] : 'None';
    $isRegistered      = in_array($currentStatus, $registeredStatuses);
    $existingBonusArr  = $part ? (int)$part['jetons_bonus_arrivee'] : 0;

    // ── GET : retourner le statut actuel ─────────────────────────
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        echo json_encode([
            'success'        => true,
            'activity_id'    => $actId,
            'activity_title' => $activity['titre-activite'],
            'activity_date'  => $activity['date_depart'],
            'status'         => $currentStatus,
            'registered'     => $isRegistered,
        ]);
        exit;
    }

    // ── POST : toggle ou action explicite ────────────────────────
    $action = $input['action'] ?? $_POST['action'] ?? 'toggle';

    if ($action === 'toggle') {
        $newStatus = $isRegistered ? 'None' : 'Inscrit';
        if ($newStatus === 'None') { $bonusIns = 0; } // pas de bonus à la désinscription
    } elseif ($action === 'register') {
        $anonyme  = !empty($input['anonyme']);
        $isOption = !empty($input['is_option']);
        $latereg  = !empty($input['latereg']);
        if ($isOption)  $newStatus = 'Option';
        else            $newStatus = 'Inscrit';
    } elseif ($action === 'unregister') {
        $newStatus = 'None';
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => "Action inconnue: $action"]);
        exit;
    }

    $valide     = in_array($newStatus, ['Inscrit', 'Option']) ? 'Actif' : 'Inactif';
    $anonymeVal = (isset($anonyme) && $anonyme && $action === 'register') ? 1 : 0;
    $lateregVal = (isset($latereg) && $latereg && $action === 'register') ? 1 : 0;

    // ── Ne pas écraser le bonus si on désinscrit ──────────────────
    if ($newStatus === 'None') {
        $upStmt = $pdo->prepare("
            UPDATE participation
            SET `option` = ?, `valide` = ?, `ds` = NOW()
            WHERE `id-membre` = ? AND `id-activite` = ?
        ");
        $upStmt->execute([$newStatus, $valide, $userId, $actId]);
    } elseif ($part) {
        // Mise à jour de la participation existante (réinscription ou modification)
        $jetonsTotal = $actJetons + $bonusIns + $existingBonusArr;
        $upStmt = $pdo->prepare("
            UPDATE participation
            SET `option` = ?, `valide` = ?, `anonyme` = ?, `latereg` = ?,
                `jetons` = ?, `jetons_bonus_ins` = ?, `jetons_total` = ?, `ds` = NOW()
            WHERE `id-membre` = ? AND `id-activite` = ?
        ");
        $upStmt->execute([$newStatus, $valide, $anonymeVal, $lateregVal, $actJetons, $bonusIns, $jetonsTotal, $userId, $actId]);
    } else {
        // Création d'une nouvelle participation
        $ordStmt = $pdo->query("SELECT MAX(ordre) as max_o FROM participation WHERE `id-activite` = $actId");
        $ordRow  = $ordStmt->fetch();
        $nextOrdre = intval($ordRow['max_o'] ?? 0) + 1;

        $jetonsTotal = $actJetons + $bonusIns;
        $insStmt = $pdo->prepare("
            INSERT INTO participation
                (`id-membre`, `id-activite`, `nom-membre`, `option`, `anonyme`, `latereg`, `id-challenge`, `ordre`, `valide`, `classement`, `id_rake`, `jetons`, `jetons_bonus_ins`, `jetons_bonus_arrivee`, `jetons_total`, `ds`)
            VALUES
                (?, ?, ?, ?, ?, ?, ?, ?, 'Actif', '0', 1, ?, ?, 0, ?, NOW())
        ");
        $insStmt->execute([$userId, $actId, $pseudo, $newStatus, $anonymeVal, $lateregVal, $challengeId, $nextOrdre, $actJetons, $bonusIns, $jetonsTotal]);
    }

    $newIsRegistered = in_array($newStatus, ['Inscrit', 'Option']);

    echo json_encode([
        'success'          => true,
        'activity_id'      => $actId,
        'status'           => $newStatus,
        'registered'       => $newIsRegistered,
        'jetons_bonus_ins' => $bonusIns,
        'jetons_total'     => $actJetons + $bonusIns,
        'action_taken'     => $newStatus === 'None' ? 'unregister' : ($part ? 'update' : 'insert'),
        'debug_actJetons'  => $actJetons,
    ]);

} catch (Exception $e) {
    http_response_code(500);
    $msg = $e->getMessage();
    if (isset($_GET['debug']) && $_GET['debug'] === '1') {
        $msg .= "\n" . $e->getTraceAsString();
    }
    echo json_encode(['success' => false, 'error' => $msg]);
}
?>
