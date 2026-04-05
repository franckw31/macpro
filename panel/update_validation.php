<?php
session_start();
include('include/config.php');

if (strlen($_SESSION['id']) == 0) {
    http_response_code(401);
    exit('Unauthorized');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_membre = isset($_POST['id_membre']) ? (int)$_POST['id_membre'] : 0;
    $id_activite = isset($_POST['id_activite']) ? (int)$_POST['id_activite'] : 0;
    
    $conn = mysqli_connect('localhost', 'root', 'Kookies7*', 'dbs9616600');
    
    error_log("Debug - membre: $id_membre, activite: $id_activite");
    
    $sql = "UPDATE participation 
            SET option = ? 
            WHERE `id-membre` = ? 
            AND `id-activite` = ?";
            
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "sii", $validation_status, $id_membre, $id_activite);
    
    // Définir le statut de validation
    $validation_status = 'Valide';
    
    if (mysqli_stmt_execute($stmt)) {
        $affected_rows = mysqli_stmt_affected_rows($stmt);
        error_log("Rows affected: " . $affected_rows);
        
        // Double check l'update
        $check_sql = "SELECT `option` FROM participation 
                     WHERE `id-membre` = ? AND `id-activite` = ?";
        $check_stmt = mysqli_prepare($conn, $check_sql);
        mysqli_stmt_bind_param($check_stmt, "ii", $id_membre, $id_activite);
        mysqli_stmt_execute($check_stmt);
        $result = mysqli_stmt_get_result($check_stmt);
        $row = mysqli_fetch_assoc($result);
        
        echo json_encode([
            'success' => true, 
            'affected' => $affected_rows,
            'current_status' => $row['option']
        ]);
    } else {
        error_log("SQL Error: " . mysqli_error($conn));
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => mysqli_error($conn)]);
    }
    
    mysqli_close($conn);
}
