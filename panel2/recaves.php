
        <?php
            session_start();
            error_reporting(0);
            include('include/config.php');
                $id_participation = intval($_GET['par']); // get value
                $id_activite = intval($_GET['act']);
                $source= $_GET['sou'];
                $req = mysqli_query($con, "SELECT * FROM `participation` WHERE `id-participation` = '$id_participation' ");
                while ($res = mysqli_fetch_array($req)) 
                    { 
                        $recave=(int)$res["recave"]+1;
                        $modif = mysqli_query($con, "UPDATE `participation` SET `recave` = $recave WHERE `id-participation` = '$id_participation'");
                    };            
                 echo ".".$source."Ok".$id_activite; 
        ?>
        <script type="text/javascript">window.location.replace("<?php echo $source.$id_activite; ?>");</script> ; 
        