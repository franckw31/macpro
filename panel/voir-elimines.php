
        <?php
            session_start();
            error_reporting(0);
            include('include/config.php');
                // if (strlen($_SESSION['id'] == 0)) {
                //     header('location:logout.php');
                //     exit ;   
                // }
                // else
                {
                $id_participation = intval($_GET['id']); // get value
                $id_activite = intval($_GET['ac']);
                echo $id_activite;
                $source= $_GET['source'].$id_activite;
                
                $req = mysqli_query($con, "SELECT * FROM `participation` WHERE `id-activite` = $id_activite AND `option` LIKE 'Elimine' ORDER BY `ds` DESC");
                
                while ($res = mysqli_fetch_array($req)) 
                    { 
                        // $modif = mysqli_query($con, "UPDATE `participation` SET `option` = 'Elimine', `id-siege` = '0',`id-table` = '0' WHERE `id-participation` = '$id_participation'");
                        $eli1=$res["id-membre"];
                        $res2=mysqli_query($con,"SELECT * FROM `membres` WHERE (`id-membre` = $eli1)");
                        $row2=mysqli_fetch_array($res2);$nom1=$row2["pseudo"];
                    };
                };
                 echo $nom1; 
        ?>
        <!-- <script type="text/javascript">window.location.replace("<?php echo $source.$id_activite; ?>");</script> ;  -->
        

        <!-- $res=mysqli_query($con,"SELECT * FROM `blindes` WHERE (`id-activite` = $id AND `ordre` = 1)");
$row=mysqli_fetch_array($res);
$fin=$row["fin"];$nom=$row["nom"];$ordre=$row["ordre"]; -->