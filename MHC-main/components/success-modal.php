<?php
/**
 * Modern Bootstrap Success Modal Component
 * Reusable success message dialog
 * 
 * Usage:
 *   <?php include '../components/success-modal.php'; ?>
 *   Then in JS: showSuccessModal('Message', title);
 */
?>
<!-- Success Modal -->
<div class="modal fade" id="successModal" tabindex="-1" aria-labelledby="successModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header border-0 pb-0">
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center py-4">
                <div class="mb-3">
                    <i class="fas fa-check-circle text-success" style="font-size: 4rem;"></i>
                </div>
                <h5 class="modal-title mb-3" id="successModalLabel">Success</h5>
                <p class="text-muted mb-0" id="successModalMessage">Operation completed successfully!</p>
            </div>
            <div class="modal-footer border-0 justify-content-center pt-0">
                <button type="button" class="btn btn-primary px-4" data-bs-dismiss="modal">OK</button>
            </div>
        </div>
    </div>
</div>

<script>
/**
 * Show modern Bootstrap success modal
 * @param {string} message - Success message to display
 * @param {string} title - Optional title (default: "Success")
 * @param {number} autoClose - Optional auto-close delay in ms (0 = no auto-close)
 */
function showSuccessModal(message, title = 'Success', autoClose = 0) {
    const modalEl = document.getElementById('successModal');
    const modalTitle = document.getElementById('successModalLabel');
    const modalMessage = document.getElementById('successModalMessage');
    
    // Set content
    modalTitle.textContent = title;
    modalMessage.textContent = message;
    
    // Create modal instance if needed
    let modal = bootstrap.Modal.getInstance(modalEl);
    if (!modal) {
        modal = new bootstrap.Modal(modalEl);
    }
    
    // Show modal
    modal.show();
    
    // Auto-close if specified
    if (autoClose > 0) {
        setTimeout(() => {
            modal.hide();
        }, autoClose);
    }
}
</script>

<style>
#successModal .modal-content {
    border-radius: 12px;
}

#successModal .modal-header {
    padding-top: 1.5rem;
    padding-left: 1.5rem;
    padding-right: 1.5rem;
}

#successModal .modal-body {
    padding-left: 2rem;
    padding-right: 2rem;
}

#successModal .modal-footer {
    padding-bottom: 1.5rem;
    padding-left: 2rem;
    padding-right: 2rem;
}

#successModal .btn {
    min-width: 100px;
    font-weight: 500;
}
</style>

