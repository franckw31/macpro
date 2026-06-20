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
$requestedUserId = (int)($_GET['user_id'] ?? 0);
if ($token === '') {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Token manquant'], JSON_UNESCAPED_UNICODE);
    exit;
}

function ce_first_constant_costs($names)
{
    foreach ($names as $name) {
        if (defined($name)) {
            return constant($name);
        }
    }
    return null;
}

function ce_first_global_costs($names)
{
    foreach ($names as $name) {
        if (isset($GLOBALS[$name]) && $GLOBALS[$name] !== '') {
            return $GLOBALS[$name];
        }
    }
    return null;
}

function ce_find_array_costs($keys)
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

function ce_db_costs()
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

    $host = ce_first_constant_costs(['DB_HOST', 'DATABASE_HOST', 'MYSQL_HOST', 'HOST', 'DB_SERVER', 'SERVER'])
        ?: ce_first_global_costs(['db_host', 'dbhost', 'host', 'hostname', 'servername', 'server', 'serveur', 'mysql_host'])
        ?: ce_find_array_costs(['host', 'hostname', 'server', 'serveur', 'db_host', 'dbhost']);
    $database = ce_first_constant_costs(['DB_NAME', 'DATABASE_NAME', 'MYSQL_DATABASE', 'DB_DATABASE', 'DATABASE', 'BDD'])
        ?: ce_first_global_costs(['db_name', 'dbname', 'database', 'bdd', 'dbase', 'mysql_database', 'mysql_db'])
        ?: ce_find_array_costs(['database', 'dbname', 'db_name', 'name', 'bdd']);
    $user = ce_first_constant_costs(['DB_USER', 'DATABASE_USER', 'MYSQL_USER', 'DB_USERNAME', 'USER', 'USERNAME'])
        ?: ce_first_global_costs(['db_user', 'dbuser', 'user', 'username', 'login', 'utilisateur', 'mysql_user'])
        ?: ce_find_array_costs(['user', 'username', 'login', 'db_user', 'dbuser']);
    $password = ce_first_constant_costs(['DB_PASS', 'DB_PASSWORD', 'DATABASE_PASSWORD', 'MYSQL_PASSWORD', 'PASSWORD', 'PASS'])
        ?: ce_first_global_costs(['db_pass', 'dbpass', 'db_password', 'password', 'pass', 'pwd', 'motdepasse', 'mysql_password'])
        ?: ce_find_array_costs(['password', 'pass', 'pwd', 'db_pass', 'dbpass']);

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

function ce_bind_costs($stmt, $params)
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

function ce_scalar_costs($db, $sql, $params = [])
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
    ce_bind_costs($stmt, $params);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_row() : null;
    $stmt->close();
    return $row ? $row[0] : false;
}

function ce_all_costs($db, $sql, $params = [])
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
    ce_bind_costs($stmt, $params);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();
    return $rows;
}

try {
    $db = ce_db_costs();
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
            $value = ce_scalar_costs($db, $authSql, [$token]);
            if ($value !== false && $value !== null && (int)$value > 0) {
                $memberId = (int)$value;
                break;
            }
        } catch (Throwable $e) {
        }
    }

    if (!$memberId && $requestedUserId > 0) {
        $memberId = $requestedUserId;
    }

    if (!$memberId) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Session invalide'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $rows = ce_all_costs(
        $db,
        'SELECT
            p.`id-participation` AS participation_id,
            p.`id-activite` AS activity_id,
            COALESCE(a.`titre-activite`, CONCAT("Partie ", p.`id-activite`)) AS title,
            a.`date_depart` AS date_depart,
            DATE_FORMAT(a.`date_depart`, "%d/%m/%y") AS date_label,
            COALESCE(a.`buyin`, 0) AS buyin,
            COALESCE(p.`recave`, 0) AS recaves,
            COALESCE(a.`buyin`, 0) * (1 + COALESCE(p.`recave`, 0)) AS cost_total,
            COALESCE(p.`gain`, 0) AS gain,
            COALESCE(p.`classement`, 0) AS classement
         FROM participation p
         LEFT JOIN activite a ON a.`id-activite` = p.`id-activite`
         WHERE p.`id-membre` = ?
         ORDER BY a.`date_depart` DESC, p.`id-participation` DESC',
        [$memberId]
    );

    $total = 0.0;
    foreach ($rows as $row) {
        $total += (float)$row['cost_total'];
    }

    echo json_encode([
        'success' => true,
        'member_id' => $memberId,
        'total' => $total,
        'count' => count($rows),
        'participations' => array_map(static function ($row) {
            return [
                'participation_id' => (int)$row['participation_id'],
                'activity_id' => (int)$row['activity_id'],
                'title' => $row['title'],
                'date_depart' => $row['date_depart'],
                'date_label' => $row['date_label'],
                'buyin' => (float)$row['buyin'],
                'recaves' => (int)$row['recaves'],
                'cost_total' => (float)$row['cost_total'],
                'gain' => (float)$row['gain'],
                'classement' => (int)$row['classement'],
            ];
        }, $rows),
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
