<?php
$conn = mysqli_connect('localhost', 'root', 'Kookies7*', 'dbs9616600');
if (!$conn) die("Connection failed: " . mysqli_connect_error());
$result = mysqli_query($conn, "SHOW COLUMNS FROM participation");
$cols = [];
while($row = mysqli_fetch_assoc($result)) {
    $cols[] = $row['Field'];
}
file_put_contents('c:/Users/MSI/Desktop/www/cols.txt', implode("\n", $cols));
?>