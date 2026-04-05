<?php 
session_start();
?>
<!-- <script type="text/javascript">
    var cleartimer = setInterval(function()
    {
        var xmlhttp=new XMLHttpRequest();
         if (<?php echo $_SESSION['stop']?>==1) {window.location.href="http://poker31.org/index.php"};
        xmlhttp.open("GET","response.php",false);
        xmlhttp.send(null);
        document.getElementById("response").innerHTML=xmlhttp.responseText;
          
    },1000);   if (<?php echo $_SESSION['stop']?>==1) {window.location.href="http://poker31.org/index.php"};
</script> -->
<?php

define('DB_SERVER','db5011397709.hosting-data.io');
define('DB_USER','dbu5472475');
define('DB_PASS' ,'Kookies7*');
define('DB_NAME', 'dbs9616600');
$con = mysqli_connect(DB_SERVER,DB_USER,DB_PASS,DB_NAME);
// Check connection
if (mysqli_connect_errno())
{
 echo "Failed to connect to MySQL: " . mysqli_connect_error();
};

date_default_timezone_set("Europe/GMT+1");
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
<br>
<div id="response"></div>
<?php
//  echo "-".$_SESSION["stop"]."-"; 
 $fini1=$_SESSION['stop'];
 if ($_SESSION['stop'] == '1') 
    {
    echo "if index stop car stop = ".$_SESSION['stop'];?>
    <script type="text/javascript"> window.location.href="http://poker31.org/index.php" </script>
    <?php ; 
    } 
    else 
    {   
        if ($_SESSION["stop"] == '0') { echo "else index start car stop =".$_SESSION["stop"];?>
        <div id="response"></div>
        <script type="text/javascript">
            function stopTimeout() {clearTimeout(cleartimer); }
            // let cleartimer; // define `count` globaly
            let cleartimer = setInterval(function()
            {
            var xmlhttp=new XMLHttpRequest();
            
            xmlhttp.open("GET","response.php",false);
            xmlhttp.send(null);
            document.getElementById("response").innerHTML=xmlhttp.responseText;
            <?php echo $_SESSION['stop'] ?>;
            // if (<?php echo $_SESSION['stop'] == '1' ?> ) stopTimeout();
            },1000);
            
        </script>
        <!-- <?php
            if ($_SESSION['stop'] == '1')
                 { echo "on clear"; ?><script type="text/javascript">clearInterval(cleartimer)</script> <?php
            } ?> -->
        
    <?php };
    };
?>
