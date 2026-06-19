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
        'SELECT COUNT(*) FROM participation WHERE `id-membre` = ? AND COALESCE(`gain`, 0) > 0',
        [$memberId]
    );
    $recavesTotal = fetch_scalar(
        $db,
        'SELECT COALESCE(SUM(COALESCE(`recave`, 0)), 0) FROM participation WHERE `id-membre` = ?',
        [$memberId]
    );
    $brutTotal = (float)$gainTotal - (float)$coutInTotal - (float)$rakeTotal;

    echo json_encode([
        'success' => true,
        'member_id' => $memberId,
        'source' => $memberSource,
        'cout_in_total' => (float)$coutInTotal,
        'gain_total' => (float)$gainTotal,
        'max_gain' => (float)$maxGain,
        'rake_total' => (float)$rakeTotal,
        'brut_total' => $brutTotal,
        'parties_count' => (int)$partiesCount,
        'gains_count' => (int)$gainsCount,
        'victories_count' => (int)$victoriesCount,
        'itm_count' => (int)$itmCount,
        'recaves_total' => (int)$recavesTotal,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
