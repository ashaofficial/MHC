<?php
include "../secure/db.php";

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    die("Invalid patient ID.");
}

$sql   = "SELECT * FROM patients WHERE id = ?";
$stmt  = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$result  = mysqli_stmt_get_result($stmt);
$patient = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$patient) {
    die("Patient not found.");
}

/* ---------- get latest case id for this patient ---------- */
$latest_case_id = 0;
if (!empty($patient['id'])) {
    $pid = (int)$patient['id'];
    // Ensure the `cases` table exists before preparing statements (prevents fatal errors)
    $tableExists = false;
    $tr = $conn->query("SHOW TABLES LIKE 'cases'");
    if ($tr && $tr->num_rows > 0) {
        $tableExists = true;
        $tr->close();
    }

    if ($tableExists) {
        $q = mysqli_prepare($conn, "SELECT id FROM cases WHERE patient_id = ? ORDER BY visit_date DESC LIMIT 1");
        if ($q) {
            mysqli_stmt_bind_param($q, 'i', $pid);
            mysqli_stmt_execute($q);
            $res = mysqli_stmt_get_result($q);
            $row = mysqli_fetch_assoc($res);
            if ($row && isset($row['id'])) $latest_case_id = (int)$row['id'];
            mysqli_stmt_close($q);
        }
    } else {
        // cases table not present; leave $latest_case_id = 0
    }
}

function h($v)
{
    return htmlspecialchars((string)$v ?? '', ENT_QUOTES, 'UTF-8');
}
function display_or_na($v)
{
    $v = trim((string)$v);
    return $v === '' ? 'N/A' : h($v);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title><?= h($patient['name']) ?> - Patient Profile</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Common styles (unchanged link) -->
    <link rel="stylesheet" href="../css/common.css">
    <!-- Patient specific styles (unchanged link) -->
    <link rel="stylesheet" href="../css/patient.css">

    <style>
        /* Make inner tabs (case, followups, billing...) full width + tall */
        .inner-tab-frame {
            width: 100%;
            height: 680px;
            /* adjust if you want more/less height */
            border: 0;
            overflow: auto;
        }
    </style>
</head>

<body>



    <!-- HEADER (Name + Back button) -->
    <header class="patient-header">
        <div class="header-left-content">
            <span class="patient-name-inline"><?= h($patient['name']) ?></span>
            <span class="header-divider">|</span>
            <span class="patient-id-inline">PAT<?= (int)$patient['id'] ?></span>
        </div>
        <a href="patients_view.php" class="btn btn-back-list btn-sm">← Back to List</a>
    </header>

    <!-- TABS STRIP -->
    <nav class="patient-tabs">
        <ul class="nav nav-tabs border-0" id="patientTab" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="personal-tab" data-bs-toggle="tab"
                    data-bs-target="#personal" type="button" role="tab">
                    <span class="tab-icon">👤</span> Personal Information
                </button>
            </li>

            <li class="nav-item" role="presentation">
                <button class="nav-link" id="case_-tab" data-bs-toggle="tab"
                    data-bs-target="#case" type="button" role="tab">
                    <span class="tab-icon">🩺</span> case details
                </button>
            </li>

            <li class="nav-item" role="presentation">
                <button class="nav-link" id="prescriptions-tab" data-bs-toggle="tab"
                    data-bs-target="#prescriptions" type="button" role="tab"
                    aria-controls="prescriptions" aria-selected="false">
                    <span class="tab-icon">💊</span> Prescriptions
                </button>
            </li>

            <!-- NEW: Case Update tab -->
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="case_update-tab" data-bs-toggle="tab"
                    data-bs-target="#case_update" type="button" role="tab" aria-controls="case_update" aria-selected="false">
                    <span class="tab-icon">📝</span> Follow-up
                </button>
            </li>

            <li class="nav-item" role="presentation">
                <button class="nav-link" id="billing-tab" data-bs-toggle="tab"
                    data-bs-target="#billing" type="button" role="tab">
                    <span class="tab-icon">💳</span> Billing
                </button>
            </li>
        </ul>
    </nav>

    <!-- TAB CONTENT -->
    <div class="tab-content patient-tab-content" id="patientTabContent">

        <!-- PERSONAL INFO TAB -->
        <div class="tab-pane fade show active" id="personal" role="tabpanel" aria-labelledby="personal-tab">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h4 class="section-title mb-0">Personal Information</h4>
                <a href="edit_patient.php?id=<?= (int)$patient['id'] ?>" class="btn btn-edit">
                    ✏ Edit
                </a>
            </div>

            <div class="info-grid">
                <!-- Row 1: ID | Blood Group -->
                <div class="info-field">
                    <div class="field-label">PATIENT ID</div>
                    <div class="field-value">PAT<?= (int)$patient['id'] ?></div>
                </div>
                <div class="info-field">
                    <div class="field-label">BLOOD GROUP</div>
                    <div class="field-value"><?= display_or_na($patient['blood_group']) ?></div>
                </div>

                <!-- Row 2: Mobile | Marital -->
                <div class="info-field">
                    <div class="field-label">MOBILE NO</div>
                    <div class="field-value"><?= display_or_na($patient['mobile_no']) ?></div>
                </div>
                <div class="info-field">
                    <div class="field-label">MARITAL STATUS</div>
                    <div class="field-value"><?= display_or_na($patient['marital_status']) ?></div>
                </div>

                <!-- Row 3: Age | Gender -->
                <div class="info-field">
                    <div class="field-label">AGE</div>
                    <div class="field-value">
                        <?= ($patient['age'] !== null && $patient['age'] !== '') ? h($patient['age']) . ' years' : 'N/A' ?>
                    </div>
                </div>
                <div class="info-field">
                    <div class="field-label">GENDER</div>
                    <div class="field-value"><?= display_or_na($patient['gender']) ?></div>
                </div>

                <!-- Row 4: Father / Spouse | DOB -->
                <div class="info-field">
                    <div class="field-label">FATHER / SPOUSE NAME</div>
                    <div class="field-value"><?= display_or_na($patient['father_spouse_name']) ?></div>
                </div>
                <div class="info-field">
                    <div class="field-label">DATE OF BIRTH</div>
                    <div class="field-value">
                        <?= $patient['date_of_birth'] ? h($patient['date_of_birth']) : 'N/A' ?>
                    </div>
                </div>

                <!-- Row 5 (bottom): Address | Place -->
                <div class="info-field">
                    <div class="field-label">ADDRESS</div>
                    <div class="field-value"><?= display_or_na($patient['address']) ?></div>
                </div>
                <div class="info-field">
                    <div class="field-label">PLACE (CITY)</div>
                    <div class="field-value"><?= display_or_na($patient['city']) ?></div>
                </div>
            </div>
        </div>

        <!-- CASE TAB – full width iframe -->
        <div class="tab-pane fade" id="case" role="tabpanel" aria-labelledby="case_-tab">
            <iframe
                class="inner-tab-frame"
                src="../cases/case.php?patient_id=<?= (int)$patient['id'] ?>"></iframe>
        </div>

        <!-- PRESCRIPTIONS TAB – full width iframe -->
        <div class="tab-pane fade" id="prescriptions" role="tabpanel" aria-labelledby="prescriptions-tab">
            <iframe src="../medical/prescriptions.php?patient_id=<?= (int)$patient['id'] ?><?php if ($latest_case_id > 0) echo '&case_id=' . (int)$latest_case_id; ?>" class="inner-tab-frame"></iframe>
        </div>

        <!-- NEW: Case Update pane -->
        <div class="tab-pane fade" id="case_update" role="tabpanel" aria-labelledby="case_update-tab">
            <?php if ($latest_case_id > 0): ?>
                <iframe class="inner-tab-frame" src="../follow-up/case_update.php?case_id=<?= (int)$latest_case_id ?>"></iframe>
            <?php else: ?>
                <div style="padding:20px; background:#fff; border-radius:8px;">
                    <p><strong>No case found for this patient yet.</strong></p>
                    <p>Create a case first (from "case details") or <a href="case_update.php?patient_id=<?= (int)$patient['id'] ?>">click here to add one</a>.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- BILLING TAB (you can wire later) -->
        <div class="tab-pane fade" id="billing" role="tabpanel" aria-labelledby="billing-tab">
            <p class="text-muted small m-3">
                Billing page not implemented yet. You can load <code>billing_patient.php?id=<?= (int)$patient['id']; ?></code> here.
            </p>
        </div>

    </div> <!-- /tab-content -->


    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>