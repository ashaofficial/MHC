<?php
include "secure/config.php";
include "secure/db.php";
include "secure/jwt.php";

$token = null;
// Check Authorization header first
$headers = getallheaders();
if (!empty($headers['Authorization'])) {
    $token = trim(str_replace('Bearer', '', $headers['Authorization']));
}
// Fallback to cookie
if (!$token && isset($_COOKIE['access_token'])) {
    $token = $_COOKIE['access_token'];
}
if (!$token) {
    http_response_code(401);
    die(json_encode(["status"=>"error","message"=>"No token provided"]));
}
$data = verifyJWT($token);
if (!$data) {
    http_response_code(401);
    die(json_encode(["status"=>"error","message"=>"Invalid token"]));
}
// Check expiry (exp)
if (isset($data['exp']) && $data['exp'] < time()) {
    http_response_code(401);
    die(json_encode(["status"=>"error","message"=>"Token expired"]));
}
// Verify there is an active session for this user matching the refresh token cookie
$user_id = $data['user_id'] ?? null;
$refresh = $_COOKIE['refresh_token'] ?? '';
if (!$user_id || !$refresh) {
    http_response_code(401);
    die(json_encode(["status"=>"error","message"=>"Invalid session"]));
}

$session_sql = "SELECT id FROM user_session WHERE user_id = ? AND refresh_token = ? AND expires_at > NOW() LIMIT 1";
$sstmt = $conn->prepare($session_sql);
if ($sstmt === false) {
    http_response_code(500);
    die(json_encode(["status"=>"error","message"=>"Session lookup failed"]));
}
$sstmt->bind_param("is", $user_id, $refresh);
$sstmt->execute();
$sres = $sstmt->get_result();
if ($sres->num_rows === 0) {
    http_response_code(401);
    die(json_encode(["status"=>"error","message"=>"Session not found or expired"]));
}

$USER = $data; // available for the included script
