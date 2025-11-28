<?php
include "../secure/db.php";

// ---------------- READ FILTERS ----------------
$q             = trim($_GET['q'] ?? '');           // search by name / mobile / email / consultant / id
$from_date_raw = trim($_GET['from_date'] ?? '');  // from date (dd-mm-yyyy or yyyy-mm-dd)
$to_date_raw   = trim($_GET['to_date'] ?? '');    // to date   (dd-mm-yyyy or yyyy-mm-dd)

// Helper: normalize any date string to Y-m-d for SQL
function normalize_date_for_sql($str) {
    if ($str === '') return '';
    $normalized = str_replace('/', '-', $str);
    $ts = strtotime($normalized);
    if ($ts === false) return '';
    return date('Y-m-d', $ts);
}

$from_date_sql = normalize_date_for_sql($from_date_raw);
$to_date_sql   = normalize_date_for_sql($to_date_raw);

// ---------------- BUILD QUERY ----------------
$sql = "SELECT id, name, age, consultant_doctor, city, visitor_date
        FROM patients
        WHERE 1";

// TEXT SEARCH: matches name, mobile, email, consultant, id
if ($q !== '') {
    $q_esc = mysqli_real_escape_string($conn, $q);
    $sql .= " AND (
                name LIKE '%$q_esc%' OR
                mobile_no LIKE '%$q_esc%' OR
                email LIKE '%$q_esc%' OR
                consultant_doctor LIKE '%$q_esc%' OR
                CAST(id AS CHAR) LIKE '%$q_esc%'
            )";
}

// DATE RANGE FILTER
if ($from_date_sql !== '' && $to_date_sql !== '') {
    $from_esc = mysqli_real_escape_string($conn, $from_date_sql);
    $to_esc   = mysqli_real_escape_string($conn, $to_date_sql);
    $sql     .= " AND DATE(visitor_date) BETWEEN '$from_esc' AND '$to_esc'";
} elseif ($from_date_sql !== '') {
    $from_esc = mysqli_real_escape_string($conn, $from_date_sql);
    $sql     .= " AND DATE(visitor_date) >= '$from_esc'";
} elseif ($to_date_sql !== '') {
    $to_esc = mysqli_real_escape_string($conn, $to_date_sql);
    $sql   .= " AND DATE(visitor_date) <= '$to_esc'";
}

$sql .= " ORDER BY visitor_date DESC, id DESC";
$result = mysqli_query($conn, $sql);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Patient Directory</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Bootstrap (for basic layout) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/common.css">
    <link rel="stylesheet" href="../css/patient-pages.css">
</head>
<body class="patient-surface">
<div class="patient-shell">
    <div class="glass-panel patient-directory-panel">
        <header class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
            <div>
                <h1 class="page-title mb-0">Patient Directory</h1>
                <p class="sub-text">Search and filter patients</p>
            </div>
            <div class="text-muted fw-semibold">
                <?= date('l, F d, Y') ?>
            </div>
        </header>

        <!-- FILTER BAR -->
        <section class="filter-bar">
            <form id="filterForm" method="get">

                <!-- big search box -->
                <div class="flex-grow-1">
                    <input
                        type="text"
                        name="q"
                        class="w-100"
                        placeholder="Search by name, mobile, email, consultant or ID…"
                        value="<?= htmlspecialchars($q) ?>"
                    >
                </div>

                <!-- right controls: From date, To date, buttons -->
                <div class="d-flex flex-wrap gap-2 mt-3">
                    <input
                        type="date"
                        name="from_date"
                        title="From date"
                        value="<?= htmlspecialchars($from_date_raw) ?>"
                    >
                    <input
                        type="date"
                        name="to_date"
                        title="To date"
                        value="<?= htmlspecialchars($to_date_raw) ?>"
                    >
                    <button type="submit" class="btn btn-primary">Search</button>
                    <button type="button" class="btn btn-secondary"
                            onclick="window.location.href='patients_view.php'">
                        Clear
                    </button>
                </div>
            </form>
        </section>

        <!-- RESULTS -->
        <section>
            <?php if ($result && mysqli_num_rows($result) > 0): ?>
                <?php while ($row = mysqli_fetch_assoc($result)): ?>
                    <a href="patient.php?id=<?= $row['id'] ?>" class="patient-card-link">
                        <div class="patient-result-card">
                            <strong>
                                PAT<?= $row['id'] ?> &nbsp;–&nbsp;
                                <?= htmlspecialchars($row['name']) ?>
                            </strong>
                            <div>Age: <?= $row['age'] ?: '-' ?></div>
                            <div>Consultant: <?= htmlspecialchars($row['consultant_doctor'] ?: '-') ?></div>
                            <div>City: <?= htmlspecialchars($row['city'] ?: '-') ?></div>
                        </div>
                    </a>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="text-muted">No records found.</div>
            <?php endif; ?>
        </section>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // auto-submit when date changes (search box submits on Enter)
    const form = document.getElementById('filterForm');
    ['from_date', 'to_date'].forEach(function (name) {
        const el = form.elements[name];
        if (el) {
            el.addEventListener('change', function () {
                form.submit();
            });
        }
    });
</script>
</body>
</html>
