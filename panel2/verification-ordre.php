
        <?php
            include_once('include/config.php');
            $ret = mysqli_query($con, "SELECT * FROM `activite` WHERE 1 ");
            while ($row = mysqli_fetch_array($ret)) 
                { 
                $pointeur = $row["id-activite"];
                $pointeur_ordre = 0;
                $ret2 = mysqli_query($con, "SELECT * FROM `participation` WHERE (`id-activite` = '$pointeur' AND `option` LIKE 'Reservation') OR (`id-activite` = '$pointeur' AND `option` LIKE 'Option')
                 OR (`id-activite` = '$pointeur' AND `option` LIKE 'Inscrit') OR (`id-activite` = '$pointeur' AND `option` LIKE 'Confirme') OR (`id-activite` = '$pointeur' AND `option` LIKE 'Elimine') ORDER BY `ordre` ASC" ) ;
                while ($row2 = mysqli_fetch_array($ret2)) 
                    { 
                    $id = $row2['id-participation'];
                    $pointeur_ordre = $pointeur_ordre + 1;
                    $modif = mysqli_query($con, "UPDATE `participation` SET `ordre` = '$pointeur_ordre' WHERE `id-participation` = '$id'");
                } ;
            };
            echo "Ok"
        ?>
        