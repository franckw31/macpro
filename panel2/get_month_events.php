<?php
include ('include/config.php');

$month = isset($_GET['month']) ? intval($_GET['month']) : date('m');
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

$query = "SELECT * FROM events WHERE DATE_FORMAT(start_date, '%m') = '$month' AND DATE_FORMAT(start_date, '%Y') = '$year'";
$result = mysqli_query($conn, $query);

$events = [];
while ($row = mysqli_fetch_assoc($result)) {
    $events[] = $row;
}

header('Content-Type: application/json');
echo json_encode($events);
?>
