<?php
// 1. On démarre le buffer immédiatement pour capturer toute sortie parasite
ob_start();

session_start();
// On désactive l'affichage des erreurs pour ne pas casser le JSON
error_reporting(E_ALL);
ini_set('display_errors', 0);

include('include/config.php');

// On définit le header JSON
header('Content-Type: application/json; charset=utf-8');

// Fonction pour nettoyer et envoyer la réponse JSON proprement
function sendResponse($data) {
    // On efface tout ce qui a pu être affiché avant (warnings, erreurs PHP...)
    ob_clean();
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(['status' => 'error', 'message' => 'Méthode non autorisée']);
}

$victim_id = isset($_POST['victim_id']) ? intval($_POST['victim_id']) : 0;
$eliminator_id = isset($_POST['eliminator_id']) ? intval($_POST['eliminator_id']) : 0;
$eliminator_name = isset($_POST['eliminator_name']) ? mysqli_real_escape_string($con, $_POST['eliminator_name']) : '';
$is_definitive = isset($_POST['is_definitive']) ? intval($_POST['is_definitive']) : 0;
$activity_id = isset($_POST['activity_id']) ? intval($_POST['activity_id']) : 0;

if ($victim_id <= 0) {
    sendResponse(['status' => 'error', 'message' => 'ID victime manquant']);
}

// --- 1. Récupération / Vérification des données ---

// Si l'ID activité est manquant
if ($activity_id == 0) {
    // Table participation : id-activite (avec tiret)
    $act_query = mysqli_query($con, "SELECT `id-activite` FROM `participation` WHERE `id-participation` = '$victim_id'");
    if ($act_row = mysqli_fetch_array($act_query)) {
        $activity_id = intval($act_row['id-activite']);
    }
}

// Si id éliminateur non fourni
if ($eliminator_id <= 0 && $eliminator_name !== '') {
    // Table membres : id-membre (avec tiret)
    $membre_query = "SELECT `id-membre` FROM `membres` WHERE `pseudo` = '$eliminator_name' LIMIT 1";
    $membre_result = mysqli_query($con, $membre_query);
    if ($membre_result && mysqli_num_rows($membre_result) > 0) {
        $membre_row = mysqli_fetch_array($membre_result);
        $eliminator_id = intval($membre_row['id-membre']);
    }
}

// Si nom éliminateur non fourni
if ($eliminator_name === '' && $eliminator_id > 0) {
    $membre_query = "SELECT `pseudo` FROM `membres` WHERE `id-membre` = '$eliminator_id' LIMIT 1";
    $membre_result = mysqli_query($con, $membre_query);
    if ($membre_result && mysqli_num_rows($membre_result) > 0) {
        $membre_row = mysqli_fetch_array($membre_result);
        $eliminator_name = mysqli_real_escape_string($con, $membre_row['pseudo']);
    }
}

// Infos victime
$victim_member_id = 0;
$victim_member_name = '';
// Table participation : id-membre, nom-membre (avec tirets)
$victim_query = "SELECT `id-membre`, `nom-membre` FROM `participation` WHERE `id-participation` = '$victim_id' LIMIT 1";
$victim_result = mysqli_query($con, $victim_query);
if ($victim_result && mysqli_num_rows($victim_result) > 0) {
    $victim_row = mysqli_fetch_array($victim_result);
    $victim_member_id = intval($victim_row['id-membre']);
    $victim_member_name = mysqli_real_escape_string($con, $victim_row['nom-membre']);
}

// --- 2. Historique (Table eliminations) ---
// CORRECTION MAJEURE : Utilisation des noms de colonnes avec UNDERSCORES (_) comme dans votre SQL
$ins_sql = "INSERT INTO `eliminations` 
    (`id_participation`, `id_membre`, `nom_membre`, `id_membre_victime`, `nom_membre_victime`, `is_definitive`, `id_activite`, `created_at`) 
    VALUES 
    ('$victim_id', '$eliminator_id', '$eliminator_name', '$victim_member_id', '$victim_member_name', '$is_definitive', '$activity_id', NOW())";

// On exécute silencieusement
@mysqli_query($con, $ins_sql); 

// --- 3. Calcul des pertes ---
if ($is_definitive == 1) {
    $nouvelles_pertes = 3;
    $est_elimine = true;
} else {
    $check_query = "SELECT `pertes` FROM `participation` WHERE `id-participation` = '$victim_id' LIMIT 1";
    $check_result = mysqli_query($con, $check_query);
    $pertes_actuelles = 0;
    if ($check_result && mysqli_num_rows($check_result) > 0) {
        $check_row = mysqli_fetch_array($check_result);
        $pertes_actuelles = intval($check_row['pertes']);
    }
    $nouvelles_pertes = $pertes_actuelles + 1;
    $est_elimine = false; 
}

// --- 4. Mise à jour Participation (UPDATE) ---
// Table participation : utilise des TIRETS (-)
$classement = null;

if ($est_elimine) {
    // --- CAS DÉFINITIF : On calcule le classement ---
    
    // Compter le nombre TOTAL de participants
    $count_total_query = mysqli_query($con, "SELECT COUNT(*) as total FROM `participation` WHERE `id-activite` = '$activity_id'");
    $row_total = mysqli_fetch_assoc($count_total_query);
    $total_joueurs = intval($row_total['total']);

    // Compter le nombre de joueurs DÉJÀ ÉLIMINÉS (classement > 0)
    $count_elim_query = mysqli_query($con, "SELECT COUNT(*) as elimines FROM `participation` WHERE `id-activite` = '$activity_id' AND `classement` > 0 AND `id-participation` != '$victim_id'");
    $row_elim = mysqli_fetch_assoc($count_elim_query);
    $nb_deja_elimines = intval($row_elim['elimines']);

    // Calcul : Total - Déjà sortis
    $classement = $total_joueurs - $nb_deja_elimines;
    if ($classement < 1) $classement = 1;

    $update_sql = "UPDATE `participation` SET 
        `nom-membre-vainqueur` = '$eliminator_name', 
        `id-membre-vainqueur` = '$eliminator_id', 
        `pertes` = '$nouvelles_pertes',
        `classement` = '$classement'
        WHERE `id-participation` = '$victim_id'";

} else {
    // --- CAS NON DÉFINITIF ---
    // On met à jour les pertes ET l'éliminateur (pour l'affichage "Sorti par")
    // MAIS on ne touche PAS au classement.
    
    $update_sql = "UPDATE `participation` SET 
        `nom-membre-vainqueur` = '$eliminator_name', 
        `id-membre-vainqueur` = '$eliminator_id', 
        `pertes` = '$nouvelles_pertes'
        WHERE `id-participation` = '$victim_id'";
}

// Exécution
$update_result = mysqli_query($con, $update_sql);

if (!$update_result) {
    sendResponse(['status' => 'error', 'message' => 'Erreur SQL UPDATE: '.mysqli_error($con)]);
}

// Si on vient de classer un joueur 2e, attribuer 1er au dernier joueur encore non classé (le vainqueur)
if ($est_elimine && $classement == 2) {
    $winner_q = mysqli_query($con, "SELECT `id-participation` FROM `participation` WHERE `id-activite` = '$activity_id' AND `classement` = 0 LIMIT 1");
    if ($winner_q && mysqli_num_rows($winner_q) > 0) {
        $winner_row = mysqli_fetch_assoc($winner_q);
        $winner_participation_id = intval($winner_row['id-participation']);
        mysqli_query($con, "UPDATE `participation` SET `classement` = 1 WHERE `id-participation` = $winner_participation_id");
    }
}

$affected_rows = mysqli_affected_rows($con);

// Envoi de la réponse finale
sendResponse([
    'status' => 'success', 
    'message' => $est_elimine ? 'Joueur éliminé (Classé '.$classement.'e)' : 'Perte enregistrée ('.$nouvelles_pertes.'/3)', 
    'affected_rows' => $affected_rows, 
    'pertes_updated' => $nouvelles_pertes,
    'classement_calcule' => $classement
]);
?>
