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

require_once __DIR__ . '/../serveur-smtp/send.php';

try {
    $pdo = new PDO('mysql:host=localhost;dbname=dbs9616600;charset=utf8mb4', 'root', 'Kookies7*', [
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
    $email  = trim($body['email']  ?? '');
    $pseudo = trim($body['pseudo'] ?? '');

    if (empty($email) && empty($pseudo)) {
        http_response_code(400);
        echo json_encode(['error' => 'Saisissez votre e-mail ou votre pseudo']);
        exit;
    }

    // Recherche par e-mail OU pseudo
    if (!empty($email)) {
        $stmt = $pdo->prepare("SELECT pseudo, password_ext, email FROM membres WHERE email = :val LIMIT 1");
    } else {
        $stmt = $pdo->prepare("SELECT pseudo, password_ext, email FROM membres WHERE pseudo = :val LIMIT 1");
    }
    $stmt->execute([':val' => !empty($email) ? $email : $pseudo]);
    $member = $stmt->fetch();

    // Toujours repondre succes — ne revele pas si l'e-mail existe
    if (!$member) {
        echo json_encode(['success' => true]);
        exit;
    }

    $pseudo_val   = htmlspecialchars($member['pseudo'],       ENT_QUOTES, 'UTF-8');
    $password_val = htmlspecialchars($member['password_ext'], ENT_QUOTES, 'UTF-8');
    $dest_email   = $member['email'];

    $subject  = 'CardEvent \u2014 Acces a votre compte';
    $htmlBody = buildEmail($pseudo_val, $password_val);

    sendRealEmail($dest_email, $subject, $htmlBody);

    echo json_encode(['success' => true]);
}

function buildEmail(string $pseudo, string $password): string
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
          <td style="padding:32px;">
            <p style="color:#ccc;font-size:15px;margin:0 0 24px;">
              Voici vos informations de connexion pour acceder a l'application.
            </p>

            <table width="100%" cellpadding="0" cellspacing="0"
                   style="background:#1a1a1a;border-radius:12px;overflow:hidden;margin-bottom:28px;">
              <tr>
                <td style="padding:16px 20px;border-bottom:1px solid #222;">
                  <div style="color:#666;font-size:11px;text-transform:uppercase;letter-spacing:1px;margin-bottom:4px;">Pseudo</div>
                  <div style="color:#fff;font-size:17px;font-weight:600;">{$pseudo}</div>
                </td>
              </tr>
              <tr>
                <td style="padding:16px 20px;">
                  <div style="color:#666;font-size:11px;text-transform:uppercase;letter-spacing:1px;margin-bottom:4px;">Mot de passe</div>
                  <div style="color:#00d1ff;font-size:17px;font-weight:600;letter-spacing:1px;">{$password}</div>
                </td>
              </tr>
            </table>

            <table width="100%" cellpadding="0" cellspacing="0">
              <tr>
                <td align="center">
                  <a href="cardevent://open"
                     style="display:inline-block;background:#00d1ff;color:#000;font-size:16px;
                            font-weight:700;text-decoration:none;padding:14px 36px;
                            border-radius:12px;letter-spacing:0.5px;">
                    Ouvrir CardEvent
                  </a>
                </td>
              </tr>
            </table>

            <p style="color:#555;font-size:12px;margin:24px 0 0;text-align:center;line-height:1.6;">
              Si le bouton ne fonctionne pas, ouvrez l'application manuellement<br>
              et connectez-vous avec les identifiants ci-dessus.
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
