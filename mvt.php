<?php
// Inclusion of the database configuration
require_once 'config.php';

// Check if connection was successful before proceeding
if (!isset($db_connection) || $db_connection === false) {
    die("Cannot access the database connection. Check config.php.");
}

$conn = $db_connection;

/**
 * Récupère et affiche les informations générales des membres.
 * @param mysqli $conn La connexion mysqli.
 */
function afficherMembres(mysqli $conn): void {
    echo "<h2 style='color: blue;'>Liste des Membres</h2>";
    // IMPORTANT: Ajustez cette requête en fonction de la structure de votre table 'membres'
    // Exemples de colonnes attendues: id_membre, nom, email
    $sql = "SELECT id_membre, nom, email FROM membres LIMIT 10"; 
    $result = $conn->query($sql);

    if ($result === false) {
        echo "<p style='color: red;'>Erreur lors de la récupération des membres : " . mysqli_error($conn) . "</p>";
        return;
    }

    echo "<table border='1' cellpadding='5' cellspacing='0' style='width: 60%;'>\n";
    echo "<thead><tr><th>ID Membre</th><th>Nom</th><th>Email</th><th>...</th></tr></thead><tbody>\n";
    
    while ($membre = $result->fetch_assoc()) {
        // ATTENTION : Assurez-vous que les clés correspondent aux colonnes de votre DB
        echo "<tr>";
        echo "<td>" . htmlspecialchars($membre['id']) . "</td>";
        echo "<td>" . htmlspecialchars($membre['name']) . "</td>";
        echo "<td>" . htmlspecialchars($membre['email']) . "</td>";
        echo "</tr>";
    }
    echo "</tbody>";
}

/**
 * Affiche le solde cumulé d'un membre spécifique.
 */
function afficherSoldeMembre($membreId, $conn) {
    echo "<h3>Solde de l'utilisateur ID: " . htmlspecialchars($membreId) . "</h3>";
    
    // Requête pour calculer le total des transactions (doit être adapté à votre schéma de transactions)
    $sql = "SELECT SUM(montant) AS total_solde FROM transactions WHERE membre_id = ? AND date_transaction <= CURDATE()";
    
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("i", $membreId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $solde = $row['total_solde'];

        if ($solde === null) {
            echo "<p style='color: gray;'>Aucune transaction trouvée pour ce membre.</p>";
        } else {
            // Formatage monétaire
            echo "<p style='font-size: 1.5em; font-weight: bold;'>Solde Total: " . number_format($solde, 2, ',') . " €</p>";
        }
        $stmt->close();
    } else {
        echo "<p style='color: red;'>Erreur lors de la préparation de la requête de solde.</p>";
    }
}


// --- EXÉCUTION ---
// ATTENTION: Ce bloc nécessite une connexion de base de données fonctionnelle.
/*
// 1. Établir la connexion (Adaptez les informations !)
$servername = "localhost";
$username = "user";
$password = "password";
$dbname = "database_name";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// 2. Exemples d'utilisation :
afficherSoldeMembre(1, $conn); // Vérifie le solde pour le membre avec ID 1
afficherSoldeMembre(5, $conn); // Vérifie le solde pour le membre avec ID 5

$conn->close();
*/
?>