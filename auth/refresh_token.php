<?php
header('Content-Type: application/json');
include_once __DIR__ . '/../secure/config.php';
include_once __DIR__ . '/../secure/db.php';
include_once __DIR__ . '/../secure/jwt.php';

// Expect refresh_token to be sent as HttpOnly cookie
$refresh_token = $_COOKIE['refresh_token'] ?? null;
if (!$refresh_token) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'No refresh token provided']);
    exit;
}

// Lookup session
$stmt = $conn->prepare("SELECT id, user_id, expires_at FROM user_session WHERE refresh_token = ? LIMIT 1");
if ($stmt === false) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'DB error']);
    exit;
}
$stmt->bind_param('s', $refresh_token);
$stmt->execute();
$res = $stmt->get_result();
$session = $res->fetch_assoc();
if (!$session) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Invalid refresh token']);
    exit;
}

// Check expiry
$now = new DateTime('now');
$expiresAt = new DateTime($session['expires_at']);
if ($expiresAt < $now) {
    // expired
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Refresh token expired']);
    exit;
}

// Fetch user info (username and role)
$uStmt = $conn->prepare("SELECT u.id AS user_pk, c.username, r.role_name FROM users u LEFT JOIN credential c ON c.user_id = u.id LEFT JOIN roles r ON u.role_id = r.id WHERE u.id = ? LIMIT 1");
if ($uStmt === false) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'DB error']);
    exit;
}
$uStmt->bind_param('i', $session['user_id']);
$uStmt->execute();
$uRes = $uStmt->get_result();
$user = $uRes->fetch_assoc();
if (!$user) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'User not found']);
    exit;
}

// Generate new access token (1 hour)
$payload = [
    'user_id' => (int)$user['user_pk'],
    'username' => $user['username'] ?? null,
    'role' => $user['role_name'] ?? null,
    'exp' => time() + 3600
];
$access_token = generateJWT($payload);

// Rotate the refresh token (7 days)
$new_refresh = bin2hex(random_bytes(32));
$new_expires = date('Y-m-d H:i:s', time() + 7*24*3600);

$upd = $conn->prepare("UPDATE user_session SET refresh_token = ?, expires_at = ? WHERE id = ?");
if ($upd === false) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'DB error']);
    exit;
}
$upd->bind_param('ssi', $new_refresh, $new_expires, $session['id']);
$upd->execute();

// Set cookies (match login.php settings)
$secureFlag = !empty($use_https);
setcookie('access_token', $access_token, [
    'expires' => time() + 3600,
    'path' => '/',
    'httponly' => true,
    'secure' => $secureFlag,
    'samesite' => 'Lax'
]);
setcookie('refresh_token', $new_refresh, [
    'expires' => time() + 7*24*3600,
    'path' => '/',
    'httponly' => true,
    'secure' => $secureFlag,
    'samesite' => 'Lax'
]);

// Return new expiry so client can update UI (exp in seconds)
echo json_encode([
    'status' => 'success',
    'exp' => $payload['exp']
]);
exit;
?>