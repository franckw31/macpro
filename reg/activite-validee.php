<script src="jquery-3.3.1.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/limonte-sweetalert2/11.4.24/sweetalert2.all.js"></script>
<?php

session_start();
error_reporting(0);

// include('/panel/include/config.php');
$conx = mysqli_connect("db5011397709.hosting-data.io","dbu5472475","Kookies7*","dbs9616600");
$email = $_GET['email']; // get value
$num_membre = $_GET['membre']; // get value
$num_activite = $_GET['activite']; // get value
$reset = $_GET['reset']; // get value
$date_activite = $_GET['date']; // get value
$ville_activite = $_GET['ville']; // get value
$titre_activite = $_GET['titre']; // get value
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

//Load Composer's autoloader
require 'vendor/autoload.php';
//Create an instance; passing `true` enables exceptions
$mail = new PHPMailer(true);
try {
    //Server settings
    $mail->SMTPDebug = 0;                      //Enable verbose debug output
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
	  $mail->addAddress($email, 'Privé');     //Add a recipient
 //   $mail->addAddress('ellen@example.com');               //Name is optional
    $mail->addReplyTo('admin@poker31.org', 'Administrateur');
 //   $mail->addCC('cc@example.com');
 //   $mail->addBCC('bcc@example.com');

    //Attachments
 //   $mail->addAttachment('/var/tmp/file.tar.gz');         //Add attachments
 //   $mail->addAttachment('/tmp/image.jpg', 'new.jpg');    //Optional name

    //Content
    $mail->isHTML(true);                                  //Set email format to HTML
    $mail->Subject = 'Activité Validée';
    $mail->Body    = '<p> Date : ' . $date_activite . '</p>';
    $mail->Body    = '<p> Titre : ' . $titre_activite . '</p><p> Lieu : ' . $ville_activite . '</p><p> Date : ' . $date_activite . '</p><p> Reset mot de passe : <a href="http://poker31.org/reg/change-Password.php?Reset=' . $reset . '">"http://poker31.org/reg/change-Password.php?Reset=' . $reset . '"</a></p>'.'<p> Lien activité : <b><a href="http://poker31.org/panel/voir-activite.php?uid=' . $num_activite . '">"http://poker31.org/panel/voir-activite.php?uid=' . $num_activite . '"</a></p>';

    $mail->AltBody = 'This is the body in plain text for non-HTML mail clients';

    $mail->send();
    
    echo '-';
    ?>
    <script>
  Swal.fire({
    type: 'error',
    title: 'Merci',
    confirmButtonText: 'Continuer',
    text: 'Message envoyé',
    footer: '',
    showCloseButton: true
})
.then(function (result) {
    if (result.value) {
        window.location = "/index.php";
    }
})
    </script>
        
    <?php
    
} catch (Exception $e) {
    echo "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
}
// echo '<script type="text/javascript">sweetAlert("Message envoyé avec succés","Retour au site ...","success")</script>';
// echo '<script language="JavaScript" type="text/javascript">window.location.replace("/index.php");</script>';  
 
?>
<!-- <script language="JavaScript" type="text/javascript">window.location.replace("/index.php");</script> -->