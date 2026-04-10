
        <?php
            session_start();
            error_reporting(0);
            include('include/config.php');
                {
                // $id_participation = intval($_GET['id']); // get value
                // $id_activite = intval($_GET['ac']);
                // echo $id_activite;
                // $source= $_GET['source'].$id_activite;
                
                $req = mysqli_query($con, "SELECT * FROM `participation` ");
                
                while ($res = mysqli_fetch_array($req)) 
                    { 
                        $id=$res["id-membre"]; 
                        $id_participation=$res["id-participation"];
                        echo "(".$id.")";
                        $req2=mysqli_query($con,"SELECT * FROM `membres` WHERE `id-membre` = $id");
                        $res2=mysqli_fetch_array($req2);
                        $nom=$res2["pseudo"];
                        echo "{".$nom."}";
                        $modif = mysqli_query($con, "UPDATE `participation` SET `nom-membre` = '$nom' WHERE `id-participation` = '$id_participation'");
                        
                    };
                };
        ?>