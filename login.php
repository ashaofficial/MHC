<?php
header("Content-Type: application/json");
include "secure/config.php";
include "secure/db.php";
include "secure/jwt.php";

$username = $_POST['username'] ?? '';
$password = $_POST['password'] ?? '';

if (!$username || !$password) {
    http_response_code(400);
    echo json_encode(["status"=>"error","message"=>"Enter username and password"]);
    exit;
}

// Query: credential -> users -> roles
$sql = "SELECT c.id AS cred_id, c.user_id, c.password_hash, u.name AS user_name, u.id AS user_pk, r.role_name
        FROM credential c
        JOIN users u ON c.user_id = u.id
        LEFT JOIN roles r ON u.role_id = r.id
        WHERE c.username = ? LIMIT 1";
$stmt = $conn->prepare($sql);
if ($stmt === false) {
    error_log("Prepare failed: " . $conn->error);
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Database error"]);
    exit;
}
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    http_response_code(401);
    echo json_encode(["status"=>"error","message"=>"Invalid login"]);
    exit;
}

// Verify password
$stored_hash = $user['password_hash'];
$verified = false;

// If stored value looks like a bcrypt/argon hash, use password_verify
if (is_string($stored_hash) && preg_match('/^\$2[ayb]\$|^\$argon2/', $stored_hash)) {
    $verified = password_verify($password, $stored_hash);
} else {
    // Stored value is probably plain text. If it matches provided password, hash it and update DB.
    if ($stored_hash === $password) {
        $newhash = password_hash($password, PASSWORD_DEFAULT);
        $upd = $conn->prepare("UPDATE credential SET password_hash = ?, updated_on = NOW() WHERE id = ?");
        if ($upd) {
            $upd->bind_param("si", $newhash, $user['cred_id']);
            $upd->execute();
            $stored_hash = $newhash;
            $verified = password_verify($password, $stored_hash);
        } else {
            // fallback: still attempt verify using new hash
            $stored_hash = password_hash($password, PASSWORD_DEFAULT);
            $verified = password_verify($password, $stored_hash);
        }
    } else {
        // If it's not bcrypt and doesn't equal plain password, treat as not verified
        $verified = false;
    }
}

if (!$verified) {
    http_response_code(401);
    echo json_encode(["status"=>"error","message"=>"Invalid login"]);
    exit;
}

// Create access token (short-lived)
$payload = [
    "user_id" => (int)$user["user_id"],
    "username" => $username,
    "role" => $user["role_name"] ?? null,
    "exp" => time() + 3600 // 1 hour
];
$access_token = generateJWT($payload);

// Create a secure random refresh token (server-side) and store it in DB for 7 days
$refresh_token = bin2hex(random_bytes(32));
$refresh_expires = date('Y-m-d H:i:s', time() + 7*24*3600); // 7 days

// Store refresh token in DB (column named refresh_token per schema)
$sess = $conn->prepare("INSERT INTO user_session (user_id, refresh_token, expires_at) VALUES (?, ?, ?)");
if ($sess === false) {
    error_log("Session prepare error: " . $conn->error);
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Session storage error"]);
    exit;
}
$sess->bind_param("iss", $user["user_id"], $refresh_token, $refresh_expires);
$sess->execute();
$sess_id = $conn->insert_id;

// Set cookies safely
$secureFlag = !empty($use_https); // from secure/config.php
$cookieOptionsAccess = [
    'expires' => time() + 3600,
    'path' => '/',
    'httponly' => true,
    'secure' => $secureFlag,
    'samesite' => 'Lax'
];
setcookie('access_token', $access_token, $cookieOptionsAccess);

// Refresh token as HttpOnly cookie (long lived)
$cookieOptionsRefresh = [
    'expires' => time() + 7*24*3600,
    'path' => '/',
    'httponly' => true,
    'secure' => $secureFlag,
    'samesite' => 'Lax'
];
setcookie('refresh_token', $refresh_token, $cookieOptionsRefresh);

// Also return token to JS for SPA usage if needed
echo json_encode([
    "status" => "success",
    "token"  => $access_token,
    "role"   => $user["role_name"] ?? null
]);
exit;
?>
