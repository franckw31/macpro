<?php
$conn = mysqli_connect('localhost', 'root', 'Kookies7*', 'dbs9616600');
$result = mysqli_query($conn, "SHOW COLUMNS FROM participation");
while($row = mysqli_fetch_assoc($result)) {
    echo $row['Field'] . "\n";
}
?>