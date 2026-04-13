<?php
/**
 * forgot-password.php — Relance de l'application
 *
 * POST { action: "request_reset", email: "..." }
 *   -> verifie que l'e-mail existe dans la base
 *   -> envoie un e-mail avec un bouton "Ouvrir CardEvent" (deep link)
 *   -> repond toujours { success: true } (ne revele pas si l'e-mail existe)
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../serveur-smtp/send.php';

try {
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erreur base de donnees']);
    exit;
}

$body   = json_decode(file_get_contents('php://input'), true) ?? [];
$action = trim($body['action'] ?? '');

if ($action !== 'request_reset') {
    http_response_code(400);
    echo json_encode(['error' => 'Action inconnue']);
    exit;
}

handleRequestReset($pdo, $body);

function handleRequestReset(PDO $pdo, array $body): void
{
    $email = trim($body['email'] ?? '');

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['error' => 'Adresse e-mail invalide']);
        exit;
    }

    $stmt = $pdo->prepare("SELECT id FROM membres WHERE email = :email LIMIT 1");
    $stmt->execute([':email' => $email]);
    $exists = $stmt->fetch();

    // Toujours repondre succes — ne revele pas si l'e-mail existe
    if (!$exists) {
        echo json_encode(['success' => true]);
        exit;
    }

    $subject  = 'CardEvent \u2014 Acces a votre compte';
    $htmlBody = buildEmail();

    sendRealEmail($email, $subject, $htmlBody);

    echo json_encode(['success' => true]);
}

function buildEmail(): string
{
    return <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Acces CardEvent</title>
</head>
<body style="margin:0;padding:0;background:#0d0d0d;font-family:'Helvetica Neue',Helvetica,Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#0d0d0d;padding:40px 0;">
  <tr>
    <td align="center">
      <table width="480" cellpadding="0" cellspacing="0"
             style="background:#111;border-radius:16px;overflow:hidden;max-width:480px;width:100%;">

        <tr>
          <td style="background:#00d1ff11;padding:32px 32px 24px;text-align:center;border-bottom:1px solid #1a1a1a;">
            <div style="font-size:48px;margin-bottom:12px;">&#127137;</div>
            <h1 style="margin:0;color:#00d1ff;font-size:22px;font-weight:700;letter-spacing:1px;">CardEvent</h1>
          </td>
        </tr>

        <tr>
          <td style="padding:40px 32px;text-align:center;">
            <p style="color:#ccc;font-size:15px;margin:0 0 32px;line-height:1.6;">
              Appuyez sur le bouton ci-dessous pour acceder a votre compte dans l'application.
            </p>

            <a href="cardevent://open"
               style="display:inline-block;background:#00d1ff;color:#000;font-size:16px;
                      font-weight:700;text-decoration:none;padding:14px 40px;
                      border-radius:12px;letter-spacing:0.5px;">
              Ouvrir CardEvent
            </a>

            <p style="color:#555;font-size:12px;margin:28px 0 0;line-height:1.6;">
              Si le bouton ne fonctionne pas, ouvrez l'application manuellement.
            </p>
          </td>
        </tr>

        <tr>
          <td style="padding:16px 32px;text-align:center;border-top:1px solid #1a1a1a;">
            <p style="margin:0;color:#444;font-size:11px;">
              Si vous n'etes pas a l'origine de cette demande, ignorez cet e-mail.<br>
              &copy; CardEvent
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
}
