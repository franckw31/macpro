<?php
date_default_timezone_set("Europe/Paris");
$timecountdownend = strtotime("2024-11-15 00:00:00");
$timecountdownstart = strtotime("-2 hour");
$timeleft = $timecountdownend - $timecountdownstart;
echo "suite";
 if(isset($_POST["type"]) === true && $_POST["type"] == "timerupdate")
{
    echo $timeleft ;
}
