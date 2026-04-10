<?php
            include_once('include/config.php');
            $ret = mysqli_query($con, "SELECT * FROM `activite` WHERE 1 ");
            while ($row = mysqli_fetch_array($ret)) 
                { 
                $nb_tables = $row["nb-tables"];
                $pointeur = $row["id-activite"];
                $pointeur_position = 0;
                $ret2 = mysqli_query($con, "SELECT * FROM `participation` WHERE (`id-activite` = '$pointeur' AND `option` LIKE 'Inscrit') OR (`id-activite` = '$pointeur' AND `option` LIKE 'Option')
                 OR (`id-activite` = '$pointeur' AND `option` LIKE 'Reservation') OR (`id-activite` = '$pointeur' AND `option` LIKE 'Inscrit') OR (`id-activite` = '$pointeur' AND `option` LIKE 'Inscrit') ORDER BY RAND()" ) ;
                while ($row2 = mysqli_fetch_array($ret2)) 
                    { 
                    $id = $row2['id-participation'];
                    $pointeur_position = $pointeur_position + 1;
                    $modif = mysqli_query($con, "UPDATE `participation` SET `position` = '$pointeur_position' WHERE `id-participation` = '$id'");
                } ;
            };
            
            // echo "Ok-";
            $sql2 = mysqli_query($con, "SELECT * FROM `activite` ");
            while ($res2 = mysqli_fetch_array($sql2)) 
                { 
                $activi = $res2['id-activite'];
                $ret = mysqli_query($con, "SELECT * FROM `activite` WHERE `id-activite` = '$activi' ");
                while ($row = mysqli_fetch_array($ret)) 
                    { 
                        $modif = mysqli_query($con, "UPDATE `participation` SET `id-table` = '' , `id-siege` = ''  WHERE `id-activite` = '$activi'");
                        $nb_tables = $row["nb-tables"]; 
                    };   
                // $nb_tables=2;
                $latable = "";
                
                $pointeur = $res2["id-activite"];
                $sql3= mysqli_query($con, "SELECT * FROM `participation` WHERE (`id-activite` = '$pointeur' AND `option` NOT LIKE  'Elimine') " ) ;
                $nb = mysqli_num_rows($sql3);
                $t1max=$nb/$nb_tables;
                $t1max=(round($t1max, 0));
                $lesiegetable1="";
                $t2max=$t1max;$lesiegetable2="";
                $t3max=$t1max;$lesiegetable3="";
                echo "----------------".$t1max.'+'.$nb_tables."-------------------------";
                while ($res3 = mysqli_fetch_array($sql3)) 
                    { 
                        $id = $res3["id-participation"];
                        if ($res3['position'] <= $t1max) 
                          {
                            $latable = "1";
                            (int)$lesiegetable2 = "";
                            (int)$lesiegetable1 = $res3['position'];
                            $affect_siege = mysqli_query($con, "UPDATE `participation` SET `id-siege` = $lesiegetable1 WHERE `id-participation` = $id ");
                            $affect_table = mysqli_query($con, "UPDATE `participation` SET `id-table` = $latable WHERE `id-participation` = $id ");
                            
                          }
                        else
                          {
                            if ($res3['position'] <= ((int)$t2max + (int)$t1max) ) 
                            {
                            $latable = "2";
                            (int)$lesiegetable1 = "";
                            (int)$lesiegetable2 = ($res3['position']-(int)$t1max);
                            $affect_siege = mysqli_query($con, "UPDATE `participation` SET `id-siege` = $lesiegetable2 WHERE `id-participation` = $id ");
                            $affect_table = mysqli_query($con, "UPDATE `participation` SET `id-table` = $latable WHERE `id-participation` = $id ");
                            }
                            else
                            {
                            };
                          };
                        echo $res3["id-activite"]."-".$res3["id-membre"]."-".$res3["position"]."/"."{table=".$latable."-siege=".$lesiegetable1.$lesiegetable2."}";
                    };
                    
                    
                    echo ":..................:";
                };
        ?>
        <!-- INSERT INTO `activite` (SELECT NULL, `id-structure-buyin`, `id-membre`, `titre-activite`, `date_depart`, `heure_depart`, `ville`, `rue`, `lng`, `lat`, `icon`, `ico-siz`, `photo`, `lien`, `lien-id`, `lien-texte`, `lien-texte-fin`, `places`, `reserves`, `options`, `libre`, `commentaire`, `buyin`, `rake`, `bounty`, `jetons`, `recave`, `addon`, `ante`, `bonus`, `nb-tables`, `taille-table1`, `id-table1`, `taille-table2`, `id-table2`, `taille-table3`, `id-table3`, `taille-table4`, `id-table4`, `taille-table5`, `id-table5`, `taille-table6`, `id-table6` FROM `activite` WHERE `id-activite` = 30) -->