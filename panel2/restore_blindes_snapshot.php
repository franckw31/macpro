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

if(isset($_POST['snapshot_id']) && isset($_POST['id_activite'])) {
    $snapshot_id = intval($_POST['snapshot_id']);
    $id_activite = intval($_POST['id_activite']);
    $id_membre = intval($_SESSION['id']);
    
    // Définir le fuseau horaire
    date_default_timezone_set('Europe/Paris');
    
    // Récupérer le snapshot
    $snapshot_query = mysqli_query($con, "SELECT * FROM `blindes_snapshots` WHERE `id` = '$snapshot_id' AND `id_membre` = '$id_membre'");
    
    if($snapshot_query && mysqli_num_rows($snapshot_query) > 0) {
        $snapshot = mysqli_fetch_array($snapshot_query);
        $snapshot_data = json_decode($snapshot['snapshot_data'], true);
        
        if($snapshot_data && is_array($snapshot_data)) {
            // Récupérer l'heure de fin ET les minutes de la première ligne (ordre=1) avant restauration
            $first_fin_before = null;
            $first_minutes_before = 0;
            $req_first = mysqli_query($con, "SELECT `fin`, `minutes` FROM `blindes-live` WHERE `id-activite` = '$id_activite' AND `ordre` = 1 LIMIT 1");
            
            if($req_first && mysqli_num_rows($req_first) > 0) {
                $row_first = mysqli_fetch_array($req_first);
                $first_fin_before = $row_first['fin'];
                $first_minutes_before = intval($row_first['minutes']);
            }
            
            // Si pas d'heure de fin avant restauration, utiliser l'heure actuelle
            if(!$first_fin_before || trim($first_fin_before) === '') {
                $first_fin_before = date('Y-m-d H:i:s');
                $first_minutes_before = 0;
            }
            
            // Récupérer les minutes de la première ligne du snapshot
            $first_minutes_snapshot = 0;
            foreach($snapshot_data as $blinde) {
                if(intval($blinde['ordre']) == 1) {
                    $first_minutes_snapshot = intval($blinde['minutes']);
                    break;
                }
            }
            
            // Créer un objet DateTime pour manipuler les dates correctement
            try {
                $first_fin_datetime = new DateTime($first_fin_before);
                
                // Calculer la nouvelle heure de fin pour la première ligne
                // heure_fin_avant - minutes_avant + minutes_snapshot
                if($first_minutes_before > 0) {
                    $first_fin_datetime->modify('-' . $first_minutes_before . ' minutes');
                }
                
                if($first_minutes_snapshot > 0) {
                    $first_fin_datetime->modify('+' . $first_minutes_snapshot . ' minutes');
                }
                
                // IMPORTANT: Garder le format DATETIME complet
                $first_fin_calculated = $first_fin_datetime->format('Y-m-d H:i:s');
                
            } catch (Exception $e) {
                $response['message'] = 'Erreur de conversion de date: ' . $e->getMessage();
                header('Content-Type: application/json');
                echo json_encode($response);
                exit;
            }
            
            // Supprimer toutes les blindes actuelles
            mysqli_query($con, "DELETE FROM `blindes-live` WHERE `id-activite` = '$id_activite'");
            
            // Créer un nouveau DateTime pour le parcours (pas un clone de celui qui a été modifié)
            $current_datetime = new DateTime($first_fin_calculated);
            
            foreach($snapshot_data as $blinde) {
                $ordre = intval($blinde['ordre']);
                $sb = intval($blinde['sb']);
                $bb = intval($blinde['bb']);
                $ante = intval($blinde['ante']);
                $minutes = intval($blinde['minutes']);
                
                // Pour la première ligne (ordre=1), utiliser l'heure calculée
                if($ordre == 1) {
                    $fin = $first_fin_calculated;
                } else {
                    // Pour les autres lignes, ajouter les minutes à partir du DateTime courant
                    try {
                        $current_datetime->modify('+' . $minutes . ' minutes');
                        $fin = $current_datetime->format('Y-m-d H:i:s');
                    } catch (Exception $e) {
                        $fin = $first_fin_calculated;
                    }
                }
                
                $fin_escaped = mysqli_real_escape_string($con, $fin);
                
                // Insérer la blinde
                $insert = mysqli_query($con, "INSERT INTO `blindes-live` (`id-activite`, `ordre`, `sb`, `bb`, `ante`, `minutes`, `fin`) VALUES ('$id_activite', '$ordre', '$sb', '$bb', '$ante', '$minutes', '$fin_escaped')");
                
                if(!$insert) {
                    $response['message'] = 'Erreur insertion ordre ' . $ordre . ': ' . mysqli_error($con);
                    header('Content-Type: application/json');
                    echo json_encode($response);
                    exit;
                }
            }
            
            $response['status'] = 'success';
            $response['message'] = 'Snapshot restauré avec succès';
        } else {
            $response['message'] = 'Données du snapshot invalides';
        }
    } else {
        $response['message'] = 'Snapshot introuvable ou vous n\'avez pas les droits';
    }
} else {
    $response['message'] = 'Paramètres manquants';
}

header('Content-Type: application/json');
echo json_encode($response);
?>
