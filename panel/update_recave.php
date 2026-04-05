<?php
session_start();
include('include/config.php');

header('Content-Type: application/json');

if (strlen($_SESSION['id']) == 0) {
    echo json_encode(['status' => 'error', 'message' => 'Session expirée']);
    exit;
}

$response = ['status' => 'success', 'message' => 'Mise à jour effectuée'];

// 1. TRAITEMENT DES RECAVES
if (isset($_POST['updates'])) {
    $updates = json_decode($_POST['updates'], true);
    if (is_array($updates)) {
        foreach ($updates as $update) {
            $id = intval($update['id-participation']);
            $recave = intval($update['recave']);
            
            // Mise à jour de la recave
            $query = "UPDATE `participation` SET `recave` = '$recave' WHERE `id-participation` = '$id'";
            if (!mysqli_query($con, $query)) {
                $response['status'] = 'error';
                $response['message'] = 'Erreur SQL Recave: ' . mysqli_error($con);
            }
        }
    }
}

// 2. TRAITEMENT DES CLASSEMENTS (C'est cette partie qui manquait probablement)
if (isset($_POST['classements'])) {
    $classements = json_decode($_POST['classements'], true);
    if (is_array($classements)) {
        foreach ($classements as $item) {
            $id = intval($item['id-participation']);
            $rank = intval($item['classement']);
            
            // Mise à jour du classement
            // On force la mise à jour même si la valeur est la même
            $query = "UPDATE `participation` SET `classement` = '$rank' WHERE `id-participation` = '$id'";
            
            if (!mysqli_query($con, $query)) {
                $response['status'] = 'error';
                $response['message'] = 'Erreur SQL Classement: ' . mysqli_error($con);
                error_log("Erreur SQL Update Classement: " . mysqli_error($con));
            } else {
                // Log pour vérification (optionnel)
                error_log("Classement mis à jour pour ID $id : Rang $rank");
            }
        }
    }
}

echo json_encode($response);
?>
