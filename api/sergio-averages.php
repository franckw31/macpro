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

function load_cardevent_config()
{
    $candidates = [
        __DIR__ . '/config.php',
        __DIR__ . '/../config.php',
        __DIR__ . '/../panel/config.php',
        __DIR__ . '/../../panel/config.php',
    ];

    foreach ($candidates as $file) {
        if (is_file($file)) {
            require_once $file;
            return $file;
        }
    }

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'config.php introuvable',
        'tested' => $candidates,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

load_cardevent_config();

$token = trim($_GET['token'] ?? '');
$activityId = (int)($_GET['activity_id'] ?? 0);

if ($token === '') {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Token manquant']);
    exit;
}

function cardevent_get_pdo()
{
    if (function_exists('get_pdo')) {
        return get_pdo();
    }

    if (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) {
        return $GLOBALS['pdo'];
    }

    if (isset($GLOBALS['db']) && $GLOBALS['db'] instanceof PDO) {
        return $GLOBALS['db'];
    }

    throw new RuntimeException('Connexion PDO introuvable dans config.php');
}

function fetch_scalar($pdo, $sql, $params = [])
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchColumn();
}

function try_query($pdo, $sql, $params, &$errors)
{
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $errors[] = $e->getMessage();
        return null;
    }
}

try {
    $pdo = cardevent_get_pdo();
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

    if (!$authenticated) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'error' => 'Session invalide',
            'debug' => array_slice(array_values(array_unique($authErrors)), 0, 3),
        ], JSON_UNESCAPED_UNICODE);
        exit;
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
