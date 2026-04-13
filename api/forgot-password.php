<?php
// ============================================================
//  forgot-password.php  –  Réinitialisation de mot de passe
//
//  POST {"action":"request_reset", "email":"..."}
//    → génère un token (30 min), envoie l'e-mail avec deep link
//
//  GET  ?token=XXX
//    → redirige vers cardevent://reset-password?token=XXX
//
//  POST {"action":"reset_password", "token":"...", "password":"..."}
//    → valide le token, met à jour le mot de passe
// ============================================================

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Content-Type: application/json');
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../serveur-smtp/send.php';

// ── Connexion PDO ─────────────────────────────────────────────
try {
    $pdo = new PDO(
        'mysql:host=localhost;dbname=dbs9616600;charset=utf8mb4',
        'root',
        'Kookies7*',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erreur de connexion à la base de données']);
    exit;
}

// ── Table des tokens de réinitialisation ─────────────────────
$pdo->exec("
    CREATE TABLE IF NOT EXISTS `password_reset_tokens` (
        `id`         INT AUTO_INCREMENT PRIMARY KEY,
        `membre_id`  INT NOT NULL,
        `token`      VARCHAR(64) NOT NULL,
        `expires_at` TIMESTAMP NOT NULL,
        `used`       TINYINT(1) NOT NULL DEFAULT 0,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY `unique_token` (`token`),
        INDEX `membre_idx` (`membre_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

// ── Routage ───────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    handleRedirect();
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $input  = json_decode(file_get_contents('php://input'), true) ?? [];
    $action = trim($input['action'] ?? '');
    if ($action === 'request_reset') {
        handleRequestReset($pdo, $input);
    } elseif ($action === 'reset_password') {
        handleResetPassword($pdo, $input);
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Action inconnue']);
    }
} else {
    header('Content-Type: application/json');
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Méthode non autorisée']);
}

// ── GET : redirection vers le deep link iOS ───────────────────
function handleRedirect(): void
{
    $token = trim($_GET['token'] ?? '');
    if (empty($token)) {
        renderPage('❌ Lien invalide', 'Ce lien de réinitialisation est invalide.', '#ff6b6b');
        return;
    }

    $deepLink = 'cardevent://reset-password?token=' . urlencode($token);
    header('Content-Type: text/html; charset=UTF-8');
    echo <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>CardEvent – Réinitialisation</title>
  <script>setTimeout(function(){ window.location = "$deepLink"; }, 500);</script>
</head>
<body style="margin:0;padding:0;background:#0d0d0d;font-family:Arial,sans-serif;color:#fff;
             display:flex;justify-content:center;align-items:center;min-height:100vh">
  <div style="max-width:420px;text-align:center;padding:40px 24px">
    <div style="font-size:60px;margin-bottom:12px">♠</div>
    <h1 style="color:#00d1ff;font-size:26px;font-weight:900;margin:0 0 6px">CardEvent</h1>
    <h2 style="color:#00d1ff;font-size:20px;margin:28px 0 16px">🔑 Ouverture de l'application…</h2>
    <p style="color:rgba(255,255,255,0.65);font-size:15px;line-height:1.7">
      Si l'application ne s'ouvre pas automatiquement,<br>
      assurez-vous que CardEvent est installé sur cet appareil.
    </p>
  </div>
</body>
</html>
HTML;
}

// ── POST : demande de réinitialisation ────────────────────────
function handleRequestReset(PDO $pdo, array $input): void
{
    $email = strtolower(trim($input['email'] ?? ''));

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Adresse e-mail invalide']);
        return;
    }

    // Chercher le membre (réponse identique qu'il existe ou non → sécurité)
    $stmt = $pdo->prepare("
        SELECT `id-membre`, `pseudo`, `fname`
        FROM `membres`
        WHERE `email` = ?
        LIMIT 1
    ");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        $memberId = (int)$user['id-membre'];
        $pseudo   = $user['pseudo'];
        $fname    = !empty($user['fname']) ? $user['fname'] : $pseudo;

        // Supprimer les anciens tokens de ce membre
        $pdo->prepare("DELETE FROM `password_reset_tokens` WHERE `membre_id` = ?")
            ->execute([$memberId]);

        // Générer un token (30 min)
        $token   = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+30 minutes'));
        $pdo->prepare("
            INSERT INTO `password_reset_tokens` (`membre_id`, `token`, `expires_at`)
            VALUES (?, ?, ?)
        ")->execute([$memberId, $token, $expires]);

        $resetUrl = 'https://viendez.com/api/forgot-password.php?token=' . $token;

        $body = <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#0d0d0d;font-family:Arial,sans-serif;color:#ffffff">
  <table width="100%" cellpadding="0" cellspacing="0">
    <tr>
      <td align="center" style="padding:40px 20px">
        <table width="480" cellpadding="0" cellspacing="0"
               style="background:#1a1a1a;border-radius:16px;overflow:hidden;max-width:480px">

          <tr>
            <td style="background:linear-gradient(135deg,#000,#1a1a2e);padding:32px;text-align:center">
              <div style="font-size:52px;line-height:1">♠</div>
              <h1 style="color:#00d1ff;font-size:26px;margin:12px 0 4px;font-weight:900">CardEvent</h1>
              <p style="color:rgba(255,255,255,0.55);margin:0;font-size:14px">Réinitialisation du mot de passe</p>
            </td>
          </tr>

          <tr>
            <td style="padding:32px">
              <p style="font-size:16px;margin:0 0 12px">
                Bonjour <strong style="color:#00d1ff">$fname</strong>,
              </p>
              <p style="color:rgba(255,255,255,0.75);line-height:1.7;margin:0 0 28px">
                Vous avez demandé la réinitialisation de votre mot de passe CardEvent.<br>
                Ce lien est valable <strong>30&nbsp;minutes</strong>.
              </p>

              <div style="text-align:center;margin-bottom:28px">
                <a href="$resetUrl"
                   style="display:inline-block;background:#00d1ff;color:#000000;font-weight:700;
                          font-size:16px;padding:14px 36px;border-radius:12px;text-decoration:none">
                  🔑 Réinitialiser mon mot de passe
                </a>
              </div>

              <p style="color:rgba(255,255,255,0.35);font-size:12px;text-align:center;word-break:break-all">
                Ou copiez ce lien : $resetUrl
              </p>

              <hr style="border:none;border-top:1px solid rgba(255,255,255,0.1);margin:24px 0">

              <p style="color:rgba(255,255,255,0.35);font-size:12px;text-align:center;margin:0">
                Si vous n'avez pas fait cette demande, ignorez simplement cet e-mail.
              </p>
            </td>
          </tr>

          <tr>
            <td style="background:#111;padding:14px;text-align:center">
              <p style="color:rgba(255,255,255,0.25);font-size:11px;margin:0">
                CardEvent · viendez.com
              </p>
            </td>
          </tr>

        </table>
      </td>
    </tr>
  </table>
</body>
</html>
HTML;

        sendRealEmail($email, '🔑 Réinitialisation de votre mot de passe CardEvent', $body);
    }

    // Toujours succès pour ne pas révéler si l'adresse est connue
    echo json_encode(['success' => true]);
}

// ── POST : réinitialisation du mot de passe ───────────────────
function handleResetPassword(PDO $pdo, array $input): void
{
    $token    = trim($input['token']    ?? '');
    $password = trim($input['password'] ?? '');

    if (empty($token) || empty($password)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Token et mot de passe requis']);
        return;
    }

    if (strlen($password) < 6) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Le mot de passe doit contenir au moins 6 caractères']);
        return;
    }

    // Valider le token
    $stmt = $pdo->prepare("
        SELECT r.membre_id, m.pseudo
        FROM `password_reset_tokens` r
        JOIN `membres` m ON m.`id-membre` = r.membre_id
        WHERE r.token = ?
          AND r.expires_at > NOW()
          AND r.used = 0
        LIMIT 1
    ");
    $stmt->execute([$token]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'error'   => 'Lien invalide ou expiré (30 min). Veuillez faire une nouvelle demande.',
        ]);
        return;
    }

    $memberId = (int)$row['membre_id'];

    // Mettre à jour le mot de passe (+ password_ext pour compatibilité)
    $pdo->prepare("
        UPDATE `membres`
        SET `password` = ?, `password_ext` = ?
        WHERE `id-membre` = ?
    ")->execute([$password, $password, $memberId]);

    // Marquer le token comme utilisé
    $pdo->prepare("UPDATE `password_reset_tokens` SET `used` = 1 WHERE `token` = ?")
        ->execute([$token]);

    echo json_encode(['success' => true]);
}

// ── Helper page HTML ──────────────────────────────────────────
function renderPage(string $title, string $message, string $color = '#00d1ff'): void
{
    header('Content-Type: text/html; charset=UTF-8');
    echo <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>CardEvent</title>
</head>
<body style="margin:0;padding:0;background:#0d0d0d;font-family:Arial,sans-serif;color:#fff;
             display:flex;justify-content:center;align-items:center;min-height:100vh">
  <div style="max-width:420px;text-align:center;padding:40px 24px">
    <div style="font-size:60px;margin-bottom:12px">♠</div>
    <h1 style="color:#00d1ff;font-size:26px;font-weight:900;margin:0 0 6px">CardEvent</h1>
    <h2 style="color:$color;font-size:20px;margin:28px 0 12px">$title</h2>
    <p style="color:rgba(255,255,255,0.65);font-size:15px;line-height:1.7">$message</p>
  </div>
</body>
</html>
HTML;
}
