<?php
$conx = mysqli_connect('localhost','root','Kookies7*','dbs9616600');
$res = mysqli_query($conx, 'DESCRIBE chat_messages');
while($row = mysqli_fetch_assoc($res)) {
    echo $row['Field'] . " - " . $row['Type'] . "\n";
}
?>
