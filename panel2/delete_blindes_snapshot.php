<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
include('include/config.php');

if(strlen($_SESSION['id']) == 0) {
    header('location:logout.php');
    exit;
}

$response = array('status' => 'error', 'message' => 'Erreur inconnue');

if(isset($_POST['snapshot_id'])) {
    $snapshot_id = intval($_POST['snapshot_id']);
    $id_membre = intval($_SESSION['id']);
    
    // Vérifier et supprimer
    $delete_query = mysqli_query($con, "DELETE FROM `blindes_snapshots` WHERE `id` = '$snapshot_id' AND `id_membre` = '$id_membre'");
    
    if($delete_query) {
        if(mysqli_affected_rows($con) > 0) {
            $response['status'] = 'success';
            $response['message'] = 'Snapshot supprimé avec succès';
        } else {
            $response['message'] = 'Snapshot introuvable ou vous n\'avez pas les droits';
        }
    } else {
        $response['message'] = 'Erreur SQL: ' . mysqli_error($con);
    }
} else {
    $response['message'] = 'ID de snapshot manquant';
}

header('Content-Type: application/json');
echo json_encode($response);
?>
