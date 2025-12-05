<?php
/**
 * Shared Helper Functions
 * Common utilities to reduce code duplication across the application
 */

/**
 * Check if user has admin role
 */
function isAdmin($role) {
    return in_array(strtolower(trim($role ?? '')), ['administrator', 'admin'], true);
}

/**
 * Check if user has specific role
 */
function hasRole($role, $targetRole) {
    return strtolower(trim($role ?? '')) === strtolower(trim($targetRole));
}

/**
 * Normalize role name (case-insensitive check)
 */
function normalizeRole($role) {
    $lower = strtolower(trim($role ?? ''));
    if ($lower === 'administrator' || $lower === 'admin') return 'administrator';
    if ($lower === 'consultant') return 'consultant';
    if ($lower === 'receptionist') return 'receptionist';
    return $lower;
}

/**
 * Get role badge HTML
 */
function getRoleBadge($role) {
    $role = strtolower(trim($role ?? ''));
    if (in_array($role, ['administrator', 'admin'], true)) {
        return '<span class="badge bg-danger">Admin</span>';
    } elseif ($role === 'consultant') {
        return '<span class="badge bg-info">Consultant</span>';
    } elseif ($role === 'receptionist') {
        return '<span class="badge bg-primary">Receptionist</span>';
    }
    return '<span class="badge bg-secondary">' . htmlspecialchars($role) . '</span>';
}

/**
 * Get status badge HTML
 */
function getStatusBadge($status) {
    return '<span class="badge bg-' . (($status === 'active') ? 'success' : 'secondary') . '">' . htmlspecialchars($status) . '</span>';
}

/**
 * Send JSON response
 */
function jsonResponse($status, $message, $data = null, $code = 200) {
    http_response_code($code);
    $response = ['status' => $status, 'message' => $message];
    if ($data !== null) {
        $response = array_merge($response, $data);
    }
    header('Content-Type: application/json');
    die(json_encode($response));
}

/**
 * Validate file upload
 */
function validateFileUpload($file, $maxSize = 2097152, $allowedMimes = ['image/jpeg', 'image/png', 'image/gif']) {
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
        return ['valid' => false, 'error' => 'File upload failed'];
    }
    if ($file['size'] > $maxSize) {
        return ['valid' => false, 'error' => 'File too large'];
    }
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    if (!in_array($mime, $allowedMimes, true)) {
        return ['valid' => false, 'error' => 'Invalid file type'];
    }
    return ['valid' => true, 'mime' => $mime, 'data' => file_get_contents($file['tmp_name'])];
}

/**
 * Hash password (deprecated - use PasswordUtils::hash instead)
 * @deprecated Use PasswordUtils::hash() instead
 */
function hashPassword($password) {
    if (class_exists('PasswordUtils')) {
        return PasswordUtils::hash($password);
    }
    return password_hash($password, PASSWORD_DEFAULT);
}

/**
 * Verify password (deprecated - use PasswordUtils::verify instead)
 * @deprecated Use PasswordUtils::verify() instead
 */
function verifyPassword($password, $hash) {
    if (class_exists('PasswordUtils')) {
        return PasswordUtils::verify($password, $hash);
    }
    return password_verify($password, $hash);
}
?>
