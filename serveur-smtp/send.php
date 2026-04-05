<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../PHPMailer/PHPMailer/src/Exception.php';
require_once __DIR__ . '/../PHPMailer/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../PHPMailer/PHPMailer/src/SMTP.php';

/**
 * Fonction pour envoyer un email via un vrai serveur SMTP
 */
function sendRealEmail($to, $subject, $body, $isHtml = true) {
    $config = require __DIR__ . '/config.php';
    $mail = new PHPMailer(true);

    try {
        // Paramètres du serveur
        $mail->isSMTP();
        $mail->Host       = $config['host'];
        $mail->SMTPAuth   = $config['auth'];
        $mail->Username   = $config['username'];
        $mail->Password   = $config['password'];
        $mail->SMTPSecure = $config['secure'];
        $mail->Port       = $config['port'];
        $mail->CharSet    = 'UTF-8';

        // Destinataires
        $mail->setFrom($config['from_email'], $config['from_name']);
        $mail->addAddress($to);

        // Contenu
        $mail->isHTML($isHtml);
        $mail->Subject = $subject;
        $mail->Body    = $body;

        if (!$isHtml) {
            $mail->AltBody = strip_tags($body);
        }

        $mail->send();
        return ["success" => true, "message" => "Email envoyé avec succès"];
    } catch (Exception $e) {
        return ["success" => false, "message" => "Erreur SMTP : {$mail->ErrorInfo}"];
    }
}

// Exemple d'utilisation si appelé directement
if (basename(__FILE__) == basename($_SERVER['SCRIPT_FILENAME'])) {
    $res = sendRealEmail('franck.wenger@wanadoo.fr', 'Test Serveur Réel', 'Ceci est un test depuis le nouveau serveur SMTP.');
    echo $res['message'];
}
