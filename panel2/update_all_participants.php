<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

session_start();
header('Content-Type: application/json');

try {
    if (strlen($_SESSION['id']) == 0) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        error_log("Received POST data in update_all_participants.php:");
        
        $id_activite = isset($_POST['id_activite']) ? (int)$_POST['id_activite'] : 0;
        $updates = isset($_POST['updates']) ? json_decode($_POST['updates'], true) : [];
        
        error_log("Activity ID: " . $id_activite);
        error_log("Updates: " . print_r($updates, true));
        
        if (empty($updates) || !$id_activite) {
            error_log("Invalid data received");
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid data']);
            exit;
        }

        $conn = mysqli_connect('localhost', 'root', 'Kookies7*', 'dbs9616600');
        if (!$conn) {
            error_log("Database connection failed: " . mysqli_connect_error());
            echo json_encode(['success' => false, 'error' => 'Database connection failed: ' . mysqli_connect_error()]);
            exit;
        }
        
        mysqli_set_charset($conn, 'utf8mb4');
    
    // Vérifier les permissions : seul un admin ou l'organisateur de l'activité peut modifier
    $current_user_id = $_SESSION['id'];
    $is_admin = false;
    $is_organizer = false;
    
    // Vérifier si l'utilisateur est admin (droits = 2)
    $admin_check = mysqli_query($conn, "SELECT droits FROM membres WHERE `id-membre` = " . (int)$current_user_id);
    if (!$admin_check) {
        error_log("Admin check failed: " . mysqli_error($conn));
        echo json_encode(['success' => false, 'error' => 'Erreur SQL']);
        mysqli_close($conn);
        exit;
    }
    
    $admin_row = mysqli_fetch_assoc($admin_check);
    $is_admin = ($admin_row && (int)$admin_row['droits'] == 2);
    
    // Vérifier si l'utilisateur est l'organisateur de l'activité
    $organizer_check = mysqli_query($conn, "SELECT `id-membre` FROM activite WHERE `id-activite` = " . (int)$id_activite);
    if (!$organizer_check) {
        error_log("Organizer check failed: " . mysqli_error($conn));
        echo json_encode(['success' => false, 'error' => 'Erreur SQL']);
        mysqli_close($conn);
        exit;
    }
    $organizer_row = mysqli_fetch_assoc($organizer_check);
    $is_organizer = ($organizer_row && (int)$organizer_row['id-membre'] == (int)$current_user_id);
    
    // Rejeter si l'utilisateur n'a pas les permissions
    if (!$is_admin && !$is_organizer) {
        http_response_code(403);
        error_log("Permission denied for user $current_user_id trying to modify activity $id_activite");
        echo json_encode(['success' => false, 'error' => 'Vous n\'avez pas la permission de modifier cette activité']);
        mysqli_close($conn);
        exit;
    }
    
    mysqli_begin_transaction($conn);
    
    try {
        foreach ($updates as $update) {
            $id_membre = (int)$update['id_membre'];
            $valide = $update['valide'];
            
            error_log("Processing update for member $id_membre with valide=$valide");
            
            // Déterminer la valeur de 'option' en fonction de 'valide'
            $option = ($valide === 'Actif') ? 'Présent' : 'Réservation';
            
            $sql = "UPDATE participation 
                    SET `valide` = ?, `option` = ?, `ds` = NOW()
                    WHERE `id-membre` = ? AND `id-activite` = ?";
            
            $stmt = mysqli_prepare($conn, $sql);
            if (!$stmt) {
                throw new Exception("Failed to prepare statement: " . mysqli_error($conn) . " | SQL: " . $sql);
            }
            
            // Bind parameters: string, string, int, int
            if (!mysqli_stmt_bind_param($stmt, "ssii", $valide, $option, $id_membre, $id_activite)) {
                throw new Exception("Failed to bind parameters: " . mysqli_error($conn));
            }
            
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception("Failed to update member $id_membre: " . mysqli_stmt_error($stmt) . " | SQL: " . $sql);
            }
            
            $affected = mysqli_stmt_affected_rows($stmt);
            error_log("Updated member $id_membre - Affected rows: $affected");
            mysqli_stmt_close($stmt);
        }
        
        mysqli_commit($conn);
        error_log("All updates committed successfully");
        echo json_encode(['success' => true, 'message' => 'Updates completed']);
        
    } catch (Exception $e) {
        mysqli_rollback($conn);
        $error_msg = $e->getMessage();
        error_log("Error during update: " . $error_msg);
        echo json_encode(['success' => false, 'error' => 'Erreur: ' . $error_msg]);
    } finally {
        mysqli_close($conn);
    }
    }
} catch (Exception $e) {
    http_response_code(500);
    error_log("Exception in update_all_participants.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Exception: ' . $e->getMessage()]);
} catch (Throwable $t) {
    http_response_code(500);
    error_log("Error in update_all_participants.php: " . $t->getMessage());
    echo json_encode(['success' => false, 'error' => 'Erreur: ' . $t->getMessage()]);
}
