<?php
include('include/config.php');
$q = mysqli_query($con, "SELECT * FROM rake");
while($r = mysqli_fetch_assoc($q)) {
    print_r($r);
}
?>