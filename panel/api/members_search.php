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
$sql = "SELECT `id-membre`, pseudo FROM membres";
if ($safe !== '') {
    $sql .= " WHERE pseudo LIKE '%" . $safe . "%'";
}
$sql .= " ORDER BY pseudo ASC LIMIT 50";
$res = @mysqli_query($con, $sql);
if ($res) {
    while ($r = mysqli_fetch_assoc($res)) {
        $out[] = ['id' => intval($r['id-membre']), 'pseudo' => $r['pseudo']];
    }
}
header('Content-Type: application/json; charset=utf-8');
echo json_encode($out);
