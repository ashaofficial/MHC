<?php
include "auth.php";
if (strtolower($USER['role'] ?? '') !== 'administrator') {
    die("Access denied!");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administrator Dashboard</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <!-- Bootstrap JS and Popper.js -->
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.min.js"></script>
</head>
<body class="role-admin">
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="brand">
            <h3><i class="fas fa-shield-alt"></i> Admin</h3>
        </div>
        <ul class="nav-menu">
            <li><a href="#" class="nav-link active" onclick="showSection('dashboard'); return false;"><i class="fas fa-chart-line"></i> Dashboard</a></li>
            <li><a href="#" class="nav-link" onclick="showSection('users'); return false;"><i class="fas fa-users"></i> Users</a></li>
            <li><a href="#" class="nav-link" onclick="showSection('settings'); return false;"><i class="fas fa-cog"></i> Settings</a></li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Header/Navbar -->
        <nav class="navbar">
            <h1 class="navbar-title">Administrator Dashboard</h1>
            <div class="navbar-right">
                <div class="profile-dropdown">
                    <div class="profile-menu">
                        <div class="profile-icon"><?php echo strtoupper($USER['name'][0] ?? $USER['username'][0]); ?></div>
                        <div>
                            <div class="profile-name"><?php echo htmlspecialchars($USER['name'] ?? $USER['username']); ?></div>
                                <div class="profile-role">Administrator</div>
                        </div>
                    </div>
                    <div class="dropdown-content">
                        <a href="#" onclick="openProfileModal(); return false;"><i class="fas fa-user-circle"></i> My Profile</a>
                        <a href="#" onclick="loadSettings(); return false;"><i class="fas fa-sliders-h"></i> Settings</a>
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
        // small toast helper to display messages from modals/pages
        function ensureAdminToast() {
            if (document.getElementById('adminActionToast')) return document.getElementById('adminActionToast');
            const wrapper = document.createElement('div');
            wrapper.className = 'position-fixed bottom-0 end-0 p-3';
            wrapper.style.zIndex = 9999;
            wrapper.innerHTML = `
                <div id="adminActionToast" class="toast align-items-center text-bg-success" role="status" aria-live="polite" aria-atomic="true">
                  <div class="d-flex">
                    <div class="toast-body" id="adminActionToastBody">Saved</div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                  </div>
                </div>`;
            document.body.appendChild(wrapper);
            return document.getElementById('adminActionToast');
        }

        function showToast(message, success = true) {
            const toastEl = ensureAdminToast();
            const body = document.getElementById('adminActionToastBody');
            if (body) body.innerText = message;
            toastEl.classList.remove('text-bg-danger','text-bg-success');
            toastEl.classList.add(success? 'text-bg-success' : 'text-bg-danger');
            try {
                const bs = new bootstrap.Toast(toastEl);
                bs.show();
            } catch (e) {
                // fallback alert
                if (!success) console.error(message);
                alert(message);
            }
        }
        function showSection(section) {
            const contentArea = document.getElementById('contentArea');
            
            switch(section) {
                case 'dashboard':
                    contentArea.innerHTML = `
                        <h2>Dashboard</h2>
                    `;
                    break;
                case 'users':
                    contentArea.innerHTML = `
                        <h2>Manage Users</h2>
                    `;
                    break;
                case 'settings':
                    // load settings into the main area
                    loadSettings();
                    break;
            }

            // Update active nav link
            document.querySelectorAll('.nav-link').forEach(link => link.classList.remove('active'));
            event.target.classList.add('active');
        }

                // Load settings.php into the #contentArea (inject HTML fragment and initialize)
                async function loadSettings() {
                        const contentArea = document.getElementById('contentArea');
                        const res = await fetch('settings.php?embed=1');
                        const html = await res.text();
                        const parser = new DOMParser();
                        const doc = parser.parseFromString(html, 'text/html');
                        // pull inner container
                        const container = doc.querySelector('.container');
                        if (container) {
                                contentArea.innerHTML = container.innerHTML;
                        } else {
                                contentArea.innerHTML = html;
                        }
                        // move modal nodes into document body so they function
                        doc.querySelectorAll('div.modal').forEach(m => {
                            try {
                                const imported = document.importNode(m, true);
                                const existing = document.getElementById(imported.id);
                                if (existing) existing.remove();
                                document.body.appendChild(imported);
                            } catch (err) {
                                // fallback: clone using outerHTML
                                const tmp = document.createElement('div');
                                tmp.innerHTML = m.outerHTML;
                                const node = tmp.firstElementChild;
                                const existing = document.getElementById(node.id);
                                if (existing) existing.remove();
                                document.body.appendChild(node);
                            }
                        });
                        // execute inline scripts found in the fragment and import external scripts if needed
                        doc.querySelectorAll('script').forEach(s => {
                            // skip if identical external script already present
                            if (s.src) {
                                if (!document.querySelector('script[src="' + s.src + '"]')) {
                                    const newScript = document.createElement('script');
                                    newScript.src = s.src;
                                    newScript.async = false;
                                    document.body.appendChild(newScript);
                                }
                                return;
                            }
                            const newScript = document.createElement('script');
                            newScript.type = s.type || 'text/javascript';
                            newScript.text = s.textContent;
                            document.body.appendChild(newScript);
                        });
                        // initialize settings page if function provided
                        if (typeof initializeSettingsPage === 'function') {
                                initializeSettingsPage();
                        }
                }

                // Profile modal handling
                function openProfileModal() {
                        const modalHtml = `
                        <div class="modal fade" id="profileModal" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header"><h5 class="modal-title">My Profile</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">
                                        <form id="profileForm">
                                            <div class="mb-2"><label class="form-label">Full name</label>
                                                <input name="name" class="form-control" value="${escapeHtml(`<?php echo addslashes($USER['name'] ?? $USER['username']); ?>`)}" required>
                                            </div>
                                            <div class="mb-2"><label class="form-label">Email</label>
                                                <input name="email" type="email" class="form-control" value="${escapeHtml(`<?php echo addslashes($USER['email'] ?? ''); ?>`)}">
                                            </div>
                                            <div class="mb-2"><label class="form-label">Mobile</label>
                                                <input name="mobile" class="form-control" value="${escapeHtml(`<?php echo addslashes($USER['mobile'] ?? ''); ?>`)}">
                                            </div>
                                            <div class="mb-2"><label class="form-label">New Password</label>
                                                <input name="password" type="password" class="form-control">
                                            </div>
                                        </form>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                        <button id="saveProfileBtn" type="button" class="btn btn-primary">Save</button>
                                    </div>
                                </div>
                            </div>
                        </div>`;
                        // append modal
                        const tmp = document.createElement('div'); tmp.innerHTML = modalHtml;
                        // remove existing profileModal if present
                        const old = document.getElementById('profileModal'); if (old) old.remove();
                        document.body.appendChild(tmp.firstElementChild);
                        const pm = new bootstrap.Modal(document.getElementById('profileModal'));
                        pm.show();
                        document.getElementById('saveProfileBtn').addEventListener('click', async () => {
                                const form = document.getElementById('profileForm');
                                const fd = new FormData(form);
                                const res = await fetch('save_profile.php', { method: 'POST', body: fd });
                                const j = await res.json();
                                if (j.status === 'success') {
                                        pm.hide();
                                        // update displayed name
                                        const pname = document.querySelector('.profile-name');
                                        if (pname) pname.innerText = form.name.value;
                                        showToast(j.message || 'Profile updated');
                                } else {
                                        showToast(j.message || 'Error updating profile', false);
                                }
                        }, { once: true });
                }

                function escapeHtml(s) { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;'); }

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
