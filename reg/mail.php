<?php
$id_membre = $_GET['email']; // get value
$num_membre = $_GET['membre']; // get value
$num_activite = $_GET['activite']; // get value
//Import PHPMailer classes into the global namespace
//These must be at the top of your script, not inside a function
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

//Load Composer's autoloader
require 'vendor/autoload.php';

//Create an instance; passing `true` enables exceptions
$mail = new PHPMailer(true);

try {
    //Server settings
    $mail->SMTPDebug = SMTP::DEBUG_SERVER;                      //Enable verbose debug output
    $mail->isSMTP();                                            //Send using SMTP
    $mail->Host       = 'smtp.ionos.fr';                     //Set the SMTP server to send through
    $mail->SMTPAuth   = true;                                   //Enable SMTP authentication
    $mail->Username   = 'admin@poker31.org';                     //SMTP username
    $mail->Password   = 'Kookies7*p';                               //SMTP password
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;            //Enable implicit TLS encryption
    $mail->Port       = 465;                                    //TCP port to connect to; use 587 if you have set `SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS`

    //Recipients
    $mail->setFrom('admin@poker31.org', 'Admin@Poker31.Org');
 //   $mail->addAddress('wenger.franck@gmail.com', 'Franck.W');     //Add a recipient
	  $mail->addAddress($id_membre, 'Privé');     //Add a recipient
 //   $mail->addAddress('ellen@example.com');               //Name is optional
    $mail->addReplyTo('admin@poker31.org', 'Administrateur');
 //   $mail->addCC('cc@example.com');
 //   $mail->addBCC('bcc@example.com');

    //Attachments
 //   $mail->addAttachment('/var/tmp/file.tar.gz');         //Add attachments
 //   $mail->addAttachment('/tmp/image.jpg', 'new.jpg');    //Optional name

    //Content
    $mail->isHTML(true);                                  //Set email format to HTML
    $mail->Subject = 'Nouvel Acces www.poker31.org';
    $mail->Body    = 'Mot de passe temporaire 123456 Cliquer IcI : http://www.poker31.org'.'<p> This is the Verifecation Link<b><a href="http://poker31.org/reg/change-Password.php?Reset=' . $num_membre.$num_activite . '">"http://poker31.org/reg/change-Password.php?Reset=' . $num_membre.$num_activite . '"</a></b></p>'.'<p> Lien activité<b><a href="http://poker31.org/panel/voir-activite.php?uid=' . $num_activite . '">"http://poker31.org/panel/voir-activite.php?uid=' . $num_activite . '"</a></b></p>.<p> This is the Verifecation Link<b><a href="http://poker31.org/panel/inscription-activite.php?quoi=' . $num_activite . '&qui=' .$num_membre.'">"http://poker31.org/panel/inscription-activite.php?quoi=' . $num_activite . '&qui=' .$num_membre. '"</a></b></p>';

    $mail->AltBody = 'This is the body in plain text for non-HTML mail clients';

    $mail->send();
    echo 'Message has been sent';
} catch (Exception $e) {
    echo "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
}