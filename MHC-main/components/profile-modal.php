<?php
/**
 * Profile Modal Component
 * Includes profile edit modal and password change modal (rendered as static HTML)
 * 
 * Usage: <?php include 'components/profile-modal.php'; ?>
 * Note: Include confirmation-modal.php and success-modal.php for modern modals
 */

// Determine if current user is admin
$isAdmin = in_array(strtolower(trim($USER['role'] ?? '')), ['administrator','admin'], true);
?>

<!-- Modern Modal Dialogs Component -->
<?php 
if (!defined('MODAL_DIALOGS_INCLUDED')) {
    define('MODAL_DIALOGS_INCLUDED', true);
    include __DIR__ . '/modal-dialogs.php';
}
?>

<!-- Profile Edit Modal (pre-populated with current user data) -->
<div class="modal fade" id="profileModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-user-circle"></i> My Profile</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="profileForm" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label class="form-label">Full Name</label>
                        <input name="name" class="form-control" value="<?php echo htmlspecialchars($USER['name'] ?? $USER['username'] ?? ''); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input name="email" type="email" class="form-control" value="<?php echo htmlspecialchars($USER['email'] ?? ''); ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Mobile</label>
                        <input name="mobile" class="form-control" value="<?php echo htmlspecialchars($USER['mobile'] ?? ''); ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Date of Joining</label>
                        <input name="doj" type="date" class="form-control" value="<?php echo htmlspecialchars($USER['doj'] ?? ''); ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Date of Birth</label>
                        <input name="dob" type="date" class="form-control" value="<?php echo htmlspecialchars($USER['dob'] ?? ''); ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control"><?php echo htmlspecialchars($USER['description'] ?? ''); ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Photo</label>
                        <div class="mb-2">
                            <?php if (!empty($USER['photo'])): ?>
                                <?php $b64 = base64_encode($USER['photo']); ?>
                                <img id="profilePhotoPreview" src="data:image/*;base64,<?php echo $b64; ?>" alt="photo" style="max-width:120px;max-height:120px;display:block;margin-bottom:8px;border-radius:6px;" />
                            <?php else: ?>
                                <img id="profilePhotoPreview" src="https://via.placeholder.com/120?text=No+Photo" alt="photo" style="max-width:120px;max-height:120px;display:block;margin-bottom:8px;border-radius:6px;" />
                            <?php endif; ?>
                        </div>
                        <input name="photo" id="photoInput" type="file" accept="image/*" class="form-control">
                        <small class="form-text text-muted">Upload a new photo to replace current (optional)</small>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button id="saveProfileBtn" type="button" class="btn btn-primary"><i class="fas fa-save"></i> Save</button>
                <?php if ($isAdmin): ?>
                    <button id="openPasswordFromProfile" type="button" class="btn btn-warning"><i class="fas fa-lock"></i> Change Password</button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Password Change Modal -->
<div class="modal fade" id="passwordModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-lock"></i> Change Password</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="passwordForm">
                    <div class="mb-3">
                        <label class="form-label">New Password</label>
                        <input id="newPassword" name="password" type="password" class="form-control" placeholder="Enter new password" required>
                        <small class="form-text text-muted">Minimum 6 characters</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Confirm Password</label>
                        <input id="confirmPassword" name="password_confirm" type="password" class="form-control" placeholder="Re-enter password" required>
                    </div>
                    <div id="passwordError" class="alert alert-danger d-none" role="alert"></div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button id="savePasswordBtn" type="button" class="btn btn-primary"><i class="fas fa-save"></i> Change Password</button>
            </div>
        </div>
    </div>
</div>

<script>
/**
 * Open Profile Modal
 */
function openProfileModal() {
    const modal = new bootstrap.Modal(document.getElementById('profileModal'));
    modal.show();
}

/**
 * Open Password Change Modal
 */
function openPasswordModal() {
    const modal = new bootstrap.Modal(document.getElementById('passwordModal'));
    modal.show();
}

/**
 * Initialize profile form handlers
 */
document.addEventListener('DOMContentLoaded', () => {
    // Save profile form
    const saveProfileBtn = document.getElementById('saveProfileBtn');
    if (saveProfileBtn) {
        saveProfileBtn.addEventListener('click', async () => {
            const form = document.getElementById('profileForm');
            const fd = new FormData(form);
            
            // Confirmation before saving
            if (typeof showConfirmModal === 'function') {
                showConfirmModal('Are you sure you want to save your profile changes?', async () => {
                    await saveProfileData(fd);
                });
                return;
            }
            
            // Fallback to old confirm
            if (!confirm('Save profile changes?')) return;
            
            await saveProfileData(fd);
        });
    }
    
    // Extract profile save logic
    async function saveProfileData(fd) {
        try {
            const res = await fetch('../tools/save_profile.php', {
                method: 'POST',
                body: fd,
                credentials: 'include'
            });
            
            const text = await res.text();
            let j;
            try {
                j = JSON.parse(text);
            } catch (parseErr) {
                console.error('Failed to parse JSON:', text);
                throw new Error('Invalid response from server. Please check console for details.');
            }
            
            if (j.status === 'success') {
                const modal = bootstrap.Modal.getInstance(document.getElementById('profileModal'));
                if (modal) modal.hide();
                
                // Show success modal
                if (typeof showSuccessModal === 'function') {
                    showSuccessModal(j.message || 'Profile updated successfully');
                } else if (typeof showNotification === 'function') {
                    showNotification(j.message || 'Profile updated successfully', true);
                } else {
                    alert(j.message || 'Profile updated successfully');
                }
                
                // Refresh tables depending on where we are:
                // - Admin dashboard: refresh all panes (users/roles/consultants)
                // - Settings page: at least refresh users (and consultants if available)
                if (typeof refreshAllPanesAdmin === 'function') {
                    refreshAllPanesAdmin();
                } else {
                    if (typeof refreshUsersPaneAdmin === 'function') {
                        refreshUsersPaneAdmin();
                    }
                    if (typeof loadUsersPane === 'function') {
                        loadUsersPane();
                    }
                    if (typeof loadConsultantsPane === 'function') {
                        loadConsultantsPane();
                    }
                }
            } else {
                if (typeof showErrorModal === 'function') {
                    showErrorModal(j.message || 'Error updating profile');
                } else if (typeof showNotification === 'function') {
                    showNotification(j.message || 'Error updating profile', false);
                } else {
                    alert(j.message || 'Error updating profile');
                }
            }
        } catch (err) {
            if (typeof showErrorModal === 'function') {
                showErrorModal('Error: ' + err.message);
            } else if (typeof showError === 'function') {
                showError('Error: ' + err.message);
            } else {
                alert('Error: ' + err.message);
            }
        }
    }

    // Save password form
    const savePasswordBtn = document.getElementById('savePasswordBtn');
    if (savePasswordBtn) {
        savePasswordBtn.addEventListener('click', async () => {
            const newPassword = document.getElementById('newPassword').value.trim();
            const confirmPassword = document.getElementById('confirmPassword').value.trim();
            const errorDiv = document.getElementById('passwordError');
            
            // Clear previous error
            errorDiv.classList.add('d-none');
            
            // Validate passwords match
            if (newPassword !== confirmPassword) {
                errorDiv.textContent = 'Passwords do not match!';
                errorDiv.classList.remove('d-none');
                return;
            }
            
            // Validate minimum length
            if (newPassword.length < 6) {
                errorDiv.textContent = 'Password must be at least 6 characters!';
                errorDiv.classList.remove('d-none');
                return;
            }
            
            // Confirmation before changing password
            if (typeof showConfirmModal === 'function') {
                showConfirmModal('Are you sure you want to change your password?', async () => {
                    await changePasswordData(newPassword, errorDiv);
                });
                return;
            }
            
            // Fallback to old confirm
            if (!confirm('Are you sure you want to change your password?')) return;
            
            await changePasswordData(newPassword, errorDiv);
        });
    }
    
    // Extract password change logic
    async function changePasswordData(newPassword, errorDiv) {
        const fd = new FormData();
        fd.append('password', newPassword);
        
        try {
                const res = await fetch('../tools/change_password.php', {
                    method: 'POST',
                    body: fd,
                    credentials: 'include'
                });
                
                const text = await res.text();
                let j;
                try {
                    j = JSON.parse(text);
                } catch (parseErr) {
                    console.error('Failed to parse JSON:', text);
                    throw new Error('Invalid response from server. Please check console for details.');
                }
                
            if (j.status === 'success') {
                const modal = bootstrap.Modal.getInstance(document.getElementById('passwordModal'));
                if (modal) modal.hide();
                document.getElementById('passwordForm').reset();
                
                // Show success modal
                if (typeof showSuccessModal === 'function') {
                    showSuccessModal(j.message || 'Password changed successfully');
                } else if (typeof showNotification === 'function') {
                    showNotification(j.message || 'Password changed successfully', true);
                } else {
                    alert(j.message || 'Password changed successfully');
                }
            } else {
                if (typeof showErrorModal === 'function') {
                    showErrorModal(j.message || 'Error changing password');
                } else if (typeof showNotification === 'function') {
                    showNotification(j.message || 'Error changing password', false);
                } else {
                    alert(j.message || 'Error changing password');
                }
            }
        } catch (err) {
            if (typeof showErrorModal === 'function') {
                showErrorModal('Error: ' + err.message);
            } else if (typeof showError === 'function') {
                showError('Error: ' + err.message);
            } else {
                alert('Error: ' + err.message);
            }
        }
    }

    // Open password modal from profile modal (admin only)
    const openPasswordBtn = document.getElementById('openPasswordFromProfile');
    if (openPasswordBtn) {
        openPasswordBtn.addEventListener('click', () => {
            const profileModal = bootstrap.Modal.getInstance(document.getElementById('profileModal'));
            if (profileModal) profileModal.hide();
            openPasswordModal();
        });
    }
});
</script>
