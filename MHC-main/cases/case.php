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
// Prescribed medicines (two entries)
$med1Name       = $case['medicine_name1']     ?? '';
$med1Date       = $case['medicine_date1']     ?? '';
$med2Name       = $case['medicine_name2']     ?? '';
$med2Date       = $case['medicine_date2']     ?? '';
// Re-analysis and Re-case fields (single entry shown by default)
$reanalysisReason     = $case['reanalysis_reason']     ?? '';
$reanalysisConclusion = $case['reanalysis_conclusion'] ?? '';
$reanalysisMedName    = $case['reanalysis_medicine_name'] ?? '';
$reanalysisMedDate    = $case['reanalysis_medicine_date'] ?? '';
$recaseReason         = $case['recase_reason']         ?? '';
$recaseMedName        = $case['recase_medicine_name']  ?? '';
$recaseMedDate        = $case['recase_medicine_date']  ?? '';

// Fetch re-analysis 2 and 3 records from reanalysis table
$reanalysis2Data = null;
$reanalysis3Data = null;
if ($caseId > 0) {
    $stmtRA = $conn->prepare("
        SELECT reason, conclusion, medicine_name, medicine_date 
        FROM reanalysis 
        WHERE case_id = ? 
        ORDER BY id ASC 
        LIMIT 2
    ");
    if ($stmtRA) {
        $stmtRA->bind_param("i", $caseId);
        $stmtRA->execute();
        $resRA = $stmtRA->get_result();
        $raIndex = 0;
        while ($rowRA = $resRA->fetch_assoc()) {
            if ($raIndex === 1) {
                $reanalysis2Data = $rowRA;
            } elseif ($raIndex === 2) {
                $reanalysis3Data = $rowRA;
            }
            $raIndex++;
        }
        $stmtRA->close();
    }
}

// Fetch re-case 2 and 3 records from recasetaking table
$recase2Data = null;
$recase3Data = null;
if ($caseId > 0) {
    $stmtRC = $conn->prepare("
        SELECT reason, medicine_name, medicine_date 
        FROM recasetaking 
        WHERE case_id = ? 
        ORDER BY id ASC 
        LIMIT 2
    ");
    if ($stmtRC) {
        $stmtRC->bind_param("i", $caseId);
        $stmtRC->execute();
        $resRC = $stmtRC->get_result();
        $rcIndex = 0;
        while ($rowRC = $resRC->fetch_assoc()) {
            if ($rcIndex === 1) {
                $recase2Data = $rowRC;
            } elseif ($rcIndex === 2) {
                $recase3Data = $rowRC;
            }
            $rcIndex++;
        }
        $stmtRC->close();
    }
}

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

    // Validate next_followup_date - must be a complete date (YYYY-MM-DD) or null
    $postNextFU = null;
    if (!empty($_POST['next_followup_date'])) {
        $dateValue = trim($_POST['next_followup_date']);
        // Check if it matches the format YYYY-MM-DD
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateValue)) {
            // Verify it's a valid date
            $dateParts = explode('-', $dateValue);
            if (checkdate((int)$dateParts[1], (int)$dateParts[2], (int)$dateParts[0])) {
                $postNextFU = $dateValue;
            }
        }
    }

    // Re-analysis fields
    $postReanalysisReason    = trim($_POST['reanalysis_reason'] ?? '');
    $postReanalysisConclusion = trim($_POST['reanalysis_conclusion'] ?? '');
    $postReanalysisMedicineName = isset($_POST['medicines']) && isset($_POST['medicines'][0]['name']) ? trim($_POST['medicines'][0]['name']) : '';
    $postReanalysisMedicineDate = null;
    if (isset($_POST['medicines']) && isset($_POST['medicines'][0]['date']) && !empty($_POST['medicines'][0]['date'])) {
        $dateValue = trim($_POST['medicines'][0]['date']);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateValue)) {
            $dateParts = explode('-', $dateValue);
            if (checkdate((int)$dateParts[1], (int)$dateParts[2], (int)$dateParts[0])) {
                $postReanalysisMedicineDate = $dateValue;
            }
        }
    }

    // Re-case taking fields
    $postRecaseReason        = trim($_POST['recase_reason'] ?? '');
    $postRecaseMedicineName  = isset($_POST['recase_medicines']) && isset($_POST['recase_medicines'][0]['name']) ? trim($_POST['recase_medicines'][0]['name']) : '';
    $postRecaseMedicineDate  = null;
    if (isset($_POST['recase_medicines']) && isset($_POST['recase_medicines'][0]['date']) && !empty($_POST['recase_medicines'][0]['date'])) {
        $dateValue = trim($_POST['recase_medicines'][0]['date']);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateValue)) {
            $dateParts = explode('-', $dateValue);
            if (checkdate((int)$dateParts[1], (int)$dateParts[2], (int)$dateParts[0])) {
                $postRecaseMedicineDate = $dateValue;
            }
        }
    }

    if (!$postConsultant || !isset($consultants[$postConsultant])) {
        $errors[] = "Please select a consultant.";
    }
    if ($postChief === '') {
        $errors[] = "Chief complaint is required.";
    }

    if (empty($errors)) {
        // Fetch consultant.user_id (if available) to store as consultant_user_id
        $consultantUserId = null;
        if ($postConsultant > 0) {
            $stmtCU = $conn->prepare("SELECT user_id FROM consultants WHERE id = ? LIMIT 1");
            if ($stmtCU) {
                $stmtCU->bind_param("i", $postConsultant);
                $stmtCU->execute();
                $rCU = $stmtCU->get_result();
                if ($rowCU = $rCU->fetch_assoc()) {
                    $consultantUserId = isset($rowCU['user_id']) ? (int)$rowCU['user_id'] : null;
                }
                $stmtCU->close();
            }
        }

        // Simplified schema: patient_id is directly stored in cases table
        // No need for separate patient_internal_id fetch

        // Prescribed medicines from main form (2 entries expected)
        $med1Name = trim($_POST['prescribed_medicines'][0]['name'] ?? '');
        $med1Date = null;
        if (!empty($_POST['prescribed_medicines'][0]['date'])) {
            $d = trim($_POST['prescribed_medicines'][0]['date']);
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) $med1Date = $d;
        }
        $med2Name = trim($_POST['prescribed_medicines'][1]['name'] ?? '');
        $med2Date = null;
        if (!empty($_POST['prescribed_medicines'][1]['date'])) {
            $d2 = trim($_POST['prescribed_medicines'][1]['date']);
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $d2)) $med2Date = $d2;
        }

        if ($postCaseId > 0) {
            // UPDATE existing case
            $sqlUpd = "
                UPDATE cases
                SET consultant_id = ?,
                    consultant_user_id = ?,
                    chief_complaint = ?,
                    other_complaint_1 = ?,
                    other_complaint_2 = ?,
                    other_complaint_3 = ?,
                    summary = ?,
                    status = ?,
                    next_followup_date = ?,
                    medicine_name1 = ?,
                    medicine_date1 = ?,
                    medicine_name2 = ?,
                    medicine_date2 = ?
                WHERE id = ?
            ";
            $stmtUpd = $conn->prepare($sqlUpd);
            if (!$stmtUpd) {
                $errors[] = "DB error (prepare update): " . $conn->error;
            } else {
                $cuid = $consultantUserId !== null ? (int)$consultantUserId : null;

                $stmtUpd->bind_param(
                    "iisssssssssssi",
                    $postConsultant,
                    $cuid,
                    $postChief,
                    $postOther1,
                    $postOther2,
                    $postOther3,
                    $postSummary,
                    $postStatus,
                    $postNextFU,
                    $med1Name,
                    $med1Date,
                    $med2Name,
                    $med2Date,
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
                    consultant_id, consultant_user_id, patient_id,
                    chief_complaint, other_complaint_1, other_complaint_2, other_complaint_3,
                    summary, status, next_followup_date,
                    medicine_name1, medicine_date1, medicine_name2, medicine_date2
                ) VALUES (
                    ?, ?, ?,
                    ?, ?, ?, ?,
                    ?, ?, ?,
                    ?, ?, ?, ?
                )
            ";
            $stmtIns = $conn->prepare($sqlIns);
            if (!$stmtIns) {
                $errors[] = "DB error (prepare insert): " . $conn->error;
            } else {
                $cuid = $consultantUserId !== null ? (int)$consultantUserId : null;

                $stmtIns->bind_param(
                    "iiisssssssssss",
                    $postConsultant,
                    $cuid,
                    $patientId,
                    $postChief,
                    $postOther1,
                    $postOther2,
                    $postOther3,
                    $postSummary,
                    $postStatus,
                    $postNextFU,
                    $med1Name,
                    $med1Date,
                    $med2Name,
                    $med2Date
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
                    $bindParams = array_merge([$types], $params);
                    // mysqli_stmt::bind_param requires references
                    $refs = [];
                    foreach ($bindParams as &$ref) {
                        $refs[] = &$ref;
                    }
                    call_user_func_array([$stmtUpdNames, 'bind_param'], $refs);
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
    // Clear prescribed medicines when starting a new form
    $med1Name = '';
    $med1Date = '';
    $med2Name = '';
    $med2Date = '';
    // Clear re-analysis and recase fields for new form
    $reanalysisReason = '';
    $reanalysisConclusion = '';
    $reanalysisMedName = '';
    $reanalysisMedDate = '';
    $recaseReason = '';
    $recaseMedName = '';
    $recaseMedDate = '';
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
            <div class="header-top-row">
                <div class="header-left">
                    <span class="header-title">Case Details</span>
                    <span class="header-patient"><?= h($patient['name']); ?></span>
                    <span class="header-divider">|</span>
                    <span class="header-patient-id">PAT-<?= (int)$patient['id']; ?></span>
                </div>
                <div class="header-right">
                    <span class="header-case-no">Case: <?= $caseId ? "#" . $caseId : "New"; ?></span>
                    <span class="header-divider">|</span>
                    <span class="header-visit-date"><?= h(date('M d, Y', strtotime($visitDate))); ?></span>
                </div>
            </div>
            <div class="header-bottom-row">
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


            <!-- Top Row: Consultant, Status, Follow-up Date -->
            <div class="row g-3 mb-4">
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
                        <option value="open" <?= $status === 'open' ? 'selected' : ''; ?>>Open</option>
                        <option value="in_progress" <?= $status === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                        <option value="closed" <?= $status === 'closed' ? 'selected' : ''; ?>>Closed</option>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="case-label form-label">Next Follow-up Date</label>
                    <input type="date" name="next_followup_date" class="form-control form-control-sm" value="<?= h($nextFollowup); ?>">
                </div>
            </div>

            <!-- Main Content Layout: Chief Complaint (Left) | Other Complaints + Files Upload (Right) -->
            <div class="row g-4">
                <!-- Left Column: Chief Complaint and Summary -->
                <div class="col-md-6">
                    <div>
                        <label class="case-label form-label">Chief Complaint <span class="text-danger">*</span></label>
                        <textarea name="chief_complaint"
                            class="form-control form-control-sm case-textarea"
                            required
                            placeholder="Primary complaint..."><?= h($chiefComplaint); ?></textarea>
                    </div>

                    <div class="mt-3">
                        <label class="case-label form-label">Summary / Impression</label>
                        <textarea name="summary"
                            class="form-control form-control-sm case-textarea"
                            placeholder="Clinical summary and observations..."><?= h($summary); ?></textarea>
                    </div>
                    <!-- Prescribed Medicines block (under Summary/Impression) -->
                    <div class="mt-3 prescribed-medicines">
                        <label class="case-label form-label">Prescribed Medicines Name</label>
                        <div class="prescribed-list">
                            <?php for ($i = 0; $i < 2; $i++):
                                // choose corresponding stored values if available
                                $valName = $i === 0 ? ($med1Name ?? '') : ($med2Name ?? '');
                                $valDate = $i === 0 ? ($med1Date ?? '') : ($med2Date ?? '');
                            ?>
                                <div class="prescribed-row d-flex align-items-center mb-2">
                                    <div class="prescribed-number me-2"><?= ($i + 1); ?>.</div>
                                    <input type="text" name="prescribed_medicines[<?= $i; ?>][name]" value="<?= h($valName); ?>" class="form-control form-control-sm prescribed-name" placeholder="Medicine name">
                                    <input type="date" name="prescribed_medicines[<?= $i; ?>][date]" value="<?= h($valDate); ?>" class="form-control form-control-sm prescribed-date ms-2" style="max-width:180px">
                                </div>
                            <?php endfor; ?>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Other Complaints + Case Files Upload -->
                <div class="col-md-6">
                    <!-- Other Complaints Section -->
                    <div class="mb-4">
                        <label class="case-label form-label">Other Complaint</label>
                        <div class="other-complaints-stack">
                            <div class="complaint-row">
                                <span class="complaint-number">1)</span>
                                <textarea name="other_complaint_1"
                                    class="form-control form-control-sm case-textarea-sm"
                                    placeholder="Secondary complaint..."><?= h($other1); ?></textarea>
                            </div>

                            <div class="complaint-row mt-2">
                                <span class="complaint-number">2)</span>
                                <textarea name="other_complaint_2"
                                    class="form-control form-control-sm case-textarea-sm"
                                    placeholder="Tertiary complaint..."><?= h($other2); ?></textarea>
                            </div>

                            <div class="complaint-row mt-2">
                                <span class="complaint-number">3)</span>
                                <textarea name="other_complaint_3"
                                    class="form-control form-control-sm case-textarea-sm"
                                    placeholder="Additional complaint..."><?= h($other3); ?></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Case Files Upload Section -->
                    <div>
                        <label class="case-label form-label">Case Files Upload</label>
                        <div class="case-files-upload">
                            <div class="file-upload-row">
                                <label class="file-upload-label">
                                    <i class="fas fa-file-pdf me-1"></i>1) Pre case
                                </label>
                                <div class="file-upload-input-wrapper">
                                    <input type="file"
                                        id="pre_case_taking"
                                        name="pre_case_taking"
                                        class="form-control form-control-sm file-input"
                                        accept=".pdf,.doc,.docx">
                                    <div id="pre_case_taking_progress" class="progress mt-2" style="display:none; height: 6px;">
                                        <div class="progress-bar progress-bar-striped progress-bar-animated bg-success" role="progressbar" style="width: 100%"></div>
                                    </div>
                                    <div id="pre_case_taking_status" style="display:none; margin-top:6px; font-size:0.75rem;"></div>
                                </div>
                            </div>

                            <div class="file-upload-row mt-3">
                                <label class="file-upload-label">
                                    <i class="fas fa-file-contract me-1"></i>2) Re case
                                </label>
                                <div class="file-upload-input-wrapper">
                                    <input type="file"
                                        id="case_taking"
                                        name="case_taking"
                                        class="form-control form-control-sm file-input"
                                        accept=".pdf,.doc,.docx">
                                    <div id="case_taking_progress" class="progress mt-2" style="display:none; height: 6px;">
                                        <div class="progress-bar progress-bar-striped progress-bar-animated bg-success" role="progressbar" style="width: 100%"></div>
                                    </div>
                                    <div id="case_taking_status" style="display:none; margin-top:6px; font-size:0.75rem;"></div>
                                </div>
                            </div>

                            <div class="file-upload-row mt-3">
                                <label class="file-upload-label">
                                    <i class="fas fa-images me-1"></i>3) Report
                                </label>
                                <div class="file-upload-input-wrapper">
                                    <input type="file"
                                        id="reports"
                                        name="reports"
                                        class="form-control form-control-sm file-input"
                                        accept=".pdf,.jpg,.jpeg,.png,.gif,.doc,.docx">
                                    <div id="reports_progress" class="progress mt-2" style="display:none; height: 6px;">
                                        <div class="progress-bar progress-bar-striped progress-bar-animated bg-success" role="progressbar" style="width: 100%"></div>
                                    </div>
                                    <div id="reports_status" style="display:none; margin-top:6px; font-size:0.75rem;"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bottom Buttons Row: Save | Re-analysis | Re-case | New Form -->
            <div class="row g-3 mt-4">
                <div class="col-12">
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <!-- 1. Save Case (submit) -->
                        <button type="submit" class="btn btn-primary btn-sm" id="saveCaseBtn">
                            <i class="fas fa-save me-2"></i>Save Case
                        </button>

                        <!-- 2. Toggle Re-analysis section -->
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="toggleReAnalysisBtn">
                            <i class="fas fa-rotate-right me-2"></i>Re-analysis
                        </button>

                        <!-- 3. Toggle Re-case taking section -->
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="toggleReCaseTakingBtn">
                            <i class="fas fa-file-medical me-2"></i>Re-case
                        </button>

                        <!-- 4. New Form -->
                        <a href="?patient_id=<?= (int)$patientId; ?>&new_case=1" class="btn btn-outline-primary btn-sm ms-1">
                            <i class="fas fa-plus me-2"></i>New Form
                        </a>

                        <!-- optional spacer or helper text can go here -->
                    </div>
                </div>
            </div>
    </div>

    <!-- RE-ANALYSIS SECTION -->
    <div class="case-section mt-4" id="reAnalysisSection" style="display: none;">
        <div class="case-section-header">
            <span class="case-section-title">
                <i class="fas fa-rotate-right me-2"></i>Re-analysis
            </span>
        </div>

        <!-- Side-by-side: Reason | Conclusion -->
        <div class="row g-3">
            <div class="col-md-6">
                <label class="case-label form-label">Reason for Re_analysis</label>
                <textarea name="reanalysis_reason" class="form-control form-control-sm case-textarea-sm" placeholder="Reason for re-analysis..."><?= h($case['reanalysis_reason'] ?? ''); ?></textarea>
            </div>

            <div class="col-md-6">
                <label class="case-label form-label">Conclusion After Re-analysis</label>
                <textarea name="reanalysis_conclusion" class="form-control form-control-sm case-textarea-sm" placeholder="Conclusion after re-analysis..."><?= h($case['reanalysis_conclusion'] ?? ''); ?></textarea>
            </div>
        </div>

        <!-- Prescribed medicines (left) and Save Re-analysis (right) -->
        <div class="row g-3 mt-3 align-items-center">
            <div class="col-md-8">
                <label class="case-label form-label">Prescribed Medicines</label>
                <div id="medicinesList">
                    <?php for ($mi = 0; $mi < 1; $mi++): ?>
                        <div class="input-group mb-2 medicine-row">
                            <input type="text" name="medicines[<?= $mi; ?>][name]" class="form-control form-control-sm" placeholder="Medicine name">
                            <input type="date" name="medicines[<?= $mi; ?>][date]" class="form-control form-control-sm ms-2" style="max-width:180px">
                        </div>
                    <?php endfor; ?>
                </div>
            </div>

            <div class="col-md-4 text-end">
                <div class="d-flex justify-content-end gap-2">

                    <button type="button" class="btn btn-primary btn-sm" onclick="saveReanalysis('')">
                        <i class="fas fa-save me-1"></i>Save Re-analysis
                    </button>

                    <button type="button" class="btn btn-outline-secondary btn-sm" id="toggleReanalysis2Btn">
                        <i class="fas fa-plus me-1"></i>Re-analysis 2
                    </button>


                </div>
            </div>
        </div>
        <!-- Separator line after Re-analysis 1 -->
        <hr class="case-block-separator">
        <!-- Hidden second re-analysis block -->
        <div class="row g-3 mt-3 align-items-center" id="reanalysisBlock2" style="display:<?= $reanalysis2Data ? 'flex' : 'none'; ?>;">
            <div class="col-md-6">
                <label class="case-label form-label">Reason for Re_analysis (2)</label>
                <textarea name="reanalysis_reason_2" class="form-control form-control-sm case-textarea-sm" placeholder="Reason for re-analysis..."><?= h($reanalysis2Data['reason'] ?? ''); ?></textarea>
            </div>
            <div class="col-md-6">
                <label class="case-label form-label">Conclusion After Re-analysis (2)</label>
                <textarea name="reanalysis_conclusion_2" class="form-control form-control-sm case-textarea-sm" placeholder="Conclusion after re-analysis..."><?= h($reanalysis2Data['conclusion'] ?? ''); ?></textarea>
            </div>
            <div class="col-md-8 mt-3">
                <label class="case-label form-label">Prescribed Medicines (2)</label>
                <div class="input-group mb-2 medicine-row">
                    <input type="text" name="medicines_2_name" class="form-control form-control-sm" placeholder="Medicine name" value="<?= h($reanalysis2Data['medicine_name'] ?? ''); ?>">
                    <input type="date" name="medicines_2_date" class="form-control form-control-sm ms-2" style="max-width:180px" value="<?= h($reanalysis2Data['medicine_date'] ?? ''); ?>">
                </div>
            </div>
            <div class="col-md-4 text-end mt-3">
                <button type="button" class="btn btn-primary btn-sm" onclick="saveReanalysis('_2')">
                    <i class="fas fa-save me-1"></i>Save Re-analysis 2
                </button>
                <button type="button" class="btn btn-outline-secondary btn-sm" id="toggleReanalysis3Btn">
                    <i class="fas fa-plus me-1"></i>Re-analysis 3
                </button>
            </div>
        </div>
        <!-- Separator line after Re-analysis 2 -->
        <hr class="case-block-separator">
        <!-- Hidden third re-analysis block - Full width -->
        <div class="row g-3 mt-3" id="reanalysisBlock3" style="display:<?= $reanalysis3Data ? 'flex' : 'none'; ?>; flex-direction: column;">
            <div class="col-md-12">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="case-label form-label">Reason for Re_analysis (3)</label>
                        <textarea name="reanalysis_reason_3" class="form-control form-control-sm case-textarea-sm" placeholder="Reason for re-analysis..."><?= h($reanalysis3Data['reason'] ?? ''); ?></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="case-label form-label">Conclusion After Re-analysis (3)</label>
                        <textarea name="reanalysis_conclusion_3" class="form-control form-control-sm case-textarea-sm" placeholder="Conclusion after re-analysis..."><?= h($reanalysis3Data['conclusion'] ?? ''); ?></textarea>
                    </div>
                    <div class="col-md-8 mt-3">
                        <label class="case-label form-label">Prescribed Medicines (3)</label>
                        <div class="input-group mb-2 medicine-row">
                            <input type="text" name="medicines_3_name" class="form-control form-control-sm" placeholder="Medicine name" value="<?= h($reanalysis3Data['medicine_name'] ?? ''); ?>">
                            <input type="date" name="medicines_3_date" class="form-control form-control-sm ms-2" style="max-width:180px" value="<?= h($reanalysis3Data['medicine_date'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="col-md-4 text-end mt-3">
                        <button type="button" class="btn btn-primary btn-sm" onclick="saveReanalysis('_3')">
                            <i class="fas fa-save me-1"></i>Save Re-analysis 3
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- RE-CASE TAKING SECTION -->
    <div class="case-section mt-4" id="reCaseTakingSection" style="display: none;">
        <div class="case-section-header">
            <span class="case-section-title">
                <i class="fas fa-file-medical me-2"></i>Re-Case Taking
            </span>
        </div>

        <!-- Two columns: Reason (left) | Upload (right) -->
        <div class="row g-3">
            <div class="col-md-6">
                <label class="case-label form-label">Reason for RE-case taking</label>
                <textarea name="recase_reason" class="form-control form-control-sm case-textarea-sm" placeholder="Reason for re-case taking..."><?= h($case['recase_reason'] ?? ''); ?></textarea>
            </div>

            <div class="col-md-6">
                <label class="case-label form-label">Upload New Re_case (PDF)</label>
                <input type="file" id="re_case_pdf" name="re_case_pdf" class="form-control form-control-sm file-input" accept=".pdf,.doc,.docx">
                <div id="re_case_pdf_progress" class="progress mt-2" style="display:none;">
                    <div class="progress-bar progress-bar-striped progress-bar-animated bg-success" role="progressbar" style="width: 100%"></div>
                </div>
                <div id="re_case_pdf_status" style="display:none; margin-top:8px;"></div>
            </div>
        </div>

        <!-- Single Prescribed medicine left, Save button right -->
        <div class="row g-3 mt-3 align-items-center">
            <div class="col-md-8">
                <label class="case-label form-label">Prescribed Medicine</label>
                <div class="input-group mb-2 medicine-row">
                    <input type="text" name="recase_medicines[0][name]" class="form-control form-control-sm" placeholder="Medicine name">
                    <input type="date" name="recase_medicines[0][date]" class="form-control form-control-sm ms-2" style="max-width:180px">
                </div>
            </div>

            <div class="col-md-4 text-end">
                <div class="d-flex justify-content-end gap-2">

                    <button type="button" class="btn btn-primary btn-sm" onclick="saveRecaseTaking('')">
                        <i class="fas fa-save me-1"></i>Save Re-case
                    </button>

                    <button type="button" class="btn btn-outline-secondary btn-sm" id="toggleRecase2Btn">
                        <i class="fas fa-plus me-1"></i>Re-case 2
                    </button>


                </div>
            </div>
        </div>
        <!-- Separator line after Re-case 1 -->
        <hr class="case-block-separator">
        <!-- Hidden second re-case block -->
        <div class="row g-3 mt-3 align-items-center" id="recaseBlock2" style="display:<?= $recase2Data ? 'flex' : 'none'; ?>;">
            <div class="col-md-6">
                <label class="case-label form-label">Reason for RE-case taking (2)</label>
                <textarea name="recase_reason_2" class="form-control form-control-sm case-textarea-sm" placeholder="Reason for re-case taking..."><?= h($recase2Data['reason'] ?? ''); ?></textarea>
            </div>
            <div class="col-md-6">
                <label class="case-label form-label">Upload New Re_case (PDF) (2)</label>
                <input type="file" id="re_case_pdf_2" name="re_case_pdf_2" class="form-control form-control-sm file-input" accept=".pdf,.doc,.docx">
                <div id="re_case_pdf_2_progress" class="progress mt-2" style="display:none;">
                    <div class="progress-bar progress-bar-striped progress-bar-animated bg-success" role="progressbar" style="width: 100%"></div>
                </div>
                <div id="re_case_pdf_2_status" style="display:none; margin-top:8px;"></div>
            </div>
            <div class="col-md-8 mt-3">
                <label class="case-label form-label">Prescribed Medicine (2)</label>
                <div class="input-group mb-2 medicine-row">
                    <input type="text" name="recase_medicines_2_name" class="form-control form-control-sm" placeholder="Medicine name" value="<?= h($recase2Data['medicine_name'] ?? ''); ?>">
                    <input type="date" name="recase_medicines_2_date" class="form-control form-control-sm ms-2" style="max-width:180px" value="<?= h($recase2Data['medicine_date'] ?? ''); ?>">
                </div>
            </div>
            <div class="col-md-4 text-end mt-3">
                <button type="button" class="btn btn-primary btn-sm" onclick="saveRecaseTaking('_2')">
                    <i class="fas fa-save me-1"></i>Save Re-case 2
                </button>

                <button type="button" class="btn btn-outline-secondary btn-sm" id="toggleRecase3Btn">
                    <i class="fas fa-plus me-1"></i>Re-case 3
                </button>
            </div>
        </div>
        <!-- Separator line after Re-case 2 -->
        <hr class="case-block-separator">
        <!-- Hidden third re-case block - Full width -->
        <div class="row g-3 mt-3" id="recaseBlock3" style="display:<?= $recase3Data ? 'flex' : 'none'; ?>; flex-direction: column;">
            <div class="col-md-12">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="case-label form-label">Reason for RE-case taking (3)</label>
                        <textarea name="recase_reason_3" class="form-control form-control-sm case-textarea-sm" placeholder="Reason for re-case taking..."><?= h($recase3Data['reason'] ?? ''); ?></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="case-label form-label">Upload New Re_case (PDF) (3)</label>
                        <input type="file" id="re_case_pdf_3" name="re_case_pdf_3" class="form-control form-control-sm file-input" accept=".pdf,.doc,.docx">
                        <div id="re_case_pdf_3_progress" class="progress mt-2" style="display:none;">
                            <div class="progress-bar progress-bar-striped progress-bar-animated bg-success" role="progressbar" style="width: 100%"></div>
                        </div>
                        <div id="re_case_pdf_3_status" style="display:none; margin-top:8px;"></div>
                    </div>
                    <div class="col-md-8 mt-3">
                        <label class="case-label form-label">Prescribed Medicine (3)</label>
                        <div class="input-group mb-2 medicine-row">
                            <input type="text" name="recase_medicines_3_name" class="form-control form-control-sm" placeholder="Medicine name" value="<?= h($recase3Data['medicine_name'] ?? ''); ?>">
                            <input type="date" name="recase_medicines_3_date" class="form-control form-control-sm ms-2" style="max-width:180px" value="<?= h($recase3Data['medicine_date'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="col-md-4 text-end mt-3">
                        <button type="button" class="btn btn-primary btn-sm" onclick="saveRecaseTaking('_3')">
                            <i class="fas fa-save me-1"></i>Save Re-case 3
                        </button>
                    </div>
                </div>
            </div>
        </div>
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

            // Toggle Re-Analysis section
            const toggleReAnalysisBtn = document.getElementById('toggleReAnalysisBtn');
            const reAnalysisSection = document.getElementById('reAnalysisSection');
            if (toggleReAnalysisBtn && reAnalysisSection) {
                toggleReAnalysisBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    // Hide Re-Case Taking section if open
                    const reCaseTakingSection = document.getElementById('reCaseTakingSection');
                    if (reCaseTakingSection) {
                        reCaseTakingSection.style.display = 'none';
                    }
                    // Toggle Re-Analysis section
                    const isHidden = reAnalysisSection.style.display === 'none';
                    reAnalysisSection.style.display = isHidden ? 'block' : 'none';
                });
            }

            // Toggle Re-Case Taking section
            const toggleReCaseTakingBtn = document.getElementById('toggleReCaseTakingBtn');
            const reCaseTakingSection = document.getElementById('reCaseTakingSection');
            if (toggleReCaseTakingBtn && reCaseTakingSection) {
                toggleReCaseTakingBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    // Hide Re-Analysis section if open
                    if (reAnalysisSection) {
                        reAnalysisSection.style.display = 'none';
                    }
                    // Toggle Re-Case Taking section
                    const isHidden = reCaseTakingSection.style.display === 'none';
                    reCaseTakingSection.style.display = isHidden ? 'block' : 'none';
                });
            }
        });

        function uploadFile(inputElement) {
            const file = inputElement.files[0];
            if (!file) return;

            const fieldName = inputElement.id;
            const caseId = document.getElementById('caseIdHidden').value || '0';
            const patientId = document.getElementById('patientIdHidden').value;
            const progressDiv = document.getElementById(fieldName + '_progress');
            const statusDiv = document.getElementById(fieldName + '_status');

            // Get consultant ID from form if available
            const consultantSelect = document.querySelector('select[name="consultant_id"]');
            const consultantId = consultantSelect ? consultantSelect.value : '0';

            const formData = new FormData();
            formData.append('file', file);
            formData.append('field_name', fieldName);
            formData.append('file_type', fieldName);
            formData.append('case_id', caseId);
            formData.append('patient_id', patientId);
            formData.append('consultant_id', consultantId);

            progressDiv.style.display = 'block';
            statusDiv.style.display = 'none';

            fetch('case_ajax.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    progressDiv.style.display = 'none';

                    if (data.success) {
                        showFileUploadSuccess(fieldName, data.file_name, data.file_size, data.file_type);
                        inputElement.value = ''; // Clear input
                        // Update case ID if a new case was created
                        if (data.case_id > 0) {
                            document.getElementById('caseIdHidden').value = data.case_id;
                        }
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

        function showFileUploadSuccess(fieldName, fileName, fileSize, fileType) {
            // Show success modal with file type
            const fileUploadModal = new bootstrap.Modal(document.getElementById('fileUploadModal'));
            document.getElementById('uploadFileName').textContent = fileName;
            document.getElementById('uploadFileSize').textContent = 'Size: ' + fileSize;
            fileUploadModal.show();

            // Auto-hide after 2 seconds
            setTimeout(function() {
                fileUploadModal.hide();
            }, 2000);

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
                        /* ignore */
                    }
                }
            });
        })();

        // Toggle handlers for the second blocks
        document.addEventListener('DOMContentLoaded', function() {
            const toggleRe2 = document.getElementById('toggleReanalysis2Btn');
            const reBlock2 = document.getElementById('reanalysisBlock2');
            if (toggleRe2 && reBlock2) {
                toggleRe2.addEventListener('click', function(e) {
                    e.preventDefault();
                    reBlock2.style.display = reBlock2.style.display === 'none' ? 'flex' : 'none';
                });
            }
            const toggleRe3 = document.getElementById('toggleReanalysis3Btn');
            const reBlock3 = document.getElementById('reanalysisBlock3');
            if (toggleRe3 && reBlock3) {
                toggleRe3.addEventListener('click', function(e) {
                    e.preventDefault();
                    reBlock3.style.display = reBlock3.style.display === 'none' ? 'flex' : 'none';
                });
            }

            const toggleRec2 = document.getElementById('toggleRecase2Btn');
            const rcBlock2 = document.getElementById('recaseBlock2');
            if (toggleRec2 && rcBlock2) {
                toggleRec2.addEventListener('click', function(e) {
                    e.preventDefault();
                    rcBlock2.style.display = rcBlock2.style.display === 'none' ? 'flex' : 'none';
                });
            }
            const toggleRec3 = document.getElementById('toggleRecase3Btn');
            const rcBlock3 = document.getElementById('recaseBlock3');
            if (toggleRec3 && rcBlock3) {
                toggleRec3.addEventListener('click', function(e) {
                    e.preventDefault();
                    rcBlock3.style.display = rcBlock3.style.display === 'none' ? 'flex' : 'none';
                });
            }
        });

        // Handle "Add More Complaints" button
        document.addEventListener('DOMContentLoaded', function() {
            const addMoreBtn = document.getElementById('addMoreComplaintsBtn');
            if (addMoreBtn) {
                addMoreBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    const complaintNumber = document.querySelectorAll('.other-complaint-item').length + 1;

                    // Create new complaint item
                    const newComplaint = document.createElement('div');
                    newComplaint.className = 'other-complaint-item';
                    newComplaint.innerHTML = `
                        <label class="complaint-label">Other Complaint ${complaintNumber}</label>
                        <textarea class="form-control form-control-sm case-textarea-xs" 
                                  placeholder="Additional..." 
                                  style="min-height: 45px; resize: vertical; font-size: 0.85rem; line-height: 1.3; padding: 6px 8px;"></textarea>
                        <button type="button" class="btn btn-sm btn-outline-danger" style="align-self: flex-end; font-size: 0.75rem; padding: 2px 8px;">
                            <i class="fas fa-trash"></i> Remove
                        </button>
                    `;

                    // Add remove functionality
                    const removeBtn = newComplaint.querySelector('.btn-outline-danger');
                    removeBtn.addEventListener('click', function() {
                        newComplaint.remove();
                    });

                    // Insert before the button
                    addMoreBtn.parentElement.insertBefore(newComplaint, addMoreBtn);
                });
            }
        });

        // Handle Add/Remove medicines in Re-analysis section
        document.addEventListener('DOMContentLoaded', function() {
            const addMedBtn = document.getElementById('addMedicineBtn');
            const medsList = document.getElementById('medicinesList');
            if (addMedBtn && medsList) {
                addMedBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    const row = document.createElement('div');
                    row.className = 'input-group mb-2 medicine-row';

                    const nameInput = document.createElement('input');
                    nameInput.type = 'text';
                    nameInput.name = 'medicines[][name]';
                    nameInput.className = 'form-control form-control-sm';
                    nameInput.placeholder = 'Medicine name';

                    const dateInput = document.createElement('input');
                    dateInput.type = 'date';
                    dateInput.name = 'medicines[][date]';
                    dateInput.className = 'form-control form-control-sm ms-2';
                    dateInput.style.maxWidth = '180px';

                    const removeBtn = document.createElement('button');
                    removeBtn.type = 'button';
                    removeBtn.className = 'btn btn-sm btn-outline-danger ms-2 remove-medicine-btn';
                    removeBtn.title = 'Remove';
                    const icon = document.createElement('i');
                    icon.className = 'fas fa-trash';
                    removeBtn.appendChild(icon);

                    row.appendChild(nameInput);
                    row.appendChild(dateInput);
                    row.appendChild(removeBtn);
                    medsList.appendChild(row);

                    removeBtn.addEventListener('click', function() {
                        row.remove();
                    });
                });

                // attach remove handler to any existing remove buttons
                medsList.querySelectorAll('.remove-medicine-btn').forEach(function(b) {
                    b.addEventListener('click', function() {
                        b.closest('.medicine-row').remove();
                    });
                });
            }
        });

        function saveReanalysis(suffix = '') {
            const caseIdHidden = document.getElementById('caseIdHidden').value;
            const patientIdHidden = document.getElementById('patientIdHidden').value;
            const consultantSelect = document.querySelector('select[name="consultant_id"]');
            const consultantId = consultantSelect ? consultantSelect.value : '0';

            // support suffix ('' or '_2') to handle second block inputs
            const reasonInput = document.querySelector('textarea[name="reanalysis_reason' + suffix + '"]') || document.querySelector('textarea[name="reanalysis_reason"]');
            const conclusionInput = document.querySelector('textarea[name="reanalysis_conclusion' + suffix + '"]') || document.querySelector('textarea[name="reanalysis_conclusion"]');
            const medicineNameInput = document.querySelector('input[name="medicines' + (suffix === '_2' ? '_2_name' : '[0][name]') + '"]') || document.querySelector('input[name="medicines[0][name]"]');
            const medicineDateInput = document.querySelector('input[name="medicines' + (suffix === '_2' ? '_2_date' : '[0][date]') + '"]') || document.querySelector('input[name="medicines[0][date]"]');

            if (!reasonInput || !conclusionInput) {
                alert('Re-analysis form not found');
                return;
            }

            const data = {
                case_id: caseIdHidden,
                patient_id: patientIdHidden,
                consultant_id: consultantId,
                patient_name: '<?= h($patient['name']) ?>',
                consultant_name: document.querySelector('select[name="consultant_id"] option:checked').textContent,
                reason: reasonInput ? reasonInput.value : '',
                conclusion: conclusionInput ? conclusionInput.value : '',
                medicine_name: medicineNameInput ? medicineNameInput.value : '',
                medicine_date: medicineDateInput ? medicineDateInput.value : ''
            };

            console.log('Sending data to reanalysis_ajax.php:', data);

            fetch('reanalysis_ajax.php', {
                    method: 'POST',
                    body: new URLSearchParams(data)
                })
                .then(r => {
                    console.log('Response status:', r.status);
                    return r.text();
                })
                .then(text => {
                    console.log('Response text:', text);
                    try {
                        const res = JSON.parse(text);
                        if (res.success) {
                            showSuccessModal(res.message);
                            // Do not clear inputs for any re-analysis block — keep entered data visible after save
                        } else {
                            alert('Error: ' + res.message);
                        }
                    } catch (e) {
                        console.error('JSON parse error:', e);
                        alert('Server error: ' + text);
                    }
                })
                .catch(error => {
                    console.error('Fetch error:', error);
                    alert('Network error: ' + error.message);
                });
        }

        function saveRecaseTaking(suffix = '') {
            const caseIdHidden = document.getElementById('caseIdHidden').value;
            const patientIdHidden = document.getElementById('patientIdHidden').value;
            const consultantSelect = document.querySelector('select[name="consultant_id"]');
            const consultantId = consultantSelect ? consultantSelect.value : '0';

            // support suffix '' or '_2'
            const reasonInput = document.querySelector('textarea[name="recase_reason' + suffix + '"]') || document.querySelector('textarea[name="recase_reason"]');
            const medicineNameInput = document.querySelector('input[name="recase_medicines' + (suffix === '_2' ? '_2_name' : '[0][name]') + '"]') || document.querySelector('input[name="recase_medicines[0][name]"]');
            const medicineDateInput = document.querySelector('input[name="recase_medicines' + (suffix === '_2' ? '_2_date' : '[0][date]') + '"]') || document.querySelector('input[name="recase_medicines[0][date]"]');

            if (!reasonInput) {
                alert('Re-case taking form not found');
                return;
            }

            const data = {
                case_id: caseIdHidden,
                patient_id: patientIdHidden,
                consultant_id: consultantId,
                patient_name: '<?= h($patient['name']) ?>',
                consultant_name: document.querySelector('select[name="consultant_id"] option:checked').textContent,
                reason: reasonInput ? reasonInput.value : '',
                medicine_name: medicineNameInput ? medicineNameInput.value : '',
                medicine_date: medicineDateInput ? medicineDateInput.value : ''
            };

            console.log('Sending data to recasetaking_ajax.php:', data);

            fetch('recasetaking_ajax.php', {
                    method: 'POST',
                    body: new URLSearchParams(data)
                })
                .then(r => {
                    console.log('Response status:', r.status);
                    return r.text();
                })
                .then(text => {
                    console.log('Response text:', text);
                    try {
                        const res = JSON.parse(text);
                        if (res.success) {
                            showSuccessModal(res.message);
                            // Do not clear any re-case inputs — keep entered data visible after save
                        } else {
                            alert('Error: ' + res.message);
                        }
                    } catch (e) {
                        console.error('JSON parse error:', e);
                        alert('Server error: ' + text);
                    }
                })
                .catch(error => {
                    console.error('Fetch error:', error);
                    alert('Network error: ' + error.message);
                });
        }

        function showSuccessModal(msg) {
            const modal = new bootstrap.Modal(document.getElementById('successModal'));
            document.querySelector('#successModal .modal-body').innerText = msg;
            modal.show();
            setTimeout(() => modal.hide(), 2000);
        }
    </script>
</body>

</html>