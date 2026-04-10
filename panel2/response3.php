<?php
session_start();
// $_SESSION["cptblinde"]=$_SESSION["cptblinde"]+1;
$departsecondes=strtotime(date("Y-m-d H:i:s"));
// $arriveesecondes1=strtotime($_SESSION["fin3"]);
$arriveesecondes1=strtotime($_SESSION["fin".$_SESSION["bl"]]);
$ecartsecondes1=$arriveesecondes1-$departsecondes;
// $_SESSION["blinde"]="3";
// $_SESSION["cptblinde"]=$_SESSION["blinde"];
if ($ecartsecondes1 >= 0)
{ echo gmdate("i:s",$ecartsecondes1);}
else
// { $_SESSION["stop1"] = "1";echo "0";}
{ 
    echo "0";
    $_SESSION["bl"]=$_SESSION["bl"]+1;
    // $_SESSION["blinde"]="3";
    // $_SESSION["cptblinde"]=$_SESSION["cptblinde"]+1;
};
?> 