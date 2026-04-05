<?php
header('Content-Type: application/json; charset=utf-8');

try {
    // Connect to database
    $pdo = new PDO(
        'mysql:host=localhost;dbname=dbs9616600;charset=utf8mb4',
        'root',
        'Kookies7*',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
    
    // Test query
    $stmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM participation");
    $stmt->execute();
    $result = $stmt->fetch();
    
    echo json_encode([
        'success' => true,
        'message' => 'Database connection successful',
        'participation_count' => $result['cnt'] ?? 0
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'code' => $e->getCode()
    ]);
    error_log("DB Connection Error: " . $e->getMessage());
}
?>
