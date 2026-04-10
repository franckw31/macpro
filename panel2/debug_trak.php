<?php
session_start();
if (empty($_SESSION['id'])) { die('Non connecté'); }

$con = mysqli_connect('localhost', 'root', 'Kookies7*', 'dbs9616600');
mysqli_set_charset($con, 'utf8mb4');

$id_get = isset($_GET['id']) ? (int)$_GET['id'] : 0;

echo "<h3>debug_trak.php</h3>";
echo "<p><b>SESSION id :</b> " . (int)$_SESSION['id'] . "</p>";
echo "<p><b>GET id (id_cible) :</b> $id_get</p>";

// Vérifier existence table
$r = mysqli_query($con, "SHOW TABLES LIKE 'trak'");
echo "<p><b>Table trak existe :</b> " . (mysqli_num_rows($r) ? "OUI" : "NON") . "</p>";

// Tout le contenu de trak
$all = mysqli_query($con, "SELECT * FROM trak");
echo "<p><b>Total lignes dans trak :</b> " . mysqli_num_rows($all) . "</p>";
echo "<table border=1 cellpadding=4>";
echo "<tr><th>id</th><th>id_auteur</th><th>id_cible</th><th>id_activite</th><th>note</th><th>created_at</th></tr>";
while ($row = mysqli_fetch_assoc($all)) {
    echo "<tr>";
    foreach ($row as $k => $v) echo "<td>" . htmlspecialchars($v) . "</td>";
    echo "</tr>";
}
echo "</table>";

// Requête exacte utilisée par voir-membre.php
if ($id_get) {
    $sql = "SELECT t.id, t.id_auteur, t.id_cible, t.id_activite, t.note, t.created_at,
                   COALESCE(m.pseudo,'Inconnu') AS auteur_pseudo
            FROM trak t
            LEFT JOIN membres m ON t.id_auteur = m.`id-membre`
            WHERE t.id_cible = $id_get";
    echo "<h4>Requête filtrée sur id_cible=$id_get :</h4>";
    $r2 = mysqli_query($con, $sql);
    echo "<p>Erreur SQL : " . htmlspecialchars(mysqli_error($con)) . "</p>";
    echo "<p>Lignes : " . ($r2 ? mysqli_num_rows($r2) : 0) . "</p>";
    echo "<table border=1 cellpadding=4><tr><th>id</th><th>id_auteur</th><th>id_cible</th><th>auteur_pseudo</th><th>note</th></tr>";
    while ($row = mysqli_fetch_assoc($r2)) {
        echo "<tr><td>{$row['id']}</td><td>{$row['id_auteur']}</td><td>{$row['id_cible']}</td><td>" . htmlspecialchars($row['auteur_pseudo']) . "</td><td>" . htmlspecialchars($row['note']) . "</td></tr>";
    }
    echo "</table>";
}
