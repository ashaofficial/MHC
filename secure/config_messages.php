<?php
/**
 * Centralized Message Configuration
 * All system messages, notifications, and alerts are defined here
 */

// Success messages
define('MSG_USER_CREATED', 'User created successfully');
define('MSG_USER_UPDATED', 'User updated successfully');
define('MSG_PASSWORD_CHANGED', 'Password changed successfully');
define('MSG_PROFILE_UPDATED', 'Profile updated successfully');
define('MSG_PATIENT_ADDED', 'Patient added successfully');
define('MSG_PATIENT_UPDATED', 'Patient updated successfully');
define('MSG_MEDICAL_INFO_SAVED', 'Medical information saved successfully');
define('MSG_ROLE_ADDED', 'Role added successfully');
define('MSG_ROLE_UPDATED', 'Role updated successfully');
define('MSG_CONSULTANT_SAVED', 'Consultant saved successfully');

// Error messages
define('MSG_ERROR_ACCESS_DENIED', 'Access denied');
define('MSG_ERROR_UNAUTHORIZED', 'Unauthorized access');
define('MSG_ERROR_INVALID_LOGIN', 'Invalid username or password');
define('MSG_ERROR_NAME_REQUIRED', 'Name is required');
define('MSG_ERROR_USERNAME_REQUIRED', 'Username is required');
define('MSG_ERROR_PASSWORD_REQUIRED', 'Password is required');
define('MSG_ERROR_PASSWORD_TOO_SHORT', 'Password must be at least 6 characters');
define('MSG_ERROR_PASSWORDS_MISMATCH', 'Passwords do not match');
define('MSG_ERROR_DATABASE', 'Database error occurred');
define('MSG_ERROR_DUPLICATE_ENTRY', 'Duplicate entry: this record already exists');
define('MSG_ERROR_USER_NOT_FOUND', 'User not found');
define('MSG_ERROR_PATIENT_NOT_FOUND', 'Patient not found');
define('MSG_ERROR_ROLE_IN_USE', 'Cannot delete role assigned to users');
define('MSG_ERROR_CANNOT_DELETE_SELF', 'Cannot delete the currently logged-in user');
define('MSG_ERROR_REQUIRED_FIELDS', 'Please fill in all required fields');

// Confirmation messages (before action)
define('MSG_CONFIRM_SAVE', 'Save changes?');
define('MSG_CONFIRM_DELETE', 'Are you sure you want to delete this?');
define('MSG_CONFIRM_PASSWORD_CHANGE', 'Are you sure you want to change your password?');
define('MSG_CONFIRM_LOGOUT', 'Are you sure you want to logout?');

// Info messages
define('MSG_INFO_LOADING', 'Loading...');
define('MSG_INFO_NO_DATA', 'No data available');
define('MSG_INFO_NO_PATIENTS', 'No patients found');

/**
 * Get message by key
 */
function getMessage($key, $default = '') {
    if (defined($key)) {
        return constant($key);
    }
    return $default ?: $key;
}
?>

