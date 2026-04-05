<?php
header('Content-Type: application/json; charset=utf-8');

try {
    $pdo = new PDO(
        'mysql:host=localhost;dbname=dbs9616600;charset=utf8mb4',
        'root',
        'Kookies7*',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
    
    // Check if table exists
    $tableStmt = $pdo->prepare("
        SELECT TABLE_NAME 
        FROM INFORMATION_SCHEMA.TABLES 
        WHERE TABLE_SCHEMA = 'dbs9616600' 
        AND TABLE_NAME = 'app_auth_tokens'
    ");
    $tableStmt->execute();
    $tableExists = $tableStmt->fetch() !== false;
    
    $output = [
        'success' => true,
        'database' => 'dbs9616600',
        'app_auth_tokens_exists' => $tableExists,
        'tables' => [],
        'tokens' => []
    ];
    
    // List all tables
    $allTablesStmt = $pdo->query("SHOW TABLES");
    $output['tables'] = $allTablesStmt->fetchAll(PDO::FETCH_COLUMN);
    
    if ($tableExists) {
        // Get table structure
        $structStmt = $pdo->prepare("DESCRIBE app_auth_tokens");
        $structStmt->execute();
        $output['app_auth_tokens_structure'] = $structStmt->fetchAll();
        
        // Get non-expired tokens
        $tokensStmt = $pdo->prepare("
            SELECT id, membre_id, token, expires_at, last_used_at
            FROM app_auth_tokens 
            WHERE expires_at > NOW()
            LIMIT 5
        ");
        $tokensStmt->execute();
        $tokens = $tokensStmt->fetchAll();
        $output['valid_tokens_count'] = count($tokens);
        $output['sample_tokens'] = array_map(function($t) {
            return [
                'id' => $t['id'],
                'membre_id' => $t['membre_id'],
                'token_preview' => substr($t['token'], 0, 20) . '...',
                'expires_at' => $t['expires_at'],
                'last_used_at' => $t['last_used_at']
            ];
        }, $tokens);
    }
    
    echo json_encode($output, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}
?>
