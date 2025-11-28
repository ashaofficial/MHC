<?php
header('Content-Type: application/json');
include_once __DIR__ . '/../auth.php';
include_once __DIR__ . '/../secure/db.php';

if (strtolower($USER['role'] ?? '') !== 'administrator') {
    http_response_code(403);
    echo json_encode(['status'=>'error','message'=>'Access denied']);
    exit;
}

$user_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
$spec = trim($_POST['specialization'] ?? '');
if (!$user_id || !$spec) {
    http_response_code(400);
    echo json_encode(['status'=>'error','message'=>'user_id and specialization required']);
    exit;
}

// check if consultant exists
$q = $conn->prepare("SELECT id FROM consultants WHERE user_id = ? LIMIT 1");
$q->bind_param('i', $user_id);
$q->execute();
$res = $q->get_result();
if ($res && $res->num_rows) {
    $row = $res->fetch_assoc();
    $upd = $conn->prepare("UPDATE consultants SET specialization = ?, updated_at = NOW() WHERE id = ?");
    $upd->bind_param('si', $spec, $row['id']);
    if ($upd->execute()) echo json_encode(['status'=>'success']); else { http_response_code(500); echo json_encode(['status'=>'error','message'=>$upd->error]); }
} else {
    $ins = $conn->prepare("INSERT INTO consultants (user_id, specialization, status, created_at, updated_at) VALUES (?, ?, 'active', NOW(), NOW())");
    $ins->bind_param('is', $user_id, $spec);
    if ($ins->execute()) echo json_encode(['status'=>'success','id'=>$conn->insert_id]); else { http_response_code(500); echo json_encode(['status'=>'error','message'=>$ins->error]); }
}
