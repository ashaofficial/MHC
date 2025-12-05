<?php
/**
 * Dashboard Sidebar Component
 * Include this in dashboard pages to display consistent sidebar navigation
 * 
 * Usage: <?php include 'components/sidebar.php'; ?>
 */

// Get role from global $USER variable set by auth.php
$role = strtolower(trim($USER['role'] ?? ''));
$isAdmin = in_array($role, ['administrator', 'admin'], true);
$isConsultant = $role === 'consultant';
$isReceptionist = $role === 'receptionist';

// Determine role-specific CSS class
$roleClass = match($role) {
    'administrator', 'admin' => 'role-admin',
    'consultant' => 'role-consultant',
    'receptionist' => 'role-receptionist',
    default => 'role-user'
};
?>

<!-- Sidebar Navigation -->
<div class="sidebar">
    <div class="brand">
        <h3>
            <i class="fas fa-<?php echo $isAdmin ? 'shield-alt' : ($isConsultant ? 'stethoscope' : 'phone'); ?>"></i>
            <?php echo ucfirst($role); ?>
        </h3>
    </div>
    
    <ul class="nav-menu">
        <li>
            <a href="#" class="nav-link active" onclick="showSection('dashboard'); return false;">
                <i class="fas fa-chart-line"></i> Dashboard
            </a>
        </li>
            <?php if ($isAdmin): ?>
                <li>
                    <a href="#" class="nav-link" onclick="showSection('settings'); return false;">
                        <i class="fas fa-users"></i> Users
                    </a>
                </li>
            <?php endif; ?>

            <?php if ($isReceptionist): ?>
                <li>
                    <a href="#" class="nav-link" onclick="showSection('appointments'); return false;">
                        <i class="fas fa-calendar-alt"></i> Appointments
                    </a>
                </li>
            <?php endif; ?>

            <?php if ($isAdmin || $isConsultant): ?>
                <li>
                    <a href="#" class="nav-link" onclick="showSection('work_tracker'); return false;">
                        <i class="fas fa-tasks"></i> Work Tracker
                    </a>
                </li>
            <?php endif; ?>

            <!-- Patients Tab -->
            <li>
                <a href="#" class="nav-link" onclick="toggleSubmenu('patientsSub'); return false;">
                    <i class="fas fa-user-injured"></i> Patients
                    <i class="fas fa-chevron-down submenu-caret" style="float:right; padding-left:6px"></i>
                </a>
                <ul id="patientsSub" class="submenu" aria-expanded="false">
                    <li>
                        <a href="#" class="nav-link" onclick="menuLinkClick('patients_view','patientsSub', this); return false;">
                            <i class="fas fa-eye"></i> View Patients
                        </a>
                    </li>
                    <?php if (! $isConsultant): ?>
                    <li>
                        <a href="#" class="nav-link" onclick="menuLinkClick('patients_manage','patientsSub', this); return false;">
                            <i class="fas fa-user-plus"></i> Add / Edit Patients
                        </a>
                    </li>
                    <?php endif; ?>
                    <li>
                        <a href="#" class="nav-link" onclick="menuLinkClick('patient_files','patientsSub', this); return false;">
                            <i class="fas fa-folder-open"></i> Patient Files
                        </a>
                    </li>
                </ul>
            </li>

            <!-- Billing Tab -->
            <li>
                <a href="#" class="nav-link" onclick="toggleSubmenu('billingSub'); return false;">
                    <i class="fas fa-file-invoice-dollar"></i> Billing
                    <i class="fas fa-chevron-down submenu-caret" style="float:right"></i>
                </a>
                <ul id="billingSub" class="submenu" aria-expanded="false">
                    <?php if ($isAdmin): ?>
                    <li>
                        <a href="#" class="nav-link" onclick="menuLinkClick('billing_items','billingSub', this); return false;">
                            <i class="fas fa-list"></i> Billing Items
                        </a>
                    </li>
                    <?php endif; ?>
                    <?php if ($isAdmin || $isReceptionist): ?>
                    <li>
                        <a href="#" class="nav-link" onclick="menuLinkClick('billing_create','billingSub', this); return false;">
                            <i class="fas fa-plus-circle"></i> Create Bill
                        </a>
                    </li>
                    <li>
                        <a href="#" class="nav-link" onclick="menuLinkClick('billing_update','billingSub', this); return false;">
                            <i class="fas fa-pen-to-square"></i> Update Bill
                        </a>
                    </li>
                    <?php endif; ?>
                    <li>
                        <a href="#" class="nav-link" onclick="menuLinkClick('billing_history','billingSub', this); return false;">
                            <i class="fas fa-clock-rotate-left"></i> Bill History
                        </a>
                    </li>
                </ul>
            </li>
    </ul>
</div>

<script>
// Toggle show/hide for sidebar submenus with animation and caret rotation
function toggleSubmenu(id) {
    const el = document.getElementById(id);
    if (!el) return;
    const isOpen = el.classList.toggle('open');
    el.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    // rotate caret inside the parent anchor
    const parentAnchor = el.previousElementSibling;
    if (parentAnchor) {
        const caret = parentAnchor.querySelector('.submenu-caret');
        if (caret) caret.classList.toggle('open', isOpen);
    }
}

// Called when a submenu link is clicked: show section, mark active, and close submenu
function menuLinkClick(section, submenuId, el) {
    // show content
    try { showSection(section); } catch (e) { console.warn(e); }

    // update active link
    document.querySelectorAll('.nav-link').forEach(l => l.classList.remove('active'));
    if (el) el.classList.add('active');

    // close the submenu after click
    if (submenuId) {
        const s = document.getElementById(submenuId);
        if (s) {
            s.classList.remove('open');
            s.setAttribute('aria-expanded', 'false');
        }
        // remove caret open state
        const parentAnchor = s?.previousElementSibling;
        const caret = parentAnchor?.querySelector('.submenu-caret');
        if (caret) caret.classList.remove('open');
    }
}

// Close all submenus when clicking outside the sidebar
document.addEventListener('click', function(e){
    if (!e.target.closest('.sidebar')) {
        document.querySelectorAll('.submenu.open').forEach(u => u.classList.remove('open'));
        document.querySelectorAll('.submenu-caret.open').forEach(c => c.classList.remove('open'));
    }
});
</script>

<style>
/* Sidebar submenu styling and simple slide animation */
.sidebar .nav-menu { list-style: none; padding-left: 10px; margin: 0; }
.sidebar .nav-menu li { margin: 0; }
.sidebar .nav-link { display: block; padding: 10px 12px; color: #fff; text-decoration: none; }
.sidebar .nav-link:hover { background: rgba(255,255,255,0.05); }
.sidebar .submenu { list-style: none; padding-left: 20px; overflow: hidden; max-height: 0; transition: max-height .22s ease; background: rgba(0,0,0,0.03); }
.sidebar .submenu.open { max-height: 500px; }
.sidebar .submenu li .nav-link { padding-left: 18px; font-size: 0.95em; }
.submenu-caret { transition: transform .22s ease; color: #ddd; }
.submenu-caret.open { transform: rotate(180deg); }
.nav-link.active { background: rgba(255,255,255,0.08); }
</style>
