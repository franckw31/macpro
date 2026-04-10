<?php
session_start();
include('include/config.php');

if (strlen($_SESSION['id']) == 0) {
    http_response_code(401);
    exit('Unauthorized');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log("Received challenger update request");
    
    $id_membre = isset($_POST['id_membre']) ? (int)$_POST['id_membre'] : 0;
    $id_activite = isset($_POST['id_activite']) ? (int)$_POST['id_activite'] : 0;
    $challenger = isset($_POST['challenger']) ? (int)$_POST['challenger'] : 0;
    
    error_log("member: $id_membre, activity: $id_activite, challenger: $challenger");
    
    if (!$id_membre || !$id_activite) {
        error_log("Missing required data");
        echo json_encode(['success' => false, 'error' => 'Missing data']);
        exit;
    }

    $conn = mysqli_connect('localhost', 'root', 'Kookies7*', 'dbs9616600');
    if (!$conn) {
        error_log("DB connection failed: " . mysqli_connect_error());
        echo json_encode(['success' => false, 'error' => 'Connection failed']);
        exit;
    }
    
    try {
        // Update avec transaction
        mysqli_begin_transaction($conn);
        
        $sql = "UPDATE participation 
                SET challenger = ?,
                    caisse_chal = CASE 
                        WHEN ? = 1 THEN COALESCE(caisse_chal, 0) + 5
                        WHEN ? = 0 THEN GREATEST(COALESCE(caisse_chal, 0) - 5, 0)
                        ELSE COALESCE(caisse_chal, 0)
                    END
                WHERE `id-membre` = ? AND `id-activite` = ?";
                
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            throw new Exception("Erreur de préparation: " . mysqli_error($conn));
        }
        
        mysqli_stmt_bind_param($stmt, "iiiii", 
            $challenger,
            $challenger,
            $challenger,
            $id_membre,
            $id_activite
        );
        
        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception("Erreur d'exécution: " . mysqli_stmt_error($stmt));
        }
        
        // Vérifier l'update
        $affected = mysqli_stmt_affected_rows($stmt);
        if ($affected === 0) {
            throw new Exception("Aucune mise à jour effectuée");
        }
        
        // Get updated values
        $sql = "SELECT p.*, 
                (a.buyin + a.bounty + a.rake + (CASE WHEN p.challenger = 1 THEN 5 ELSE 0 END)) as cout_in,
                p.caisse_chal
                FROM participation p 
                JOIN activite a ON p.`id-activite` = a.`id-activite`
                WHERE p.`id-membre` = ? AND p.`id-activite` = ?";
        
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "ii", $id_membre, $id_activite);
        
        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception(mysqli_stmt_error($stmt));
        }
        
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);
        
        if (!$row) {
            throw new Exception("No data found");
        }
        
        mysqli_commit($conn);
        echo json_encode([
            'success' => true,
            'cout_in' => $row['cout_in'],
            'caisse_chal' => $row['caisse_chal'],
            'challenger' => $challenger
        ]);
        
    } catch (Exception $e) {
        mysqli_rollback($conn);
        error_log("Error in update_challenger.php: " . $e->getMessage());
        echo json_encode([
            'success' => false, 
            'error' => $e->getMessage(),
            'details' => [
                'membre' => $id_membre,
                'activite' => $id_activite,
                'challenger' => $challenger
            ]
        ]);
        exit;
    } finally {
        if (isset($stmt)) mysqli_stmt_close($stmt);
        mysqli_close($conn);
    }
}
