<?php
session_start();
date_default_timezone_set('Arctic/Longyearbyen');
$departsecondes = strtotime(date("Y-m-d H:i:s"));
$pause = $_SESSION["en_pause" . $_SESSION["act"]];
// $pause = "1";
if ($pause == "1")
    $departsecondes = strtotime(date($heure_pause));

// $req0 = mysqli_query($con,"SELECT * FROM `blindes-live` WHERE (`id-activite` = '56' AND `ordre` = '1' )");
// $row0 = mysqli_fetch_array($req0);
// $commence = $row0["fin"];
// $en_pause = $row0["en_pause"];
// $heure_pause = $row0["heure_pause"];
// if ($en_pause == "1") $departsecondes=strtotime(date($heure_pause));

$arriveesecondes1 = strtotime($_SESSION["fin" . $_SESSION["bl"]]);
$nomr = $_SESSION["nom" . $_SESSION["bl"]];
$antr = $_SESSION["ante" . $_SESSION["bl"]];
if ($antr == "0") {
    $antr = "";
} else {
    $antr = " + " . $antr;
}
;

$ecartsecondes1 = $arriveesecondes1 - $departsecondes;
if ($ecartsecondes1 < 1) {
    // echo "Terminé";
    $_SESSION["ante"] = "1";

    $_SESSION["bl"] = $_SESSION["bl"] + 1;
} else {
    if ($pause == "0")
        echo "ANTE = " . $_SESSION["ante" . $_SESSION["bl"]];
}