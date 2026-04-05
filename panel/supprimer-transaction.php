<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
include('include/config.php');

// Fonction de mise à jour du solde (copie de celle de voir-membre.php)
function updateMemberBalance($membre_id, $con) {
    try {
        // Calcul du solde : somme des entrées - somme des sorties
        $query = "SELECT 
            COALESCE(SUM(CASE WHEN id_type_mvt = 1 THEN montant ELSE 0 END), 0) -
            COALESCE(SUM(CASE WHEN id_type_mvt = 2 THEN montant ELSE 0 END), 0) as balance
            FROM portefeuille 
            WHERE id_mvt_membre = ?";
            
        $stmt = mysqli_prepare($con, $query);
        mysqli_stmt_bind_param($stmt, 'i', $membre_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);
        $balance = $row['balance'];
        
        // Mise à jour du solde dans la table membres
        $update = mysqli_prepare($con, "UPDATE membres SET solde = ? WHERE `id-membre` = ?");
        mysqli_stmt_bind_param($update, 'di', $balance, $membre_id);
        mysqli_stmt_execute($update);
        
        return true;
    } catch (Exception $e) {
        error_log("Erreur mise à jour solde: " . $e->getMessage());
        return false;
    }
}

// Vérification connexion utilisateur
if(strlen($_SESSION['id'])==0) { 
    header('location:logout.php');
    exit;
}

// Initialiser $id_membre
$id_membre = 0;

// Vérification ID transaction
if(!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = "ID de transaction manquant";
    header('location:voir-membre.php');
    exit;
}

try {
    // Récupérer l'ID de la transaction
    $id_transaction = intval($_GET['id']);
    
    // Vérifier la connexion BD
    if(!$con) {
        throw new Exception("Erreur de connexion à la base de données");
    }
    
    // Récupérer l'ID du membre avant suppression
    $stmt = mysqli_prepare($con, "SELECT id_mvt_membre FROM portefeuille WHERE id_mvt = ?");
    if(!$stmt) {
        throw new Exception("Erreur de préparation de la requête select: " . mysqli_error($con));
    }
    
    mysqli_stmt_bind_param($stmt, 'i', $id_transaction);
    
    if(!mysqli_stmt_execute($stmt)) {
        throw new Exception("Erreur d'exécution de la requête select: " . mysqli_stmt_error($stmt));
    }
    
    mysqli_stmt_store_result($stmt);
    
    if(mysqli_stmt_num_rows($stmt) == 0) {
        throw new Exception("Transaction #" . $id_transaction . " non trouvée");
    }
    
    mysqli_stmt_bind_result($stmt, $id_membre);
    mysqli_stmt_fetch($stmt);
    mysqli_stmt_close($stmt);

    // Début de la transaction
    mysqli_begin_transaction($con);

    // Supprimer la transaction
    $stmt = mysqli_prepare($con, "DELETE FROM portefeuille WHERE id_mvt = ?");
    if(!$stmt) {
        throw new Exception("Erreur de préparation de la requête delete: " . mysqli_error($con));
    }

    mysqli_stmt_bind_param($stmt, 'i', $id_transaction);

    if(!mysqli_stmt_execute($stmt)) {
        throw new Exception("Erreur lors de la suppression: " . mysqli_stmt_error($stmt));
    }

    if(mysqli_stmt_affected_rows($stmt) == 0) {
        throw new Exception("Aucune transaction n'a été supprimée");
    }

    // Mettre à jour le solde après suppression
    if (!updateMemberBalance($id_membre, $con)) {
        throw new Exception("Erreur lors de la mise à jour du solde");
    }

    // Valider la transaction
    mysqli_commit($con);

    $_SESSION['msg'] = "Transaction #" . $id_transaction . " supprimée avec succès";

} catch (Exception $e) {
    // Annuler la transaction en cas d'erreur
    mysqli_rollback($con);
    $_SESSION['error'] = $e->getMessage();
} finally {
    if(isset($stmt)) {
        mysqli_stmt_close($stmt);
    }
    // Redirection avec l'onglet portefeuille actif
    header("Location: voir-membre.php?id=" . $id_membre . "#portefeuilleE");
    exit;
}
?>