<?php
/**
 * save_profile.php
 * Update current user's profile fields (users table)
 * Does NOT allow changing role_id or status
 */

// Start output buffering to catch any unintended output
ob_start();

header('Content-Type: application/json');

include_once __DIR__ . '/../auth.php';
include_once __DIR__ . '/../secure/db.php';
include_once __DIR__ . '/../components/helpers.php';
include_once __DIR__ . '/../secure/config_messages.php';
include_once __DIR__ . '/../components/notification.php';

// Clear any output that was generated during includes
ob_end_clean();

$user_id = $USER['user_id'] ?? null;
if (!$user_id) {
    Notification::jsonResponse('error', getMessage('MSG_ERROR_UNAUTHORIZED'), null, 401);
}

$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$mobile = trim($_POST['mobile'] ?? '');
$doj = isset($_POST['doj']) ? trim($_POST['doj']) : null;
$dob = isset($_POST['dob']) ? trim($_POST['dob']) : null;
$description = trim($_POST['description'] ?? '');

if ($name === '') {
    Notification::jsonResponse('error', getMessage('MSG_ERROR_NAME_REQUIRED'), null, 400);
}

try {
    // handle optional photo upload
    $hasPhoto = isset($_FILES['photo']) && is_uploaded_file($_FILES['photo']['tmp_name']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK;
    if ($hasPhoto) {
        $validation = validateFileUpload($_FILES['photo'], 2097152, ['image/jpeg', 'image/png', 'image/gif']);
        if (!$validation['valid']) {
            Notification::jsonResponse('error', $validation['error'], null, 400);
        }
        $photoData = $validation['data'];

        $stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, mobile = ?, doj = ?, dob = ?, description = ?, photo = ?, updated_at = NOW() WHERE id = ?");
        if (!$stmt) throw new Exception('Prepare failed: ' . $conn->error);
        $stmt->bind_param('sssssssi', $name, $email, $mobile, $doj, $dob, $description, $photoData, $user_id);
    } else {
        $stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, mobile = ?, doj = ?, dob = ?, description = ?, updated_at = NOW() WHERE id = ?");
        if (!$stmt) throw new Exception('Prepare failed: ' . $conn->error);
        $stmt->bind_param('ssssssi', $name, $email, $mobile, $doj, $dob, $description, $user_id);
    }
    if (!$stmt->execute()) throw new Exception('Execute failed: ' . $stmt->error);

    Notification::jsonResponse('success', getMessage('MSG_PROFILE_UPDATED'));
} catch (Exception $e) {
    Notification::jsonResponse('error', $e->getMessage(), null, 500);
}