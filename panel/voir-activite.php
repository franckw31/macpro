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
$ret = mysqli_query($con, "SELECT * FROM `activite` WHERE 1 ");
while ($row = mysqli_fetch_array($ret)) {
    $pointeur = $row["id-activite"];
    $pointeur_ordre = 0;
   //echo $pointeur."-".$pointeur_ordre;
    $ret2 = mysqli_query($con, "SELECT * FROM `participation` WHERE (`id-activite` = '$pointeur' AND `option` LIKE 'Reservation') OR (`id-activite` = '$pointeur' AND `option` LIKE 'Option')
        OR (`id-activite` = '$pointeur' AND `option` LIKE 'Inscrit') OR (`id-activite` = '$pointeur' AND `option` LIKE 'Confirme') OR (`id-activite` = '$pointeur' AND `option` LIKE 'Elimine') ORDER BY `ordre` ASC");
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
        $cur = mysqli_query($con, "SELECT * FROM `activite` WHERE `id-activite` = '$id'");
        $currow = mysqli_fetch_assoc($cur);

        // Helper: take POST value if provided and not empty, otherwise fallback to current DB value
        $get = function($name, $fallback) {
            if (isset($_POST[$name]) && $_POST[$name] !== '') return $_POST[$name];
            return $fallback;
        };

        // Text fields (escape)
        $titre_activite = mysqli_real_escape_string($con, $get('titre-activite', $currow['titre-activite']));
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
            $sql = "UPDATE `activite` SET 
                `titre-activite` = '$titre_activite',
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
                WHERE `id-activite` = '$id'";

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

                echo '<script>window.location.replace("/panel/voir-activite.php?uid=' . $id . '");</script>';
            } else {
                echo '<script>alert("Erreur lors de la mise à jour: ' . mysqli_real_escape_string($con,mysqli_error($con)) . '");</script>';
            }
        } else {
            // Not authorized to edit: show a message for clarity
            echo '<div class="alert alert-warning" style="margin:10px">Vous n\'êtes pas autorisé à modifier cette activité.</div>';
        }
    }
    ;
    if (isset($_POST['submitinsmanu'])) {
        $lois = $_POST['lois'];
        //$activi = $id;
        $activi = $_SESSION['id'];
        $activi = $_POST['activi']; // get value
        echo $lois . "-" . $activi;
        $sql0 = mysqli_query($con, "SELECT * FROM `participation` WHERE `id-membre` = '$lois' AND `id-activite` = '$activi' ");
        // Return the number of rows in result set
        $rowcount = mysqli_num_rows($sql0);
        // if ($rowcount == '0') {
        if (1) {
            $ordre = "0";
            $sql1 = mysqli_query($con, "SELECT * FROM `participation` WHERE (`id-activite` = '$activi' AND `option` LIKE 'Reservation') OR (`id-activite` = '$activi' AND `option` LIKE 'Option') OR (`id-activite` = '$activi' AND `option` LIKE 'Inscrit') ");
            $ordre = mysqli_num_rows($sql1);
            $intordre = (int) $ordre;
            $intordre = $intordre + 1;
            $ordre = (string) $intordre;
            echo "a" . $ordre . $activi . "-" . $lois;
            $sql2 = mysqli_query($con, "INSERT INTO `participation` (`id-membre`, `id-activite`, `ordre`) VALUES ( '$lois', '$activi','$ordre' )");
            // recherche email
            echo "b";
            $sql3 = mysqli_query($con, "SELECT * FROM `membres` WHERE `id-membre` =  $lois ");
            echo "c";
            while ($result = mysqli_fetch_array($sql3)) {
                $email = $result['email'];
                $num_membre = $result['id-membre'];
                $num_activite = $activi;
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
                $mail->Body = '<p>Votre inscription est prise en compte</p><p>Votre ordre d inscription est : ' . $ordre . '</p><p> Reset mot de passe : <a href="http://poker31.org/reg/change-Password.php?Reset=' . $reset . '">"http://poker31.org/reg/change-Password.php?Reset=' . $reset . '"</a></p>' . '<p> Lien activité : <b><a href="http://poker31.org/panel/voir-activite.php?uid=' . $num_activite . '">"http://poker31.org/panel/voir-activite.php?uid=' . $num_activite . '"</a></p>';
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
window.location.replace("/panel/voir-activite.php?uid=<?php echo $num_activite ?>");
</script> ; <?php
        //  header('location:/panel/liste-activites.php');
        //  exit;

        // $_SESSION['msg'] = "bingo !!";
    }
    ;
    // echo "hello insmanu2";
    if (isset($_POST['submit-ins'])) {
       
        $lois = $_SESSION['id'];
        $activi = $id;
        $sql0 = mysqli_query($con, "SELECT * FROM `participation` WHERE `id-membre` = '$lois' AND `id-activite` = '$activi' ");
        // Return the number of rows in result set
        $rowcount = mysqli_num_rows($sql0);
        // if ($rowcount == '0') {
        if (1) {
            // echo "hello insmanu4";
            $ordre = "0";
            $sql1 = mysqli_query($con, "SELECT * FROM `participation` WHERE (`id-activite` = '$activi' AND `option` LIKE 'Reservation') OR (`id-activite` = '$activi' AND `option` LIKE 'Option') OR (`id-activite` = '$activi' AND `option` LIKE 'Inscrit') ");
            $ordre = mysqli_num_rows($sql1);
            $intordre = (int) $ordre;
            $intordre = $intordre + 1;
            $ordre = (string) $intordre;
            $sql2 = mysqli_query($con, "INSERT INTO `participation` (`id-membre`, `id-activite`, `ordre`, `id-siege`, `id-table`) VALUES ( '$lois', '$activi','$ordre','0','0' )");
            // recherche email
            $sql3 = mysqli_query($con, "SELECT * FROM `membres` WHERE `id-membre` =  $lois ");
            while ($result = mysqli_fetch_array($sql3)) {
                $email = $result['email'];
                $num_membre = $result['id-membre'];
                $num_activite = $activi;
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
window.location.replace("/panel/voir-activite.php?uid=<?php echo $num_activite ?>");
</script> ;
<?php            
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
                $mail->Body = '<p>Votre inscription est prise en compte</p><p>Votre ordre d inscription est : ' . $ordre . '</p><p> Reset mot de passe : <a href="http://poker31.org/reg/change-Password.php?Reset=' . $reset . '">"http://poker31.org/reg/change-Password.php?Reset=' . $reset . '"</a></p>' . '<p> Lien activité : <b><a href="http://poker31.org/panel/voir-activite.php?uid=' . $num_activite . '">"http://poker31.org/panel/voir-activite.php?uid=' . $num_activite . '"</a></p>';
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
        //  echo '<script language="JavaScript" type="text/javascript"> window.location.replace("/panel/liste-activites.php"); </script>';
        ?>
<script type="text/javascript">
window.location.replace("/panel/voir-activite.php?uid=<?php echo $num_activite ?>");
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
        $sql = mysqli_query($con, "UPDATE `participation` SET `id-membre`='$id_membre',`id-membre-vainqueur`='$id_membre_vainqueur',`id-activite`='$id_activite',`id-siege`='$id_siege',`id-table`='$id_table',`id-challenge`='$id_challenge',`option`='$option',`ordre`='$ordre',`valide`='$valide',`commentaire`='$commentaire',`classement`='$classement',`points`='$gain',`ds`= CURRENT_TIMESTAMP,`ip-ins`='1',`ip-mod`='2',`ip-sup`='3' WHERE `participation`.`id-participation` = '$id'");
    }
    if (($_POST['xxx'])) {
        $lois = $_SESSION['id'];
        $activi = $id;
        $sql2 = mysqli_query($con, "INSERT INTO `participation` (`id-membre`, `id-membre-vainqueur`, `id-activite`, `id-siege`, `id-table`, `id-challenge`, `option`, `ordre`, `valide`, `commentaire`, `classement`, `points`, `gain`, `ds`, `ip-ins`, `ip-mod`, `ip-sup`, `bounty`) VALUES ( '$lois', '', '$activi', '', '', '', 'Reservation', '$ordre', 'Actif', NULL, '1', '0', '0', CURRENT_TIMESTAMP, '', '', '', '0')");
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

        // $sql = mysqli_query($con, "UPDATE `participation` SET `id-membre`='$id_membre',`id-membre-vainqueur`='$id_membre_vainqueur',`id-activite`='$id_activite',`id-siege`='$id_siege',`id-table`='$id_table',`id-challenge`='$id_challenge',`option`='$option',`ordre`='$ordre',`valide`='$valide',`commentaire`='$commentaire',`classement`='$gain',`ds`= CURRENT_TIMESTAMP,`ip-ins`='1',`ip-mod`='2',`ip-sup`='3' WHERE `participation`.`id-participation` = '$id'");
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
            $sql0 = mysqli_query($con, "SELECT * FROM `participation` WHERE `id-membre` = '$lois' AND `id-activite` = '$activi' ");
        // Return the number of rows in result set
        $rowcount = mysqli_num_rows($sql0);
        // echo "-".$rowcount;
        if ($rowcount == '1') {
            $sql10 = mysqli_query($con, "SELECT * FROM `participation` WHERE `id-membre` = '$lois' AND `id-activite` = '$activi' ");
            $part = mysqli_fetch_array($sql10);
            $id_part = $part['id-participation'];
        //    echo "-".$id_part;
            
//            $sql2 = mysqli_query($con, "UPDATE `participation` SET `id-membre`='$lois',`position`='0',`id-table`='$id_table',`id-siege`='$id_siege',`id-activite`='$activi',`option`='Annule',`ds`= CURRENT_TIMESTAMP WHERE `participation`.`id-participation` = '$id_part'");
//            $sql2 = mysqli_query($con, "UPDATE `participation` SET `id-membre`='$lois',`position`='0',`id-table`='1',`id-siege`='0',`option`='Annule',`ds`= CURRENT_TIMESTAMP WHERE `participation`.`id-participation` = '$id_part'");
            $sql2 = mysqli_query($con, "UPDATE `participation` SET `id-membre`='$lois',`position`='0',`id-table`='1',`id-siege`='0',`option`='Annule',`ds`= CURRENT_TIMESTAMP WHERE `participation`.`id-participation` = '919'");    
        }
        ;
        // echo "coucou";
        // echo '<script language="JavaScript" type="text/javascript"> window.location.replace("/panel/liste-activite.php"); </script>';
        ?>
<script type="text/javascript">
//window.location.replace("/panel/voir-activite.php?uid=<?php echo $num_activite ?>");
</script> ;
<?php
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
            $sql0 = mysqli_query($con, "SELECT * FROM `participation` WHERE `id-membre` = '$lois' AND `id-activite` = '$activi' ");
        // Return the number of rows in result set
        $rowcount = mysqli_num_rows($sql0);
        // echo "-".$rowcount;
        if ($rowcount == '1') {
            $sql10 = mysqli_query($con, "SELECT * FROM `participation` WHERE `id-membre` = '$lois' AND `id-activite` = '$activi' ");
            $part = mysqli_fetch_array($sql10);
            $id_part = $part['id-participation'];
        //    echo "-".$id_part;
            
//            $sql2 = mysqli_query($con, "UPDATE `participation` SET `id-membre`='$lois',`position`='0',`id-table`='$id_table',`id-siege`='$id_siege',`id-activite`='$activi',`option`='Annule',`ds`= CURRENT_TIMESTAMP WHERE `participation`.`id-participation` = '$id_part'");
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
    <title>Admin | Edition Membre</title>
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

    .p10 {
        display: flex;
        align-items: center;
        justify-content: center;
        text-align: center;
        color: #fff;
        font-weight: bold;
        font-size: 17px;
    }
    </style>

    <script type="text/javascript">
    function valid() {
        if (document.adddoc.npass.value != document.adddoc.cfpass.value) {
            alert("Password and Confirm Password Field do not match  !!");
            document.adddoc.cfpass.focus();
            return false;
        }
        return true;
    }
    </script>
    <script>
    function checkemailAvailability() {
        $("#loaderIcon").show();
        jQuery.ajax({
            url: "check_availability.php",
            data: 'emailid=' + $("#docemail").val(),
            type: "POST",
            success: function(data) {
                $("#email-availability-status").html(data);
                $("#loaderIcon").hide();
            },
            error: function() {}
        });
    }
    </script>
    <script>
    // var audio = new Audio("plus1_071016_Alex.WAV");
    // var audio = new Audio("http://glpjt.s3.amazonaws.com/so/av/a12.mp3");                 
    var audio = new Audio("https://s3.amazonaws.com/audio-experiments/examples/elon_mono.wav");

    function playAudio() {
        audio.play();
    }

    function pauseAudio() {
        audio.pause();
    }

    function cancelAudio() {
        audio.pause();
        audio.currentTime = 0;
    }
    //  playAudio();
    </script>
</head>

<body>
    
    <div id="app">
        <?php include ('include/sidebar.php'); ?>
        <div class="app-content">
            <?php include ('include/header.php'); ?>
            <!-- end: TOP NAVBAR -->
            <div class="main-content">
                <div class="wrap-content container" id="container">
                    <!-- start: PAGE TITLE -->
                    <section id="page-title">
                    </section>
                    <!-- end: PAGE TITLE -->
                    <!-- start: BASIC EXAMPLE -->
                    <div id="conteneur">
                        <div id="contenu">
                            <div id="auCentre">
                                <?php
                                    $id = intval($_GET['uid']);
                                    $reqnbt = mysqli_query($con, "SELECT * FROM `activite` WHERE `id-activite` = '$id' ");
                                    $res = mysqli_fetch_array($reqnbt);
                                    if (!$res) {
                                        echo "<div class='alert alert-danger' style='margin:20px;'>Activité introuvable pour l'identifiant <b>".htmlspecialchars($id)."</b>.</div>";
                                        exit;
                                    }
                                    $nbt = $res["nb-tables"];
                                    /* echo '--------'.$nbt.'---------'; */
                                    if ($nbt == '1') { ?>
                                <div id="bMenu">
                                    <a href="#" id="infos" class="btnnav" onmouseover="afficher1('infos')" onclick="afficher1('infos'); return false;">Infos</a> 
                                    <a href="#" id="inscrits" class="btnnav" onmouseover="afficher1('inscrits')" onclick="afficher1('inscrits'); return false;">Joueurs</a> 
                                    <a href="#" id="t1" class="btnnav" onmouseover="afficher1('t1')" onclick="afficher1('t1'); return false;">Table 1</a> 
                                    <!-- <a href="#" id="t2" class="btnnav" onmouseover="afficher2('t2')">Table 2</a> -->
                                    <!-- <a href="#" id="t3" class="btnnav" onmouseover="afficher2('t3')">Table 3</a> -->
                                    <!-- <a href="#" id="t4" class="btnnav" onmouseover="afficher2('t4')">Table 4</a> -->
                                    <a href="voir-blindes.php?uid=<?php echo $id; ?>" id="blindes" class="btnnav"
                                        onclick="window.location=this.href">Timer</a>
                                    <a href="sieges.php?ac=<?php echo $id; ?>&sou=/panel/voir-activite.php" id="blindes" class="btnnav"
                                        onclick="return confirm('Attention Réaffectation des Sieges ! ');">Place
                                        (re)</a>
                                    <a href="creation-blindes.php?act=<?php echo $id; ?>&sou=/panel/voir-activite.php"
                                        id="blindes" class="btnnav"
                                        onclick="return confirm('Attention Reset Horloge ! ');">Start (re)</a>
                                </div>
                                <?php 
                                //echo "coucou";
                                };
                                    if ($nbt == '2') { ?>
                                <div id="bMenu">
                                    <a href="#" id="infos" class="btnnav" onmouseover="afficher2('infos')" onclick="afficher2('infos'); return false;">Infos</a>
                                    <a href="#" id="inscrits" class="btnnav"
                                        onmouseover="afficher2('inscrits')" onclick="afficher2('inscrits'); return false;">Joueurs</a>
                                    <a href="#" id="t1" class="btnnav" onmouseover="afficher2('t1')" onclick="afficher2('t1'); return false;">Table 1</a>
                                    <a href="#" id="t2" class="btnnav" onmouseover="afficher2('t2')" onclick="afficher2('t2'); return false;">Table 2</a>
                                    <!-- <a href="#" id="t3" class="btnnav" onmouseover="afficher2('t3')">Table 3</a> -->
                                    <!-- <a href="#" id="t4" class="btnnav" onmouseover="afficher2('t4')">Table 4</a> -->
                                    <a href="voir-blindes.php?uid=<?php echo $id; ?>" id="blindes" class="btnnav"
                                        onclick="window.location=this.href">Timer</a>
                                    <a href="sieges.php?ac=<?php echo $id; ?>" id="blindes" class="btnnav"
                                        onclick="return confirm('Attention Réaffectation des Sieges ! ');">Place
                                        (re)</a>
                                    <a href="creation-blindes.php?zero=1&act=<?php echo $id; ?>&sou=/panel/voir-activite.php"
                                        id="blindes" class="btnnav"
                                        onclick="return confirm('Attention Reset Horloge ! ');">Start (re)</a>
                                </div>
                                <?php }
                                    ;

                                    if ($nbt == '3') { ?>
                                <div id="bMenu">
                                    <a href="#" id="infos" class="btnnav" onmouseover="afficher3('infos')" onclick="afficher3('infos'); return false;">Infos</a>
                                    <a href="#" id="inscrits" class="btnnav"
                                        onmouseover="afficher3('inscrits')" onclick="afficher3('inscrits'); return false;">Joueurs</a>
                                    <a href="#" id="t1" class="btnnav" onmouseover="afficher3('t1')" onclick="afficher3('t1'); return false;">Table 1</a>
                                    <a href="#" id="t2" class="btnnav" onmouseover="afficher3('t2')" onclick="afficher3('t2'); return false;">Table 2</a>
                                    <a href="#" id="t3" class="btnnav" onmouseover="afficher3('t3')" onclick="afficher3('t3'); return false;">Table 3</a>
                                    <!-- <a href="#" id="t4" class="btnnav" onmouseover="afficher3('t4')">Table 4</a> -->
                                    <a href="voir-blindes.php?uid=<?php echo $id; ?>" id="blindes" class="btnnav"
                                        onclick="window.location=this.href">Timer</a>
                                    <a href="sieges.php?ac=<?php echo $id; ?>" id="blindes" class="btnnav"
                                        onclick="return confirm('Attention Réaffectation des Sieges ! ');">Place
                                        (re)</a>
                                    <a href="creation-blindes.php?act=<?php echo $id; ?>&sou=/panel/voir-activite.php"
                                        id="blindes" class="btnnav"
                                        onclick="return confirm('Attention Reset Horloge ! ');">Start (re)</a>
                                </div>
                            </div>
                            <?php }
                                    ;

                                    if ($nbt == '4') { ?>
                            <div id="bMenu">
                                <a href="#" id="infos" class="btnnav" onmouseover="afficher4('infos')" onclick="afficher4('infos'); return false;">Infos</a>
                                <a href="#" id="inscrits" class="btnnav" onmouseover="afficher4('inscrits')" onclick="afficher4('inscrits'); return false;">Joueurs</a>
                                <a href="#" id="t1" class="btnnav" onmouseover="afficher4('t1')" onclick="afficher4('t1'); return false;">Table 1</a>
                                <a href="#" id="t2" class="btnnav" onmouseover="afficher4('t2')" onclick="afficher4('t2'); return false;">Table 2</a>
                                <a href="#" id="t3" class="btnnav" onmouseover="afficher4('t3')" onclick="afficher4('t3'); return false;">Table 3</a>
                                <a href="#" id="t4" class="btnnav" onmouseover="afficher4('t4')" onclick="afficher4('t4'); return false;">Table 4</a>
                                <a href="voir-blindes.php?uid=<?php echo $id; ?>" id="blindes" class="btnnav"
                                    onclick="window.location=this.href">Timer</a>
                                <a href="sieges.php?ac=<?php echo $id; ?>" id="blindes" class="btnnav"
                                    onclick="return confirm('Attention Réaffectation des Sieges ! ');">Place
                                    (re)</a>
                                <a href="creation-blindes.php?act=<?php echo $id; ?>&sou=/panel/voir-activite.php"
                                    id="blindes" class="btnnav"
                                    onclick="return confirm('Attention Reset Horloge ! ');">Start (re)</a>
                            </div>
                            <?php };?>
                            <?php if (empty($nbt) || $nbt == 0) { ?>
                            <!-- Fallback tab menu (server-side) -->
                            <div id="bMenu">
                                <a href="#" id="infos" class="btnnav" onclick="afficher1('infos'); return false;">Infos</a>
                                <a href="#" id="inscrits" class="btnnav" onclick="afficher1('inscrits'); return false;">Joueurs</a>
                                <a href="#" id="t1" class="btnnav" onclick="afficher1('t1'); return false;">Table 1</a>
                                <a href="voir-blindes.php?uid=<?php echo $id; ?>" id="blindes" class="btnnav" onclick="window.location=this.href">Timer</a>
                            </div>
                            <?php } ?>
                            <div id="bSection">
                                <div id="infosE">

                                    <!-- ResponsiveVoice include removed to prevent intrusive floating UI -->
                                    <!-- <script src="https://code.responsivevoice.org/responsivevoice.js?key=RTEc1M0w" onload="try{ responsiveVoice.setDefaultVoice('French Female'); }catch(e){ console.warn('responsiveVoice load onload', e); }"></script> -->
                                    <div class="wrap-content container" id="container">
                                        <div class="container-fluid bbg-pink">
                                            <div class="col-md-12">
                                                <div class="row margin-top-30">
                                                    <div class="panel-wwhite">
                                                        <div class="ppanel-body">
                                                            <!-- <?php echo htmlentities($_SESSION['msg'] = ""); ?> -->
                                                            <div class="form-group">
                                                                <?php
                                                                    $id = intval($_GET['uid']);
                                                                    $_SESSION['pause' . '$id'] = 1;
                                                                    $sql = mysqli_query($con, "SELECT * FROM `activite` WHERE `id-activite` =  '$id'");
                                                                    while ($row = mysqli_fetch_array($sql)) {
                                                                        $id_org = $row["id-membre"];
                                                                        $sql2 = mysqli_query($con, "SELECT * FROM `membres` WHERE `id-membre` =  '$id_org'");
                                                                        while ($row2 = mysqli_fetch_array($sql2)) {
                                                                            $nom_org = $row2['pseudo'];
                                                                        } ?>

                                                                <form method="post" id="form-activite" style="margin:0;">
                                                                <table class="table table-bordered">
                                                                    <tr>
                                                                        <td rowspan="2"><img
                                                                                src="images/<?php echo $row['photo'] ?>"
                                                                                width="85" height="85"
                                                                                style="text-align:center">
                                                                            <form id="image_upload_form"
                                                                                enctype="multipart/form-data"
                                                                                action="upload-photo-activite.php?editid=<?php echo $id ?>"
                                                                                method="post" class="change-pic">
                                                                                <input type="hidden"
                                                                                    name="MAX_FILE_SIZE"
                                                                                    value="10000000" />
                                                                                <div>
                                                                                    <input type="file"
                                                                                        class="fa fa-camera" id="file"
                                                                                        name="fileToUpload"
                                                                                        style="display:none;" /><input
                                                                                        type="button"
                                                                                        onClick="fileToUpload.click();"
                                                                                        value="Modifier" />
                                                                                    <i class="fa fa-camera"></i>
                                                                                </div>
                                                                                <script type="text/javascript">
                                                                                document.getElementById("file")
                                                                                    .onchange = function() {
                                                                                        document.getElementById(
                                                                                                "image_upload_form")
                                                                                            .submit();
                                                                                    };
                                                                                </script>

                                                                            </form>
                                                                        </td>
                                                                            <td colspan="4">
                                                                                    <input type="hidden" name="submit" value="1" id="hidden-submit">
                                                                                <input class="form-control" id="titre-activite"
                                                                                        name="titre-activite" type="text"
                                                                                        style="text-align:center; font-size:22px; width:100%"
                                                                                        value="<?php echo $row['titre-activite']; ?>">
                                                                                    <div style="text-align:center; margin-top:10px; display:flex; gap:10px; justify-content:center;">
                                                                                        <button type="submit" name="submit" id="submit" class="btn btn-oo btn-primary" style="display:none">Mise à jour</button>
                                                                                        <button type="submit" id="submit-ins" class="btn btn-primaryg btn-block" name="submit-ins">S'inscrire</button>
                                                                                        <button type="submit" class="btn btn-primary btn-block" name="submit">Modifier</button>
                                                                                        <button type="submit" class="btn btn-primary-rouge btn-block" name="submit-desins">Se désinscrire</button>
                                                                                    </div>
                                                                                </form>
                                                                            </td>
                                                                        </tr>

                                                                    <tr>
                                                                        <th>Date</th>
                                                                        <td><input class="form-control" id="date_depart"
                                                                                name="date_depart" type="timestamp"
                                                                                value="<?php echo $row['date_depart']; ?>">

                                                                        </td>
                                                                        <th><a
                                                                                href="creation-blindes.php?act=<?php echo $row['id-activite']; ?>&sou=/panel/voir-activite.php">Heure</a>
                                                                        </th>


                                                                        <td><input class="form-control"
                                                                                id="heure_depart" name="heure_depart"
                                                                                type="timestamp"
                                                                                value="<?php echo $row['heure_depart']; ?>">
                                                                        </td>
                                                                    </tr>
                                                                    <tr>
                                                                        <th><a
                                                                                href="voir-membre.php?id=<?php echo $row['id-membre']; ?>">Orga:
                                                                                <?php echo " " . $nom_org . " "; ?></a>
                                                                        </th>

                                                                        <td><?php
                                                                                $membres = mysqli_query($con, "SELECT `id-membre`,`pseudo` FROM `membres` ORDER BY `pseudo` ASC");
                                                                                //    echo "<h align='center' class='jaune'>Pseudo : <select name=id-membre-vainqueur><option value='-Anonyme-'>Choisir ICI --></h>"; 
                                                                                echo "<align='center' class='rougesurblanc'><select name=id-membre><option value=$monmembre>--> Changer Organisateur IcI <--";
                                                                                while ($choix = mysqli_fetch_assoc($membres)) {
                                                                                    $listepseudo = $choix['pseudo'];
                                                                                    // if ($listepseudo == $_SESSION['login'])
                                                                                    //     { echo '<option value="'.$choix['pseudo'].'" selected="selected">'.$_SESSION['login'].'</option>  ';}
                                                                                    // else
                                                                                    {
                                                                                        echo "<option value={$choix["id-membre"]}>{$choix["pseudo"]}\n";
                                                                                    }
                                                                                }
                                                                                echo "</select>";
                                                                                ?>
                                                                        </td>
                                                                        <th>ville</th>
                                                                        <td>
                                                                            <input class="form-control" id="ville"
                                                                                name="ville" type="text"
                                                                                value="<?php echo $row['ville']; ?>">
                                                                        </td>
                                                                    </tr>
                                                                    <tr>
                                                                        <th style="color: #fffdfdff; background-color: #9b9898ff;">lng</th>
                                                                        <td><input class="form-control" id="lng"
                                                                                name="lng" type="text"
                                                                                value="<?php echo $row['lng']; ?>">
                                                                        </td>
                                                                        <th style="color: #fffdfdff; background-color: #9b9898ff;">lat</th>
                                                                        <td><input class="form-control" id="lat"
                                                                                name="lat" type="text"
                                                                                value="<?php echo $row['lat']; ?>">
                                                                        </td>
                                                                    </tr>
                                                                    <tr>
                                                                        <th style="color: #fffdfdff; background-color: #9b9898ff;">places</th>
                                                                        <td><input class="form-control" id="places"
                                                                                name="places" type="text"
                                                                                value="<?php echo $row['places']; ?>">
                                                                        </td>
                                                                        <script type="text/javascript">
                                                                        document.getElementById("places").onchange =
                                                                            function() {
                                                                                var f = document.getElementById("form-activite");
                                                                                if (f) f.submit();
                                                                            };
                                                                        </script>
                                                                        <th style="color: #fffdfdff; background-color: #9b9898ff;">nb tables</th>
                                                                        <td><input class="form-control" id="nb-tables"
                                                                                name="nb-tables" type="text"
                                                                                value="<?php echo $row['nb-tables']; ?>">
                                                                        </td>
                                                                    </tr>
                                                                    <tr>
                                                                        <th style="color: #fffdfdff; background-color: #9b9898ff;">buyin</th>
                                                                        <td><input class="form-control"
                                                                                id="pasbuyinsword" name="buyin"
                                                                                type="text"
                                                                                value="<?php echo $row['buyin']; ?>">
                                                                        </td>
                                                                        <th style="color: #fffdfdff; background-color: #9b9898ff;">Rake</th>
                                                                        <td><input class="form-control" id="rake"
                                                                                name="rake" type="text"
                                                                                value="<?php echo $row['rake']; ?>">
                                                                        </td>
                                                                    </tr>
                                                                    <tr>
                                                                        <th style="color: #fffdfdff; background-color: #9b9898ff;">nbrrecave</th>
                                                                        <td><input class="form-control" id="recave"
                                                                                name="recave" type="text"
                                                                                value="<?php echo $row['recave']; ?>"></td>
                                                                        <th style="color: #fffdfdff; background-color: #9b9898ff;">montant recave</th>
                                                                        <td><input class="form-control" id="recave_montant"
                                                                                name="recave_montant" type="text"
                                                                                value="<?php echo $row['recave_montant']; ?>"></td>
                                                                    </tr>
                                                                    <tr>
                                                                        <th style="color: #fffdfdff; background-color: #9b9898ff;">jetons recave</th>
                                                                        <td><input class="form-control" id="recave_jetons"
                                                                                name="recave_jetons" type="text"
                                                                                value="<?php echo $row['recave_jetons']; ?>"></td>
                                                                        <th style="color: #fffdfdff; background-color: #9b9898ff;">addon</th>
                                                                        <td><input class="form-control" id="addon"
                                                                                name="addon" type="text"
                                                                                value="<?php echo $row['addon']; ?>">
                                                                        </td>
                                                                    </tr>
                                                                    <tr>
                                                                        <th style="color: #fffdfdff; background-color: #9b9898ff;">bounty</th>
                                                                        <td><input class="form-control" id="bounty"
                                                                                name="bounty" type="text"
                                                                                value="<?php echo $row['bounty']; ?>">
                                                                        </td>
                                                                        <th style="color: #fffdfdff; background-color: #9b9898ff;">ante</th>
                                                                        <td><input class="form-control" id="ante"
                                                                                name="ante" type="text"
                                                                                value="<?php echo $row['ante']; ?>">
                                                                        </td>
                                                                    </tr>
                                                                    <tr>
                                                                        <th style="color: #fffdfdff; background-color: #9b9898ff;">jetons</th>
                                                                        <td><input class="form-control" id="jetons"
                                                                                name="jetons" type="text"
                                                                                value="<?php echo $row['jetons']; ?>">
                                                                        </td>
                                                                        <th style="color: #fffdfdff; background-color: #9b9898ff;">dont bonus</th>
                                                                        <td><input class="form-control" id="bonus"
                                                                                name="bonus" type="text"
                                                                                value="<?php echo $row['bonus']; ?>">
                                                                        </td>
                                                                    </tr>
                                                                            <tr>
                                                                            <th style="color: #fffdfdff; background-color: #9b9898ff;">structure</th>
                                                                            <td><input class="form-control" id="structure"
                                                                                name="structure" type="text"
                                                                                value="<?php echo $row['id_structure']; ?>">
                                                                            </td>
                                                                            <th style="color: #fffdfdff; background-color: #9b9898ff;">Challenge</th>
                                                                            <td><input class="form-control" id="challenge"
                                                                                name="challenge" type="text"
                                                                                value="<?php echo isset($row['id_challenge']) ? $row['id_challenge'] : ''; ?>">
                                                                            </td>
                                                                            </tr>
                                                                    <tr>
                                                                        <td style="display:none" ; colspan="2">
                                                                            <select name="lois" value="lois"
                                                                                class="form-control" required="false">
                                                                                <option
                                                                                    value="<?php echo htmlentities($_SESSION['id']); ?>">
                                                                                    <?php echo htmlentities($_SESSION['id']); ?>
                                                                                </option>
                                                                            </select>
                                                                        </td>
                                                                        <td style="display:none" ; colspan="2">
                                                                            <select name="activi" value="activi"
                                                                                class="form-control" required="false">
                                                                                <option
                                                                                    value="<?php echo htmlentities($row['id-activite']); ?>">
                                                                                    <?php echo htmlentities($row['id-activite']); ?>
                                                                                </option>
                                                                            </select>
                                                                        </td>
                                                                        <td style="display:none;"
                                                                            style="text-align:center ;">
                                                                            <button type="submit"
                                                                                class="btn btn-primary btn-block"
                                                                                name="submit">Mise à
                                                                                jour</button>
                                                                        </td>
                                                                    </tr>
                                                                    </tr>
                                                                </table>
                                                                </form>
                                                                <?php
                                                                    }
                                                                    ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div id="t1E" class="rubrique montrer">
                                    <script>
                                    responsiveVoice.setDefaultVoice("French Female")
                                    </script>
                                    <!-- <script >responsiveVoice.speak("Table 1","French Female",{volume: 1})</script> -->

                                    
                                    <script>
                                    // playAudio();
                                    </script>
                                    <!-- <audio src="https://www.soundhelix.com/examples/mp3/SoundHelix-Song-1.mp3" autoplay loop controls></audio> -->
                                    <!-- <audio src="https://poker31.org/panel/popa.mp3" autoplay loop controls></audio> -->

                                    <div class="ccontainer-fluid ccontainer-fullw bg-dark-bricky "
                                        style="background-color:grey;opacity:1">
                                        <!-- <button onclick="playAudio()" type="button" class="btn btn-oo btn-primary" id="play" >Audio</button> -->
                                        <?php
                                            $rowcountocc = "0";
                                            $scru = "0";
                                            // au moins 1 joueur ?
                                            $sqlv = mysqli_query($con, "SELECT * FROM `participation` WHERE `id-activite` = '$id' ");
                                            $rowcountv = mysqli_num_rows($sqlv);
                                            while ((int) $rowcountocc < 1 and $rowcountv > 0) {
                                                (int) $scru++;
                                                // echo $scru."/////////////////////////////////";
                                                $sqla = mysqli_query($con, "SELECT * FROM `participation` WHERE (`id-activite` = '$id' AND `id-table` = '$scru' ) LIMIT 1 ");
                                                $rowcountocc = mysqli_num_rows($sqla);
                                            }
                                            ;
                                            (int) $tableaff = (int) $scru;
                                            // $tableaff = 1;
                                            $cnt = 0;
                                            unset($idmembre);
                                            unset($position);
                                            unset($idparticipation);
                                            unset($siege);
                                            unset($option);
                                            unset($pseudo);
                                            unset($anonyme);
                                            $sql = mysqli_query($con, "SELECT  `id-membre`,`position`,`id-participation`,`id-siege`,`option`, `anonyme` FROM `participation` WHERE (`id-activite` = '$id' AND `id-table` = '$tableaff' )  ORDER BY `id-siege` ");
                                            $nb_lignes = mysqli_num_rows($sql);
                                            // echo $nb_lignes;
                                        
                                            while ($row = mysqli_fetch_array($sql)) {
                                                $cnt = $cnt + 1;
                                                if ($row['id-siege'] <> $cnt)
                                                    $cnt = $cnt + 1;
                                                if ($row['id-siege'] <> $cnt)
                                                    $cnt = $cnt + 1;
                                                if ($row['id-siege'] <> $cnt)
                                                    $cnt = $cnt + 1;
                                                if ($row['id-siege'] <> $cnt)
                                                    $cnt = $cnt + 1;
                                                if ($row['id-siege'] <> $cnt)
                                                    $cnt = $cnt + 1;
                                                if ($row['id-siege'] <> $cnt)
                                                    $cnt = $cnt + 1;
                                                if ($row['id-siege'] <> $cnt)
                                                    $cnt = $cnt + 1;
                                                if ($row['id-siege'] <> $cnt)
                                                    $cnt = $cnt + 1;
                                                if ($row['id-siege'] <> $cnt)
                                                    $cnt = $cnt + 1;
                                                $idmembre[$cnt] = $row['id-membre'];
                                                $position[$cnt] = $row['position'];
                                                $idparticipation[$cnt] = $row['id-participation'];
                                                $siege[$cnt] = $row['id-siege'];
                                                $option[$cnt] = $row['option'];
                                                $anonyme[$cnt] = $row['anonyme'];
                                                $sql2 = mysqli_query($con, "SELECT * FROM `membres` WHERE `id-membre` =  '$idmembre[$cnt]'");
                                                while ($row2 = mysqli_fetch_array($sql2)) {
                                                    $pseudo[$cnt] = $row2['pseudo'];
                                                }
                                            }
                                            ;

                                            ?>

                                        <div id="main">

                                            <div class="players">

                                                <div class="player player-1 playing" id="player1">
                                                    <div class="avatar p1" style="background: blue ;font-size: 1.7vw">
                                                        <form method="post">
                                                            <?php if ($option[1] == 'Elimine')
                                                                    $nom = 'X';
                                                                else
                                                                    $nom = $pseudo[1]; ?>
                                                            <button type="submit" id='submitpl'
                                                                value=<?php echo $idparticipation[1] ?>
                                                                class="btbn btn-pokerblue btn-block "
                                                                name="submitpl"><?php echo $nom ?>
                                                            </button>
                                                        </form>
                                                    </div>
                                                </div>

                                                <div class="player player-2 playing" id="player2">
                                                    <div class="avatar p2" style="background: red ;font-size: 1.7vw">
                                                        <form method="post">
                                                            <?php if ($option[2] == 'Elimine')
                                                                    $nom = 'X';
                                                                else
                                                                    $nom = $pseudo[2]; ?>
                                                            <button type="submit" id='submitpl'
                                                                value=<?php echo $idparticipation[2] ?>
                                                                class="btbn btn-pokerred btn-block "
                                                                name="submitpl"><?php echo $nom ?>
                                                            </button>
                                                        </form>
                                                    </div>
                                                </div>

                                                <div class="player player-3 playing" id="player3">
                                                    <div class="avatar p3" style="background: black ;font-size:18px">
                                                        <form method="post">
                                                            <?php if ($option[3] == 'Elimine')
                                                                    $nom = 'X';
                                                                else
                                                                    $nom = $pseudo[3]; ?>
                                                            <button type="submit" id='submitpl'
                                                                value=<?php echo $idparticipation[3] ?>
                                                                class="btnn btn-primary-noir btn-block "
                                                                name="submitpl"><?php echo $nom ?>
                                                            </button>
                                                        </form>
                                                    </div>
                                                </div>

                                                <div class="player player-4 playing" id="player4">
                                                    <div class="avatar p4" style="background: orange; font-size:18px">
                                                        <form method="post">
                                                            <?php if ($option[4] == 'Elimine')
                                                                    $nom = 'X';
                                                                else
                                                                    $nom = $pseudo[4]; ?>
                                                            <button type="submit" id='submitpl'
                                                                value=<?php echo $idparticipation[4] ?>
                                                                class="btnn btn-primary-orange2 btn-block "
                                                                name="submitpl"><?php echo $nom ?>
                                                            </button>
                                                        </form>
                                                    </div>
                                                </div>

                                                <div class="player player-5 playing" id="player5">
                                                    <div class="avatar p5" style="background: grey; font-size:18px">
                                                        <form method="post">
                                                            <?php if ($option[5] == 'Elimine')
                                                                    $nom = 'X';
                                                                else
                                                                    $nom = $pseudo[5]; ?>
                                                            <button type="submit" id='submitpl'
                                                                value=<?php echo $idparticipation[5] ?>
                                                                class="btnn btn-primary-grey btn-block "
                                                                name="submitpl"><?php echo $nom ?>
                                                            </button>
                                                        </form>
                                                    </div>
                                                </div>

                                                <div class="player player-6 playing" id="player6">
                                                    <div class="avatar p6" style="background: brown; font-size:18px">
                                                        <form method="post">
                                                            <?php if ($option[6] == 'Elimine')
                                                                    $nom = 'X';
                                                                else
                                                                    $nom = $pseudo[6]; ?>
                                                            <button type="submit" id='submitpl'
                                                                value=<?php echo $idparticipation[6] ?>
                                                                class="btnn btn-primary-brown btn-block"
                                                                name="submitpl"><?php echo $nom ?>
                                                            </button>
                                                        </form>
                                                    </div>
                                                </div>

                                                <div class="player player-7 playing" id="player7">
                                                    <div class="avatar p7" style="background: pink; font-size:18px">
                                                        <form method="post">
                                                            <?php if ($option[7] == 'Elimine')
                                                                    $nom = 'X';
                                                                else
                                                                    $nom = $pseudo[7]; ?>
                                                            <button type="submit" id='submitpl'
                                                                value=<?php echo $idparticipation[7] ?>
                                                                class="btnn btn-primary-pink btn-block"
                                                                name="submitpl"><?php echo $nom ?>
                                                            </button>
                                                        </form>
                                                    </div>
                                                </div>

                                                <div class="player player-8 playing" id="player8">
                                                    <div class="avatar p8" style="background: purple; font-size:18px">

                                                        <form method="post">
                                                            <?php if ($option[8] == 'Elimine')
                                                                    $nom = 'X';
                                                                else
                                                                    $nom = $pseudo[8]; ?>
                                                            <button type="submit" id='submitpl'
                                                                value=<?php echo $idparticipation[8] ?>
                                                                class="btnn btn-primary-purple btn-block"
                                                                name="submitpl"><?php echo $nom ?>
                                                            </button>
                                                        </form>
                                                    </div>
                                                </div>

                                                <div class="player player-9 playing" id="player9">
                                                    <div class="avatar p9" style="background: grey; font-size:18px">
                                                        <form method="post">
                                                            <?php if ($option[9] == 'Elimine')
                                                                    $nom = 'X';
                                                                else
                                                                    $nom = $pseudo[9]; ?>
                                                            <button type="submit" id='submitpl'
                                                                value=<?php echo $idparticipation[9] ?>
                                                                class="btnn btn-primary-orange2 btn-block"
                                                                name="submitpl"><?php echo $nom ?>
                                                            </button>
                                                        </form>
                                                    </div>
                                                </div>

                                                <div class="player player-10 playing" id="player10">
                                                    <div class="avatar p10" style="background: green; font-size:18px">

                                                        <form method="post">
                                                            <?php if ($option[10] == 'Elimine')
                                                                    $nom = 'X';
                                                                else
                                                                    $nom = $pseudo[10]; ?>
                                                            <button type="submit" id='submitpl'
                                                                value=<?php echo $idparticipation[10] ?>
                                                                class="btnn btn-primary-noir btn-block"
                                                                name="submitpl"><?php echo $nom ?>
                                                            </button>
                                                    </div>
                                                </div>

                                            </div>

                                            <div class='square-box' opacity:0.85>
                                                <div class='square-content'>
                                                    <?php if (1) { ?>
                                                    <div id="response"></div><?php }
                                                        ; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <?php
                                        date_default_timezone_set('Europe/Paris');
                                        $req = mysqli_query($con, "SELECT * FROM `blindes-live` WHERE (`id-activite` = $id AND `ordre` = 1)");
                                        $row = mysqli_fetch_array($req);
                                        $commence = $row["fin"];
                                        $actu = strtotime(date("Y-m-d H:i:s"));
                                        $debu = strtotime($commence);
                                        $ecar = $actu - $debu ;
                                        //echo $ecar;
                                       
                                        if ($ecar > 0) {
                                            ?>
                                    <div class='info6-content '> <?php echo $star; ?> </div>
                                    <?php
                                            $sqla = mysqli_query($con, "SELECT * FROM `participation` WHERE ((`id-activite` = $id AND `option` NOT LIKE 'Annule' ) AND (`id-activite` = $id AND `option` NOT LIKE 'Elimine' ) AND (`id-activite` = $id AND `id-membre` <> '2' ))");
                                            $rowcounta = mysqli_num_rows($sqla);
                                            $sqlb = mysqli_query($con, "SELECT * FROM `activite` WHERE `id-activite` = $id");
                                            $resb = mysqli_fetch_array($sqlb);
                                            $joueursmax = $resb["places"];
                                            ?>
                                    <div class='info7-content '>
                                        <?php echo $rowcounta + 1 . " Joueurs inscrits sur " . $joueursmax . " max" ?>
                                    </div>
                                    <div class='info8-content'>
                                        <table class="table ttable-bordered">
                                            <form method="post">
                                                <tr>
                                                    <td style="text-align:center ; display:none">
                                                        <button type="submit" name="xxx" id="submit"
                                                            class="btn btn-oo btn-primary">
                                                            Mise à jour
                                                        </button>
                                                    </td>
                                                    <td style="text-align:center ;">
                                                        <button type="submit" class="btn btn-primaryg btn-block"
                                                            name="submit-ins">S'inscrire
                                                        </button>
                                                    </td>
                                                    <td style="text-align:center ;">
                                                        <button type="submit" class="btn btn-primary btn-block"
                                                            name="btn-info">Infos
                                                        </button>
                                                    </td>
                                                    <td style="text-align:center ;">
                                                        <button type="submit" class="btn btn-primary-rouge btn-block"
                                                            name="submit-deins">Se
                                                            désinscrire
                                                        </button>
                                                    </td>
                                                </tr>
                                            </form>
                                        </table>
                                    </div>

                                    <?php } else {
                                            ?>
                                    <div class='info1-content '> <?php echo "Dernier Eliminé : ".$nbt; ?>
                                        <?php
                                                $sql = mysqli_query($con, "SELECT * FROM `participation` WHERE (`id-activite` = $id AND `option` NOT LIKE 'Elimine' )");
                                                $rowcount = mysqli_num_rows($sql);
                                                $sql = mysqli_query($con, "SELECT * FROM `participation` WHERE (`id-activite` = $id AND `option` NOT LIKE 'Annule' )");
                                                $rowcount2 = mysqli_num_rows($sql);
                                                $sql = mysqli_query($con, "SELECT * FROM `participation` WHERE (`id-activite` = $id AND `option` NOT LIKE 'Annule' ) OR (`id-activite` = $id AND `option` NOT LIKE 'Elimine' )");
                                                $rowcount3 = mysqli_num_rows($sql);
                                                $req = mysqli_query($con, "SELECT * FROM `participation` WHERE `id-activite` = $id AND `option` LIKE 'Elimine' ORDER BY `ds` ASC");
                                                $rowcounteli = mysqli_num_rows($req);
                                                while ($res = mysqli_fetch_array($req)) {
                                                    $eli1 = $res["id-membre"];
                                                    $res2 = mysqli_query($con, "SELECT * FROM `membres` WHERE (`id-membre` = $eli1)");
                                                    $row2 = mysqli_fetch_array($res2);
                                                    $nom1 = $row2["pseudo"];
                                                }
                                                ;
                                                echo $nom1;
                                                ?>
                                    </div>

                                    <div class='info2-content '> <?php echo "Bulle : " ?>
                                        <?php
                                                if ($rowcount2 > 0) {
                                                    $bul = 2;
                                                }
                                                ;
                                                if ($rowcount2 > 5) {
                                                    $bul = 3;
                                                }
                                                ;
                                                if ($rowcount2 > 8) {
                                                    $bul = 4;
                                                }
                                                ;
                                                if ($rowcount2 > 13) {
                                                    $bul = 5;
                                                }
                                                ;
                                                if ($rowcount2 > 20) {
                                                    $bul = 6;
                                                }
                                                ;
                                                $sql = mysqli_query($con, "SELECT * FROM `participation` WHERE (`id-activite` = $id AND `option` NOT LIKE 'Elimine' )");
                                                $rowcount = mysqli_num_rows($sql);
                                                $sql = mysqli_query($con, "SELECT * FROM `participation` WHERE (`id-activite` = $id AND `option` NOT LIKE 'Annule' )");
                                                $rowcount2 = mysqli_num_rows($sql);
                                                $sql = mysqli_query($con, "SELECT * FROM `participation` WHERE (`id-activite` = $id AND `option` NOT LIKE 'Annule' ) OR (`id-activite` = $id AND `option` NOT LIKE 'Elimine' )");
                                                $rowcount3 = mysqli_num_rows($sql);
                                                $req = mysqli_query($con, "SELECT * FROM `participation` WHERE `id-activite` = $id AND `option` LIKE 'Elimine' ORDER BY `ds` ASC");
                                                $reqbul = mysqli_query($con, "SELECT * FROM `participation` WHERE `id-activite` = $id AND `option` LIKE 'Elimine' AND `classement` = $bul");
                                                $rowcounteli = mysqli_num_rows($req);
                                                while ($res = mysqli_fetch_array($reqbul)) {
                                                    $eli1 = $res["id-membre"];
                                                    $res2 = mysqli_query($con, "SELECT * FROM `membres` WHERE (`id-membre` = $eli1)");
                                                    $row2 = mysqli_fetch_array($res2);
                                                    $nom1 = $row2["pseudo"];
                                                }
                                                ;
                                                echo $nom1;
                                                ?>
                                    </div>

                                    <div class='info2-content'>
                                        <div id="car-pause">
                                        </div>
                                    </div>
                                    <?php
                                            if ($rowcount2 > 5) {
                                                $payes = 2;
                                                $r1 = 0.6;
                                                $r2 = 0.4;
                                            }
                                            ;
                                            if ($rowcount2 > 8) {
                                                $payes = 3;
                                                $r1 = 0.5;
                                                $r2 = 0.3;
                                                $r3 = 0.2;
                                            }
                                            ;
                                            if ($rowcount2 > 13) {
                                                $payes = 4;
                                                $r1 = 0.4;
                                                $r2 = 0.28;
                                                $r3 = 0.19;
                                                $r4 = 0.13;
                                            }
                                            ;
                                            if ($rowcount2 > 20) {
                                                $payes = 5;
                                                $r1 = 0.35;
                                                $r2 = 0.25;
                                                $r3 = 0.18;
                                                $r4 = 0.13;
                                                $r5 = 0.09;
                                            }
                                            ;
                                            ?>
                                    <?php if ($rowcount2 - $rowcounteli - $payes > 0) { ?>
                                    <div class='info3-content '>
                                        <?php echo "Premier des " . $payes . " payés dans " . $rowcount2 - $rowcounteli - $payes . " Joueurs sur " . $rowcount2 ?>
                                    </div>
                                    <?php } else { ?>
                                    <div class='info3-content '>
                                        <?php echo $payes . " payés maintenant sur les " . $rowcount2 . " joueurs" ?>
                                    </div>
                                    <?php }
                                            ;
                                            $sql2 = mysqli_query($con, "SELECT * FROM `activite` WHERE (`id-activite` = $id  )");
                                            $res2 = mysqli_fetch_array($sql2);
                                            $buyin = $res2["buyin"];
                                            $jetons = $res2["jetons"];
                                            $pot = 0;
                                            $nbr = 0;
                                            $nba = 0;
                                            $req3 = mysqli_query($con, "SELECT * FROM `participation` WHERE (`id-activite` = $id AND `option` NOT LIKE 'Annule') ");
                                            while ($res3 = mysqli_fetch_array($req3)) {
                                                $pot = $pot + (((int) ($res3["recave"]) + (int) ($res3["addon"])));
                                                $nbr = $nbr + $res3["recave"];
                                                $nba = $nba + $res3["addon"];
                                            }
                                            ;
                                            $tot = $pot + $rowcount2;
                                            $final = $tot * $buyin;
                                            if ($payes == 2) {
                                                $p2 = $final * $r2;
                                                $p2 = $p2 / 10;
                                                $p2 = round($p2, 0);
                                                $p2 = $p2 * 10;
                                                $p1 = $final - $p2;
                                                ?>
                                    <div class='info4-content '>
                                        <?php echo "Pot total : " . $final . "€ soit : " . "P1=" . $p1 . "€, P2=" . $p2 . "€" ?>
                                    </div>
                                    <?php
                                            }
                                            ;
                                            if ($payes == 3) {
                                                $p3 = $final * $r3;
                                                $p3 = $p3 / 10;
                                                $p3 = round($p3, 0);
                                                $p3 = $p3 * 10;
                                                $p2 = $final * $r2;
                                                $p2 = $p2 / 10;
                                                $p2 = round($p2, 0);
                                                $p2 = $p2 * 10;
                                                $p1 = $final - $p3 - $p2;
                                                ?>
                                    <div class='info4-content '>
                                        <?php echo "Pot total : " . $final . "€ soit : " . "P1=" . $p1 . "€, P2=" . $p2 . "€, P3=" . $p3 . "€" ?>
                                    </div>
                                    <?php
                                            }
                                            ;
                                            if ($payes == 4) {
                                                $p4 = $final * $r4;
                                                $p4 = $p4 / 10;
                                                $p4 = round($p4, 0);
                                                $p4 = $p4 * 10;
                                                $p3 = $final * $r3;
                                                $p3 = $p3 / 10;
                                                $p3 = round($p3, 0);
                                                $p3 = $p3 * 10;
                                                $p2 = $final * $r2;
                                                $p2 = $p2 / 10;
                                                $p2 = round($p2, 0);
                                                $p2 = $p2 * 10;
                                                $p1 = $final - $p4 - $p3 - $p2;
                                                ?>
                                    <div class='info4-content '>
                                        <?php echo "Pot total : " . $final . "€ soit : " . "P1=" . $p1 . "€, P2=" . $p2 . "€, P3=" . $p3 . "€, P4=" . $p4 . "€" ?>
                                    </div>
                                    <?php
                                            }
                                            ;
                                            if ($payes == 5) {
                                                $p5 = $final * $r5;
                                                $p5 = $p5 / 10;
                                                $p5 = round($p5, 0);
                                                $p5 = $p5 * 10;
                                                $p4 = $final * $r4;
                                                $p4 = $p4 / 10;
                                                $p4 = round($p4, 0);
                                                $p4 = $p4 * 10;
                                                $p3 = $final * $r3;
                                                $p3 = $p3 / 10;
                                                $p3 = round($p3, 0);
                                                $p3 = $p3 * 10;
                                                $p2 = $final * $r2;
                                                $p2 = $p2 / 10;
                                                $p2 = round($p2, 0);
                                                $p2 = $p2 * 10;
                                                $p1 = $final - $p5 - $p4 - $p3 - $p2;
                                                ?>
                                    <div class='info4-content '>
                                        <?php echo "Pot total : " . $final . "€ soit : " . "P1=" . $p1 . "€, P2=" . $p2 . "€, P3=" . $p3 . "€, P4=" . $p4 . "€, P5=" . $p5 . "€" ?>
                                    </div>
                                    <?php
                                            }
                                            ;
                                            $enjeu = ($rowcount2 - $rowcounteli);
                                            if ($enjeu == 0)
                                                $enjeu = 1;
                                            else
                                                $enjeu = $rowcount2 - $rowcounteli;
                                            ?>

                                    <div class='info5-content '>
                                        <?php echo "Stack Moyen : " . ($jetons * ($rowcount2 + $nbr + $nba)) / ($enjeu) . " sur " . ($jetons * ($rowcount2 + $nbr + $nba)) . " = (" . $rowcount2 . "B ) + " . " (" . $nbr . "R ) + " . " (" . $nba . "A )" ?>
                                    </div>

                                    <?php $_SESSION["act"] = $id;
                                            // include_once('horloge.php'); 
                                            ?>
                                    <?php $_SESSION["act"] = $id;
                                            // include_once('horloge-pause.php'); 
                                            ?>
                                    <?php }
                                        ; ?>
                                    <!--</div>-->
                                    <?php } ?>
                                </div>
                                <div id="t2E">
                                    <script>
                                    responsiveVoice.setDefaultVoice("French Female")
                                    </script>
                                    <!-- <script >responsiveVoice.speak("Table 2","French Female",{volume: 1})</script> -->
                                    <?php if ($nbt >= '2') { ?>
                                    <script>
                                    var audio3 = new Audio("popa.mp3");
                                    var audio2 = new Audio("http://glpjt.s3.amazonaws.com/so/av/a12.mp3");
                                    var audio = new Audio(
                                        "https://s3.amazonaws.com/audio-experiments/examples/elon_mono.wav");

                                    function playAudio() {
                                        audio3.play();
                                    }

                                    function pauseAudio() {
                                        audio2.play();
                                    }

                                    function cancelAudio() {
                                        audio.pause();
                                        audio.currentTime = 0;
                                    }
                                    // playAudio();
                                    // audio3.play();
                                    </script>
                                    <script>
                                    // playAudio();
                                    </script>
                                    <!-- <audio src="https://www.soundhelix.com/examples/mp3/SoundHelix-Song-1.mp3" autoplay loop controls></audio> -->
                                    <!-- <audio src="https://poker31.org/panel/popa.mp3" autoplay loop controls></audio> -->

                                    <div class="ccontainer-fluid ccontainer-fullw bg-dark-bricky "
                                        style="background-color:grey;opacity:1">
                                        <?php
                                                $rowcountocc = "0";
                                                $scru = $tableaff;
                                                if ((int) $rowcountocc < 1) {
                                                    (int) $scru++;
                                                    // echo $scru."/////////////////////////////////";
                                                    $sqla = mysqli_query($con, "SELECT * FROM `participation` WHERE (`id-activite` = '$id' AND `id-table` = '$scru' ) LIMIT 1 ");
                                                    $rowcountocc = mysqli_num_rows($sqla);
                                                }
                                                ;
                                                (int) $tableaff = (int) $scru;
                                                $tableaff = 2;
                                                $cnt = 0;
                                                unset($idmembre);
                                                unset($position);
                                                unset($idparticipation);
                                                unset($siege);
                                                unset($option);
                                                unset($pseudo);
                                                unset($anonyme);
                                                $sql = mysqli_query($con, "SELECT  `id-membre`,`position`,`id-participation`,`id-siege`,`option`, `anonyme` FROM `participation` WHERE (`id-activite` = '$id' AND `id-table` = '$tableaff' )  ORDER BY `id-siege` ");
                                                $nb_lignes = mysqli_num_rows($sql);
                                                // echo $nb_lignes;
                                        
                                                while ($row = mysqli_fetch_array($sql)) {
                                                    $cnt = $cnt + 1;
                                                    if ($row['id-siege'] <> $cnt)
                                                        $cnt = $cnt + 1;
                                                    if ($row['id-siege'] <> $cnt)
                                                        $cnt = $cnt + 1;
                                                    if ($row['id-siege'] <> $cnt)
                                                        $cnt = $cnt + 1;
                                                    if ($row['id-siege'] <> $cnt)
                                                        $cnt = $cnt + 1;
                                                    if ($row['id-siege'] <> $cnt)
                                                        $cnt = $cnt + 1;
                                                    if ($row['id-siege'] <> $cnt)
                                                        $cnt = $cnt + 1;
                                                    if ($row['id-siege'] <> $cnt)
                                                        $cnt = $cnt + 1;
                                                    if ($row['id-siege'] <> $cnt)
                                                        $cnt = $cnt + 1;
                                                    if ($row['id-siege'] <> $cnt)
                                                        $cnt = $cnt + 1;
                                                    $idmembre[$cnt] = $row['id-membre'];
                                                    $position[$cnt] = $row['position'];
                                                    $idparticipation[$cnt] = $row['id-participation'];
                                                    $siege[$cnt] = $row['id-siege'];
                                                    $option[$cnt] = $row['option'];
                                                    $anonyme[$cnt] = $row['anonyme'];
                                                    $sql2 = mysqli_query($con, "SELECT * FROM `membres` WHERE `id-membre` =  '$idmembre[$cnt]'");
                                                    while ($row2 = mysqli_fetch_array($sql2)) {
                                                        $pseudo[$cnt] = $row2['pseudo'];
                                                    }
                                                }
                                                ;

                                                ?>

                                        <div id="main">

                                            <div class="players">

                                                <div class="player player-1 playing" id="player1">
                                                    <div class="avatar p1" style="background: blue ;font-size: 1.7vw">
                                                        <form method="post">
                                                            <?php if ($option[1] == 'Elimine')
                                                                    $nom = 'X';
                                                                else
                                                                    $nom = $pseudo[1]; ?>
                                                            <button type="submit" id='submitpl'
                                                                value=<?php echo $idparticipation[1] ?>
                                                                class="btbn btn-pokerblue btn-block "
                                                                name="submitpl"><?php echo $nom ?>
                                                            </button>
                                                        </form>
                                                    </div>
                                                </div>

                                                <div class="player player-2 playing" id="player2">
                                                    <div class="avatar p2" style="background: red ;font-size: 1.7vw">
                                                        <form method="post">
                                                            <?php if ($option[2] == 'Elimine')
                                                                    $nom = 'X';
                                                                else
                                                                    $nom = $pseudo[2]; ?>
                                                            <button type="submit" id='submitpl'
                                                                value=<?php echo $idparticipation[2] ?>
                                                                class="btbn btn-pokerred btn-block "
                                                                name="submitpl"><?php echo $nom ?>
                                                            </button>
                                                        </form>
                                                    </div>
                                                </div>

                                                <div class="player player-3 playing" id="player3">
                                                    <div class="avatar p3" style="background: black ;font-size:18px">
                                                        <form method="post">
                                                            <?php if ($option[3] == 'Elimine')
                                                                    $nom = 'X';
                                                                else
                                                                    $nom = $pseudo[3]; ?>
                                                            <button type="submit" id='submitpl'
                                                                value=<?php echo $idparticipation[3] ?>
                                                                class="btnn btn-primary-noir btn-block "
                                                                name="submitpl"><?php echo $nom ?>
                                                            </button>
                                                        </form>
                                                    </div>
                                                </div>

                                                <div class="player player-4 playing" id="player4">
                                                    <div class="avatar p4" style="background: orange; font-size:18px">
                                                        <form method="post">
                                                            <?php if ($option[4] == 'Elimine')
                                                                    $nom = 'X';
                                                                else
                                                                    $nom = $pseudo[4]; ?>
                                                            <button type="submit" id='submitpl'
                                                                value=<?php echo $idparticipation[4] ?>
                                                                class="btnn btn-primary-orange2 btn-block "
                                                                name="submitpl"><?php echo $nom ?>
                                                            </button>
                                                        </form>
                                                    </div>
                                                </div>

                                                <div class="player player-5 playing" id="player5">
                                                    <div class="avatar p5" style="background: grey; font-size:18px">
                                                        <form method="post">
                                                            <?php if ($option[5] == 'Elimine')
                                                                    $nom = 'X';
                                                                else
                                                                    $nom = $pseudo[5]; ?>
                                                            <button type="submit" id='submitpl'
                                                                value=<?php echo $idparticipation[5] ?>
                                                                class="btnn btn-primary-grey btn-block "
                                                                name="submitpl"><?php echo $nom ?>
                                                            </button>
                                                        </form>
                                                    </div>
                                                </div>

                                                <div class="player player-6 playing" id="player6">
                                                    <div class="avatar p6" style="background: brown; font-size:18px">
                                                        <form method="post">
                                                            <?php if ($option[6] == 'Elimine')
                                                                    $nom = 'X';
                                                                else
                                                                    $nom = $pseudo[6]; ?>
                                                            <button type="submit" id='submitpl'
                                                                value=<?php echo $idparticipation[6] ?>
                                                                class="btnn btn-primary-brown btn-block"
                                                                name="submitpl"><?php echo $nom ?>
                                                            </button>
                                                        </form>
                                                    </div>
                                                </div>

                                                <div class="player player-7 playing" id="player7">
                                                    <div class="avatar p7" style="background: pink; font-size:18px">
                                                        <form method="post">
                                                            <?php if ($option[7] == 'Elimine')
                                                                    $nom = 'X';
                                                                else
                                                                    $nom = $pseudo[7]; ?>
                                                            <button type="submit" id='submitpl'
                                                                value=<?php echo $idparticipation[7] ?>
                                                                class="btnn btn-primary-pink btn-block"
                                                                name="submitpl"><?php echo $nom ?>
                                                            </button>
                                                        </form>
                                                    </div>
                                                </div>

                                                <div class="player player-8 playing" id="player8">
                                                    <div class="avatar p8" style="background: purple; font-size:18px">

                                                        <form method="post">
                                                            <?php if ($option[8] == 'Elimine')
                                                                    $nom = 'X';
                                                                else
                                                                    $nom = $pseudo[8]; ?>
                                                            <button type="submit" id='submitpl'
                                                                value=<?php echo $idparticipation[8] ?>
                                                                class="btnn btn-primary-purple btn-block"
                                                                name="submitpl"><?php echo $nom ?>
                                                            </button>
                                                        </form>
                                                    </div>
                                                </div>

                                                <div class="player player-9 playing" id="player9">
                                                    <div class="avatar p9" style="background: grey; font-size:18px">
                                                        <form method="post">
                                                            <?php if ($option[9] == 'Elimine')
                                                                    $nom = 'X';
                                                                else
                                                                    $nom = $pseudo[9]; ?>
                                                            <button type="submit" id='submitpl'
                                                                value=<?php echo $idparticipation[9] ?>
                                                                class="btnn btn-primary-orange2 btn-block"
                                                                name="submitpl"><?php echo $nom ?>
                                                            </button>
                                                        </form>
                                                    </div>
                                                </div>

                                                <div class="player player-10 playing" id="player10">
                                                    <div class="avatar p10" style="background: green; font-size:18px">

                                                        <form method="post">
                                                            <?php if ($option[10] == 'Elimine')
                                                                    $nom = 'X';
                                                                else
                                                                    $nom = $pseudo[10]; ?>
                                                            <button type="submit" id='submitpl'
                                                                value=<?php echo $idparticipation[10] ?>
                                                                class="btnn btn-primary-green btn-block"
                                                                name="submitpl"><?php echo $nom ?>
                                                            </button>
                                                        </form>
                                                    </div>
                                                </div>

                                            </div>

                                            <div class='square-box' opacity:0.85>
                                                <div class='square-content'>
                                                    <?php if (1) { ?>
                                                    <div id="response"></div><?php }
                                                        ; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <?php
                                        date_default_timezone_set('Europe/Paris');
                                        $req = mysqli_query($con, "SELECT * FROM `blindes-live` WHERE (`id-activite` = $id AND `ordre` = 1)");
                                        $row = mysqli_fetch_array($req);
                                        $commence = $row["fin"];
                                        $actu = strtotime(date("Y-m-d H:i:s"));
                                        $debu = strtotime($commence);
                                        $ecar = $actu - $debu ;
                                        //echo $ecar;
                                       
                                        if ($ecar > 0) {
                                            ?>
                                    <div class='info6-content '> <?php echo $star; ?> </div>
                                    <?php
                                            $sqla = mysqli_query($con, "SELECT * FROM `participation` WHERE ((`id-activite` = $id AND `option` NOT LIKE 'Annule' ) AND (`id-activite` = $id AND `option` NOT LIKE 'Elimine' ) AND (`id-activite` = $id AND `id-membre` <> '2' ))");
                                            $rowcounta = mysqli_num_rows($sqla);
                                            $sqlb = mysqli_query($con, "SELECT * FROM `activite` WHERE `id-activite` = $id");
                                            $resb = mysqli_fetch_array($sqlb);
                                            $joueursmax = $resb["places"];
                                            ?>
                                    <div class='info7-content '>
                                        <?php echo $rowcounta + 1 . " Joueurs inscrits sur " . $joueursmax . " max" ?>
                                    </div>
                                    <div class='info8-content'>
                                        <table class="table ttable-bordered">
                                            <form method="post">
                                                <tr>
                                                    <td style="text-align:center ; display:none">
                                                        <button type="submit" name="xxx" id="submit"
                                                            class="btn btn-oo btn-primary">
                                                            Mise à jour
                                                        </button>
                                                    </td>
                                                    <td style="text-align:center ;">
                                                        <button type="submit" class="btn btn-primaryg btn-block"
                                                            name="submit-ins">S'inscrire
                                                        </button>
                                                    </td>
                                                    <td style="text-align:center ;">
                                                        <button type="submit" class="btn btn-primary btn-block"
                                                            name="btn-info">Infos
                                                        </button>
                                                    </td>
                                                    <td style="text-align:center ;">
                                                        <button type="submit" class="btn btn-primary-rouge btn-block"
                                                            name="submit-deins">Se
                                                            désinscrire
                                                        </button>
                                                    </td>
                                                </tr>
                                            </form>
                                        </table>
                                    </div>

                                    <?php } else {
                                            ?>
                                    <div class='info1-content '> <?php echo "Dernier Eliminé : " ?>
                                        <?php
                                                $sql = mysqli_query($con, "SELECT * FROM `participation` WHERE (`id-activite` = $id AND `option` NOT LIKE 'Elimine' )");
                                                $rowcount = mysqli_num_rows($sql);
                                                $sql = mysqli_query($con, "SELECT * FROM `participation` WHERE (`id-activite` = $id AND `option` NOT LIKE 'Annule' )");
                                                $rowcount2 = mysqli_num_rows($sql);
                                                $sql = mysqli_query($con, "SELECT * FROM `participation` WHERE (`id-activite` = $id AND `option` NOT LIKE 'Annule' ) OR (`id-activite` = $id AND `option` NOT LIKE 'Elimine' )");
                                                $rowcount3 = mysqli_num_rows($sql);
                                                $req = mysqli_query($con, "SELECT * FROM `participation` WHERE `id-activite` = $id AND `option` LIKE 'Elimine' ORDER BY `ds` ASC");
                                                $rowcounteli = mysqli_num_rows($req);
                                                while ($res = mysqli_fetch_array($req)) {
                                                    $eli1 = $res["id-membre"];
                                                    $res2 = mysqli_query($con, "SELECT * FROM `membres` WHERE (`id-membre` = $eli1)");
                                                    $row2 = mysqli_fetch_array($res2);
                                                    $nom1 = $row2["pseudo"];
                                                }
                                                ;
                                                echo $nom1;
                                                ?>
                                    </div>

                                    <div class='info2-content '> <?php echo "Bulle : " ?>
                                        <?php
                                                if ($rowcount2 > 0) {
                                                    $bul = 2;
                                                }
                                                ;
                                                if ($rowcount2 > 5) {
                                                    $bul = 3;
                                                }
                                                ;
                                                if ($rowcount2 > 8) {
                                                    $bul = 4;
                                                }
                                                ;
                                                if ($rowcount2 > 13) {
                                                    $bul = 5;
                                                }
                                                ;
                                                if ($rowcount2 > 20) {
                                                    $bul = 6;
                                                }
                                                ;
                                                $sql = mysqli_query($con, "SELECT * FROM `participation` WHERE (`id-activite` = $id AND `option` NOT LIKE 'Elimine' )");
                                                $rowcount = mysqli_num_rows($sql);
                                                $sql = mysqli_query($con, "SELECT * FROM `participation` WHERE (`id-activite` = $id AND `option` NOT LIKE 'Annule' )");
                                                $rowcount2 = mysqli_num_rows($sql);
                                                $sql = mysqli_query($con, "SELECT * FROM `participation` WHERE (`id-activite` = $id AND `option` NOT LIKE 'Annule' ) OR (`id-activite` = $id AND `option` NOT LIKE 'Elimine' )");
                                                $rowcount3 = mysqli_num_rows($sql);
                                                $req = mysqli_query($con, "SELECT * FROM `participation` WHERE `id-activite` = $id AND `option` LIKE 'Elimine' ORDER BY `ds` ASC");
                                                $reqbul = mysqli_query($con, "SELECT * FROM `participation` WHERE `id-activite` = $id AND `option` LIKE 'Elimine' AND `classement` = $bul");
                                                $rowcounteli = mysqli_num_rows($req);
                                                while ($res = mysqli_fetch_array($reqbul)) {
                                                    $eli1 = $res["id-membre"];
                                                    $res2 = mysqli_query($con, "SELECT * FROM `membres` WHERE (`id-membre` = $eli1)");
                                                    $row2 = mysqli_fetch_array($res2);
                                                    $nom1 = $row2["pseudo"];
                                                }
                                                ;
                                                echo $nom1;
                                                ?>
                                    </div>

                                    <div class='info2-content'>
                                        <div id="car-pause">
                                        </div>
                                    </div>
                                    <?php
                                            if ($rowcount2 > 5) {
                                                $payes = 2;
                                                $r1 = 0.6;
                                                $r2 = 0.4;
                                            }
                                            ;
                                            if ($rowcount2 > 8) {
                                                $payes = 3;
                                                $r1 = 0.5;
                                                $r2 = 0.3;
                                                $r3 = 0.2;
                                            }
                                            ;
                                            if ($rowcount2 > 13) {
                                                $payes = 4;
                                                $r1 = 0.4;
                                                $r2 = 0.28;
                                                $r3 = 0.19;
                                                $r4 = 0.13;
                                            }
                                            ;
                                            if ($rowcount2 > 20) {
                                                $payes = 5;
                                                $r1 = 0.35;
                                                $r2 = 0.25;
                                                $r3 = 0.18;
                                                $r4 = 0.13;
                                                $r5 = 0.09;
                                            }
                                            ;
                                            ?>
                                    <?php if ($rowcount2 - $rowcounteli - $payes > 0) { ?>
                                    <div class='info3-content '>
                                        <?php echo "Premier des " . $payes . " payés dans " . $rowcount2 - $rowcounteli - $payes . " Joueurs sur " . $rowcount2 ?>
                                    </div>
                                    <?php } else { ?>
                                    <div class='info3-content '>
                                        <?php echo $payes . " payés maintenant sur les " . $rowcount2 . " joueurs" ?>
                                    </div>
                                    <?php }
                                            ;
                                            $sql2 = mysqli_query($con, "SELECT * FROM `activite` WHERE (`id-activite` = $id  )");
                                            $res2 = mysqli_fetch_array($sql2);
                                            $buyin = $res2["buyin"];
                                            $jetons = $res2["jetons"];
                                            $pot = 0;
                                            $nbr = 0;
                                            $nba = 0;
                                            $req3 = mysqli_query($con, "SELECT * FROM `participation` WHERE (`id-activite` = $id AND `option` NOT LIKE 'Annule') ");
                                            while ($res3 = mysqli_fetch_array($req3)) {
                                                $pot = $pot + (((int) ($res3["recave"]) + (int) ($res3["addon"])));
                                                $nbr = $nbr + $res3["recave"];
                                                $nba = $nba + $res3["addon"];
                                            }
                                            ;
                                            $tot = $pot + $rowcount2;
                                            $final = $tot * $buyin;
                                            if ($payes == 2) {
                                                $p2 = $final * $r2;
                                                $p2 = $p2 / 10;
                                                $p2 = round($p2, 0);
                                                $p2 = $p2 * 10;
                                                $p1 = $final - $p2;
                                                ?>
                                    <div class='info4-content '>
                                        <?php echo "Pot total : " . $final . "€ soit : " . "P1=" . $p1 . "€, P2=" . $p2 . "€" ?>
                                    </div>
                                    <?php
                                            }
                                            ;
                                            if ($payes == 3) {
                                                $p3 = $final * $r3;
                                                $p3 = $p3 / 10;
                                                $p3 = round($p3, 0);
                                                $p3 = $p3 * 10;
                                                $p2 = $final * $r2;
                                                $p2 = $p2 / 10;
                                                $p2 = round($p2, 0);
                                                $p2 = $p2 * 10;
                                                $p1 = $final - $p3 - $p2;
                                                ?>
                                    <div class='info4-content '>
                                        <?php echo "Pot total : " . $final . "€ soit : " . "P1=" . $p1 . "€, P2=" . $p2 . "€, P3=" . $p3 . "€" ?>
                                    </div>
                                    <?php
                                            }
                                            ;
                                            if ($payes == 4) {
                                                $p4 = $final * $r4;
                                                $p4 = $p4 / 10;
                                                $p4 = round($p4, 0);
                                                $p4 = $p4 * 10;
                                                $p3 = $final * $r3;
                                                $p3 = $p3 / 10;
                                                $p3 = round($p3, 0);
                                                $p3 = $p3 * 10;
                                                $p2 = $final * $r2;
                                                $p2 = $p2 / 10;
                                                $p2 = round($p2, 0);
                                                $p2 = $p2 * 10;
                                                $p1 = $final - $p4 - $p3 - $p2;
                                                ?>
                                    <div class='info4-content '>
                                        <?php echo "Pot total : " . $final . "€ soit : " . "P1=" . $p1 . "€, P2=" . $p2 . "€, P3=" . $p3 . "€, P4=" . $p4 . "€" ?>
                                    </div>
                                    <?php
                                            }
                                            ;
                                            if ($payes == 5) {
                                                $p5 = $final * $r5;
                                                $p5 = $p5 / 10;
                                                $p5 = round($p5, 0);
                                                $p5 = $p5 * 10;
                                                $p4 = $final * $r4;
                                                $p4 = $p4 / 10;
                                                $p4 = round($p4, 0);
                                                $p4 = $p4 * 10;
                                                $p3 = $final * $r3;
                                                $p3 = $p3 / 10;
                                                $p3 = round($p3, 0);
                                                $p3 = $p3 * 10;
                                                $p2 = $final * $r2;
                                                $p2 = $p2 / 10;
                                                $p2 = round($p2, 0);
                                                $p2 = $p2 * 10;
                                                $p1 = $final - $p5 - $p4 - $p3 - $p2;
                                                ?>
                                    <div class='info4-content '>
                                        <?php echo "Pot total : " . $final . "€ soit : " . "P1=" . $p1 . "€, P2=" . $p2 . "€, P3=" . $p3 . "€, P4=" . $p4 . "€, P5=" . $p5 . "€" ?>
                                    </div>
                                    <?php
                                            }
                                            ;
                                            $enjeu = ($rowcount2 - $rowcounteli);
                                            if ($enjeu == 0)
                                                $enjeu = 1;
                                            else
                                                $enjeu = $rowcount2 - $rowcounteli;
                                            ?>

                                    <div class='info5-content '>
                                        <?php echo "Stack Moyen : " . ($jetons * ($rowcount2 + $nbr + $nba)) / ($enjeu) . " sur " . ($jetons * ($rowcount2 + $nbr + $nba)) . " = (" . $rowcount2 . "B ) + " . " (" . $nbr . "R ) + " . " (" . $nba . "A )" ?>
                                    </div>
                                    <?php } ?>
                                </div>
                                <div id="inscritsE">
                                    <div class="row">
                                        <div class="col-md-12">
                                            <div class="container-fluid container-fullw bg-white">
                                                <div class="row">
                                                    <div class="col-md-12">
                                                        <div class="row margin-top-30">
                                                            <div class="col-lg-8 col-md-12">
                                                                <div class="panel panel-wwhite">
                                                                    <div class="panel-body">
                                                                        <div id="layoutSidenav_content">
                                                                            <main>
                                                                                <div class="container-fluid px-4">
                                                                                    <ol class="breadcrumb mb-4">
                                                                                        <li class="breadcrumb-item">
                                                                                            <a
                                                                                                href="liste-membres.php">Membres</a>
                                                                                        </li>
                                                                                        <li
                                                                                            class="breadcrumb-item active">
                                                                                            <a
                                                                                                href="sieges.php?&ac=<?php echo $id ?>&sou=/panel/voir-activite.php">Inscrits</a>
                                                                                        </li>
                                                                                    </ol>
                                                                                    <div class="card mb-4">
                                                                                        <div class="card-body">
                                                                                            <table id="example"
                                                                                                class="display"
                                                                                                style="width:100% ;font-size:14px;color:black">
                                                                                                <thead>
                                                                                                    <tr>
                                                                                                        <th>classt
                                                                                                        </th>
                                                                                                        <th>Pseudo
                                                                                                        </th>
                                                                                                        <th>Statut
                                                                                                        </th>
                                                                                                        <th>Table
                                                                                                        </th>
                                                                                                        <th>Siege
                                                                                                        </th>
                                                                                                        <th>Bounty
                                                                                                        </th>
                                                                                                        <th>Recaves
                                                                                                        </th>
                                                                                                        <th>Addon
                                                                                                        </th>
                                                                                                        <th>Infos
                                                                                                        </th>
                                                                                                    </tr>
                                                                                                </thead>
                                                                                                <tbody>
                                                                                                    <?php $id = intval($_GET['uid']); ?>
                                                                                                    <?php $ret = mysqli_query($con, "SELECT * FROM `participation` WHERE `id-activite` = '$id' ");

                                                                                                    $cnt = 1;
                                                                                                    while ($row = mysqli_fetch_array($ret)) { ?>
                                                                                                    <?php
                                                                                                        $id2 = $row['id-membre'];
                                                                                                        $sql2 = mysqli_query($con, "SELECT * FROM `membres` WHERE `id-membre` = '$id2' ORDER BY 'classement' ASC");
                                                                                                        while ($row2 = mysqli_fetch_array($sql2)) { ?>
                                                                                                    <tr>
                                                                                                        <td>
                                                                                                            <?php echo $row['classement']; ?>
                                                                                                        </td>
                                                                                                        <td>
                                                                                                            <a
                                                                                                                href="voir-membre.php?id=<?php echo $row['id-membre']; ?>"><?php echo $row2['pseudo']; ?></a>
                                                                                                        </td>
                                                                                                        <td>
                                                                                                            <?php echo $row['option']; ?>
                                                                                                        </td>
                                                                                                        <td>
                                                                                                            <?php echo $row['id-table']; ?>
                                                                                                        </td>
                                                                                                        <td>
                                                                                                            <?php echo $row['id-siege']; ?>
                                                                                                        </td>
                                                                                                        <td>
                                                                                                            <?php echo $row['bounty']; ?>
                                                                                                        </td>
                                                                                                        <td>
                                                                                                            <?php echo $row['recave']; ?>
                                                                                                        </td>
                                                                                                        <td>
                                                                                                            <?php echo $row['addon']; ?>
                                                                                                        </td>
                                                                                                        <?php } ?>

                                                                                                        <td>
                                                                                                            <a href="voir-participation.php?id=<?php echo $row['id-participation']; ?>"
                                                                                                                tooltip="Edition"><i
                                                                                                                    class="fa fa-pencil"></i></a>
                                                                                                            <i
                                                                                                                class="fas fa-edit"></i></a>
                                                                                                        </td>

                                                                                                    </tr>
                                                                                                    <?php $cnt = $cnt + 1;
                                                                                                    } ?>
                                                                                                </tbody>
                                                                                            </table>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                            </main>
                                                                        </div>
                                                                        <form method="post">
                                                                            <table>
                                                                                <tr>
                                                                                    <td colspan="3">
                                                                                        <select name="lois"
                                                                                            class="form-control"
                                                                                            required="true">
                                                                                            <option value="lois">-
                                                                                                Participant à
                                                                                                Ajouter
                                                                                                manuellement -
                                                                                            </option>
                                                                                            <?php $ret2 = mysqli_query($con, "select * from membres ORDER BY `pseudo` ASC");
                                                                                            while ($row2 = mysqli_fetch_array($ret2)) {
                                                                                                ?>
                                                                                            <option
                                                                                                value="<?php echo htmlentities($row2['id-membre']); ?>">
                                                                                                <?php echo htmlentities($row2['pseudo']); ?>
                                                                                            </option>
                                                                                            <?php } ?>
                                                                                        </select>
                                                                                    </td>

                                                                                    <td style="display:yes" ;
                                                                                        colspan="2">
                                                                                        <select name="activi"
                                                                                            value="activi"
                                                                                            class="form-control"
                                                                                            required="false">
                                                                                            <option
                                                                                                value="<?php echo htmlentities($id); ?>">
                                                                                                <?php echo htmlentities($id); ?>
                                                                                            </option>
                                                                                        </select>
                                                                                    </td>
                                                                                    <td>
                                                                                        <button type="submit"
                                                                                            name="submitinsmanu"
                                                                                            id="submitinsmanu"
                                                                                            class="btn btn-o btn-primary">
                                                                                            Ajout
                                                                                        </button>
                                                                                    </td>
                                                                                </tr>
                                                                            </table>
                                                                        </form>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- end: BASIC EXAMPLE -->
                    <!-- end: SELECT BOXES -->
                </div>
            </div>
        </div>
    </div>
    <!-- start: FOOTER -->
    <?php include ('include/footer.php'); ?>
    <!-- end: FOOTER -->
    <!-- start: SETTINGS -->
    <?php include ('include/setting.php'); ?>
    <!-- end: SETTINGS -->
    </div>
    <!-- start: MAIN JAVASCRIPTS -->
    <script src="vendor/jquery/jquery.min.js"></script>
    <script src="vendor/bootstrap/js/bootstrap.min.js"></script>
    <script src="vendor/modernizr/modernizr.js"></script>
    <script src="vendor/jquery-cookie/jquery.cookie.js"></script>
    <script src="vendor/perfect-scrollbar/perfect-scrollbar.min.js"></script>
    <script src="vendor/switchery/switchery.min.js"></script>
    <!-- <script src="https://code.jquery.com/jquery-3.7.0.js"></script> -->
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <!-- end: MAIN JAVASCRIPTS -->
    <!-- start: JAVASCRIPTS REQUIRED FOR THIS PAGE ONLY -->
    <script src="vendor/maskedinput/jquery.maskedinput.min.js"></script>
    <script src="vendor/bootstrap-touchspin/jquery.bootstrap-touchspin.min.js"></script>
    <script src="vendor/autosize/autosize.min.js"></script>
    <script src="vendor/selectFx/classie.js"></script>
    <script src="vendor/selectFx/selectFx.js"></script>
    <script src="vendor/select2/select2.min.js"></script>
    <script src="vendor/bootstrap-datepicker/bootstrap-datepicker.min.js"></script>
    <script src="vendor/bootstrap-timepicker/bootstrap-timepicker.min.js"></script>
    <!-- end: JAVASCRIPTS REQUIRED FOR THIS PAGE ONLY -->
    <!-- start: CLIP-TWO JAVASCRIPTS -->
    <script src="assets/js/main.js"></script>
    <!-- start: JavaScript Event Handlers for this page -->
    <script src="assets/js/form-elements.js"></script>
    <script>
    jQuery(document).ready(function() {
        Main.init();
        FormElements.init();
    });
    </script>
    <!-- end: JavaScript Event Handlers for this page -->
    <!-- end: CLIP-TWO JAVASCRIPTS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.0/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous">
    </script>
    <script src="../js/scripts.js"></script>
    <!-- <script src="https://cdn.jsdelivr.net/npm/simple-datatables@latest" crossorigin="anonymous"></script>
            <script src="../js/datatables-simple-demo.js"></script> -->
    <script type="text/javascript" language="javascript">
    function afficher1(id) {
        console.log('afficher1 called for', id);
        var leCalque = document.getElementById(id);
        var leCalqueE = document.getElementById(id + "E");

        // safe guards
        ['infosE','inscritsE','t1E'].forEach(function(el){var e=document.getElementById(el); if(e) e.className = 'rubrique bgImg';});
        ['infos','inscrits','t1'].forEach(function(el){var e=document.getElementById(el); if(e) e.className = 'btnnav';});

        if (leCalqueE) leCalqueE.className += " montrer";
        if (leCalque) leCalque.className = "btnnavA";
    }

    function afficher2(id) {
        console.log('afficher2 called for', id);
        var leCalque = document.getElementById(id);
        var leCalqueE = document.getElementById(id + "E");

        ['infosE','inscritsE','t1E','t2E'].forEach(function(el){var e=document.getElementById(el); if(e) e.className = 'rubrique bgImg';});
        ['infos','inscrits','t1','t2'].forEach(function(el){var e=document.getElementById(el); if(e) e.className = 'btnnav';});

        if (leCalqueE) leCalqueE.className += " montrer";
        if (leCalque) leCalque.className = "btnnavA";
    }

    function afficher3(id) {
        console.log('afficher3 called for', id);
        var leCalque = document.getElementById(id);
        var leCalqueE = document.getElementById(id + "E");

        ['infosE','inscritsE','t1E','t2E','t3E'].forEach(function(el){var e=document.getElementById(el); if(e) e.className = 'rubrique bgImg';});
        ['infos','inscrits','t1','t2','t3'].forEach(function(el){var e=document.getElementById(el); if(e) e.className = 'btnnav';});

        if (leCalqueE) leCalqueE.className += " montrer";
        if (leCalque) leCalque.className = "btnnavA";
    }

    function afficher4(id) {
        console.log('afficher4 called for', id);
        var leCalque = document.getElementById(id);
        var leCalqueE = document.getElementById(id + "E");

        ['infosE','t2E','inscritsE','t1E','t3E','t4E'].forEach(function(el){var e=document.getElementById(el); if(e) e.className = 'rubrique bgImg';});
        ['infos','t2','inscrits','t1','t3','t4'].forEach(function(el){var e=document.getElementById(el); if(e) e.className = 'btnnav';});

        if (leCalqueE) leCalqueE.className += " montrer";
        if (leCalque) leCalque.className = "btnnavA";
    }

    // attach debug listeners to tab buttons
    (function() {
        try {
            var menu = document.getElementById('bMenu');
            if (menu) {
                var items = menu.querySelectorAll('.btnnav');
                items.forEach(function(it){
                    it.addEventListener('click', function(e){
                        console.log('btnnav clicked', it.id);
                    });
                    it.addEventListener('mouseover', function(e){
                        //console.log('btnnav mouseover', it.id);
                    });
                });
            }
        } catch (e) { console.error(e); }
    })();
    </script>
    <?php
        // $onglet = 'inf';
        $onglet = $_GET['onglet'];
        if ($onglet == 'inf') {
            ?>
    <script type="text/javascript" language="javascript">
    afficher('infos');
    </script>;
    <?php
        }
        ;
        if ($onglet == 'ins') {
            ?>
    <script type="text/javascript" language="javascript">
    afficher('inscrits');
    </script>;
    <?php
        }
        ;
        if ($onglet == '1') {
            ?>
    <script type="text/javascript" language="javascript">
    afficher('t1');
    </script>;
    <?php
        }
        ;
        if ($onglet == '2') {
            ?>
    <script type="text/javascript" language="javascript">
    afficher('t2');
    </script>;
    <?php
        }
        ;
        if ($onglet == '3') {
            ?>
    <script type="text/javascript" language="javascript">
    afficher('t3');
    </script>;
    <?php
        }
        ;
        if ($onglet == '4') {
            ?>
    <script type="text/javascript" language="javascript">
    afficher('t4');
    </script>;
    <?php
        }
        ;
        if ($onglet == '' and $nbt == "1") {
            ?>
    <script type="text/javascript" language="javascript">
    afficher1('t1');
    </script>;
    <?php
        }
        ;

        if ($onglet == '' and $nbt == "2") {
            ?>
    <script type="text/javascript" language="javascript">
    afficher2('t1');
    </script>;
    <?php
        }
        ;

        if ($onglet == '' and $nbt == "3") {
            ?>
    <script type="text/javascript" language="javascript">
    afficher3('t1');
    </script>;
    <?php
        }
        ;
        if ($onglet == '' and $nbt == "4") {
            ?>
    <script type="text/javascript" language="javascript">
    afficher4('t1');
    </script>;
    <?php
        }
        ;
        ?>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        try {
            var rubriques = document.querySelectorAll('.rubrique');
            console.log('DEBUG: rubrique count', rubriques.length);
            rubriques.forEach(function(r){
                console.log('rubrique', r.id, 'class=', r.className, 'computed display=', window.getComputedStyle(r).display);
            });
            var btns = document.querySelectorAll('#bMenu .btnnav');
            console.log('DEBUG: btnnav count (inside first #bMenu)', btns.length);
            btns.forEach(function(b){ console.log('btn', b.id, 'text=', b.textContent.trim(), 'class=', b.className); });
            // check for any element with tabindex= -1 or pointer-events none covering buttons
            var elems = document.querySelectorAll('body *');
            var coverers = [];
            elems.forEach(function(el){
                var cs = window.getComputedStyle(el);
                if (cs.pointerEvents === 'none') return;
                if (cs.position === 'absolute' || cs.position === 'fixed' ) {
                    var z = parseInt(cs.zIndex) || 0;
                    if (z > 0) coverers.push({id: el.id || el.tagName, z: z, display: cs.display});
                }
            });
            console.log('DEBUG: potential coverers (abs/fixed z>0) sample', coverers.slice(0,10));

            // Fallback: ensure menu visible and one tab active
            try {
                var menuEl = document.getElementById('bMenu');
                if (menuEl && window.getComputedStyle(menuEl).display === 'none') {
                    menuEl.style.display = 'block';
                    console.log('DEBUG: forced bMenu visible');
                }
                // hide all rubriques
                rubriques.forEach(function(r){ r.classList.remove('montrer'); });
                // if no active button, activate default based on server-side nb tables
                var active = document.querySelector('#bMenu .btnnavA');
                if (!active) {
                    console.log('DEBUG: no active btnnavA found; selecting default');
                    // default will be selected server-side; fallback to afficher1
                    if (typeof afficher1 === 'function' && typeof afficher2 === 'function') {
                        var nbt = <?php echo json_encode($nbt); ?>;
                        if (nbt == '2') { if (typeof afficher2 === 'function') afficher2('t1'); else console.log('afficher2 missing'); }
                        else if (nbt == '3') { if (typeof afficher3 === 'function') afficher3('t1'); else console.log('afficher3 missing'); }
                        else if (nbt == '4') { if (typeof afficher4 === 'function') afficher4('t1'); else console.log('afficher4 missing'); }
                        else { if (typeof afficher1 === 'function') afficher1('t1'); else console.log('afficher1 missing'); }
                    } else {
                        console.log('DEBUG: afficher functions not yet defined');
                    }
                }
            } catch (e) { console.error('fallback ERR', e); }

        } catch (e) { console.error('DEBUG ERR', e); }
    });
    </script>

    <style>
    /* Conservative fixes to force tab menu visible and ensure only one rubrique shown */
    #bMenu { display: block !important; position: relative !important; z-index: 5000 !important; }
    #bMenu .btnnav { display: inline-block !important; visibility: visible !important; opacity: 1 !important; pointer-events: auto !important; background: rgba(255,255,255,0.95) !important; color: #333 !important; border-color: #a33639 !important; z-index: 5001 !important; }
    .rubrique { display: none !important; }
    .rubrique.montrer { display: block !important; }
    </style>

    <script>
    (function(){
        try {
            var nbt = <?php echo json_encode($nbt); ?>;
            // ensure all rubriques are hidden by default
            document.querySelectorAll('.rubrique').forEach(function(r){ r.classList.remove('montrer'); });

            // expose and attach click handlers to menu buttons
            var menu = document.getElementById('bMenu');
            if (menu) {
                var btns = menu.querySelectorAll('.btnnav');
                btns.forEach(function(b){
                    b.style.display = 'inline-block';
                    b.style.visibility = 'visible';
                    b.style.pointerEvents = 'auto';
                    b.addEventListener('click', function(ev){
                        // If the link has a real href (not just '#'), allow the browser to handle it
                        var href = b.getAttribute('href');
                        if (href && href.trim() !== '#' && href.trim() !== '') {
                            // let the default action (and inline onclick confirm) occur
                            return;
                        }
                        // otherwise treat as a tab click
                        ev.preventDefault();
                        var id = b.id;
                        if (nbt == '2' && typeof afficher2 === 'function') afficher2(id);
                        else if (nbt == '3' && typeof afficher3 === 'function') afficher3(id);
                        else if (nbt == '4' && typeof afficher4 === 'function') afficher4(id);
                        else if (typeof afficher1 === 'function') afficher1(id);
                    });
                });
                // activate a default tab if none active
                if (!menu.querySelector('.btnnavA')) {
                    if (nbt == '2' && typeof afficher2 === 'function') afficher2('t1');
                    else if (nbt == '3' && typeof afficher3 === 'function') afficher3('t1');
                    else if (nbt == '4' && typeof afficher4 === 'function') afficher4('t1');
                    else if (typeof afficher1 === 'function') afficher1('t1');
                }
            }
        } catch (e) { console.error('tab-fix-err', e); }
    })();
    </script>

<script>
// Ensure "Table 1" is the default active tab on page load
(function(){
    function activateTable1(){
        try{
            var nbt = (typeof window.nbt !== 'undefined' && window.nbt !== null) ? window.nbt : <?php echo json_encode($nbt); ?>;
            // add active class to the t1 button
            var menu = document.getElementById('bMenu');
            if (menu){
                var current = menu.querySelector('.btnnavA'); if (current) current.classList.remove('btnnavA');
                var t1 = menu.querySelector('#t1'); if (t1) t1.classList.add('btnnavA');
            }
            // call the appropriate afficher function
            if (nbt == '2' && typeof afficher2 === 'function') afficher2('t1');
            else if (nbt == '3' && typeof afficher3 === 'function') afficher3('t1');
            else if (nbt == '4' && typeof afficher4 === 'function') afficher4('t1');
            else if (typeof afficher1 === 'function') afficher1('t1');
        }catch(e){ console.warn('activateTable1 failed', e); }
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', activateTable1); else activateTable1();
})();
</script>

</body>

</html>
<?php } ?>