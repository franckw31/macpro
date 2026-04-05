<?php
session_start();
$from=date("Y-m-d H:i:s");
$fin1=$_SESSION["fin1"];
$nom1=$_SESSION["nom1"];
$departsecondes=strtotime($from);
$arriveesecondes1=strtotime($fin1);
$ecartsecondes1=$arriveesecondes1-$departsecondes;

if ($ecartsecondes1 >= 0)
{
    echo gmdate("i:s",$ecartsecondes1);
}
else
{   
    $_SESSION["stop1"] = "1";
    echo "0";
}; ?> 