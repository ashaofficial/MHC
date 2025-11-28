<?php
header('Content-Type: application/json');
include_once __DIR__ . '/../auth.php';
include_once __DIR__ . '/../secure/db.php';
include_once __DIR__ . '/../secure/password_utils.php';
include_once __DIR__ . '/../secure/config_messages.php';
include_once __DIR__ . '/../components/helpers.php';

if (!isAdmin($USER['role'] ?? '')) {
    Notification::jsonResponse('error', getMessage('MSG_ERROR_ACCESS_DENIED'), null, 403);
}

$id = isset($_POST['id']) && $_POST['id'] !== '' ? (int)$_POST['id'] : 0;
$name = trim($_POST['name'] ?? '');
$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';
$role_id = isset($_POST['role_id']) ? (int)$_POST['role_id'] : null;
$email = trim($_POST['email'] ?? '');
$mobile = trim($_POST['mobile'] ?? '');

if (!$name || !$username) {
    Notification::jsonResponse('error', getMessage('MSG_ERROR_REQUIRED_FIELDS') . ': Name and username required', null, 400);
}

if ($id) {
    // update
    $ust = $conn->prepare("UPDATE users SET name = ?, role_id = ?, email = ?, mobile = ?, updated_at = NOW() WHERE id = ?");
    $ust->bind_param('sissi', $name, $role_id, $email, $mobile, $id);
    $ust->execute();
    if ($password) {
        $hash = PasswordUtils::hash($password);
        $c = $conn->prepare("UPDATE credential SET password_hash = ?, updated_on = NOW() WHERE user_id = ?");
        $c->bind_param('si', $hash, $id);
        $c->execute();
    }
    Notification::jsonResponse('success', getMessage('MSG_USER_UPDATED'));
    exit;
} else {
    // create
    $ust = $conn->prepare("INSERT INTO users (name, role_id, email, mobile, status, created_at, updated_at) VALUES (?, ?, ?, ?, 'active', NOW(), NOW())");
    $ust->bind_param('siss', $name, $role_id, $email, $mobile);
    if (!$ust->execute()) {
        http_response_code(500);
        echo json_encode(['status'=>'error','message'=>$ust->error]);
        exit;
    }
    $newid = $conn->insert_id;
    // Validate and hash password
    if (empty($password)) {
        // Generate random password if not provided
        $password = bin2hex(random_bytes(4));
    }
    $hash = PasswordUtils::hash($password);
    $c = $conn->prepare("INSERT INTO credential (user_id, username, password_hash) VALUES (?, ?, ?)");
    $c->bind_param('iss', $newid, $username, $hash);
    $c->execute();

    // if role is consultant, add to consultants (basic)
    $roleName = null;
    $rr = $conn->prepare("SELECT role_name FROM roles WHERE id = ? LIMIT 1");
    $rr->bind_param('i', $role_id);
    $rr->execute();
    $rres = $rr->get_result();
    if ($rres && $rres->num_rows) {
        $roleName = $rres->fetch_assoc()['role_name'];
    }
    if (strtolower($roleName ?? '') === 'consultant') {
        $spec = trim($_POST['specialization'] ?? 'General');
        $insc = $conn->prepare("INSERT INTO consultants (user_id, specialization, status, created_at, updated_at) VALUES (?, ?, 'active', NOW(), NOW())");
        $insc->bind_param('is', $newid, $spec);
        $insc->execute();
    }

    Notification::jsonResponse('success', getMessage('MSG_USER_CREATED'), ['id' => $newid]);
}
