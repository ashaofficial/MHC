<?php
/**
 * Modern Bootstrap Modal Dialogs Component
 * Provides confirmation and success message modals
 * 
 * Usage:
 *   <?php include 'components/modal-dialogs.php'; ?>
 *   Then in JS: showConfirmModal('message', onConfirm, onCancel)
 *               showSuccessModal('message', autoCloseDelay)
 */
?>
<!-- Confirmation Modal -->
<div class="modal fade" id="confirmModal" tabindex="-1" aria-labelledby="confirmModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header border-0 pb-0">
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center py-4">
                <div class="mb-3">
                    <i class="fas fa-question-circle text-warning" style="font-size: 3rem;"></i>
                </div>
                <h5 class="modal-title mb-3" id="confirmModalLabel">Confirm Action</h5>
                <p class="text-muted mb-0" id="confirmModalMessage">Are you sure you want to proceed?</p>
            </div>
            <div class="modal-footer border-0 justify-content-center pt-0">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" id="confirmModalCancel">
                    <i class="fas fa-times me-2"></i>Cancel
                </button>
                <button type="button" class="btn btn-primary" id="confirmModalConfirm">
                    <i class="fas fa-check me-2"></i>Confirm
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Success Modal -->
<div class="modal fade" id="successModal" tabindex="-1" aria-labelledby="successModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header border-0 pb-0">
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center py-5">
                <div class="mb-3">
                    <i class="fas fa-check-circle text-success" style="font-size: 3rem;"></i>
                </div>
                <h6 class="modal-title mb-2" id="successModalLabel">Success!</h6>
                <p class="text-muted mb-0 small" id="successModalMessage">Operation completed successfully</p>
            </div>
            <div class="modal-footer border-0 justify-content-center pt-0">
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">OK</button>
            </div>
        </div>
    </div>
</div>

<!-- Error Modal -->
<div class="modal fade" id="errorModal" tabindex="-1" aria-labelledby="errorModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header border-0 pb-0">
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center py-4">
                <div class="mb-3">
                    <i class="fas fa-exclamation-circle text-danger" style="font-size: 3rem;"></i>
                </div>
                <h5 class="modal-title mb-3" id="errorModalLabel">Error</h5>
                <p class="text-muted mb-0" id="errorModalMessage">An error occurred</p>
            </div>
            <div class="modal-footer border-0 justify-content-center pt-0">
                <button type="button" class="btn btn-danger" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i>Close
                </button>
            </div>
        </div>
    </div>
</div>

<script>
/**
 * Show error modal
 * @param {string} message - Error message
 * @param {string} title - Optional title for the modal
 */
function showErrorModal(message, title = 'Error') {
    const modalEl = document.getElementById('errorModal');
    const titleEl = document.getElementById('errorModalLabel');
    const messageEl = document.getElementById('errorModalMessage');
    
    if (!modalEl) {
        console.error('Error modal not found. Falling back to alert.');
        alert(title + ': ' + message);
        return;
    }
    
    // Set title and message
    titleEl.textContent = title;
    messageEl.textContent = message || 'An error occurred';
    
    // Show modal
    const modal = new bootstrap.Modal(modalEl);
    modal.show();

    // Ensure modal appears above any other open modals by nudging z-index of modal and its backdrop
    try {
        const modalZ = 11000;
        modalEl.style.zIndex = modalZ;
        const backs = document.querySelectorAll('.modal-backdrop.show');
        if (backs && backs.length) {
            const b = backs[backs.length - 1];
            b.style.zIndex = modalZ - 1;
        }
    } catch (e) {
        // ignore
    }
}

/**
 * Show confirmation modal
 * @param {string} message - Confirmation message
 * @param {function} onConfirm - Callback when confirmed
 * @param {function} onCancel - Optional callback when cancelled
 * @param {string} title - Optional title for the modal
 */
function showConfirmModal(message, onConfirm, onCancel = null, title = 'Confirm Action') {
    const modalEl = document.getElementById('confirmModal');
    const titleEl = document.getElementById('confirmModalLabel');
    const messageEl = document.getElementById('confirmModalMessage');
    const confirmBtn = document.getElementById('confirmModalConfirm');
    const cancelBtn = document.getElementById('confirmModalCancel');
    
    if (!modalEl) {
        console.error('Confirm modal not found');
        if (onConfirm) onConfirm();
        return;
    }
    
    // Set title and message
    if (titleEl) titleEl.textContent = title;
    if (messageEl) messageEl.textContent = message || 'Are you sure you want to proceed?';
    
    // Remove previous event listeners by cloning
    const newConfirmBtn = confirmBtn.cloneNode(true);
    confirmBtn.parentNode.replaceChild(newConfirmBtn, confirmBtn);
    
    const newCancelBtn = cancelBtn.cloneNode(true);
    cancelBtn.parentNode.replaceChild(newCancelBtn, cancelBtn);
    
    // Show modal
    const modal = new bootstrap.Modal(modalEl);
    modal.show();
    
    // Handle confirm
    newConfirmBtn.addEventListener('click', () => {
        modal.hide();
        if (typeof onConfirm === 'function') {
            onConfirm();
        }
    });
    
    // Handle cancel
    newCancelBtn.addEventListener('click', () => {
        modal.hide();
        if (typeof onCancel === 'function') {
            onCancel();
        }
    });
    
    // Handle backdrop click
    modalEl.addEventListener('hidden.bs.modal', function handler() {
        modalEl.removeEventListener('hidden.bs.modal', handler);
        if (typeof onCancel === 'function') {
            onCancel();
        }
    }, { once: true });
}

/**
 * Show success modal
 * @param {string} message - Success message
 * @param {string} title - Optional title for the modal
 */
function showSuccessModal(message, title = 'Success!') {
    const modalEl = document.getElementById('successModal');
    const titleEl = document.getElementById('successModalLabel');
    const messageEl = document.getElementById('successModalMessage');
    
    if (!modalEl) {
        console.error('Success modal not found');
        if (typeof showNotification === 'function') {
            showNotification(message, true);
        } else {
            alert(message);
        }
        return;
    }
    
    // Set title and message
    titleEl.textContent = title;
    messageEl.textContent = message || 'Operation completed successfully';
    
    // Show modal
    const modal = new bootstrap.Modal(modalEl);
    modal.show();
    
    // No auto-close - user must click to close
    // Ensure modal appears above any other open modals by nudging z-index of modal and its backdrop
    try {
        const modalZ = 11000;
        modalEl.style.zIndex = modalZ;
        const backs = document.querySelectorAll('.modal-backdrop.show');
        if (backs && backs.length) {
            const b = backs[backs.length - 1];
            b.style.zIndex = modalZ - 1;
        }
    } catch (e) {
        // ignore
    }
}
</script>

<style>
/* Modal Dialog Styles */
#confirmModal .modal-content,
#successModal .modal-content,
#errorModal .modal-content {
    border-radius: 12px;
}

#confirmModal .modal-header {
    padding: 1rem 1.5rem 0;
}

#confirmModal .modal-body {
    padding: 1.5rem;
}

#confirmModal .modal-footer {
    padding: 0 1.5rem 1.5rem;
}

#successModal .modal-body {
    padding: 2rem 1.5rem;
}

#confirmModal .btn-close {
    margin: 0;
}

#confirmModal .btn,
#successModal .btn,
#errorModal .btn {
    min-width: 100px;
    padding: 0.5rem 1.5rem;
    border-radius: 8px;
    font-weight: 500;
}

#confirmModal .btn-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border: none;
}

#confirmModal .btn-primary:hover {
    background: linear-gradient(135deg, #5568d3 0%, #653a91 100%);
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.15);
}

#successModal .fas.fa-check-circle {
    animation: scaleIn 0.3s ease-out;
}

#errorModal .fas.fa-exclamation-circle {
    animation: scaleIn 0.3s ease-out;
}

#errorModal .modal-header {
    padding: 1rem 1.5rem 0;
}

#errorModal .modal-body {
    padding: 1.5rem;
}

#errorModal .modal-footer {
    padding: 0 1.5rem 1.5rem;
}

#errorModal .btn-close {
    margin: 0;
}

#errorModal .btn-danger {
    background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
    border: none;
}

#errorModal .btn-danger:hover {
    background: linear-gradient(135deg, #d67fe8 0%, #e0465a 100%);
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.15);
}

/* Ensure confirmation modal appears above form modals */
#confirmModal {
    z-index: 10060 !important;
}

#confirmModal .modal-backdrop {
    z-index: 10059 !important;
}

#successModal .modal-header {
    padding: 1rem 1.5rem 0;
}

#successModal .modal-footer {
    padding: 0 1.5rem 1.5rem;
}

#successModal .btn-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border: none;
}

#successModal .btn-primary:hover {
    background: linear-gradient(135deg, #5568d3 0%, #653a91 100%);
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.15);
}

@keyframes scaleIn {
    from {
        transform: scale(0);
        opacity: 0;
    }
    to {
        transform: scale(1);
        opacity: 1;
    }
}
</style>

