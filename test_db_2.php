<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
echo "Starting...\n";
$con = mysqli_connect("localhost", "root", "Kookies7*", "dbs9616600");
if (!$con) {
    echo "Connection failed: " . mysqli_connect_error() . "\n";
    exit(1);
}
echo "Connected.\n";
$res = mysqli_query($con, "SELECT rake_0 FROM participation LIMIT 1");
if (!$res) {
    echo "Query failed: " . mysqli_error($con) . "\n";
} else {
    echo "Query succeeded\n";
}
?>