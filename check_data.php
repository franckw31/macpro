<?php
$conx = mysqli_connect('localhost','root','Kookies7*','dbs9616600');
$res = mysqli_query($conx, 'SELECT id, updated_at FROM chat_messages LIMIT 5');
while($row = mysqli_fetch_assoc($res)) {
    echo "ID: " . $row['id'] . " Updated: " . $row['updated_at'] . "\n";
}
?>
