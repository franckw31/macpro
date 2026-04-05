<?php
session_start();
include('include/config.php');

header('Content-Type: application/json; charset=utf-8');

$activityId = isset($_GET['id_activite']) ? (int) $_GET['id_activite'] : 0;
if ($activityId <= 0) {
    echo json_encode(['version' => null]);
    exit;
}

// On se base sur l'état des sièges en base pour cette activité.
// Si une place change, la "version" changera et les pages se rechargeront.
$sql = mysqli_query(
        $con,
        "SELECT `id-participation`, `id-table`, `id-siege`, `heure_arrivee`, `jetons_bonus_arrivee`
         FROM participation
         WHERE `id-activite` = " . $activityId . "
             AND (`option` IS NULL OR `option` <> 'Annule')"
);

if (!$sql) {
    echo json_encode(['version' => null]);
    exit;
}

$parts = [];
while ($row = mysqli_fetch_assoc($sql)) {
    $pid = isset($row['id-participation']) ? (int) $row['id-participation'] : 0;
    $tbl = isset($row['id-table']) ? (int) $row['id-table'] : 0;
    $seat = isset($row['id-siege']) ? (int) $row['id-siege'] : 0;
    $heure = isset($row['heure_arrivee']) ? $row['heure_arrivee'] : '';
    $bonus = isset($row['jetons_bonus_arrivee']) ? (int)$row['jetons_bonus_arrivee'] : 0;
    // Inclure heure_arrivee et jetons_bonus_arrivee pour détecter les arrivées
    $parts[] = $pid . '-' . $tbl . '-' . $seat . '-' . $heure . '-' . $bonus;
}

// Version simple basée sur un hash de l'état des participations
$version = md5(implode('|', $parts));

echo json_encode(['version' => $version]);
exit;
