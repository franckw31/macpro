<?php
session_start();
$_SESSION["stopante"] = '0';
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
    $_SESSION["ante".$cnt]=$row["ante"]; 
    };   
if ($_SESSION["stopante"] == '0') { ?> 
    <script type="text/javascript">
        let nIntervId3;
        function compteura() { if (!nIntervId3) { nIntervId3 = setInterval(decomptea, 1000);}}
        function decomptea() { var xmlhttp=new XMLHttpRequest(); xmlhttp.open("GET","response-ante.php",false); xmlhttp.send(null);                     
            if (xmlhttp.responseText == 0) {stopcompteura();compteura()} else {document.getElementById("response-ante").innerHTML=xmlhttp.responseText;}}
        function stopcompteura() { clearInterval(nIntervId3); nIntervId3 = null; }
        stopcompteura();
        compteura();
    </script>
<?php ; }
?> 