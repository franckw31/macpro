<?php
session_start();

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


?>


<!-- <audio src="http://poker31.org/panel/cardevent/popa.mp3" autoplay>
</audio> -->
<iframe src="popa.mp3" allow="autoplay" id="audio" style="display:none"></iframe>