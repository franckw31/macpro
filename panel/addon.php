
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
                $source= $_GET['source'];
                $req = mysqli_query($con, "SELECT * FROM `participation` WHERE `id-participation` = '$id_participation' ");
                
                while ($res = mysqli_fetch_array($req)) 
                    { 
                        $addon=(int)$res["addon"]+1;
                        $modif = mysqli_query($con, "UPDATE `participation` SET `addon` = $addon WHERE `id-participation` = '$id_participation'");
                    };
                };
                // echo ".".$source."Ok"; 
        ?>
        <script type="text/javascript">window.location.replace("<?php echo $source.$id_activite; ?>");</script> ; 
        