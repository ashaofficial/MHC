<?php include "auth.php"; if (strtolower($USER['role'] ?? '') !== 'consultant') die("Access denied!"); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Consultant Dashboard</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/dashboard.css">
</head>
<body class="role-consultant">
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="brand">
            <h3><i class="fas fa-briefcase"></i> Consultant</h3>
        </div>
        <ul class="nav-menu">
            <li><a href="#" class="nav-link active" onclick="showSection('dashboard'); return false;"><i class="fas fa-chart-line"></i> Dashboard</a></li>
            <li><a href="#" class="nav-link" onclick="showSection('clients'); return false;"><i class="fas fa-handshake"></i> Clients</a></li>
            <li><a href="#" class="nav-link" onclick="showSection('projects'); return false;"><i class="fas fa-project-diagram"></i> Projects</a></li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Header/Navbar -->
        <nav class="navbar">
            <h1 class="navbar-title">Consultant Dashboard</h1>
            <div class="navbar-right">
                <div class="profile-dropdown">
                    <div class="profile-menu">
                        <div class="profile-icon"><?php echo strtoupper($USER['name'][0] ?? $USER['username'][0]); ?></div>
                        <div>
                            <div class="profile-name"><?php echo htmlspecialchars($USER['name'] ?? $USER['username']); ?></div>
                                <div class="profile-role">Consultant</div>
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
                                        <h5 class="card-title"><i class="fas fa-handshake"></i> Active Clients</h5>
                                        <p class="card-text text-muted">Manage your client relationships</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <div class="card">
                                    <div class="card-body">
                                        <h5 class="card-title"><i class="fas fa-tasks"></i> Ongoing Projects</h5>
                                        <p class="card-text text-muted">Track your projects</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <div class="card">
                                    <div class="card-body">
                                        <h5 class="card-title"><i class="fas fa-clock"></i> Hours Logged</h5>
                                        <p class="card-text text-muted">View your time tracking</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
                    break;
                case 'clients':
                    contentArea.innerHTML = `
                        <h2>Clients</h2>
                        <p>Manage all your client relationships and information.</p>
                        <button class="btn btn-primary"><i class="fas fa-plus"></i> Add New Client</button>
                    `;
                    break;
                case 'projects':
                    contentArea.innerHTML = `
                        <h2>Projects</h2>
                        <p>View and manage all your projects.</p>
                        <button class="btn btn-primary"><i class="fas fa-plus"></i> Create New Project</button>
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
