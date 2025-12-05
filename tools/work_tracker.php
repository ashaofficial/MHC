<?php
include_once __DIR__ . '/../auth.php';
include_once __DIR__ . '/../secure/db.php';
include_once __DIR__ . '/../components/helpers.php';

requireLogin();
requireRole(['administrator', 'admin', 'consultant', 'receptionist']);

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$userId = (int)($USER['user_id'] ?? 0);
$userRole = strtolower(trim($USER['role'] ?? ''));

// Simple error logger for debugging save failures
function work_tracker_log($line) {
    $file = __DIR__ . '/work_tracker_error.log';
    $entry = date('Y-m-d H:i:s') . " - " . $line . PHP_EOL;
    @file_put_contents($file, $entry, FILE_APPEND | LOCK_EX);
}

// Handle AJAX: list records
if (($_GET['action'] ?? '') === 'list') {
    header('Content-Type: application/json');

    $rows = [];
    $sql = "SELECT wt.id,
                   wt.patient_id,
                   wt.patient_name,
                   wt.action_item,
                   wt.consultant_name,
                   wt.appointment_date,
                   wt.entered_by,
                   wt.edited_by,
                   wt.updated_at,
                   wt.status,
                   wt.notes,
                   u.name AS entered_by_name,
                   u2.name AS edited_by_name,
                   p.mobile_no
            FROM work_tracker wt
            LEFT JOIN users u ON u.id = wt.entered_by
            LEFT JOIN users u2 ON u2.id = wt.edited_by
            LEFT JOIN patients p ON p.id = wt.patient_id
            ORDER BY wt.appointment_date DESC, wt.id DESC";
    if ($res = $conn->query($sql)) {
        while ($r = $res->fetch_assoc()) {
            $rows[] = $r;
        }
    }

    echo json_encode(['status' => 'success', 'rows' => $rows]);
    exit;
}

// Handle AJAX: fetch single record for edit
if (($_GET['action'] ?? '') === 'get' && isset($_GET['id'])) {
    header('Content-Type: application/json');
    $id = (int)$_GET['id'];
    $stmt = $conn->prepare("SELECT id, patient_id, patient_name, action_item, consultant_name, appointment_date, status, notes FROM work_tracker WHERE id = ? LIMIT 1");
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Unable to load work item']);
        exit;
    }
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    if (!$row) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Work item not found']);
        exit;
    }
    echo json_encode(['status' => 'success', 'item' => $row]);
    exit;
}

// Handle AJAX: patient suggestions
if (($_GET['action'] ?? '') === 'patient_suggest') {
    header('Content-Type: application/json');
    $q = trim($_GET['q'] ?? '');
    if (strlen($q) < 2) {
        echo json_encode(['status' => 'success', 'items' => []]);
        exit;
    }
    $like = $q . '%';
    $stmt = $conn->prepare("SELECT id, name FROM patients WHERE name LIKE ? ORDER BY name LIMIT 10");
    if (!$stmt) {
        echo json_encode(['status' => 'error', 'items' => []]);
        exit;
    }
    $stmt->bind_param('s', $like);
    $stmt->execute();
    $res = $stmt->get_result();
    $items = [];
    while ($r = $res->fetch_assoc()) {
        $items[] = $r;
    }
    echo json_encode(['status' => 'success', 'items' => $items]);
    exit;
}

// Handle AJAX: consultant suggestions (from users table username)
if (($_GET['action'] ?? '') === 'consultant_suggest') {
    header('Content-Type: application/json');
    $q = trim($_GET['q'] ?? '');
    if (strlen($q) < 2) {
        echo json_encode(['status' => 'success', 'items' => []]);
        exit;
    }
    $like = $q . '%';
    $stmt = $conn->prepare("SELECT id, name FROM users WHERE name LIKE ? ORDER BY name LIMIT 10");
    if (!$stmt) {
        echo json_encode(['status' => 'error', 'items' => []]);
        exit;
    }
    $stmt->bind_param('s', $like);
    $stmt->execute();
    $res = $stmt->get_result();
    $items = [];
    while ($r = $res->fetch_assoc()) {
        $items[] = $r;
    }
    echo json_encode(['status' => 'success', 'items' => $items]);
    exit;
}

// Handle AJAX: get work tracker history
if (($_GET['action'] ?? '') === 'history' && isset($_GET['id'])) {
    header('Content-Type: application/json');
    $id = (int)$_GET['id'];
    $stmt = $conn->prepare("SELECT id, patient_name, action_item, consultant_name, appointment_date, status, notes, edited_by, edited_at FROM work_tracker_history WHERE work_tracker_id = ? ORDER BY edited_at DESC");
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Unable to load history']);
        exit;
    }
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $res = $stmt->get_result();
    $history = [];
    while ($row = $res->fetch_assoc()) {
        // Get edited_by username
        $userStmt = $conn->prepare("SELECT name FROM users WHERE id = ?");
        if ($userStmt) {
            $editedByName = '-';
            if ($row['edited_by']) {
                $userStmt->bind_param('i', $row['edited_by']);
                $userStmt->execute();
                $userRes = $userStmt->get_result();
                if ($userRow = $userRes->fetch_assoc()) {
                    $editedByName = $userRow['name'];
                }
            }
            $row['edited_by_name'] = $editedByName;
            $userStmt->close();
        }
        $history[] = $row;
    }
    $stmt->close();
    echo json_encode(['status' => 'success', 'history' => $history]);
    exit;
}


// Handle AJAX: save record (add or update)
if (($_GET['action'] ?? '') === 'save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    $id = isset($_POST['id']) && $_POST['id'] !== '' ? (int)$_POST['id'] : 0;
    $patientId = isset($_POST['patient_id']) && $_POST['patient_id'] !== '' ? (int)$_POST['patient_id'] : null;
    $patientName = trim($_POST['patient_name'] ?? '');
    $actionItem = trim($_POST['action_item'] ?? '');
    $consultantName = trim($_POST['consultant_name'] ?? '');
    $appointmentRaw = trim($_POST['appointment_date'] ?? '');
    $statusRaw = trim($_POST['status'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    $allowedActions = ['Pre-case-taking', 'case-taking', 'Follow-up', 'team-discussion'];
    if (!in_array($actionItem, $allowedActions, true)) {
        $actionItem = 'Pre-case-taking';
    }

    // Status handling: open, medicine_ongoing, case_to_be_taken, closed
    $allowedStatuses = ['open', 'medicine_ongoing', 'case_to_be_taken', 'closed'];
    if (!in_array($statusRaw, $allowedStatuses, true)) {
        $statusRaw = 'open'; // default for new items
    }
    $status = $statusRaw;

    if ($patientName === '' || $consultantName === '' || $appointmentRaw === '') {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Patient name, consultant name and appointment date are required.']);
        exit;
    }

    $appointmentTs = strtotime($appointmentRaw);
    if ($appointmentTs === false) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Invalid appointment date.']);
        exit;
    }
    $appointmentDate = date('Y-m-d H:i:s', $appointmentTs);

    // For all roles: block new work if any open work exists for this patient
    if ($id === 0) {
        $hasOpen = false;
        if ($patientId) {
            $dupStmt = $conn->prepare("SELECT id FROM work_tracker WHERE patient_id = ? AND status = 'open'");
            if ($dupStmt) {
                $dupStmt->bind_param('i', $patientId);
                $dupStmt->execute();
                $dupRes = $dupStmt->get_result();
                if ($dupRes && $dupRes->num_rows > 0) {
                    $hasOpen = true;
                }
                $dupStmt->close();
            }
        } else {
            $dupStmt = $conn->prepare("SELECT id FROM work_tracker WHERE patient_name = ? AND status = 'open'");
            if ($dupStmt) {
                $dupStmt->bind_param('s', $patientName);
                $dupStmt->execute();
                $dupRes = $dupStmt->get_result();
                if ($dupRes && $dupRes->num_rows > 0) {
                    $hasOpen = true;
                }
                $dupStmt->close();
            }
        }
        if ($hasOpen) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'This patient already has an open work item. Please close the previous work before adding a new one.']);
            exit;
        }
    }

    // For any new record, enforce default status = 'open' server-side regardless of posted value
    if ($id === 0) {
        $status = 'open';
    }

    if ($id > 0) {
        // Store old values before update for history
        $oldStmt = $conn->prepare("SELECT patient_id, patient_name, action_item, consultant_name, appointment_date, status, notes FROM work_tracker WHERE id = ?");
        $oldStmt->bind_param('i', $id);
        $oldStmt->execute();
        $oldRes = $oldStmt->get_result();
        $oldData = $oldRes->fetch_assoc();
        $oldStmt->close();

        // Update main record
        $stmt = $conn->prepare("UPDATE work_tracker
            SET patient_id = ?, patient_name = ?, action_item = ?, consultant_name = ?, appointment_date = ?, status = ?, notes = ?, edited_by = ?, updated_at = NOW()
            WHERE id = ?");
        if (!$stmt) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Unable to prepare update']);
            exit;
        }
        $stmt->bind_param(
            'issssssii',
            $patientId,
            $patientName,
            $actionItem,
            $consultantName,
            $appointmentDate,
            $status,
            $notes,
            $userId,
            $id
        );

        if (!$stmt->execute()) {
            work_tracker_log("UPDATE failed: stmt_error=" . ($stmt->error ?? '') . "; conn_error=" . ($conn->error ?? ''));
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Unable to save record']);
            exit;
        }
        $stmt->close();

        // Insert history record if data changed
        if ($oldData && ($oldData['patient_name'] !== $patientName || $oldData['action_item'] !== $actionItem || $oldData['consultant_name'] !== $consultantName || $oldData['status'] !== $status || $oldData['notes'] !== $notes)) {
            $histStmt = $conn->prepare("INSERT INTO work_tracker_history
                (work_tracker_id, patient_id, patient_name, action_item, consultant_name, appointment_date, status, edited_by, notes)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            if ($histStmt) {
                $histStmt->bind_param(
                    'iisssssis',
                    $id,
                    $patientId,
                    $patientName,
                    $actionItem,
                    $consultantName,
                    $appointmentDate,
                    $status,
                    $userId,
                    $notes
                );
                $histStmt->execute();
                $histStmt->close();
            }
        }
    } else {
        $stmt = $conn->prepare("INSERT INTO work_tracker
            (patient_id, patient_name, action_item, consultant_name, appointment_date, status, entered_by, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        if (!$stmt) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Unable to prepare insert']);
            exit;
        }
        $stmt->bind_param(
            'isssssis',
            $patientId,
            $patientName,
            $actionItem,
            $consultantName,
            $appointmentDate,
            $status,
            $userId,
            $notes
        );

        if (!$stmt->execute()) {
            work_tracker_log("INSERT failed: stmt_error=" . ($stmt->error ?? '') . "; conn_error=" . ($conn->error ?? '') . "; POST=" . json_encode(array_intersect_key($_POST, array_flip(['patient_id','patient_name','action_item','consultant_name','appointment_date','status']))));
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Unable to save record']);
            exit;
        }
        $stmt->close();
    }

    echo json_encode(['status' => 'success', 'message' => 'Work item saved']);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Work Tracker</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/common.css">
    <link rel="stylesheet" href="../css/dashboard.css">
    <style>
        #patientSuggestions,
        #consultantSuggestions {
            border: 2px solid #ccc;
            border-radius: 0.25rem;
            background-color: #fff;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        /* Highlight fields that were changed in an edit history entry */
        .edited-field {
            background-color: #d5d3d3ff;
            color: black;
            padding: 4px 6px;
            border-radius: 4px;
            display: inline-block;
        }
    </style>
</head>
<body class="p-3" data-user-role="<?php echo htmlspecialchars($userRole); ?>">
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="mb-0">Work Tracker</h3>
        <button id="addWorkBtn" class="btn btn-sm btn-success">
            <i class="fas fa-plus"></i> Add Work
        </button>
    </div>

    <div class="card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive" style="overflow-x:auto;">
                <table class="table table-striped table-hover table-sm mb-0 work-tracker-table" style="min-width:<?php echo $userRole === 'receptionist' ? '800px' : '1200px'; ?>;">
                    <thead class="table-light">
                        <tr>
                            <th>Patient ID</th>
                            <th>Patient Name</th>
                            <th>Action Item</th>
                            <th>Consultant Name</th>
                            <th>Appointment Date</th>
                            <th>Status</th>
                            <?php if ($userRole !== 'receptionist'): ?>
                            <th>Entered By</th>
                            <th>Edited By</th>
                            <th>Edited At</th>
                            <th>Notes</th>
                            <?php endif; ?>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody id="workTrackerBody">
                        <tr><td colspan="<?php echo $userRole === 'receptionist' ? '7' : '12'; ?>" class="text-muted text-center">Loading...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Work Tracker Modal -->
<div class="modal fade" id="workTrackerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Work Item</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="workTrackerForm">
                    <input type="hidden" name="id" id="work_id">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Patient ID</label>
                            <input type="number" class="form-control" name="patient_id" min="0" step="1">
                        </div>
                        <div class="col-md-5 position-relative">
                            <label class="form-label">Patient Name<span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="patient_name" id="patient_name" autocomplete="off" required>
                            <div class="list-group position-absolute w-100 d-none" id="patientSuggestions" style="z-index: 1056; max-height: 200px; overflow-y: auto;"></div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Action Item<span class="text-danger">*</span></label>
                            <select class="form-select" name="action_item" required>
                                <option value="Pre-case-taking">Pre-case taking</option>
                                <option value="case-taking">Case taking</option>
                                <option value="Follow-up">Follow-up</option>
                                <option value="team-discussion">Team discussion</option>
                            </select>
                        </div>
                        <div class="col-md-6 position-relative">
                            <label class="form-label">Consultant Name<span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="consultant_name" id="consultant_name" autocomplete="off" required>
                            <div class="list-group position-absolute w-100 d-none" id="consultantSuggestions" style="z-index: 1056; max-height: 200px; overflow-y: auto;"></div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Appointment Date<span class="text-danger">*</span></label>
                            <input type="datetime-local" class="form-control" name="appointment_date" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Status<span class="text-danger">*</span></label>
                            <select class="form-select" name="status" id="statusField" required>
                                <option value="open">Open</option>
                                <option value="medicine_ongoing">Medicine Ongoing</option>
                                <option value="case_to_be_taken">Case to be Taken</option>
                                <option value="closed">Closed</option>
                            </select>
                        </div>
                        <div class="col-12" id="notesField">
                            <label class="form-label">Notes</label>
                            <textarea class="form-control" name="notes" rows="3"></textarea>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="saveWorkBtn">Save</button>
            </div>
        </div>
    </div>
</div>

<!-- Edit History Modal -->
<div class="modal fade" id="editHistoryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit History</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="historyContent" style="max-height: 500px; overflow-y: auto;">
                    <p class="text-muted">Loading...</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<?php
// Shared confirmation/success/error modals + toast helpers
include_once __DIR__ . '/../components/modal-dialogs.php';
include_once __DIR__ . '/../components/notification_js.php';
?>
<script>
let workTrackerModal;

document.addEventListener('DOMContentLoaded', () => {
    const modalEl = document.getElementById('workTrackerModal');
    workTrackerModal = new bootstrap.Modal(modalEl);

    document.getElementById('addWorkBtn').addEventListener('click', () => {
        document.getElementById('workTrackerForm').reset();
        document.getElementById('work_id').value = '';
            updateStatusOptions();
        workTrackerModal.show();
    });

    document.getElementById('saveWorkBtn').addEventListener('click', saveWorkItem);

    initTypeahead('patient_name', 'patientSuggestions', 'patient_suggest');
    initTypeahead('consultant_name', 'consultantSuggestions', 'consultant_suggest');

    // Hide Status and Notes fields for receptionist
    const isReceptionist = document.body.dataset.userRole === 'receptionist';
    if (isReceptionist) {
        const statusField = document.getElementById('statusField');
        const statusFieldCol = statusField.closest('.col-md-6');
        const notesField = document.getElementById('notesField');
        if (statusFieldCol) statusFieldCol.style.display = 'none';
        if (notesField) notesField.style.display = 'none';
        // Remove required attribute for status field
        if (statusField) statusField.removeAttribute('required');
    }

    loadWorkTracker();
     // For admin/consultant users, poll periodically so receptionist additions appear without manual refresh
    if (!isReceptionist) {
        const WORK_TRACKER_POLL_MS = 10000; // 10 seconds
        const workTrackerPoll = setInterval(() => {
            loadWorkTracker();
        }, WORK_TRACKER_POLL_MS);
        // Clear interval when page unloads
        window.addEventListener('beforeunload', () => clearInterval(workTrackerPoll));
    }

    // Listen for storage events from other tabs/windows to refresh immediately
    window.addEventListener('storage', (e) => {
        if (e.key === 'work_tracker_updated') {
            loadWorkTracker();
        }
    });
});

function updateStatusOptions() {
    const statusField = document.getElementById('statusField');
    const workId = document.getElementById('work_id').value;
    const userRole = document.body.dataset.userRole;
    
    statusField.innerHTML = ''; // clear options
    
    if (userRole === 'receptionist') {
        // Receptionists can only set status to "open" for new items
        if (!workId) {
            const opt = document.createElement('option');
            opt.value = 'open';
            opt.textContent = 'Open';
            statusField.appendChild(opt);
            statusField.value = 'open';
        } else {
            // When editing, receptionists can only see current status (read-only or show only current)
            const currentStatus = document.querySelector('[name="status"]').value || 'open';
            const opt = document.createElement('option');
            opt.value = currentStatus;
            opt.textContent = currentStatus.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
            statusField.appendChild(opt);
        }
    } else {
        // Admin/Consultant can set all statuses and change them
        const options = [
            { value: 'open', text: 'Open' },
            { value: 'medicine_ongoing', text: 'Medicine Ongoing' },
            { value: 'case_to_be_taken', text: 'Case to be Taken' },
            { value: 'closed', text: 'Closed' }
        ];
        options.forEach(({ value, text }) => {
            const opt = document.createElement('option');
            opt.value = value;
            opt.textContent = text;
            statusField.appendChild(opt);
        });
        // Default new items to 'open'
        if (!workId) {
            statusField.value = 'open';
        }
    }
}

async function loadWorkTracker() {
    const tbody = document.getElementById('workTrackerBody');
    tbody.innerHTML = '<tr><td colspan="8" class="text-muted text-center">Loading...</td></tr>';
    try {
        const res = await fetch('work_tracker.php?action=list', { credentials: 'same-origin' });
        const data = await res.json();
        if (data.status !== 'success') {
            tbody.innerHTML = '<tr><td colspan="7" class="text-danger text-center">Unable to load data.</td></tr>';
            return;
        }
        renderWorkRows(data.rows || []);
    } catch (e) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-danger text-center">Error loading data.</td></tr>';
    }
}

function renderWorkRows(rows) {
    const tbody = document.getElementById('workTrackerBody');
    const isReceptionist = document.body.dataset.userRole === 'receptionist';
    
    if (!rows.length) {
        tbody.innerHTML = `<tr><td colspan="${isReceptionist ? '7' : '12'}" class="text-muted text-center">No work items found.</td></tr>`;
        return;
    }

    const out = rows.map(r => {
        const appDate = r.appointment_date ? formatDateTime(r.appointment_date) : '-';
        const enteredBy = r.entered_by_name || '-';
        const editedBy = r.edited_by_name || '-';
        const editedAt = r.updated_at ? format12HourDateTime(r.updated_at) : '-';
        const status = r.status ? r.status.replace(/_/g, ' ') : '-';
        const notes = r.notes ? escapeHtml(r.notes) : '';
        
        // Format status with badge color
        let statusBadge = 'bg-secondary';
        if (r.status === 'open') statusBadge = 'bg-primary';
        else if (r.status === 'medicine_ongoing') statusBadge = 'bg-info';
        else if (r.status === 'case_to_be_taken') statusBadge = 'bg-warning';
        else if (r.status === 'closed') statusBadge = 'bg-success';
        
        const statusLabel = r.status ? r.status.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase()) : '-';
        const isClosed = r.status === 'closed';
        const editDisabled = isClosed ? 'disabled' : '';
        const editBtn = `<button type="button" class="btn btn-sm btn-outline-primary ${editDisabled}" onclick="${!isClosed ? `editWorkItem(${r.id})` : ''}" ${editDisabled ? 'title="Cannot edit closed work items"' : ''}>
            Edit
        </button>`;
        
        if (isReceptionist) {
            // Receptionist view: only basic columns
            const mobileNo = r.mobile_no ? `<small class="text-muted d-block">${escapeHtml(r.mobile_no)}</small>` : '';
            return `
                <tr data-id="${r.id}">
                    <td>${r.patient_id ? r.patient_id : ''}</td>
                    <td>${escapeHtml(r.patient_name || '')}${mobileNo}</td>
                    <td>${escapeHtml(r.action_item || '')}</td>
                    <td>${escapeHtml(r.consultant_name || '')}</td>
                    <td>${appDate}</td>
                    <td><span class="badge ${statusBadge}">${escapeHtml(statusLabel)}</span></td>
                    <td class="text-center">
                        ${editBtn}
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="showEditHistory(${r.id})">
                            History
                        </button>
                    </td>
                </tr>
            `;
        } else {
            // Admin/Consultant view: all columns
            const mobileNo = r.mobile_no ? `<small class="text-muted d-block">${escapeHtml(r.mobile_no)}</small>` : '';
            return `
                <tr data-id="${r.id}">
                    <td>${r.patient_id ? r.patient_id : ''}</td>
                    <td>${escapeHtml(r.patient_name || '')}${mobileNo}</td>
                    <td>${escapeHtml(r.action_item || '')}</td>
                    <td>${escapeHtml(r.consultant_name || '')}</td>
                    <td>${appDate}</td>
                    <td><span class="badge ${statusBadge}">${escapeHtml(statusLabel)}</span></td>
                    <td>${escapeHtml(enteredBy)}</td>
                    <td>${escapeHtml(editedBy)}</td>
                    <td>${editedAt}</td>
                    <td>${notes}</td>
                    <td class="text-center">
                        ${editBtn}
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="showEditHistory(${r.id})">
                            History
                        </button>
                    </td>
                </tr>
            `;
        }
    }).join('');

    tbody.innerHTML = out;
}

async function saveWorkItem() {
    const form = document.getElementById('workTrackerForm');
    if (!form.reportValidity()) {
        return;
    }
    const fd = new FormData(form);
    const doSave = async () => {
        try {
            const res = await fetch('work_tracker.php?action=save', {
                method: 'POST',
                body: fd,
                credentials: 'same-origin'
            });
            const data = await res.json();
            if (data.status === 'success') {
                if (workTrackerModal) workTrackerModal.hide();
                if (typeof showSuccessModal === 'function') {
                    showSuccessModal(data.message || 'Work item saved successfully');
                }
                await loadWorkTracker();
                try {
                    // notify other open tabs/windows to refresh their work tracker
                    localStorage.setItem('work_tracker_updated', Date.now().toString());
                } catch (e) {
                    // ignore storage errors
                }
            } else {
                if (typeof showErrorModal === 'function') {
                    showErrorModal(data.message || 'Unable to save work item.');
                } else {
                    alert(data.message || 'Unable to save work item.');
                }
            }
        } catch (e) {
            if (typeof showErrorModal === 'function') {
                showErrorModal('Network error while saving.');
            } else {
                alert('Network error while saving.');
            }
        }
    };

    const id = document.getElementById('work_id').value;
    const msg = id ? 'Are you sure you want to update this work item?' : 'Are you sure you want to add this work item?';
    if (typeof showConfirmModal === 'function') {
        showConfirmModal(msg, doSave, null, id ? 'Update Work' : 'Add Work');
    } else if (confirm(msg)) {
        doSave();
    }
}

async function editWorkItem(id) {
    try {
        const res = await fetch('work_tracker.php?action=get&id=' + encodeURIComponent(id), { credentials: 'same-origin' });
        const data = await res.json();
        if (data.status !== 'success' || !data.item) {
            if (typeof showErrorModal === 'function') {
                showErrorModal(data.message || 'Unable to load work item.');
            } else {
                alert(data.message || 'Unable to load work item.');
            }
            return;
        }
        const item = data.item;
        document.getElementById('work_id').value = item.id;
        document.querySelector('[name="patient_id"]').value = item.patient_id ?? '';
        document.querySelector('[name="patient_name"]').value = item.patient_name ?? '';
        document.querySelector('[name="action_item"]').value = item.action_item ?? 'Pre-case-taking';
        document.querySelector('[name="consultant_name"]').value = item.consultant_name ?? '';
        document.querySelector('[name="appointment_date"]').value = item.appointment_date ? item.appointment_date.replace(' ', 'T').slice(0,16) : '';
        document.querySelector('[name="status"]').value = item.status ?? 'open';
        document.querySelector('[name="notes"]').value = item.notes ?? '';
        updateStatusOptions();
        workTrackerModal.show();
    } catch (e) {
        if (typeof showErrorModal === 'function') {
            showErrorModal('Network error while loading work item.');
        } else {
            alert('Network error while loading work item.');
        }
    }
}

async function showEditHistory(id) {
    const historyModal = new bootstrap.Modal(document.getElementById('editHistoryModal'));
    const historyContent = document.getElementById('historyContent');
    historyContent.innerHTML = '<p class="text-muted">Loading...</p>';
    
    try {
        const res = await fetch('work_tracker.php?action=history&id=' + encodeURIComponent(id), { credentials: 'same-origin' });
        const data = await res.json();
        
        if (data.status !== 'success' || !Array.isArray(data.history)) {
            historyContent.innerHTML = '<p class="text-danger">Unable to load history.</p>';
            historyModal.show();
            return;
        }
        
        if (data.history.length === 0) {
            historyContent.innerHTML = '<p class="text-muted">No edit history available.</p>';
            historyModal.show();
            return;
        }
        
        // We receive history ordered by edited_at DESC (newest first).
        // Labeling: the oldest edit should be #1 while newest is highest number.
        // We'll keep newest at top, but compute labels so oldest == 1.
        const totalHistory = data.history.length;
        const historyHtml = data.history.map((h, idx) => {
            const editedAt = formatDateTime(h.edited_at);
            const editedBy = h.edited_by_name || '-';
            const status = h.status ? h.status.replace(/_/g, ' ') : '-';
            const appointmentDate = h.appointment_date ? formatDateTime(h.appointment_date) : '-';
            // Compute label so oldest == 1
            const label = totalHistory - idx;
            // Determine previous (older) record to compare and highlight changed fields
            const prev = (idx + 1 < totalHistory) ? data.history[idx + 1] : null;

            function fieldHtml(title, value, key, formatter) {
                const displayValue = (typeof formatter === 'function') ? formatter(value) : (value ?? '-');
                let safe = escapeHtml(displayValue || '-');
                if (prev && prev.hasOwnProperty(key)) {
                    const prevVal = prev[key] ?? '';
                    // Compare normalized values for dates/status
                    const curVal = value ?? '';
                    if (String(prevVal) !== String(curVal)) {
                        // changed
                        safe = `<span class="edited-field">${safe}</span>`;
                    }
                }
                return `<p><strong>${title}:</strong> ${safe}</p>`;
            }

            return `
            <div class="history-card card mb-3" data-history-idx="${idx}">
                <div class="card-header d-flex justify-content-between align-items-center" style="cursor:pointer;background-color:#5a8c7a;color:white;" onclick="toggleHistoryCard(${idx})">
                    <div><strong>Edit #${label}</strong> - ${editedAt} by ${escapeHtml(editedBy)}</div>
                    <div><i class="fas fa-chevron-down toggle-icon"></i></div>
                </div>
                <div class="card-body history-content" style="display:${idx === 0 ? 'block' : 'none'};">
                    <div class="row">
                        <div class="col-md-6">
                            ${fieldHtml('Patient Name', h.patient_name, 'patient_name')}
                            ${fieldHtml('Action Item', h.action_item, 'action_item')}
                            ${fieldHtml('Consultant Name', h.consultant_name, 'consultant_name')}
                        </div>
                        <div class="col-md-6">
                            ${fieldHtml('Appointment Date', appointmentDate, 'appointment_date', v => escapeHtml(appointmentDate))}
                            <p><strong>Status:</strong> <span class="badge bg-info">${(prev && prev.status !== h.status) ? `<span class="edited-field">${escapeHtml(status)}</span>` : escapeHtml(status)}</span></p>
                            ${fieldHtml('Notes', h.notes, 'notes')}
                        </div>
                    </div>
                </div>
            </div>
            `;
        }).join('');
        
        historyContent.innerHTML = historyHtml;
    } catch (e) {
        historyContent.innerHTML = '<p class="text-danger">Error loading history.</p>';
    }
    
    historyModal.show();
}

function toggleHistoryCard(idx) {
    const cards = document.querySelectorAll('.history-card');
    cards.forEach(card => {
        const cardIdx = parseInt(card.getAttribute('data-history-idx'));
        const content = card.querySelector('.history-content');
        const icon = card.querySelector('.toggle-icon');
        
        if (cardIdx === idx) {
            // Toggle the clicked card
            const isHidden = content.style.display === 'none';
            content.style.display = isHidden ? 'block' : 'none';
            icon.classList.toggle('fa-chevron-down');
            icon.classList.toggle('fa-chevron-up');
        } else {
            // Minimize others
            content.style.display = 'none';
            icon.classList.remove('fa-chevron-up');
            icon.classList.add('fa-chevron-down');
        }
    });
}

function formatDateTime(value) {
    const d = new Date(value.replace(' ', 'T'));
    if (Number.isNaN(d.getTime())) return value;
    return d.toLocaleString(undefined, {
        year: 'numeric',
        month: 'short',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit'
    });
}

function format12HourDateTime(value) {
    const d = new Date(value.replace(' ', 'T'));
    if (Number.isNaN(d.getTime())) return value;
    return d.toLocaleString(undefined, {
        year: 'numeric',
        month: 'short',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hour12: true
    });
}

function escapeHtml(str) {
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function initTypeahead(inputId, listId, action) {
    const input = document.getElementById(inputId);
    const list = document.getElementById(listId);
    if (!input || !list) return;

    let lastQuery = '';
    let debounceTimer = null;

    input.addEventListener('input', () => {
        const val = input.value.trim();
        if (val.length < 2) {
            list.classList.add('d-none');
            list.innerHTML = '';
            lastQuery = '';
            return;
        }
        if (val === lastQuery) return;
        lastQuery = val;
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => fetchSuggestions(val), 250);
    });

    input.addEventListener('blur', () => {
        setTimeout(() => {
            list.classList.add('d-none');
        }, 200);
    });

    async function fetchSuggestions(q) {
        try {
            const res = await fetch('work_tracker.php?action=' + action + '&q=' + encodeURIComponent(q), { credentials: 'same-origin' });
            const data = await res.json();
            if (data.status !== 'success' || !Array.isArray(data.items)) {
                list.classList.add('d-none');
                list.innerHTML = '';
                return;
            }
            if (!data.items.length) {
                list.classList.add('d-none');
                list.innerHTML = '';
                return;
            }
            list.innerHTML = data.items.map(it => {
                const safeName = escapeHtml(it.name || '');
                const pid = it.id ?? '';
                return `<button type="button" class="list-group-item list-group-item-action" data-id="${pid}" data-name="${safeName}">${safeName}</button>`;
            }).join('');
            list.classList.remove('d-none');

            list.querySelectorAll('.list-group-item').forEach(btn => {
                btn.addEventListener('click', () => {
                    const name = btn.getAttribute('data-name') || '';
                    const id = btn.getAttribute('data-id') || '';
                    input.value = name;
                    if (inputId === 'patient_name') {
                        const pidInput = document.querySelector('[name="patient_id"]');
                        if (pidInput) pidInput.value = id;
                    }
                    list.classList.add('d-none');
                });
            });
        } catch (e) {
            list.classList.add('d-none');
            list.innerHTML = '';
        }
    }
}
</script>
</body>
</html>


