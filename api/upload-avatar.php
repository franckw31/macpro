<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

try {
    // DB connection - adjust if your environment differs
    $pdo = new PDO('mysql:host=localhost;dbname=dbs9616600;charset=utf8mb4','root','Kookies7*',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);

    // Parse headers robustly (some PHP setups don't populate HTTP_AUTHORIZATION)
    $headers = function_exists('apache_request_headers') ? apache_request_headers() : [];
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $headers['Authorization'] ?? $headers['authorization'] ?? '';
    $token = '';
    if (preg_match('/Bearer\s+(\S+)/', $authHeader, $m)) { $token = $m[1]; }
    // Fallback: token as form field (multipart) or query param
    if (empty($token) && isset($_POST['token'])) { $token = trim($_POST['token']); }
    if (empty($token) && isset($_GET['token'])) { $token = trim($_GET['token']); }
    if (empty($token)) { error_log('[upload-avatar] token missing'); echo json_encode(['success'=>false,'error'=>'token manquant']); exit; }

    // Validate token in app_auth_tokens table
    $stmt = $pdo->prepare("SELECT membre_id, expires_at FROM app_auth_tokens WHERE token = ? LIMIT 1");
    $stmt->execute([$token]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) { error_log('[upload-avatar] token not found in DB: '.substr($token,0,20)); echo json_encode(['success'=>false,'error'=>'token invalide']); exit; }
    if (!empty($row['expires_at']) && strtotime($row['expires_at']) <= time()) { error_log('[upload-avatar] token expired for member '.$row['membre_id']); echo json_encode(['success'=>false,'error'=>'token expiré']); exit; }

    $memberId = intval($row['membre_id']);

    if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success'=>false,'error'=>'fichier manquant']); exit;
    }

    $f = $_FILES['file'];
    $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg','jpeg','png','gif','webp'];
    if (!in_array($ext, $allowed)) { echo json_encode(['success'=>false,'error'=>'type de fichier non autorisé']); exit; }

    $targetDir = __DIR__ . '/../images/faces/';
    if (!is_dir($targetDir)) { @mkdir($targetDir, 0755, true); }
    $safeName = 'profile_' . $memberId . '_' . time() . '.' . $ext;
    $targetPath = $targetDir . $safeName;

    if (!move_uploaded_file($f['tmp_name'], $targetPath)) {
        echo json_encode(['success'=>false,'error'=>'impossible d\'enregistrer le fichier']); exit;
    }

    // Update DB
    $u = $pdo->prepare("UPDATE membres SET photo = ? WHERE `id-membre` = ?");
    $u->execute([$safeName, $memberId]);

    $publicUrl = 'https://viendez.com/images/faces/' . rawurlencode($safeName);
    echo json_encode(['success'=>true,'photo_url'=>$publicUrl]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success'=>false,'error'=>'server error']);
}
