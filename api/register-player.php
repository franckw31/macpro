<?php
// ============================================================
//  register-player.php  –  Inscription d'un nouveau joueur
//
//  POST {"action":"register", pseudo, fname, lname, email,
//        password, ville, date_naissance}
//    → crée le compte (email_verified=0), envoie l'e-mail de
//      vérification, retourne JSON {success:true}
//
//  GET ?action=verify_email&token=XXXX
//    → active le compte et affiche une page HTML de confirmation
// ============================================================

// CORS
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

// ── Colonnes supplémentaires (ajoutées si absentes) ───────────
$colonnes = [
    'fname'                      => 'VARCHAR(100) NULL DEFAULT NULL',
    'lname'                      => 'VARCHAR(100) NULL DEFAULT NULL',
    'ville'                      => 'VARCHAR(100) NULL DEFAULT NULL',
    'date_naissance'             => 'DATE NULL DEFAULT NULL',
    'email_verified'             => 'TINYINT(1) NOT NULL DEFAULT 1',
    'email_verification_token'   => 'VARCHAR(64) NULL DEFAULT NULL',
    'email_verification_expires' => 'TIMESTAMP NULL DEFAULT NULL',
];

foreach ($colonnes as $col => $def) {
    try {
        $check = $pdo->prepare(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME   = 'membres'
               AND COLUMN_NAME  = ?"
        );
        $check->execute([$col]);
        if ((int)$check->fetchColumn() === 0) {
            $pdo->exec("ALTER TABLE `membres` ADD COLUMN `$col` $def");
        }
    } catch (PDOException $e) {
        // Ignore – la colonne existe peut-être déjà avec un type différent
    }
}

// ── Routage ───────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    handleVerifyEmail($pdo);
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    handleRegister($pdo);
} else {
    header('Content-Type: application/json');
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Méthode non autorisée']);
}

// ─────────────────────────────────────────────────────────────

// ── POST : inscription ────────────────────────────────────────
function handleRegister(PDO $pdo): void
{
    $input = json_decode(file_get_contents('php://input'), true) ?? [];

    if (trim($input['action'] ?? '') !== 'register') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Action inconnue']);
        return;
    }

    // Récupération des champs
    $pseudo        = trim($input['pseudo']        ?? '');
    $lname         = trim($input['lname']         ?? '');
    $fname         = trim($input['fname']         ?? '');
    $email         = strtolower(trim($input['email'] ?? ''));
    $password      = trim($input['password']      ?? '');
    $ville         = trim($input['ville']         ?? '');
    $dateNaissance = trim($input['date_naissance'] ?? '');

    // Validation
    if (empty($pseudo) || empty($lname) || empty($fname) ||
        empty($email)  || empty($password) ||
        empty($ville)  || empty($dateNaissance)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Tous les champs sont obligatoires']);
        return;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Adresse e-mail invalide']);
        return;
    }

    if (strlen($password) < 6) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Le mot de passe doit contenir au moins 6 caractères']);
        return;
    }

    if (!DateTime::createFromFormat('Y-m-d', $dateNaissance)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Format de date invalide (attendu : YYYY-MM-DD)']);
        return;
    }

    // Pseudo already taken?
    $s = $pdo->prepare("SELECT COUNT(*) FROM `membres` WHERE `pseudo` = ?");
    $s->execute([$pseudo]);
    if ((int)$s->fetchColumn() > 0) {
        http_response_code(409);
        echo json_encode(['success' => false, 'error' => 'Ce pseudo est déjà utilisé, choisissez-en un autre']);
        return;
    }

    // Email already registered?
    $s = $pdo->prepare("SELECT COUNT(*) FROM `membres` WHERE `email` = ?");
    $s->execute([$email]);
    if ((int)$s->fetchColumn() > 0) {
        http_response_code(409);
        echo json_encode(['success' => false, 'error' => 'Cette adresse e-mail est déjà associée à un compte']);
        return;
    }

    // Génération du token de vérification (valable 24 h)
    $token   = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', strtotime('+24 hours'));

    // Insertion du membre (non vérifié)
    $pdo->prepare("
        INSERT INTO `membres`
            (`pseudo`, `fname`, `lname`, `email`, `password`,
             `ville`, `date_naissance`,
             `email_verified`, `email_verification_token`, `email_verification_expires`)
        VALUES (?, ?, ?, ?, ?,  ?, ?,  0, ?, ?)
    ")->execute([
        $pseudo, $fname, $lname, $email, $password,
        $ville, $dateNaissance,
        $token, $expires,
    ]);

    // E-mail de vérification
    $verifyUrl = 'https://viendez.com/api/register-player.php?action=verify_email&token=' . $token;

    $body = <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
</head>
<body style="margin:0;padding:0;background:#0d0d0d;font-family:Arial,sans-serif;color:#ffffff">
  <table width="100%" cellpadding="0" cellspacing="0">
    <tr>
      <td align="center" style="padding:40px 20px">
        <table width="480" cellpadding="0" cellspacing="0"
               style="background:#1a1a1a;border-radius:16px;overflow:hidden;max-width:480px">

          <!-- En-tête -->
          <tr>
            <td style="background:linear-gradient(135deg,#000,#1a1a2e);padding:32px;text-align:center">
              <div style="font-size:52px;line-height:1">♠</div>
              <h1 style="color:#00d1ff;font-size:26px;margin:12px 0 4px;font-weight:900">CardEvent</h1>
              <p style="color:rgba(255,255,255,0.55);margin:0;font-size:14px">Vérification de votre compte</p>
            </td>
          </tr>

          <!-- Corps -->
          <tr>
            <td style="padding:32px">
              <p style="font-size:16px;margin:0 0 12px">
                Bonjour <strong style="color:#00d1ff">{$fname} {$lname}</strong>,
              </p>
              <p style="color:rgba(255,255,255,0.75);line-height:1.7;margin:0 0 12px">
                Bienvenue sur <strong>CardEvent</strong> ! Votre compte joueur a été créé
                avec le pseudo <strong style="color:#00d1ff">{$pseudo}</strong>.
              </p>
              <p style="color:rgba(255,255,255,0.75);line-height:1.7;margin:0 0 28px">
                Cliquez sur le bouton ci-dessous pour activer votre compte.
                Ce lien est valable <strong>24&nbsp;heures</strong>.
              </p>

              <!-- Bouton CTA -->
              <div style="text-align:center;margin-bottom:28px">
                <a href="{$verifyUrl}"
                   style="display:inline-block;background:#00d1ff;color:#000000;font-weight:700;
                          font-size:16px;padding:14px 36px;border-radius:12px;text-decoration:none;
                          letter-spacing:.3px">
                  ✅ Activer mon compte
                </a>
              </div>

              <!-- Lien de secours -->
              <p style="color:rgba(255,255,255,0.35);font-size:12px;text-align:center;word-break:break-all">
                Ou copiez ce lien : {$verifyUrl}
              </p>

              <hr style="border:none;border-top:1px solid rgba(255,255,255,0.1);margin:24px 0">

              <p style="color:rgba(255,255,255,0.35);font-size:12px;text-align:center;margin:0">
                Si vous n'avez pas créé ce compte, ignorez simplement cet e-mail.
              </p>
            </td>
          </tr>

          <!-- Pied de page -->
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

    sendRealEmail($email, '✅ Activez votre compte CardEvent', $body);

    echo json_encode([
        'success' => true,
        'message' => "Un e-mail de vérification a été envoyé à $email. Valable 24 h.",
    ]);
}

// ── GET : vérification du token ───────────────────────────────
function handleVerifyEmail(PDO $pdo): void
{
    $action = trim($_GET['action'] ?? '');
    $token  = trim($_GET['token']  ?? '');

    if ($action !== 'verify_email' || empty($token)) {
        renderHtml('Lien invalide', 'Ce lien de vérification est invalide.', false);
        return;
    }

    $stmt = $pdo->prepare("
        SELECT `id-membre`, `pseudo`
        FROM `membres`
        WHERE `email_verification_token` = ?
          AND `email_verification_expires` > NOW()
          AND `email_verified` = 0
        LIMIT 1
    ");
    $stmt->execute([$token]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        renderHtml(
            '❌ Lien expiré ou déjà utilisé',
            'Ce lien de vérification est invalide ou a expiré (24&nbsp;h).<br>'
          . 'Veuillez vous ré-inscrire depuis l\'application.',
            false
        );
        return;
    }

    // Activer le compte
    $pdo->prepare("
        UPDATE `membres`
        SET `email_verified`             = 1,
            `email_verification_token`   = NULL,
            `email_verification_expires` = NULL
        WHERE `id-membre` = ?
    ")->execute([$user['id-membre']]);

    $pseudo = htmlspecialchars($user['pseudo']);
    renderHtml(
        '✅ Compte activé !',
        "Félicitations <strong style=\"color:#00d1ff\">$pseudo</strong>&nbsp;!<br><br>"
      . 'Votre compte CardEvent est maintenant actif.<br>'
      . 'Vous pouvez vous connecter dans l\'application.',
        true
    );
}

// ── Helper : page HTML ────────────────────────────────────────
function renderHtml(string $title, string $message, bool $ok): void
{
    header('Content-Type: text/html; charset=UTF-8');
    $color = $ok ? '#00d1ff' : '#ff6b6b';
    $icon  = $ok ? '✅' : '❌';
    // Si succès, on tente de rouvrir l'app iOS via le custom URL scheme
    $redirectScript = $ok
        ? '<script>setTimeout(function(){ window.location = "cardevent://email-verified"; }, 800);</script>'
        : '';
    echo <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>CardEvent – Vérification du compte</title>
  $redirectScript
</head>
<body style="margin:0;padding:0;background:#0d0d0d;font-family:Arial,sans-serif;
             display:flex;justify-content:center;align-items:center;min-height:100vh">
  <div style="max-width:420px;text-align:center;padding:40px 24px">
    <div style="font-size:60px;margin-bottom:12px">♠</div>
    <h1 style="color:#00d1ff;font-size:26px;font-weight:900;margin:0 0 6px">CardEvent</h1>
    <h2 style="color:$color;font-size:22px;margin:28px 0 16px">$icon&nbsp;$title</h2>
    <p style="color:rgba(255,255,255,0.75);line-height:1.7;font-size:15px">$message</p>
  </div>
</body>
</html>
HTML;
}
