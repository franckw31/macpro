<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['id']) || strlen($_SESSION['id']) == 0) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

include('include/config.php');

$idParticipation = isset($_POST['id_participation']) ? intval($_POST['id_participation']) : 0;
$tableNo        = isset($_POST['table_no']) ? intval($_POST['table_no']) : 0;
$seatNo         = isset($_POST['seat_no']) ? intval($_POST['seat_no']) : 0;

if ($idParticipation <= 0) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid participation id']);
    exit;
}

// Normalisation : si table ou siège non valides, on remet à 0
if ($tableNo < 0) { $tableNo = 0; }
if ($seatNo < 0)  { $seatNo  = 0; }

$sql = sprintf(
    "UPDATE `participation` SET `id-table` = %d, `id-siege` = %d WHERE `id-participation` = %d",
    $tableNo,
    $seatNo,
    $idParticipation
);

if (!mysqli_query($con, $sql)) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'DB error']);
    exit;
}

echo json_encode(['status' => 'ok']);
