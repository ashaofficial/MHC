<?php
include "../auth.php";
include "../components/helpers.php";

// Check consultant role
if (!hasRole($USER['role'] ?? '', 'consultant')) {
    die("Access denied!");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Consultant Dashboard</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/dashboard.css">
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.min.js"></script>
</head>
<body class="role-consultant">

    <!-- Sidebar (included component) -->
    <?php include '../components/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Header/Navbar (Shared Component) -->
        <?php $pageTitle = "Consultant Dashboard"; include '../components/navbar.php'; ?>

        <!-- Content Area -->
        <div id="contentArea" class="container p-4">
            <!-- Dynamic content injected here -->
        </div>
    </div>

    <!-- Profile & Confirmation Modals -->
    <?php include '../components/profile-modal.php'; ?>
    <?php include '../components/confirmation-modal.php'; ?>

    <!-- Dashboard Utilities (Shared Component) -->
    <?php include '../components/dashboard-utils.php'; ?>

    <script>
        /**
         * Override showSection for consultant-specific logic
         */
        function showSection(section) {
            const contentArea = document.getElementById('contentArea');
            
            switch(section) {
                case 'dashboard':
                    contentArea.innerHTML = `
                        <div class="row">
                            <div class="col-md-12">
                                <h2><i class="fas fa-chart-line"></i> Dashboard</h2>
                                <p class="text-muted mt-3">Welcome to your Consultant Dashboard</p>
                            </div>
                        </div>
                    `;
                    break;
                case 'appointments':
                    contentArea.innerHTML = `
                        <div class="row">
                            <div class="col-md-12">
                                <h2><i class="fas fa-calendar-alt"></i> Appointments</h2>
                                <p class="text-muted mt-3">View and manage your appointments</p>
                            </div>
                        </div>
                    `;
                    break;
                case 'patients_view':
                    contentArea.innerHTML = `
                        <div class="card shadow-sm border-0">
                            <div class="card-body p-0">
                                <iframe src="../patients/patients_view.php"
                                        style="width:100%;min-height:80vh;border:0;"
                                        title="View Patients"></iframe>
                            </div>
                        </div>
                    `;
                    break;
                case 'patient_files':
                    contentArea.innerHTML = `
                        <div class="card shadow-sm border-0">
                            <div class="card-body p-0">
                                <iframe src="../patients/patient_files_viewer.php"
                                        style="width:100%;min-height:80vh;border:0;"
                                        title="Patient Files"></iframe>
                            </div>
                        </div>
                    `;
                    break;
                case 'billing_history':
                    contentArea.innerHTML = `
                        <div class="card shadow-sm border-0">
                            <div class="card-body p-0">
                                <iframe src="../billing/bill_history.php"
                                        style="width:100%;min-height:80vh;border:0;"
                                        title="Bill History"></iframe>
                            </div>
                        </div>
                    `;
                    break;
                case 'work_tracker':
                    contentArea.innerHTML = `
                        <div class="card shadow-sm border-0">
                            <div class="card-body p-0">
                                <iframe src="../tools/work_tracker.php"
                                        style="width:100%;min-height:80vh;border:0;"
                                        title="Work Tracker"></iframe>
                            </div>
                        </div>
                    `;
                    break;
                default:
                    contentArea.innerHTML = '<h2>' + section + '</h2>';
            }

            document.querySelectorAll('.sidebar .nav-link')
                .forEach(link => link.classList.remove('active'));

            document.querySelectorAll('.sidebar .nav-link').forEach(link => {
                if (link.getAttribute('onclick')?.includes("showSection('" + section + "')")) {
                    link.classList.add('active');
                }
            });
        }

        // Initialize dashboard on page load
        document.addEventListener('DOMContentLoaded', () => {
            showSection('dashboard');
        });
    </script>
</body>
</html>
