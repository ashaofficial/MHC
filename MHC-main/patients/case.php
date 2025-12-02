<?php

session_start();
include "../secure/db.php";

function h($v)
{
    return htmlspecialchars((string)$v ?? '', ENT_QUOTES, 'UTF-8');
}

// Get patient and case IDs
$patientId = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;
$caseId    = isset($_GET['case_id']) ? (int)$_GET['case_id'] : (isset($_GET['id']) ? (int)$_GET['id'] : 0);

// If only case id provided, fetch patient_id from that case
if ($patientId <= 0 && $caseId > 0) {
    $stmtTemp = $conn->prepare("SELECT patient_id FROM cases WHERE id = ? LIMIT 1");
    if ($stmtTemp) {
        $stmtTemp->bind_param("i", $caseId);
        $stmtTemp->execute();
        $rTemp = $stmtTemp->get_result();
        if ($rowTemp = $rTemp->fetch_assoc()) {
            $patientId = (int)$rowTemp['patient_id'];
        }
        $stmtTemp->close();
    }
}

if ($patientId <= 0) {
    die("Invalid patient ID.");
}

$stmt = $conn->prepare("SELECT * FROM patients WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $patientId);
$stmt->execute();
$res = $stmt->get_result();
$patient = $res->fetch_assoc();
$stmt->close();

if (!$patient) {
    die("Patient not found.");
}

// Fetch consultants
$consultants = [];
$sqlCons = "
    SELECT c.id, u.name
    FROM consultants c
    INNER JOIN users u ON c.user_id = u.id
    WHERE c.status = 'active'
    ORDER BY u.name ASC
";
$resCons = $conn->query($sqlCons);
while ($row = $resCons->fetch_assoc()) {
    $consultants[$row['id']] = $row['name'];
}

// Load case data
$case   = null;
if ($caseId > 0) {
    $stmtCase = $conn->prepare("SELECT * FROM cases WHERE id = ? LIMIT 1");
    $stmtCase->bind_param("i", $caseId);
    $stmtCase->execute();
    $resCase = $stmtCase->get_result();
    if ($resCase->num_rows > 0) {
        $case = $resCase->fetch_assoc();
        $caseId = (int)$case['id'];
    } else {
        $case   = null;
        $caseId = 0;
    }
    $stmtCase->close();
} else {
    $sqlCase = "
        SELECT *
        FROM cases
        WHERE patient_id = ?
        ORDER BY visit_date DESC
        LIMIT 1
    ";
    $stmtCase = $conn->prepare($sqlCase);
    $stmtCase->bind_param("i", $patientId);
    $stmtCase->execute();
    $resCase = $stmtCase->get_result();
    if ($resCase->num_rows > 0) {
        $case   = $resCase->fetch_assoc();
        $caseId = (int)$case['id'];
    }
    $stmtCase->close();
}

// Set form variables
$visitDate      = $case['visit_date']         ?? date('Y-m-d H:i:s');
$consultantId   = isset($case['consultant_id'])
    ? (int)$case['consultant_id']
    : (count($consultants) ? (int)array_key_first($consultants) : 0);
$chiefComplaint = $case['chief_complaint']    ?? '';
$other1         = $case['other_complaint_1']  ?? '';
$other2         = $case['other_complaint_2']  ?? '';
$other3         = $case['other_complaint_3']  ?? '';
$summary        = $case['summary']            ?? '';
$status         = $case['status']             ?? 'open';
$nextFollowup   = $case['next_followup_date'] ?? '';

$errors  = [];
$success = false;

// Check if patient_files table exists
$hasPatientFiles = false;
if ($result = $conn->query("SHOW TABLES LIKE 'patient_files'")) {
    $hasPatientFiles = $result->num_rows > 0;
    $result->close();
}

// Handle POST to save case
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postCaseId     = isset($_POST['case_id']) ? (int)$_POST['case_id'] : 0;
    $postConsultant = isset($_POST['consultant_id']) ? (int)$_POST['consultant_id'] : 0;
    $postChief      = trim($_POST['chief_complaint'] ?? '');
    $postOther1     = trim($_POST['other_complaint_1'] ?? '');
    $postOther2     = trim($_POST['other_complaint_2'] ?? '');
    $postOther3     = trim($_POST['other_complaint_3'] ?? '');
    $postSummary    = trim($_POST['summary'] ?? '');
    $postStatus     = $_POST['status'] ?? 'open';
    $postNextFU     = !empty($_POST['next_followup_date']) ? $_POST['next_followup_date'] : null;

    if (!$postConsultant || !isset($consultants[$postConsultant])) {
        $errors[] = "Please select a consultant.";
    }
    if ($postChief === '') {
        $errors[] = "Chief complaint is required.";
    }

    if (empty($errors)) {
        if ($postCaseId > 0) {
            // UPDATE existing case
            $sqlUpd = "
                UPDATE cases
                SET consultant_id      = ?,
                    chief_complaint    = ?,
                    other_complaint_1  = ?,
                    other_complaint_2  = ?,
                    other_complaint_3  = ?,
                    summary            = ?,
                    status             = ?,
                    next_followup_date = ?
                WHERE id = ?
            ";
            $stmtUpd = $conn->prepare($sqlUpd);
            if (!$stmtUpd) {
                $errors[] = "DB error (prepare update): " . $conn->error;
            } else {
                $stmtUpd->bind_param(
                    "isssssssi",
                    $postConsultant,
                    $postChief,
                    $postOther1,
                    $postOther2,
                    $postOther3,
                    $postSummary,
                    $postStatus,
                    $postNextFU,
                    $postCaseId
                );
                if (!$stmtUpd->execute()) {
                    $errors[] = "DB error (update): " . $stmtUpd->error;
                } else {
                    $success = true;
                    $caseId = $postCaseId;
                }
                $stmtUpd->close();
            }
        } else {
            // INSERT new case
            $sqlIns = "
                INSERT INTO cases (
                    consultant_id, patient_id,
                    chief_complaint, other_complaint_1, other_complaint_2, other_complaint_3,
                    summary, status, next_followup_date
                ) VALUES (
                    ?, ?,
                    ?, ?, ?, ?,
                    ?, ?, ?
                )
            ";
            $stmtIns = $conn->prepare($sqlIns);
            if (!$stmtIns) {
                $errors[] = "DB error (prepare insert): " . $conn->error;
            } else {
                $stmtIns->bind_param(
                    "iisssssss",
                    $postConsultant,
                    $patientId,
                    $postChief,
                    $postOther1,
                    $postOther2,
                    $postOther3,
                    $postSummary,
                    $postStatus,
                    $postNextFU
                );
                if (!$stmtIns->execute()) {
                    $errors[] = "DB error (insert): " . $stmtIns->error;
                } else {
                    $success = true;
                    $caseId = $stmtIns->insert_id;
                }
                $stmtIns->close();
            }
        }

        // ----------------- NEW: ensure consultant_name and patient_name are saved -----------------
        if (empty($errors) && $caseId > 0) {
            // fetch consultant name
            $consultantName = null;
            if ($postConsultant > 0) {
                $stmtC = $conn->prepare("
                    SELECT u.name
                    FROM consultants cons
                    JOIN users u ON cons.user_id = u.id
                    WHERE cons.id = ? LIMIT 1
                ");
                if ($stmtC) {
                    $stmtC->bind_param("i", $postConsultant);
                    $stmtC->execute();
                    $rC = $stmtC->get_result();
                    if ($rowC = $rC->fetch_assoc()) {
                        $consultantName = $rowC['name'];
                    }
                    $stmtC->close();
                }
            }

            // fetch patient name
            $patientName = null;
            $stmtP = $conn->prepare("SELECT name FROM patients WHERE id = ? LIMIT 1");
            if ($stmtP) {
                $stmtP->bind_param("i", $patientId);
                $stmtP->execute();
                $rP = $stmtP->get_result();
                if ($rowP = $rP->fetch_assoc()) {
                    $patientName = $rowP['name'];
                }
                $stmtP->close();
            }

            // check if either name exists and update the cases row (only if columns exist)
            $rcol = $conn->query("SHOW COLUMNS FROM cases LIKE 'consultant_name'");
            $hasConsultantNameCol = ($rcol && $rcol->num_rows > 0);
            if ($rcol) $rcol->close();
            $rcol2 = $conn->query("SHOW COLUMNS FROM cases LIKE 'patient_name'");
            $hasPatientNameCol = ($rcol2 && $rcol2->num_rows > 0);
            if ($rcol2) $rcol2->close();

            if ($hasConsultantNameCol || $hasPatientNameCol) {
                // build dynamic update
                $sets = [];
                $types = "";
                $params = [];
                if ($hasConsultantNameCol) {
                    $sets[] = "consultant_name = ?";
                    $types .= "s";
                    $params[] = $consultantName ?? "";
                }
                if ($hasPatientNameCol) {
                    $sets[] = "patient_name = ?";
                    $types .= "s";
                    $params[] = $patientName ?? "";
                }
                $types .= "i";
                $params[] = $caseId;

                $sqlUpdNames = "UPDATE cases SET " . implode(", ", $sets) . " WHERE id = ?";
                $stmtUpdNames = $conn->prepare($sqlUpdNames);
                if ($stmtUpdNames) {
                    // bind dynamically
                    $bindNames = array_merge([$types], $params);
                    // mysqli_stmt::bind_param requires references
                    $tmp = [];
                    foreach ($bindNames as $k => $v) $tmp[$k] = &$bindNames[$k];
                    call_user_func_array([$stmtUpdNames, 'bind_param'], $tmp);
                    $stmtUpdNames->execute();
                    $stmtUpdNames->close();
                }
            }
        }

        // If saved successfully, redirect to GET for PRG and show success popup
        if (empty($errors) && $caseId > 0) {
            header("Location: case.php?patient_id=" . (int)$patientId . "&case_id=" . (int)$caseId . "&saved=1");
            exit;
        }
    }
}

// Check if page was just saved
$wasSaved = isset($_GET['saved']) && $_GET['saved'] == 1;

// Fetch past cases list
$pastCases = [];
$sqlPast = "
    SELECT c.id, c.visit_date, c.chief_complaint,
           cons.id AS consultant_id, u.name AS consultant_name,
           p.name AS patient_name
    FROM cases c
    LEFT JOIN consultants cons ON c.consultant_id = cons.id
    LEFT JOIN users u ON cons.user_id = u.id
    LEFT JOIN patients p ON c.patient_id = p.id
    WHERE c.patient_id = ?
    ORDER BY c.visit_date DESC
";
$stmtPast = $conn->prepare($sqlPast);
if ($stmtPast) {
    $stmtPast->bind_param("i", $patientId);
    $stmtPast->execute();
    $rPast = $stmtPast->get_result();
    while ($r = $rPast->fetch_assoc()) {
        $pastCases[] = $r;
    }
    $stmtPast->close();
}

// Check if user wants to create a new case
$isNewCase = isset($_GET['new_case']) && $_GET['new_case'] == 1;

if ($isNewCase) {
    // Reset form for new case
    $case = null;
    $caseId = 0;
    $visitDate = date('Y-m-d H:i:s');
    $consultantId = count($consultants) ? (int)array_key_first($consultants) : 0;
    $chiefComplaint = '';
    $other1 = '';
    $other2 = '';
    $other3 = '';
    $summary = '';
    $status = 'open';
    $nextFollowup = '';
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Case Details</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/common.css">
    <link rel="stylesheet" href="../css/case.css">
</head>

<body>

    <div class="case-page-wrapper">

        <!-- SUCCESS MODAL -->
        <div class="modal fade" id="successModal" tabindex="-1" backdrop="static" keyboard="false">
            <div class="modal-dialog modal-dialog-centered modal-sm">
                <div class="modal-content border-0 shadow-lg">
                    <div class="modal-header bg-success text-white border-0">
                        <h5 class="modal-title fw-bold">
                            <i class="fas fa-check-circle me-2"></i>Success
                        </h5>
                    </div>
                    <div class="modal-body text-center py-5">
                        <div style="animation: scaleIn 0.5s ease-out;">
                            <i class="fas fa-check-circle text-success" style="font-size: 4rem; margin-bottom: 20px;"></i>
                        </div>
                        <h6 class="mt-3 text-dark fw-600">Case Saved Successfully!</h6>
                        <p class="text-muted small mt-2 mb-0">Case #<?= $caseId; ?> has been saved for patient <?= h($patient['name']); ?></p>
                    </div>
                    <div class="modal-footer border-0 justify-content-center pb-4">
                        <button type="button" class="btn btn-success btn-sm px-4" data-bs-dismiss="modal">
                            <i class="fas fa-check me-1"></i>OK
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- FILE UPLOAD SUCCESS MODAL -->
        <div class="modal fade" id="fileUploadModal" tabindex="-1" backdrop="static" keyboard="false">
            <div class="modal-dialog modal-dialog-centered modal-sm">
                <div class="modal-content border-0 shadow-lg">
                    <div class="modal-header bg-info text-white border-0">
                        <h5 class="modal-title fw-bold">
                            <i class="fas fa-file-check me-2"></i>File Uploaded
                        </h5>
                    </div>
                    <div class="modal-body text-center py-4">
                        <div style="animation: scaleIn 0.5s ease-out;">
                            <i class="fas fa-file-check text-info" style="font-size: 3.5rem; margin-bottom: 15px;"></i>
                        </div>
                        <h6 class="mt-3 text-dark fw-600" id="uploadFileName">File Uploaded</h6>
                        <p class="text-muted small mt-2 mb-0" id="uploadFileSize">Successfully saved to case records</p>
                    </div>
                    <div class="modal-footer border-0 justify-content-center pb-4">
                        <button type="button" class="btn btn-info btn-sm px-4" data-bs-dismiss="modal">
                            <i class="fas fa-check me-1"></i>OK
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- HEADER CARD -->
        <div class="case-header-card mb-3">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <div class="case-title">
                        <i class="fas fa-file-medical me-2"></i>Case Details
                    </div>
                    <div class="case-subline">
                        <span class="case-patient-name"><?= h($patient['name']); ?></span>
                        <span class="case-pipe">|</span>
                        <span class="case-patient-id">PAT-<?= (int)$patient['id']; ?></span>
                    </div>
                </div>
                <div class="text-end">
                    <div class="case-status-label">
                        <i class="fas fa-circle-info me-1"></i>STATUS
                    </div>
                    <?php
                    $statusClass = "case-status-open";
                    $statusIcon  = "fa-circle-dot";
                    if ($status === "in_progress") {
                        $statusClass = "case-status-progress";
                        $statusIcon  = "fa-spinner";
                    }
                    if ($status === "closed") {
                        $statusClass = "case-status-closed";
                        $statusIcon  = "fa-check-circle";
                    }
                    ?>
                    <span class="case-status-pill <?= $statusClass ?>">
                        <i class="fas <?= $statusIcon ?> me-1"></i><?= strtoupper(str_replace('_', ' ', $status)); ?>
                    </span>
                    <div class="case-meta-small mt-2">
                        Case: <?= $caseId ? "#" . $caseId : "New Record"; ?>
                    </div>
                    <div class="case-meta-small">
                        <?= h(date('M d, Y', strtotime($visitDate))); ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- ALERTS -->
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <strong>Errors Found:</strong>
                <ul class="mb-0 mt-2">
                    <?php foreach ($errors as $e): ?>
                        <li><?= h($e); ?></li>
                    <?php endforeach; ?>
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- MAIN FORM -->
        <form method="post" class="case-form-card" id="caseForm">
            <input type="hidden" name="case_id" value="<?= (int)$caseId; ?>">
            <input type="hidden" id="patientIdHidden" value="<?= (int)$patientId; ?>">
            <input type="hidden" id="caseIdHidden" value="<?= (int)$caseId; ?>">

            <!-- CASE SHEET SECTION -->
            <div class="case-section">
                <div class="case-section-header">
                    <span class="case-section-title">
                        <i class="fas fa-clipboard me-2"></i>Case Sheet
                    </span>
                </div>

                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="case-label form-label">Consultant <span class="text-danger">*</span></label>
                        <select name="consultant_id" class="form-select form-select-sm" required>
                            <option value="">-- Select Consultant --</option>
                            <?php foreach ($consultants as $cid => $cname): ?>
                                <option value="<?= (int)$cid; ?>" <?= (int)$cid == $consultantId ? 'selected' : ''; ?>>
                                    <?= h($cname); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="case-label form-label">Case Status</label>
                        <select name="status" class="form-select form-select-sm">
                            <option value="open" <?= $status === 'open' ? 'selected' : ''; ?>>
                                Open
                            </option>
                            <option value="in_progress" <?= $status === 'in_progress' ? 'selected' : ''; ?>>
                                In Progress
                            </option>
                            <option value="closed" <?= $status === 'closed' ? 'selected' : ''; ?>>
                                Closed
                            </option>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="case-label form-label">Next Follow-up Date</label>
                        <input type="date"
                            name="next_followup_date"
                            class="form-control form-control-sm"
                            value="<?= h($nextFollowup); ?>">
                        <small class="form-text text-muted">Optional</small>
                    </div>
                </div>

                <div class="mt-3">
                    <label class="case-label form-label">Chief Complaint <span class="text-danger">*</span></label>
                    <textarea name="chief_complaint"
                        class="form-control form-control-sm case-textarea"
                        required
                        placeholder="Describe the primary complaint..."><?= h($chiefComplaint); ?></textarea>
                    <small class="form-text text-muted">Required. Main reason for visit.</small>
                </div>

                <div class="row g-3 mt-2">
                    <div class="col-md-4">
                        <label class="case-label form-label">Other Complaint 1</label>
                        <textarea name="other_complaint_1"
                            class="form-control form-control-sm case-textarea-sm"
                            placeholder="Secondary complaint (optional)..."><?= h($other1); ?></textarea>
                    </div>
                    <div class="col-md-4">
                        <label class="case-label form-label">Other Complaint 2</label>
                        <textarea name="other_complaint_2"
                            class="form-control form-control-sm case-textarea-sm"
                            placeholder="Tertiary complaint (optional)..."><?= h($other2); ?></textarea>
                    </div>
                    <div class="col-md-4">
                        <label class="case-label form-label">Other Complaint 3</label>
                        <textarea name="other_complaint_3"
                            class="form-control form-control-sm case-textarea-sm"
                            placeholder="Additional complaint (optional)..."><?= h($other3); ?></textarea>
                    </div>
                </div>

                <div class="mt-3">
                    <label class="case-label form-label">Summary / Impression</label>
                    <textarea name="summary"
                        class="form-control form-control-sm case-textarea"
                        placeholder="Clinical summary, observations, and initial impressions..."><?= h($summary); ?></textarea>
                </div>
            </div>

            <!-- FILES SECTION -->
            <?php if ($hasPatientFiles): ?>
                <div class="case-section mt-4">
                    <div class="case-section-header">
                        <span class="case-section-title">
                            <i class="fas fa-file-upload me-2"></i>Case Files
                        </span>
                        <span class="case-section-hint">
                            <i class="fas fa-info-circle me-1"></i>Upload documents (optional)
                        </span>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="case-file-label">
                                <i class="fas fa-file-pdf me-1"></i>Pre Case Taking
                            </div>
                            <input type="file"
                                id="pre_case_taking"
                                name="pre_case_taking"
                                class="form-control form-control-sm file-input"
                                accept=".pdf,.doc,.docx">
                            <small class="case-file-hint">
                                <i class="fas fa-lightbulb me-1"></i>Initial questionnaire or notes
                            </small>
                            <div id="pre_case_taking_progress" class="progress mt-2" style="display:none;">
                                <div class="progress-bar progress-bar-striped progress-bar-animated bg-success" role="progressbar" style="width: 100%"></div>
                            </div>
                            <div id="pre_case_taking_status" style="display:none; margin-top:8px;"></div>
                        </div>

                        <div class="col-md-4">
                            <div class="case-file-label">
                                <i class="fas fa-file-contract me-1"></i>Case Taking
                            </div>
                            <input type="file"
                                id="case_taking"
                                name="case_taking"
                                class="form-control form-control-sm file-input"
                                accept=".pdf,.doc,.docx">
                            <small class="case-file-hint">
                                <i class="fas fa-lightbulb me-1"></i>Detailed case-taking form
                            </small>
                            <div id="case_taking_progress" class="progress mt-2" style="display:none;">
                                <div class="progress-bar progress-bar-striped progress-bar-animated bg-success" role="progressbar" style="width: 100%"></div>
                            </div>
                            <div id="case_taking_status" style="display:none; margin-top:8px;"></div>
                        </div>

                        <div class="col-md-4">
                            <div class="case-file-label">
                                <i class="fas fa-images me-1"></i>Reports & Images
                            </div>
                            <input type="file"
                                id="reports"
                                name="reports"
                                class="form-control form-control-sm file-input"
                                accept=".pdf,.jpg,.jpeg,.png,.gif,.doc,.docx">
                            <small class="case-file-hint">
                                <i class="fas fa-lightbulb me-1"></i>Lab reports, scans, or images
                            </small>
                            <div id="reports_progress" class="progress mt-2" style="display:none;">
                                <div class="progress-bar progress-bar-striped progress-bar-animated bg-success" role="progressbar" style="width: 100%"></div>
                            </div>
                            <div id="reports_status" style="display:none; margin-top:8px;"></div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- ACTION BUTTONS -->
            <div class="case-actions mt-4">
                <button type="submit" class="btn btn-success btn-sm px-5" id="saveCaseBtn">
                    <i class="fas fa-save me-2"></i>Save Case
                </button>
                <a href="case.php?patient_id=<?= (int)$patientId; ?>&new_case=1" class="btn btn-primary btn-sm px-5 ms-2">
                    <i class="fas fa-plus-circle me-2"></i>New Form
                </a>
            </div>
        </form>

    </div>

    <!-- PREVIOUS CASES LIST -->
    <div class="case-page-wrapper mt-3">
        <div class="case-section">
            <div class="case-section-header">
                <span class="case-section-title">
                    <i class="fas fa-history me-2"></i>Previous Cases
                </span>
                <span class="case-section-hint">
                    <i class="fas fa-info-circle me-1"></i><?= count($pastCases); ?> total cases for this patient
                </span>
            </div>

            <?php if (empty($pastCases)): ?>
                <div class="text-muted small">
                    <i class="fas fa-info-circle me-1"></i>No previous cases found for this patient.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 10%;">Case ID</th>
                                <th style="width: 15%;">Date</th>
                                <th style="width: 18%;">Patient</th>
                                <th style="width: 20%;">Consultant</th>
                                <th style="width: 25%;">Chief Complaint</th>
                                <th style="width: 10%; text-align: center;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pastCases as $pc): ?>
                                <tr class="align-middle">
                                    <td>
                                        <span class="badge bg-info">#<?= (int)$pc['id']; ?></span>
                                    </td>
                                    <td>
                                        <small><?= h(date('M d, Y H:i', strtotime($pc['visit_date']))); ?></small>
                                    </td>
                                    <td>
                                        <small><?= h($pc['patient_name'] ?? $patient['name']); ?></small>
                                    </td>
                                    <td>
                                        <small class="fw-600"><?= h($pc['consultant_name'] ?? 'N/A'); ?></small>
                                    </td>
                                    <td>
                                        <small class="text-muted" title="<?= h($pc['chief_complaint'] ?? ''); ?>">
                                            <?= h(mb_strimwidth($pc['chief_complaint'] ?? '', 0, 50, '...')); ?>
                                        </small>
                                    </td>
                                    <td style="text-align: center;">
                                        <a href="case.php?patient_id=<?= (int)$patientId; ?>&case_id=<?= (int)$pc['id']; ?>"
                                            class="btn btn-sm btn-outline-primary"
                                            title="Edit this case">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Show success modal if page was saved
            <?php if ($wasSaved): ?>
                const successModal = new bootstrap.Modal(document.getElementById('successModal'));
                successModal.show();
            <?php endif; ?>

            // Handle file uploads via AJAX
            const fileInputs = document.querySelectorAll('.file-input');
            fileInputs.forEach(input => {
                input.addEventListener('change', function() {
                    uploadFile(this);
                });
            });
        });

        function uploadFile(inputElement) {
            const file = inputElement.files[0];
            if (!file) return;

            const fieldName = inputElement.id;
            const caseId = document.getElementById('caseIdHidden').value || '0';
            const patientId = document.getElementById('patientIdHidden').value;
            const progressDiv = document.getElementById(fieldName + '_progress');
            const statusDiv = document.getElementById(fieldName + '_status');

            const formData = new FormData();
            formData.append('file', file);
            formData.append('field_name', fieldName);
            formData.append('file_type', fieldName);
            formData.append('case_id', caseId);
            formData.append('patient_id', patientId);

            progressDiv.style.display = 'block';
            statusDiv.style.display = 'none';

            fetch('../patients/case_ajax.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    progressDiv.style.display = 'none';

                    if (data.success) {
                        showFileUploadSuccess(fieldName, data.file_name, data.file_size);
                        inputElement.value = ''; // Clear input
                    } else {
                        showFileUploadError(fieldName, data.message || 'Upload failed');
                    }
                })
                .catch(error => {
                    progressDiv.style.display = 'none';
                    console.error('Error:', error);
                    showFileUploadError(fieldName, 'Network error occurred');
                });
        }

        function showFileUploadSuccess(fieldName, fileName, fileSize) {
            // Show success modal
            const fileUploadModal = new bootstrap.Modal(document.getElementById('fileUploadModal'));
            document.getElementById('uploadFileName').textContent = fileName;
            document.getElementById('uploadFileSize').textContent = 'Size: ' + fileSize;
            fileUploadModal.show();

            // Also show inline status
            const statusDiv = document.getElementById(fieldName + '_status');
            statusDiv.innerHTML = `
                <div class="alert alert-success py-2 px-3 mb-0" role="alert">
                    <i class="fas fa-check-circle me-1"></i>
                    <small><strong>${fileName}</strong> uploaded successfully</small>
                </div>
            `;
            statusDiv.style.display = 'block';
        }

        function showFileUploadError(fieldName, message) {
            const statusDiv = document.getElementById(fieldName + '_status');
            statusDiv.innerHTML = `
                <div class="alert alert-danger py-2 px-3 mb-0" role="alert">
                    <i class="fas fa-exclamation-circle me-1"></i>
                    <small><strong>Error:</strong> ${message}</small>
                </div>
            `;
            statusDiv.style.display = 'block';
        }

        (function() {
            var openTab = <?= json_encode($_GET['open_tab'] ?? '') ?>;
            if (!openTab) return;
            document.addEventListener('DOMContentLoaded', function() {
                // try anchor like <a href="#prescriptions"> or element with id
                var anchor = document.querySelector('a[href="#' + openTab + '"]');
                if (anchor) {
                    anchor.click();
                    return;
                }
                var el = document.getElementById(openTab) || document.querySelector('[data-tab="' + openTab + '"]');
                if (el) {
                    try {
                        el.click();
                    } catch (e) {
                        /* ignore */ }
                }
            });
        })();
    </script>
</body>

</html>