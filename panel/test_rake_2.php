<?php
include('include/config.php');
$q = mysqli_query($con, "SHOW TABLES LIKE 'rake'");
if(mysqli_num_rows($q) > 0) {
    echo "Table rake exists\n";
    $q2 = mysqli_query($con, "DESCRIBE rake");
    while($r = mysqli_fetch_assoc($q2)) {
        print_r($r);
    }
    $q3 = mysqli_query($con, "SELECT * FROM rake");
    while($r = mysqli_fetch_assoc($q3)) {
        print_r($r);
    }
} else {
    echo "Table rake does NOT exist\n";
}
?>