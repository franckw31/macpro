<?php
session_start();
error_reporting(0);
include('include/config.php');

// Vérifier que l'utilisateur est connecté
if (strlen($_SESSION['id']) == 0) {
    header('location:logout.php');
    exit;
}

$message = '';
$count = 0;

// Récupérer toutes les blindes-live et mettre à jour le champ 'minutes'
$query = mysqli_query($con, "SELECT `id`, `duree` FROM `blindes-live` WHERE `duree` IS NOT NULL AND `duree` != ''");

if ($query && mysqli_num_rows($query) > 0) {
    while ($row = mysqli_fetch_array($query)) {
        $id = intval($row['id']);
        $duree = $row['duree']; // Format: HH:MM:SS
        
        // Extraire les deux derniers caractères du champ duree
        $minutes = intval(substr($duree, -2));
        
        if ($minutes !== false && $minutes >= 0) {
            $minutes_totales = $minutes;
            
            // Mettre à jour le champ 'minutes'
            $update = mysqli_query($con, "UPDATE `blindes-live` SET `minutes` = '$minutes_totales' WHERE `id` = '$id'");
            
            if ($update) {
                $count++;
            }
        }
    }
    
    $message = "$count enregistrement(s) mis à jour avec succès. Le champ 'minutes' a été rempli à partir du champ 'duree'.";
} else {
    $message = "Aucun enregistrement à traiter ou erreur lors de la récupération des données.";
}

?>
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="fr">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <title>Migration Duree -> Minutes</title>
    <link rel="stylesheet" href="vendor/bootstrap/css/bootstrap.min.css">
    <style>
        body {
            padding: 20px;
            font-family: Arial, sans-serif;
        }
        .container {
            max-width: 600px;
            margin-top: 50px;
        }
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .alert-info {
            background-color: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        .btn {
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Migration des données : Duree → Minutes</h1>
        
        <div class="alert alert-info">
            <strong>Objectif :</strong> Convertir les données du champ 'duree' (format HH:MM:SS) 
            en minutes et les stocker dans le champ 'minutes' de la table 'blindes-live'.
        </div>
        
        <div class="alert alert-success">
            <strong>Résultat :</strong> <?php echo htmlspecialchars($message, ENT_QUOTES); ?>
        </div>
        
        <div>
            <p>
                <strong>Note :</strong> Une fois cette migration terminée avec succès, vous pouvez supprimer 
                le champ 'duree' de la table 'blindes-live' sans risquer une perte de données, 
                car les informations sont désormais stockées dans le champ 'minutes'.
            </p>
        </div>
        
        <div>
            <a href="voir-blindes.php" class="btn btn-primary">Retour</a>
        </div>
    </div>
</body>
</html>
