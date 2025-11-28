<?php
/**
 * auth.php
 * Central auth helper included by pages. Verifies JWT access token (cookie or Authorization header),
 * loads user profile into $USER and provides simple helpers `requireLogin` and `requireRole`.
 */

// Load configuration, DB and JWT helpers
include_once __DIR__ . '/secure/config.php';
include_once __DIR__ . '/secure/db.php';
include_once __DIR__ . '/secure/jwt.php';

$USER = [];

function get_authorization_header() {
    if (!empty($_SERVER['HTTP_AUTHORIZATION'])) return $_SERVER['HTTP_AUTHORIZATION'];
    if (function_exists('getallheaders')) {
        $h = getallheaders();
        if (!empty($h['Authorization'])) return $h['Authorization'];
        if (!empty($h['authorization'])) return $h['authorization'];
    }
    return '';
}

// Determine token from cookie or Authorization header
$token = $_COOKIE['access_token'] ?? '';
if (!$token) {
    $hdr = get_authorization_header();
    if ($hdr) $token = preg_replace('/^(Bearer\s+)/i', '', trim($hdr));
}

// Verify token and populate $USER from DB when possible
if ($token) {
    $data = verifyJWT($token);
    if ($data && is_array($data)) {
        if (isset($data['exp']) && (int)$data['exp'] < time()) {
            // token expired
            $data = false;
        }
    }

    if ($data && isset($data['user_id'])) {
        $user_id = (int)$data['user_id'];
        // Attempt to fetch latest user profile from DB
        $sql = "SELECT u.id AS user_id, u.name, u.email, u.mobile, u.doj, u.dob, u.description, u.photo, r.role_name
                FROM users u
                LEFT JOIN roles r ON u.role_id = r.id
                WHERE u.id = ? LIMIT 1";

        if (isset($conn)) {
            $stmt = $conn->prepare($sql);
            if ($stmt) {
                $stmt->bind_param('i', $user_id);
                $stmt->execute();
                $res = $stmt->get_result();
                $row = $res->fetch_assoc();
                if ($row) {
                    $USER = [
                        'user_id' => (int)$row['user_id'],
                        'name' => $row['name'] ?? '',
                        'email' => $row['email'] ?? '',
                        'mobile' => $row['mobile'] ?? '',
                        'doj' => $row['doj'] ?? '',
                        'dob' => $row['dob'] ?? '',
                        'description' => $row['description'] ?? '',
                        'photo' => $row['photo'] ?? null,
                        'role' => $row['role_name'] ?? ($data['role'] ?? '')
                    ];
                    if (!isset($USER['username']) && isset($data['username'])) $USER['username'] = $data['username'];
                }
            }
        } else {
            // DB connection unavailable; fall back to token payload only
            $USER = [
                'user_id' => $user_id,
                'username' => $data['username'] ?? null,
                'role' => $data['role'] ?? null
            ];
        }
    }
}

/**
 * Require that a user is logged in. Dies with 401 if not.
 */
function requireLogin() {
    global $USER;
    if (empty($USER['user_id'])) {
        header('HTTP/1.1 401 Unauthorized');
        die('Access denied!');
    }
}

/**
 * Require one of the roles (string or array). Dies with 403 if not allowed.
 */
function requireRole($roles) {
    global $USER;
    $role = strtolower(trim($USER['role'] ?? ''));
    $allowed = is_array($roles) ? $roles : [$roles];
    $allowed = array_map(function($r){ return strtolower(trim($r)); }, $allowed);
    if (!in_array($role, $allowed, true)) {
        header('HTTP/1.1 403 Forbidden');
        die('Access denied!');
    }
}

?>
