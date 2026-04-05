<?php
$pdo = new PDO('mysql:host=localhost;dbname=dbs9616600;charset=utf8', 'root', 'Kookies7*');
$q = $pdo->query("DESCRIBE activite");
print_r($q->fetchAll(PDO::FETCH_ASSOC));
