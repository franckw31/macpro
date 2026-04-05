<?php
session_start();
if ($_SESSION["act"] > 0) {
    $id = $_SESSION["act"];
} else
    $id = 1;
// $finpause=strtotime(date("Y-m-d H:i:s"));
include_once ('include/config.php');
$req0 = mysqli_query($con, "SELECT * FROM `blindes-live` WHERE (`id-activite` = $id AND `ordre` = 24)");
$row0 = mysqli_fetch_array($req0);
$commence = $row0["fin"];
$en_pause = $row0["en_pause"];
$heure_pause = $row0["heure_pause"];

$req0 = mysqli_query($con, "SELECT * FROM `blindes-live` WHERE (`id-activite` = '$id' AND `nom` LIKE 'Pause')");
$row0 = mysqli_fetch_array($req0);

$ordre = $row0["ordre"];
$ordre = $ordre - 1;

$req2 = mysqli_query($con, "SELECT * FROM `blindes-live` WHERE (`id-activite` = '$id' AND `ordre` = 24)");
$row2 = mysqli_fetch_array($req2);

$finestim = $row2["fin"];
$tfinestim = strtotime($finestim);
$tfinestim = date("H:i:s", $tfinestim);
date_default_timezone_set('Arctic/Longyearbyen');
$departsecondes = strtotime(date("Y-m-d H:i:s"));
// $departsecondes=strtotime(date($heure_pause));
// $duree = strtotime($duree);
// date_add($finpause, date_interval_create_from_date_string($duree.'minutes'));
$arriveesecondes = strtotime($finestim);
// $duree=((int)$duree)*60;
// $arriveesecondesf=$arriveesecondes-($duree*60);

$ecartsecondes = $arriveesecondes - $departsecondes;
// $ecartsecondesf=$arriveesecondesf-$departsecondes;
if ($ecartsecondes < 1) {
    echo " Terminé ";
    $_SESSION["estimfin"] = "1";
    // $_SESSION["bl"]=$_SESSION["bl"]+1;    
} else {
    if ($ecartsecondes < 3600000) {
        echo "Fin Estimé dans : " . gmdate("h", (int) $ecartsecondes) . " H et " . gmdate("i", $ecartsecondes) . " M (" . $tfinestim . ")";
    }
}