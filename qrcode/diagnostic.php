<?php
/**
 * Script de diagnostic pour vérifier la connexion SQL
 */

echo "<h2>Diagnostic de connexion SQL</h2>";

// Afficher le chemin du fichier config
$config_path = dirname(dirname(__FILE__)) . '/config.php';
echo "<p><strong>Chemin config.php attendu:</strong> " . $config_path . "</p>";
echo "<p><strong>Fichier existe:</strong> " . (file_exists($config_path) ? "✓ OUI" : "✗ NON") . "</p>";

if (file_exists($config_path)) {
    echo "<hr>";
    
    // Inclure config
    require_once $config_path;
    
    echo "<p><strong>Variable \$conx existe:</strong> " . (isset($conx) ? "✓ OUI" : "✗ NON") . "</p>";
    
    if (isset($conx)) {
        echo "<p><strong>Type de \$conx:</strong> " . gettype($conx) . "</p>";
        echo "<p><strong>\$conx est une ressource valide:</strong> " . (is_object($conx) || is_resource($conx) ? "✓ OUI" : "✗ NON") . "</p>";
        
        // Vérifier la connexion
        if ($conx) {
            echo "<p style='color: green;'><strong>✓ Connexion RÉUSSIE!</strong></p>";
            
            // Afficher les informations de connexion
            echo "<p><strong>Serveur connecté:</strong> " . $conx->server_info . "</p>";
            echo "<p><strong>Charset:</strong> " . $conx->character_set_name() . "</p>";
            
            // Tester une requête simple
            echo "<hr>";
            echo "<h3>Test de requête</h3>";
            
            $result = $conx->query("SELECT 1 as test");
            if ($result) {
                echo "<p style='color: green;'><strong>✓ Requête SELECT fonctionnelle</strong></p>";
                $row = $result->fetch_assoc();
                echo "<p>Résultat: " . print_r($row, true) . "</p>";
            } else {
                echo "<p style='color: red;'><strong>✗ Erreur lors de la requête: " . $conx->error . "</strong></p>";
            }
            
            // Vérifier la table collections
            echo "<hr>";
            echo "<h3>Vérification de la table collections</h3>";
            
            $tables = $conx->query("SHOW TABLES LIKE 'collections'");
            if ($tables && $tables->num_rows > 0) {
                echo "<p style='color: green;'><strong>✓ Table 'collections' existe</strong></p>";
                
                // Afficher la structure
                $structure = $conx->query("DESCRIBE collections");
                if ($structure) {
                    echo "<p><strong>Structure de la table:</strong></p>";
                    echo "<table border='1' cellpadding='5'>";
                    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
                    while ($col = $structure->fetch_assoc()) {
                        echo "<tr>";
                        echo "<td>" . $col['Field'] . "</td>";
                        echo "<td>" . $col['Type'] . "</td>";
                        echo "<td>" . $col['Null'] . "</td>";
                        echo "<td>" . $col['Key'] . "</td>";
                        echo "<td>" . $col['Default'] . "</td>";
                        echo "<td>" . $col['Extra'] . "</td>";
                        echo "</tr>";
                    }
                    echo "</table>";
                }
            } else {
                echo "<p style='color: red;'><strong>✗ Table 'collections' N'EXISTE PAS</strong></p>";
                echo "<p>Créez-la avec cette requête:</p>";
                echo "<pre>";
                echo "CREATE TABLE collections (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);";
                echo "</pre>";
            }
            
        } else {
            echo "<p style='color: red;'><strong>✗ Connexion ÉCHOUÉE: " . mysqli_connect_error() . "</strong></p>";
        }
    } else {
        echo "<p style='color: red;'><strong>✗ Variable \$conx non définie</strong></p>";
    }
} else {
    echo "<p style='color: red;'><strong>✗ Fichier config.php NOT FOUND!</strong></p>";
}

echo "<hr>";
echo "<p><a href='read_qrcode.php'>← Retour</a></p>";
?>
