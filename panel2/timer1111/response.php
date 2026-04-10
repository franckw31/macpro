<?php
session_start();

$from=date("Y-m-d H:i:s");
$fin1=$_SESSION["fin1"];
$nom1=$_SESSION["nom1"];
$departsecondes=strtotime($from);
$arriveesecondes1=strtotime($fin1);

$ecartsecondes1=$arriveesecondes1-$departsecondes;

// echo gmdate("Y-m-d H:i:s",$ecartsecondes1)." Soit : ".$ecartsecondes1." secondes pour la fin de la ".$nom1.$_SESSION["stop"] ;
echo " il est : ".$from."-".$ecartsecondes1;

if ((int)($ecartsecondes1) >= 0)
{
echo gmdate("H:i:s",$ecartsecondes1)." Soit : ".$ecartsecondes1." secondes pour la fin de la ".$nom1 ;
echo " il est : ".$from."      "."if response car stop = ".$_SESSION["stop"];;
}

else
{  
    // echo $ecartsecondes1;
    $_SESSION["stop"]='1';
    echo "else responsE car STOP = ".$_SESSION["stop"];
     
    ?><script type="text/javascript">clearInterval(cleartimer)</script> <?php
}; ?> 


