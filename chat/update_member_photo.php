<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Démarrer la session
session_start();

// Header JSON
header('Content-Type: application/json; charset=utf-8');

try {
    // Vérifier authentification
    if (!isset($_SESSION['id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Non authentifié']);
        exit;
    }
    
    // Vérifier les paramètres
    if (!isset($_POST['id_membre']) || !isset($_POST['photo'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Paramètres manquants']);
        exit;
    }
    
    // Connexion directe à la base de données
    $con = mysqli_connect('localhost', 'root', 'Kookies7*', 'dbs9616600');
    
    if (!$con) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erreur connexion DB: ' . mysqli_connect_error()]);
        exit;
    }
    
    mysqli_set_charset($con, 'utf8mb4');
    
    $id_membre = intval($_POST['id_membre']);
    $photo = $_POST['photo'];
    $user_id = intval($_SESSION['id']);
    $user_role = isset($_SESSION['role']) ? $_SESSION['role'] : 'user';
    
    // Vérifier permissions
    if ($id_membre !== $user_id && $user_role !== 'admin' && $user_role !== 'superadmin') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Non autorisé']);
        exit;
    }
    
    // Valider que le fichier existe
    $avatar_path = dirname(__FILE__) . '/../images/faces/' . basename($photo);
    if (!file_exists($avatar_path)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Fichier avatar introuvable']);
        exit;
    }
    
    // Mettre à jour la BD
    $query = "UPDATE membres SET photo = ? WHERE `id-membre` = ?";
    $stmt = mysqli_prepare($con, $query);
    
    if (!$stmt) {
        throw new Exception("Erreur prepare: " . mysqli_error($con));
    }
    
    mysqli_stmt_bind_param($stmt, 'si', $photo, $id_membre);
    
    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception("Erreur execute: " . mysqli_stmt_error($stmt));
    }
    
    if (mysqli_stmt_affected_rows($stmt) > 0) {
        echo json_encode(['success' => true, 'message' => 'Photo mise à jour']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Aucune ligne modifiée']);
    }
    
    mysqli_stmt_close($stmt);
    mysqli_close($con);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
}
?>
