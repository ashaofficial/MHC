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

function h($v) {
    return htmlspecialchars((string)$v ?? '', ENT_QUOTES, 'UTF-8');
}
function display_or_na($v) {
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

    <!-- Common styles -->
    <link rel="stylesheet" href="../css/common.css">
    <!-- Patient specific styles -->
    <link rel="stylesheet" href="../css/patient.css">

</head>
<body>

<div class="patient-page-wrapper">

    <!-- HEADER -->
    <header class="patient-header">
        <div>
            <h2 class="patient-name mb-0"><?= h($patient['name']) ?></h2>
            <small class="text-muted">
                Patient ID: PAT<?= $patient['id'] ?>
            </small>
        </div>
        <div class="d-flex gap-2">
            <a href="patients_view.php" class="btn btn-secondary btn-sm">← Back to List</a>
        </div>
    </header>

    <!-- TABS -->
    <nav class="patient-tabs">
        <ul class="nav nav-tabs border-0" id="patientTab" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="personal-tab" data-bs-toggle="tab"
                        data-bs-target="#personal" type="button" role="tab">
                    <span class="tab-icon">👤</span> Personal Information
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="medical-tab" data-bs-toggle="tab"
                        data-bs-target="#medical" type="button" role="tab">
                    <span class="tab-icon">🩺</span> Medical Information
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="follow-tab" data-bs-toggle="tab"
                        data-bs-target="#followups" type="button" role="tab">
                    <span class="tab-icon">📅</span> Follow-ups
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
                <!-- EDIT BUTTON INSIDE PERSONAL INFO SECTION -->
                <a href="edit_patient.php?id=<?= $patient['id'] ?>" class="btn btn-edit btn-sm">
                    ✏ Edit
                </a>
            </div>

            <div class="info-grid">
                <!-- Row 1: ID | Consultant -->
                <div class="info-field">
                    <div class="field-label">Patient ID</div>
                    <div class="field-value">PAT<?= $patient['id'] ?></div>
                </div>
                <div class="info-field">
                    <div class="field-label">Consultant Name</div>
                    <div class="field-value"><?= display_or_na($patient['consultant_doctor']) ?></div>
                </div>

                <!-- Row 2: Mobile | Marital Status -->
                <div class="info-field">
                    <div class="field-label">Mobile No</div>
                    <div class="field-value"><?= display_or_na($patient['mobile_no']) ?></div>
                </div>
                <div class="info-field">
                    <div class="field-label">Marital Status</div>
                    <div class="field-value"><?= display_or_na($patient['marital_status']) ?></div>
                </div>

                <!-- Row 3: Age | Gender -->
                <div class="info-field">
                    <div class="field-label">Age</div>
                    <div class="field-value">
                        <?= ($patient['age'] !== null && $patient['age'] !== '') ? h($patient['age']) . ' years' : 'N/A' ?>
                    </div>
                </div>
                <div class="info-field">
                    <div class="field-label">Gender</div>
                    <div class="field-value"><?= display_or_na($patient['gender']) ?></div>
                </div>

                <!-- Row 4: Address | Place -->
                <div class="info-field">
                    <div class="field-label">Address</div>
                    <div class="field-value"><?= display_or_na($patient['address']) ?></div>
                </div>
                <div class="info-field">
                    <div class="field-label">Place (City)</div>
                    <div class="field-value"><?= display_or_na($patient['city']) ?></div>
                </div>

                <!-- Extra row: Father/Spouse & DOB -->
                <div class="info-field">
                    <div class="field-label">Father / Spouse Name</div>
                    <div class="field-value"><?= display_or_na($patient['father_spouse_name']) ?></div>
                </div>
                <div class="info-field">
                    <div class="field-label">Date of Birth</div>
                    <div class="field-value">
                        <?= $patient['date_of_birth'] ? h($patient['date_of_birth']) : 'N/A' ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- MEDICAL INFO TAB – shows medical_info.php inside -->
        <div class="tab-pane fade" id="medical" role="tabpanel" aria-labelledby="medical-tab">
            <iframe
                class="med-info-frame"
                src="../medical/medical_info.php?patient_id=<?= $patient['id'] ?>">
            </iframe>
        </div>

        <!-- FOLLOW-UPS TAB -->
        <div class="tab-pane fade" id="followups" role="tabpanel" aria-labelledby="follow-tab">
            <h4 class="section-title">Follow-up Details</h4>
            <p class="text-muted mb-1">
                Follow-up scheduling module not yet implemented.
            </p>
            <p class="text-muted">
                Later you can show upcoming visits, reminders, and past consultation notes here.
            </p>
        </div>

        <!-- BILLING TAB -->
        <div class="tab-pane fade" id="billing" role="tabpanel" aria-labelledby="billing-tab">
            <h4 class="section-title">Billing</h4>
            <p class="text-muted mb-1">
                Billing module not yet implemented.
            </p>
            <p class="text-muted">
                You can add invoices, payments, and balances linked to this patient in future.
            </p>
        </div>

    </div><!-- /.patient-tab-content -->

</div><!-- /.patient-page-wrapper -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Activate Bootstrap tabs
    var triggerTabList = [].slice.call(document.querySelectorAll('#patientTab button'));
    triggerTabList.forEach(function (triggerEl) {
        var tabTrigger = new bootstrap.Tab(triggerEl);
        triggerEl.addEventListener('click', function (event) {
            event.preventDefault();
            tabTrigger.show();
        });
    });
</script>
</body>
</html>
