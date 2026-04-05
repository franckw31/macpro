<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (strlen($_SESSION['id']) == 0) {
    http_response_code(401);
    exit(json_encode(['success' => false, 'message' => 'Non autorisé']));
}

include('include/config.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validation des paramètres
        if (!isset($_POST['id'], $_POST['field'], $_POST['value'])) {
            throw new Exception("Paramètres manquants");
        }

        $id = (int)$_POST['id'];
        $field = trim($_POST['field']);
        $value = (int)$_POST['value'];

        // Connexion DB
        $conn = mysqli_connect(DB_CONFIG['host'], DB_CONFIG['user'], DB_CONFIG['password'], DB_CONFIG['name']);
        if (!$conn) {
            throw new Exception("Erreur connexion base de données");
        }
        mysqli_set_charset($conn, 'utf8mb4');

        // Vérification que l'enregistrement existe
        $check = mysqli_query($conn, "SELECT * FROM participation WHERE `id-participation` = $id");
        if (!$check || mysqli_num_rows($check) === 0) {
            throw new Exception("Participation ID $id non trouvée");
        }

        // Vérification que le champ existe
        $fields_info = mysqli_query($conn, "DESCRIBE participation");
        $valid_fields = [];
        $recave_type = '';
        while ($field_info = mysqli_fetch_assoc($fields_info)) {
            $valid_fields[] = $field_info['Field'];
            if ($field_info['Field'] === 'recave') {
                $recave_type = $field_info['Type'];
            }
        }
        
        if (!in_array($field, $valid_fields)) {
            throw new Exception("Champ '$field' invalide");
        }

        // Validation spécifique pour recave
        if ($field === 'recave' && ($value < 0 || $value > 4)) {
            throw new Exception("La valeur recave doit être entre 0 et 4");
        }

        // Requête de mise à jour
        $sql = "UPDATE participation SET `$field` = ?, `ds` = NOW() WHERE `id-participation` = ?";
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            throw new Exception("Erreur préparation requête : " . mysqli_error($conn));
        }

        mysqli_stmt_bind_param($stmt, "ii", $value, $id);
        
        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception("Erreur exécution requête : " . mysqli_stmt_error($stmt));
        }

        if (mysqli_stmt_affected_rows($stmt) === 0) {
            // Vérifier si la valeur est identique
            $check_value = mysqli_query($conn, "SELECT `$field` FROM participation WHERE `id-participation` = $id");
            $current = mysqli_fetch_assoc($check_value);
            if ($current[$field] == $value) {
                throw new Exception("Valeur identique - aucune mise à jour nécessaire");
            } else {
                throw new Exception("Erreur de mise à jour");
            }
        }

        // Récupérer la nouvelle valeur pour confirmation
        $result = mysqli_query($conn, "SELECT `$field` FROM participation WHERE `id-participation` = $id");
        $updated = mysqli_fetch_assoc($result);

        mysqli_close($conn);

        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => 'Mise à jour effectuée',
            'field' => $field,
            'value' => $updated[$field],
            'id' => $id
        ]);

    } catch (Exception $e) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage(),
            'debug' => [
                'field' => $field ?? null,
                'value' => $value ?? null,
                'id' => $id ?? null
            ]
        ]);
    }
}
?>
