<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
include('include/config.php');

if(strlen($_SESSION['id']) == 0) {
    header('location:logout.php');
    exit;
}

$response = array('status' => 'error', 'message' => 'Erreur inconnue', 'snapshots' => array());

$id_membre = intval($_SESSION['id']);

// Récupérer tous les snapshots de l'utilisateur
$query = mysqli_query($con, "SELECT bs.*, a.`titre-activite` 
                              FROM `blindes_snapshots` bs 
                              LEFT JOIN `activite` a ON bs.`id_activite` = a.`id-activite`
                              WHERE bs.`id_membre` = '$id_membre' 
                              ORDER BY bs.`created_at` DESC");

if($query) {
    $snapshots = array();
    while($row = mysqli_fetch_array($query)) {
        $snapshots[] = array(
            'id' => intval($row['id']),
            'name' => $row['snapshot_name'],
            'id_membre' => intval($row['id_membre']),
            'id_activite' => intval($row['id_activite']),
            'titre_activite' => $row['titre-activite'] ? $row['titre-activite'] : 'Activité supprimée'
        );
    }
    
    $response['status'] = 'success';
    $response['snapshots'] = $snapshots;
    $response['count'] = count($snapshots);
} else {
    $response['message'] = 'Erreur SQL: ' . mysqli_error($con);
}

header('Content-Type: application/json');
echo json_encode($response);
?>
