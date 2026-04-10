<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

session_start();
header('Content-Type: application/json');

try {
    include('include/config.php');

    if (strlen($_SESSION['id']) == 0) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $id_activite = isset($_POST['id_activite']) ? (int)$_POST['id_activite'] : 0;
        
        if ($id_activite <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'ID activité invalide']);
            exit;
        }

        $conn = mysqli_connect('localhost', 'root', 'Kookies7*', 'dbs9616600');
        
        if (!$conn) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Erreur de connexion à la base de données']);
            exit;
        }
        
        mysqli_set_charset($conn, 'utf8mb4');
        
        // Vérifier les permissions : seul un admin ou l'organisateur de l'activité peut modifier
        $current_user_id = $_SESSION['id'];
        $is_admin = false;
        $is_organizer = false;
        
        // Vérifier si l'utilisateur est admin (droits = 2)
        $admin_check = mysqli_query($conn, "SELECT droits FROM membres WHERE `id-membre` = " . (int)$current_user_id);
        if ($admin_check && mysqli_num_rows($admin_check) > 0) {
            $admin_row = mysqli_fetch_assoc($admin_check);
            $is_admin = ((int)$admin_row['droits'] == 2);
        }
        
        // Vérifier si l'utilisateur est l'organisateur de l'activité
        $organizer_check = mysqli_query($conn, "SELECT `id-membre` FROM activite WHERE `id-activite` = " . (int)$id_activite);
        if ($organizer_check && mysqli_num_rows($organizer_check) > 0) {
            $organizer_row = mysqli_fetch_assoc($organizer_check);
            $is_organizer = ((int)$organizer_row['id-membre'] == (int)$current_user_id);
        }
        
        // Rejeter si l'utilisateur n'a pas les permissions
        if (!$is_admin && !$is_organizer) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Vous n\'avez pas la permission de modifier cette activité']);
            mysqli_close($conn);
            exit;
        }
        
        // Mettre à jour le bonus arrivée pour tous les participants de l'activité
        $update_sql = "UPDATE participation p
                       INNER JOIN activite a ON p.`id-activite` = a.`id-activite`
                       SET p.jetons_bonus_arrivee = CASE 
                           WHEN p.heure_arrivee IS NOT NULL 
                            AND p.heure_arrivee != '0000-00-00 00:00:00'
                            AND p.heure_arrivee < a.date_depart 
                           THEN 5000 
                           ELSE 0 
                       END,
                       p.jetons_total = p.jetons + p.jetons_bonus_ins + CASE 
                           WHEN p.heure_arrivee IS NOT NULL 
                            AND p.heure_arrivee != '0000-00-00 00:00:00'
                            AND p.heure_arrivee < a.date_depart 
                           THEN 5000 
                           ELSE 0 
                       END
                       WHERE p.`id-activite` = " . (int)$id_activite;
        
        error_log("Update bonus arrivée SQL: " . $update_sql);
        
        if (mysqli_query($conn, $update_sql)) {
            $affected = mysqli_affected_rows($conn);
            error_log("Rows updated: $affected");
            echo json_encode(['success' => true, 'affected' => $affected]);
        } else {
            http_response_code(500);
            $error_msg = mysqli_error($conn);
            error_log("Execute error: " . $error_msg);
            echo json_encode(['success' => false, 'error' => 'Erreur SQL: ' . $error_msg]);
        }
        
        mysqli_close($conn);
    } else {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    }
} catch (Exception $e) {
    http_response_code(500);
    error_log("Exception in update_bonus_arrivee.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Exception: ' . $e->getMessage()]);
} catch (Throwable $t) {
    http_response_code(500);
    error_log("Error in update_bonus_arrivee.php: " . $t->getMessage());
    echo json_encode(['success' => false, 'error' => 'Erreur: ' . $t->getMessage()]);
}
