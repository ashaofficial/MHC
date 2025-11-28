<?php include "auth.php"; if (strtolower($USER['role'] ?? '') !== 'receptionist') die("Access denied!"); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receptionist Dashboard</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/dashboard.css">
</head>
<body class="role-receptionist">
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="brand">
            <h3><i class="fas fa-phone"></i> Receptionist</h3>
        </div>
        <ul class="nav-menu">
            <li><a href="#" class="nav-link active" onclick="showSection('dashboard'); return false;"><i class="fas fa-chart-line"></i> Dashboard</a></li>
            <li><a href="#" class="nav-link" onclick="showSection('appointments'); return false;"><i class="fas fa-calendar"></i> Appointments</a></li>
            <li><a href="#" class="nav-link" onclick="showSection('calls'); return false;"><i class="fas fa-phone"></i> Call Log</a></li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Header/Navbar -->
        <nav class="navbar">
            <h1 class="navbar-title">Receptionist Dashboard</h1>
            <div class="navbar-right">
                <div class="profile-dropdown">
                    <div class="profile-menu">
                        <div class="profile-icon"><?php echo strtoupper($USER['name'][0] ?? $USER['username'][0]); ?></div>
                        <div>
                            <div class="profile-name"><?php echo htmlspecialchars($USER['name'] ?? $USER['username']); ?></div>
                                <div class="profile-role">Receptionist</div>
                        </div>
                    </div>
                    <div class="dropdown-content">
                        <a href="#"><i class="fas fa-user-circle"></i> My Profile</a>
                        <a href="#"><i class="fas fa-sliders-h"></i> Settings</a>
                        <button onclick="logout()"><i class="fas fa-sign-out-alt"></i> Logout</button>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Content Area -->
        <div id="contentArea" class="container p-4">
            <!-- dynamic content will be injected here -->
        </div>

    <script>
        function showSection(section) {
            const contentArea = document.getElementById('contentArea');
            
            switch(section) {
                case 'dashboard':
                    contentArea.innerHTML = `
                        <h2>Dashboard</h2>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <div class="card">
                                    <div class="card-body">
                                        <h5 class="card-title"><i class="fas fa-calendar"></i> Today's Appointments</h5>
                                        <p class="card-text text-muted">View scheduled appointments</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <div class="card">
                                    <div class="card-body">
                                        <h5 class="card-title"><i class="fas fa-phone"></i> Call Logs</h5>
                                        <p class="card-text text-muted">Review incoming calls</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <div class="card">
                                    <div class="card-body">
                                        <h5 class="card-title"><i class="fas fa-users"></i> Visitors</h5>
                                        <p class="card-text text-muted">Check visitor log</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
                    break;
                case 'appointments':
                    contentArea.innerHTML = `
                        <h2>Appointments</h2>
                        <p>Manage and schedule all appointments.</p>
                        <button class="btn btn-primary"><i class="fas fa-plus"></i> Schedule New Appointment</button>
                    `;
                    break;
                case 'calls':
                    contentArea.innerHTML = `
                        <h2>Call Log</h2>
                        <p>View complete call history and logs.</p>
                        <button class="btn btn-primary"><i class="fas fa-sync"></i> Refresh Logs</button>
                    `;
                    break;
            }

            // Update active nav link
            document.querySelectorAll('.nav-link').forEach(link => link.classList.remove('active'));
            event.target.classList.add('active');
        }

        function logout() {
            if (confirm('Are you sure you want to logout?')) {
                fetch("logout.php")
                    .then(() => {
                        window.location = "login.html";
                    });
            }
        }
    </script>
</body>
</html>
