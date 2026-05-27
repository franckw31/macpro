<?php
session_start();
error_reporting(0);
include(__DIR__ . '/../../include/config.php');

$uid = isset($_SESSION['id']) ? intval($_SESSION['id']) : 0;
if ($uid <= 0) {
    http_response_code(401);
    echo json_encode([]);
    exit;
}
// only allow admins 2 and 265 to search members
if (!in_array($uid, [2,265], true)) {
    http_response_code(403);
    echo json_encode([]);
    exit;
}

$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$out = [];
$safe = mysqli_real_escape_string($con, $q);
$sql = "SELECT `id-membre`, pseudo, email FROM membres";
if ($safe !== '') {
    // If user typed a number only, allow searching by id
    if (ctype_digit($q)) {
        $sql .= " WHERE `id-membre` = " . intval($q);
    } else {
        // prefix match on pseudo (case-insensitive) and email prefix
        $esc = mysqli_real_escape_string($con, strtolower($q));
        $sql .= " WHERE LOWER(pseudo) LIKE '" . $esc . "%' OR LOWER(email) LIKE '" . $esc . "%'";
    }
}
// return a reasonable limit to avoid huge payloads
$sql .= " ORDER BY pseudo ASC LIMIT 200";
$res = @mysqli_query($con, $sql);
if ($res) {
    while ($r = mysqli_fetch_assoc($res)) {
        $out[] = ['id' => intval($r['id-membre']), 'pseudo' => $r['pseudo'], 'email' => $r['email'] ?? ''];
    }
}
header('Content-Type: application/json; charset=utf-8');
echo json_encode($out);
