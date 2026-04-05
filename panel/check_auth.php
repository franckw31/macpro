<?php
session_start();
$conn = mysqli_connect('localhost', 'root', 'Kookies7*', 'dbs9616600');
mysqli_set_charset($conn, 'utf8mb4');

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

$user_id = $_SESSION['id'];
echo "Current User ID: " . htmlspecialchars($user_id) . "<br><br>";

// Vérifier les tables disponibles
echo "Tables in database:<br>";
$tables_result = mysqli_query($conn, "SHOW TABLES");
if ($tables_result) {
    while ($table = mysqli_fetch_row($tables_result)) {
        echo "  - " . htmlspecialchars($table[0]) . "<br>";
    }
} else {
    echo "Error: " . mysqli_error($conn) . "<br>";
}

echo "<br><br>";

// Vérifier la structure de la table membres
echo "Structure of 'membres' table:<br>";
$structure = mysqli_query($conn, "SHOW COLUMNS FROM membres");
if ($structure) {
    echo "<table border='1'><tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    while ($col = mysqli_fetch_assoc($structure)) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($col['Field']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Type']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Null']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Key']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Default']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Extra']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "Error: " . mysqli_error($conn) . "<br>";
}

echo "<br><br>";

// Lister tous les membres
echo "All members in database:<br>";
$sql_all = "SELECT * FROM membres LIMIT 20";
echo "SQL: $sql_all<br>";
$result_all = mysqli_query($conn, $sql_all);
if ($result_all) {
    $num = mysqli_num_rows($result_all);
    echo "Rows found: $num<br>";
    while ($row = mysqli_fetch_assoc($result_all)) {
        echo "<pre>" . print_r($row, true) . "</pre>";
    }
} else {
    echo "Query failed: " . mysqli_error($conn) . "<br>";
}

mysqli_close($conn);
?>
