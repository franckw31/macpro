<?php
    define('DB_SERVER','db5011397709.hosting-data.io');
    define('DB_USER','dbu5472475');
    define('DB_PASS' ,'Kookies7*');
    define('DB_NAME', 'dbs9616600');
    $con = mysqli_connect(DB_SERVER,DB_USER,DB_PASS,DB_NAME);
    
     $qui = $_GET['qui'];
     $quoi = $_GET['quoi'];
    // $sql2 = mysqli_query($con, "INSERT INTO `participation` (`id-participation`, `id-membre`, `id-membre-vaiqueur`, `id-activite`, `id-siege`, `id-table`, `id-challenge`, `option`, `ordre`, `valide`, `commentaire`, `classement`, `points`, `gain`, `ds`, `ip-ins`, `ip-mod`, `ip-sup`) VALUES (NULL, '$qui', '', '$quoi', '', '', '', 'Réservation', '', 'Actif', NULL, '0', '0', '0', CURRENT_TIMESTAMP, '', '', '')");
    
    $sql = mysqli_query($con, "SELECT * FROM `participation` WHERE `id-membre` = '$qui' AND `id-activite` = '$quoi' ");
        // Return the number of rows in result set
        $rowcount = mysqli_num_rows($sql);

        if ($rowcount == '0') {
           
            $sql3 = mysqli_query($con, "SELECT * FROM `participation` WHERE (`id-activite` = '$quoi' AND `option` LIKE 'Reservation' OR `id-activite` = '$quoi' AND `option` LIKE 'Inscrit' )");
            $ordre = mysqli_num_rows($sql3);

            $intordre = (int) $ordre;
            $intordre = $intordre + 1;
            $ordre = (string) $intordre;

            // Nouveau participant : classement = 0 (non classé au départ)
            $sql2 = mysqli_query($con, "INSERT INTO `participation` ( `id-membre`, `id-membre-vainqueur`, `id-activite`, `id-siege`, `id-table`, `id-challenge`, `option`, `ordre`, `valide`, `commentaire`, `classement`, `points`, `gain`, `ds`, `ip-ins`, `ip-mod`, `ip-sup`) VALUES ('$qui', '', '$quoi', '', '', '', 'Reservation', '$ordre', 'Actif', NULL, '0', '0', '0', CURRENT_TIMESTAMP, '', '', '')");        
            echo "Inscription Prise en compte, Merci en :". $ordre;  

        }
        else 
        { echo "Vous etes deja inscrit !";};
?>