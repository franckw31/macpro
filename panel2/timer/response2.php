<?php
session_start();
$from=date("Y-m-d H:i:s");
$fin2=$_SESSION["fin2"];
$nom2=$_SESSION["nom2"];
$departsecondes=strtotime($from);
$arriveesecondes1=strtotime($fin2);
$ecartsecondes2=$arriveesecondes1-$departsecondes;

if ($ecartsecondes2 >= 0)
{
    echo gmdate("i:s",$ecartsecondes2);
}
else
{   
    $_SESSION["stop2"] = "1";
    echo "0";
}; ?> 