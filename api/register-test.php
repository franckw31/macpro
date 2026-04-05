<?php
// Minimal test endpoint to debug registration API
header('Content-Type: application/json; charset=utf-8');
error_log("[reg-test] ========== TEST REQUEST ==========");
error_log("[reg-test] Method: " . $_SERVER['REQUEST_METHOD']);
error_log("[reg-test] All SERVER vars: " . json_encode($_SERVER, JSON_PARTIAL_OUTPUT_ON_ERROR));

try {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    error_log("[reg-test] SESSION: " . json_encode($_SESSION));
    error_log("[reg-test] REQUEST: " . file_get_contents('php://input'));
    
    // Try database connection
    $pdo = new PDO(
        'mysql:host=localhost;dbname=dbs9616600;charset=utf8mb4',
        'root',
        'Kookies7*',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    error_log("[reg-test] Database connected");
    
    // List tables
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    error_log("[reg-test] Tables: " . json_encode($tables));
    
    echo json_encode([
        'success' => true,
        'message' => 'API test endpoint working',
        'session_id' => $_SESSION['id'] ?? null,
        'has_authorization' => isset($_SERVER['HTTP_AUTHORIZATION']) ? 'yes' : 'no',
        'tables_count' => count($tables)
    ]);
} catch (Exception $e) {
    error_log("[reg-test] EXCEPTION: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
error_log("[reg-test] ========== TEST END ==========");
?>
