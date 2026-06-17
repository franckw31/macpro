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

$cardeventConfigCandidates = [
    __DIR__ . '/config.php',
    __DIR__ . '/../config.php',
    __DIR__ . '/../panel/config.php',
    __DIR__ . '/../../panel/config.php',
];
$cardeventConfigLoaded = null;

foreach ($cardeventConfigCandidates as $file) {
    if (is_file($file)) {
        require_once $file;
        $cardeventConfigLoaded = $file;
        break;
    }
}

if ($cardeventConfigLoaded === null) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'config.php introuvable',
        'tested' => $cardeventConfigCandidates,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$token = trim($_GET['token'] ?? '');
$activityId = (int)($_GET['activity_id'] ?? 0);

if ($token === '') {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Token manquant']);
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

function cardevent_config_debug()
{
    $globals = [];
    foreach ($GLOBALS as $name => $value) {
        if (in_array($name, ['GLOBALS', '_GET', '_POST', '_SERVER', '_COOKIE', '_FILES', '_ENV', '_REQUEST'], true)) {
            continue;
        }
        if (stripos($name, 'pass') !== false || stripos($name, 'pwd') !== false) {
            continue;
        }
        if (is_scalar($value) || $value === null) {
            $globals[] = $name;
        } elseif (is_object($value)) {
            $globals[] = $name . ':' . get_class($value);
        } elseif (is_array($value)) {
            $globals[] = $name . ':array(' . implode(',', array_slice(array_keys($value), 0, 8)) . ')';
        }
    }

    $constants = [];
    foreach (array_keys(get_defined_constants(true)['user'] ?? []) as $name) {
        if (stripos($name, 'pass') !== false || stripos($name, 'pwd') !== false) {
            continue;
        }
        $constants[] = $name;
    }

    return [
        'config' => $GLOBALS['cardeventConfigLoaded'] ?? null,
        'globals' => array_slice($globals, 0, 30),
        'constants' => array_slice($constants, 0, 30),
    ];
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

    throw new RuntimeException('Connexion DB introuvable dans config.php | debug=' . json_encode(cardevent_config_debug(), JSON_UNESCAPED_UNICODE));
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

function try_query($db, $sql, $params, &$errors)
{
    try {
        if ($db instanceof PDO) {
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        $stmt = $db->prepare($sql);
        if (!$stmt) {
            throw new RuntimeException($db->error);
        }
        mysqli_bind_params($stmt, $params);
        $stmt->execute();
        $result = $stmt->get_result();
        $rows = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $rows[] = $row;
            }
        }
        $stmt->close();
        return $rows;
    } catch (Throwable $e) {
        $errors[] = $e->getMessage();
        return null;
    }
}

try {
    $pdo = cardevent_get_db();
    $authErrors = [];
    $authenticated = false;

    foreach ([
        'SELECT membre_id FROM sessions WHERE token = ? AND expires_at > NOW() LIMIT 1',
        'SELECT user_id FROM sessions WHERE token = ? AND expires_at > NOW() LIMIT 1',
        'SELECT id_membre FROM sessions WHERE token = ? AND expires_at > NOW() LIMIT 1',
        'SELECT membre_id FROM sessions WHERE token = ? LIMIT 1',
        'SELECT user_id FROM sessions WHERE token = ? LIMIT 1',
        'SELECT id_membre FROM sessions WHERE token = ? LIMIT 1',
        'SELECT membre_id FROM api_sessions WHERE token = ? LIMIT 1',
        'SELECT user_id FROM api_sessions WHERE token = ? LIMIT 1',
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
            if (fetch_scalar($pdo, $authSql, [$token])) {
                $authenticated = true;
                break;
            }
        } catch (Throwable $e) {
            $authErrors[] = $e->getMessage();
        }
    }

    $authWarning = null;
    if (!$authenticated) {
        $authWarning = 'Session non retrouvée pour cette API, calcul des notes autorisé en lecture seule';
    }

    $beforeDate = null;
    if ($activityId > 0) {
        foreach ([
            'SELECT date_depart FROM activite WHERE id = ? LIMIT 1',
            'SELECT date FROM activite WHERE id = ? LIMIT 1',
            'SELECT date_depart FROM activites WHERE id = ? LIMIT 1',
            'SELECT date FROM activites WHERE id = ? LIMIT 1',
        ] as $dateSql) {
            try {
                $beforeDate = fetch_scalar($pdo, $dateSql, [$activityId]) ?: null;
                if ($beforeDate !== null) {
                    break;
                }
            } catch (Throwable $e) {
                // Try the next known schema variant.
            }
        }
    }

    $errors = [];
    $queries = [];
    foreach (['membre_id', 'id_membre', 'user_id', 'joueur_id'] as $memberCol) {
        foreach (['activite_id', 'id_activite', 'activity_id'] as $activityCol) {
            foreach (['activite', 'activites'] as $activityTable) {
                foreach (['date_depart', 'date'] as $dateCol) {
                    $sql = "
                        SELECT
                            m.pseudo,
                            ROUND(AVG(CAST(REPLACE(p.sergio_score, ',', '.') AS DECIMAL(10,2))), 1) AS sergio_score_moyen
                        FROM participation p
                        INNER JOIN membres m ON m.id = p.$memberCol
                        INNER JOIN $activityTable a ON a.id = p.$activityCol
                        WHERE p.sergio_score IS NOT NULL
                          AND TRIM(p.sergio_score) <> ''
                          AND REPLACE(p.sergio_score, ',', '.') REGEXP '^-?[0-9]+([.][0-9]+)?$'
                    ";
                    $params = [];
                    if ($beforeDate !== null) {
                        $sql .= " AND a.$dateCol < ? ";
                        $params[] = $beforeDate;
                    }
                    $sql .= " GROUP BY m.id, m.pseudo ORDER BY m.pseudo ASC";
                    $queries[] = [$sql, $params];
                }
            }
        }
    }

    foreach ($queries as $query) {
        $sql = $query[0];
        $params = $query[1];
        $rows = try_query($pdo, $sql, $params, $errors);
        if ($rows !== null) {
            echo json_encode([
                'success' => true,
                'scores' => $rows,
                'count' => count($rows),
                'before_date' => $beforeDate,
                'auth_warning' => $authWarning,
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Aucune requête sergio_score compatible',
        'debug' => array_slice(array_values(array_unique($errors)), 0, 8),
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
