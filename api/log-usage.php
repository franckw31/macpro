<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

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

    // Create table if it doesn't exist
    $pdo->exec("CREATE TABLE IF NOT EXISTS `app_usage_logs` (
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `device_id` VARCHAR(255) NOT NULL,
        `user_name` VARCHAR(255) DEFAULT NULL,
        `phone_number` VARCHAR(32) DEFAULT NULL,
        `ios_identity` VARCHAR(255) DEFAULT NULL,
        `phone_name` VARCHAR(255) DEFAULT NULL,
        `icloud_account` VARCHAR(64) DEFAULT NULL,
        `icloud_id` VARCHAR(64) DEFAULT NULL,
        `device_name` VARCHAR(255) DEFAULT NULL,
        `device_model` VARCHAR(255) DEFAULT NULL,
        `os_version` VARCHAR(50) DEFAULT NULL,
        `app_version` VARCHAR(50) DEFAULT NULL,
        `timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        INDEX `device_id_idx` (`device_id`),
        INDEX `timestamp_idx` (`timestamp`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Backward compatibility: add user_name if table already existed
    $colCheck = $pdo->query("SHOW COLUMNS FROM `app_usage_logs` LIKE 'user_name'");
    if (!$colCheck || $colCheck->rowCount() === 0) {
        $pdo->exec("ALTER TABLE `app_usage_logs` ADD COLUMN `user_name` VARCHAR(255) DEFAULT NULL AFTER `device_id`");
    }

    $phoneColCheck = $pdo->query("SHOW COLUMNS FROM `app_usage_logs` LIKE 'phone_number'");
    if (!$phoneColCheck || $phoneColCheck->rowCount() === 0) {
        $pdo->exec("ALTER TABLE `app_usage_logs` ADD COLUMN `phone_number` VARCHAR(32) DEFAULT NULL AFTER `user_name`");
    }

    $identityColCheck = $pdo->query("SHOW COLUMNS FROM `app_usage_logs` LIKE 'ios_identity'");
    if (!$identityColCheck || $identityColCheck->rowCount() === 0) {
        $pdo->exec("ALTER TABLE `app_usage_logs` ADD COLUMN `ios_identity` VARCHAR(255) DEFAULT NULL AFTER `phone_number`");
    }

    $phoneNameColCheck = $pdo->query("SHOW COLUMNS FROM `app_usage_logs` LIKE 'phone_name'");
    if (!$phoneNameColCheck || $phoneNameColCheck->rowCount() === 0) {
        $pdo->exec("ALTER TABLE `app_usage_logs` ADD COLUMN `phone_name` VARCHAR(255) DEFAULT NULL AFTER `ios_identity`");
    }

    $icloudAccountColCheck = $pdo->query("SHOW COLUMNS FROM `app_usage_logs` LIKE 'icloud_account'");
    if (!$icloudAccountColCheck || $icloudAccountColCheck->rowCount() === 0) {
        $pdo->exec("ALTER TABLE `app_usage_logs` ADD COLUMN `icloud_account` VARCHAR(64) DEFAULT NULL AFTER `phone_name`");
    }

    $icloudIdColCheck = $pdo->query("SHOW COLUMNS FROM `app_usage_logs` LIKE 'icloud_id'");
    if (!$icloudIdColCheck || $icloudIdColCheck->rowCount() === 0) {
        $pdo->exec("ALTER TABLE `app_usage_logs` ADD COLUMN `icloud_id` TEXT DEFAULT NULL AFTER `icloud_account`");
    } else {
        $icloudIdCol = $icloudIdColCheck->fetch();
        if (isset($icloudIdCol['Type']) && stripos($icloudIdCol['Type'], 'varchar') === 0) {
            $pdo->exec("ALTER TABLE `app_usage_logs` MODIFY COLUMN `icloud_id` TEXT DEFAULT NULL");
        }
    }

    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true);
    error_log("log-usage.php received: " . ($rawInput ?: '[empty-body]'));

    if (!is_array($input)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid JSON payload']);
        exit;
    }

    $deviceId = trim((string)($input['device_id'] ?? ''));
    $userName = trim((string)($input['user_name'] ?? ''));
    $phoneNumber = trim((string)($input['phone_number'] ?? ''));
    $iosIdentity = trim((string)($input['ios_identity'] ?? ''));
    $phoneName = trim((string)($input['phone_name'] ?? ''));
    $icloudAccount = trim((string)($input['icloud_account'] ?? ''));
    $icloudId = trim((string)($input['icloud_id'] ?? ''));
    $deviceName = trim((string)($input['device_name'] ?? ''));
    $deviceModel = trim((string)($input['device_model'] ?? ''));
    $osVersion = trim((string)($input['os_version'] ?? ''));
    $appVersion = trim((string)($input['app_version'] ?? ''));

    if (
        $deviceId === '' &&
        $userName === '' &&
        $phoneNumber === '' &&
        $iosIdentity === '' &&
        $phoneName === '' &&
        $icloudAccount === '' &&
        $icloudId === '' &&
        $deviceName === '' &&
        $deviceModel === '' &&
        $osVersion === '' &&
        $appVersion === ''
    ) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Empty payload']);
        exit;
    }

    if ($deviceId === '') {
        $deviceId = 'unknown';
    }

    $userName = $userName !== '' ? $userName : null;
    $phoneNumber = $phoneNumber !== '' ? $phoneNumber : null;
    $iosIdentity = $iosIdentity !== '' ? $iosIdentity : null;
    $phoneName = $phoneName !== '' ? $phoneName : null;
    $icloudAccount = $icloudAccount !== '' ? $icloudAccount : null;
    $icloudId = $icloudId !== '' ? $icloudId : null;
    $deviceName = $deviceName !== '' ? $deviceName : null;
    $deviceModel = $deviceModel !== '' ? $deviceModel : null;
    $osVersion = $osVersion !== '' ? $osVersion : null;
    $appVersion = $appVersion !== '' ? $appVersion : null;

    $stmt = $pdo->prepare("
        INSERT INTO `app_usage_logs` 
        (`device_id`, `user_name`, `phone_number`, `ios_identity`, `phone_name`, `icloud_account`, `icloud_id`, `device_name`, `device_model`, `os_version`, `app_version`) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([$deviceId, $userName, $phoneNumber, $iosIdentity, $phoneName, $icloudAccount, $icloudId, $deviceName, $deviceModel, $osVersion, $appVersion]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Usage logged',
        'log_id' => $pdo->lastInsertId()
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error'
    ]);
}
?>
