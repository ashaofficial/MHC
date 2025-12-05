<?php
include "../auth.php";
include "../components/helpers.php";
include "../secure/db.php";

$role = $USER['role'] ?? '';
$isAdminRole = isAdmin($role);
$isReceptionistRole = hasRole($role, 'receptionist');
$canManageBills = $isAdminRole || $isReceptionistRole;

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

$search      = trim($_GET['search'] ?? '');
$from_date   = trim($_GET['from_date'] ?? '');
$to_date     = trim($_GET['to_date'] ?? '');
$status      = trim($_GET['status'] ?? '');
$method      = trim($_GET['method'] ?? '');
$patient_id_filter = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;

$hasFilter = ($search !== '' || $from_date !== '' || $to_date !== '' || $status !== '' || $method !== '' || $patient_id_filter > 0);

$conditions = [];
$params = [];
$types = '';

// Filter by patient_id if provided
if ($patient_id_filter > 0) {
    $conditions[] = "patient_id = ?";
    $params[] = $patient_id_filter;
    $types .= 'i';
}

if ($search !== '') {
    // If search is numeric, also check for exact bill_id match
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
    $params[] = $from_date;
    $types   .= 's';
}
if ($to_date !== '' && DateTime::createFromFormat('Y-m-d', $to_date)) {
    $conditions[] = "bill_date <= ?";
    $params[] = $to_date;
    $types   .= 's';
}

if ($status !== '' && isset($billingStatuses[$status])) {
    $conditions[] = "status = ?";
    $params[] = $status;
    $types   .= 's';
} else {
    $status = '';
}

if ($method !== '' && isset($paymentMethods[$method])) {
    $conditions[] = "method = ?";
    $params[] = $method;
    $types   .= 's';
} else {
    $method = '';
}

$bills = [];
$totals = [
    'total'    => 0.0,
    'paid'     => 0.0,
    'unpaid'   => 0.0
];

if ($hasFilter) {
    // Join to patients to fetch age and gender for display
    // Also join credential to get username if available; fallback to users.username or users.name
        $sql = "SELECT b.*, COALESCE(p.age, '') AS patient_age, COALESCE(p.gender, '') AS patient_gender, COALESCE(p.mobile_no, '') AS patient_mobile,
                 COALESCE(cr.username, u.name, '') AS updated_by
             FROM billings b
             LEFT JOIN patients p ON p.id = b.patient_id
             LEFT JOIN users u ON u.id = b.updated_by
             LEFT JOIN credential cr ON cr.user_id = u.id";
    if (!empty($conditions)) {
        $sql .= " WHERE " . implode(' AND ', $conditions);
    }
    $sql .= " ORDER BY bill_date DESC, bill_id DESC LIMIT 200";

    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt === false) {
        die("Failed to prepare statement: " . mysqli_error($conn));
    }

    if (!empty($params)) {
        $bindParams = [];
        $bindParams[] = &$types;
        foreach ($params as $key => $value) {
            $bindParams[] = &$params[$key];
        }
        call_user_func_array([$stmt, 'bind_param'], $bindParams);
    }

    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    while ($row = mysqli_fetch_assoc($result)) {
        $bills[] = $row;
        $totals['total'] += (float)$row['total_amount'];
        if ($row['status'] === 'paid') {
            $totals['paid'] += (float)$row['total_amount'];
        } else {
            $totals['unpaid'] += (float)$row['total_amount'];
        }
    }
    mysqli_stmt_close($stmt);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Bill History</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/common.css">
    <link rel="stylesheet" href="../css/patient-pages.css">
</head>
<body class="patient-surface">
<div class="patient-shell">
    <header class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h1 class="page-title mb-0">Bill History</h1>
            <p class="sub-text mb-0">Review up to 200 recent bills with filters</p>
        </div>
    </header>

    <div class="glass-panel patient-directory-panel">
        <form method="get" class="row g-3 align-items-end mb-4">
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
                        <option value="<?= $value ?>" <?= ($status === $value) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Method</label>
                <select name="method" class="form-select">
                    <option value="">All</option>
                    <?php foreach ($paymentMethods as $value => $label): ?>
                        <option value="<?= $value ?>" <?= ($method === $value) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-1 text-end">
                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-add w-100">Filter</button>
                    <a href="bill_history.php" class="btn btn-secondary w-100">Reset</a>
                </div>
            </div>
        </form>

        <?php if (! $hasFilter): ?>
            <div class="text-center text-muted py-5">
                <i class="fas fa-filter fa-2x mb-3"></i>
                <p class="mb-0">Use the filters above and click <strong>Filter</strong> to load bills.</p>
            </div>
        <?php else: ?>
            <div class="mb-3">
                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="p-3 border rounded-4 text-center">
                            <div class="text-muted text-uppercase small">Total Amount</div>
                            <div class="fs-5 fw-semibold">₹<?= number_format($totals['total'], 2) ?></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="p-3 border rounded-4 text-center">
                            <div class="text-muted text-uppercase small">Paid</div>
                            <div class="fs-5 fw-semibold text-success">₹<?= number_format($totals['paid'], 2) ?></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="p-3 border rounded-4 text-center">
                            <div class="text-muted text-uppercase small">Unpaid</div>
                            <div class="fs-5 fw-semibold text-danger">₹<?= number_format($totals['unpaid'], 2) ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <?php if (empty($bills)): ?>
                <div class="text-center text-muted py-5">
                    <i class="fas fa-folder-open fa-2x mb-3"></i>
                    <p class="mb-0">No bills match your filters.</p>
                </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Date</th>
                            <th>Patient</th>
                            <th>Consultant</th>
                            <th>Notes</th>
                            <th>Amount (₹)</th>
                            <th>Status</th>
                            <th>Method</th>
                            <th>Updated By</th>
                            <th>Updated</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bills as $bill): ?>
                            <tr>
                                <td><span class="fw-semibold">#<?= (int)$bill['bill_id'] ?></span></td>
                                <td><?= htmlspecialchars(date('d M Y', strtotime($bill['bill_date']))) ?></td>
                                <td>
                                    <div class="fw-semibold"><?= htmlspecialchars($bill['patient_name'] ?: '—') ?></div>
                                    <?php if (!empty($bill['patient_id'])): ?>
                                        <div class="text-muted small">ID: <?= (int)$bill['patient_id'] ?> | Age/Sex: <?= htmlspecialchars($bill['patient_age'] ?: '—') ?>/<?= htmlspecialchars($bill['patient_gender'] ?: '—') ?></div>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($bill['consultant_name'] ?: '—') ?></td>
                                <td>
                                    <?php $cleanNotes = getCleanNotes($bill['notes'] ?? ''); ?>
                                    <div class="text-muted small"><?= htmlspecialchars(mb_strimwidth($cleanNotes, 0, 80, '...')) ?: '—' ?></div>
                                </td>
                                <td>
                                    <div class="fw-semibold">₹<?= number_format((float)$bill['total_amount'], 2) ?></div>
                                </td>
                                <td>
                                    <span class="badge bg-<?=
                                        $bill['status'] === 'paid' ? 'success' :
                                        ($bill['status'] === 'pending' ? 'warning text-dark' :
                                        ($bill['status'] === 'cancelled' ? 'danger' : 'secondary'))
                                    ?>">
                                        <?= htmlspecialchars(ucfirst($bill['status'])) ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($paymentMethods[$bill['method'] ?? ''] ?? ($bill['method'] ? ucfirst($bill['method']) : '—')) ?></td>
                                <td><?= htmlspecialchars($bill['updated_by'] ?: '—') ?></td>
                                <td><?= htmlspecialchars(date('d M Y H:i', strtotime($bill['updated_at']))) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        <?php endif; ?>
        </div>
    </div>
</div>


</body>
</html>
  