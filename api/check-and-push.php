<?php
// ============================================================
//  Script cron — vérifie les inscriptions et envoie des push
//  Fréquence recommandée : toutes les minutes
//
//  Commande cron (cPanel) :
//  * * * * * /usr/bin/php /home/USERNAME/public_html/api/check-and-push.php >> /home/USERNAME/logs/push.log 2>&1
// ============================================================

require_once __DIR__ . '/apns_config.php';

// ── Helpers JWT ES256 ────────────────────────────────────────

function base64url_encode(string $data): string {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function generateAPNsJWT(): string {
    $header  = base64url_encode(json_encode(['alg' => 'ES256', 'kid' => APNS_KEY_ID]));
    $payload = base64url_encode(json_encode(['iss' => APNS_TEAM_ID, 'iat' => time()]));
    $data    = "$header.$payload";

    $keyContent = file_get_contents(APNS_KEY_PATH);
    $privateKey = openssl_pkey_get_private($keyContent);
    if (!$privateKey) {
        throw new RuntimeException('Impossible de charger la clé APNs : ' . APNS_KEY_PATH);
    }

    openssl_sign($data, $derSig, $privateKey, OPENSSL_ALGO_SHA256);

    // Convertir DER → R||S (format requis par JWT ES256)
    $offset = 0;
    if (ord($derSig[$offset++]) !== 0x30) throw new RuntimeException('DER invalide');
    $len = ord($derSig[$offset++]);
    if ($len & 0x80) $offset += ($len & 0x7f); // longueur sur plusieurs octets

    $offset++; // marqueur 0x02 (R)
    $rLen = ord($derSig[$offset++]);
    $r = substr($derSig, $offset, $rLen);
    $offset += $rLen;

    $offset++; // marqueur 0x02 (S)
    $sLen = ord($derSig[$offset++]);
    $s = substr($derSig, $offset, $sLen);

    $r = str_pad(ltrim($r, "\x00"), 32, "\x00", STR_PAD_LEFT);
    $s = str_pad(ltrim($s, "\x00"), 32, "\x00", STR_PAD_LEFT);

    return "$data." . base64url_encode($r . $s);
}

// ── Envoi APNs HTTP/2 ────────────────────────────────────────

function sendAPNsPush(string $deviceToken, string $title, string $body, string $jwt): array {
    $host    = APNS_PRODUCTION ? 'https://api.push.apple.com' : 'https://api.sandbox.push.apple.com';
    $payload = json_encode([
        'aps' => [
            'alert' => ['title' => $title, 'body' => $body],
            'sound' => 'default',
        ]
    ]);

    $ch = curl_init("$host/3/device/$deviceToken");
    curl_setopt_array($ch, [
        CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_2_0,
        CURLOPT_PORT           => 443,
        CURLOPT_HTTPHEADER     => [
            "authorization: bearer $jwt",
            "apns-topic: " . APNS_BUNDLE_ID,
            "apns-push-type: alert",
            "apns-priority: 10",
            "Content-Type: application/json",
        ],
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ['code' => $httpCode, 'response' => $response];
}

// ── Main ─────────────────────────────────────────────────────

try {
    $pdo = new PDO(
        'mysql:host=localhost;dbname=dbs9616600;charset=utf8mb4',
        'root',
        'Kookies7*',
        [
            PDO::ATTR_ERRMODE          => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );

    // Table snapshots (mémorise le dernier count connu par activité)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `push_snapshots` (
            `snap_key`   VARCHAR(100) PRIMARY KEY,
            `value`      TEXT,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // Prochaine activité
    $stmt = $pdo->query("
        SELECT a.`id-activite`, a.`titre-activite`, a.`date_depart`,
               COUNT(p.`id-participation`) AS participants_count
        FROM `activite` a
        LEFT JOIN `participation` p ON p.`id-activite` = a.`id-activite`
        WHERE a.`date_depart` >= NOW()
        GROUP BY a.`id-activite`, a.`titre-activite`, a.`date_depart`
        ORDER BY a.`date_depart` ASC
        LIMIT 1
    ");
    $activity = $stmt->fetch();

    if (!$activity) {
        echo "[" . date('Y-m-d H:i:s') . "] Aucune activité prévue.\n";
        exit(0);
    }

    $activityId   = $activity['id-activite'];
    $currentCount = (int)$activity['participants_count'];
    $title        = $activity['titre-activite'];
    $snapKey      = "activity_{$activityId}_count";

    // Dernier count connu
    $stmt = $pdo->prepare("SELECT `value` FROM `push_snapshots` WHERE `snap_key` = ?");
    $stmt->execute([$snapKey]);
    $snapshot      = $stmt->fetch();
    $previousCount = $snapshot !== false ? (int)$snapshot['value'] : null;

    echo "[" . date('Y-m-d H:i:s') . "] \"$title\" | Inscrits: $currentCount"
        . " (précédent: " . ($previousCount ?? 'inconnu') . ")\n";

    // Envoyer push seulement si le count a changé (et qu'on a un référentiel)
    if ($previousCount !== null && $currentCount !== $previousCount) {
        $diff   = $currentCount - $previousCount;
        $emoji  = $diff > 0 ? '🟢' : '🔴';
        $action = $diff > 0
            ? "$diff nouvelle(s) inscription(s)"
            : abs($diff) . " désinscription(s)";

        $notifTitle = "$emoji $title";
        $notifBody  = "$action — $currentCount inscrit(s) au total";

        // Récupérer tous les tokens enregistrés
        try {
            $tokens = $pdo->query("SELECT `device_token` FROM `push_tokens`")->fetchAll();
        } catch (PDOException $e) {
            $tokens = [];
        }

        if (!empty($tokens)) {
            $jwt = generateAPNsJWT();
            foreach ($tokens as $row) {
                $result = sendAPNsPush($row['device_token'], $notifTitle, $notifBody, $jwt);
                $shortToken = substr($row['device_token'], 0, 8) . '...';
                echo "  → $shortToken : HTTP {$result['code']}";
                if ($result['response']) echo " | " . $result['response'];
                echo "\n";

                // Supprimer les tokens invalidés par Apple (410 = token désactivé)
                if ($result['code'] === 410) {
                    $del = $pdo->prepare("DELETE FROM `push_tokens` WHERE `device_token` = ?");
                    $del->execute([$row['device_token']]);
                    echo "    ↳ Token supprimé (désactivé par Apple)\n";
                }
            }
        } else {
            echo "  Aucun token enregistré, push ignoré.\n";
        }
    }

    // Mettre à jour le snapshot
    $stmt = $pdo->prepare("
        INSERT INTO `push_snapshots` (`snap_key`, `value`) VALUES (?, ?)
        ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)
    ");
    $stmt->execute([$snapKey, $currentCount]);

} catch (Exception $e) {
    echo "[ERREUR] " . $e->getMessage() . "\n";
    exit(1);
}
