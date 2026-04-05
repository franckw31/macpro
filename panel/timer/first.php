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
date_default_timezone_set("Europe/Moscow");
$fin1="";
$res=mysqli_query($con,"SELECT * FROM blindes" );
while($row=mysqli_fetch_array($res))
{
    $fin1=$row["fin1"];
    $nom1=$row["nom1"];
};


// $_SESSION["start_time"]=date("Y-m-d H:i:s");
// $timecountdownstart=strtotime(date("Y-m-d H:i:s"));
// $timecountdownend=$fin1;
// $end_time=strtotime($fin1);
// $timeleft=$timecountdownend-$timecountdownstart;
$_SESSION["fin1"]=$fin1;
$_SESSION["nom1"]=$nom1;
// echo "debut".$_SESSION["start_time"]."-".$_SESSION["end_time"]."-".$timeleft."-fin";
?>
<script type="text/javascript">
    window.location="/panel/cardevent/index.php";
</script>
