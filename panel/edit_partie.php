<?php
session_start();
include_once ('/panel/include/config.php');
error_reporting(0);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// unset ($_POST['submitinsmanu']);
// unset ($_POST['submit']);
// unset ($_POST['submitplb']);
// unset ($_POST['submitdesins']);
//$comp = intval($_GET['comp']); // get value
include_once ('include/config.php');
$ret = mysqli_query($con, "SELECT * FROM `partie` WHERE 1 "); // Changed table name from activite to partie
while ($row = mysqli_fetch_array($ret)) {
    $pointeur = $row["id-partie"]; // Changed column name
    $pointeur_ordre = 0;
   //echo $pointeur."-".$pointeur_ordre;
    $ret2 = mysqli_query($con, "SELECT * FROM `participation` WHERE (`id-partie` = '$pointeur' AND `option` LIKE 'Reservation') OR (`id-partie` = '$pointeur' AND `option` LIKE 'Option')
        OR (`id-partie` = '$pointeur' AND `option` LIKE 'Inscrit') OR (`id-partie` = '$pointeur' AND `option` LIKE 'Confirme') OR (`id-partie` = '$pointeur' AND `option` LIKE 'Elimine') ORDER BY `ordre` ASC");
    while ($row2 = mysqli_fetch_array($ret2)) {
        $id = $row2['id-participation'];
        $pointeur_ordre = $pointeur_ordre + 1;
    //    echo"/".$pointeur_ordre."/";
        // $modif = mysqli_query($con, "UPDATE `participation` SET `ordre` = '$pointeur_ordre' WHERE `id-participation` = '$id'");
    }
    ;
}
;
// echo "Reorg Ok";
require 'vendor/autoload.php';
if (!empty($_GET['uid']) && !empty($_SERVER['HTTP_REFERER'])) {
    $cardeventUid = intval($_GET['uid']);
    $referer = (string)$_SERVER['HTTP_REFERER'];
    if ($cardeventUid > 0 && strpos($referer, '/panel/cardevent.php') !== false) {
        header('Location: /panel/cardevent.php?uid=' . $cardeventUid . '&open_registration=1');
        exit;
    }
}
if (strlen($_SESSION['id'] == 0)) {
    // if (1==2) {
    header('location:logout.php');
    exit;
} else {
    $id = intval($_GET['uid']); // get value
    if (isset($_POST['submit'])) {
        // Load current values from DB
        $cur = mysqli_query($con, "SELECT * FROM `partie` WHERE `id-partie` = '$id'"); // Changed table name
        $currow = mysqli_fetch_assoc($cur);

        // Helper: take POST value if provided and not empty, otherwise fallback to current DB value
        $get = function($name, $fallback) {
            if (isset($_POST[$name]) && $_POST[$name] !== '') return $_POST[$name];
            return $fallback;
        };

        // Text fields (escape)
        $titre_partie = mysqli_real_escape_string($con, $get('titre-partie', $currow['titre-partie'])); // Changed field name
        $date_depart = mysqli_real_escape_string($con, $get('date_depart', $currow['date_depart']));
        $heure_depart = mysqli_real_escape_string($con, $get('heure_depart', $currow['heure_depart']));
        $ville = mysqli_real_escape_string($con, $get('ville', $currow['ville']));

        // Numeric / integer fields
        $places = is_numeric($get('places', $currow['places'])) ? intval($get('places', $currow['places'])) : $currow['places'];
        $nb_tables = is_numeric($get('nb-tables', $currow['nb-tables'])) ? intval($get('nb-tables', $currow['nb-tables'])) : $currow['nb-tables'];

        $rake = is_numeric($get('rake', $currow['rake'])) ? $get('rake', $currow['rake']) : $currow['rake'];
        $buyin = is_numeric($get('buyin', $currow['buyin'])) ? $get('buyin', $currow['buyin']) : $currow['buyin'];
        $bounty = is_numeric($get('bounty', $currow['bounty'])) ? $get('bounty', $currow['bounty']) : $currow['bounty'];
        $recave = is_numeric($get('recave', $currow['recave'])) ? $get('recave', $currow['recave']) : $currow['recave'];
        $recave_montant = is_numeric($get('recave_montant', $currow['recave_montant'])) ? $get('recave_montant', $currow['recave_montant']) : $currow['recave_montant'];
        $recave_jetons = is_numeric($get('recave_jetons', $currow['recave_jetons'])) ? $get('recave_jetons', $currow['recave_jetons']) : $currow['recave_jetons'];
        $addon = mysqli_real_escape_string($con, $get('addon', $currow['addon']));
        $ante = mysqli_real_escape_string($con, $get('ante', $currow['ante']));
        $jetons = is_numeric($get('jetons', $currow['jetons'])) ? $get('jetons', $currow['jetons']) : $currow['jetons'];
        $bonus = is_numeric($get('bonus', $currow['bonus'])) ? $get('bonus', $currow['bonus']) : $currow['bonus'];
        $lng = mysqli_real_escape_string($con, $get('lng', $currow['lng']));
        $lat = mysqli_real_escape_string($con, $get('lat', $currow['lat']));

        $idmembre = isset($_POST['id-membre']) && $_POST['id-membre'] !== '' ? intval($_POST['id-membre']) : (isset($currow['id-membre']) ? $currow['id-membre'] : null);
        $commentaire = isset($_POST['commentaire']) ? mysqli_real_escape_string($con, $_POST['commentaire']) : $currow['commentaire'];
        $challenge = isset($_POST['challenge']) && $_POST['challenge'] !== '' ? intval($_POST['challenge']) : (isset($currow['id_challenge']) ? intval($currow['id_challenge']) : null);
        $structure = isset($_POST['structure']) && $_POST['structure'] !== '' ? intval($_POST['structure']) : (isset($currow['id_structure']) ? $currow['id_structure'] : null);

        $idmembresession = $_SESSION['id'];

        // Only allow updates if the current session user is the original organizer (from DB) or an admin (id 265).
        if (isset($currow['id-membre']) && ($idmembresession == $currow['id-membre'] || $idmembresession == 265)) {
            $sql = "UPDATE `partie` SET 
                `titre-partie` = '$titre_partie',
                `date_depart` = '$date_depart',
                `heure_depart` = '$heure_depart',
                `ville` = '$ville',
                `places` = '$places',
                `nb-tables` = '$nb_tables',
                `id_challenge` = " . (is_null($challenge) ? 'NULL' : "'$challenge'") . ",
                `id_structure` = " . (is_null($structure) ? 'NULL' : "'$structure'") . ",
                `buyin` = '$buyin',
                `rake` = '$rake',
                `bounty` = '$bounty',
                `jetons` = '$jetons',
                `recave` = '$recave',
                `recave_montant` = '$recave_montant',
                `recave_jetons` = '$recave_jetons',
                `addon` = '$addon',
                `ante` = '$ante',
                `bonus` = '$bonus',
                `lng` = '$lng',
                `lat` = '$lat'
                WHERE `id-partie` = '$id'"; // Changed column name

            $msg = mysqli_query($con, $sql);
            if ($msg) {
                // --- Mise à jour du nom du groupe de chat si nécessaire ---
                $months = ["", "Janvier", "Février", "Mars", "Avril", "Mai", "Juin", "Juillet", "Août", "Septembre", "Octobre", "Novembre", "Décembre"];
                
                // Calcul de l'ancien nom
                $d_old = strtotime($currow['date_depart']);
                $formatted_old_date = date('j', $d_old) . ' ' . $months[intval(date('n', $d_old))];
                $res_old_org = mysqli_query($con, "SELECT pseudo FROM membres WHERE `id-membre` = '" . $currow['id-membre'] . "'");
                $row_old_org = mysqli_fetch_assoc($res_old_org);
                $old_org_name = $row_old_org ? $row_old_org['pseudo'] : "Organisateur";
                $old_group_name = $formatted_old_date . " " . $old_org_name;
                
                // Calcul du nouveau nom
                $d_new = strtotime($date_depart);
                $formatted_new_date = date('j', $d_new) . ' ' . $months[intval(date('n', $d_new))];
                $res_new_org = mysqli_query($con, "SELECT pseudo FROM membres WHERE `id-membre` = '$idmembre'");
                $row_new_org = mysqli_fetch_assoc($res_new_org);
                $new_org_name = $row_new_org ? $row_new_org['pseudo'] : "Organisateur";
                $new_group_name = $formatted_new_date . " " . $new_org_name;
                
                if ($old_group_name !== $new_group_name) {
                    $stmt_upd_grp = mysqli_prepare($con, "UPDATE chat_groups SET name = ? WHERE name = ?");
                    mysqli_stmt_bind_param($stmt_upd_grp, "ss", $new_group_name, $old_group_name);
                    mysqli_stmt_execute($stmt_upd_grp);
                    mysqli_stmt_close($stmt_upd_grp);
                }
                // --- Fin mise à jour groupe ---

                echo '<script>window.location.replace("/panel/edit-partie.php?uid=' . $id . '");</script>'; // Changed redirect page
            } else {
                echo '<script>alert("Erreur lors de la mise à jour: ' . mysqli_real_escape_string($con,mysqli_error($con)) . '");</script>';
            }
        } else {
            // Not authorized to edit: show a message for clarity
            echo '<div class="alert alert-warning" style="margin:10px">Vous n\'êtes pas autorisé à modifier cette partie.</div>'; // Changed message
        }
    }
    ;
    if (isset($_POST['submitinsmanu'])) {
        $lois = $_SESSION['id'];
        $activi = $id;
        $activi = $_POST['activi']; // get value
        echo $lois."-".$activi;
        $sql0 = mysqli_query($con, "SELECT * FROM `participation` WHERE `id-membre` = '$lois' AND `id-partie` = '$activi' "); // Changed table name
        // Return the number of rows in result set
        $rowcount = mysqli_num_rows($sql0);
        // if ($rowcount == '0') {
        if (1) {
            $ordre = "0";
            $sql1 = mysqli_query($con, "SELECT * FROM `participation` WHERE (`id-partie` = '$activi' AND `option` LIKE 'Reservation') OR (`id-partie` = '$activi' AND `option` LIKE 'Option') OR (`id-partie` = '$activi' AND `option` LIKE 'Inscrit') "); // Changed table name
            $ordre = mysqli_num_rows($sql1);
            $intordre = (int) $ordre;
            $intordre = $intordre + 1;
            $ordre = (string) $intordre;
            echo "a" . $ordre . $activi . "-" . $lois;
            $sql2 = mysqli_query($con, "INSERT INTO `participation` (`id-membre`, `id-partie`, `ordre`) VALUES ( '$lois', '$activi','$ordre' )"); // Changed table name
            // recherche email
            echo "b";
            $sql3 = mysqli_query($con, "SELECT * FROM `membres` WHERE `id-membre` =  $lois ");
            echo "c";
            while ($result = mysqli_fetch_array($sql3)) {
                $email = $result['email'];
                $num_membre = $result['id-membre'];
                $num_partie = $activi; // Changed variable name
                $reset = $result['CodeV'];
            }
            ;
            if (strlen($email == 0)) {
                $email = "admin@poker31.org";
                $num_membre = "265";
                $reset = "";
            }
            ;
            // debut mail
            // echo '<script language="JavaScript" type="text/javascript"> window.location.replace("/index.php"); </script>';
            $mail = new PHPMailer(true);
            try {
                //Server settings
                // $mail->SMTPDebug = SMTP::DEBUG_SERVER;                      //Enable verbose debug output
                $mail->SMTPDebug = 0; //Enable verbose debug output
                $mail->isSMTP(); //Send using SMTP
                $mail->Host = 'smtp.ionos.fr'; //Set the SMTP server to send through
                $mail->SMTPAuth = true; //Enable SMTP authentication
                $mail->Username = 'admin@poker31.org'; //SMTP username
                $mail->Password = 'Kookies7*p'; //SMTP password
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; //Enable implicit TLS encryption
                $mail->Port = 465; //TCP port to connect to; use 587 if you have set `SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS`

                //Recipients
                $mail->setFrom('admin@poker31.org', 'Admin@Poker31.Org');
                //   $mail->addAddress('wenger.franck@gmail.com', 'Franck.W');     //Add a recipient
                $mail->addAddress($email, 'Utilisateur-Poker31'); //Add a recipient
                //   $mail->addAddress('ellen@example.com');               //Name is optional
                $mail->addReplyTo('admin@poker31.org', 'Administrateur');
                //   $mail->addCC('cc@example.com');
                //   $mail->addBCC('bcc@example.com');

                //Attachments
                //   $mail->addAttachment('/var/tmp/file.tar.gz');         //Add attachments
                //   $mail->addAttachment('/tmp/image.jpg', 'new.jpg');    //Optional name
                //Content
                $mail->isHTML(true); //Set email format to HTML
                $mail->Subject = 'AR Inscription www.poker31.org';
                $mail->Body = '<p>Votre inscription est prise en compte</p><p>Votre ordre d inscription est : ' . $ordre . '</p><p> Reset mot de passe : <a href="http://poker31.org/reg/change-Password.php?Reset=' . $reset . '">"http://poker31.org/reg/change-Password.php?Reset=' . $reset . '"</a></p>' . '<p> Lien partie : <b><a href="http://poker31.org/panel/voir-partie.php?uid=' . $num_partie . '">"http://poker31.org/panel/voir-partie.php?uid=' . $num_partie . '"</a></p>'; // Changed link
                $mail->AltBody = 'This is the body in plain text for non-HTML mail clients';
                $mail->send();
                // echo 'Message has been sent';
            } catch (Exception $e) {
                // echo "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
            }
            ;
        }
        ;
        // echo "hello insmanu1";
        ?>
<script type="text/javascript">
window.location.replace("/panel/voir-partie.php?uid=<?php echo $num_partie ?>"); // Changed redirect page
</script> ; <?php
        //  header('location:/panel/liste-activite.php');
        //  exit;

        // $_SESSION['msg'] = "bingo !!";
    }
    ;
    // echo "hello insmanu2";
    if (isset($_POST['submit-ins'])) {
       
        $lois = $_SESSION['id'];
        $activi = $id;
        $sql0 = mysqli_query($con, "SELECT * FROM `participation` WHERE `id-membre` = '$lois' AND `id-partie` = '$activi' "); // Changed table name
        // Return the number of rows in result set
        $rowcount = mysqli_num_rows($sql0);
        // if ($rowcount == '0') {
        if (1) {
            // echo "hello insmanu4";
            $ordre = "0";
            $sql1 = mysqli_query($con, "SELECT * FROM `participation` WHERE (`id-partie` = '$activi' AND `option` LIKE 'Reservation') OR (`id-partie` = '$activi' AND `option` LIKE 'Option') OR (`id-partie` = '$activi' AND `option` LIKE 'Inscrit') "); // Changed table name
            $ordre = mysqli_num_rows($sql1);
            $intordre = (int) $ordre;
            $intordre = $intordre + 1;
            $ordre = (string) $intordre;
            $sql2 = mysqli_query($con, "INSERT INTO `participation` (`id-membre`, `id-partie`, `ordre`, `id-siege`, `id-table`) VALUES ( '$lois', '$activi','$ordre','0','0' )"); // Changed table name
            // recherche email
            $sql3 = mysqli_query($con, "SELECT * FROM `membres` WHERE `id-membre` =  $lois ");
            while ($result = mysqli_fetch_array($sql3)) {
                $email = $result['email'];
                $num_membre = $result['id-membre'];
                $num_partie = $activi; // Changed variable name
                $reset = $result['CodeV'];
            }
            ;
            if (strlen($email == 0)) {
                $email = "admin@poker31.org";
                $num_membre = "265";
                $reset = "";
            }
            ;
            ?>
<script type="text/javascript">
window.location.replace("/panel/voir-partie.php?uid=<?php echo $num_partie ?>"); // Changed redirect page
</script> ; <?php
            
            // debut mail
            echo '<script language="JavaScript" type="text/javascript"> window.location.replace("/index.php"); </script>';
            $mail = new PHPMailer(true);
            try {
                //Server settings
                $mail->SMTPDebug = SMTP::DEBUG_SERVER;                      //Enable verbose debug output
                // $mail->SMTPDebug = 0; //Enable verbose debug output
                $mail->isSMTP(); //Send using SMTP
                $mail->Host = 'smtp.ionos.fr'; //Set the SMTP server to send through
                $mail->SMTPAuth = true; //Enable SMTP authentication
                $mail->Username = 'admin@poker31.org'; //SMTP username
                $mail->Password = 'Kookies7*p'; //SMTP password
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; //Enable implicit TLS encryption
                $mail->Port = 465; //TCP port to connect to; use 587 if you have set `SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS`

                //Recipients
                $mail->setFrom('admin@poker31.org', 'Admin@Poker31.Org');
                //   $mail->addAddress('wenger.franck@gmail.com', 'Franck.W');     //Add a recipient
                $mail->addAddress($email, 'Utilisateur-Poker31'); //Add a recipient
                //   $mail->addAddress('ellen@example.com');               //Name is optional
                $mail->addReplyTo('admin@poker31.org', 'Administrateur');
                //   $mail->addCC('cc@example.com');
                //   $mail->addBCC('bcc@example.com');

                //Attachments
                //   $mail->addAttachment('/var/tmp/file.tar.gz');         //Add attachments
                //   $mail->addAttachment('/tmp/image.jpg', 'new.jpg');    //Optional name
                //Content
                $mail->isHTML(true); //Set email format to HTML
                $mail->Subject = 'AR Inscription www.poker31.org';
                $mail->Body = '<p>Votre inscription est prise en compte</p><p>Votre ordre d inscription est : ' . $ordre . '</p><p> Reset mot de passe : <a href="http://poker31.org/reg/change-Password.php?Reset=' . $reset . '">"http://poker31.org/reg/change-Password.php?Reset=' . $reset . '"</a></p>' . '<p> Lien partie : <b><a href="http://poker31.org/panel/voir-partie.php?uid=' . $num_partie . '">"http://poker31.org/panel/voir-partie.php?uid=' . $num_partie . '"</a></p>'; // Changed link
                $mail->AltBody = 'This is the body in plain text for non-HTML mail clients';
                $mail->send();
                // echo 'Message has been sent';
            } catch (Exception $e) {
                // echo "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
            }
            ;
        }
        ;
        echo "hello ins";
        //  echo '<script language="JavaScript" type="text/javascript"> window.location.replace("/panel/liste-activite.php"); </script>';
        ?>
<script type="text/javascript">
window.location.replace("/panel/voir-partie.php?uid=<?php echo $num_partie ?>"); // Changed redirect page
</script> ; <?php


        $_SESSION['msg'] = "bingo !!";
    }
    ;
    if (($_POST['submitpl'])) {
        $particip = $_POST['submitpl'];
        header('location:voir-participation.php?id=' . $particip);
        // $sql2 = mysqli_query($con, "INSERT INTO `competences-individu` (`id-indiv`, `id-comp`) VALUES ('$id', '$compet')");
        // $_SESSION['msg'] = "Doctor Specialization added successfully !!";
    }
    if (($_POST['submitplb'])) {
        $sql = mysqli_query($con, "UPDATE `participation` SET `id-membre`='$id_membre',`id-membre-vainqueur`='$id_membre_vainqueur',`id-partie`='$id_partie',`id-siege`='$id_siege',`id-table`='$id_table',`id-challenge`='$id_challenge',`option`='$option',`ordre`='$ordre',`valide`='$valide',`commentaire`='$commentaire',`classement`='$classement',`points`='$gain',`ds`= CURRENT_TIMESTAMP,`ip-ins`='1',`ip-mod`='2',`ip-sup`='3' WHERE `participation`.`id-participation` = '$id'"); // Changed column name
    }
    if (($_POST['xxx'])) {
        $lois = $_SESSION['id'];
        $activi = $id;
        $sql2 = mysqli_query($con, "INSERT INTO `participation` (`id-membre`, `id-membre-vainqueur`, `id-partie`, `id-siege`, `id-table`, `id-challenge`, `option`, `ordre`, `valide`, `commentaire`, `classement`, `points`, `gain`, `ds`, `ip-ins`, `ip-mod`, `ip-sup`, `bounty`) VALUES ( '$lois', '', '$activi', '', '', '', 'Reservation', '$ordre', 'Actif', NULL, '1', '0', '0', CURRENT_TIMESTAMP, '', '', '', '0')"); // Changed column name
    }
    if ($_POST['btn-info']) {
        ?>
<script type="text/javascript" language="javascript">
afficher('infos');
</script>;
<?php
    }
    if ($_POST['pause']) {
        $pau = $_POST['pause'];
        $_SESSION['pause' . $id] = $pau;
        // 
        ?>
<!-- <div class='place3-content'> <audio src="https://www.soundhelix.com/examples/mp3/SoundHelix-Song-1.mp3" autoplay loop
        controls></audio></div>
    --> <?php

        // echo '<script language="JavaScript" type="text/javascript"> window.location.replace("/panel/blindes.php?uid=64.php"); </script>';
        ?>
<script type="text/javascript">
window.location.replace("/panel/voir-blindes.php?uid=<?php echo $id ?>");
</script> ; <?php

        // $sql = mysqli_query($con, "UPDATE `participation` SET `id-membre`='$id_membre',`id-membre-vainqueur`='$id_membre_vainqueur',`id-partie`='$id_partie',`id-siege`='$id_siege',`id-table`='$id_table',`id-challenge`='$id_challenge',`option`='$option',`ordre`='$ordre',`valide`='$valide',`commentaire`='$commentaire',`classement`='$gain',`ds`= CURRENT_TIMESTAMP,`ip-ins`='1',`ip-mod`='2',`ip-sup`='3' WHERE `participation`.`id-participation` = '$id'"); // Changed column name
    }
    if (($_POST['submit2'])) {
        $compet = $_POST['compet'];
        echo $compet;
        $sql2 = mysqli_query($con, "INSERT INTO `competences-individu` (`id-indiv`, `id-comp`) VALUES ('$id', '$compet')");
        // $_SESSION['msg'] = "Doctor Specialization added successfully !!";
    }
    if (isset($_POST['submit-desins'])) {
//        unset ($_POST['submit-desins']);
        $lois = $_SESSION['id'];
        $activi = $id;
        echo $lois."-".$activi;
        
        if ($option == 'Annule') {
            echo "coucouc";
            $id_table = '';
            $id_siege = '';
        } else
            $sql0 = mysqli_query($con, "SELECT * FROM `participation` WHERE `id-membre` = '$lois' AND `id-partie` = '$activi' "); // Changed table name
        // Return the number of rows in result set
        $rowcount = mysqli_num_rows($sql0);
        // echo "-".$rowcount;
        if ($rowcount == '1') {
            $sql10 = mysqli_query($con, "SELECT * FROM `participation` WHERE `id-membre` = '$lois' AND `id-partie` = '$activi' "); // Changed table name
            $part = mysqli_fetch_array($sql10);
            $id_part = $part['id-participation'];
        //    echo "-".$id_part;
            
//            $sql2 = mysqli_query($con, "UPDATE `participation` SET `id-membre`='$lois',`position`='0',`id-table`='$id_table',`id-siege`='$id_siege',`id-partie`='$activi',`option`='Annule',`ds`= CURRENT_TIMESTAMP WHERE `participation`.`id-participation` = '$id_part'"); // Changed column name
//            $sql2 = mysqli_query($con, "UPDATE `participation` SET `id-membre`='$lois',`position`='0',`id-table`='1',`id-siege`='0',`option`='Annule',`ds`= CURRENT_TIMESTAMP WHERE `participation`.`id-participation` = '$id_part'");
            $sql2 = mysqli_query($con, "UPDATE `participation` SET `id-membre`='$lois',`position`='0',`id-table`='1',`id-siege`='0',`option`='Annule',`ds`= CURRENT_TIMESTAMP WHERE `participation`.`id-participation` = '919'");    
        }
        ;
        // echo "coucou";
        // echo '<script language="JavaScript" type="text/javascript"> window.location.replace("/panel/liste-activite.php"); </script>';
        ?>
<script type="text/javascript">
//window.location.replace("/panel/voir-activite.php?uid=<?php echo $num_activite ?>");
</script> ; <?php
        // $_SESSION['msg'] = "bingo !!";
        }
    ;
    

        if (isset($_POST['submit-desinsbis'])) {
//        unset ($_POST['submit-desins']);
        $lois = $_SESSION['id'];
        $activi = $id;
        echo $lois."-".$activi;
        
        if ($option == 'Annule') {
            echo "coucouc";
            $id_table = '';
            $id_siege = '';
        } else
            $sql0 = mysqli_query($con, "SELECT * FROM `participation` WHERE `id-membre` = '$lois' AND `id-partie` = '$activi' "); // Changed table name
        // Return the number of rows in result set
        $rowcount = mysqli_num_rows($sql0);
        // echo "-".$rowcount;
        if ($rowcount == '1') {
            $sql10 = mysqli_query($con, "SELECT * FROM `participation` WHERE `id-membre` = '$lois' AND `id-partie` = '$activi' "); // Changed table name
            $part = mysqli_fetch_array($sql10);
            $id_part = $part['id-participation'];
        //    echo "-".$id_part;
            
//            $sql2 = mysqli_query($con, "UPDATE `participation` SET `id-membre`='$lois',`position`='0',`id-table`='$id_table',`id-siege`='$id_siege',`id-partie`='$activi',`option`='Annule',`ds`= CURRENT_TIMESTAMP WHERE `participation`.`id-participation` = '$id_part'"); // Changed column name
//            $sql2 = mysqli_query($con, "UPDATE `participation` SET `id-membre`='$lois',`position`='0',`id-table`='1',`id-siege`='0',`option`='Annule',`ds`= CURRENT_TIMESTAMP WHERE `participation`.`id-participation` = '$id_part'");
            $sql2 = mysqli_query($con, "UPDATE `participation` SET `id-membre`='$lois',`position`='0',`id-table`='1',`id-siege`='0',`option`='Annule',`ds`= CURRENT_TIMESTAMP WHERE `participation`.`id-participation` = '919'");    
        }
        ;
        // echo "coucou";
        // echo '<script language="JavaScript" type="text/javascript"> window.location.replace("/panel/liste-activite.php"); </script>';
        ?>
<script type="text/javascript">
//window.location.replace("/panel/voir-activite.php?uid=<?php echo $num_activite ?>");
</script> ; <?php
        // $_SESSION['msg'] = "bingo !!";
    }
    ;
    ?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="fr">

<head>
    <script>
    (function(){
        try {
            var params = new URLSearchParams(window.location.search);
            var uid = params.get('uid') || '';
            var raw = sessionStorage.getItem('cardevent-reg-return');
            if (!uid || !raw) return;
            var data = JSON.parse(raw);
            if (!data || String(data.uid || '') !== String(uid)) return;
            if (Math.abs(Date.now() - Number(data.ts || 0)) >= 60000) return;
            window.location.replace('/panel/cardevent.php?uid=' + encodeURIComponent(uid) + '&open_registration=1');
        } catch (e) {}
    })();
    </script>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-16" />
    <!-- <meta http-equiv="refresh" content="30"> -->
    <title>Admin | Edition Partie</title> <!-- Changed Title -->
    <link rel="icon" type="image/png" href="/panel/assets/images/toulouse.jfif">
    <link rel="shortcut icon" href="/panel/assets/images/toulouse.jfif">
    <!-- <link href="http://fonts.googleapis.com/css?family=Lato:300,400,400italic,600,700|Raleway:300,400,500,600,700|Crete+Round:400italic" rel="stylesheet" type="text/css" /> -->
    <link rel="stylesheet" href="vendor/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="vendor/fontawesome/css/font-awesome.min.css">
    <link rel="stylesheet" href="vendor/themify-icons/themify-icons.min.css">
    <!-- <link href="vendor/animate.css/animate.min.css" rel="stylesheet" media="screen"> -->
    <link href="vendor/perfect-scrollbar/perfect-scrollbar.min.css" rel="stylesheet" media="screen">
    <link href="vendor/switchery/switchery.min.css" rel="stylesheet" media="screen">
    <link href="vendor/bootstrap-touchspin/jquery.bootstrap-touchspin.min.css" rel="stylesheet" media="screen">
    <link href="vendor/select2/select2.min.css" rel="stylesheet" media="screen">
    <link href="vendor/bootstrap-datepicker/bootstrap-datepicker3.standalone.min.css" rel="stylesheet" media="screen">
    <!-- <link href="https://cdn.jsdelivr.net/npm/simple-datatables@latest/dist/style.css" rel="stylesheet" /> -->
    <link href="vendor/bootstrap-timepicker/bootstrap-timepicker.min.css" rel="stylesheet" media="screen">
    <link rel="stylesheet" href="assets/css/styles.css">
    <link rel="stylesheet" href="assets/css/plugins.css">
    <link rel="stylesheet" href="assets/css/themes/theme-1.css" id="skin_color" />
    <script src="https://code.jquery.com/jquery-3.7.0.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <link href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css" rel="stylesheet" />
    <script src="https://cdnjs.cloudflare.com/ajax/libs/luxon/2.3.1/luxon.min.js"></script>
    <script type="text/javascript">
    $(document).ready(function() {
        $('#example').DataTable({
            order: [
                [0, 'asc']
            ],
            pageLength: 8,
            language: {
                url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/fr-FR.json'
            }
        });
    });
    </script>
    <script type="text/javascript">
    $(document).ready(function() {
        $('#example2').DataTable({
            pageLength: 8,
            language: {
                url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/fr-FR.json'
            }
        });
    });
    </script>
    <script type="text/javascript">
    $(document).ready(function() {
        $('#example3').DataTable({
            pageLength: 8,
            language: {
                url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/fr-FR.json'
            }
        });
    });
    </script>
    <link rel="stylesheet" href="css/mes-styles.css">
    <link rel="stylesheet" href="css/les-styles.css">

    <script>
    try{ responsiveVoice.setDefaultVoice("French Female"); }catch(e){ console.warn('responsiveVoice not loaded yet'); }
    </script>
    <!-- <script>responsiveVoice.speak("menu activite")</script> -->
    <style>
    /* body{ */
    /* background-image: url('/assets/images/table.jpg') !important;
                                                                                                                                } */
    /* img { width: 100%} */
    .square-box {
        position: absolute;
        width: 87%;
        height: 62%;
        overflow: hidden;
        /* background: #000;  */
        background-size: 100% 100%;
        background-image: url('/panel/images/table-banks.jpg');
        background-repeat: no-repeat;
        opacity: 1;
        left: 0;
        right: 0;
        top: -100px;
        bottom: 0;
        border-radius: 50%;
        /* border: 1px solid white; */
        /* Prevent the decorative overlay from intercepting mouse/touch events so buttons below can be clicked */
        pointer-events: none;
        z-index: 0;

    }

    .info1 {
        position: absolute;
        width: 90%;
        height: 50%;
        overflow: hidden;
        background: #6495ED;
        opacity: 0.75;
        left: 0;
        right: 0;
        top: -100px;
        bottom: 0;
        margin: auto;

    }

    .info2 {
        position: absolute;
        width: 90%;
        height: 50%;
        overflow: hidden;
        background: #6495ED;
        opacity: 0.75;
        left: 0;
        right: 0;
        top: -100px;
        bottom: 0;
        margin: auto;

    }

    .info1-content {
        position: absolute;
        top: 77%;
        left: 10%;
        color: blue;
        width: 100%;
        height: 100%;
        font-size: 2vw;

    }

    .info2-content {
        position: absolute;
        top: 82%;
        left: 10%;
        color: green;
        width: 100%;
        height: 100%;
        font-size: 2vw;

    }

    .info3-content {
        position: absolute;
        top: 87%;
        left: 10%;
        color: black;
        width: 100%;
        height: 100%;
        font-size: 2vw;

    }

    .info4-content {
        position: absolute;
        top: 92%;
        left: 10%;
        color: red;
        width: 100%;
        height: 100%;
        font-size: 2vw;

    }

    .info5-content {
        position: absolute;
        top: 97%;
        left: 10%;
        color: grey;
        width: 100%;
        height: 100%;
        font-size: 2vw;

    }

    .info6-content {
        position: absolute;
        top: 82%;
        left: 22%;
        color: red;
        width: 100%;
        height: 100%;
        font-size: 2vw;

    }

    .info7-content {
        position: absolute;
        top: 88%;
        left: 34%;
        color: green;
        width: 100%;
        height: 100%;
        font-size: 2vw;

    }

    .info8-content {
        position: absolute;
        top: 94%;
        left: 5%;
        color: green;
        width: 90%;
        height: 100%;
        font-size: 1.5vw;

    }

    .square-box2 {
        position: absolute;
        width: 50%;
        height: 20%;
        overflow: hidden;
        background: red;
        opacity: 0.25;
        left: 0;
        right: 0;
        top: -130px;
        bottom: 0;
        margin: auto;
        /* border-radius: 200px;
                                                                                                                                    border: 2px solid white; */
    }

    .titi {
        display: flex;
        align-items: center;
        justify-content: center;
        text-align: center;
    }

    .square-box:before {
        content: "";
        display: block;
        padding-top: 100%;
    }

    .square-content {
        position: absolute;
        top: 43%;
        left: 28%;
        color: white;
        width: 100%;
        height: 100%;
        font-size: 2.25vw;

    }

    .square2-content {
        position: absolute;
        top: 53%;
        left: 28%;
        color: white;
        width: 100%;
        height: 100%;
        font-size: 2.25vw;

    }

    .place-content {
        position: relative;
        top: 33px;
        left: -60px;
        color: white;
        width: 100%;
        height: 100%;
        font-size: 2.25vw;

    }

    .place2-content {
        position: relative;
        top: 33px;
        left: 80px;
        color: white;
        width: 100%;
        height: 100%;
        font-size: 2.25vw;

    }

    .place3-content {
        position: absolute;
        top: 120px;
        left: 140px;
        color: white;
        width: 80%;
        height: 100%;
        font-size: 1.5vw;

    }

    .square-content div {
        display: table;
        width: 100%;
        height: 100%;
    }

    .square-content span {
        display: table-cell;
        text-align: center;
        vertical-align: middle;
        color: white
    }

    .players {
        position: relative;
        top: -10px;
        width: 100%;
        height: 100%;
        z-index: 100;

    }

    .players .player {
        position: absolute;
    }

    .players .player.player-1 {
        top: 11%;

        left: 50%;
        -webkit-transform: translatex(-50%) translatey(-50%);
        transform: translatex(-50%) translatey(-50%);
    }

    .players .player.player-1p {
        top: 20%;
        left: 49%;
        color: white;
        -webkit-transform: translatex(-50%) translatey(-50%);
        transform: translatex(-50%) translatey(-50%);
    }

    .players .player.player-2 {
        top: 14%;

        left: 73%;
        -webkit-transform: translatex(-50%) translatey(-50%);
        transform: translatex(-50%) translatey(-50%);
    }

    .players .player.player-3 {
        top: 29%;
        left: 94%;
        -webkit-transform: translatex(-50%) translatey(-50%);
        transform: translatex(-50%) translatey(-50%);
    }

    .players .player.player-4 {
        top: 55%;
        left: 94%;
        -webkit-transform: translatex(-50%) translatey(-50%);
        transform: translatex(-50%) translatey(-50%);
    }

    .players .player.player-5 {
        top: 71%;
        left: 73%;
        -webkit-transform: translatex(-50%) translatey(-50%);
        transform: translatex(-50%) translatey(-50%);
    }

    .players .player.player-6 {
        top: 73.5%;
        left: 50%;
        -webkit-transform: translatex(-50%) translatey(-50%);
        transform: translatex(-50%) translatey(-50%);
    }

    .players .player.player-7 {
        top: 71%;
        left: 26%;
        -webkit-transform: translatex(-50%) translatey(-50%);
        transform: translatex(-50%) translatey(-50%);
    }

    .players .player.player-8 {
        top: 55%;
        left: 6%;
        -webkit-transform: translatex(-50%) translatey(-50%);
        transform: translatex(-50%) translatey(-50%);
    }

    .players .player.player-9 {
        top: 29%;
        left: 6%;
        -webkit-transform: translatex(-50%) translatey(-50%);
        transform: translatex(-50%) translatey(-50%);
    }

    .players .player.player-10 {
        top: 14%;
        left: 26%;
        -webkit-transform: translatex(-50%) translatey(-50%);
        transform: translatex(-50%) translatey(-50%);
    }

    .players .player .avatar {
        width: 14vw;
        height: 8vw;
        background-color: lightcoral;

        border-radius: 100%;
        position: relative;
        top: 5px;
        z-index: 1;
    }

    #main {
        position: absolute;
        width: 85%;
        height: 100%;
        overflow: none;
        left: 0;
        right: 0;
        top: 0;
        bottom: 0;
        margin: auto;


    }

    .p1p {



        display: flex;
        align-items: center;
        justify-content: center;
        text-align: center;
        color: #666;
        font-weight: bold;
        font-size: 17px;
    }

    .p1 {
        display: flex;
        align-items: center;
        justify-content: center;
        text-align: center;
        color: #666;
        font-weight: bold;
        font-size: 17px;


    }

    .p2 {
        display: flex;
        align-items: center;
        justify-content: center;
        text-align: center;
        color: #fff;
        font-weight: bold;
        font-size: 17px;
        opacity: 0.95;
    }

    .p3 {
        display: flex;
        align-items: center;
        justify-content: center;
        text-align: center;
        color: #fff;
        font-weight: bold;
        font-size: 2.5vw;
        opacity: 0.95;

    }

    .p4 {

        display: flex;
        align-items: center;
        justify-content: center;
        text-align: center;
        color: #fff;
        font-weight: bold;
        font-size: 2.5vw;
        opacity: 0.9;
    }

    .p5 {
        display: flex;
        align-items: center;
        justify-content: center;
        text-align: center;
        color: #fff;
        font-weight: bold;
        font-size: 17px;
    }

    .p6 {
        display: flex;
        align-items: center;
        justify-content: center;
        text-align: center;
        color: #fff;
        font-weight: bold;
        font-size: 2.5vw;
        opacity: 0.90;
    }

    .p7 {
        display: flex;
        align-items: center;
        justify-content: center;
        text-align: center;
        color: #fff;
        font-weight: bold;
        font-size: 2.5vw;
        opacity: 0.95;
    }

    .p8 {
        display: flex;
        align-items: center;
        justify-content: center;
        text-align: center;
        color: #fff;
        font-weight: bold;
        font-size: 17px;
    }

    .p9 {
        display: flex;
        align-items: center;
        justify-content: center;
        text-align: center;
        color: #fff;
        font-weight: bold;
        font-size: 17px;
    }
</style>
</html>