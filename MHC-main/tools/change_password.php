<?php
/**
 * Handle password change
 * Receives new password via POST and updates database with bcrypt hash
 */

// Start output buffering to catch any unintended output
ob_start();

header("Content-Type: application/json");

// Check authorization
include_once __DIR__ . '/../auth.php';
include_once __DIR__ . '/../secure/db.php';
include_once __DIR__ . '/../secure/password_utils.php';
include_once __DIR__ . '/../secure/config_messages.php';
include_once __DIR__ . '/../components/notification.php';

// Clear any output that was generated during includes
ob_end_clean();

$user_id = $USER['user_id'] ?? null;

if (!$user_id) {
    Notification::jsonResponse('error', getMessage('MSG_ERROR_UNAUTHORIZED'), null, 401);
}

$password = $_POST['password'] ?? '';

// Validate password
$validation = PasswordUtils::validate($password);
if (!$validation['valid']) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => $validation['error']]);
    exit;
}

try {
    // Hash password using centralized utility
    $password_hash = PasswordUtils::hash($password);
    
    // Update credential password
    $update_sql = "UPDATE credential SET password_hash = ?, updated_on = NOW() WHERE user_id = ?";
    $stmt = $conn->prepare($update_sql);
    
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param("si", $password_hash, $user_id);
    
    if (!$stmt->execute()) {
        throw new Exception("Update failed: " . $stmt->error);
    }
    
    Notification::jsonResponse('success', getMessage('MSG_PASSWORD_CHANGED'));
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Error changing password: " . $e->getMessage()
    ]);
}

exit;
?>
