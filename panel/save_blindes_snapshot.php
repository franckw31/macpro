<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
include('include/config.php');

// Log function
function logMessage($message) {
    error_log(date('[Y-m-d H:i:s] ') . $message . "\n", 3, 'snapshot_logs.txt');
}

if(strlen($_SESSION['id']) == 0) {
    logMessage("Session invalide");
    header('location:logout.php');
    exit;
}

$response = array('status' => 'error', 'message' => 'Erreur inconnue');

logMessage("Début de la sauvegarde");
logMessage("POST data: " . print_r($_POST, true));

if(isset($_POST['id_activite']) && isset($_POST['snapshot_name'])) {
    $id_activite = intval($_POST['id_activite']);
    $snapshot_name = mysqli_real_escape_string($con, trim($_POST['snapshot_name']));
    $id_membre = intval($_SESSION['id']);
    
    logMessage("id_activite: $id_activite, snapshot_name: $snapshot_name, id_membre: $id_membre");
    
    // Vérifier que la table existe
    $check_table = mysqli_query($con, "SHOW TABLES LIKE 'blindes_snapshots'");
    if(mysqli_num_rows($check_table) == 0) {
        $response['message'] = 'La table blindes_snapshots n\'existe pas';
        logMessage($response['message']);
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }
    
    // Récupérer toutes les blindes actuelles
    $sql_blindes = "SELECT * FROM `blindes-live` WHERE `id-activite` = '$id_activite' ORDER BY `ordre` ASC";
    logMessage("SQL blindes: $sql_blindes");
    
    $req_blindes = mysqli_query($con, $sql_blindes);
    
    if(!$req_blindes) {
        $response['message'] = 'Erreur requête blindes: ' . mysqli_error($con);
        logMessage($response['message']);
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }
    
    $count_blindes = mysqli_num_rows($req_blindes);
    logMessage("Nombre de blindes trouvées: $count_blindes");
    
    if($count_blindes > 0) {
        date_default_timezone_set('Europe/Paris');
        $created_at = date('Y-m-d H:i:s');
        
        // Sérialiser les données des blindes (SANS l'heure de fin)
        $blindes_data = array();
        while($blinde = mysqli_fetch_array($req_blindes)) {
            $blindes_data[] = array(
                'ordre' => intval($blinde['ordre']),
                'sb' => intval($blinde['sb']),
                'bb' => intval($blinde['bb']),
                'ante' => intval($blinde['ante']),
                'minutes' => intval($blinde['minutes'])
            );
        }
        
        logMessage("Données blindes: " . print_r($blindes_data, true));
        
        $snapshot_data_json = json_encode($blindes_data);
        logMessage("JSON blindes: $snapshot_data_json");
        
        $snapshot_data = mysqli_real_escape_string($con, $snapshot_data_json);
        
        // Insérer la sauvegarde
        $sql_insert = "INSERT INTO `blindes_snapshots` (`id_activite`, `id_membre`, `snapshot_name`, `snapshot_data`, `created_at`) VALUES ('$id_activite', '$id_membre', '$snapshot_name', '$snapshot_data', '$created_at')";
        logMessage("SQL insert: $sql_insert");
        
        $insert_query = mysqli_query($con, $sql_insert);
        
        if($insert_query) {
            $snapshot_id = mysqli_insert_id($con);
            $response['status'] = 'success';
            $response['message'] = 'Sauvegarde créée avec succès';
            $response['snapshot_id'] = $snapshot_id;
            logMessage("Succès! ID: $snapshot_id");
        } else {
            $response['message'] = 'Erreur SQL insert: ' . mysqli_error($con);
            logMessage($response['message']);
        }
    } else {
        $response['message'] = 'Aucune blinde trouvée pour cette activité (id: ' . $id_activite . ')';
        logMessage($response['message']);
    }
} else {
    $missing = array();
    if(!isset($_POST['id_activite'])) $missing[] = 'id_activite';
    if(!isset($_POST['snapshot_name'])) $missing[] = 'snapshot_name';
    $response['message'] = 'Paramètres manquants: ' . implode(', ', $missing);
    logMessage($response['message']);
}

header('Content-Type: application/json');
echo json_encode($response);
logMessage("Réponse: " . json_encode($response));
logMessage("---");
?>
