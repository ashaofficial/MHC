<?php
// settings_action.php
header('Content-Type: application/json');
include_once __DIR__ . '/../auth.php';
include_once __DIR__ . '/../secure/db.php';
include_once __DIR__ . '/../secure/password_utils.php';
include_once __DIR__ . '/../secure/config_messages.php';
include_once __DIR__ . '/../components/helpers.php';
include_once __DIR__ . '/../components/notification.php';

// Allow common admin role name variants
if (!isAdmin($USER['role'] ?? '')) {
    Notification::jsonResponse('error', getMessage('MSG_ERROR_ACCESS_DENIED'), null, 403);
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {

    // -------- SAVE USER (create or update) --------
    case 'save_user':
        $id = isset($_POST['id']) && $_POST['id'] !== '' ? (int)$_POST['id'] : 0;
        $name = trim($_POST['name'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $role_id = isset($_POST['role_id']) && $_POST['role_id'] !== '' ? (int)$_POST['role_id'] : 0;
        $email = trim($_POST['email'] ?? '');
        $mobile = trim($_POST['mobile'] ?? '');
        $status = trim($_POST['status'] ?? 'active');
        $doj = trim($_POST['doj'] ?? null) ?: null;
        $dob = trim($_POST['dob'] ?? null) ?: null;
        $description = trim($_POST['description'] ?? '');
        $specialization = trim($_POST['specialization'] ?? '');

        if ($name === '' || $username === '') {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Name and username required']);
            exit;
        }

        $hasPhoto = isset($_FILES['photo']) && is_uploaded_file($_FILES['photo']['tmp_name']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK;
        if ($hasPhoto) {
            $validation = validateFileUpload($_FILES['photo'], 2 * 1024 * 1024, ['image/jpeg', 'image/png', 'image/gif']);
            if (!$validation['valid']) {
                http_response_code(400);
                echo json_encode(['status' => 'error', 'message' => $validation['error']]);
                exit;
            }
            $photoData = $validation['data'];
        }

        if ($id) {
            // update user
            if ($hasPhoto) {
                $ust = $conn->prepare("UPDATE users SET name = ?, role_id = ?, email = ?, mobile = ?, status = ?, doj = ?, dob = ?, description = ?, photo = ?, updated_at = NOW() WHERE id = ?");
                if ($ust === false) { http_response_code(500); echo json_encode(['status'=>'error','message'=>$conn->error]); exit; }
                $ust->bind_param('sisssssssi', $name, $role_id, $email, $mobile, $status, $doj, $dob, $description, $photoData, $id);
            } else {
                $ust = $conn->prepare("UPDATE users SET name = ?, role_id = ?, email = ?, mobile = ?, status = ?, doj = ?, dob = ?, description = ?, updated_at = NOW() WHERE id = ?");
                if ($ust === false) { http_response_code(500); echo json_encode(['status'=>'error','message'=>$conn->error]); exit; }
                $ust->bind_param('sissssssi', $name, $role_id, $email, $mobile, $status, $doj, $dob, $description, $id);
            }
            if (!$ust->execute()) {
                $errno = $conn->errno;
                $err = $ust->error;
                if ($errno === 1062) { http_response_code(400); echo json_encode(['status'=>'error','message'=>'Duplicate entry: ' . $err]); }
                else { http_response_code(500); echo json_encode(['status'=>'error','message'=>$err]); }
                exit;
            }

            // update password if provided
            if ($password !== '') {
                $hash = PasswordUtils::hash($password);
                $c = $conn->prepare("UPDATE credential SET password_hash = ?, updated_on = NOW() WHERE user_id = ?");
                if ($c) { $c->bind_param('si', $hash, $id); $c->execute(); }
            }

            // consultant row handling
            $roleName = null;
            $rr = $conn->prepare("SELECT role_name FROM roles WHERE id = ? LIMIT 1");
            if ($rr) { $rr->bind_param('i', $role_id); $rr->execute(); $rres = $rr->get_result(); if ($rres && $rres->num_rows) $roleName = $rres->fetch_assoc()['role_name']; }

            if (strtolower($roleName ?? '') === 'consultant' || $specialization !== '') {
                $qc = $conn->prepare("SELECT id FROM consultants WHERE user_id = ? LIMIT 1");
                if ($qc) {
                    $qc->bind_param('i', $id); $qc->execute(); $rc = $qc->get_result();
                    if ($rc && $rc->num_rows) {
                        $row = $rc->fetch_assoc();
                        $upd = $conn->prepare("UPDATE consultants SET specialization = ?, updated_at = NOW() WHERE id = ?");
                        if ($upd) { $upd->bind_param('si', $specialization, $row['id']); $upd->execute(); }
                    } elseif ($specialization !== '') {
                        $insc = $conn->prepare("INSERT INTO consultants (user_id, specialization, status, created_at, updated_at) VALUES (?, ?, 'active', NOW(), NOW())");
                        if ($insc) { $insc->bind_param('is', $id, $specialization); $insc->execute(); }
                    }
                }
            }

            Notification::jsonResponse('success', getMessage('MSG_USER_UPDATED'));
        } else {
            // create user
            if ($hasPhoto) {
                $ust = $conn->prepare("INSERT INTO users (name, role_id, email, mobile, status, doj, dob, description, photo, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
                if ($ust === false) { http_response_code(500); echo json_encode(['status'=>'error','message'=>$conn->error]); exit; }
                $ust->bind_param('sisssssss', $name, $role_id, $email, $mobile, $status, $doj, $dob, $description, $photoData);
            } else {
                $ust = $conn->prepare("INSERT INTO users (name, role_id, email, mobile, status, doj, dob, description, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
                if ($ust === false) { http_response_code(500); echo json_encode(['status'=>'error','message'=>$conn->error]); exit; }
                $ust->bind_param('sissssss', $name, $role_id, $email, $mobile, $status, $doj, $dob, $description);
            }
            if (!$ust->execute()) {
                $errno = $conn->errno;
                $err = $ust->error;
                if ($errno === 1062) { http_response_code(400); echo json_encode(['status'=>'error','message'=>'Duplicate entry: ' . $err]); }
                else { http_response_code(500); echo json_encode(['status'=>'error','message'=>$err]); }
                exit;
            }
            $newid = $conn->insert_id;

            // credential
            $finalPassword = $password ?: bin2hex(random_bytes(4));
            $hash = PasswordUtils::hash($finalPassword);
            $c = $conn->prepare("INSERT INTO credential (user_id, username, password_hash, updated_on) VALUES (?, ?, ?, NOW())");
            if ($c === false) {
                // rollback created user
                $conn->query("DELETE FROM users WHERE id = " . (int)$newid);
                Notification::jsonResponse('error', getMessage('MSG_ERROR_DATABASE') . ': ' . $conn->error, null, 500);
            }
            $c->bind_param('iss', $newid, $username, $hash);
            if (!$c->execute()) {
                $conn->query("DELETE FROM users WHERE id = " . (int)$newid);
                $errno = $conn->errno; $err = $c->error;
                if ($errno === 1062) {
                    Notification::jsonResponse('error', getMessage('MSG_ERROR_DUPLICATE_ENTRY') . ': ' . $err, null, 400);
                } else {
                    Notification::jsonResponse('error', getMessage('MSG_ERROR_DATABASE') . ': ' . $err, null, 500);
                }
            }

            // if role is consultant, insert specialization if provided
            $roleName = null;
            $rr = $conn->prepare("SELECT role_name FROM roles WHERE id = ? LIMIT 1");
            if ($rr) { $rr->bind_param('i', $role_id); $rr->execute(); $rres = $rr->get_result(); if ($rres && $rres->num_rows) $roleName = $rres->fetch_assoc()['role_name']; }

            if (strtolower($roleName ?? '') === 'consultant') {
                $spec = trim($specialization ?: 'General');
                $insc = $conn->prepare("INSERT INTO consultants (user_id, specialization, status, created_at, updated_at) VALUES (?, ?, 'active', NOW(), NOW())");
                if ($insc) { $insc->bind_param('is', $newid, $spec); $insc->execute(); }
            }

            Notification::jsonResponse('success', getMessage('MSG_USER_CREATED'), ['id' => $newid]);
        }
        break;

    // -------- SAVE ROLE (create or update) --------
    case 'save_role':
        $id = isset($_POST['id']) && $_POST['id'] !== '' ? (int)$_POST['id'] : 0;
        $name = trim($_POST['role_name'] ?? '');
        if (!$name) {
            Notification::jsonResponse('error', 'Role name required', null, 400);
        }
        if ($id) {
            $u = $conn->prepare("UPDATE roles SET role_name = ?, updated_at = NOW() WHERE id = ?");
            $u->bind_param('si', $name, $id);
            if ($u->execute()) {
                Notification::jsonResponse('success', getMessage('MSG_ROLE_UPDATED'));
            } else {
                Notification::jsonResponse('error', getMessage('MSG_ERROR_DATABASE') . ': ' . $u->error, null, 500);
            }
        } else {
            $ins = $conn->prepare("INSERT INTO roles (role_name, status, created_at, updated_at) VALUES (?, 'active', NOW(), NOW())");
            $ins->bind_param('s', $name);
            if ($ins->execute()) {
                Notification::jsonResponse('success', getMessage('MSG_ROLE_ADDED'), ['id' => $conn->insert_id]);
            } else {
                Notification::jsonResponse('error', getMessage('MSG_ERROR_DATABASE') . ': ' . $ins->error, null, 500);
            }
        }
        break;

    // -------- DELETE ROLE --------
    case 'delete_role':
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        if (!$id) { http_response_code(400); echo json_encode(['status'=>'error','message'=>'Role id required']); exit; }

        // Prevent deleting role if users exist with this role
        $check = $conn->prepare("SELECT COUNT(*) AS c FROM users WHERE role_id = ?");
        $check->bind_param('i', $id); $check->execute(); $cr = $check->get_result()->fetch_assoc();
        if ($cr['c'] > 0) {
            http_response_code(400);
            echo json_encode(['status'=>'error','message'=>'Cannot delete role assigned to users']);
            exit;
        }

        $del = $conn->prepare("DELETE FROM roles WHERE id = ?");
        $del->bind_param('i', $id);
        if ($del->execute()) {
            Notification::jsonResponse('success', getMessage('MSG_ROLE_DELETED'));
        } else {
            Notification::jsonResponse('error', getMessage('MSG_ERROR_DATABASE') . ': ' . $del->error, null, 500);
        }
        break;

    // -------- DELETE USER (robust) --------
    case 'delete_user':
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        if (!$id) { http_response_code(400); echo json_encode(['status'=>'error','message'=>'User id required']); exit; }

        // Prevent deleting yourself
        $currentId = (int)($USER['user_id'] ?? 0);
        if ($currentId === $id) { http_response_code(400); echo json_encode(['status'=>'error','message'=>'Cannot delete the currently logged-in user']); exit; }

        $conn->begin_transaction();
        try {
            // ensure user exists and get role
            $q = $conn->prepare("SELECT role_id FROM users WHERE id = ? LIMIT 1");
            if ($q === false) throw new Exception($conn->error);
            $q->bind_param('i', $id); $q->execute(); $res = $q->get_result();
            if (!$res || $res->num_rows === 0) throw new Exception('User not found');
            $row = $res->fetch_assoc(); $roleId = (int)$row['role_id'];

            if ($roleId) {
                $rq = $conn->prepare("SELECT role_name FROM roles WHERE id = ? LIMIT 1");
                if ($rq) { $rq->bind_param('i', $roleId); $rq->execute(); $rres = $rq->get_result(); if ($rres && $rres->num_rows) {
                    $roleName = strtolower($rres->fetch_assoc()['role_name']);
                    if (in_array($roleName, ['admin','administrator'])) {
                        $cq = $conn->prepare("SELECT COUNT(*) AS cnt FROM users u JOIN roles r ON u.role_id = r.id WHERE LOWER(r.role_name) IN ('admin','administrator')");
                        if ($cq === false) throw new Exception($conn->error);
                        $cq->execute(); $cc = $cq->get_result()->fetch_assoc(); $adminsCount = (int)($cc['cnt'] ?? 0);
                        if ($adminsCount <= 1) throw new Exception('Cannot delete this user — at least one administrator must remain.');
                    }
                } }
            }

            // delete related rows (consultants, credential, sessions) then user
            $d1 = $conn->prepare("DELETE FROM consultants WHERE user_id = ?"); if ($d1 === false) throw new Exception($conn->error); $d1->bind_param('i', $id); if ($d1->execute() === false) throw new Exception($d1->error);
            $d2 = $conn->prepare("DELETE FROM credential WHERE user_id = ?"); if ($d2 === false) throw new Exception($conn->error); $d2->bind_param('i', $id); if ($d2->execute() === false) throw new Exception($d2->error);
            $d3 = $conn->prepare("DELETE FROM user_session WHERE user_id = ?"); if ($d3 === false) throw new Exception($conn->error); $d3->bind_param('i', $id); if ($d3->execute() === false) throw new Exception($d3->error);
            $d4 = $conn->prepare("DELETE FROM users WHERE id = ?"); if ($d4 === false) throw new Exception($conn->error); $d4->bind_param('i', $id); if ($d4->execute() === false) throw new Exception($d4->error);

            $conn->commit();
            echo json_encode(['status'=>'success','message'=>'User deleted']); exit;
        } catch (Exception $ex) {
            $conn->rollback(); http_response_code(400); echo json_encode(['status'=>'error','message'=>$ex->getMessage()]); exit;
        }
        break;

    // -------- SAVE CONSULTANT (create/update) --------
    case 'save_consultant':
        $user_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
        $spec = trim($_POST['specialization'] ?? '');
        if (!$user_id || $spec === '') { http_response_code(400); echo json_encode(['status'=>'error','message'=>'user_id and specialization required']); exit; }
        $q = $conn->prepare("SELECT id FROM consultants WHERE user_id = ? LIMIT 1");
        if ($q === false) { http_response_code(500); echo json_encode(['status'=>'error','message'=>$conn->error]); exit; }
        $q->bind_param('i', $user_id); $q->execute(); $res = $q->get_result();
        if ($res && $res->num_rows) { $row = $res->fetch_assoc(); $upd = $conn->prepare("UPDATE consultants SET specialization = ?, updated_at = NOW() WHERE id = ?"); if ($upd) { $upd->bind_param('si', $spec, $row['id']); if ($upd->execute()) echo json_encode(['status'=>'success','message'=>'Consultant updated']); else { http_response_code(500); echo json_encode(['status'=>'error','message'=>$upd->error]); } } }
        else { $ins = $conn->prepare("INSERT INTO consultants (user_id, specialization, status, created_at, updated_at) VALUES (?, ?, 'active', NOW(), NOW())"); if ($ins) { $ins->bind_param('is', $user_id, $spec); if ($ins->execute()) echo json_encode(['status'=>'success','id'=>$conn->insert_id]); else { http_response_code(500); echo json_encode(['status'=>'error','message'=>$ins->error]); } } }
        exit;
        break;

    // -------- DELETE CONSULTANT --------
    case 'delete_consultant':
        $user_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
        if (!$user_id) { http_response_code(400); echo json_encode(['status'=>'error','message'=>'user_id required']); exit; }
        $del = $conn->prepare("DELETE FROM consultants WHERE user_id = ?"); if ($del === false) { http_response_code(500); echo json_encode(['status'=>'error','message'=>$conn->error]); exit; }
        $del->bind_param('i', $user_id);
        if ($del->execute()) echo json_encode(['status'=>'success','message'=>'Consultant deleted']); else { http_response_code(500); echo json_encode(['status'=>'error','message'=>$del->error]); }
        exit;
        break;

    default:
        http_response_code(400);
        echo json_encode(['status'=>'error','message'=>'Unknown action']);
        exit;
}
?>
