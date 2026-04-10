<?php
session_start();
error_reporting(0);
include('include/config.php'); 
$id_participation = intval($_GET['id']); // get value
$id_activite = intval($_GET['ac']);
$source= $_GET['source'];
$req = mysqli_query($con, "SELECT * FROM `participation` WHERE `id-participation` = '$id_participation' ");            
while ($res = mysqli_fetch_array($req)) 
    { 
    $siegelibre=$res['id-siege'];$tablelibre=$res['id-table'];
    $modif = mysqli_query($con, "UPDATE `participation` SET `option` = 'Elimine', `id-siege` = '0',`id-table` = '0' WHERE `id-participation` = '$id_participation'");
    };
; 
?>
<!-- <script type="text/javascript">window.location.replace("<?php echo $source.$id_activite; ?>");</script> ;  -->
<?php
$sql0 = mysqli_query($con, "SELECT * FROM `participation` WHERE ( (`id-activite` = '$id_activite' AND `option` NOT LIKE  'Annule') AND (`id-activite` = '$id_activite' AND `option` NOT LIKE  'Elimine') ) " ) ;
$nb_joueurs = mysqli_num_rows($sql0);
$sqleli = mysqli_query($con, "SELECT * FROM `participation` WHERE (`id-activite` = '$id_activite' AND `option` LIKE  'Elimine') " ) ;
$nb_joueurseli = mysqli_num_rows($sqleli);
$sqldep = mysqli_query($con, "SELECT * FROM `participation` WHERE ( (`id-activite` = '$id_activite' AND `option` NOT LIKE  'Annule') AND (`id-activite` = '$id_activite' AND `option` NOT LIKE  'Attente') ) " ) ;
$nb_joueursdep = mysqli_num_rows($sqldep);
$classement = $nb_joueursdep - $nb_joueurseli+1;
$modif = mysqli_query($con, "UPDATE `participation` SET `classement` = '$classement' WHERE `id-participation` = '$id_participation'");
$sql = mysqli_query($con, "SELECT * FROM `activite` WHERE `id-activite` = $id_activite ");
while ($res = mysqli_fetch_array($sql)) 
    { 
    $nb_tables = $res["nb-tables"];
    };    
(int)$table = 1;
while ((int)$table <= (int)$nb_tables)
    {
    $sql2 = mysqli_query($con, "SELECT * FROM `participation` WHERE (`id-activite` = $id_activite AND `id-table` = $table) ");
    $cpttable[$table] = mysqli_num_rows($sql2);
    (int)$table = (int)$table + 1;
    };
echo $cpttable[1].$cpttable[2].$cpttable[3].$cpttable[4];
$moy=$nb_joueurs/$nb_tables;$moy = (round($moy,0));
echo "-".$moy."-";
echo "{".$siegelibre."-".$tablelibre."}";
$table = 1;
while ((int)$table <= (int)$nb_tables)
    {
    // $sql3 = mysqli_query($con, "SELECT * FROM `participation` WHERE (`id-activite` = $id_activite AND `id-table` = $table) ");
    if ($cpttable[$table] > $moy)
        {
        $tabledepart = $table;
        };
    (int)$table = (int)$table + 1;
    };
    // echo "sortie while".$id_activite.$tabledepart;   
if ($tabledepart > 0) // donc deplacement
    {
    $sql3 = mysqli_query($con, "SELECT * FROM `participation` WHERE (`id-activite` = $id_activite AND `id-table` = $tabledepart) ORDER BY `id-siege`");
    // echo "sql3 av while";
    while  ($res3 = mysqli_fetch_array($sql3))
        { 
        $id_membre = $res3['id-membre'];echo "+".$id_membre."+";
        };
    // echo $id_membre;
    $modif = mysqli_query($con, "UPDATE `participation` SET `id-table` = '$tablelibre' , `id-siege` = '$siegelibre'  WHERE (`id-activite` = $id_activite AND `id-membre` = $id_membre ) ");
    };
       
// on casse ?
// si 4t et nbj=19

echo "nb tab : ".$nb_tables;
echo "nb joueurs : ".$nb_joueurs;


    // on casse ?
    // si 3t et nbj=14
if ($nb_tables=3 AND $nb_joueurs==14) // on casse 3e table
    {
        // on cherche la table a sup (4j)
        echo "on casse 3eme table ";
        $cpt=1;
        $table_recev_scrut = 0;
        $touve = "non";
        while ((int)$cpt <= (int)$nb_tables)
        {   
            $req1 = mysqli_query($con, "SELECT * FROM `participation` WHERE (`id-activite` = $id_activite AND `id-table` = $cpt AND `id-siege` > 0) ");
            $nb_joueursatable = mysqli_num_rows($req1);
            echo "cpt : ".$cpt;
            echo "joueurs a tables : ".$nb_joueursatable;

            if ($nb_joueursatable == 4) 
            {
                echo "4 joueurs";
                $tableacasser = $cpt;
                // on cherche premier des 4 joueurs à deplacer
                $nb_joueurs_a_dep = $nb_joueursatable+1;
                while  ($res1 = mysqli_fetch_array($req1) AND $nb_joueurs_a_dep > 0)
                {
                    echo "tab a casser :".$tableacasser;
                    $id_part = $res1['id-participation']; // premier des 4 a dep
                    $trouve = "non";
                    $id_membre = $res1['id-membre']; // premier des 4 a dep
                    $modif = mysqli_query($con, "UPDATE `participation` SET `id-siege` = '0',`id-table` = '0' WHERE `id-participation` = '$id_part'"); // on libere le siege
                    echo "libere :".$id_part;
                    // on cherche 1ere table receveuse
                    $table_recev_scrut = $table_recev_scrut + 1;
                                                                                        if ($table_recev_scrut  == $tableacasser) $table_recev_scrut = $table_recev_scrut + 1;
                    if ($table_recev_scrut > $nb_tables) $table_recev_scrut = 1;
                    if ($table_recev_scrut  == $tableacasser) 
                    { 
                        if ($table_recev_scrut > $nb_tables) 
                            {$table_recev_scrut = 1;}
                        else
                            {$table_recev_scrut = $table_recev_scrut + 1;};
                    };
                    // while (($table_recev_scrut <= $nb_tables) AND $trouve = "non")
                    {
                            echo "table recev ".$table_recev_scrut;
                            // table receveuse = cpt2
                            // on cherche 1er siege libre
                            $req2 = mysqli_query($con, "SELECT * FROM `participation` WHERE (`id-activite` = $id_activite AND `id-table` = $table_recev_scrut ) ");
                            
                            while ($res2 = mysqli_fetch_array($req2))
                            {
                                echo "idsiege : ".$res2['id-siege'];                                
                                $occupe[$res2['id-siege']]=1; 
                            };
                            $place = 0;
                            while ($place<11)
                            {
                                $place = $place + 1 ;
                                if ($occupe[$place] <> "1") 
                                {
                                    $id_siegelibre=$place;
                                    $id_tablelibre=$table_recev_scrut;
                                    $place=11; // on arrete
                                };
                            };
                            // on place 1er des 4 joueurs
                            $nb_joueurs_a_dep = $nb_joueurs_a_dep - 1;
                            $modif = mysqli_query($con, "UPDATE `participation` SET `id-siege` = '$id_siegelibre',`id-table` = '$id_tablelibre' WHERE `id-participation` = '$id_part'");
                            echo "occupe :".$occupe[1]."-".$occupe[2]."-".$occupe[3]."-".$occupe[4]."-".$occupe[5]."-".$occupe[6]."-".$occupe[7]."-".$occupe[8]."-".$occupe[9]."-".$occupe[10]."+++".$id_siegelibre.'/'.$id_tablelibre."/".$id_part;
                            $trouve = "oui" ; // on cherche joueur suivant
                       
                    }
                }
            }
            $cpt = $cpt + 1;
        }; 
        $nb_tables=$nb_tables-1;
        $modif = mysqli_query($con, "UPDATE `activite` SET `nb-tables` = '$nb_tables' WHERE `id-activite` = '$id_activite'");
    };
?>
<script type="text/javascript">window.location.replace("<?php echo $source.$id_activite; ?>")</script>  