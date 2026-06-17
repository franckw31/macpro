<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

$configCandidates = [
    __DIR__ . '/config.php',
    __DIR__ . '/../config.php',
    __DIR__ . '/../panel/config.php',
];

foreach ($configCandidates as $file) {
    if (is_file($file)) {
        require_once $file;
        break;
    }
}

function json_response($data, $status = 200)
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function first_defined_constant($names)
{
    foreach ($names as $name) {
        if (defined($name)) {
            return constant($name);
        }
    }
    return null;
}

function first_global($names)
{
    foreach ($names as $name) {
        if (isset($GLOBALS[$name]) && $GLOBALS[$name] !== '') {
            return $GLOBALS[$name];
        }
    }
    return null;
}

function get_db()
{
    if (function_exists('get_pdo')) {
        return get_pdo();
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

    $host = first_defined_constant(['DB_HOST', 'DATABASE_HOST', 'MYSQL_HOST', 'HOST'])
        ?: first_global(['db_host', 'dbhost', 'host', 'hostname', 'servername', 'server']);
    $database = first_defined_constant(['DB_NAME', 'DATABASE_NAME', 'MYSQL_DATABASE', 'DB_DATABASE'])
        ?: first_global(['db_name', 'dbname', 'database', 'bdd']);
    $user = first_defined_constant(['DB_USER', 'DATABASE_USER', 'MYSQL_USER', 'DB_USERNAME'])
        ?: first_global(['db_user', 'dbuser', 'user', 'username', 'login']);
    $password = first_defined_constant(['DB_PASS', 'DB_PASSWORD', 'DATABASE_PASSWORD', 'MYSQL_PASSWORD'])
        ?: first_global(['db_pass', 'dbpass', 'db_password', 'password', 'pass', 'pwd']);

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

    throw new RuntimeException('Connexion DB introuvable');
}

function bind_mysqli_params($stmt, $params)
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

function db_all($db, $sql, $params = [])
{
    if ($db instanceof PDO) {
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    $stmt = $db->prepare($sql);
    if (!$stmt) {
        throw new RuntimeException($db->error);
    }
    bind_mysqli_params($stmt, $params);
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
}

function db_exec($db, $sql, $params = [])
{
    if ($db instanceof PDO) {
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return;
    }

    $stmt = $db->prepare($sql);
    if (!$stmt) {
        throw new RuntimeException($db->error);
    }
    bind_mysqli_params($stmt, $params);
    $stmt->execute();
    $stmt->close();
}

function input_data()
{
    $json = json_decode(file_get_contents('php://input'), true);
    return is_array($json) ? array_merge($_POST, $json) : $_POST;
}

try {
    $db = get_db();
    $input = input_data();
    $token = trim($_GET['token'] ?? $input['token'] ?? '');
    $activityId = (int)($_GET['activity_id'] ?? $_GET['id_activite'] ?? $input['activity_id'] ?? $input['id_activite'] ?? 0);
    $action = trim($_GET['action'] ?? $input['action'] ?? 'fetch');

    if ($token === '') {
        json_response(['success' => false, 'error' => 'Token manquant'], 401);
    }
    if ($activityId <= 0) {
        json_response(['success' => false, 'error' => 'Activité manquante'], 400);
    }

    $users = db_all(
        $db,
        'SELECT t.membre_id, m.pseudo
         FROM app_auth_tokens t
         INNER JOIN membres m ON m.`id-membre` = t.membre_id
         WHERE t.token = ? AND t.expires_at > NOW()
         LIMIT 1',
        [$token]
    );
    if (!$users) {
        json_response(['success' => false, 'error' => 'Session invalide'], 401);
    }

    $userId = (int)$users[0]['membre_id'];
    $pseudo = $users[0]['pseudo'] ?: 'Joueur';

    $organizers = db_all(
        $db,
        'SELECT `id-membre` AS organizer_id FROM activite WHERE `id-activite` = ? LIMIT 1',
        [$activityId]
    );
    $organizerId = $organizers ? (int)$organizers[0]['organizer_id'] : 0;

    if ($action === 'send') {
        $message = trim($input['message'] ?? $input['msg'] ?? '');
        if ($message === '') {
            json_response(['success' => false, 'error' => 'Message vide'], 400);
        }

        db_exec(
            $db,
            'INSERT INTO qv_messages
                (id_activite, id_expediteur, pseudo_exp, role, message, id_destinataire, lu_orga, lu_joueur, lu_to_recipient)
             VALUES
                (?, ?, ?, ?, ?, ?, 0, 1, 0)',
            [$activityId, $userId, $pseudo, 'joueur', $message, $organizerId]
        );

        json_response(['success' => true]);
    }

    $messages = db_all(
        $db,
        'SELECT id, id_expediteur, pseudo_exp, role, message, created_at
         FROM qv_messages
         WHERE id_activite = ?
           AND (id_expediteur = ? OR id_destinataire = ? OR id_destinataire = 0)
         ORDER BY created_at ASC, id ASC',
        [$activityId, $userId, $userId]
    );

    $msgs = [];
    foreach ($messages as $row) {
        $fromId = (int)$row['id_expediteur'];
        $msgs[] = [
            'id' => (int)$row['id'],
            'from' => $row['pseudo_exp'],
            'from_id' => $fromId,
            'role' => $row['role'],
            'mine' => $fromId === $userId,
            'msg' => $row['message'],
            'at' => $row['created_at'],
        ];
    }

    json_response(['success' => true, 'msgs' => $msgs]);
} catch (Throwable $e) {
    json_response(['success' => false, 'error' => $e->getMessage()], 500);
}
