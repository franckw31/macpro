<?php
header('Content-Type: application/json');
try {
    $pdo = new PDO('mysql:host=localhost;dbname=dbs9616600;charset=utf8mb4', 'root', 'Kookies7*', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    
    // Get next activity id
    $act = $pdo->query("SELECT `id-activite`, `titre-activite`, `date_depart` FROM `activite` WHERE `date_depart` >= NOW() ORDER BY `date_depart` ASC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    
    // Count by valide value
    $stmt = $pdo->prepare("SELECT `valide`, COUNT(*) as nb FROM `participation` WHERE `id-activite` = ? GROUP BY `valide`");
    $stmt->execute([$act['id-activite']]);
    $byValide = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // List all participants
    $stmt2 = $pdo->prepare("SELECT `nom-membre`, `valide`, `option` FROM `participation` WHERE `id-activite` = ? ORDER BY `ordre`");
    $stmt2->execute([$act['id-activite']]);
    $participants = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'activite' => $act,
        'count_by_valide' => $byValide,
        'total' => count($participants),
        'participants' => $participants
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
