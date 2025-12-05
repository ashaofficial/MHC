<?php
include "../auth.php";
include "../components/helpers.php";

// Check admin role
if (!isAdmin($USER['role'] ?? '')) {
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
    <link rel="stylesheet" href="../css/dashboard.css">

    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.min.js"></script>
</head>

<body class="role-admin">

    <!-- Sidebar -->
    <?php include "../components/sidebar.php"; ?>

    <!-- Main Content Wrapper -->
    <div class="main-content">

        <!-- Correct Navbar -->
        <?php $pageTitle = "Administrator Dashboard"; include '../components/navbar.php'; ?>

        <!-- Dynamic Content here -->
        <div id="contentArea" class="container p-4"></div>
    </div>

    <!-- Shared Modals -->
    <?php include '../components/profile-modal.php'; ?>
    <?php include '../components/dashboard-utils.php'; ?>


<script>
/* ---------------------------
   Fragment Fetcher (unchanged)
---------------------------- */
async function fetchFragment(url) {
    try {
        const res = await fetch(url);
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        const html = await res.text();

        if (html.includes('<!DOCTYPE') || html.includes('<html')) {
            return '<tr><td colspan="10" class="text-danger">Fragment validation failed</td></tr>';
        }
        return html;
    } catch (err) {
        return '<tr><td colspan="7" class="text-danger">Error loading fragment: ' + err.message + '</td></tr>';
    }
}

let adminUserSortField = 'profile';
let adminUserSortDir = 'asc';
let adminUserFilters = { name: '', mobile: '', doj: '' };

/* ---------------------------
   Load Settings Tabs
---------------------------- */
let adminModalsReady = false;

async function ensureAdminModalsLoaded(forceReload = false) {
    if (adminModalsReady && !forceReload) return;
    try {
        const res = await fetch('settings.php?loadModals=1');
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        const html = await res.text();
        const doc = new DOMParser().parseFromString(html, "text/html");
        doc.querySelectorAll('.modal').forEach(m => {
            const exist = document.getElementById(m.id);
            if (exist) exist.remove();
            document.body.appendChild(document.importNode(m, true));
        });
        // reset cached Bootstrap modal references so they bind to the new DOM nodes
        window._userModal = null;
        window._roleModal = null;
        window._consultantModal = null;
        adminModalsReady = true;
    } catch (err) {
        console.error('Failed to load admin modals', err);
        showToast('Unable to load admin modals', false);
    }
}

async function loadSettings() {
    const contentArea = document.getElementById('contentArea');

    contentArea.innerHTML = `
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2 class="mb-0">Settings</h2>
            </div>
            <div class="d-flex justify-content-between align-items-center">
                <ul class="nav nav-tabs mt-3 mb-3">
                    <li class="nav-item"><a class="nav-link active" id="users-tab" data-bs-toggle="tab" href="#users">Users</a></li>
                    <li class="nav-item"><a class="nav-link" id="roles-tab" data-bs-toggle="tab" href="#roles">Roles</a></li>
                    <li class="nav-item"><a class="nav-link" id="consultants-tab" data-bs-toggle="tab" href="#consultants">Consultants</a></li>
                </ul>
                <div class="mb-3">
                    <button id="addUserBtn" class="btn btn-sm btn-success"><i class="fas fa-plus"></i> Add User</button>
                </div>
            </div>

            <div class="tab-content mt-3">
                <div class="tab-pane fade show active" id="users"><div id="usersPane">Loading users...</div></div>
                <div class="tab-pane fade" id="roles"><div id="rolesPane">Loading roles...</div></div>
                <div class="tab-pane fade" id="consultants"><div id="consultantsPane">Loading consultants...</div></div>
            </div>
        </div>
    `;

    try {
        const userParams = new URLSearchParams({
            usersFragment: '1',
            sortField: adminUserSortField,
            sortDir: adminUserSortDir,
            filterName: adminUserFilters.name,
            filterMobile: adminUserFilters.mobile,
            filterDoj: adminUserFilters.doj
        });

        const [usersHTML, rolesHTML, consultsHTML] = await Promise.all([
            fetchFragment('settings.php?' + userParams.toString()),
            fetchFragment('settings.php?rolesFragment=1'),
            fetchFragment('settings.php?consultantsFragment=1')
        ]);

        renderUsersPaneAdmin(usersHTML);
        renderRolesPaneAdmin(rolesHTML);
        renderConsultantsPaneAdmin(consultsHTML);

        await ensureAdminModalsLoaded();

        // Bind admin-side handlers so Edit/Save work when fragments are loaded into Admin page
        bindUserButtonsAdmin();
        bindUserFilterControlsAdmin();
        bindRoleButtonsAdmin();
        bindConsultantButtonsAdmin();

    } catch (err) {
        contentArea.innerHTML = `<p class="text-danger">Failed to load settings.</p>`;
    }
}

function renderUsersPaneAdmin(rowsHTML) {
    const pane = document.getElementById('usersPane');
    if (!pane) return;
    pane.innerHTML = `
        <div class="card shadow-sm mb-3">
            <div class="card-body">
                <form id="userFilterForm" class="user-filter-inline">
                    <div class="filter-field">
                        <label class="form-label">Profile</label>
                        <input type="text" class="form-control form-control-sm" id="userFilterName" placeholder="Search profile..." value="${adminUserFilters.name || ''}">
                    </div>
                    <div class="filter-field">
                        <label class="form-label">Mobile</label>
                        <input type="text" class="form-control form-control-sm" id="userFilterMobile" placeholder="Search mobile..." value="${adminUserFilters.mobile || ''}">
                    </div>
                    <div class="filter-field filter-field--short">
                        <label class="form-label">Date of Joining</label>
                        <input type="date" class="form-control form-control-sm" id="userFilterDoj" value="${adminUserFilters.doj || ''}">
                    </div>
                    <div class="filter-actions">
                        <button type="submit" class="btn btn-primary btn-sm me-2"><i class="fas fa-filter"></i> Apply</button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="resetUserFilters"><i class="fas fa-undo"></i> Reset</button>
                    </div>
                </form>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-striped table-hover table-sm">
                <thead class="table-light">
                    <tr>
                        <th>
                            <button type="button" class="btn btn-link p-0 user-sort-btn" data-field="profile">
                                Profile <span class="sort-icon" data-field="profile">${getAdminSortIcon('profile')}</span>
                            </button>
                        </th>
                        <th>
                            <button type="button" class="btn btn-link p-0 user-sort-btn" data-field="username">
                                Username <span class="sort-icon" data-field="username">${getAdminSortIcon('username')}</span>
                            </button>
                        </th>
                        <th>
                            <button type="button" class="btn btn-link p-0 user-sort-btn" data-field="role">
                                Role <span class="sort-icon" data-field="role">${getAdminSortIcon('role')}</span>
                            </button>
                        </th>
                        <th>Email</th><th>Mobile</th><th>DOJ</th><th>DOB</th>
                        <th>Description</th><th>Status</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>${rowsHTML}</tbody>
            </table>
        </div>
    `;
}

function renderRolesPaneAdmin(html) {
    const pane = document.getElementById('rolesPane');
    if (!pane) return;
    pane.innerHTML = html;
}

function renderConsultantsPaneAdmin(html) {
    const pane = document.getElementById('consultantsPane');
    if (!pane) return;
    pane.innerHTML = html;
}

function bindUserFilterControlsAdmin() {
    const form = document.getElementById('userFilterForm');
    const nameInput = document.getElementById('userFilterName');
    const mobileInput = document.getElementById('userFilterMobile');
    const dojInput = document.getElementById('userFilterDoj');

    if (form) {
        form.addEventListener('submit', (e) => {
            e.preventDefault();
            adminUserFilters.name = nameInput ? nameInput.value.trim() : '';
            adminUserFilters.mobile = mobileInput ? mobileInput.value.trim() : '';
            adminUserFilters.doj = dojInput ? dojInput.value.trim() : '';
            refreshUsersPaneAdmin();
        });
    }

    const resetBtn = document.getElementById('resetUserFilters');
    if (resetBtn) {
        resetBtn.addEventListener('click', () => {
            adminUserFilters = { name: '', mobile: '', doj: '' };
            refreshUsersPaneAdmin();
        });
    }

    document.querySelectorAll('.user-sort-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const field = btn.dataset.field;
            if (!field) return;
            if (adminUserSortField === field) {
                adminUserSortDir = adminUserSortDir === 'asc' ? 'desc' : 'asc';
            } else {
                adminUserSortField = field;
                adminUserSortDir = 'asc';
            }
            refreshUsersPaneAdmin();
        });
    });
}

function getAdminSortIcon(field) {
    if (adminUserSortField !== field) return '⇅';
    return adminUserSortDir === 'asc' ? '↑' : '↓';
}

async function refreshUsersPaneAdmin() {
    const pane = document.getElementById('usersPane');
    if (!pane) return;
    pane.innerHTML = '<div class="text-muted">Refreshing users...</div>';
    const params = new URLSearchParams({
        usersFragment: '1',
        sortField: adminUserSortField,
        sortDir: adminUserSortDir,
        filterName: adminUserFilters.name,
        filterMobile: adminUserFilters.mobile,
        filterDoj: adminUserFilters.doj
    });
    try {
        const rows = await fetchFragment('settings.php?' + params.toString());
        renderUsersPaneAdmin(rows);
        bindUserButtonsAdmin();
        bindUserFilterControlsAdmin();
    } catch (err) {
        pane.innerHTML = `<div class="text-danger">Unable to refresh users.</div>`;
    }
}

async function refreshRolesPaneAdmin() {
    const pane = document.getElementById('rolesPane');
    if (!pane) return;
    pane.innerHTML = '<div class="text-muted">Refreshing roles...</div>';
    try {
        const html = await fetchFragment('settings.php?rolesFragment=1');
        renderRolesPaneAdmin(html);
        bindRoleButtonsAdmin();
    } catch (err) {
        pane.innerHTML = `<div class="text-danger">Unable to refresh roles.</div>`;
    }
}

async function refreshConsultantsPaneAdmin() {
    const pane = document.getElementById('consultantsPane');
    if (!pane) return;
    pane.innerHTML = '<div class="text-muted">Refreshing consultants...</div>';
    try {
        const html = await fetchFragment('settings.php?consultantsFragment=1');
        renderConsultantsPaneAdmin(html);
        bindConsultantButtonsAdmin();
    } catch (err) {
        pane.innerHTML = `<div class="text-danger">Unable to refresh consultants.</div>`;
    }
}

async function refreshAllPanesAdmin() {
    await Promise.all([
        refreshUsersPaneAdmin(),
        refreshRolesPaneAdmin(),
        refreshConsultantsPaneAdmin()
    ]);
}

/* ---------------------------
   Router
---------------------------- */
function showSection(section) {
    const contentArea = document.getElementById('contentArea');

    switch (section) {
        case 'dashboard':
            contentArea.innerHTML = `
                <h2><i class="fas fa-chart-line"></i> Dashboard</h2>
                <p class="text-muted mt-3">Welcome to the Administrator Dashboard</p>
            `;
            break;

        case 'settings':
            loadSettings();
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

        case 'patients_manage':
            contentArea.innerHTML = `
                <div class="card shadow-sm border-0">
                    <div class="card-body p-0">
                        <iframe src="../patients/patient_add.php"
                                style="width:100%;min-height:80vh;border:0;"
                                title="Add / Edit Patients"></iframe>
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

        case 'billing_create':
            contentArea.innerHTML = `
                <div class="card shadow-sm border-0">
                    <div class="card-body p-0">
                        <iframe src="../billing/create_bill.php"
                                style="width:100%;min-height:80vh;border:0;"
                                title="Create Bill"></iframe>
                    </div>
                </div>
            `;
            break;

        case 'billing_items':
            contentArea.innerHTML = `
                <div class="card shadow-sm border-0">
                    <div class="card-body p-0">
                        <iframe src="../billing/items.php"
                                style="width:100%;min-height:80vh;border:0;"
                                title="Billing Items"></iframe>
                    </div>
                </div>
            `;
            break;

        case 'billing_update':
            contentArea.innerHTML = `
                <div class="card shadow-sm border-0">
                    <div class="card-body p-0">
                        <iframe src="../billing/update_bill.php"
                                style="width:100%;min-height:80vh;border:0;"
                                title="Update Bill"></iframe>
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
            contentArea.innerHTML = `<h2>${section}</h2>`;
    }

    document.querySelectorAll('.sidebar .nav-link')
        .forEach(a => a.classList.remove('active'));

    const links = document.querySelectorAll('.sidebar .nav-link');
    for (let link of links) {
        if (link.getAttribute('onclick')?.includes("showSection('" + section + "')")) {
            link.classList.add('active');
            break;
        }
    }
}

document.addEventListener('DOMContentLoaded', () => {
    showSection('dashboard');
});

/* ---------------------------
   Admin-side binding helpers
   These replicate the essential parts of settings.js so that when
   Settings fragments are loaded into the Admin dashboard they behave
   the same (edit/save role, consultant, and user; and role accordion)
---------------------------- */

function bindUserButtonsAdmin() {
    document.querySelectorAll('.editUserBtn').forEach(btn => {
        btn.onclick = async (e) => {
            const id = btn.dataset.id;
            try {
                const res = await fetch('settings.php?editJson=' + encodeURIComponent(id));
                const data = await res.json();
                if (data.status === 'ok') {
                    // Ensure modal exists
                    const umEl = document.getElementById('userModal');
                    if (!umEl) return alert('User modal not loaded');
                    document.getElementById('userModalTitle').innerText = 'Edit User';
                    document.getElementById('userForm').reset();
                    document.getElementById('user_id').value = data.user.id;
                    document.getElementById('name').value = data.user.name || '';
                    document.getElementById('username').value = data.user.username || '';
                    document.getElementById('username').readOnly = true;
                    document.getElementById('email').value = data.user.email || '';
                    document.getElementById('mobile').value = data.user.mobile || '';
                    document.getElementById('role_id').value = data.user.role_id || '';
                    document.getElementById('status').value = data.user.status || 'active';
                    document.getElementById('doj').value = data.user.doj || '';
                    document.getElementById('dob').value = data.user.dob || '';
                    document.getElementById('description').value = data.user.description || '';
                    document.getElementById('specialization').value = data.consultant_specialization || '';
                    // show modal
                    const um = getUserModal(); if (um) um.show();
                } else {
                    showToast('Failed to load user', false);
                }
            } catch (e) {
                showToast('Error fetching user details', false);
            }
        };
    });

    // Add user button (if present)
    const addUserBtn = document.getElementById('addUserBtn');
    if (addUserBtn) addUserBtn.onclick = () => {
        document.getElementById('userModalTitle').innerText = 'Add User';
        document.getElementById('userForm').reset();
        document.getElementById('user_id').value = '';
        document.getElementById('username').readOnly = false;
        const um = getUserModal(); if (um) um.show();
    };

    // Save user handler
    const saveUserBtn = document.getElementById('saveUserBtn');
    if (saveUserBtn) saveUserBtn.onclick = async () => {
        const form = document.getElementById('userForm');
        const userId = document.getElementById('user_id').value;
        const isEdit = userId !== '';
        const confirmMessage = isEdit ? 'Are you sure you want to update this user?' : 'Are you sure you want to add this user?';

        const performSave = async () => {
            const fd = new FormData(form);
            try {
                const res = await fetch('settings_action.php?action=save_user', { method: 'POST', body: fd });
                const j = await res.json();
                if (j.status === 'success') {
                    const um = getUserModal(); if (um) um.hide();
                    if (typeof showSuccessModal === 'function') showSuccessModal(j.message || 'User saved successfully'); else showToast(j.message || 'User saved');
                    await refreshAllPanesAdmin();
                } else {
                    if (typeof showErrorModal === 'function') showErrorModal(j.message || 'Error saving user'); else showToast(j.message || 'Error saving user', false);
                }
            } catch (e) {
                showToast('Network error saving user', false);
            }
        };

        if (typeof showConfirmModal === 'function') {
            showConfirmModal(confirmMessage, performSave);
        } else if (confirm(confirmMessage)) {
            await performSave();
        }
    };
}

// lightweight modal helpers and toast fallback for Admin page
function getUserModal(){ if (!window._userModal) { const el = document.getElementById('userModal'); if (el) window._userModal = new bootstrap.Modal(el); } return window._userModal; }
function getRoleModal(){ if (!window._roleModal) { const el = document.getElementById('roleModal'); if (el) window._roleModal = new bootstrap.Modal(el); } return window._roleModal; }
function getConsultantModal(){ if (!window._consultantModal) { const el = document.getElementById('consultantModal'); if (el) window._consultantModal = new bootstrap.Modal(el); } return window._consultantModal; }

function showToast(message, success = true) {
    if (success) {
        if (typeof showNotification === 'function') {
            showNotification(message, true);
        } else {
            alert(message);
        }
    } else {
        if (typeof showErrorModal === 'function') {
            showErrorModal(message);
        } else if (typeof showNotification === 'function') {
            showNotification(message, false);
        } else {
            alert(message);
        }
    }
}

function bindRoleButtonsAdmin() {
    function animateRoleContents(contents, expand) {
        if (!contents) return;

        const cleanUp = () => {
            if (contents._roleTransitionHandler) {
                contents.removeEventListener('transitionend', contents._roleTransitionHandler);
                delete contents._roleTransitionHandler;
            }
        };
        cleanUp();

        if (expand) {
            contents.classList.remove('collapsed');
            contents.style.maxHeight = '0px';
            const target = contents.scrollHeight;
            requestAnimationFrame(() => {
                contents.style.maxHeight = target + 'px';
            });
            const handler = (evt) => {
                if (evt.propertyName !== 'max-height') return;
                contents.style.maxHeight = 'none';
                cleanUp();
            };
            contents._roleTransitionHandler = handler;
            contents.addEventListener('transitionend', handler);
        } else {
            const current = contents.scrollHeight;
            contents.style.maxHeight = current + 'px';
            requestAnimationFrame(() => {
                contents.style.maxHeight = '0px';
            });
            const handler = (evt) => {
                if (evt.propertyName !== 'max-height') return;
                contents.classList.add('collapsed');
                contents.style.maxHeight = '';
                cleanUp();
            };
            contents._roleTransitionHandler = handler;
            contents.addEventListener('transitionend', handler);
        }
    }

    function toggleRoleBlock(container) {
        const btn = container.querySelector('.role-toggle');
        const contents = container.querySelector('.role-contents');
        const header = container.querySelector('.role-header');
        const expanded = btn.getAttribute('aria-expanded') === 'true';

        // close others
        document.querySelectorAll('.role-block').forEach(rb => {
            if (rb === container) return;
            const t = rb.querySelector('.role-toggle');
            const c = rb.querySelector('.role-contents');
            const h = rb.querySelector('.role-header');
            if (t && c && h) {
                t.setAttribute('aria-expanded','false');
                t.innerHTML = '<i class="fa fa-chevron-down"></i>';
                animateRoleContents(c, false);
                h.classList.remove('expanded');
                h.classList.add('collapsed');
            }
        });

        if (expanded) {
            btn.setAttribute('aria-expanded','false');
            btn.innerHTML = '<i class="fa fa-chevron-down"></i>';
            animateRoleContents(contents, false);
            if (header) { header.classList.remove('expanded'); header.classList.add('collapsed'); }
        } else {
            btn.setAttribute('aria-expanded','true');
            btn.innerHTML = '<i class="fa fa-chevron-up"></i>';
            animateRoleContents(contents, true);
            if (header) { header.classList.remove('collapsed'); header.classList.add('expanded'); }
        }
    }

    document.querySelectorAll('.role-block').forEach(rb => {
        const btn = rb.querySelector('.role-toggle');
        const header = rb.querySelector('.role-header');
        const c = rb.querySelector('.role-contents');
        if (c && c.classList.contains('collapsed')) c.style.maxHeight = '0px';
        if (btn) btn.addEventListener('click', (e) => { e.stopPropagation(); toggleRoleBlock(rb); });
        if (header) header.addEventListener('click', (e) => { toggleRoleBlock(rb); });
    });
}

function bindConsultantButtonsAdmin() {
    const consultantForm = document.getElementById('consultantForm');
    if (consultantForm) consultantForm.dataset.mode = consultantForm.dataset.mode || 'add';

    document.querySelectorAll('.editConsultantBtn').forEach(btn => {
        btn.onclick = async () => {
            const uid = btn.dataset.userid;
            try {
                const res = await fetch('settings.php?editJson=' + encodeURIComponent(uid));
                const data = await res.json();
                if (data.status === 'ok') {
                    document.getElementById('consultant_user_id').value = data.user.id;
                    document.getElementById('consultant_specialization').value = data.consultant_specialization || '';
                    if (consultantForm) consultantForm.dataset.mode = 'edit';
                    const cm = getConsultantModal(); if (cm) cm.show();
                } else {
                    showToast('Failed to load consultant details', false);
                }
            } catch (e) {
                showToast('Network error fetching consultant details', false);
            }
        };
    });

    const saveConsultantBtn = document.getElementById('saveConsultantBtn');
    if (saveConsultantBtn) saveConsultantBtn.onclick = async () => {
        const form = document.getElementById('consultantForm');
        const mode = form?.dataset.mode === 'edit' ? 'edit' : 'add';
        const confirmMessage = mode === 'edit' ? 'Are you sure you want to update this consultant?' : 'Are you sure you want to add this consultant?';

        const performSave = async () => {
            const fd = new FormData(form);
            try {
                const res = await fetch('settings_action.php?action=save_consultant', { method: 'POST', body: fd });
                const j = await res.json();
                if (j.status === 'success') {
                    const cm = getConsultantModal(); if (cm) cm.hide();
                    if (form) form.dataset.mode = 'add';
                    if (typeof showSuccessModal === 'function') showSuccessModal(j.message || 'Consultant saved successfully'); else showToast('Consultant saved');
                    await refreshAllPanesAdmin();
                } else {
                    showToast(j.message || 'Error saving consultant', false);
                }
            } catch (e) {
                showToast('Network error saving consultant', false);
            }
        };

        if (typeof showConfirmModal === 'function') {
            showConfirmModal(confirmMessage, performSave);
        } else if (confirm(confirmMessage)) {
            await performSave();
        }
    };
}
</script>

</body>
</html>
