<?php
session_start();
// echo "debut-";
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
date_default_timezone_get("Europe/paris");
$duration="";
$res=mysqli_query($con,"SELECT * FROM blindes" );
while($row=mysqli_fetch_array($res))
{
    $duration=$row["duration"];
};
// echo $duration;
$_SESSION["duration"]=$duration;
$_SESSION["start_time"]=date("Y-m-d H:i:s");
// echo $_SESSION["start_time"];
$end_time=date('Y-m-d H:i:s', strtotime('+'.$_SESSION["duration"].'minutes',strtotime($_SESSION["start_time"])));
$_SESSION["end_time"]=$end_time;
// echo "-".$_SESSION["end_time"]."-".$end_time;
// echo "-fin";
?>
<script type="text/javascript">
    window.location="/panel/cardevent/index.php";
</script>
