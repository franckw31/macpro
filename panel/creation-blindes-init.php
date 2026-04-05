<?php
session_start();
error_reporting(0);
$_SESSION["cptblinde"] = '1';
include ('include/config.php');
$activite = intval($_GET['act']); // get value
$source = intval($_GET['sou']); // get value
$zero = intval($_GET['zero']); // get value
$req1 = mysqli_query($con, "SELECT  * FROM `activite` WHERE `id-activite` = '$activite' ");
$nb_lignes = mysqli_num_rows($req1);
echo " nbr activ = (" . $nb_lignes . ")";
date_default_timezone_set('Arctic/Longyearbyen');
$heure = date("Y-m-d H:i:s", time());
// $duree = date("H:i:s", time());
echo "(" . $heure . "-" . $duree . ")";
$modif = mysqli_query($con, "INSERT INTO `blindes-live` (`id-activite`, `minutes`, `ordre`,`fin` ) VALUES ('$activite', '5', '1', '$heure' )");
?>
<script language="JavaScript" type="text/javascript">
    window.location.replace("/panel/voir-activite.php?uid=<?php echo $activite ?>");
</script>';
