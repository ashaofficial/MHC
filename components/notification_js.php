<?php
/**
 * JavaScript Notification System Component
 * Include this file in HTML pages to get unified notification system
 * 
 * Usage:
 *   <?php include 'components/notification_js.php'; ?>
 *   Then in JS: showNotification('Message', true/false);
 */
?>
<script>
/**
 * Unified Notification System (JavaScript)
 * Modern toast notification for success/error messages
 */

// Notification configuration
const NOTIFICATION_CONFIG = {
    duration: 5000, // 5 seconds
    position: 'bottom-end', // bottom-right
    showCloseButton: true,
    animation: true
};

/**
 * Show notification toast
 * @param {string} message - Message to display
 * @param {boolean} success - true for success (green), false for error (red)
 * @param {object} options - Optional configuration
 */
function showNotification(message, success = true, options = {}) {
    const config = { ...NOTIFICATION_CONFIG, ...options };
    
    // Ensure notification container exists
    let container = document.getElementById('notification-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'notification-container';
        container.className = 'position-fixed p-3';
        container.style.cssText = 'z-index: 9999; bottom: 0; right: 0; max-width: 400px;';
        document.body.appendChild(container);
    }
    
    // Create toast element
    const toastId = 'toast-' + Date.now();
    const toastClass = success ? 'text-bg-success' : 'text-bg-danger';
    const icon = success ? '<i class="fas fa-check-circle"></i>' : '<i class="fas fa-exclamation-circle"></i>';
    
    const toastHTML = `
        <div id="${toastId}" class="toast align-items-center ${toastClass} border-0 shadow" 
             role="alert" aria-live="assertive" aria-atomic="true" style="min-width: 300px;">
            <div class="d-flex">
                <div class="toast-body d-flex align-items-center gap-2">
                    <span>${icon}</span>
                    <span>${escapeHtml(message)}</span>
                </div>
                ${config.showCloseButton ? `
                <button type="button" class="btn-close btn-close-white me-2 m-auto" 
                        data-bs-dismiss="toast" aria-label="Close"></button>
                ` : ''}
            </div>
        </div>
    `;
    
    // Insert toast
    container.insertAdjacentHTML('beforeend', toastHTML);
    const toastElement = document.getElementById(toastId);
    
    // Show toast using Bootstrap if available, else fallback
    if (typeof bootstrap !== 'undefined' && bootstrap.Toast) {
        const toast = new bootstrap.Toast(toastElement, {
            autohide: true,
            delay: config.duration
        });
        toast.show();
        
        // Remove element after hide
        toastElement.addEventListener('hidden.bs.toast', () => {
            toastElement.remove();
        });
    } else {
        // Fallback: Simple fade in/out
        toastElement.style.opacity = '0';
        toastElement.style.transition = 'opacity 0.3s';
        
        setTimeout(() => {
            toastElement.style.opacity = '1';
        }, 10);
        
        setTimeout(() => {
            toastElement.style.opacity = '0';
            setTimeout(() => toastElement.remove(), 300);
        }, config.duration);
    }
}

/**
 * Show success notification (convenience function)
 */
function showSuccess(message, options = {}) {
    showNotification(message, true, options);
}

/**
 * Show error notification (convenience function)
 * Uses error modal if available, otherwise falls back to toast notification
 */
function showError(message, options = {}) {
    // Prefer error modal if available
    if (typeof showErrorModal === 'function') {
        showErrorModal(message);
    } else {
        showNotification(message, false, options);
    }
}

/**
 * Escape HTML to prevent XSS
 */
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

/**
 * Show confirmation dialog before action
 * @param {string} message - Confirmation message
 * @param {function} onConfirm - Callback if confirmed
 * @param {function} onCancel - Optional callback if cancelled
 */
function showConfirm(message, onConfirm, onCancel = null) {
    if (confirm(message)) {
        if (typeof onConfirm === 'function') {
            onConfirm();
        }
    } else {
        if (typeof onCancel === 'function') {
            onCancel();
        }
    }
}

// Auto-show notification from PHP session if exists
document.addEventListener('DOMContentLoaded', function() {
    <?php
    // Check for notification in session
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (isset($_SESSION['notification'])) {
        $notification = $_SESSION['notification'];
        $type = $notification['type'] === 'success' ? 'true' : 'false';
        $message = json_encode($notification['message']);
        echo "showNotification({$message}, {$type});\n";
        unset($_SESSION['notification']);
    }
    ?>
});
</script>

<style>
/* Notification container styles */
#notification-container {
    pointer-events: none;
}

#notification-container .toast {
    pointer-events: auto;
    margin-bottom: 0.5rem;
}

/* Animation for toast appearance */
.toast {
    animation: slideInRight 0.3s ease-out;
}

@keyframes slideInRight {
    from {
        transform: translateX(100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}
</style>

