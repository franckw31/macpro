<?php
ob_start();
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 0);
include('include/config.php');
header('Content-Type: application/json; charset=utf-8');

function sendResponse($data) {
    ob_clean();
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(['status' => 'error', 'message' => 'Méthode non autorisée']);
}

$activity_id = isset($_POST['activity_id']) ? intval($_POST['activity_id']) : 0;
if ($activity_id <= 0) {
    sendResponse(['status' => 'error', 'message' => 'ID activité manquant']);
}

// ── 1. Trouver la DERNIÈRE élimination de cette activité ──────────────────
$last_q = mysqli_query($con,
    "SELECT e.* FROM `eliminations` e
     JOIN `participation` p ON e.`id_participation` = p.`id-participation`
     WHERE p.`id-activite` = '$activity_id'
     ORDER BY e.`created_at` DESC, e.`id` DESC
     LIMIT 1"
);

if (!$last_q || mysqli_num_rows($last_q) === 0) {
    sendResponse(['status' => 'error', 'message' => 'Aucune élimination à annuler']);
}

$last = mysqli_fetch_assoc($last_q);
$elim_id        = intval($last['id']);
$victim_part_id = intval($last['id_participation']);
$is_definitive  = intval($last['is_definitive']);
$elim_name      = $last['nom_membre'];         // éliminateur
$victim_name    = $last['nom_membre_victime']; // victime

// ── 2. Supprimer l'enregistrement dans eliminations ───────────────────────
$del = mysqli_query($con, "DELETE FROM `eliminations` WHERE `id` = '$elim_id'");
if (!$del) {
    sendResponse(['status' => 'error', 'message' => 'Erreur suppression: ' . mysqli_error($con)]);
}

// ── 3. Annuler les effets dans participation ───────────────────────────────
if ($is_definitive == 1) {
    // Réinitialiser classement à 0
    mysqli_query($con,
        "UPDATE `participation` SET `classement` = 0, `pertes` = GREATEST(0, `pertes` - 3)
         WHERE `id-participation` = '$victim_part_id'"
    );

    // Si le joueur avait été classé 2e, et qu'on lui avait auto-attribué le 1er à quelqu'un :
    // On annule aussi le 1er auto-attribué (si aucune autre élim → classement=1 sans entrée dans eliminations)
    // Pour simplifier : on remet classement=0 sur le joueur classé 1er s'il n'a aucune entrée dans eliminations
    $winner_q = mysqli_query($con,
        "SELECT p.`id-participation` FROM `participation` p
         WHERE p.`id-activite` = '$activity_id'
           AND p.`classement` = 1
           AND p.`id-participation` != '$victim_part_id'
           AND NOT EXISTS (
               SELECT 1 FROM `eliminations` e WHERE e.`id_participation` = p.`id-participation` AND e.`is_definitive` = 1
           )
         LIMIT 1"
    );
    if ($winner_q && mysqli_num_rows($winner_q) > 0) {
        $winner_row = mysqli_fetch_assoc($winner_q);
        mysqli_query($con, "UPDATE `participation` SET `classement` = 0 WHERE `id-participation` = " . intval($winner_row['id-participation']));
    }
} else {
    // Recave : décrémente le compteur recave
    mysqli_query($con,
        "UPDATE `participation` SET `recave` = GREATEST(0, `recave` - 1), `pertes` = GREATEST(0, `pertes` - 1)
         WHERE `id-participation` = '$victim_part_id'"
    );
}

sendResponse([
    'status'      => 'success',
    'message'     => 'Annulé : ' . ($is_definitive ? 'élimination définitive' : 'recave') . ' de ' . $victim_name,
    'victim_name' => $victim_name,
    'elim_name'   => $elim_name,
    'was_definitive' => $is_definitive
]);
?>
