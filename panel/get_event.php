<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
include('include/config.php');

header('Content-Type: application/json');

try {
    if (!$conn) {
        throw new Exception("Erreur de connexion à la base de données");
    }

    if (!isset($_GET['id'])) {
        throw new Exception("ID de l'activité manquant");
    }

    $id = intval($_GET['id']);

    $query = "SELECT `id-activite`, `titre-activite`, `buyin`, `rake`, `recave`, `date_depart`, `heure_depart`, `ville` 
              FROM activite 
              WHERE `id-activite` = ?";

    $stmt = mysqli_prepare($conn, $query);
    if (!$stmt) {
        throw new Exception("Erreur de préparation: " . mysqli_error($conn));
    }

    if (!mysqli_stmt_bind_param($stmt, 'i', $id)) {
        throw new Exception("Erreur de liaison des paramètres");
    }

    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception("Erreur d'exécution");
    }

    $result = mysqli_stmt_get_result($stmt);
    if (!$result) {
        throw new Exception("Erreur de récupération des données");
    }

    $event = mysqli_fetch_assoc($result);
    if (!$event) {
        throw new Exception("Activité non trouvée");
    }

    echo json_encode([
        'success' => true,
        'id' => $event['id-activite'],
        'title' => $event['titre-activite'],
        'buyin' => $event['buyin'],
        'rake' => $event['rake'],
        'recave' => $event['recave'],
        'start_date' => $event['date_depart'],
        'heure_depart' => $event['heure_depart'],
        'ville' => $event['ville']
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

if (isset($stmt)) mysqli_stmt_close($stmt);
if (isset($conn)) mysqli_close($conn);
