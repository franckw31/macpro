<?php
session_start();
error_reporting(0);
include('include/config.php');

$response = array('status' => 'error', 'message' => 'Erreur lors de la mise à jour');

if(isset($_POST['id']) && isset($_POST['field']) && isset($_POST['value'])) {
    $id = intval($_POST['id']);
    $field = $_POST['field']; // 'sb', 'bb', ou 'ante'
    $value = intval($_POST['value']);
    
    // Valider le champ
    if(!in_array($field, array('sb', 'bb', 'ante'))) {
        $response['message'] = 'Champ invalide';
    } else if($value < 0) {
        $response['message'] = 'La valeur doit être positive';
    } else {
        // Échapper le nom du champ pour safety
        $allowed_fields = array('sb' => '`sb`', 'bb' => '`bb`', 'ante' => '`ante`');
        $field_escaped = $allowed_fields[$field];
        
        $update = mysqli_query($con, "UPDATE `blindes-live` SET $field_escaped = '$value' WHERE `id` = '$id'");
        
        if($update) {
            $response['status'] = 'success';
            $response['message'] = 'Valeur mise à jour avec succès';
            $response['new_value'] = $value;
        } else {
            $response['message'] = 'Erreur SQL: ' . mysqli_error($con);
        }
    }
} else {
    $response['message'] = 'Paramètres manquants';
}

header('Content-Type: application/json');
echo json_encode($response);
?>
