<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

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
    echo json_encode(['success' => false, 'error' => 'config.php introuvable'], JSON_UNESCAPED_UNICODE);
    exit;
}

$token = trim($_GET['token'] ?? '');
if ($token === '') {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Token manquant'], JSON_UNESCAPED_UNICODE);
    exit;
}

function cardevent_first_defined_constant_net($names)
{
    foreach ($names as $name) {
        if (defined($name)) {
            return constant($name);
        }
    }
    return null;
}

function cardevent_first_global_net($names)
{
    foreach ($names as $name) {
        if (isset($GLOBALS[$name]) && $GLOBALS[$name] !== '') {
            return $GLOBALS[$name];
        }
    }
    return null;
}

function cardevent_find_in_arrays_net($keys)
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

function cardevent_get_db_net()
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

    $host = cardevent_first_defined_constant_net(['DB_HOST', 'DATABASE_HOST', 'MYSQL_HOST', 'HOST', 'DB_SERVER', 'SERVER'])
        ?: cardevent_first_global_net(['db_host', 'dbhost', 'host', 'hostname', 'servername', 'server', 'serveur', 'mysql_host'])
        ?: cardevent_find_in_arrays_net(['host', 'hostname', 'server', 'serveur', 'db_host', 'dbhost']);
    $database = cardevent_first_defined_constant_net(['DB_NAME', 'DATABASE_NAME', 'MYSQL_DATABASE', 'DB_DATABASE', 'DATABASE', 'BDD'])
        ?: cardevent_first_global_net(['db_name', 'dbname', 'database', 'bdd', 'dbase', 'mysql_database', 'mysql_db'])
        ?: cardevent_find_in_arrays_net(['database', 'dbname', 'db_name', 'name', 'bdd']);
    $user = cardevent_first_defined_constant_net(['DB_USER', 'DATABASE_USER', 'MYSQL_USER', 'DB_USERNAME', 'USER', 'USERNAME'])
        ?: cardevent_first_global_net(['db_user', 'dbuser', 'user', 'username', 'login', 'utilisateur', 'mysql_user'])
        ?: cardevent_find_in_arrays_net(['user', 'username', 'login', 'db_user', 'dbuser']);
    $password = cardevent_first_defined_constant_net(['DB_PASS', 'DB_PASSWORD', 'DATABASE_PASSWORD', 'MYSQL_PASSWORD', 'PASSWORD', 'PASS'])
        ?: cardevent_first_global_net(['db_pass', 'dbpass', 'db_password', 'password', 'pass', 'pwd', 'motdepasse', 'mysql_password'])
        ?: cardevent_find_in_arrays_net(['password', 'pass', 'pwd', 'db_pass', 'dbpass']);

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

function mysqli_bind_params_net($stmt, $params)
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

function fetch_scalar_net($db, $sql, $params = [])
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
    mysqli_bind_params_net($stmt, $params);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_row() : null;
    $stmt->close();
    return $row ? $row[0] : false;
}

function fetch_all_net($db, $sql, $params = [])
{
    if ($db instanceof PDO) {
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    $stmt = $db->prepare($sql);
    if (!$stmt) {
        throw new RuntimeException($db->error);
    }
    mysqli_bind_params_net($stmt, $params);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();
    return $rows;
}

try {
    $db = cardevent_get_db_net();
    $memberId = null;

    foreach ([
        'SELECT membre_id FROM sessions WHERE token = ? AND expires_at > NOW() LIMIT 1',
        'SELECT user_id FROM sessions WHERE token = ? AND expires_at > NOW() LIMIT 1',
        'SELECT id_membre FROM sessions WHERE token = ? AND expires_at > NOW() LIMIT 1',
        'SELECT `id-membre` FROM sessions WHERE token = ? AND expires_at > NOW() LIMIT 1',
        'SELECT membre_id FROM app_auth_tokens WHERE token = ? AND expires_at > NOW() LIMIT 1',
        'SELECT membre_id FROM sessions WHERE token = ? LIMIT 1',
        'SELECT user_id FROM sessions WHERE token = ? LIMIT 1',
        'SELECT id_membre FROM sessions WHERE token = ? LIMIT 1',
        'SELECT `id-membre` FROM sessions WHERE token = ? LIMIT 1',
        'SELECT membre_id FROM app_auth_tokens WHERE token = ? LIMIT 1',
    ] as $authSql) {
        try {
            $value = fetch_scalar_net($db, $authSql, [$token]);
            if ($value !== false && $value !== null && (int)$value > 0) {
                $memberId = (int)$value;
                break;
            }
        } catch (Throwable $e) {
        }
    }

    if (!$memberId) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Session invalide'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $rows = fetch_all_net(
        $db,
        'SELECT
            m.`id-membre` AS member_id,
            COALESCE(m.`pseudo`, CONCAT("Joueur ", m.`id-membre`)) AS pseudo,
            COUNT(p.`id-participation`) AS parties_count,
            COALESCE(SUM(COALESCE(a.`buyin`, 0) * (1 + COALESCE(p.`recave`, 0))), 0) AS cout_in_total,
            COALESCE(SUM(COALESCE(p.`recave`, 0) * COALESCE(a.`recave_montant`, 0)), 0) AS recaves_amount_total,
            COALESCE(SUM(COALESCE(p.`rake`, 0)), 0) AS rake_total,
            COALESCE(SUM(COALESCE(p.`gain`, 0)), 0) AS gain_total,
            (
                COALESCE(SUM(COALESCE(p.`gain`, 0)), 0)
                - COALESCE(SUM(COALESCE(a.`buyin`, 0) * (1 + COALESCE(p.`recave`, 0))), 0)
                - COALESCE(SUM(COALESCE(p.`recave`, 0) * COALESCE(a.`recave_montant`, 0)), 0)
                - COALESCE(SUM(COALESCE(p.`rake`, 0)), 0)
            ) AS net_total
         FROM participation p
         INNER JOIN membres m ON m.`id-membre` = p.`id-membre`
         LEFT JOIN activite a ON a.`id-activite` = p.`id-activite`
         GROUP BY m.`id-membre`, m.`pseudo`
         ORDER BY net_total DESC, pseudo ASC'
    );

    echo json_encode([
        'success' => true,
        'me' => $memberId,
        'players' => array_map(static function ($row) use ($memberId) {
            return [
                'member_id' => (int)$row['member_id'],
                'pseudo' => $row['pseudo'],
                'is_me' => (int)$row['member_id'] === $memberId,
                'parties_count' => (int)$row['parties_count'],
                'cout_in_total' => (float)$row['cout_in_total'],
                'recaves_amount_total' => (float)$row['recaves_amount_total'],
                'rake_total' => (float)$row['rake_total'],
                'gain_total' => (float)$row['gain_total'],
                'net_total' => (float)$row['net_total'],
            ];
        }, $rows),
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
