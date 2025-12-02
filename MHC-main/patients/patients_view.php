<?php
include "../secure/db.php";

// ---------------- READ FILTERS ----------------
$q             = trim($_GET['q'] ?? '');           // search by id / name / mobile / city / email
$from_date_raw = trim($_GET['from_date'] ?? '');  // from date
$to_date_raw   = trim($_GET['to_date'] ?? '');    // to date

// Check if user actually applied any filter
$hasFilter = ($q !== '' || $from_date_raw !== '' || $to_date_raw !== '');

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

$result = null;

// ---------------- BUILD QUERY ONLY IF SEARCH APPLIED ----------------
if ($hasFilter) {

    $sql = "SELECT id, name, age, mobile_no, city, visitor_date
            FROM patients
            WHERE 1";

    // TEXT SEARCH: matches id, name, mobile, city, email
    if ($q !== '') {
        $q_esc = mysqli_real_escape_string($conn, $q);
        $sql .= " AND (
                    CAST(id AS CHAR) LIKE '%$q_esc%' OR
                    name LIKE '%$q_esc%' OR
                    mobile_no LIKE '%$q_esc%' OR
                    city LIKE '%$q_esc%' OR
                    email LIKE '%$q_esc%'
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
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Patient Directory</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Bootstrap (optional, for base grid) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Your common layout (if any) -->
    <link rel="stylesheet" href="../css/common.css">

    <!-- Page-specific CSS -->
    <link rel="stylesheet" href="../css/patients_view.css">
</head>
<body>
<div class="app-layout">

    <!-- If you have a sidebar, it can sit here -->
    <!-- <aside class="sidebar">...</aside> -->

    <main class="main-content">
        <!-- HEADER -->
        <header class="main-header">
            <div>
                <h1 class="page-title">Patient Directory</h1>
                <p class="welcome-text">Search patients by ID, name, mobile or city</p>
            </div>
            <div class="current-date">
                <?= date('l, F d, Y') ?>
            </div>
        </header>

        <!-- FILTER BAR (one straight line) -->
        <section class="filter-bar">
            <form id="filterForm" method="get" class="filter-form">
                <!-- Search -->
                <div class="input-group search-group">
                    <input
                        type="text"
                        name="q"
                        placeholder="Patient ID / Name / Mobile / City"
                        value="<?= htmlspecialchars($q) ?>"
                    >
                </div>

                <!-- From date -->
                <div class="input-group date-group">
                    <input
                        type="date"
                        name="from_date"
                        title="From date"
                        value="<?= htmlspecialchars($from_date_raw) ?>"
                    >
                </div>

                <!-- To date -->
                <div class="input-group date-group">
                    <input
                        type="date"
                        name="to_date"
                        title="To date"
                        value="<?= htmlspecialchars($to_date_raw) ?>"
                    >
                </div>

                <!-- Buttons -->
                <button type="submit" class="btn primary">Search</button>
                <button
                    type="button"
                    class="btn secondary"
                    onclick="window.location.href='patients_view.php'">
                    Clear
                </button>
            </form>
        </section>

        <!-- RESULTS – only after search -->
        <?php if ($hasFilter): ?>
            <section>
                <?php if ($result && mysqli_num_rows($result) > 0): ?>
                    <?php while ($row = mysqli_fetch_assoc($result)): ?>
                        <a href="patient.php?id=<?= $row['id'] ?>" class="patient-card-link">
                            <div class="patient-result-card">
                                <div class="row">
                                    <!-- LEFT SIDE: pat id, name, age, mobile, city -->
                                    <div class="col-8 col-sm-9">
                                        <strong class="d-block mb-1">
                                            PAT<?= $row['id'] ?> – <?= htmlspecialchars($row['name']) ?>
                                        </strong>
                                        <div>Age: <?= $row['age'] !== null && $row['age'] !== '' ? htmlspecialchars($row['age']) : '-' ?></div>
                                        <div>Mobile: <?= htmlspecialchars($row['mobile_no'] ?: '-') ?></div>
                                        <div>City: <?= htmlspecialchars($row['city'] ?: '-') ?></div>
                                    </div>

                                    <!-- RIGHT SIDE: visit date -->
                                    <div class="col-4 col-sm-3 text-end">
                                        <div class="visit-label">Visit Date</div>
                                        <div class="visit-date">
                                            <?php
                                            if (!empty($row['visitor_date'])) {
                                                echo date('d M Y', strtotime($row['visitor_date']));
                                            } else {
                                                echo '-';
                                            }
                                            ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </a>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="mt-3" style="color: var(--text-muted);">
                        No records found for this search.
                    </div>
                <?php endif; ?>
            </section>
        <?php endif; ?>

    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Auto-submit when date inputs change
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
