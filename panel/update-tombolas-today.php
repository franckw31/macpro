<?php
// Update participation.tombolas for activities happening today.
// Usage: run on server where include/config.php provides a mysqli connection:
// php panel/update-tombolas-today.php
session_start();
include(__DIR__ . '/../include/config.php');

$db = null;
if (isset($con) && (is_resource($con) || $con instanceof mysqli)) {
    $db = $con;
} elseif (isset($conn) && (is_resource($conn) || $conn instanceof mysqli)) {
    $db = $conn;
}

if (!$db) {
    fwrite(STDERR, "Database connection not available. Ensure include/config.php defines \$con (mysqli)\n");
    exit(2);
}

// Ensure tombolas column exists
$res = mysqli_query($db, "SHOW COLUMNS FROM `participation` LIKE 'tombolas'");
if (! $res || mysqli_num_rows($res) === 0) {
    echo "Column 'tombolas' not found in table 'participation'. Nothing to do.\n";
    exit(0);
}

// Update tombolas for participations where activity date is today.
// Set tombolas = 1 when participation.ds <= date_depart - INTERVAL 24 HOUR, else 0.
$sql = "UPDATE participation p
JOIN activite a ON p.`id-activite` = a.`id-activite`
SET p.`tombolas` = CASE WHEN (p.`ds` IS NOT NULL AND p.`ds` <= DATE_SUB(a.`date_depart`, INTERVAL 24 HOUR)) THEN 1 ELSE 0 END
WHERE DATE(a.`date_depart`) = CURDATE()";

if (! mysqli_query($db, $sql)) {
    fwrite(STDERR, "Update failed: " . mysqli_error($db) . "\n");
    exit(3);
}

$affected = mysqli_affected_rows($db);
echo "Update applied. Rows affected: $affected\n";

// Optionally list a few updated participations
$listSql = "SELECT p.`id-participation`, p.`id-membre`, p.`id-activite`, p.`tombolas`, p.`ds`, a.`date_depart` FROM participation p JOIN activite a ON p.`id-activite`=a.`id-activite` WHERE DATE(a.`date_depart`)=CURDATE() LIMIT 50";
$r = mysqli_query($db, $listSql);
if ($r) {
    echo "Sample rows (up to 50):\n";
    while ($row = mysqli_fetch_assoc($r)) {
        printf("#%s member:%s activity:%s tombolas:%s ds:%s date_depart:%s\n", $row['id-participation'], $row['id-membre'], $row['id-activite'], $row['tombolas'], $row['ds'], $row['date_depart']);
    }
}

echo "Done.\n";

?>
