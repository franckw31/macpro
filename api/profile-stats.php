<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

register_shutdown_function(function () {
    $error = error_get_last();
    if ($error === null) {
        return;
    }
    $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR];
    if (!in_array($error['type'], $fatalTypes, true)) {
        return;
    }
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode([
        'success' => false,
        'error' => 'Erreur PHP: ' . $error['message'],
        'file' => basename($error['file'] ?? ''),
        'line' => $error['line'] ?? null,
    ], JSON_UNESCAPED_UNICODE);
});

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

$configCandidates = [
    __DIR__ . '/config.php',
    __DIR__ . '/../config.php',
    __DIR__ . '/../panel/config.php',
    __DIR__ . '/../../panel/config.php',
];

$configLoaded = null;
foreach ($configCandidates as $file) {
    if (is_file($file)) {
        require_once $file;
        $configLoaded = $file;
        break;
    }
}

if ($configLoaded === null) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'config.php introuvable',
        'tested' => $configCandidates,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$token = trim($_GET['token'] ?? '');
$requestedUserId = (int)($_GET['user_id'] ?? 0);
if ($token === '') {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Token manquant'], JSON_UNESCAPED_UNICODE);
    exit;
}

function cardevent_first_defined_constant($names)
{
    foreach ($names as $name) {
        if (defined($name)) {
            return constant($name);
        }
    }
    return null;
}

function cardevent_first_global($names)
{
    foreach ($names as $name) {
        if (isset($GLOBALS[$name]) && $GLOBALS[$name] !== '') {
            return $GLOBALS[$name];
        }
    }
    return null;
}

function cardevent_find_in_arrays($keys)
{
    foreach ($GLOBALS as $value) {
        if (!is_array($value)) {
            continue;
        }
        foreach ($keys as $key) {
            if (isset($value[$key]) && $value[$key] !== '') {
                return $value[$key];
            }
        }
    }
    return null;
}

function cardevent_get_db()
{
    foreach (['get_pdo', 'getPDO', 'pdo', 'db', 'database', 'connect_db', 'connectDB', 'connexion', 'getConnection'] as $functionName) {
        if (function_exists($functionName)) {
            $result = $functionName();
            if ($result instanceof PDO || (class_exists('mysqli') && $result instanceof mysqli)) {
                return $result;
            }
        }
    }

    foreach ($GLOBALS as $value) {
        if ($value instanceof PDO) {
            return $value;
        }
    }

    if (class_exists('mysqli')) {
        foreach ($GLOBALS as $value) {
            if ($value instanceof mysqli) {
                return $value;
            }
        }
    }

    $host = cardevent_first_defined_constant(['DB_HOST', 'DATABASE_HOST', 'MYSQL_HOST', 'HOST', 'DB_SERVER', 'SERVER'])
        ?: cardevent_first_global(['db_host', 'dbhost', 'host', 'hostname', 'servername', 'server', 'serveur', 'mysql_host'])
        ?: cardevent_find_in_arrays(['host', 'hostname', 'server', 'serveur', 'db_host', 'dbhost']);
    $database = cardevent_first_defined_constant(['DB_NAME', 'DATABASE_NAME', 'MYSQL_DATABASE', 'DB_DATABASE', 'DATABASE', 'BDD'])
        ?: cardevent_first_global(['db_name', 'dbname', 'database', 'bdd', 'dbase', 'mysql_database', 'mysql_db'])
        ?: cardevent_find_in_arrays(['database', 'dbname', 'db_name', 'name', 'bdd']);
    $user = cardevent_first_defined_constant(['DB_USER', 'DATABASE_USER', 'MYSQL_USER', 'DB_USERNAME', 'USER', 'USERNAME'])
        ?: cardevent_first_global(['db_user', 'dbuser', 'user', 'username', 'login', 'utilisateur', 'mysql_user'])
        ?: cardevent_find_in_arrays(['user', 'username', 'login', 'db_user', 'dbuser']);
    $password = cardevent_first_defined_constant(['DB_PASS', 'DB_PASSWORD', 'DATABASE_PASSWORD', 'MYSQL_PASSWORD', 'PASSWORD', 'PASS'])
        ?: cardevent_first_global(['db_pass', 'dbpass', 'db_password', 'password', 'pass', 'pwd', 'motdepasse', 'mysql_password'])
        ?: cardevent_find_in_arrays(['password', 'pass', 'pwd', 'db_pass', 'dbpass']);

    if ($host && $database && $user !== null) {
        return new PDO(
            "mysql:host=$host;dbname=$database;charset=utf8mb4",
            $user,
            $password ?: '',
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );
    }

    throw new RuntimeException('Connexion DB introuvable dans config.php');
}

function mysqli_bind_params($stmt, $params)
{
    if (empty($params)) {
        return;
    }
    $types = str_repeat('s', count($params));
    $refs = [];
    foreach ($params as $key => $value) {
        $refs[$key] = &$params[$key];
    }
    array_unshift($refs, $types);
    call_user_func_array([$stmt, 'bind_param'], $refs);
}

function fetch_scalar($db, $sql, $params = [])
{
    if ($db instanceof PDO) {
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }

    $stmt = $db->prepare($sql);
    if (!$stmt) {
        throw new RuntimeException($db->error);
    }
    mysqli_bind_params($stmt, $params);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_row() : null;
    $stmt->close();
    return $row ? $row[0] : false;
}

try {
    $db = cardevent_get_db();
    $memberId = null;
    $memberSource = null;
    $authErrors = [];

    foreach ([
        'SELECT membre_id FROM sessions WHERE token = ? AND expires_at > NOW() LIMIT 1',
        'SELECT user_id FROM sessions WHERE token = ? AND expires_at > NOW() LIMIT 1',
        'SELECT id_membre FROM sessions WHERE token = ? AND expires_at > NOW() LIMIT 1',
        'SELECT `id-membre` FROM sessions WHERE token = ? AND expires_at > NOW() LIMIT 1',
        'SELECT membre_id FROM sessions WHERE token = ? LIMIT 1',
        'SELECT user_id FROM sessions WHERE token = ? LIMIT 1',
        'SELECT id_membre FROM sessions WHERE token = ? LIMIT 1',
        'SELECT `id-membre` FROM sessions WHERE token = ? LIMIT 1',
        'SELECT membre_id FROM api_sessions WHERE token = ? LIMIT 1',
        'SELECT user_id FROM api_sessions WHERE token = ? LIMIT 1',
        'SELECT id_membre FROM api_sessions WHERE token = ? LIMIT 1',
        'SELECT membre_id FROM mobile_sessions WHERE token = ? LIMIT 1',
        'SELECT user_id FROM mobile_sessions WHERE token = ? LIMIT 1',
        'SELECT id_membre FROM mobile_sessions WHERE token = ? LIMIT 1',
        'SELECT membre_id FROM user_sessions WHERE token = ? LIMIT 1',
        'SELECT user_id FROM user_sessions WHERE token = ? LIMIT 1',
        'SELECT id_membre FROM user_sessions WHERE token = ? LIMIT 1',
        'SELECT membre_id FROM app_sessions WHERE token = ? LIMIT 1',
        'SELECT user_id FROM app_sessions WHERE token = ? LIMIT 1',
        'SELECT id_membre FROM app_sessions WHERE token = ? LIMIT 1',
    ] as $authSql) {
        try {
            $value = fetch_scalar($db, $authSql, [$token]);
            if ($value !== false && $value !== null && (int)$value > 0) {
                $memberId = (int)$value;
                $memberSource = 'session';
                break;
            }
        } catch (Throwable $e) {
            $authErrors[] = $e->getMessage();
        }
    }

    if (!$memberId && $requestedUserId > 0) {
        $memberId = $requestedUserId;
        $memberSource = 'user_id';
    }

    if (!$memberId) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'error' => 'Session invalide',
            'debug' => array_slice(array_values(array_unique($authErrors)), 0, 5),
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $coutInTotal = fetch_scalar(
        $db,
        'SELECT COALESCE(SUM(COALESCE(`cout_in`, 0)), 0) FROM participation WHERE `id-membre` = ?',
        [$memberId]
    );
    $gainTotal = fetch_scalar(
        $db,
        'SELECT COALESCE(SUM(COALESCE(`gain`, 0)), 0) FROM participation WHERE `id-membre` = ?',
        [$memberId]
    );
    $maxGain = fetch_scalar(
        $db,
        'SELECT COALESCE(MAX(COALESCE(`gain`, 0)), 0) FROM participation WHERE `id-membre` = ?',
        [$memberId]
    );
    $rakeTotal = fetch_scalar(
        $db,
        'SELECT COALESCE(SUM(COALESCE(`rake`, 0)), 0) FROM participation WHERE `id-membre` = ?',
        [$memberId]
    );
    $partiesCount = fetch_scalar(
        $db,
        'SELECT COUNT(*) FROM participation WHERE `id-membre` = ?',
        [$memberId]
    );
    $gainsCount = fetch_scalar(
        $db,
        'SELECT COUNT(*) FROM participation WHERE `id-membre` = ? AND COALESCE(`gain`, 0) > 0',
        [$memberId]
    );
    $victoriesCount = fetch_scalar(
        $db,
        'SELECT COUNT(*) FROM participation WHERE `id-membre` = ? AND COALESCE(`classement`, 0) = 1 AND COALESCE(`gain`, 0) > 0',
        [$memberId]
    );
    $itmCount = fetch_scalar(
        $db,
        'SELECT COUNT(*) FROM participation WHERE `id-membre` = ? AND COALESCE(`classement`, 0) < 10 AND COALESCE(`classement`, 0) <> 0',
        [$memberId]
    );

    echo json_encode([
        'success' => true,
        'member_id' => $memberId,
        'source' => $memberSource,
        'cout_in_total' => (float)$coutInTotal,
        'gain_total' => (float)$gainTotal,
        'max_gain' => (float)$maxGain,
        'rake_total' => (float)$rakeTotal,
        'parties_count' => (int)$partiesCount,
        'gains_count' => (int)$gainsCount,
        'victories_count' => (int)$victoriesCount,
        'itm_count' => (int)$itmCount,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
