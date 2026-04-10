<?php
session_start();
$_SESSION["stopsb"] = '0';
error_reporting(0);
include_once('include/config.php');
$cnt=0;
// $id=32;
$sql = mysqli_query($con, "SELECT  * FROM `blindes-live` WHERE `id-activite` = $id ORDER BY `ordre` ");
    
while($row = mysqli_fetch_array($sql))
    {  
    $cnt = $cnt + 1;  
    $_SESSION["fin".$cnt]=$row["fin"];
    $_SESSION["nom".$cnt]=$row["nom"];
    $_SESSION["sb".$cnt]=$row["sb"];
    $_SESSION["bb".$cnt]=$row["bb"];
    $_SESSION["ante".$cnt]=$row["ante"]; 
    };   
if ($_SESSION["stopsb"] == '0') { ?> 
    <script type="text/javascript">
        var audio = new Audio("/la-cucaracha-horn.mp3");

// If the count down is finished, play audio file

        let nIntervId4;
        function compteursb() { if (!nIntervId4) { nIntervId4 = setInterval(decomptesb, 100);}}
        function decomptesb() { var xmlhttp=new XMLHttpRequest(); xmlhttp.open("GET","response-sb.php",false); xmlhttp.send(null);                     
            if (xmlhttp.responseText == 0) {audio.play();stopcompteursb();compteursb()} else {document.getElementById("response-sb").innerHTML=xmlhttp.responseText;}}
        function stopcompteursb() { clearInterval(nIntervId4); nIntervId4 = null; }
        stopcompteursb()
        compteursb();
    </script>
<?php ; }
?> 