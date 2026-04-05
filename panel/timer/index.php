<?php 
session_start();
include_once('../include/config.php');
$fin0="";
$fin1="";
$nom1="";
$res=mysqli_query($con,"SELECT * FROM blindes WHERE id='1'");
while($row=mysqli_fetch_array($res))
{
    $fin1=$row["fin"];
    $nom1=$row["nom"];
};
$_SESSION["fin1"]=$fin1;
$_SESSION["nom1"]=$nom1;

$res=mysqli_query($con,"SELECT * FROM blindes WHERE id='2'");
while($row=mysqli_fetch_array($res))
{
    $fin2=$row["fin"];
    $nom2=$row["nom"];
};
$_SESSION["fin2"]=$fin2;
$_SESSION["nom2"]=$nom2;

$_SESSION["stop1"]='0';
$_SESSION["stop2"]='0';

echo "Début = ".date("Y-m-d H:i:s")." / "."Fin = ".$_SESSION["fin1"]." etat : ".$_SESSION["stop2"];
?>
<div id="response">----</div>
<div id="response2">Terminé</div>
<?php
 $fini1=$_SESSION['stop1'];
 $fini2=$_SESSION['stop2']; 
 
if ($_SESSION["stop2"] == '0') { ?>
    <div id="response"></div>
    <div id="response2"></div>
    <script type="text/javascript">
        function stopTimeout1() {clearInterval(cleartimer1); }
        let cleartimer1 = setInterval(function()
        {
            var xmlhttp=new XMLHttpRequest();            
            xmlhttp.open("GET","response.php",false);
            xmlhttp.send(null);
            // document.getElementById("response").innerHTML="fin blinde 1 = "+xmlhttp.responseText;
            if (xmlhttp.responseText == 0) {clearInterval(cleartimer1)} else {document.getElementById("response").innerHTML="fin blinde 1 = "+xmlhttp.responseText;}
        },1000);
    </script>

    <script type="text/javascript">
        function stopTimeout2() {clearInterval(cleartimer2); }
        let cleartimer2 = setInterval(function()
        {
            var xmlhttp=new XMLHttpRequest();            
            xmlhttp.open("GET","response2.php",false);
            xmlhttp.send(null);
            // document.getElementById("response").innerHTML="fin blinde 2 = "+xmlhttp.responseText;
            if (xmlhttp.responseText == 0) {clearInterval(cleartimer2);} else {document.getElementById("response2").innerHTML="fin blinde 2 = "+xmlhttp.responseText;}
        },1000);
    </script>

<?php }
else
{
    ?>
    <script type="text/javascript"> window.location.href="http://poker31.org/toto.php" </script>
    <?php ; echo "stop1 car stop = ".$_SESSION['stop1'];
};

?> 