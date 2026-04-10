<?php
session_start();
error_reporting(0);
include_once('include/config.php');

$fin="";
$nom="";
$res=mysqli_query($con,"SELECT * FROM `blindes` WHERE (`id-activite` = 30 AND `ordre` = 2)");
while($row=mysqli_fetch_array($res))
{
    $fin=$row["fin"];
    $nom=$row["nom"];
    $ordre=$row["ordre"];
};

$_SESSION["fin"]=$fin;
$_SESSION["nom"]=$nom;
$_SESSION["stop"]='0';
?>
<div id="main">
<form>
  <input type="hidden" id="nom" value="<?php echo $nom; ?>">
</form>

<div class='square-box' opacity:0.99>
        <div class='square-content' id="response" style='font-size:30px ; color:black'></div>
    </div>

<?php

if ($_SESSION["stop"] == '0') { ?>

    <div id="response"></div>
    <script type="text/javascript">
        let nIntervId;
        function compteur() {
            // check if an interval has already been set up
            if (!nIntervId) {
                nIntervId = setInterval(decompte, 1000);
            }
        }

        function decompte() {
            var xmlhttp=new XMLHttpRequest();   
            xmlhttp.open("GET","response.php",false);
            xmlhttp.send(null);
                     
            if (xmlhttp.responseText == 0) {stopcompteur()} else {document.getElementById("response").innerHTML=document.getElementById("nom").value+" : "+xmlhttp.responseText;}
        }

        function stopcompteur() {
            clearInterval(nIntervId);
            // release our intervalID from the variable
            nIntervId = null;
            // window.location.href="https://poker31.org/";
        }
        compteur();
    </script>

    <?php ; }
else
{
    ?>
    <script type="text/javascript"> window.location.href="http://poker31.org/toto.php" </script>
    <?php 
};

?> 