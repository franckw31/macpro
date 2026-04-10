<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
include('include/config.php');

header('Content-Type: application/json');

try {
    // Vérification de la connexion
    if (!$conn) {
        throw new Exception("Erreur de connexion à la base de données");
    }

    // Vérification des champs requis
    if (!isset($_POST['title'], $_POST['start_date'])) {
        throw new Exception('Les champs titre et date de début sont requis');
    }

    // Préparation des données
    $title = trim($_POST['title']);
    $buyin = isset($_POST['buyin']) ? intval($_POST['buyin']) : 0;
    $date_depart = date('Y-m-d H:i:s', strtotime($_POST['start_date']));
    $heure_depart = !empty($_POST['heure_depart']) ? 
                    date('Y-m-d H:i:s', strtotime($_POST['heure_depart'])) : 
                    $date_depart;
    $ville = isset($_POST['ville']) ? trim($_POST['ville']) : '';

    // Requête préparée
    $query = "INSERT INTO activite (`titre-activite`, `buyin`, `date_depart`, `heure_depart`, `ville`) 
              VALUES (?, ?, ?, ?, ?)";
              
    $stmt = mysqli_prepare($conn, $query);
    if (!$stmt) {
        throw new Exception("Erreur de préparation de la requête: " . mysqli_error($conn));
    }

    // Liaison des paramètres et exécution
    if (!mysqli_stmt_bind_param($stmt, 'sisss', $title, $buyin, $date_depart, $heure_depart, $ville)) {
        throw new Exception("Erreur de liaison des paramètres: " . mysqli_stmt_error($stmt));
    }

    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception("Erreur d'exécution de la requête: " . mysqli_stmt_error($stmt));
    }

    $id_activite = mysqli_insert_id($conn);

    // --- Création automatique du groupe de chat ---
    $months = ["", "Janvier", "Février", "Mars", "Avril", "Mai", "Juin", "Juillet", "Août", "Septembre", "Octobre", "Novembre", "Décembre"];
    $d_obj = strtotime($date_depart);
    $formatted_date = date('j', $d_obj) . ' ' . $months[intval(date('n', $d_obj))];
    
    $creator_id = isset($_SESSION['id']) ? $_SESSION['id'] : 265; // Fallback to admin if no session
    $res_org = mysqli_query($conn, "SELECT pseudo FROM membres WHERE `id-membre` = '$creator_id'");
    $row_org = mysqli_fetch_assoc($res_org);
    $organizer_name = $row_org ? $row_org['pseudo'] : "Organisateur";
    
    $new_group_name = $formatted_date . " " . $organizer_name;
    
    // 1. Récupérer le dernier groupe pour copier les membres
    
    // 1. Récupérer le dernier groupe pour copier les membres
    $res_last_grp = mysqli_query($conn, "SELECT id FROM chat_groups ORDER BY id DESC LIMIT 1");
    if ($res_last_grp && mysqli_num_rows($res_last_grp) > 0) {
        $row_last_grp = mysqli_fetch_assoc($res_last_grp);
        $last_group_id = $row_last_grp['id'];
        
        // 2. Créer le nouveau groupe
        $stmt_grp = mysqli_prepare($conn, "INSERT INTO chat_groups (name, created_by) VALUES (?, ?)");
        mysqli_stmt_bind_param($stmt_grp, "si", $new_group_name, $creator_id);
        
        if (mysqli_stmt_execute($stmt_grp)) {
            $new_group_id = mysqli_insert_id($conn);
            mysqli_stmt_close($stmt_grp);
            
            // 3. Copier les membres du dernier groupe
            $res_members = mysqli_query($conn, "SELECT member_id FROM chat_group_members WHERE group_id = $last_group_id");
            while ($member = mysqli_fetch_assoc($res_members)) {
                $m_id = $member['member_id'];
                mysqli_query($conn, "INSERT IGNORE INTO chat_group_members (group_id, member_id) VALUES ($new_group_id, $m_id)");
            }
            
            // S'assurer que le créateur est aussi dans le groupe s'il n'y était pas
            mysqli_query($conn, "INSERT IGNORE INTO chat_group_members (group_id, member_id) VALUES ($new_group_id, $creator_id)");
        }
    }
    // --- Fin création groupe ---

    // Réponse en cas de succès
    echo json_encode([
        'success' => true,
        'id' => $id_activite,
        'message' => "L'activité a été créée avec succès"
    ]);

} catch (Exception $e) {
    // Réponse en cas d'erreur
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

// Nettoyage
if (isset($stmt)) mysqli_stmt_close($stmt);
if (isset($conn)) mysqli_close($conn);
?>
