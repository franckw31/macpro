<?php
// Compatibility shim: some clients call /panel/api/register-activity.php
// Forward/include the real API implementation located at /api/register-activity.php

$real = __DIR__ . '/../../api/register-activity.php';
// Si accès direct en GET sans Accept: application/json, affiche une page HTML explicite
if ($_SERVER['REQUEST_METHOD'] === 'GET' && (!isset($_SERVER['HTTP_ACCEPT']) || strpos($_SERVER['HTTP_ACCEPT'], 'application/json') === false)) {
    echo "<html><head><title>API register-activity</title></head><body style='font-family:sans-serif;background:#222;color:#eee;padding:2em;'>";
    echo "<h2>API register-activity.php</h2>";
    echo "<p>Cette URL est une API REST. Pour l'utiliser, faites une requête AJAX ou <code>curl</code> avec authentification (session ou token).<br>";
    echo "Si vous voyez une page blanche, c'est que la réponse est du JSON, pas du HTML.</p>";
    echo "<hr><small>".date('c')."</small></body></html>";
    exit;
}
if (file_exists($real)) {
    require $real;
} else {
    header('Content-Type: application/json');
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Not found (shim)']);
}
