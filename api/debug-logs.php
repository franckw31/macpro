<?php
header('Content-Type: text/html; charset=utf-8');

try {
    $pdo = new PDO(
        'mysql:host=localhost;dbname=dbs9616600;charset=utf8mb4',
        'root',
        'Kookies7*',
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );

    // Check if table exists
    $tables = $pdo->query("SHOW TABLES LIKE 'app_usage_logs'")->fetchAll();
    echo "<h2>Table app_usage_logs exists: " . (count($tables) > 0 ? "YES" : "NO") . "</h2>";

    if (count($tables) > 0) {
        // Get table structure
        echo "<h3>Table Structure:</h3>";
        $columns = $pdo->query("DESCRIBE app_usage_logs")->fetchAll();
        echo "<pre>";
        print_r($columns);
        echo "</pre>";

        // Count rows
        $count = $pdo->query("SELECT COUNT(*) as total FROM app_usage_logs")->fetch();
        echo "<h3>Total rows: " . $count['total'] . "</h3>";

        // Get last 10 rows
        echo "<h3>Last 10 entries:</h3>";
        $logs = $pdo->query("SELECT * FROM app_usage_logs ORDER BY id DESC LIMIT 10")->fetchAll();
        echo "<pre>";
        print_r($logs);
        echo "</pre>";
    }
} catch (PDOException $e) {
    echo "<h2 style='color: red;'>Database error: " . htmlspecialchars($e->getMessage()) . "</h2>";
}
?>
