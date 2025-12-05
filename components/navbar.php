<?php
/**
 * Dashboard Navbar Component
 * Displays header with profile dropdown and logout button
 * 
 * Usage: <?php include 'components/navbar.php'; $pageTitle = "My Page"; ?>
 *        <?php include 'components/navbar.php'; ?>
 */

// Get role and name from $USER (set by auth.php)
$userName = htmlspecialchars($USER['name'] ?? $USER['username'] ?? 'User');
$userRole = ucfirst(strtolower($USER['role'] ?? 'user'));
$userUsername = $USER['username'] ?? 'User';
$userInitial = strtoupper(mb_substr($userUsername, 0, 1)) ?? 'U';
$profilePhotoSrc = '';
$hasPhoto = false;

// Check if user has a photo in the photo field (LONGBLOB)
if (!empty($USER['photo'])) {
    // Safely encode the binary photo data
    $photoData = $USER['photo'];
    if (is_string($photoData) && strlen($photoData) > 0) {
        $profilePhotoSrc = 'data:image/jpeg;base64,' . base64_encode($photoData);
        $hasPhoto = true;
    }
}
$pageTitle = $pageTitle ?? 'Dashboard';

// Try to extract access token expiry to show client-side countdown
$session_expiry_ts = null;
if (!empty($_COOKIE['access_token'])) {
    include_once __DIR__ . '/../secure/jwt.php';
    $payload = verifyJWT($_COOKIE['access_token']);
    if ($payload && !empty($payload['exp'])) {
        $session_expiry_ts = (int)$payload['exp'];
    }
}

// Ensure modal/dialog helpers are available for modern prompts
include_once __DIR__ . '/modal-dialogs.php';
?>

<!-- Navbar Header -->
<nav class="navbar">
    <h1 class="navbar-title"><?php echo $pageTitle; ?></h1>
    
    <div class="navbar-right">
        <div id="session-timer" class="session-timer" style="margin-right:12px;display:flex;align-items:center;">
            <span style="font-size:0.9em;color:#555;margin-right:6px;">Session expires in</span>
            <span id="session-countdown">--:--:--</span>
        </div>
        <div class="profile-dropdown">
            <!-- Profile icon and name -->
            <div class="profile-menu" onclick="toggleProfileDropdown(event)">
                <?php if ($hasPhoto && $profilePhotoSrc): ?>
                    <div class="profile-icon has-photo" style="background-image:url('<?php echo $profilePhotoSrc; ?>');background-size:cover;background-position:center;"></div>
                <?php else: ?>
                    <div class="profile-icon profile-icon--initial" aria-hidden="true">
                        <?php echo htmlspecialchars($userInitial); ?>
                    </div>
                <?php endif; ?>
                <div>
                    <div class="profile-name"><?php echo $userName; ?></div>
                    <div class="profile-role"><?php echo $userRole; ?></div>
                </div>
            </div>
            
            <!-- Dropdown menu -->
            <div class="dropdown-content" id="profileDropdown">
                <a href="#" onclick="openProfileModal(); return false;">
                    <i class="fas fa-user-circle"></i> My Profile
                </a>
                <a href="#" onclick="openPasswordModal(); return false;">
                    <i class="fas fa-lock"></i> Change Password
                </a>
                <button onclick="logout();">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </button>
            </div>
        </div>
    </div>
</nav>

<script>
/**
 * Toggle profile dropdown visibility
 */
function toggleProfileDropdown(event) {
    event.stopPropagation();
    const dropdown = document.getElementById('profileDropdown');
    if (dropdown) {
        dropdown.classList.toggle('show');
    }
}

/**
 * Close dropdown when clicking outside
 */
document.addEventListener('click', (e) => {
    const dropdown = document.getElementById('profileDropdown');
    const menu = document.querySelector('.profile-menu');
    if (dropdown && menu && !menu.contains(e.target)) {
        dropdown.classList.remove('show');
    }
});

/**
 * Logout handler
 */
function logout() {
    const performLogout = () => {
        fetch("/auth/logout.php", {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' }
        })
        .then(resp => {
            // Close any open modals
            document.querySelectorAll('.modal.show').forEach(modal => {
                const bsModal = bootstrap.Modal.getInstance(modal);
                if (bsModal) bsModal.hide();
            });
            // Redirect to login page
            window.location = "/auth/login.html";
        })
        .catch(err => {
            console.error('Logout error:', err);
            // Force redirect even if logout fails
            window.location = "/auth/login.html";
        });
    };

    if (typeof showConfirmModal === 'function') {
        showConfirmModal('Are you sure you want to logout?', performLogout);
    } else if (confirm('Are you sure you want to logout?')) {
        performLogout();
    }
}
</script>

<script>
// Session countdown and refresh logic
(function(){
    const serverExpiry = <?php echo ($session_expiry_ts !== null) ? (int)$session_expiry_ts : 'null'; ?>;
    if (!serverExpiry) return; // no token info available

    const countdownEl = document.getElementById('session-countdown');
    let expiryTs = serverExpiry; // seconds since epoch
    let promptedForExtend = false;

    function formatTime(seconds) {
        if (seconds <= 0) return '00:00:00';
        const hrs = Math.floor(seconds / 3600);
        const mins = Math.floor((seconds % 3600) / 60);
        const secs = seconds % 60;
        return [hrs, mins, secs].map(n => String(n).padStart(2,'0')).join(':');
    }

    function updateCountdown() {
        const now = Math.floor(Date.now() / 1000);
        const remaining = expiryTs - now;
        if (countdownEl) countdownEl.textContent = formatTime(remaining);

        // If remaining <= 10 minutes and we haven't asked yet, prompt to extend
        if (remaining <= 10 * 60 && remaining > 0 && !promptedForExtend) {
            promptedForExtend = true;
            // Use modern Bootstrap confirmation modal if available
            const askToExtend = () => {
                const msg = 'Your session will expire in ' + formatTime(remaining) + '. Extend session?';
                const onConfirm = () => {
                    // call extendSession and show feedback
                    extendSession().then(() => {
                        if (typeof showSuccess === 'function') {
                            showSuccess('Session extended');
                        }
                    }).catch(() => {
                        if (typeof showError === 'function') {
                            showError('Unable to extend session. You will be logged out.');
                        }
                    });
                };
                const onCancel = () => {
                    // user cancelled or didn't respond - logout directly
                    if (typeof showNotification === 'function') {
                        showNotification('You have been logged out.', false, { duration: 3000 });
                    }
                    clearInterval(timerInterval);
                    logout();
                };

                if (typeof showConfirmModal === 'function') {
                    showConfirmModal(msg, onConfirm, onCancel, 'Extend Session?');
                } else {
                    if (confirm(msg)) {
                        onConfirm();
                    } else {
                        onCancel();
                    }
                }
            };
            // small delay so UI updates before modal
            setTimeout(askToExtend, 200);
        }

        // If expired, auto logout
        if (remaining <= 0) {
            // clear interval and logout
            clearInterval(timerInterval);
            try { logout(); } catch(e) { window.location = '/auth/login.html'; }
        }
    }

    // Call refresh endpoint to exchange refresh token for new tokens
    async function extendSession() {
        const controller = new AbortController();
        const timeout = setTimeout(() => controller.abort(), 10000);
        try {
            const resp = await fetch('/auth/refresh_token.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json' },
                signal: controller.signal
            });
            clearTimeout(timeout);
            if (!resp.ok) {
                // server rejected refresh or session invalid
                if (typeof showError === 'function') showError('Session refresh failed');
                logout();
                return;
            }
            const data = await resp.json();
            if (data && data.status === 'success' && data.exp) {
                expiryTs = parseInt(data.exp, 10);
                promptedForExtend = false; // reset prompt state
                if (countdownEl) countdownEl.textContent = formatTime(expiryTs - Math.floor(Date.now()/1000));
                // show success toast
                if (typeof showSuccess === 'function') showSuccess('Session extended');
            } else {
                if (typeof showError === 'function') showError('Session refresh failed');
                logout();
            }
        } catch (err) {
            // no response -> auto logout
            if (typeof showError === 'function') showError('No response from server. Logging out.');
            logout();
        }
    }

    // Start interval
    const timerInterval = setInterval(updateCountdown, 1000);
    // immediate first update
    updateCountdown();
})();
</script>
