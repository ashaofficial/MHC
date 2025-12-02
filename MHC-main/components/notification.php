<?php
/**
 * Unified Notification System
 * Provides consistent success/error message handling for both PHP and JavaScript
 * 
 * Usage in PHP:
 *   Notification::showSuccess('User created successfully');
 *   Notification::showError('Something went wrong');
 * 
 * Usage in JavaScript:
 *   showNotification('User created successfully', true);
 *   showNotification('Something went wrong', false);
 */

class Notification {
    /**
     * Show success notification (PHP side - adds to session)
     */
    public static function showSuccess($message) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['notification'] = [
            'type' => 'success',
            'message' => $message
        ];
    }
    
    /**
     * Show error notification (PHP side - adds to session)
     */
    public static function showError($message) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['notification'] = [
            'type' => 'error',
            'message' => $message
        ];
    }
    
    /**
     * Get and clear notification from session (call in view)
     */
    public static function getAndClear() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $notification = $_SESSION['notification'] ?? null;
        unset($_SESSION['notification']);
        return $notification;
    }
    
    /**
     * Send JSON response with notification data
     */
    public static function jsonResponse($status, $message, $data = null, $code = 200) {
        http_response_code($code);
        header('Content-Type: application/json');
        $response = [
            'status' => $status,
            'message' => $message
        ];
        if ($data !== null) {
            $response = array_merge($response, $data);
        }
        die(json_encode($response));
    }
}
?>

