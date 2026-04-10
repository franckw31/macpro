<?php
include 'include/config.php';
$res = mysqli_query($con, 'DESCRIBE participation');
while($row = mysqli_fetch_assoc($res)) {
    echo $row['Field'] . " - " . $row['Type'] . "\n";
}
unlink(__FILE__);
