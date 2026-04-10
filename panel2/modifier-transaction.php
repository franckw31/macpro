<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
include('include/config.php');

// Vérification connexion utilisateur
if(strlen($_SESSION['id'])==0) { 
    header('location:logout.php');
    exit;
}

// Fonction de mise à jour du solde
function updateMemberBalance($membre_id, $con) {
    try {
        // Calcul du solde : somme des entrées - somme des sorties
        $query = "SELECT 
            COALESCE(SUM(CASE WHEN id_type_mvt = 1 THEN montant ELSE 0 END), 0) -
            COALESCE(SUM(CASE WHEN id_type_mvt = 2 THEN montant ELSE 0 END), 0) as balance
            FROM portefeuille 
            WHERE id_mvt_membre = ?";
            
        $stmt = mysqli_prepare($con, $query);
        if (!$stmt) {
            throw new Exception("Erreur préparation requête solde: " . mysqli_error($con));
        }

        mysqli_stmt_bind_param($stmt, 'i', $membre_id);
        
        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception("Erreur calcul solde: " . mysqli_stmt_error($stmt));
        }

        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);
        $balance = $row['balance'];
        
        // Debug
        error_log("Nouveau solde calculé pour membre #$membre_id : $balance");
        
        // Mise à jour du solde dans la table membres
        $update = mysqli_prepare($con, "UPDATE membres SET solde = ? WHERE `id-membre` = ?");
        if (!$update) {
            throw new Exception("Erreur préparation mise à jour solde: " . mysqli_error($con));
        }

        mysqli_stmt_bind_param($update, 'di', $balance, $membre_id);
        
        if (!mysqli_stmt_execute($update)) {
            throw new Exception("Erreur mise à jour solde: " . mysqli_stmt_error($update));
        }
        
        return true;

    } catch (Exception $e) {
        error_log("Erreur updateMemberBalance: " . $e->getMessage());
        throw $e; // Relancer l'exception pour la gestion plus haut
    }
}

$id_transaction = intval($_GET['id']);
$msg = '';
$error = '';

// Traitement du formulaire de modification
if(isset($_POST['submit'])) {
    try {
        // Validation des données
        if (!isset($_POST['montant']) || !isset($_POST['id_type_mvt'])) {
            throw new Exception("Tous les champs sont obligatoires");
        }

        $montant = floatval($_POST['montant']);
        $id_type_mvt = intval($_POST['id_type_mvt']);
        $date_mvt = !empty($_POST['date_mvt']) ? $_POST['date_mvt'] : date('Y-m-d');
        $id_membre = intval($_POST['id_membre']);

        // Début de la transaction
        mysqli_begin_transaction($con);

        // Mise à jour de la transaction
        $stmt = mysqli_prepare($con, "UPDATE portefeuille SET montant = ?, id_type_mvt = ?, date_mvt = ? WHERE id_mvt = ?");
        mysqli_stmt_bind_param($stmt, 'disi', $montant, $id_type_mvt, $date_mvt, $id_transaction);

        if(!mysqli_stmt_execute($stmt)) {
            throw new Exception("Erreur lors de la mise à jour: " . mysqli_stmt_error($stmt));
        }

        // Mise à jour du solde
        if (!updateMemberBalance($id_membre, $con)) {
            throw new Exception("Erreur lors de la mise à jour du solde");
        }

        mysqli_commit($con);
        header("Location: voir-membre.php?id=" . $id_membre . "#portefeuilleE");
        exit;

    } catch (Exception $e) {
        mysqli_rollback($con);
        $error = $e->getMessage();
    }
}

// Récupération des données de la transaction
$stmt = mysqli_prepare($con, "SELECT p.*, m.`id-membre` FROM portefeuille p 
                            JOIN membres m ON p.id_mvt_membre = m.`id-membre`
                            WHERE p.id_mvt = ?");
mysqli_stmt_bind_param($stmt, 'i', $id_transaction);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$transaction = mysqli_fetch_assoc($result);

if(!$transaction) {
    header("Location: voir-membre.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <title>Modifier Transaction</title>
    <!-- Inclure vos CSS -->
</head>
<body>
    <div class="container">
        <h2>Modifier la Transaction #<?php echo $id_transaction; ?></h2>
        
        <?php if($error) : ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <form method="post" class="form">
            <input type="hidden" name="id_membre" value="<?php echo $transaction['id-membre']; ?>">
            
            <div class="form-group">
                <label>Type</label>
                <select class="form-control" name="id_type_mvt" required>
                    <option value="1" <?php echo $transaction['id_type_mvt'] == 1 ? 'selected' : ''; ?>>Entrée</option>
                    <option value="2" <?php echo $transaction['id_type_mvt'] == 2 ? 'selected' : ''; ?>>Sortie</option>
                </select>
            </div>

            <div class="form-group">
                <label>Montant</label>
                <input type="number" step="0.01" class="form-control" name="montant" 
                       value="<?php echo $transaction['montant']; ?>" required>
            </div>

            <div class="form-group">
                <label>Date</label>
                <input type="date" class="form-control" name="date_mvt" 
                       value="<?php echo $transaction['date_mvt']; ?>">
            </div>

            <div class="form-group">
                <button type="submit" name="submit" class="btn btn-primary">Enregistrer</button>
                <a href="voir-membre.php?id=<?php echo $transaction['id-membre']; ?>#portefeuilleE" 
                   class="btn btn-default">Annuler</a>
            </div>
        </form>
    </div>
</body>
</html>