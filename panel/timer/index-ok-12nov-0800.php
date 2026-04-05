<?php 
session_start();
include_once('../include/config.php');
$fin1="";
$nom1="";
$res=mysqli_query($con,"SELECT * FROM blindes" );
while($row=mysqli_fetch_array($res))
{
    $fin1=$row["fin1"];
    $nom1=$row["nom1"];
};
$_SESSION["fin1"]=$fin1;
$_SESSION["nom1"]=$nom1;
$_SESSION["stop"]='0';
echo "Début = ".date("Y-m-d H:i:s")." / "."Fin = ".$_SESSION["fin1"]." etat : ".$_SESSION["stop"];
?>
<div id="response"></div>
<?php
 $fini1=$_SESSION['stop'];
  
if ($_SESSION["stop"] == '0') { ?>
    <div id="response"></div>
    <script type="text/javascript">
        function stopTimeout() {clearInterval(cleartimer); }
        let cleartimer = setInterval(function()
        {
            var xmlhttp=new XMLHttpRequest();            
            xmlhttp.open("GET","response.php",false);
            xmlhttp.send(null);
            document.getElementById("response").innerHTML=xmlhttp.responseText;
            if (xmlhttp.responseText == 0) {clearInterval(cleartimer);stopTimeout();window.location="/index.php";}
        },1000);
    </script>
<?php }
else
{
    echo "stop car stop = ".$_SESSION['stop'];?>
    <script type="text/javascript"> window.location.href="http://poker31.org/toto.php" </script>
    <?php ; 
}
?> 