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

function quote_identifier($name)
{
    if (!preg_match('/^[A-Za-z0-9_]+$/', $name)) {
        throw new RuntimeException('Identifiant SQL invalide: ' . $name);
    }
    return '`' . $name . '`';
}

function table_columns($db, $table, &$errors)
{
    $rows = try_query($db, 'SHOW COLUMNS FROM ' . quote_identifier($table), [], $errors);
    if ($rows === null || empty($rows)) {
        return [];
    }

    $columns = [];
    foreach ($rows as $row) {
        $field = $row['Field'] ?? $row['field'] ?? null;
        if ($field !== null && $field !== '') {
            $columns[strtolower($field)] = $field;
        }
    }
    return $columns;
}

function first_existing_column($columns, $candidates)
{
    foreach ($candidates as $candidate) {
        $key = strtolower($candidate);
        if (isset($columns[$key])) {
            return $columns[$key];
        }
    }
    return null;
}

function first_existing_table($db, $candidates, &$errors)
{
    foreach ($candidates as $table) {
        $columns = table_columns($db, $table, $errors);
        if (!empty($columns)) {
            return [$table, $columns];
        }
    }
    return [null, []];
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
    list($participationTable, $participationColumns) = first_existing_table($pdo, ['participation', 'participations'], $errors);
    list($memberTable, $memberColumns) = first_existing_table($pdo, ['membres', 'membre', 'users', 'utilisateurs'], $errors);
    list($activityTable, $activityColumns) = first_existing_table($pdo, ['activite', 'activites', 'activity', 'activities'], $errors);

    $scoreCol = first_existing_column($participationColumns, ['sergio_score', 'sergioscore', 'score_sergio', 'sergio', 'note_sergio', 'note']);
    $memberCol = first_existing_column($participationColumns, ['membre_id', 'id_membre', 'member_id', 'id_member', 'joueur_id', 'id_joueur', 'user_id', 'id_user', 'idmembre', 'idjoueur']);
    $activityCol = first_existing_column($participationColumns, ['activite_id', 'id_activite', 'activity_id', 'id_activity', 'partie_id', 'id_partie', 'event_id', 'id_event']);
    $memberIdCol = first_existing_column($memberColumns, ['id', 'membre_id', 'id_membre', 'user_id', 'id_user']);
    $pseudoCol = first_existing_column($memberColumns, ['pseudo', 'username', 'login', 'nom', 'name']);
    $activityIdCol = first_existing_column($activityColumns, ['id', 'activite_id', 'id_activite', 'activity_id', 'id_activity']);
    $dateCol = first_existing_column($activityColumns, ['date_depart', 'date', 'start_date', 'date_start', 'debut', 'datetime']);

    $schemaDebug = [
        'participation_table' => $participationTable,
        'participation_columns' => array_values($participationColumns),
        'member_table' => $memberTable,
        'member_columns' => array_values($memberColumns),
        'activity_table' => $activityTable,
        'activity_columns' => array_values($activityColumns),
        'chosen' => [
            'score' => $scoreCol,
            'member_fk' => $memberCol,
            'activity_fk' => $activityCol,
            'member_id' => $memberIdCol,
            'pseudo' => $pseudoCol,
            'activity_id' => $activityIdCol,
            'activity_date' => $dateCol,
        ],
    ];

    if (!$participationTable || !$memberTable || !$scoreCol || !$memberCol || !$memberIdCol || !$pseudoCol) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Colonnes indispensables introuvables pour sergio_score',
            'schema' => $schemaDebug,
            'debug' => array_slice(array_values(array_unique($errors)), 0, 8),
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $sql = "
        SELECT
            m." . quote_identifier($pseudoCol) . " AS pseudo,
            ROUND(AVG(CAST(REPLACE(p." . quote_identifier($scoreCol) . ", ',', '.') AS DECIMAL(10,2))), 1) AS sergio_score_moyen
        FROM " . quote_identifier($participationTable) . " p
        INNER JOIN " . quote_identifier($memberTable) . " m
            ON m." . quote_identifier($memberIdCol) . " = p." . quote_identifier($memberCol) . "
    ";
    $params = [];

    if ($beforeDate !== null && $activityTable && $activityCol && $activityIdCol && $dateCol) {
        $sql .= "
            INNER JOIN " . quote_identifier($activityTable) . " a
                ON a." . quote_identifier($activityIdCol) . " = p." . quote_identifier($activityCol) . "
        ";
    }

    $sql .= "
        WHERE p." . quote_identifier($scoreCol) . " IS NOT NULL
          AND TRIM(p." . quote_identifier($scoreCol) . ") <> ''
          AND REPLACE(p." . quote_identifier($scoreCol) . ", ',', '.') REGEXP '^-?[0-9]+([.][0-9]+)?$'
    ";

    if ($beforeDate !== null && $activityTable && $activityCol && $activityIdCol && $dateCol) {
        $sql .= " AND a." . quote_identifier($dateCol) . " < ? ";
        $params[] = $beforeDate;
    }

    $sql .= "
        GROUP BY m." . quote_identifier($memberIdCol) . ", m." . quote_identifier($pseudoCol) . "
        ORDER BY m." . quote_identifier($pseudoCol) . " ASC
    ";

    $rows = try_query($pdo, $sql, $params, $errors);
    if ($rows === null) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Requête moyenne sergio_score impossible',
            'schema' => $schemaDebug,
            'debug' => array_slice(array_values(array_unique($errors)), 0, 8),
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo json_encode([
        'success' => true,
        'scores' => $rows,
        'count' => count($rows),
        'before_date' => $beforeDate,
        'schema' => $schemaDebug['chosen'],
        'auth_warning' => $authWarning,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
