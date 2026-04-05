<?php
session_start();
error_reporting(0);
include('include/config.php');

$response = array('status' => 'error', 'message' => 'Erreur lors de la mise à jour');

if(isset($_POST['id']) && isset($_POST['duree'])) {
    $id = intval($_POST['id']);
    $dureeMinutes = intval($_POST['duree']);
    
    // Valider: doit être un nombre entre 0 et 99 (2 caractères max)
    if($dureeMinutes >= 0 && $dureeMinutes <= 99) {
        // Récupérer la blinde actuelle
        $blindeQuery = mysqli_query($con, "SELECT * FROM `blindes-live` WHERE `id` = '$id'");
        if($blindeQuery && mysqli_num_rows($blindeQuery) > 0) {
            $blinde = mysqli_fetch_array($blindeQuery);
            $idActivite = intval($blinde['id-activite']);
            $currentOrdre = intval($blinde['ordre']);
            $oldFin = $blinde['fin'];
            $oldDureeMinutes = intval($blinde['minutes']);
            
            // Convertir fin actuelle en timestamp pour calculer le début
            $finTimestamp = strtotime($oldFin);
            
            // Calculer le début: debut = fin - (ancienne durée en secondes)
            $oldDureeSeconds = $oldDureeMinutes * 60;
            $beginTimestamp = $finTimestamp - $oldDureeSeconds;
            
            // Calculer la nouvelle fin de cette blinde
            $newDureeSeconds = $dureeMinutes * 60;
            $newFinTimestamp = $beginTimestamp + $newDureeSeconds;
            $newFin = date('Y-m-d H:i:s', $newFinTimestamp);
            
            // Calculer le décalage temporel
            $timeDelta = $newFinTimestamp - $finTimestamp;
            
            // Mettre à jour la blinde actuelle
            $update = mysqli_query($con, "UPDATE `blindes-live` SET `minutes` = '$dureeMinutes', `fin` = '$newFin' WHERE `id` = '$id'");
            
            if($update) {
                // Mettre à jour toutes les blindes suivantes
                $countUpdated = 0;
                if($timeDelta != 0) {
                    $nextBlindes = mysqli_query($con, "SELECT * FROM `blindes-live` WHERE `id-activite` = '$idActivite' AND `ordre` > '$currentOrdre' ORDER BY `ordre` ASC");
                    
                    if($nextBlindes) {
                        $numRows = mysqli_num_rows($nextBlindes);
                        if($numRows > 0) {
                            while($nextBlinde = mysqli_fetch_array($nextBlindes)) {
                                $nextId = intval($nextBlinde['id']);
                                $nextFin = $nextBlinde['fin'];
                                
                                // Convertir fin en timestamp et ajouter le décalage
                                $nextFinTimestamp = strtotime($nextFin);
                                $updatedFinTimestamp = $nextFinTimestamp + $timeDelta;
                                $updatedFin = date('Y-m-d H:i:s', $updatedFinTimestamp);
                                
                                // Mettre à jour la blinde suivante
                                $updateNext = mysqli_query($con, "UPDATE `blindes-live` SET `fin` = '$updatedFin' WHERE `id` = '$nextId'");
                                if($updateNext) {
                                    $countUpdated++;
                                }
                            }
                        }
                    }
                }
                
                $response['status'] = 'success';
                $response['message'] = 'Durée et fins mises à jour avec succès (' . $countUpdated . ' lignes suivantes mises à jour)';
                $response['new_duree'] = $dureeMinutes;
                $response['new_fin'] = $newFin;
                $response['debug_timeDelta'] = $timeDelta;
                $response['debug_ordre'] = $currentOrdre;
                $response['debug_activite'] = $idActivite;
            } else {
                $response['message'] = 'Erreur SQL: ' . mysqli_error($con);
            }
        } else {
            $response['message'] = 'Blinde non trouvée';
        }
    } else {
        $response['message'] = 'Format invalide. Entrez un nombre entre 0 et 99';
    }
} else {
    $response['message'] = 'Paramètres manquants';
}

header('Content-Type: application/json');
echo json_encode($response);
?>
