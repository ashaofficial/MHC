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

/**
 * Convert amount to words (Indian English)
 */
function amountInWords($amount) {
    $amount = (float)$amount;
    $rupees = (int)floor($amount);
    $paise = (int)round(($amount - $rupees) * 100);

    if ($rupees === 0 && $paise === 0) return 'ZERO ONLY';

    $words = strtoupper(numberToWords($rupees));
    $resultParts = [];
    if ($words !== '') $resultParts[] = $words . ' RUPEE' . ($rupees !== 1 ? 'S' : '');
    if ($paise > 0) {
        $pwords = strtoupper(numberToWords($paise));
        $resultParts[] = $pwords . ' PAISE';
    }

    $result = implode(' AND ', $resultParts) . ' ONLY';
    return $result;
}
/**
 * Convert integer number (0..999999999) to words using Indian grouping (crore, lakh, thousand)
 */
function numberToWords($num) {
    $num = (int)$num;
    if ($num === 0) return '';

    $units = [0=>'',1=>'one',2=>'two',3=>'three',4=>'four',5=>'five',6=>'six',7=>'seven',8=>'eight',9=>'nine',10=>'ten',11=>'eleven',12=>'twelve',13=>'thirteen',14=>'fourteen',15=>'fifteen',16=>'sixteen',17=>'seventeen',18=>'eighteen',19=>'nineteen'];
    $tens = [0=>'','',2=>'twenty',3=>'thirty',4=>'forty',5=>'fifty',6=>'sixty',7=>'seventy',8=>'eighty',9=>'ninety'];

    $parts = [];

    $crore = intdiv($num, 10000000);
    if ($crore > 0) {
        $parts[] = numberToWords($crore) . ' crore';
        $num = $num % 10000000;
    }
    $lakh = intdiv($num, 100000);
    if ($lakh > 0) {
        $parts[] = numberToWords($lakh) . ' lakh';
        $num = $num % 100000;
    }
    $thousand = intdiv($num, 1000);
    if ($thousand > 0) {
        $parts[] = numberToWords($thousand) . ' thousand';
        $num = $num % 1000;
    }
    $hundred = intdiv($num, 100);
    if ($hundred > 0) {
        $parts[] = numberToWords($hundred) . ' hundred';
        $num = $num % 100;
    }
    if ($num > 0) {
        if (!empty($parts)) $parts[] = 'and ' . (($num < 20) ? $units[$num] : ($tens[intdiv($num,10)] . ($num % 10 ? ' ' . $units[$num % 10] : '')));
        else $parts[] = ($num < 20) ? $units[$num] : ($tens[intdiv($num,10)] . ($num % 10 ? ' ' . $units[$num % 10] : ''));
    }

    return implode(' ', array_filter($parts));
}

/**
 * Extract clean notes by removing __ITEMS__: JSON prefix
 * Used to hide internal billing item JSON from user-facing notes field
 * The __ITEMS__: data is preserved in the DB but not shown in the form textarea
 */
function getCleanNotes($notesText) {
    if (empty($notesText)) return '';
    // Remove __ITEMS__: prefix and the JSON that follows it, including the trailing newline
    return preg_replace('/^__ITEMS__:[^\n]*\n?/', '', $notesText);
}
?>

