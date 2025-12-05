<?php
/**
 * Shared Dashboard Utilities & Toast Helper
 * Include this in all dashboard pages
 */
?>

<script>
/**
 * Show dashboard toast notification
 * @param {string} message - Toast message
 * @param {boolean} success - true for success (green), false for error (red)
 */
function showDashboardToast(message, success = true) {
    const toastEl = ensureDashboardToast();
    const body = document.getElementById('dashboardToastBody');
    if (body) body.innerText = message;
    
    toastEl.classList.remove('text-bg-danger', 'text-bg-success');
    toastEl.classList.add(success ? 'text-bg-success' : 'text-bg-danger');
    
    try {
        const bs = new bootstrap.Toast(toastEl);
        bs.show();
    } catch (e) {
        // Fallback if Bootstrap not loaded yet
        alert(message);
    }
}

/**
 * Ensure toast element exists in DOM
 */
function ensureDashboardToast() {
    let toastEl = document.getElementById('dashboardToast');
    if (toastEl) return toastEl;
    
    const wrapper = document.createElement('div');
    wrapper.className = 'position-fixed bottom-0 end-0 p-3';
    wrapper.style.zIndex = 9999;
    wrapper.innerHTML = `
        <div id="dashboardToast" class="toast align-items-center text-bg-success" role="status" aria-live="polite" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body" id="dashboardToastBody">Saved</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>`;
    document.body.appendChild(wrapper);
    
    return document.getElementById('dashboardToast');
}

/**
 * Default showSection implementation (override in dashboard pages as needed)
 */
function showSection(section) {
    const contentArea = document.getElementById('contentArea');
    if (!contentArea) return;
    
    switch(section) {
        case 'dashboard':
            contentArea.innerHTML = '<h2>Dashboard</h2><p>Welcome to the dashboard.</p>';
            break;
        default:
            contentArea.innerHTML = '<h2>' + section + '</h2>';
    }
    
    // Update active nav link
    document.querySelectorAll('.nav-link').forEach(link => link.classList.remove('active'));
    event?.target?.classList.add('active');
}
</script>
