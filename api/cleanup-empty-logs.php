<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

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

    $sql = "DELETE FROM app_usage_logs
            WHERE COALESCE(device_id, '') = ''
              AND COALESCE(user_name, '') = ''
              AND COALESCE(phone_number, '') = ''
              AND COALESCE(ios_identity, '') = ''
              AND COALESCE(phone_name, '') = ''
              AND COALESCE(icloud_account, '') = ''
              AND COALESCE(icloud_id, '') = ''
              AND COALESCE(device_name, '') = ''
              AND COALESCE(device_model, '') = ''
              AND COALESCE(os_version, '') = ''
              AND COALESCE(app_version, '') = ''";

    $deleted = $pdo->exec($sql);

    echo json_encode([
        'success' => true,
        'deleted' => (int)$deleted
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error'
    ]);
}
?>
