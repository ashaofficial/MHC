<?php
header("Content-Type: application/json");
include "secure/config.php";
include "secure/db.php";
include "secure/jwt.php";

// Determine access token from cookie or Authorization header
$token = $_COOKIE['access_token'] ?? '';
$headers = getallheaders();
if (!$token) {
    $token = $headers["Authorization"] ?? '';
    $token = str_replace("Bearer ", "", $token);
}

$refresh_cookie = $_COOKIE['refresh_token'] ?? '';

if ($token) {
    $data = verifyJWT($token);
    if ($data && isset($data['user_id'])) {
        $user_id = (int)$data['user_id'];

        if ($refresh_cookie) {
            // Expire the specific session matching the refresh token
            $sql = "UPDATE user_session SET expires_at = NOW() WHERE user_id = ? AND refresh_token = ?";
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param("is", $user_id, $refresh_cookie);
                $stmt->execute();
            }
        } else {
            // No refresh token available; expire all sessions for user
            $sql = "UPDATE user_session SET expires_at = NOW() WHERE user_id = ?";
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
            }
        }
    }
}

// Clear cookies: access and refresh
$cookie_options = ['expires' => time() - 3600, 'path' => '/', 'httponly' => true, 'secure' => false, 'samesite' => 'Lax'];
setcookie('access_token', '', $cookie_options);
setcookie('refresh_token', '', $cookie_options);

echo json_encode(["status" => "success", "message" => "Logged out"]);
?>
