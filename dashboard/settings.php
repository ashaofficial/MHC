<?php
// settings.php - full corrected version (Option A: complete missing parts, keep structure)
// Safety: start output buffering to avoid accidental HTML or PHP notice leakage when serving fragments/JSON
ob_start();

// Basic includes (these should NOT echo output; if they do, buffering will remove it for fragment endpoints)
include "../auth.php";
include "../secure/db.php";
include "../components/helpers.php";

// Tighten error reporting for production-ish behavior (still shows fatal errors)
error_reporting(E_ERROR | E_PARSE);

// Simple admin check
if (!isAdmin($USER['role'] ?? '')) {
    // For fragments/JSON respond appropriately
    if (!empty($_GET['usersFragment']) || !empty($_GET['rolesFragment']) || !empty($_GET['consultantsFragment']) || !empty($_GET['editJson']) || !empty($_GET['loadModals'])) {
        http_response_code(403);
        // clear any accidental buffered output
        @ob_end_clean();
        echo 'Access denied';
        exit;
    } else {
        http_response_code(403);
        // full page
        @ob_end_flush();
        die('Access denied');
    }
}

// Helper to safely flush fragment responses (clears any accidental output from includes)
function respond_fragment($payload, $ctype = 'text/html; charset=utf-8') {
    // discard any previous buffered content (like notices)
    if (ob_get_length() !== false) ob_clean();
    header('Content-Type: ' . $ctype);
    echo $payload;
    exit;
}

// ---------- Fetch data used by full page rendering ----------
$users = [];
$r = $conn->query("SELECT u.id, u.name, u.email, u.mobile, u.role_id, u.status, u.doj, u.dob, u.description, u.photo, r.role_name, c.username AS username FROM users u LEFT JOIN roles r ON u.role_id = r.id LEFT JOIN credential c ON c.user_id = u.id ORDER BY u.id DESC");
if ($r) while ($row = $r->fetch_assoc()) $users[] = $row;

$roles = [];
$rr = $conn->query("SELECT id, role_name FROM roles ORDER BY id");
if ($rr) while ($row = $rr->fetch_assoc()) $roles[] = $row;

// ---------- JSON for edit prefill ----------
if (!empty($_GET['editJson'])) {
    $uid = (int)$_GET['editJson'];

    $stmt = $conn->prepare("SELECT u.id, u.name, u.email, u.mobile, u.role_id, u.status, u.doj, u.dob, u.description, c.username FROM users u LEFT JOIN credential c ON c.user_id = u.id WHERE u.id = ? LIMIT 1");
    $stmt->bind_param('i', $uid);
    $stmt->execute();
    $res = $stmt->get_result();
    $user = $res->fetch_assoc();

    $consultant_spec = '';
    if ($user) {
        $qc = $conn->prepare("SELECT specialization FROM consultants WHERE user_id = ? LIMIT 1");
        $qc->bind_param('i', $uid);
        $qc->execute();
        $rc = $qc->get_result();
        if ($rc && $rc->num_rows) $consultant_spec = $rc->fetch_assoc()['specialization'];
    }

    // safer JSON respond (clear buffer first)
    respond_fragment(json_encode(['status' => $user ? 'ok' : 'error', 'user' => $user, 'consultant_specialization' => $consultant_spec]), 'application/json; charset=utf-8');
}

// ---------- Users fragment (table rows) ----------
if (!empty($_GET['usersFragment'])) {
    $allowedSortFields = ['profile', 'username', 'role'];
    $sortField = strtolower($_GET['sortField'] ?? 'profile');
    if (!in_array($sortField, $allowedSortFields, true)) {
        $sortField = 'profile';
    }
    $sortDir = strtolower($_GET['sortDir'] ?? 'asc') === 'desc' ? 'DESC' : 'ASC';
    switch ($sortField) {
        case 'role':
            $sortColumn = 'r.role_name';
            break;
        case 'username':
            $sortColumn = 'c.username';
            break;
        default:
            $sortColumn = 'u.name';
            break;
    }

    $filterName = trim($_GET['filterName'] ?? '');
    $filterMobile = trim($_GET['filterMobile'] ?? '');
    $filterDoj = trim($_GET['filterDoj'] ?? '');

    $sql = "SELECT u.id, u.name, u.email, u.mobile, u.role_id, u.status, u.doj, u.dob, u.description, u.photo, r.role_name, c.username AS username
            FROM users u
            LEFT JOIN roles r ON u.role_id = r.id
            LEFT JOIN credential c ON c.user_id = u.id";

    $where = [];
    $params = [];
    $types = '';

    if ($filterName !== '') {
        $where[] = "u.name LIKE ?";
        $params[] = '%' . $filterName . '%';
        $types   .= 's';
    }
    if ($filterMobile !== '') {
        $where[] = "u.mobile LIKE ?";
        $params[] = '%' . $filterMobile . '%';
        $types   .= 's';
    }
    if ($filterDoj !== '') {
        $where[] = "DATE(u.doj) = ?";
        $params[] = $filterDoj;
        $types   .= 's';
    }

    if (!empty($where)) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }

    $sql .= " ORDER BY {$sortColumn} {$sortDir}, u.id DESC";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        respond_fragment('<tr><td colspan="10" class="text-danger">Unable to load users.</td></tr>');
    }

    if (!empty($params)) {
        $bindParams = [];
        $bindParams[] = &$types;
        foreach ($params as $key => $value) {
            $bindParams[] = &$params[$key];
        }
        call_user_func_array([$stmt, 'bind_param'], $bindParams);
    }

    $stmt->execute();
    $r2 = $stmt->get_result();

    $rowsOutput = '';
    if ($r2 && $r2->num_rows) {
        while ($u = $r2->fetch_assoc()) {
            // Generate avatar: show photo if available, otherwise show first letter of profile name
            $username = $u['username'] ?? '';
            $displayName = $u['name'] ?? '';
            $firstLetterSource = $displayName !== '' ? $displayName : $username;
            $firstLetter = strtoupper(mb_substr($firstLetterSource, 0, 1)) ?: 'U';
            
            // Check if user has a photo
            if (!empty($u['photo'])) {
                $photoData = base64_encode($u['photo']);
                $avatar = '<div class="profile-avatar has-photo" style="background-image:url(\'data:image/jpeg;base64,' . $photoData . '\');"></div>';
            } else {
                $avatar = '<div class="profile-avatar profile-avatar--initial">' . htmlspecialchars($firstLetter) . '</div>';
            }
            
            $rowsOutput .= '<tr>';
            $rowsOutput .= '<td><div class="profile-cell">' . $avatar . '</div></td>';
            $rowsOutput .= '<td>' . htmlspecialchars($username) . '</td>';
            $rowsOutput .= '<td>' . htmlspecialchars($u['role_name'] ?? '') . '</td>';
            $rowsOutput .= '<td>' . htmlspecialchars($u['email'] ?? '') . '</td>';
            $rowsOutput .= '<td>' . htmlspecialchars($u['mobile'] ?? '') . '</td>';
            $rowsOutput .= '<td>' . htmlspecialchars($u['doj'] ?? '-') . '</td>';
            $rowsOutput .= '<td>' . htmlspecialchars($u['dob'] ?? '-') . '</td>';
            $desc = $u['description'] ?? '-';
            $short = htmlspecialchars(mb_substr($desc, 0, 50));
            if (mb_strlen($desc) > 50) $short .= '...';
            $rowsOutput .= '<td>' . $short . '</td>';
            $badgeClass = ($u['status'] === 'active') ? 'success' : 'secondary';
            $rowsOutput .= '<td><span class="badge bg-' . $badgeClass . '">' . htmlspecialchars($u['status']) . '</span></td>';
            $rowsOutput .= '<td class="text-center"><button class="btn btn-sm btn-outline-primary editUserBtn" data-id="' . intval($u['id']) . '">Edit</button></td>';
            $rowsOutput .= '</tr>';
        }
    } else {
        $rowsOutput = '<tr><td colspan="10" class="text-muted">No users found</td></tr>';
    }

    $stmt->close();
    respond_fragment($rowsOutput, 'text/html; charset=utf-8');
}

// ---------- Roles fragment ----------
if (!empty($_GET['rolesFragment'])) {
    $out = '<div class="row g-3 justify-content-start">';
    $rr2 = $conn->query("SELECT id, role_name FROM roles ORDER BY id");
    if ($rr2) {
        while ($role = $rr2->fetch_assoc()) {
            $rid = intval($role['id']);
            $rname = htmlspecialchars($role['role_name']);
            $out .= '<div class="col-md-6 col-lg-5 col-xl-4 role-block" data-role-id="' . $rid . '">';
            $out .= '<div class="card border-0 shadow-sm">';
            $out .= '<div class="card-header role-header collapsed" data-role-id="'. $rid .'" style="cursor:pointer;">';
            $out .= '<div class="d-flex justify-content-between align-items-center">';
            $out .= '<h6 class="mb-0 fw-bold role-title" style="color:inherit;">' . $rname . '</h6>';
            $out .= '<button type="button" class="btn btn-sm btn-light role-toggle" data-role-id="'. $rid .'" aria-expanded="false" style="width:32px;height:32px;padding:0;display:flex;align-items:center;justify-content:center;"><i class="fa fa-chevron-down"></i></button>';
            $out .= '</div>';
            $out .= '</div>'; // header

            // fetch users for this role
            $ur = $conn->prepare("SELECT id, name, status FROM users WHERE role_id = ? ORDER BY name");
            $ur->bind_param('i', $rid);
            $ur->execute();
            $ures = $ur->get_result();

            $out .= '<div class="card-body role-contents collapsed">';
            if ($ures && $ures->num_rows) {
                while ($uu = $ures->fetch_assoc()) {
                    $badge = ($uu['status'] === 'active') ? 'success' : 'secondary';
                    $out .= '<div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom">';
                    $out .= '<span class="fw-500">' . htmlspecialchars($uu['name']) . '</span>';
                    $out .= '<span class="badge bg-'. $badge .'">' . htmlspecialchars($uu['status']) . '</span>';
                    $out .= '</div>';
                }
            } else {
                $out .= '<p class="text-muted mb-0">No users assigned</p>';
            }
            $out .= '</div>'; // card-body
            $out .= '</div>'; // card
            $out .= '</div>'; // col
        }
    } else {
        $out .= '<div class="col-12"><p class="text-muted mb-0">No roles found</p></div>';
    }
    $out .= '</div>';

    respond_fragment($out, 'text/html; charset=utf-8');
}

// ---------- Consultants fragment ----------
if (!empty($_GET['consultantsFragment'])) {
    $qc = $conn->query("SELECT c.user_id, c.specialization, u.name, u.email, u.status FROM consultants c JOIN users u ON u.id = c.user_id ORDER BY u.name");
    $out = '<table class="table table-striped table-hover table-sm"><thead class="table-light"><tr><th>User</th><th>Email</th><th>Specialization/Position</th><th>Status</th><th class="text-center">Actions</th></tr></thead><tbody>';
    if ($qc) {
        while ($row = $qc->fetch_assoc()) {
            $out .= '<tr>';
            $out .= '<td>' . htmlspecialchars($row['name']) . '</td>';
            $out .= '<td>' . htmlspecialchars($row['email']) . '</td>';
            $out .= '<td>' . htmlspecialchars($row['specialization']) . '</td>';
            $badge = ($row['status'] === 'active') ? 'success' : 'secondary';
            $out .= '<td><span class="badge bg-' . $badge . '">' . htmlspecialchars($row['status']) . '</span></td>';
            $out .= '<td class="text-center"><button class="btn btn-sm btn-outline-primary editConsultantBtn me-1" data-userid="' . intval($row['user_id']) . '">Edit</button></td>';
            $out .= '</tr>';
        }
    } else {
        $out .= '<tr><td colspan="5" class="text-muted">No consultants found</td></tr>';
    }
    $out .= '</tbody></table>';

    respond_fragment($out, 'text/html; charset=utf-8');
}

// ---------- Optional: load only modals (for background import) ----------
if (!empty($_GET['loadModals'])) {
    // build modal markup (same as below). We'll capture below full HTML modals into $modalsHtml and return it.
    // To avoid duplication of code, we'll include the full page generation below (but capture only modal markup).
    // We'll fall through to full page generation then extract. For performance we can build modals here,
    // but to keep single source of truth, continue to full page and let caller fetch full page if needed.
}

// If reached here, serve full settings page (normal browser load).
// Flush any fragment-buffered content and continue to output full HTML
if (ob_get_length() !== false) ob_end_flush();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Settings</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/common.css">
    <link rel="stylesheet" href="../css/settings.css">
    <?php include '../components/notification_js.php'; ?>
    <?php include '../components/modal-dialogs.php'; ?>
    <?php include '../components/confirmation-modal.php'; ?>
    <?php include '../components/success-modal.php'; ?>
    <style>
        /* minimal CSS tweak: collapsed role-contents should have transition */
        .role-contents.collapsed { max-height: 0; overflow: hidden; transition: max-height 300ms ease; }
        .role-contents { transition: max-height 300ms ease; }
        
        /* Sortable table header styling */
        .sortable-header {
            user-select: none;
        }
        .sort-icon {
            font-size: 16px;
            margin-left: 6px;
            opacity: 0.6;
            font-weight: bold;
        }
        .sortable-header:hover .sort-icon {
            opacity: 1;
            color: #0056b3;
        }
    </style>
</head>
<body class="p-4">
<div class="container">
    <h2>Settings</h2>
    <div class="d-flex justify-content-between align-items-center">
        <ul class="nav nav-tabs" id="settingsTab" role="tablist">
            <li class="nav-item" role="presentation"><a class="nav-link active" id="users-tab" data-bs-toggle="tab" href="#users" role="tab">Users</a></li>
            <li class="nav-item" role="presentation"><a class="nav-link" id="roles-tab" data-bs-toggle="tab" href="#roles" role="tab">Roles</a></li>
            <li class="nav-item" role="presentation"><a class="nav-link" id="consultants-tab" data-bs-toggle="tab" href="#consultants" role="tab">Consultants</a></li>
        </ul>
        <div>
            <button id="addUserBtn" class="btn btn-sm btn-success">Add User</button>
        </div>
    </div>

    <div class="tab-content mt-3">
        <div class="tab-pane show active" id="users" role="tabpanel">
            <div class="d-flex justify-content-between align-items-center">
                <h4 class="mb-0">Existing Users</h4>
                <div></div>
            </div>
            <div id="usersPane" class="mt-2">
                <div class="text-muted">Loading users...</div>
            </div>
        </div>

        <div class="tab-pane" id="roles" role="tabpanel">
            <h4>Roles</h4>
            <div id="rolesPane" class="mt-2">
                <div class="text-muted">Loading roles...</div>
            </div>
        </div>

        <div class="tab-pane" id="consultants" role="tabpanel">
            <div class="d-flex justify-content-between align-items-center">
                <h4 class="mb-0">Consultants</h4>
                <button id="addConsultantBtn" class="btn btn-sm btn-secondary">Add Consultant</button>
            </div>
            <div id="consultantsPane" class="mt-2">
                <div class="text-muted">Loading consultants...</div>
            </div>
        </div>
    </div>
</div>

<!-- Modals -->
<!-- User Modal -->
<div class="modal fade dashboard-modal" id="userModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content bg-light border-primary">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="userModalTitle">Add User</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="userForm" enctype="multipart/form-data">
                    <input type="hidden" name="id" id="user_id">
                    <div class="row">
                        <div class="col-md-6 mb-2">
                            <label class="form-label">Full name</label>
                            <input id="name" name="name" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-2">
                            <label class="form-label">Username</label>
                            <input id="username" name="username" class="form-control" required>
                        </div>
                    </div>
                    <div class="row mt-2">
                        <div class="col-md-4 mb-2">
                            <label class="form-label">Password</label>
                            <input id="password" name="password" class="form-control" type="password">
                        </div>
                        <div class="col-md-4 mb-2">
                            <label class="form-label">Role</label>
                            <select id="role_id" name="role_id" class="form-select">
                                <?php foreach($roles as $role): ?>
                                    <option value="<?php echo $role['id']; ?>"><?php echo htmlspecialchars($role['role_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4 mb-2">
                            <label class="form-label">Status</label>
                            <select id="status" name="status" class="form-select">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>
                    <div class="row mt-2">
                        <div class="col-md-6 mb-2">
                            <label class="form-label">Email</label>
                            <input id="email" name="email" type="email" class="form-control">
                        </div>
                        <div class="col-md-6 mb-2">
                            <label class="form-label">Mobile</label>
                            <input id="mobile" name="mobile" class="form-control">
                        </div>
                    </div>
                    <div class="row mt-2">
                        <div class="col-md-6 mb-2">
                            <label class="form-label">Date of Joining</label>
                            <input id="doj" name="doj" type="date" class="form-control">
                        </div>
                        <div class="col-md-6 mb-2">
                            <label class="form-label">Date of Birth</label>
                            <input id="dob" name="dob" type="date" class="form-control">
                        </div>
                    </div>
                    <div class="row mt-2">
                        <div class="col-md-6 mb-2">
                            <label class="form-label">Description</label>
                            <textarea id="description" name="description" class="form-control" rows="2"></textarea>
                        </div>
                        <div class="col-md-6 mb-2">
                            <label class="form-label">Photo</label>
                            <input id="photo" name="photo" type="file" class="form-control">
                        </div>
                    </div>
                    <div class="row mt-2">
                        <div class="col-md-12 mb-2">
                            <label class="form-label">Specialization (if consultant)</label>
                            <input id="specialization" name="specialization" class="form-control">
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button id="saveUserBtn" type="button" class="btn btn-primary">Save</button>
            </div>
        </div>
    </div>
</div>

<!-- Role Modal -->
<div class="modal fade" id="roleModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Role</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="roleForm">
                        <div class="mb-2">
                                <label class="form-label">Role name</label>
                                <input name="role_name" id="role_name" class="form-control" required>
                        </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button id="saveRoleBtn" type="button" class="btn btn-primary">Save Role</button>
            </div>
        </div>
    </div>
</div>

<!-- Consultant Modal -->
<div class="modal fade" id="consultantModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add/Edit Consultant</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="consultantForm">
                        <div class="mb-2">
                                <label class="form-label">User</label>
                                <select name="user_id" id="consultant_user_id" class="form-select">
                                        <option value="">-- select user --</option>
                                        <?php foreach($users as $uu): ?>
                                                    <option value="<?php echo $uu['id']; ?>"><?php echo htmlspecialchars($uu['name'] . ' (' . ($uu['username'] ?? '') . ') [' . ($uu['status'] ?? 'active') . ']'); ?></option>
                                        <?php endforeach; ?>
                                </select>
                        </div>
                        <div class="mb-2">
                                <label class="form-label">Specialization/Position</label>
                                <input name="specialization" id="consultant_specialization" class="form-control" required>
                        </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button id="saveConsultantBtn" type="button" class="btn btn-primary">Save</button>
            </div>
        </div>
    </div>
</div>

<!-- Notification system is included via notification_js.php -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
/* Settings page JS
   - uses settings_action.php for AJAX saves & deletes
   - This JS mirrors what you provided but uses robust fragment loading and error handling
*/

// Lazily initialize Bootstrap modals
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

// State for users table sorting/filtering
let userSortField = 'profile';
let userSortDir = 'asc';
let userFilters = { name: '', mobile: '', doj: '' };

// ---------- INITIAL LOAD + Tab handlers ----------
document.addEventListener('DOMContentLoaded', () => {
    loadUsersPane(); // default
    const usersTab = document.getElementById('users-tab'); if (usersTab) usersTab.addEventListener('click', loadUsersPane);
    const rolesTab = document.getElementById('roles-tab'); if (rolesTab) rolesTab.addEventListener('click', loadRolesPane);
    const consultantsTab = document.getElementById('consultants-tab'); if (consultantsTab) consultantsTab.addEventListener('click', loadConsultantsPane);
});

// Robust fragment fetcher that detects unexpected full-page HTML and reports
async function fetchFragmentSafe(url) {
    const res = await fetch(url, { credentials: 'same-origin' });
    const txt = await res.text();
    // If it looks like a full HTML document, throw so caller can handle
    if (txt.trim().startsWith('<!DOCTYPE') || txt.trim().startsWith('<html') || /<head[\s>]/i.test(txt) || /<body[\s>]/i.test(txt)) {
        console.error('Fragment returned HTML instead of fragment:', url);
        throw new Error('Invalid fragment response');
    }
    return txt;
}

// ---------- Loaders ----------
async function loadUsersPane() {
    const pane = document.getElementById('usersPane');
    if (!pane) return;

    // refresh filter values from current inputs if they exist
    const nameInput = document.getElementById('userFilterName');
    const mobileInput = document.getElementById('userFilterMobile');
    const dojInput = document.getElementById('userFilterDoj');
    if (nameInput) userFilters.name = nameInput.value.trim();
    if (mobileInput) userFilters.mobile = mobileInput.value.trim();
    if (dojInput) userFilters.doj = dojInput.value.trim();

    pane.innerHTML = '<div class="text-muted">Loading users...</div>';
    const params = new URLSearchParams({
        usersFragment: '1',
        sortField: userSortField,
        sortDir: userSortDir,
        filterName: userFilters.name,
        filterMobile: userFilters.mobile,
        filterDoj: userFilters.doj
    });

    try {
        const rows = await fetchFragmentSafe('settings.php?' + params.toString());
        renderUsersPaneContent(pane, rows);
        bindUserButtons();
        bindUserFilterControls();
    } catch (err) {
        pane.innerHTML = '<div class="text-danger">Failed to load users.</div>';
    }
}

async function loadRolesPane() {
    const el = document.getElementById('rolesPane');
    el.innerHTML = '<div class="text-muted">Loading roles...</div>';
    try {
        const html = await fetchFragmentSafe('settings.php?rolesFragment=1');
        el.innerHTML = html;
        bindRoleButtons();
        document.querySelectorAll('.role-block').forEach(rb => {
            const t = rb.querySelector('.role-toggle');
            const c = rb.querySelector('.role-contents');
            const h = rb.querySelector('.role-header');
            if (t && c && h) {
                t.setAttribute('aria-expanded','false');
                t.innerHTML = '<i class="fa fa-chevron-down"></i>';
                c.classList.add('collapsed');
                c.style.maxHeight = '0px';
                h.classList.remove('expanded');
                h.classList.add('collapsed');
            }
        });
    } catch (err) {
        el.innerHTML = '<div class="text-danger">Failed to load roles.</div>';
    }
}

async function loadConsultantsPane() {
    const el = document.getElementById('consultantsPane');
    el.innerHTML = '<div class="text-muted">Loading consultants...</div>';
    try {
        const html = await fetchFragmentSafe('settings.php?consultantsFragment=1');
        el.innerHTML = html;
        bindConsultantButtons();
    } catch (err) {
        el.innerHTML = '<div class="text-danger">Failed to load consultants.</div>';
    }
}

// ---------- Bind buttons after DOM fragment load ----------
function bindUserButtons() {
    document.querySelectorAll('.editUserBtn').forEach(btn => {
        btn.onclick = async () => {
            const id = btn.dataset.id;
            try {
                const res = await fetch('settings.php?editJson=' + encodeURIComponent(id));
                const data = await res.json();
                if (data.status === 'ok') {
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
                    const um = getUserModal(); if (um) um.show();
                } else {
                    showToast('Failed to load user', false);
                }
            } catch (e) {
                showToast('Error fetching user details', false);
            }
        };
    });

    // Add user button
    const addUserBtn = document.getElementById('addUserBtn');
    if (addUserBtn) {
        addUserBtn.onclick = () => {
            document.getElementById('userModalTitle').innerText = 'Add User';
            document.getElementById('userForm').reset();
            document.getElementById('user_id').value = '';
            document.getElementById('username').readOnly = false;
            const um = getUserModal(); if (um) um.show();
        };
    }

    // Save user
    const saveUserBtn = document.getElementById('saveUserBtn');
    if (saveUserBtn) {
        saveUserBtn.onclick = async () => {
            const form = document.getElementById('userForm');
            const userId = document.getElementById('user_id').value;
            const isEdit = userId !== '';
            const confirmMessage = isEdit ? 'Are you sure you want to update this user?' : 'Are you sure you want to add this new user?';

            if (typeof showConfirmModal === 'function') {
                showConfirmModal(confirmMessage, async () => { await saveUserData(form); });
            } else {
                if (!confirm(confirmMessage)) return;
                await saveUserData(form);
            }
        };
    }

    async function saveUserData(form) {
        const fd = new FormData(form);
        try {
            const res = await fetch('settings_action.php?action=save_user', { method: 'POST', body: fd });
            const j = await res.json();
            if (j.status === 'success') {
                const um = getUserModal(); if (um) um.hide();
                if (typeof showSuccessModal === 'function') showSuccessModal(j.message || 'User saved successfully'); else showToast(j.message || 'User saved');
                // Reload both users and consultants tables so any changes
                // (including consultant-related fields like specialization)
                // are immediately reflected across tabs.
                await loadUsersPane();
                if (typeof loadConsultantsPane === 'function') {
                    await loadConsultantsPane();
                }
            } else {
                if (typeof showErrorModal === 'function') showErrorModal(j.message || 'Error saving user'); else showToast(j.message || 'Error saving user', false);
            }
        } catch (e) {
            showToast('Network error saving user', false);
        }
    }
}

function bindRoleButtons() {
    const addRoleLocal = document.getElementById('addRoleBtnLocal');
    if (addRoleLocal) addRoleLocal.onclick = () => { const rm = getRoleModal(); if (rm) rm.show(); };

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

    document.getElementById('saveRoleBtn').onclick = async () => {
        const name = document.getElementById('role_name').value.trim();
        if (!name) { showToast('Role name required', false); return; }
        const confirmMsg = 'Are you sure you want to add this role?';
        if (typeof showConfirmModal === 'function') {
            showConfirmModal(confirmMsg, async () => { await saveRoleData(name); });
        } else {
            if (!confirm(confirmMsg)) return;
            await saveRoleData(name);
        }
    };

    async function saveRoleData(name) {
        const fd = new FormData();
        fd.append('role_name', name);
        try {
            const res = await fetch('settings_action.php?action=save_role', { method: 'POST', body: fd });
            const j = await res.json();
            if (j.status === 'success') {
                const rm = getRoleModal(); if (rm) rm.hide();
                if (typeof showSuccessModal === 'function') showSuccessModal('Role added successfully'); else showToast('Role added');
                document.getElementById('role_name').value = '';
                await loadRolesPane();
            } else {
                showToast(j.message || 'Error saving role', false);
            }
        } catch (e) {
            showToast('Network error saving role', false);
        }
    }

    document.querySelectorAll('.deleteRoleBtn').forEach(btn => {
        btn.onclick = async () => {
            const id = btn.dataset.id;
            const confirmMsg = 'Are you sure you want to delete this role?';
            if (typeof showConfirmModal === 'function') {
                showConfirmModal(confirmMsg, async () => { await deleteRoleData(id); });
                return;
            }
            if (!confirm(confirmMsg)) return;
            await deleteRoleData(id);
        };
    });

    async function deleteRoleData(id) {
        const fd = new FormData(); fd.append('id', id);
        try {
            const res = await fetch('settings_action.php?action=delete_role', { method: 'POST', body: fd });
            const j = await res.json();
            if (j.status === 'success') {
                if (typeof showSuccessModal === 'function') showSuccessModal('Role deleted successfully'); else showToast('Role deleted');
                await loadRolesPane();
            } else {
                showToast(j.message || 'Error deleting role', false);
            }
        } catch (e) {
            showToast('Network error deleting role', false);
        }
    }

    document.querySelectorAll('.editRoleBtn').forEach(btn => {
        btn.onclick = async () => {
            const id = btn.dataset.id;
            const name = prompt('New role name:');
            if (!name) return;
            const fd = new FormData(); fd.append('id', id); fd.append('role_name', name);
            try {
                const res = await fetch('settings_action.php?action=save_role', { method: 'POST', body: fd });
                const j = await res.json();
                if (j.status === 'success') {
                    if (typeof showSuccessModal === 'function') showSuccessModal('Role updated successfully'); else showToast('Role updated');
                    await loadRolesPane();
                } else {
                    showToast(j.message || 'Error updating role', false);
                }
            } catch(e) {
                showToast('Network error updating role', false);
            }
        };
    });
}

function renderUsersPaneContent(container, rowsHTML) {
    container.innerHTML = `
        <form id="userFilterForm" class="user-filter-inline mb-3">
            <div class="filter-field">
                <label class="form-label">Profile</label>
                <input type="text" class="form-control form-control-sm" id="userFilterName" placeholder="Search profile..." value="${userFilters.name || ''}">
            </div>
            <div class="filter-field">
                <label class="form-label">Mobile</label>
                <input type="text" class="form-control form-control-sm" id="userFilterMobile" placeholder="Search mobile..." value="${userFilters.mobile || ''}">
            </div>
            <div class="filter-field filter-field--short">
                <label class="form-label">Date of Joining</label>
                <input type="date" class="form-control form-control-sm" id="userFilterDoj" value="${userFilters.doj || ''}">
            </div>
            <div class="filter-actions">
                <button type="submit" class="btn btn-primary btn-sm me-2"><i class="fas fa-filter"></i> Apply</button>
                <button type="button" class="btn btn-outline-secondary btn-sm" id="resetUserFilters"><i class="fas fa-undo"></i> Reset</button>
            </div>
        </form>
        <div class="table-responsive">
            <table class="table table-striped table-hover table-sm" id="usersTable">
                <thead class="table-light">
                    <tr>
                        <th>Profile</th>
                        <th>
                            <button type="button" class="btn btn-link p-0 user-sort-btn" data-field="username">
                                Username <span class="sort-icon" data-field="username">${getUserSortIcon('username')}</span>
                            </button>
                        </th>
                        <th>
                            <button type="button" class="btn btn-link p-0 user-sort-btn" data-field="role">
                                Role <span class="sort-icon" data-field="role">${getUserSortIcon('role')}</span>
                            </button>
                        </th>
                        <th>Email</th>
                        <th>Mobile</th>
                        <th>DOJ</th>
                        <th>DOB</th>
                        <th>Description</th>
                        <th>Status</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>${rowsHTML}</tbody>
            </table>
        </div>
    `;
}

function bindUserFilterControls() {
    const form = document.getElementById('userFilterForm');
    const nameInput = document.getElementById('userFilterName');
    const mobileInput = document.getElementById('userFilterMobile');
    const dojInput = document.getElementById('userFilterDoj');
    if (nameInput) nameInput.value = userFilters.name || '';
    if (mobileInput) mobileInput.value = userFilters.mobile || '';
    if (dojInput) dojInput.value = userFilters.doj || '';

    if (form) {
        form.addEventListener('submit', (e) => {
            e.preventDefault();
            userFilters.name = nameInput ? nameInput.value.trim() : '';
            userFilters.mobile = mobileInput ? mobileInput.value.trim() : '';
            userFilters.doj = dojInput ? dojInput.value.trim() : '';
            loadUsersPane();
        });
    }

    const resetBtn = document.getElementById('resetUserFilters');
    if (resetBtn) {
        resetBtn.addEventListener('click', () => {
            userFilters = { name: '', mobile: '', doj: '' };
            loadUsersPane();
        });
    }

    document.querySelectorAll('.user-sort-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const field = btn.dataset.field;
            if (!field) return;
            if (userSortField === field) {
                userSortDir = userSortDir === 'asc' ? 'desc' : 'asc';
            } else {
                userSortField = field;
                userSortDir = 'asc';
            }
            loadUsersPane();
        });
    });
}

function getUserSortIcon(field) {
    if (userSortField !== field) return '⇅';
    return userSortDir === 'asc' ? '↑' : '↓';
}

function bindConsultantButtons() {
    const addConsultantBtn = document.getElementById('addConsultantBtn');
    if (addConsultantBtn) {
        addConsultantBtn.onclick = () => {
            document.getElementById('consultantForm').reset();
            const cm = getConsultantModal(); if (cm) cm.show();
        };
    }

    const saveConsultantBtn = document.getElementById('saveConsultantBtn');
    if (saveConsultantBtn) {
        saveConsultantBtn.onclick = async () => {
            const consultantUserId = document.getElementById('consultant_user_id').value;
            const confirmMessage = consultantUserId ? 'Are you sure you want to update this consultant?' : 'Are you sure you want to add this consultant?';
            if (typeof showConfirmModal === 'function') {
                showConfirmModal(confirmMessage, async () => { await saveConsultantData(); });
            } else {
                if (!confirm(confirmMessage)) return;
                await saveConsultantData();
            }
        };
    }

    async function saveConsultantData() {
        const fd = new FormData(document.getElementById('consultantForm'));
        try {
            const res = await fetch('settings_action.php?action=save_consultant', { method: 'POST', body: fd });
            const j = await res.json();
            if (j.status === 'success') {
                const cm = getConsultantModal(); if (cm) cm.hide();
                if (typeof showSuccessModal === 'function') showSuccessModal(j.message || 'Consultant saved successfully'); else showToast('Consultant saved');
                await loadConsultantsPane();
            } else {
                showToast(j.message || 'Error saving consultant', false);
            }
        } catch (e) {
            showToast('Network error saving consultant', false);
        }
    }

    document.querySelectorAll('.editConsultantBtn').forEach(btn => {
        btn.onclick = async () => {
            const uid = btn.dataset.userid;
            try {
                const res = await fetch('settings.php?editJson=' + encodeURIComponent(uid));
                const data = await res.json();
                if (data.status === 'ok') {
                    document.getElementById('consultant_user_id').value = data.user.id;
                    document.getElementById('consultant_specialization').value = data.consultant_specialization || '';
                    const cm = getConsultantModal(); if (cm) cm.show();
                } else {
                    showToast('Failed to load consultant details', false);
                }
            } catch (e) {
                showToast('Network error fetching consultant details', false);
            }
        };
    });
}

// ---------- Sortable Table Headers ----------
function initSortableHeaders() {
    document.querySelectorAll('.sortable-header').forEach(header => {
        header.addEventListener('click', (e) => {
            if (e.target.classList.contains('sort-icon') || e.target.parentElement.classList.contains('sortable-header')) {
                showSortMenu(header);
            }
        });
    });
}

function showSortMenu(headerEl) {
    const column = headerEl.getAttribute('data-column');
    const existingMenu = document.getElementById('sortMenu');
    if (existingMenu) existingMenu.remove();

    const menu = document.createElement('div');
    menu.id = 'sortMenu';
    menu.style.cssText = `
        position: fixed;
        background: white;
        border: 1px solid #999;
        border-radius: 4px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.2);
        z-index: 10000;
        min-width: 160px;
    `;

    const rect = headerEl.getBoundingClientRect();
    menu.style.top = (rect.bottom + 8) + 'px';
    menu.style.left = (rect.left) + 'px';

    menu.innerHTML = `
        <div style="padding: 8px 0;">
            <button style="display:block;width:100%;padding:10px 16px;border:none;background:none;text-align:left;cursor:pointer;font-size:14px;" onmouseover="this.style.background='#e8e8e8'" onmouseout="this.style.background='none'" onclick="sortTable('${column}', 'asc'); document.getElementById('sortMenu').remove();">
                ↑ Ascending
            </button>
            <button style="display:block;width:100%;padding:10px 16px;border:none;background:none;text-align:left;cursor:pointer;font-size:14px;" onmouseover="this.style.background='#e8e8e8'" onmouseout="this.style.background='none'" onclick="sortTable('${column}', 'desc'); document.getElementById('sortMenu').remove();">
                ↓ Descending
            </button>
        </div>
    `;

    document.body.appendChild(menu);

    setTimeout(() => {
        document.addEventListener('click', closeSortMenu, { once: true });
    }, 0);
}

function closeSortMenu() {
    const menu = document.getElementById('sortMenu');
    if (menu) menu.remove();
}

function sortTable(column, direction) {
    const table = document.getElementById('usersTable');
    if (!table) return;

    const tbody = table.querySelector('tbody');
    if (!tbody) return;

    const rows = Array.from(tbody.querySelectorAll('tr'));
    const columnIndex = getColumnIndex(column);

    if (columnIndex === -1) return;

    rows.sort((a, b) => {
        const aVal = a.cells[columnIndex].textContent.trim();
        const bVal = b.cells[columnIndex].textContent.trim();

        if (direction === 'asc') {
            return aVal.localeCompare(bVal);
        } else {
            return bVal.localeCompare(aVal);
        }
    });

    rows.forEach(row => tbody.appendChild(row));
}

function getColumnIndex(column) {
    const colMap = { 'avatar': 0, 'username': 1, 'role': 2 };
    return colMap[column] !== undefined ? colMap[column] : -1;
}
</script>
</body>
</html>
