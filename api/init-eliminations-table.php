<?php
/**
 * init-eliminations-table.php - Initialize eliminations table
 * Run this once to create the table structure
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

try {
    $pdo = new PDO(
        'mysql:host=localhost;dbname=dbs9616600;charset=utf8mb4',
        'root',
        'Kookies7*',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // Create table if not exists
    $sql = "
    CREATE TABLE IF NOT EXISTS `eliminations` (
      `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
      `id_participation` INT NOT NULL,
      `nom_membre` VARCHAR(255) NOT NULL COMMENT 'Nom du joueur qui a éliminé',
      `id_member_eliminator` INT DEFAULT 0 COMMENT 'ID du joueur qui a éliminé',
      `is_definitive` TINYINT(1) DEFAULT 0 COMMENT '1 = Bust définitif, 0 = Recave',
      `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      INDEX `idx_id_participation` (`id_participation`),
      INDEX `idx_nom_membre` (`nom_membre`),
      INDEX `idx_created_at` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";

    $pdo->exec($sql);
    
    // Verify table exists
    $stmt = $pdo->prepare("SHOW TABLES LIKE 'eliminations'");
    $stmt->execute();
    $result = $stmt->fetch();
    
    if ($result) {
        echo json_encode([
            'success' => true, 
            'message' => 'Table eliminations créée/vérifiée avec succès'
        ]);
    } else {
        echo json_encode([
            'success' => false, 
            'message' => 'Erreur: Table non trouvée après création'
        ]);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Erreur: ' . $e->getMessage()
    ]);
}
?>
