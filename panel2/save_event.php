<?php
header('Content-Type: application/json');
include('include/config.php');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Méthode non autorisée');
    }

    // Récupération et nettoyage des données
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $title = isset($_POST['title']) ? mysqli_real_escape_string($conn, trim($_POST['title'])) : '';
    $buyin = isset($_POST['buyin']) ? intval($_POST['buyin']) : 0;
    $rake = isset($_POST['rake']) ? intval($_POST['rake']) : 0;
    $recave = isset($_POST['recave']) ? intval($_POST['recave']) : 0;
    $ville = isset($_POST['ville']) ? mysqli_real_escape_string($conn, trim($_POST['ville'])) : '';
    
    // Formatage correct des dates
    $start_date = isset($_POST['start_date']) ? $_POST['start_date'] : '';
    $end_date = isset($_POST['heure_depart']) ? $_POST['heure_depart'] : '';

    // Conversion des dates au format MySQL
    $formatted_start_date = date('Y-m-d H:i:s', strtotime($start_date));
    $formatted_end_date = !empty($end_date) ? date('Y-m-d H:i:s', strtotime($end_date)) : null;

    // Vérification des données obligatoires
    if (empty($title) || empty($formatted_start_date)) {
        throw new Exception('Données manquantes');
    }

    // Préparation de la requête de mise à jour
    $query = "UPDATE activite SET 
              `titre-activite` = ?, 
              `buyin` = ?, 
              `rake` = ?,
              `recave` = ?,
              `ville` = ?, 
              `date_depart` = ?, 
              `end_date` = ?
              WHERE `id-activite` = ?";

    $stmt = mysqli_prepare($conn, $query);
    if (!$stmt) {
        throw new Exception('Erreur de préparation de la requête : ' . mysqli_error($conn));
    }

    // Liaison des paramètres
    $bind_result = mysqli_stmt_bind_param($stmt, 
        'siiisssi',
        $title,
        $buyin,
        $rake,
        $recave,
        $ville,
        $formatted_start_date,
        $formatted_end_date,
        $id
    );

    if (!$bind_result) {
        throw new Exception('Erreur de liaison des paramètres');
    }

    // Exécution de la requête
    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception('Erreur d\'exécution : ' . mysqli_stmt_error($stmt));
    }

    mysqli_stmt_close($stmt);
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

mysqli_close($conn);
