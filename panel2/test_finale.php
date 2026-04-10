<?php
session_start();
include('include/config.php');

echo "<h2>Test - Tous les joueurs avec jetons_2 > 1 et leurs participations</h2>";

// Tous les joueurs avec jetons_2 > 1
$sql = "SELECT `id-membre`, pseudo, `jetons_2` FROM membres WHERE `jetons_2` > 1 ORDER BY `jetons_2` DESC LIMIT 20";
$result = mysqli_query($con, $sql);

echo "<table border='1'><tr><th>ID Membre</th><th>Pseudo</th><th>Jetons_2</th><th>Participations aux 3 dates</th></tr>";

while ($row = mysqli_fetch_assoc($result)) {
	$membreId = (int)$row['id-membre'];
	echo "<tr>";
	echo "<td>" . $membreId . "</td>";
	echo "<td>" . htmlspecialchars($row['pseudo']) . "</td>";
	echo "<td>" . $row['jetons_2'] . "</td>";
	echo "<td>";
	
	// Chercher les participations aux 3 dates
	$sqlPart = "SELECT `id-participation`, DATE_FORMAT(`ds`, '%Y-%m-%d') as ds_formatted, `ds` FROM participation WHERE `id-membre` = $membreId AND DATE_FORMAT(`ds`, '%Y-%m-%d') IN ('2026-02-19', '2026-02-21', '2026-02-27')";
	$partResult = mysqli_query($con, $sqlPart);
	
	if (mysqli_num_rows($partResult) > 0) {
		while ($part = mysqli_fetch_assoc($partResult)) {
			echo "Participation #" . $part['id-participation'] . " - Date: " . $part['ds_formatted'] . " (Raw: " . $part['ds'] . ")<br>";
		}
	} else {
		echo "<span style='color:red'>AUCUNE</span>";
	}
	
	echo "</td>";
	echo "</tr>";
}

echo "</table>";

// Vérifier toutes les participations de tous les joueurs jetons_2 > 1
echo "<h2>Toutes les dates des participations pour joueurs jetons_2 > 1</h2>";
$sqlAll = "SELECT DISTINCT DATE_FORMAT(p.`ds`, '%Y-%m-%d') as date_dist FROM participation p JOIN membres m ON p.`id-membre` = m.`id-membre` WHERE m.`jetons_2` > 1 ORDER BY date_dist DESC";
$allResult = mysqli_query($con, $sqlAll);

echo "<p>Dates trouvées: ";
while ($dateRow = mysqli_fetch_assoc($allResult)) {
	echo $dateRow['date_dist'] . " | ";
}
echo "</p>";

?>
