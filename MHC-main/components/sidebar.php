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
            <!-- Patients: view for all roles, manage (add/edit) for admin/receptionist -->
            <li>
                <a href="#" class="nav-link" onclick="showSection('patients_view'); return false;">
                    <i class="fas fa-user-injured"></i> View Patients
                </a>
            </li>
            <li>
                <a href="#" class="nav-link" onclick="showSection('patients_manage'); return false;">
                    <i class="fas fa-user-plus"></i> Add / Edit Patients
                </a>
            </li>
            <li>
                <a href="#" class="nav-link" onclick="showSection('patient_files'); return false;">
                    <i class="fas fa-folder-open"></i> Patient Files
                </a>
            </li>
            <li>
                <a href="#" class="nav-link" onclick="showSection('billing_create'); return false;">
                    <i class="fas fa-file-invoice-dollar"></i> Create Bill
                </a>
            </li>
            <li>
                <a href="#" class="nav-link" onclick="showSection('billing_update'); return false;">
                    <i class="fas fa-pen-to-square"></i> Update Bill
                </a>
            </li>
        <?php elseif ($isConsultant): ?>
            <li>
                <a href="#" class="nav-link" onclick="showSection('appointments'); return false;">
                    <i class="fas fa-calendar-alt"></i> Appointments
                </a>
            </li>
            <li>
                <a href="#" class="nav-link" onclick="showSection('patients_view'); return false;">
                    <i class="fas fa-user-injured"></i> View Patients
                </a>
            </li>
            <li>
                <a href="#" class="nav-link" onclick="showSection('patient_files'); return false;">
                    <i class="fas fa-folder-open"></i> Patient Files
                </a>
            </li>
        <?php elseif ($isReceptionist): ?>
            <li>
                <a href="#" class="nav-link" onclick="showSection('appointments'); return false;">
                    <i class="fas fa-calendar-alt"></i> Appointments
                </a>
            </li>
            <li>
                <a href="#" class="nav-link" onclick="showSection('patients_view'); return false;">
                    <i class="fas fa-user-injured"></i> View Patients
                </a>
            </li>
            <li>
                <a href="#" class="nav-link" onclick="showSection('patients_manage'); return false;">
                    <i class="fas fa-user-plus"></i> Add / Edit Patients
                </a>
            </li>
            <li>
                <a href="#" class="nav-link" onclick="showSection('patient_files'); return false;">
                    <i class="fas fa-folder-open"></i> Patient Files
                </a>
            </li>
            <li>
                <a href="#" class="nav-link" onclick="showSection('billing_create'); return false;">
                    <i class="fas fa-file-invoice-dollar"></i> Create Bill
                </a>
            </li>
            <li>
                <a href="#" class="nav-link" onclick="showSection('billing_update'); return false;">
                    <i class="fas fa-pen-to-square"></i> Update Bill
                </a>
            </li>
        <?php endif; ?>

        <li>
            <a href="#" class="nav-link" onclick="showSection('billing_history'); return false;">
                <i class="fas fa-clock-rotate-left"></i> Bill History
            </a>
        </li>
    </ul>
</div>
