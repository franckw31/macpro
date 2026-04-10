<?php
session_start();
$_SESSION["stoppause"] = '0';
error_reporting(0);
include_once('include/config.php');

if ($_SESSION["stoppause"] == '0') { ?> 
    <script type="text/javascript">
        let nIntervId2;
        function compteurp() { if (!nIntervId2) { nIntervId2 = setInterval(decomptep, 100);}}
        function decomptep() { var xmlhttp=new XMLHttpRequest(); xmlhttp.open("GET","car-pause.php",false); xmlhttp.send(null);                     
            if (xmlhttp.responseText == 0) {stopcompteurp();compteurp()} else {document.getElementById("car-pause").innerHTML=xmlhttp.responseText;}}
        function stopcompteurp() { clearInterval(nIntervId2); nIntervId2 = null; }
        stopcompteurp();
        compteurp();
    </script>
<?php ; }
?> 