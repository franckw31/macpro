<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php';

$mail = new PHPMailer(true);

try {
    // Paramètres du serveur
    $mail->isSMTP();
    $mail->Host = 'smtp.free.fr';
    $mail->SMTPAuth = true;
    $mail->Username = 'contact.poker31@free.fr'; // Remplacez par votre email Free
    $mail->Password = 'Kookies7*fb'; // Remplacez par votre mot de passe
    $mail->SMTPSecure = 'ssl';
    $mail->Port = 465;

    // Destinataires
    $mail->setFrom('conact.poker31@free.fr', 'Votre Nom');
    $mail->addAddress('franck.wenger@wanadoo.fr', 'Nom Destinataire');

    // Contenu de l'email
    $mail->isHTML(true);
    $mail->Subject = 'Sujet de l\'email';
    $mail->Body    = 'Corps de l\'email en <b>HTML</b>';
    $mail->AltBody = 'Corps de l\'email en texte brut';

    $mail->send();
    echo 'Message envoyé !';
} catch (Exception $e) {
    echo "Erreur lors de l'envoi de l'email : {$mail->ErrorInfo}";
}
?>
