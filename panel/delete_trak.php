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

$id_note     = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$id_session  = (int)$_SESSION['id'];

if (!$id_note) {
    echo json_encode(['success' => false, 'error' => 'ID note manquant']);
    exit;
}

// Vérifier que l'utilisateur est admin ou auteur de la note
$res = mysqli_query($conn, "SELECT id_auteur FROM trak WHERE id = $id_note LIMIT 1");
if (!$res || mysqli_num_rows($res) === 0) {
    echo json_encode(['success' => false, 'error' => 'Note introuvable']);
    exit;
}
$note_row = mysqli_fetch_assoc($res);

// Admin check (droits = 2) ou auteur
$admin_res = mysqli_query($conn, "SELECT droits FROM membres WHERE `id-membre` = $id_session LIMIT 1");
$is_admin  = false;
if ($admin_res && $row = mysqli_fetch_assoc($admin_res)) {
    $is_admin = ((int)$row['droits'] === 2);
}

if (!$is_admin && (int)$note_row['id_auteur'] !== $id_session) {
    echo json_encode(['success' => false, 'error' => 'Permission refusée']);
    exit;
}

$stmt = $conn->prepare("DELETE FROM trak WHERE id = ?");
$stmt->bind_param('i', $id_note);
if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => $conn->error]);
}
$stmt->close();
$conn->close();
