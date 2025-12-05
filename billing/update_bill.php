<?php
include "../auth.php";
include "../components/helpers.php";
include "../secure/db.php";
include "../components/modal-dialogs.php";
include "../components/notification_js.php";

$role = $USER['role'] ?? '';
$isAdminRole = isAdmin($role);
$isReceptionistRole = hasRole($role, 'receptionist');

if (!$isAdminRole && !$isReceptionistRole) {
    die("Access denied!");
}

$billingStatuses = [
    'paid'      => 'Paid',
    'unpaid'    => 'Unpaid',
    'pending'   => 'Pending',
    'cancelled' => 'Cancelled'
];

$paymentMethods = [
    'cash'          => 'Cash',
    'card'          => 'Card / POS',
    'upi'           => 'UPI',
    'bank_transfer' => 'Bank Transfer',
    'other'         => 'Other'
];

$errors = [];
$successMessage = '';

$billIdParam = isset($_GET['bill_id']) ? (int)$_GET['bill_id'] : 0;
$patient_id_filter = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $billIdParam = (int)($_POST['bill_id'] ?? 0);
}

// If patient_id is provided but no bill_id, show bills for that patient
if ($patient_id_filter > 0 && $billIdParam == 0) {
    // Fetch bills for this patient
    $stmt = mysqli_prepare($conn, "SELECT * FROM billings WHERE patient_id = ? ORDER BY bill_date DESC, bill_id DESC LIMIT 50");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'i', $patient_id_filter);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $patientBills = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $patientBills[] = $row;
        }
        mysqli_stmt_close($stmt);
        
        // If only one bill, auto-load it
        if (count($patientBills) === 1) {
            $billIdParam = (int)$patientBills[0]['bill_id'];
            $bill = fetchBill($conn, $billIdParam);
        }
    }
}

function fetchBill(mysqli $conn, int $billId): ?array {
    $stmt = mysqli_prepare($conn, "SELECT * FROM billings WHERE bill_id = ?");
    if (!$stmt) return null;
    mysqli_stmt_bind_param($stmt, 'i', $billId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $bill = mysqli_fetch_assoc($result) ?: null;
    mysqli_stmt_close($stmt);
    return $bill;
}

function fetchBillingItems(mysqli $conn): array {
    $list = [];
    $res = @mysqli_query($conn, "SELECT id, item_name, price FROM billing_items ORDER BY item_name ASC");
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            $list[] = $row;
        }
    }
    return $list;
}

$bill = $billIdParam ? fetchBill($conn, $billIdParam) : null;
$billingItems = fetchBillingItems($conn);

// fetch lists for dropdowns
function fetchRecentPatients(mysqli $conn, int $limit = 200): array {
    $list = [];
    // include age and gender for each patient to populate update form
    $res = @mysqli_query($conn, "SELECT id, name, COALESCE(age, '') AS age, COALESCE(gender, '') AS gender FROM patients ORDER BY name ASC LIMIT " . (int)$limit);
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) $list[] = $row;
    }
    return $list;
}

function fetchConsultantsList(mysqli $conn): array {
    $list = [];
    // Fetch consultants with user_id from users table
    $res = @mysqli_query($conn, "SELECT c.id as consultant_table_id, u.id as user_id, u.name, c.user_id 
                                  FROM consultants c 
                                  JOIN users u ON u.id = c.user_id 
                                  WHERE c.status = 'active' 
                                  ORDER BY u.name ASC");
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) $list[] = $row;
    }
    return $list;
}

$patientsList = fetchRecentPatients($conn);
$consultantsList = fetchConsultantsList($conn);

// Filters for quick search within Update tab (reuse history filters)
$search      = trim($_GET['search'] ?? '');
$from_date   = trim($_GET['from_date'] ?? '');
$to_date     = trim($_GET['to_date'] ?? '');
$status_f    = trim($_GET['status'] ?? '');
$method_f    = trim($_GET['method'] ?? '');
$hasSearchFilters = ($search !== '' || $from_date !== '' || $to_date !== '' || $status_f !== '' || $method_f !== '');

$searchBills = [];
$searchTotals = ['total' => 0.0, 'paid' => 0.0, 'unpaid' => 0.0];
// Only search if no bill_id is already selected
if ($hasSearchFilters && $billIdParam == 0) {
    $conditions = [];
    $params = [];
    $types = '';

    if ($search !== '') {
        if (is_numeric($search)) {
            $conditions[] = "(patient_name LIKE ? OR consultant_name LIKE ? OR bill_id = ? OR bill_id LIKE ? OR p.mobile_no LIKE ?)";
            $like = $search . '%';
            $params[] = $like; $types .= 's';
            $params[] = $like; $types .= 's';
            $params[] = (int)$search; $types .= 'i';
            $params[] = $like; $types .= 's';
            $params[] = $like; $types .= 's';
        } else {
            $conditions[] = "(patient_name LIKE ? OR consultant_name LIKE ? OR CAST(bill_id AS CHAR) LIKE ? OR p.mobile_no LIKE ?)";
            $like = $search . '%';
            $params[] = $like; $types .= 's';
            $params[] = $like; $types .= 's';
            $params[] = $like; $types .= 's';
            $params[] = $like; $types .= 's';
        }
    }

    if ($from_date !== '' && DateTime::createFromFormat('Y-m-d', $from_date)) {
        $conditions[] = "bill_date >= ?";
        $params[] = $from_date; $types .= 's';
    }
    if ($to_date !== '' && DateTime::createFromFormat('Y-m-d', $to_date)) {
        $conditions[] = "bill_date <= ?";
        $params[] = $to_date; $types .= 's';
    }

    if ($status_f !== '' && isset($billingStatuses[$status_f])) {
        $conditions[] = "status = ?";
        $params[] = $status_f; $types .= 's';
    }

    if ($method_f !== '' && isset($paymentMethods[$method_f])) {
        $conditions[] = "method = ?";
        $params[] = $method_f; $types .= 's';
    }

    $sql = "SELECT b.*, COALESCE(p.age, '') AS patient_age, COALESCE(p.gender, '') AS patient_gender, COALESCE(p.mobile_no, '') AS patient_mobile FROM billings b LEFT JOIN patients p ON p.id = b.patient_id";
    if (!empty($conditions)) $sql .= " WHERE " . implode(' AND ', $conditions);
    $sql .= " ORDER BY bill_date DESC, bill_id DESC LIMIT 200";

    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt) {
        if (!empty($params)) {
            $bindParams = [];
            $bindParams[] = &$types;
            foreach ($params as $k => $v) $bindParams[] = &$params[$k];
            call_user_func_array([$stmt, 'bind_param'], $bindParams);
        }
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        while ($r = mysqli_fetch_assoc($res)) {
            $searchBills[] = $r;
            $searchTotals['total'] += (float)$r['total_amount'];
            if ($r['status'] === 'paid') $searchTotals['paid'] += (float)$r['total_amount']; else $searchTotals['unpaid'] += (float)$r['total_amount'];
        }
        mysqli_stmt_close($stmt);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $billIdParam > 0) {
    $bill_date        = trim($_POST['bill_date'] ?? '');
    $patient_id       = trim($_POST['patient_id'] ?? '');
    $patient_name     = trim($_POST['patient_name'] ?? '');
    $consultant_id    = trim($_POST['consultant_id'] ?? '');
    $consultant_name  = trim($_POST['consultant_name'] ?? '');
    $total_amount     = trim($_POST['total_amount'] ?? '');
    $billing_item_id  = trim($_POST['billing_item_id'] ?? '');
    $status           = trim($_POST['status'] ?? 'unpaid');
    $method           = trim($_POST['method'] ?? 'cash');
    $notes            = trim($_POST['notes'] ?? '');
    $updated_by       = trim($_POST['updated_by'] ?? '');

    if ($bill_date === '' || !DateTime::createFromFormat('Y-m-d', $bill_date)) {
        $errors[] = "Bill date is required.";
    }

    $patient_id_val = ($patient_id === '') ? null : (int)$patient_id;
    $consultant_id_val = ($consultant_id === '') ? null : (int)$consultant_id;
    $total_amount_val = is_numeric($total_amount) ? (float)$total_amount : null;

    if ($total_amount_val === null || $total_amount_val < 0) {
        $errors[] = "Total amount must be a non-negative number.";
    }

    if ($patient_id_val && $patient_name === '') {
        $stmt = mysqli_prepare($conn, "SELECT name FROM patients WHERE id = ?");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'i', $patient_id_val);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_bind_result($stmt, $dbPatientName);
            if (mysqli_stmt_fetch($stmt)) {
                $patient_name = $dbPatientName;
            }
            mysqli_stmt_close($stmt);
        }
    }

    if ($consultant_id_val && $consultant_name === '') {
        $stmt = mysqli_prepare($conn, "SELECT u.name 
                                       FROM consultants c 
                                       JOIN users u ON u.id = c.user_id 
                                       WHERE c.id = ?");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'i', $consultant_id_val);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_bind_result($stmt, $dbConsultantName);
            if (mysqli_stmt_fetch($stmt)) {
                $consultant_name = $dbConsultantName;
            }
            mysqli_stmt_close($stmt);
        }
    }

    if ($patient_name === '') {
        $errors[] = "Patient name is required.";
    }
    if ($consultant_name === '') {
        $errors[] = "Consultant name is required.";
    }

    if (!isset($billingStatuses[$status])) {
        $status = 'unpaid';
    }
    if (!isset($paymentMethods[$method])) {
        $method = 'cash';
    }

    // If a billing item was selected, prepare items_json (do not store in notes)
    $items_json = null;
    if ($billing_item_id !== '') {
        $biStmt = $conn->prepare("SELECT id, item_name, price FROM billing_items WHERE id = ? LIMIT 1");
        if ($biStmt) {
            $biId = (int)$billing_item_id;
            $biStmt->bind_param('i', $biId);
            $biStmt->execute();
            $biRes = $biStmt->get_result();
            if ($biRow = $biRes->fetch_assoc()) {
                $line = [
                    'billing_item_id' => (int)$biRow['id'],
                    'description' => $biRow['item_name'],
                    'quantity' => 1,
                    'rate' => (float)$biRow['price'],
                    'amount' => (float)$biRow['price']
                ];
                $itemsArr = [$line];
                $items_json = json_encode($itemsArr);
            }
            $biStmt->close();
        }
    }

    if (empty($errors)) {
        // Ensure `items_json` column exists in `billings` table (safe check)
        $colCheck = mysqli_query($conn, "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'billings' AND COLUMN_NAME = 'items_json'");
        if ($colCheck && mysqli_num_rows($colCheck) === 0) {
            @mysqli_query($conn, "ALTER TABLE billings ADD COLUMN items_json TEXT NULL AFTER notes");
        }

        // Set updated_by from POST data (or fallback to current user id if not provided)
        // Only set if it's a valid positive integer that exists in users table
        $updated_by_val = null;
        if ($updated_by !== '' && (int)$updated_by > 0) {
            $updated_by_val = (int)$updated_by;
        } elseif (isset($USER['id']) && (int)$USER['id'] > 0) {
            $updated_by_val = (int)$USER['id'];
        }

        $sql = "UPDATE billings SET
                    bill_date = ?,
                    patient_id = ?,
                    patient_name = ?,
                    consultant_id = ?,
                    consultant_name = ?,
                    total_amount = ?,
                    status = ?,
                    method = ?,
                    notes = ?,
                    items_json = ?,
                    updated_at = NOW(),
                    updated_by = ?
                WHERE bill_id = ?";

        $stmt = mysqli_prepare($conn, $sql);
        if ($stmt) {
            mysqli_stmt_bind_param(
                $stmt,
                'sisisdssssii',
                $bill_date,
                $patient_id_val,
                $patient_name,
                $consultant_id_val,
                $consultant_name,
                $total_amount_val,
                $status,
                $method,
                $notes,
                $items_json,
                $updated_by_val,
                $billIdParam
            );
            if (mysqli_stmt_execute($stmt)) {
                $successMessage = "Bill updated successfully.";
                $bill = fetchBill($conn, $billIdParam);
            } else {
                $errors[] = "Failed to update bill. " . mysqli_error($conn);
            }
            mysqli_stmt_close($stmt);
        } else {
            $errors[] = "Unable to prepare statement: " . mysqli_error($conn);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Update Bill</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../css/common.css">
    <link rel="stylesheet" href="../css/patient-pages.css">
</head>
<body class="patient-surface">
<div class="patient-shell">
    <header class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h1 class="page-title mb-0">Update Bill</h1>
            <p class="sub-text mb-0">Search for an existing bill and update it</p>
        </div>
        <form method="get" class="row g-3 align-items-end mb-3">
            <div class="col-md-3">
                <label class="form-label">Search</label>
                <input type="text" name="search" class="form-control" placeholder="Patient, consultant, bill ID, mobile..." value="<?= htmlspecialchars($search) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">From Date</label>
                <input type="date" name="from_date" class="form-control" value="<?= htmlspecialchars($from_date) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">To Date</label>
                <input type="date" name="to_date" class="form-control" value="<?= htmlspecialchars($to_date) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    <option value="">All</option>
                    <?php foreach ($billingStatuses as $value => $label): ?>
                        <option value="<?= $value ?>" <?= ($status_f === $value) ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Method</label>
                <select name="method" class="form-select">
                    <option value="">All</option>
                    <?php foreach ($paymentMethods as $value => $label): ?>
                        <option value="<?= $value ?>" <?= ($method_f === $value) ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-1 text-end">
                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-add w-100">Filter</button>
                    <a href="update_bill.php" class="btn btn-secondary w-100">Reset</a>
                </div>
            </div>
        </form>
    </header>

    <?php if ($hasSearchFilters && !empty($searchBills) && $billIdParam == 0): ?>
        <?php 
        // If bills found, show a simple list to select from
        // If only one bill found, auto-load it directly
        if (count($searchBills) === 1) {
            $bill = fetchBill($conn, (int)$searchBills[0]['bill_id']);
            $billIdParam = (int)$searchBills[0]['bill_id'];
        } else {
            // Show simple selection list
        ?>
        <div class="glass-panel mb-3">
            <h5 class="mb-3">Select a bill to update:</h5>
            <div class="list-group">
                <?php foreach ($searchBills as $b): ?>
                    <a href="?bill_id=<?= (int)$b['bill_id'] ?>&<?= http_build_query(array_filter(['search' => $search, 'from_date' => $from_date, 'to_date' => $to_date, 'status' => $status_f, 'method' => $method_f])) ?>" class="list-group-item list-group-item-action">
                        <div class="d-flex w-100 justify-content-between">
                            <h6 class="mb-1">Bill #<?= (int)$b['bill_id'] ?> - <?= htmlspecialchars($b['patient_name'] ?: '—') ?></h6>
                            <small>₹<?= number_format((float)$b['total_amount'], 2) ?></small>
                        </div>
                        <p class="mb-1">Date: <?= htmlspecialchars(date('d M Y', strtotime($b['bill_date']))) ?> | Consultant: <?= htmlspecialchars($b['consultant_name'] ?: '—') ?></p>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php 
        }
        ?>
    <?php endif; ?>

    <div class="patient-form-card glass-panel">
        <?php if (!$bill): ?>
            <p class="text-muted mb-0">
                <?php if ($billIdParam > 0): ?>
                    <span class="text-danger">Bill not found. Please check the ID and try again.</span>
                <?php else: ?>
                    Use the filters above to search for a bill to update.
                <?php endif; ?>
            </p>
        <?php else: ?>
            <form method="post" class="row g-3" novalidate id="updateBillForm">
                <input type="hidden" name="bill_id" value="<?= (int)$bill['bill_id'] ?>">
                <input type="hidden" name="updated_by" value="<?= (int)($USER['id'] ?? 0) ?>">

                <div class="col-md-4">
                    <label class="form-label">Bill ID</label>
                    <input type="text" class="form-control" value="<?= (int)$bill['bill_id'] ?>" readonly style="background-color: #f8f9fa;">
                </div>

                <div class="col-md-4">
                    <label class="form-label">Bill Date <span class="text-danger">*</span></label>
                    <input type="date" name="bill_date" class="form-control" value="<?= htmlspecialchars($bill['bill_date']) ?>" required>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Patient Name <span class="text-danger">*</span></label>
                    <input type="text" id="patientNameInput" class="form-control" autocomplete="off" placeholder="Type patient name..." value="<?= htmlspecialchars($bill['patient_name'] ?? '') ?>" required>
                    <div id="patientNameDropdown" class="autocomplete-dropdown"></div>
                </div>

                <div class="col-md-2">
                    <label class="form-label">Patient ID</label>
                    <input type="text" id="patientIdDisplay" class="form-control" placeholder="Auto-fill" readonly style="background-color: #f8f9fa;">
                    <input type="hidden" name="patient_id" id="patientIdHidden" value="<?= (int)($bill['patient_id'] ?? 0) ?>">
                </div>

                <div class="col-md-2">
                    <label class="form-label">Age</label>
                    <input type="text" id="patientAgeDisplay" class="form-control" placeholder="Auto-fill" readonly style="background-color: #f8f9fa;">          
                </div>

                <div class="col-md-2">
                    <label class="form-label">Gender</label>
                    <input type="text" id="patientGenderDisplay" class="form-control" placeholder="Auto-fill" readonly style="background-color: #f8f9fa;">
                </div>

                <div class="col-md-2">
                    <label class="form-label">Consultant ID</label>
                    <input type="text" id="consultantIdDisplay" class="form-control" placeholder="Auto-fill" readonly style="background-color: #f8f9fa;">
                    <input type="hidden" name="consultant_id" id="consultantIdHidden" value="<?= (int)($bill['consultant_id'] ?? 0) ?>">
                </div>

                <div class="col-md-4">
                    <label class="form-label">Consultant Name <span class="text-danger">*</span></label>
                    <input type="text" id="consultantNameInput" class="form-control" autocomplete="off" placeholder="Type consultant name..." value="<?= htmlspecialchars($bill['consultant_name'] ?? '') ?>" required>
                    <div id="consultantNameDropdown" class="autocomplete-dropdown"></div>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Billing Item</label>
                    <select name="billing_item_id" id="billingItemSelect" class="form-select" onchange="populatePrice()">
                        <option value="">-- Select an item --</option>
                        <?php foreach ($billingItems as $item): ?>
                            <option value="<?= (int)$item['id'] ?>" data-price="<?= htmlspecialchars($item['price']) ?>">
                                <?= htmlspecialchars($item['item_name']) ?> (₹<?= number_format($item['price'], 2) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Total Amount (₹) <span class="text-danger">*</span></label>
                    <input type="number" step="0.01" min="0" name="total_amount" id="totalAmountField" class="form-control" value="<?= htmlspecialchars($bill['total_amount']) ?>" required>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <?php foreach ($billingStatuses as $value => $label): ?>
                            <option value="<?= $value ?>" <?= ($bill['status'] === $value) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($label) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Payment Method</label>
                    <select name="method" class="form-select">
                        <?php foreach ($paymentMethods as $value => $label): ?>
                            <option value="<?= $value ?>" <?= ($bill['method'] === $value) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($label) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Notes (Optional)</label>
                    <textarea name="notes" class="form-control" rows="1" placeholder="Additional notes..."><?= htmlspecialchars(getCleanNotes($bill['notes'])) ?></textarea>
                </div>

                <div class="col-12 mt-5">
                    <div class="d-flex justify-content-end gap-2">
                        <button type="button" class="btn btn-outline-secondary" onclick="printBill()">
                            <i class="bi bi-printer"></i> Print
                        </button>
                        <button type="submit" class="btn btn-add">
                            <i class="bi bi-save"></i> Update Bill
                        </button>
                    </div>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<style>
.autocomplete-dropdown {
    position: absolute;
    z-index: 1000;
    background: #fff;
    border: 1px solid #ccc;
    border-radius: 0 0 6px 6px;
    max-height: 100px;
    overflow-y: auto;
    width: 30%;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    display: none;
}
.autocomplete-dropdown .dropdown-item {
    cursor: pointer;
    padding: 8px 12px;
}
.autocomplete-dropdown .dropdown-item:hover {
    background: #f1f1f1;
}
</style>
<script>
// --- Patient Name Autocomplete for Update ---
const patientInput = document.getElementById('patientNameInput');
const patientDropdown = document.getElementById('patientNameDropdown');
const patientIdHidden = document.getElementById('patientIdHidden');
const patientIdDisplay = document.getElementById('patientIdDisplay');
let patientTimeout = null;
if (patientInput) {
    patientInput.addEventListener('input', function() {
        const val = this.value.trim();
        if (patientIdHidden) patientIdHidden.value = '';
        if (patientIdDisplay) patientIdDisplay.value = '';
        if (val.length < 2) {
            patientDropdown.style.display = 'none';
            return;
        }
        clearTimeout(patientTimeout);
        patientTimeout = setTimeout(() => {
            fetch('patient_search.php?q=' + encodeURIComponent(val))
                .then(r => r.json())
                .then(obj => {
                    const arr = Array.isArray(obj) ? obj : (obj.items || []);
                    if (!Array.isArray(arr) || arr.length === 0) {
                        patientDropdown.style.display = 'none';
                        return;
                    }
                    patientDropdown.innerHTML = arr.map(p => `
                        <div class="dropdown-item" data-id="${p.id}" data-name="${p.name}" data-age="${p.age}" data-gender="${p.gender}">
                            ${p.name} ${p.age ? (' | ' + p.age) : ''} ${p.gender ? (' | ' + p.gender) : ''}
                        </div>
                    `).join('');
                    patientDropdown.style.display = 'block';
                }).catch(() => { patientDropdown.style.display = 'none'; });
        }, 200);
    });
    patientDropdown.addEventListener('mousedown', function(e) {
        if (e.target.classList.contains('dropdown-item')) {
            const name = e.target.dataset.name || '';
            const id = e.target.dataset.id || '';
            const age = e.target.dataset.age || '';
            const gender = e.target.dataset.gender || '';
            patientInput.value = name;
            if (patientIdHidden) patientIdHidden.value = id;
            if (patientIdDisplay) patientIdDisplay.value = id;
            const ageDisplay = document.getElementById('patientAgeDisplay');
            const genderDisplay = document.getElementById('patientGenderDisplay');
            if (ageDisplay) ageDisplay.value = age;
            if (genderDisplay) genderDisplay.value = gender;
            // also update hidden patient name field
            const patientNameHidden = document.getElementById('patientNameHidden');
            if (patientNameHidden) patientNameHidden.value = name;
            patientDropdown.style.display = 'none';
        }
    });
    document.addEventListener('click', function(e) {
        if (!patientDropdown.contains(e.target) && e.target !== patientInput) {
            patientDropdown.style.display = 'none';
        }
    });
}

// --- Consultant Name Autocomplete for Update ---
const consultantInput = document.getElementById('consultantNameInput');
const consultantDropdown = document.getElementById('consultantNameDropdown');
const consultantIdHidden = document.getElementById('consultantIdHidden');
let consultantTimeout = null;
if (consultantInput) {
    consultantInput.addEventListener('input', function() {
        const val = this.value.trim();
        if (consultantIdHidden) consultantIdHidden.value = '';
        if (val.length < 2) {
            consultantDropdown.style.display = 'none';
            return;
        }
        clearTimeout(consultantTimeout);
        consultantTimeout = setTimeout(() => {
            fetch('consultant_search.php?q=' + encodeURIComponent(val))
                .then(r => r.json())
                .then(arr => {
                    if (!Array.isArray(arr) || arr.length === 0) {
                        consultantDropdown.style.display = 'none';
                        return;
                    }
                    consultantDropdown.innerHTML = arr.map(c => `<div class="dropdown-item" data-id="${c.id}" data-name="${c.name}">${c.name}</div>`).join('');
                    consultantDropdown.style.display = 'block';
                }).catch(() => { consultantDropdown.style.display = 'none'; });
        }, 200);
    });
    consultantDropdown.addEventListener('mousedown', function(e) {
        if (e.target.classList.contains('dropdown-item')) {
            consultantInput.value = e.target.dataset.name;
            if (consultantIdHidden) consultantIdHidden.value = e.target.dataset.id;
            const consultantNameHidden = document.getElementById('consultantNameHidden');
            if (consultantNameHidden) consultantNameHidden.value = e.target.dataset.name;
            consultantDropdown.style.display = 'none';
        }
    });
    document.addEventListener('click', function(e) {
        if (!consultantDropdown.contains(e.target) && e.target !== consultantInput) {
            consultantDropdown.style.display = 'none';
        }
    });
}

function populatePrice() {
    const select = document.getElementById('billingItemSelect');
    const option = select && select.options[select.selectedIndex];
    const price = option?.getAttribute('data-price');
    if (price) {
        const totalField = document.getElementById('totalAmountField');
        if (totalField) totalField.value = price;
    }
}

function loadIntoForm(bid) {
    try {
        const url = new URL(window.location.href);
        url.searchParams.set('bill_id', bid);
        window.location.href = url.toString();
        return;
    } catch (e) {
        window.location.href = 'update_bill.php?bill_id=' + encodeURIComponent(bid);
    }
}

// Initialization: populate displays from server-side values
document.addEventListener('DOMContentLoaded', function () {
    // If server provided patient/consultant data, fill displays
    const sid = <?= json_encode((string)($bill['patient_id'] ?? '')) ?>;
    const sname = <?= json_encode($bill['patient_name'] ?? '') ?>;
    const sage = <?= json_encode($bill['patient_age'] ?? '') ?>;
    const sgender = <?= json_encode($bill['patient_gender'] ?? '') ?>;
    const cId = <?= json_encode((string)($bill['consultant_id'] ?? '')) ?>;
    const cName = <?= json_encode($bill['consultant_name'] ?? '') ?>;

    if (patientInput && sname) patientInput.value = sname;
    if (patientIdHidden && sid) patientIdHidden.value = sid;
    if (patientIdDisplay && sid) patientIdDisplay.value = sid;
    const ageDisplay = document.getElementById('patientAgeDisplay');
    const genderDisplay = document.getElementById('patientGenderDisplay');
    if (ageDisplay && sage) ageDisplay.value = sage;
    if (genderDisplay && sgender) genderDisplay.value = sgender;

    if (consultantInput && cName) consultantInput.value = cName;
    if (consultantIdHidden && cId) consultantIdHidden.value = cId;
    const consultantIdDisplay = document.getElementById('consultantIdDisplay');
    if (consultantIdDisplay && cId) consultantIdDisplay.value = cId;

    // Form confirm handler
    const form = document.getElementById('updateBillForm');
    if (!form) return;
    form.addEventListener('submit', function (e) {
        if (form.dataset.confirmed === '1') { form.dataset.confirmed = ''; return; }
        e.preventDefault();
        const msg = 'Are you sure you want to update this bill?';
        if (typeof showConfirmModal === 'function') {
            showConfirmModal(msg, function () { form.dataset.confirmed = '1'; form.submit(); });
        } else if (confirm(msg)) {
            form.submit();
        }
    });
});

function printBill() {
    const billId = <?= json_encode((int)($bill['bill_id'] ?? 0)) ?>;
    if (billId && Number(billId) > 0) {
        window.open(`print_bill.php?id=${billId}&autoprint=1`, '_blank');
    } else {
        if (typeof showErrorModal === 'function') {
            showErrorModal('Please save the bill first.', 'Print Bill');
        } else {
            alert('Please save the bill first.');
        }
    }
}
</script>
<?php if ($successMessage): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof showSuccessModal === 'function') {
        showSuccessModal(<?= json_encode($successMessage) ?>, 'Bill Updated');
    }
});
</script>
<?php endif; ?>
<?php if (!empty($errors)): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof showErrorModal === 'function') {
        showErrorModal(<?= json_encode(implode("\n", $errors)) ?>, 'Unable to update bill');
    }
});
</script>
<?php endif; ?>
</body>
</html>

