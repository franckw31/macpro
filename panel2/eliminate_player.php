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

if ($idParticipation <= 0) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid participation id']);
    exit;
}

// Marque définitivement le joueur comme éliminé et libère sa place
$sql = sprintf(
    "UPDATE `participation` SET `id-table` = 0, `id-siege` = 0, `option` = 'Elimine' WHERE `id-participation` = %d",
    $idParticipation
);

if (!mysqli_query($con, $sql)) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'DB error']);
    exit;
}

echo json_encode(['status' => 'ok']);
