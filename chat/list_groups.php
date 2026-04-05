<?php
require_once(__DIR__ . '/../config.php');

// List all groups with their details
$sql = "SELECT g.id, g.name, g.created_by, g.activity_id, m.pseudo as creator_pseudo
        FROM chat_groups g
        LEFT JOIN membres m ON g.created_by = m.`id-membre`
        ORDER BY g.id";

$result = mysqli_query($conx, $sql);

echo "<h2>Liste des groupes de chat</h2>";
echo "<table border='1' cellpadding='10'>";
echo "<tr><th>Group ID</th><th>Nom</th><th>Créé par (ID)</th><th>Pseudo</th><th>Activity ID</th></tr>";

while ($row = mysqli_fetch_assoc($result)) {
    echo "<tr>";
    echo "<td>" . $row['id'] . "</td>";
    echo "<td>" . htmlspecialchars($row['name']) . "</td>";
    echo "<td>" . $row['created_by'] . "</td>";
    echo "<td>" . htmlspecialchars($row['creator_pseudo'] ?? 'N/A') . "</td>";
    echo "<td>" . ($row['activity_id'] ?? 'NULL') . "</td>";
    echo "</tr>";
}

echo "</table>";
?>
