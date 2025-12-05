<?php
/**
 * Centralized Password Utility Functions
 * All password hashing and verification should use these functions
 * 
 * Usage:
 *   $hash = PasswordUtils::hash($password);
 *   $isValid = PasswordUtils::verify($password, $storedHash);
 *   $needsRehash = PasswordUtils::needsRehash($storedHash);
 */

class PasswordUtils {
    /**
     * Hash a password using bcrypt (PASSWORD_DEFAULT)
     * This ensures all passwords are hashed consistently across the application
     */
    public static function hash($password) {
        if (empty($password)) {
            throw new InvalidArgumentException('Password cannot be empty');
        }
        return password_hash($password, PASSWORD_DEFAULT);
    }
    
    /**
     * Verify a password against a stored hash
     * Also handles migration from plain text passwords (one-time upgrade)
     */
    public static function verify($password, $storedHash, $conn = null, $credentialId = null) {
        if (empty($password) || empty($storedHash)) {
            return false;
        }
        
        // Check if stored value looks like a bcrypt/argon hash
        if (preg_match('/^\$2[ayb]\$|^\$argon2/', $storedHash)) {
            return password_verify($password, $storedHash);
        }
        
        // Legacy: If stored value is plain text, hash it and update DB
        if ($storedHash === $password && $conn !== null && $credentialId !== null) {
            $newHash = self::hash($password);
            $upd = $conn->prepare("UPDATE credential SET password_hash = ?, updated_on = NOW() WHERE id = ?");
            if ($upd) {
                $upd->bind_param("si", $newHash, $credentialId);
                $upd->execute();
            }
            return password_verify($password, $newHash);
        }
        
        // If not bcrypt and doesn't match plain text, treat as invalid
        return false;
    }
    
    /**
     * Check if a hash needs to be rehashed (if algorithm/cost changed)
     */
    public static function needsRehash($hash) {
        return password_needs_rehash($hash, PASSWORD_DEFAULT);
    }
    
    /**
     * Validate password strength
     */
    public static function validate($password, $minLength = 6) {
        if (empty($password)) {
            return ['valid' => false, 'error' => 'Password cannot be empty'];
        }
        if (strlen($password) < $minLength) {
            return ['valid' => false, 'error' => "Password must be at least {$minLength} characters"];
        }
        return ['valid' => true];
    }
}
?>

