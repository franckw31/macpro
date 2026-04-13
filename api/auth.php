<?php
// ============================================================
//  Endpoint d'authentification automatique pour l'app iOS
//  Actions :
//    login         → valide identifiants, retourne un token
//    verify_token  → vérifie un token existant (auto-login)
//    logout        → révoque le token
// ============================================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Méthode non autorisée']);
    exit;
}

$input    = json_decode(file_get_contents('php://input'), true);
$action   = trim($input['action']    ?? '');
$deviceId = trim($input['device_id'] ?? 'unknown');
$ip       = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$ua       = $_SERVER['HTTP_USER_AGENT'] ?? '';

try {
    $pdo = new PDO(
        'mysql:host=localhost;dbname=dbs9616600;charset=utf8mb4',
        'root',
        'Kookies7*',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // ── Table des tokens ──────────────────────────────────────────
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `app_auth_tokens` (
            `id`           INT AUTO_INCREMENT PRIMARY KEY,
            `membre_id`    INT NOT NULL,
            `token`        VARCHAR(64) NOT NULL,
            `device_id`    VARCHAR(255) NOT NULL,
            `created_at`   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `last_used_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            `expires_at`   TIMESTAMP NOT NULL,
            UNIQUE KEY `unique_token` (`token`),
            INDEX `device_idx` (`device_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // S'assurer que la colonne email_verified existe (avec DEFAULT 1 pour les comptes existants)
    try {
        $chk = $pdo->prepare(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME   = 'membres'
               AND COLUMN_NAME  = 'email_verified'"
        );
        $chk->execute();
        if ((int)$chk->fetchColumn() === 0) {
            $pdo->exec("ALTER TABLE `membres` ADD COLUMN `email_verified` TINYINT(1) NOT NULL DEFAULT 1");
        }
    } catch (PDOException $eChk) {
        // Ignore : la colonne sera ajoutée par register-player.php lors de la première inscription
    }

    // ── Helper : écrire dans activity_logs (même table que logs.php) ──
    $logAuth = function(string $event, ?string $pseudo = null, ?int $membreId = null)
        use ($pdo, $deviceId, $ip, $ua)
    {
        try {
            $details = $deviceId !== 'unknown' ? "device: $deviceId" : '';
            $pdo->prepare("
                INSERT INTO `activity_logs` (`user_id`, `username`, `action`, `source`, `details`, `ip_address`)
                VALUES (?, ?, ?, 'iOS App', ?, ?)
            ")->execute([$membreId ?? 0, $pseudo ?? 'unknown', $event, $details, $ip]);
        } catch (\Exception $e) {
            // Le log ne doit jamais bloquer l'auth
            error_log("logAuth error: " . $e->getMessage());
        }
    };

    // Helper : détermine si un membre est admin (colonne role optionnelle)
    function getIsAdmin(PDO $pdo, int $memberId): bool {
        if ($memberId === 265) return true;
        try {
            $s = $pdo->prepare("SELECT `droits` FROM `membres` WHERE `id-membre` = ? LIMIT 1");
            $s->execute([$memberId]);
            $droits = (int)$s->fetchColumn();
            return $droits === 2;
        } catch (PDOException $e) {
            return false;
        }
    }

    // Helper : détermine si un membre est organisateur Pro vérifié
    function getIsOrganizer(PDO $pdo, int $memberId): bool {
        try {
            $s = $pdo->prepare("SELECT is_verified FROM pro_organizers WHERE member_id = ? LIMIT 1");
            $s->execute([$memberId]);
            $row = $s->fetch(PDO::FETCH_ASSOC);
            return $row && (bool)$row['is_verified'];
        } catch (PDOException $e) {
            return false; // Table absente → pas encore installé
        }
    }

    // ── Action : login ────────────────────────────────────────────
    if ($action === 'login') {

        $username = trim($input['username'] ?? '');
        $password = trim($input['password'] ?? '');

        if (empty($username) || empty($password)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Identifiants manquants']);
            exit;
        }

        // Requête préparée (résistante aux injections SQL)
        $stmt = $pdo->prepare("
            SELECT `id-membre`, `pseudo`, `email`
            FROM `membres`
            WHERE (`pseudo` = :u OR `email` = :u)
              AND (`password` = :p OR `password_ext` = :p)
              AND (email_verified IS NULL OR email_verified = 1)
            LIMIT 1
        ");
        $stmt->execute([':u' => $username, ':p' => $password]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            // Vérifier si le compte existe mais n'est pas encore vérifié
            $stmtUnverified = $pdo->prepare("
                SELECT COUNT(*) FROM `membres`
                WHERE (`pseudo` = :u OR `email` = :u)
                  AND (`password` = :p OR `password_ext` = :p)
                  AND email_verified = 0
            ");
            $stmtUnverified->execute([':u' => $username, ':p' => $password]);
            if ((int)$stmtUnverified->fetchColumn() > 0) {
                $logAuth('login_failure_unverified', $username);
                http_response_code(401);
                echo json_encode(['success' => false, 'error' => 'Compte non vérifié – consultez votre e-mail pour activer votre compte']);
                exit;
            }
            $logAuth('login_failure', $username);
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Identifiants invalides']);
            exit;
        }

        $memberId    = (int)$user['id-membre'];
        $isAdmin     = getIsAdmin($pdo, $memberId);
        $isOrganizer = $isAdmin || getIsOrganizer($pdo, $memberId);
        $token     = bin2hex(random_bytes(32));   // 64 caractères hex
        $expiresAt = date('Y-m-d H:i:s', strtotime('+90 days'));

        // Supprimer l'ancien token de ce device
        $pdo->prepare("DELETE FROM `app_auth_tokens` WHERE `device_id` = ?")
            ->execute([$deviceId]);

        // Insérer le nouveau token
        $pdo->prepare("
            INSERT INTO `app_auth_tokens` (`membre_id`, `token`, `device_id`, `expires_at`)
            VALUES (?, ?, ?, ?)
        ")->execute([$memberId, $token, $deviceId, $expiresAt]);

        $logAuth('login_success', $user['pseudo'], $memberId);

        echo json_encode([
            'success'      => true,
            'token'        => $token,
            'user_id'      => $memberId,
            'pseudo'       => $user['pseudo'],
            'is_admin'     => $isAdmin,
            'is_organizer' => $isOrganizer,
            'expires_at'   => $expiresAt,
        ]);

    // ── Action : verify_token ─────────────────────────────────────
    } elseif ($action === 'verify_token') {

        $token = trim($input['token'] ?? '');

        if (empty($token)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Token manquant']);
            exit;
        }

        $stmt = $pdo->prepare("
            SELECT t.token, m.pseudo, m.`id-membre` AS user_id
            FROM `app_auth_tokens` t
            JOIN `membres` m ON m.`id-membre` = t.membre_id
            WHERE t.token = ?
              AND t.expires_at > NOW()
            LIMIT 1
        ");
        $stmt->execute([$token]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            $logAuth('verify_failure');
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Token invalide ou expiré']);
            exit;
        }

        // Rafraîchir last_used_at
        $pdo->prepare("UPDATE `app_auth_tokens` SET `last_used_at` = NOW() WHERE `token` = ?")
            ->execute([$token]);

        $isAdmin     = getIsAdmin($pdo, (int)$row['user_id']);
        $isOrganizer = $isAdmin || getIsOrganizer($pdo, (int)$row['user_id']);

        $logAuth('verify_success', $row['pseudo'], $row['user_id']);

        echo json_encode([
            'success'      => true,
            'pseudo'       => $row['pseudo'],
            'user_id'      => $row['user_id'],
            'is_admin'     => $isAdmin,
            'is_organizer' => $isOrganizer,
        ]);

    // ── Action : logout ───────────────────────────────────────────
    } elseif ($action === 'logout') {

        $token = trim($input['token'] ?? '');
        if (!empty($token)) {
            // Récupérer le pseudo avant suppression pour le log
            $row = $pdo->prepare("SELECT m.pseudo, t.membre_id FROM `app_auth_tokens` t JOIN `membres` m ON m.`id-membre` = t.membre_id WHERE t.token = ? LIMIT 1");
            $row->execute([$token]);
            $logoutUser = $row->fetch(PDO::FETCH_ASSOC);
            $pdo->prepare("DELETE FROM `app_auth_tokens` WHERE `token` = ?")
                ->execute([$token]);
            $logAuth('logout', $logoutUser['pseudo'] ?? null, $logoutUser['membre_id'] ?? null);
        } else {
            $logAuth('logout');
        }
        echo json_encode(['success' => true]);

    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => "Action inconnue : $action"]);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erreur serveur: ' . $e->getMessage()]);
}
