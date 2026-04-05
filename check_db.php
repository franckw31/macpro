<?php
include('panel/include/config.php');
$res = mysqli_query($con, "DESCRIBE `blindes-live` ");
while($row = mysqli_fetch_assoc($res)) {
    echo $row['Field'] . "\n";
}
?>