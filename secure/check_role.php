<?php
function requireRole($allowedRoles) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (!isset($_SESSION['role_name']) || !in_array($_SESSION['role_name'], (array)$allowedRoles)) {
        http_response_code(403);
        include '403.php';
        exit();
    }
}
