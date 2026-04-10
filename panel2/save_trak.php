<?php
session_start();
header('Content-Type: application/json');

if (empty($_SESSION['id'])) {
    echo json_encode(['success' => false, 'error' => 'Non authentifié']);
    exit;
}

include('include/config.php');

$conn = mysqli_connect('localhost', 'root', 'Kookies7*', 'dbs9616600');
if (!$conn) {
    echo json_encode(['success' => false, 'error' => 'Connexion BDD échouée']);
    exit;
}
mysqli_set_charset($conn, 'utf8mb4');

// Créer la table trak si elle n'existe pas encore
mysqli_query($conn, "CREATE TABLE IF NOT EXISTS `trak` (
    `id`          INT AUTO_INCREMENT PRIMARY KEY,
    `id_auteur`   INT NOT NULL COMMENT 'id-membre du joueur qui écrit la note',
    `id_cible`    INT NOT NULL COMMENT 'id-membre du participant noté',
    `id_activite` INT NOT NULL DEFAULT 0 COMMENT 'Activité concernée (0 = global)',
    `note`        TEXT NOT NULL,
    `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_cible_activite (`id_cible`, `id_activite`),
    INDEX idx_auteur (`id_auteur`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$id_auteur   = (int)$_SESSION['id'];
$id_cible    = isset($_POST['id_cible'])    ? (int)$_POST['id_cible']    : 0;
$id_activite = isset($_POST['id_activite']) ? (int)$_POST['id_activite'] : 0;
$note        = isset($_POST['note'])        ? trim($_POST['note'])        : '';

if (!$id_cible || $note === '') {
    echo json_encode(['success' => false, 'error' => 'Données manquantes (id_cible ou note vide)']);
    exit;
}

$stmt = $conn->prepare(
    "INSERT INTO trak (id_auteur, id_cible, id_activite, note) VALUES (?, ?, ?, ?)"
);
$stmt->bind_param('iiis', $id_auteur, $id_cible, $id_activite, $note);

if ($stmt->execute()) {
    $new_id = $stmt->insert_id;
    // Récupérer le pseudo de l'auteur pour le retourner au client
    $res = mysqli_query($conn, "SELECT pseudo FROM membres WHERE `id-membre` = $id_auteur LIMIT 1");
    $auteur_pseudo = '';
    if ($res && $row = mysqli_fetch_assoc($res)) {
        $auteur_pseudo = $row['pseudo'];
    }
    echo json_encode([
        'success'       => true,
        'id'            => $new_id,
        'auteur_pseudo' => $auteur_pseudo,
        'created_at'    => date('Y-m-d H:i:s')
    ]);
} else {
    echo json_encode(['success' => false, 'error' => $conn->error]);
}

$stmt->close();
$conn->close();
