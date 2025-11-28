<?php
header('Content-Type: application/json');
include_once __DIR__ . '/../auth.php';
include_once __DIR__ . '/../secure/db.php';

if (strtolower($USER['role'] ?? '') !== 'administrator') {
    http_response_code(403);
    echo json_encode(['status'=>'error','message'=>'Access denied']);
    exit;
}

$name = trim($_POST['role_name'] ?? '');
if (!$name) {
    http_response_code(400);
    echo json_encode(['status'=>'error','message'=>'Role name required']);
    exit;
}

$ins = $conn->prepare("INSERT INTO roles (role_name, status, created_at, updated_at) VALUES (?, 'active', NOW(), NOW())");
$ins->bind_param('s', $name);
if ($ins->execute()) {
    echo json_encode(['status'=>'success','id'=>$conn->insert_id]);
} else {
    http_response_code(500);
    echo json_encode(['status'=>'error','message'=>$ins->error]);
}
