<?php
include('include/config.php');
//define('DB_SERVER','localhost');
//define('DB_USER','root');
//define('DB_PASS' ,'Kookies7*');
//define('DB_NAME', 'dbs9616600');
//$con = mysqli_connect(DB_SERVER,DB_USER,DB_PASS,DB_NAME);
//include('/panel/include/config.php');
date_default_timezone_set('Europe/Rome');
// $id_blinde = intval($_GET['bli']); // get value
$id_activite = "";
$id_activite = intval($_GET['act']);
$minutes = 0;
$minutes = intval($_GET['min']);
$source = "/index.php";
$source = $_GET['sou'];
$sql = mysqli_query($con, "SELECT * FROM `blindes-live` WHERE `ordre` = '1' AND `id-activite` =  '$id_activite' ");
$row =mysqli_fetch_array($sql);$debpause = $row["heure_pause"];$en_pause = $row["en_pause"];
// while($res = mysqli_fetch_array($sql))
// {    
//     $structure = $res['id-structure'];
//     $id_blinde = $res['ordre'];
//     $blinde = $res['fin']; 
//     $dt = date_create($blinde);
//     date_add($dt, date_interval_create_from_date_string($minutes.'minutes'));
//     $fin = date_format($dt, 'Y-m-d H:i:s');
//     $modif = mysqli_query($con, "UPDATE `blindes-live` SET `fin` = '$fin' WHERE `ordre` ='$id_blinde' AND `id-activite` =  '$id_activite'");
// };
// echo "Ok";
$actu=date("Y-m-d H:i:s");
// $cptactu=strtotime($actu);
// $cptdeb=strtotime($debpause);
$delta=strtotime($actu)-strtotime($debpause)+0;
// $dt = date($delta);
// echo strtotime($actu)."-".strtotime($debpause)."-";
echo $delta;
if ($en_pause == "1") {
    $modif = mysqli_query($con, "UPDATE `blindes-live` SET `heure_depause` = '$actu', `delta` = '$delta' , `en_pause` = '0' WHERE `ordre` = '1' AND `id-activite` =  '$id_activite'");

// $sql = mysqli_query($con, "SELECT * FROM `blindes-live` WHERE `id-activite` =  '$id_activite' ");
$sql = mysqli_query($con, "SELECT * FROM `blindes-live` WHERE `id-activite` =  '$id_activite' ");
while($res = mysqli_fetch_array($sql))
{    
    $id_blinde = $res['ordre'];
    $blinde = $res['fin']; 
    $dt = date_create($blinde);

    date_add($dt, date_interval_create_from_date_string($delta." seconds"));
    $fin = date_format($dt, 'Y-m-d H:i:s');
    $modif = mysqli_query($con, "UPDATE `blindes-live` SET `fin` = '$fin' WHERE `ordre` ='$id_blinde' AND `id-activite` =  '$id_activite'");
};
};
?>
<script type="text/javascript">window.location.replace("<?php echo $source.$id_activite; ?>")</script>
