<?php
include ('include/config.php');

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id > 0) {
    $query = "DELETE FROM events WHERE id = $id";
    mysqli_query($conn, $query);
}

header('Location: agenda.php');
exit();
