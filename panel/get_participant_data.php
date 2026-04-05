
<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
file_put_contents('/tmp/debug_get_participant.txt', 'DEBUT '.date('c')."\n", FILE_APPEND);
session_start();
include('include/config.php');

file_put_contents('/tmp/debug_get_participant.txt', 'CHECKPOINT SESSION\n', FILE_APPEND);
if (strlen($_SESSION['id']) == 0) {
    file_put_contents('/tmp/debug_get_participant.txt', 'CHECKPOINT UNAUTHORIZED\n', FILE_APPEND);
    http_response_code(401);
    exit('Unauthorized');
}

// Accepte GET ou POST
$id_membre = isset($_POST['id_membre']) ? (int)$_POST['id_membre'] : (isset($_GET['id_membre']) ? (int)$_GET['id_membre'] : 0);
$id_activite = isset($_POST['id_activite']) ? (int)$_POST['id_activite'] : (isset($_GET['id_activite']) ? (int)$_GET['id_activite'] : 0);

file_put_contents('/tmp/debug_get_participant.txt', 'CHECKPOINT CONNEXION\n', FILE_APPEND);
$conn = mysqli_connect('localhost', 'root', 'Kookies7*', 'dbs9616600');
if (!$conn) {
    file_put_contents('/tmp/debug_get_participant.txt', 'CHECKPOINT ERREUR CONNEXION\n', FILE_APPEND);
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erreur de connexion']);
    exit;
}
mysqli_set_charset($conn, 'utf8mb4');

$sql = "SELECT p.*, 
        (a.buyin + a.bounty + a.rake + (CASE WHEN p.challenger = 1 THEN 5 ELSE 0 END)) as cout_in,
        (COALESCE(p.jetons, 0) + COALESCE(p.jetons_bonus_ins, 0) + COALESCE(p.jetons_bonus_arrivee, 0)) as jetons_total
        FROM participation p 
        INNER JOIN activite a ON p.`id-activite` = a.`id-activite`
        WHERE p.`id-membre` = ? AND p.`id-activite` = ?";

file_put_contents('/tmp/debug_get_participant.txt', 'CHECKPOINT PREPARE\n', FILE_APPEND);
$stmt = mysqli_prepare($conn, $sql);
if (!$stmt) {
    file_put_contents('/tmp/debug_get_participant.txt', 'CHECKPOINT ERREUR PREPARE\n', FILE_APPEND);
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erreur de préparation SQL']);
    mysqli_close($conn);
    exit;
}

file_put_contents('/tmp/debug_get_participant.txt', 'CHECKPOINT BIND\n', FILE_APPEND);
mysqli_stmt_bind_param($stmt, "ii", $id_membre, $id_activite);
file_put_contents('/tmp/debug_get_participant.txt', 'CHECKPOINT EXECUTE\n', FILE_APPEND);
if (mysqli_stmt_execute($stmt)) {
    file_put_contents('/tmp/debug_get_participant.txt', 'CHECKPOINT EXECUTE OK\n', FILE_APPEND);
    $result = mysqli_stmt_get_result($stmt);
    $data = mysqli_fetch_assoc($result);
    file_put_contents('/tmp/debug_get_participant.txt', 'CHECKPOINT FETCH '.json_encode($data)."\n", FILE_APPEND);
    echo json_encode(['success' => true, 'data' => $data]);
} else {
    file_put_contents('/tmp/debug_get_participant.txt', 'CHECKPOINT EXECUTE FAIL '.mysqli_error($conn)."\n", FILE_APPEND);
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => mysqli_error($conn)]);
}
mysqli_stmt_close($stmt);
mysqli_close($conn);
file_put_contents('/tmp/debug_get_participant.txt', 'CHECKPOINT FIN SCRIPT\n', FILE_APPEND);
