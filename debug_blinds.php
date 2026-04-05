<?php
include('panel/include/config.php');

$id = isset($_GET['uid']) ? intval($_GET['uid']) : 0;

if ($id == 0) {
    echo "Please provide uid parameter (e.g. ?uid=123)";
    exit;
}

echo "<h1>Debug Blinds for Activity $id</h1>";
echo "Current Time (time()): " . time() . "<br>";
echo "Current Date (date('Y-m-d H:i:s')): " . date('Y-m-d H:i:s') . "<br>";
echo "Timezone: " . date_default_timezone_get() . "<br>";

$q = mysqli_query($con, "SELECT * FROM `blindes-live` WHERE `id-activite` = '$id' ORDER BY `ordre` ASC");

echo "<table border='1' cellpadding='5'>";
echo "<tr><th>ID</th><th>Ordre</th><th>Nom</th><th>SB</th><th>BB</th><th>Minutes</th><th>Fin</th><th>Fin (Timestamp)</th><th>Diff (Fin - Now)</th><th>Active?</th></tr>";

$now = time();
$foundActive = false;

while($b = mysqli_fetch_assoc($q)) {
    $finTs = strtotime($b['fin']);
    $diff = $finTs - $now;
    $isActive = ($diff > 0 && !$foundActive) ? "YES" : "";
    if ($isActive) $foundActive = true;
    
    $style = $isActive ? "background-color: lightgreen;" : "";
    if ($b['sb'] == 0 && $b['bb'] == 0) $style = "background-color: lightgray;";
    
    echo "<tr style='$style'>";
    echo "<td>{$b['id']}</td>";
    echo "<td>{$b['ordre']}</td>";
    echo "<td>{$b['nom']}</td>";
    echo "<td>{$b['sb']}</td>";
    echo "<td>{$b['bb']}</td>";
    echo "<td>{$b['minutes']}</td>";
    echo "<td>{$b['fin']}</td>";
    echo "<td>$finTs</td>";
    echo "<td>$diff</td>";
    echo "<td>$isActive</td>";
    echo "</tr>";
}
echo "</table>";
?>