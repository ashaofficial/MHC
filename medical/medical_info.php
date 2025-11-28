<?php
include "../secure/db.php";

// ---------- Helpers ----------
function h($v) {
    return htmlspecialchars((string)$v ?? '', ENT_QUOTES, 'UTF-8');
}

// ---------- Get patient ----------
$patient_id = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;
if ($patient_id <= 0) {
    die("Invalid patient id.");
}

$patient = null;
$stmt = mysqli_prepare($conn, "SELECT id, name, consultant_doctor FROM patients WHERE id = ?");
mysqli_stmt_bind_param($stmt, "i", $patient_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$patient = mysqli_fetch_assoc($res);
mysqli_stmt_close($stmt);

if (!$patient) {
    die("Patient not found.");
}

// ---------- Load existing medical info (if any) ----------
$med = null;
$stmt = mysqli_prepare($conn, "SELECT * FROM medical_information WHERE patient_id = ?");
mysqli_stmt_bind_param($stmt, "i", $patient_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$med = mysqli_fetch_assoc($res);
mysqli_stmt_close($stmt);

// Initialize fields from DB or empty
$main_complaints      = $med['main_complaints']      ?? '';
$other_complaint_1    = $med['other_complaint_1']    ?? '';
$other_complaint_2    = $med['other_complaint_2']    ?? '';
$other_complaint_3    = $med['other_complaint_3']    ?? '';

$medicine_1           = $med['medicine_1']           ?? '';
$medicine_1_date      = $med['medicine_1_date']      ?? '';
$medicine_2           = $med['medicine_2']           ?? '';
$medicine_2_date      = $med['medicine_2_date']      ?? '';
$medicine_3           = $med['medicine_3']           ?? '';
$medicine_3_date      = $med['medicine_3_date']      ?? '';

$pre_case_file        = $med['pre_case_file']        ?? '';
$case_file            = $med['case_file']            ?? '';
$report_file          = $med['report_file']          ?? '';

$errors  = [];
$success = false;

// ---------- Handle POST ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Text fields
    $main_complaints   = trim($_POST['main_complaints'] ?? '');
    $other_complaint_1 = trim($_POST['other_complaint_1'] ?? '');
    $other_complaint_2 = trim($_POST['other_complaint_2'] ?? '');
    $other_complaint_3 = trim($_POST['other_complaint_3'] ?? '');

    $medicine_1      = trim($_POST['medicine_1'] ?? '');
    $medicine_1_date = trim($_POST['medicine_1_date'] ?? '');
    $medicine_2      = trim($_POST['medicine_2'] ?? '');
    $medicine_2_date = trim($_POST['medicine_2_date'] ?? '');
    $medicine_3      = trim($_POST['medicine_3'] ?? '');
    $medicine_3_date = trim($_POST['medicine_3_date'] ?? '');

    // ---------- File uploads ----------
    $upload_dir = __DIR__ . "/uploads/medical/" . $patient_id;
    $upload_rel = "uploads/medical/" . $patient_id; // for DB

    if (!is_dir($upload_dir)) {
        @mkdir($upload_dir, 0777, true);
    }

    $allowed_ext = ['pdf','doc','docx','jpg','jpeg','png'];

    function handle_file($field, $current_value, $upload_dir, $upload_rel, $allowed_ext) {
        if (!isset($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
            return $current_value;
        }
        if ($_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
            return $current_value;
        }
        $name = $_FILES[$field]['name'];
        $tmp  = $_FILES[$field]['tmp_name'];

        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed_ext)) {
            return $current_value;
        }

        $safe_name = $field . "_" . time() . "_" . preg_replace('/[^a-zA-Z0-9_\.-]/', '_', $name);
        $dest_abs  = $upload_dir . "/" . $safe_name;
        $dest_rel  = $upload_rel . "/" . $safe_name;

        if (move_uploaded_file($tmp, $dest_abs)) {
            return $dest_rel;
        }
        return $current_value;
    }

    $pre_case_file = handle_file('pre_case_file', $pre_case_file, $upload_dir, $upload_rel, $allowed_ext);
    $case_file     = handle_file('case_file',     $case_file,     $upload_dir, $upload_rel, $allowed_ext);
    $report_file   = handle_file('report_file',   $report_file,   $upload_dir, $upload_rel, $allowed_ext);

    if (empty($errors)) {
        if ($med) {
            // --- UPDATE ---
            $sql = "UPDATE medical_information SET
                        main_complaints   = ?,
                        other_complaint_1 = ?,
                        other_complaint_2 = ?,
                        other_complaint_3 = ?,
                        medicine_1        = ?,
                        medicine_1_date   = NULLIF(?, ''),
                        medicine_2        = ?,
                        medicine_2_date   = NULLIF(?, ''),
                        medicine_3        = ?,
                        medicine_3_date   = NULLIF(?, ''),
                        pre_case_file     = ?,
                        case_file         = ?,
                        report_file       = ?
                    WHERE patient_id = ?";

            $stmt = mysqli_prepare($conn, $sql);
            if ($stmt) {
                $types = str_repeat('s', 13) . 'i';
                mysqli_stmt_bind_param(
                    $stmt,
                    $types,
                    $main_complaints,
                    $other_complaint_1,
                    $other_complaint_2,
                    $other_complaint_3,
                    $medicine_1,
                    $medicine_1_date,
                    $medicine_2,
                    $medicine_2_date,
                    $medicine_3,
                    $medicine_3_date,
                    $pre_case_file,
                    $case_file,
                    $report_file,
                    $patient_id
                );
                if (mysqli_stmt_execute($stmt)) {
                    $success = true;
                } else {
                    $errors[] = "Update error: " . mysqli_stmt_error($stmt);
                }
                mysqli_stmt_close($stmt);
            } else {
                $errors[] = "Prepare failed: " . mysqli_error($conn);
            }
        } else {
            // --- INSERT ---
            $sql = "INSERT INTO medical_information (
                        patient_id,
                        main_complaints,
                        other_complaint_1,
                        other_complaint_2,
                        other_complaint_3,
                        medicine_1,
                        medicine_1_date,
                        medicine_2,
                        medicine_2_date,
                        medicine_3,
                        medicine_3_date,
                        pre_case_file,
                        case_file,
                        report_file
                    ) VALUES (
                        ?, ?, ?, ?, ?, ?, NULLIF(?, ''), ?, NULLIF(?, ''), ?, NULLIF(?, ''), ?, ?, ?
                    )";

            $stmt = mysqli_prepare($conn, $sql);
            if ($stmt) {
                $types = 'i' . str_repeat('s', 13);
                mysqli_stmt_bind_param(
                    $stmt,
                    $types,
                    $patient_id,
                    $main_complaints,
                    $other_complaint_1,
                    $other_complaint_2,
                    $other_complaint_3,
                    $medicine_1,
                    $medicine_1_date,
                    $medicine_2,
                    $medicine_2_date,
                    $medicine_3,
                    $medicine_3_date,
                    $pre_case_file,
                    $case_file,
                    $report_file
                );
                if (mysqli_stmt_execute($stmt)) {
                    $success = true;
                } else {
                    $errors[] = "Insert error: " . mysqli_stmt_error($stmt);
                }
                mysqli_stmt_close($stmt);
            } else {
                $errors[] = "Prepare failed: " . mysqli_error($conn);
            }
        }
    }
}

// For header date (today, shown as calendar-style, read-only)
$today_date_value = date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Medical Information - <?= h($patient['name']) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Font Awesome Icons -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">


    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Common styles -->
    <link rel="stylesheet" href="../css/common.css">
    <!-- Medical info styling -->
    <link rel="stylesheet" href="../css/medical.css">
</head>
<body>

<div class="med-page-wrapper container py-3">
    <div>

        <!-- HEADER -->
        <div class="med-header d-flex justify-content-between align-items-start mb-3">
            <div class="med-header-left">
                <div class="med-name-row">
                    <span class="med-patient-name"><?= h($patient['name']) ?></span>
                    <span class="med-label">Consultant:</span>
                    <span class="med-value"><?= h($patient['consultant_doctor'] ?: 'N/A') ?></span>
                </div>
                <div class="med-sub-row mt-1">
                    <span class="med-patient-id">Patient ID: PAT<?= $patient['id'] ?></span>
                </div>
            </div>
            <div class="med-header-right text-end">
                <div class="mb-1">
                    <label class="form-label mb-1 med-label-spacing">Entry Date</label>
                    <input type="date" class="form-control form-control-sm med-date-display"
                           value="<?= $today_date_value ?>" readonly>
                </div>
                
            </div>
        </div>

        <?php if ($success && empty($errors)): ?>
            <div class="alert alert-success py-2">Medical information saved successfully.</div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger py-2">
                <ul class="mb-0">
                    <?php foreach ($errors as $err): ?>
                        <li><?= h($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="post" enctype="multipart/form-data" class="med-form">

            <!-- Row: Main complaints | Other complaints -->
            <section class="med-section">
                <div class="row g-3">
                    <!-- Main complaints -->
                    <div class="col-md-6">
                        <label class="form-label">Main complaints</label>
                        <textarea
                            name="main_complaints"
                            rows="5"
                            class="form-control"
                        ><?= h($main_complaints) ?></textarea>
                    </div>

                    <!-- Other complaints (vertical 1,2,3) -->
                    <div class="col-md-6">
                        <label class="form-label">Other complaints</label>
                        <div class="other-complaints-list">
                            <div class="d-flex align-items-center mb-2">
                                <span class="oc-number">1.</span>
                                <input type="text" name="other_complaint_1" class="form-control"
                                       value="<?= h($other_complaint_1) ?>">
                            </div>
                            <div class="d-flex align-items-center mb-2">
                                <span class="oc-number">2.</span>
                                <input type="text" name="other_complaint_2" class="form-control"
                                       value="<?= h($other_complaint_2) ?>">
                            </div>
                            <div class="d-flex align-items-center">
                                <span class="oc-number">3.</span>
                                <input type="text" name="other_complaint_3" class="form-control"
                                       value="<?= h($other_complaint_3) ?>">
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Row: Medicines | Case file details -->
            <section class="med-section">
                <div class="row g-4">
                    <!-- LEFT: Medicines -->
                    <div class="col-md-6">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <h5 class="med-section-title mb-0">Prescribed Medicines Name</h5>
                        </div>

                        <div class="med-medicine-row mb-2">
                            <span class="med-row-number">1.</span>
                            <input type="text" name="medicine_1" class="form-control me-2"
                                   placeholder="Medicine name"
                                   value="<?= h($medicine_1) ?>">
                            <input type="date" name="medicine_1_date" class="form-control"
                                   value="<?= h($medicine_1_date) ?>">
                        </div>

                        <div class="med-medicine-row mb-2">
                            <span class="med-row-number">2.</span>
                            <input type="text" name="medicine_2" class="form-control me-2"
                                   placeholder="Medicine name"
                                   value="<?= h($medicine_2) ?>">
                            <input type="date" name="medicine_2_date" class="form-control"
                                   value="<?= h($medicine_2_date) ?>">
                        </div>

                        <div class="med-medicine-row">
                            <span class="med-row-number">3.</span>
                            <input type="text" name="medicine_3" class="form-control me-2"
                                   placeholder="Medicine name"
                                   value="<?= h($medicine_3) ?>">
                            <input type="date" name="medicine_3_date" class="form-control"
                                   value="<?= h($medicine_3_date) ?>">
                        </div>
                    </div>

                    <!-- RIGHT: Case files -->
                    <div class="col-md-6">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <h5 class="med-section-title mb-0">Case File Details</h5>
                        </div>

                     <!-- 1. Pre case -->
<div class="med-file-row mb-2">
    <span class="med-row-number">1.</span>
    <div class="flex-grow-1">
        <div class="med-file-inline">
            <span class="med-file-label">Pre case take </span>
            <input type="file" name="pre_case_file"
                   class="form-control form-control-sm med-file-input">
            <?php if ($pre_case_file): ?>
                <a href="<?= h($pre_case_file) ?>" target="_blank"
                   class="med-file-icon" title="Open file">
                   <i class="fa-solid fa-file-pdf"></i>
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- 2. Case take file -->
<div class="med-file-row mb-2">
    <span class="med-row-number">2.</span>
    <div class="flex-grow-1">
        <div class="med-file-inline">
            <span class="med-file-label">Case take file </span>
            <input type="file" name="case_file"
                   class="form-control form-control-sm med-file-input">
            <?php if ($case_file): ?>
                <a href="<?= h($case_file) ?>" target="_blank"
                   class="med-file-icon" title="Open file">
                   <i class="fa-solid fa-file-pdf"></i>
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- 3. Reports -->
<div class="med-file-row">
    <span class="med-row-number">3.</span>
    <div class="flex-grow-1">
        <div class="med-file-inline">
            <span class="med-file-label">Reports file </span>
            <input type="file" name="report_file"
                   class="form-control form-control-sm med-file-input">
            <?php if ($report_file): ?>
                <a href="<?= h($report_file) ?>" target="_blank"
                   class="med-file-icon" title="Open file">
                   <i class="fa-solid fa-file-pdf"></i>
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>

            </section>

            <!-- Actions -->
            <div class="d-flex justify-content-end gap-2 mt-3">
                <a href="patient.php?id=<?= $patient['id'] ?>" class="btn btn-outline-secondary">
                    Cancel
                </a>
                <button type="submit" class="btn btn-success">
                    Save Medical Info
                </button>
            </div>

        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
