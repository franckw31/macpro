<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

session_start();
// Diagnostic logging to help debug missing session during AJAX calls
error_log('update_field.php - Cookies: ' . var_export($_COOKIE, true));
error_log('update_field.php - session_id: ' . session_id());
error_log('update_field.php - _SESSION before check: ' . var_export($_SESSION, true));
header('Content-Type: application/json');

try {
    include('include/config.php');

    if (strlen($_SESSION['id']) == 0) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $id_membre = isset($_POST['id_membre']) ? (int)$_POST['id_membre'] : 0;
        $id_activite = isset($_POST['id_activite']) ? (int)$_POST['id_activite'] : 0;
        $field = isset($_POST['field']) ? $_POST['field'] : '';
        $value = isset($_POST['value']) ? $_POST['value'] : '';
        
        error_log("update_field.php: POST data - id_membre=$id_membre, id_activite=$id_activite, field=$field, value=$value");
        
        $conn = mysqli_connect('localhost', 'root', 'Kookies7*', 'dbs9616600');
        
        if (!$conn) {
            http_response_code(500);
            $error = 'Erreur de connexion à la base de données: ' . mysqli_connect_error();
            error_log("Database connection error: " . $error);
            echo json_encode(['success' => false, 'error' => $error]);
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
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Erreur SQL: ' . mysqli_error($conn)]);
        mysqli_close($conn);
        exit;
    }
    
    $admin_row = mysqli_fetch_assoc($admin_check);
    $is_admin = ($admin_row && (int)$admin_row['droits'] == 2);
    
    // Vérifier si l'utilisateur est l'organisateur de l'activité
    if ($id_activite > 0) {
        $organizer_check = mysqli_query($conn, "SELECT `id-membre` FROM activite WHERE `id-activite` = " . (int)$id_activite);
        if (!$organizer_check) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Erreur SQL: ' . mysqli_error($conn)]);
            mysqli_close($conn);
            exit;
        }
        $organizer_row = mysqli_fetch_assoc($organizer_check);
        $is_organizer = ($organizer_row && (int)$organizer_row['id-membre'] == (int)$current_user_id);
    }
    
    // Rejeter si l'utilisateur n'a pas les permissions
    if (!$is_admin && !$is_organizer) {
        http_response_code(403);
        error_log("Permission denied for user $current_user_id trying to modify activity $id_activite");
        echo json_encode(['success' => false, 'error' => 'Vous n\'avez pas la permission de modifier cette activité']);
        mysqli_close($conn);
        exit;
    }
    
    error_log("Updating field: $field with value: $value for membre: $id_membre and activite: $id_activite");
    
    // Validate field name - only fields that exist in 'participation' table
    $allowed_fields = [
        'classement', 'recave', 'points', 'tf',
        'rake', 'rake_0', 'rake_5', 'rake_10', 'rake_12', 'rake_15', 'rake_20',
        'cout_in', 'latereg', 'option', 'valide', 'gain',
        'addon', 'win', 'bonbon', 'ordre', 'position', 'heure_arrivee', 'jetons_bonus_arrivee', 'jetons_bonus_ins', 'tombolas'
    ];
    if (!in_array($field, $allowed_fields)) {
        error_log("Invalid field: $field");
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Field not allowed']);
        exit;
    }

    // Convert value based on field type
    if ($field === 'tf') {
        $value = $value === '1' ? 1 : 0;
        $param_type = "i";
    } elseif ($field === 'option' || $field === 'valide') {
        $param_type = "s";
    } elseif ($field === 'jetons_bonus_arrivee' || $field === 'jetons_bonus_ins') {
        $value = (int)$value;
        $param_type = "i";
    } else {
        $param_type = "i"; // All other fields are integers
        $value = (int)$value;
    }

    // Logic for synchronization - build complete SQL
    if ($field === 'option') {
        if ($value === 'Présent') {
            $sql = "UPDATE participation 
                    SET `option` = ?, `valide` = 'Actif', `heure_arrivee` = NOW()
                    WHERE `id-membre` = ? 
                    AND `id-activite` = ?";
        } else {
            $sql = "UPDATE participation 
                    SET `option` = ?, `valide` = 'Inactif', `heure_arrivee` = NULL
                    WHERE `id-membre` = ? 
                    AND `id-activite` = ?";
            $value = 'Inscrit'; // Quand on décoche, mettre 'Inscrit'
        }
    } elseif ($field === 'valide') {
        if ($value === 'Actif') {
            $sql = "UPDATE participation 
                    SET `valide` = ?, `option` = 'Présent', `ds` = NOW()
                    WHERE `id-membre` = ? 
                    AND `id-activite` = ?";
        } else {
            $sql = "UPDATE participation 
                    SET `valide` = ?, `option` = 'Réservation', `ds` = NOW()
                    WHERE `id-membre` = ? 
                    AND `id-activite` = ?";
        }
    } elseif ($field === 'heure_arrivee') {
        // Si true (coché), mettre NOW(), sinon NULL
        if ($value === 'true' || $value === '1') {
            $sql = "UPDATE participation 
                    SET `heure_arrivee` = NOW()
                    WHERE `id-membre` = ? 
                    AND `id-activite` = ?";
            // Adapter pour cette requête : on n'a que 2 paramètres (id_membre, id_activite)
            $param_type = "";
        } else {
            $sql = "UPDATE participation 
                    SET `heure_arrivee` = NULL
                    WHERE `id-membre` = ? 
                    AND `id-activite` = ?";
            // Adapter pour cette requête : on n'a que 2 paramètres (id_membre, id_activite)
            $param_type = "";
        }
    } elseif ($field === 'jetons_bonus_arrivee') {
        // Pour jetons_bonus_arrivee, on met directement la valeur et on recalcule jetons_total
        $sql = "UPDATE participation 
                SET `jetons_bonus_arrivee` = ?,
                    `jetons_total` = `jetons` + `jetons_bonus_ins` + ?
                WHERE `id-membre` = ? 
                AND `id-activite` = ?";
    } elseif ($field === 'jetons_bonus_ins') {
        // Pour jetons_bonus_ins, on met directement la valeur et on recalcule jetons_total
        $sql = "UPDATE participation 
                SET `jetons_bonus_ins` = ?,
                    `jetons_total` = `jetons` + ? + `jetons_bonus_arrivee`
                WHERE `id-membre` = ? 
                AND `id-activite` = ?";
    } else {
        $sql = "UPDATE participation 
                SET `" . mysqli_real_escape_string($conn, $field) . "` = ?, `ds` = NOW()
                WHERE `id-membre` = ? 
                AND `id-activite` = ?";
    }
    
    error_log("SQL Query: $sql");
    error_log("Params: field=$field, value=$value, id_membre=$id_membre, id_activite=$id_activite");
    
    $stmt = mysqli_prepare($conn, $sql);
    
    if (!$stmt) {
        http_response_code(500);
        $error_msg = mysqli_error($conn);
        echo json_encode(['success' => false, 'error' => 'Erreur de préparation SQL: ' . $error_msg . ' | SQL: ' . $sql]);
        error_log("Prepare error: " . $error_msg . " | SQL: " . $sql);
        mysqli_close($conn);
        exit;
    }
    
    // Bind parameters with correct type order: value type, id_membre (int), id_activite (int)
    if ($field === 'heure_arrivee') {
        // Pour heure_arrivee, on n'a que 2 paramètres : id_membre et id_activite
        $bind_types = "ii";
        mysqli_stmt_bind_param($stmt, $bind_types, $id_membre, $id_activite);
    } elseif ($field === 'jetons_bonus_arrivee' || $field === 'jetons_bonus_ins') {
        // Pour jetons_bonus_arrivee/ins, on a 4 paramètres : value (int), value2 (int pour jetons_total), id_membre (int), id_activite (int)
        $bind_types = "iiii";
        mysqli_stmt_bind_param($stmt, $bind_types, $value, $value, $id_membre, $id_activite);
    } else {
        $bind_types = $param_type . "ii";
        mysqli_stmt_bind_param($stmt, $bind_types, $value, $id_membre, $id_activite);
    }
    
    if (mysqli_stmt_execute($stmt)) {
        $affected = mysqli_stmt_affected_rows($stmt);
        error_log("Rows affected: $affected");
        echo json_encode(['success' => true, 'affected' => $affected]);
    } else {
        $error_msg = mysqli_stmt_error($stmt);
        error_log("Execute error: " . $error_msg);
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Erreur SQL: ' . $error_msg]);
    }
    
    mysqli_stmt_close($stmt);
    mysqli_close($conn);    }
} catch (Exception $e) {
    http_response_code(500);
    error_log("Exception in update_field.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Exception: ' . $e->getMessage()]);
} catch (Throwable $t) {
    http_response_code(500);
    error_log("Error in update_field.php: " . $t->getMessage());
    echo json_encode(['success' => false, 'error' => 'Erreur: ' . $t->getMessage()]);
}