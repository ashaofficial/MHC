<?php
/**
 * Modern Bootstrap Confirmation Modal Component
 * Reusable confirmation dialog for user actions
 * 
 * Usage:
 *   <?php include '../components/confirmation-modal.php'; ?>
 *   Then in JS: showConfirmation('Message', onConfirm, onCancel);
 */
?>
<!-- Confirmation Modal -->
<div class="modal fade" id="confirmationModal" tabindex="-1" aria-labelledby="confirmationModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header border-0 pb-0">
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center py-4">
                <div class="mb-3">
                    <i class="fas fa-question-circle text-warning" style="font-size: 4rem;"></i>
                </div>
                <h5 class="modal-title mb-3" id="confirmationModalLabel">Confirm Action</h5>
                <p class="text-muted mb-0" id="confirmationModalMessage">Are you sure you want to proceed?</p>
            </div>
            <div class="modal-footer border-0 justify-content-center pt-0">
                <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal" id="confirmationModalCancel">Cancel</button>
                <button type="button" class="btn btn-primary px-4" id="confirmationModalConfirm">Confirm</button>
            </div>
        </div>
    </div>
</div>

<script>
/**
 * Show modern Bootstrap confirmation modal
 * @param {string} message - Confirmation message to display
 * @param {function} onConfirm - Callback function when confirmed
 * @param {function} onCancel - Optional callback function when cancelled
 * @param {string} title - Optional title (default: "Confirm Action")
 */
function showConfirmation(message, onConfirm, onCancel = null, title = 'Confirm Action') {
    const modalEl = document.getElementById('confirmationModal');
    const modalTitle = document.getElementById('confirmationModalLabel');
    const modalMessage = document.getElementById('confirmationModalMessage');
    const confirmBtn = document.getElementById('confirmationModalConfirm');
    const cancelBtn = document.getElementById('confirmationModalCancel');
    
    // Set content
    modalTitle.textContent = title;
    modalMessage.textContent = message;
    
    // Create modal instance if needed
    let modal = bootstrap.Modal.getInstance(modalEl);
    if (!modal) {
        modal = new bootstrap.Modal(modalEl);
    }
    
    // Remove previous event listeners by cloning
    const newConfirmBtn = confirmBtn.cloneNode(true);
    confirmBtn.parentNode.replaceChild(newConfirmBtn, confirmBtn);
    const newCancelBtn = cancelBtn.cloneNode(true);
    cancelBtn.parentNode.replaceChild(newCancelBtn, cancelBtn);
    
    // Add new event listeners
    const newConfirm = document.getElementById('confirmationModalConfirm');
    const newCancel = document.getElementById('confirmationModalCancel');
    
    newConfirm.addEventListener('click', () => {
        modal.hide();
        if (typeof onConfirm === 'function') {
            onConfirm();
        }
    });
    
    newCancel.addEventListener('click', () => {
        modal.hide();
        if (typeof onCancel === 'function') {
            onCancel();
        }
    });
    
    // Also handle backdrop click and ESC key
    modalEl.addEventListener('hidden.bs.modal', function handler() {
        modalEl.removeEventListener('hidden.bs.modal', handler);
        if (typeof onCancel === 'function') {
            onCancel();
        }
    }, { once: true });
    
    // Show modal
    modal.show();
}

// Alias for compatibility
function showConfirm(message, onConfirm, onCancel = null, title = 'Confirm Action') {
    showConfirmation(message, onConfirm, onCancel, title);
}
</script>

<style>
#confirmationModal .modal-content {
    border-radius: 12px;
}

#confirmationModal .modal-header {
    padding-top: 1.5rem;
    padding-left: 1.5rem;
    padding-right: 1.5rem;
}

#confirmationModal .modal-body {
    padding-left: 2rem;
    padding-right: 2rem;
}

#confirmationModal .modal-footer {
    padding-bottom: 1.5rem;
    padding-left: 2rem;
    padding-right: 2rem;
}

#confirmationModal .btn {
    min-width: 100px;
    font-weight: 500;
}
</style>

