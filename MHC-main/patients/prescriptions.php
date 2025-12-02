<?php
// prescriptions.php (fixed for unknown consultant column names)
//
// Notes:
// - Requires ../secure/db.php which defines $conn (mysqli).
// - Replaces direct use of "con.name" with a runtime-detected column name
//   (tries name, consultant_name, full_name, first_name, firstname).
// - Turns off mysqli exception reporting to prevent uncaught exceptions.

mysqli_report(MYSQLI_REPORT_OFF); // avoid uncaught mysqli_sql_exception

include "../secure/db.php";
function h($v)
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

$patient_id = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;
$case_id    = isset($_GET['case_id'])    ? (int)$_GET['case_id'] : 0;

/* if patient_id missing but case supplied, read patient_id from case */
if ($patient_id <= 0 && $case_id > 0) {
    $stmt = mysqli_prepare($conn, "SELECT patient_id FROM cases WHERE id = ?");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $case_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_bind_result($stmt, $pid_from_case);
        if (mysqli_stmt_fetch($stmt)) $patient_id = (int)$pid_from_case;
        mysqli_stmt_close($stmt);
    }
}

/* friendly error if invalid */
if ($patient_id <= 0) {
?>
    <!DOCTYPE html>
    <html>

    <head>
        <meta charset="utf-8">
        <title>Prescriptions - Invalid Patient</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    </head>

    <body>
        <div class="container mt-5">
            <div class="alert alert-danger">Invalid patient. Use <code>?patient_id=&lt;id&gt;</code></div>
        </div>
    </body>

    </html>
<?php
    exit;
}

/* Fetch patient */
$patient = null;
$stmt = mysqli_prepare($conn, "SELECT id, name, age, gender, mobile_no FROM patients WHERE id = ?");
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "i", $patient_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    if ($res) $patient = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);
}

if (!$patient) {
    echo "<div class='alert alert-warning'>Patient not found</div>";
    exit;
}

$case = ['id' => '', 'visit_date' => '', 'status' => '', 'consultant_id' => '', 'consultant_name' => ''];
$total_cases = 0;

if ($case_id > 0) {
    $sql = "
        SELECT c.id, c.visit_date, c.status, c.consultant_id
        FROM cases c
        WHERE c.id = ? LIMIT 1
    ";
    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $case_id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        if ($res && $row = mysqli_fetch_assoc($res)) {
            $case = $row;
        }
        mysqli_stmt_close($stmt);
    }
}

// If no case_id supplied, get latest case for the patient
if (empty($case['id'])) {
    $sql = "
        SELECT c.id, c.visit_date, c.status, c.consultant_id
        FROM cases c
        WHERE c.patient_id = ?
        ORDER BY c.visit_date DESC
        LIMIT 1
    ";
    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $patient_id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        if ($res && $row = mysqli_fetch_assoc($res)) {
            $case = $row;
            $case_id = (int)$case['id']; // reflect back
        }
        mysqli_stmt_close($stmt);
    }
}

// Count total cases for this patient
$stmtCount = mysqli_prepare($conn, "SELECT COUNT(*) as cnt FROM cases WHERE patient_id = ?");
if ($stmtCount) {
    mysqli_stmt_bind_param($stmtCount, "i", $patient_id);
    mysqli_stmt_execute($stmtCount);
    $resCount = mysqli_stmt_get_result($stmtCount);
    if ($resCount && $rc = mysqli_fetch_assoc($resCount)) {
        $total_cases = (int)$rc['cnt'];
    }
    mysqli_stmt_close($stmtCount);
}

// Fetch consultant name (from consultants -> users) if consultant_id present
if (!empty($case['consultant_id'])) {
    $stmtCons = mysqli_prepare($conn, "
        SELECT u.name AS consultant_name
        FROM consultants cons
        LEFT JOIN users u ON cons.user_id = u.id
        WHERE cons.id = ? LIMIT 1
    ");
    if ($stmtCons) {
        mysqli_stmt_bind_param($stmtCons, "i", $case['consultant_id']);
        mysqli_stmt_execute($stmtCons);
        $resCons = mysqli_stmt_get_result($stmtCons);
        if ($resCons && $rcn = mysqli_fetch_assoc($resCons)) {
            $case['consultant_name'] = $rcn['consultant_name'];
        }
        mysqli_stmt_close($stmtCons);
    }
}

// Fetch medicine history (include created_at for date)
$history = [];
$sql = "SELECT medicine_name, medicine_category, created_at FROM prescriptions WHERE patient_id = ? ORDER BY created_at DESC LIMIT 200";
$stmt = mysqli_prepare($conn, $sql);
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "i", $patient_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    if ($res) while ($r = mysqli_fetch_assoc($res)) $history[] = $r;
    mysqli_stmt_close($stmt);
}
?>
<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Prescriptions - <?= h($patient['name']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <link rel="stylesheet" href="../css/prescription.css">
</head>

<body>
    <div class="container-prescription">

        <!-- SUCCESS MODAL -->
        <div class="modal fade" id="successModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
            <div class="modal-dialog modal-dialog-centered modal-sm">
                <div class="modal-content border-0 shadow-lg">
                    <div class="modal-header bg-success text-white border-0">
                        <h5 class="modal-title fw-bold">
                            <i class="fas fa-check-circle me-2"></i>Success
                        </h5>
                    </div>
                    <div class="modal-body text-center py-4">
                        <i class="fas fa-check-circle text-success" style="font-size: 3.2rem; margin-bottom: 12px;"></i>
                        <h6 class="mt-2 text-dark fw-bold">Saved</h6>
                        <p class="text-muted small mt-1 mb-0">Prescription saved successfully.</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="prescription-panel">

            <!-- Patient Header -->
            <div class="patient-head-card">
                <div class="ph-left">
                    <div class="ph-name"><?= h($patient['name']) ?></div>

                    <div class="pres-line">
                        <span class="pres-label">Patient ID:</span>
                        <span class="pres-value"><?= "P-" . h($patient['id']) ?></span>
                    </div>

                    <div class="pres-line">
                        <span class="pres-label">Case ID:</span>
                        <span class="pres-value"><?= $case['id'] ? ("#" . (int)$case['id']) : '--' ?></span>
                    </div>

                    <div class="pres-line">
                        <span class="pres-label">Last Visit:</span>
                        <span class="pres-value"><?= $case['visit_date'] ? h(date('M d, Y', strtotime($case['visit_date']))) : '--' ?></span>
                    </div>

                    <div class="pres-line">
                        <span class="pres-label">Total Cases:</span>
                        <span class="pres-value"><?= $total_cases ?></span>
                    </div>
                </div>

                <div class="ph-right">
                    <div class="ph-consult-title">Consultant</div>
                    <div class="ph-consult-name"><?= h($case['consultant_name'] ?: "--") ?></div>

                    <!-- <?php if ($case['id']): ?>
                        <div class="mt-2">
                            <a href="case.php?patient_id=<?= (int)$patient['id'] ?>&case_id=<?= (int)$case['id'] ?>&open_tab=prescriptions" class="btn btn-outline-primary btn-sm">Open Case</a>
                        </div>
                    <?php endif; ?> -->
                </div>
            </div>

            <!-- Category Selection -->
            <div class="block-title">MEDICINE CATEGORY</div>
            <div class="category-radio mb-3">
                <label><input type="radio" name="category" value="constitutional"> Constitutional</label>
                <label><input type="radio" name="category" value="acute"> Acute</label>
                <label><input type="radio" name="category" value="supplementary"> Supplementary</label>
                <label><input type="radio" name="category" value="other" checked> Other</label>
            </div>

            <!-- Add Medicine Form -->
            <div class="block-title">ADD MEDICINE</div>
            <div class="row g-2 mb-3">
                <div class="col-md-4">
                    <label class="form-label">Medicine Name *</label>
                    <input id="medicine_name" class="form-control" placeholder="e.g., Belladonna">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Potency</label>
                    <select id="potency" class="form-select">
                        <option value="">Select</option>
                        <option>30C</option>
                        <option>200C</option>
                        <option>1M</option>
                        <option>10M</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Dosage</label>
                    <select id="dosage" class="form-select">
                        <option value="">Select</option>
                        <option>5 drops</option>
                        <option>3 pills</option>
                        <option>1 spoon</option>
                        <option>10 drops</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Frequency</label>
                    <select id="frequency" class="form-select">
                        <option value="">Select</option>
                        <option>OD</option>
                        <option>BD</option>
                        <option>TDS</option>
                        <option>QID</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Duration</label>
                    <select id="duration" class="form-select">
                        <option value="">Select</option>
                        <option>3 days</option>
                        <option>5 days</option>
                        <option>1 week</option>
                        <option>2 weeks</option>
                        <option>1 month</option>
                    </select>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">Instructions</label>
                <textarea id="instructions" class="form-control" rows="2" placeholder="Optional instructions..."></textarea>
            </div>

            <button id="add_medicine" class="btn btn-success">
                <i class="fas fa-plus me-2"></i>ADD MEDICINE
            </button>

            <div id="add_result" class="alert mt-3" style="display:none;"></div>

            <!-- Prescription List -->
            <div class="block-title mt-4">CURRENT PRESCRIPTIONS</div>
            <div class="table-responsive table-prescription">
                <table class="table table-bordered table-sm">
                    <thead>
                        <tr>
                            <th style="width:50px">S.No</th>
                            <th>Category</th>
                            <th>Medicine</th>
                            <th>Potency</th>
                            <th>Frequency</th>
                            <th>Duration</th>
                            <th style="width:80px">Edit</th>
                            <th style="width:80px">Delete</th>
                        </tr>
                    </thead>
                    <tbody id="prescription_body">
                        <tr>
                            <td colspan="8" class="text-center text-muted">Loading...</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Medicine History -->
            <div class="block-title mt-4">MEDICINE HISTORY</div>
            <div class="history-block">
                <strong>Constitutional:</strong>
                <ul class="history-bullet">
                    <?php foreach ($history as $h) {
                        if ($h['medicine_category'] === 'constitutional') {
                            echo "<li>" . h($h['medicine_name']) . " <span class='text-muted small'>(" . h(date('d M Y', strtotime($h['created_at'] ?? ''))) . ")</span></li>";
                        }
                    } ?>
                </ul>

                <strong>Acute:</strong>
                <ul class="history-bullet">
                    <?php foreach ($history as $h) {
                        if ($h['medicine_category'] === 'acute') {
                            echo "<li>" . h($h['medicine_name']) . " <span class='text-muted small'>(" . h(date('d M Y', strtotime($h['created_at'] ?? ''))) . ")</span></li>";
                        }
                    } ?>
                </ul>

                <strong>Supplementary:</strong>
                <ul class="history-bullet">
                    <?php foreach ($history as $h) {
                        if ($h['medicine_category'] === 'supplementary') {
                            echo "<li>" . h($h['medicine_name']) . " <span class='text-muted small'>(" . h(date('d M Y', strtotime($h['created_at'] ?? ''))) . ")</span></li>";
                        }
                    } ?>
                </ul>
            </div>

        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const PATIENT_ID = <?= json_encode((int)$patient_id) ?>;
        const CASE_ID = <?= json_encode($case_id > 0 ? (int)$case_id : null) ?>;
        let successModal;

        function loadPrescriptionList() {
            console.log("Loading prescriptions for patient:", PATIENT_ID, "case:", CASE_ID);

            $.ajax({
                type: "POST",
                url: "prescription_ajax.php",
                data: {
                    action: "list",
                    patient_id: PATIENT_ID,
                    case_id: CASE_ID
                },
                success: function(html) {
                    console.log("Response received:", html);
                    if (html && html.trim()) {
                        $("#prescription_body").html(html);
                    } else {
                        $("#prescription_body").html('<tr><td colspan="8" class="text-center text-muted">No prescriptions found</td></tr>');
                    }
                },
                error: function(xhr, status, error) {
                    console.error("AJAX Error:", status, error, xhr.responseText);
                    $("#prescription_body").html('<tr><td colspan="8" class="text-center text-danger">Error loading prescriptions</td></tr>');
                }
            });
        }

        function loadHistory() {
            $.ajax({
                type: "POST",
                url: "prescription_ajax.php",
                data: {
                    action: "history",
                    patient_id: PATIENT_ID
                },
                success: function(html) {
                    try {
                        // replace the history-block container
                        $(".history-block").first().replaceWith(html);
                    } catch (e) {
                        console.error("Failed to update history UI", e);
                    }
                },
                error: function(xhr, status, err) {
                    console.error("History load error:", status, err);
                }
            });
        }

        function showResult(type, message) {
            const resultDiv = $("#add_result");
            resultDiv.removeClass("alert-success alert-danger");
            resultDiv.addClass("alert-" + (type === 'success' ? 'success' : 'danger'));
            resultDiv.html('<i class="fas fa-' + (type === 'success' ? 'check-circle' : 'exclamation-circle') + ' me-2"></i>' + message).show();
            setTimeout(() => resultDiv.hide(), 4000);
        }

        $(function() {
            // Initialize success modal
            successModal = new bootstrap.Modal(document.getElementById('successModal'));

            // Load prescriptions on page load
            loadPrescriptionList();
            loadHistory(); // <--- load history on page load

            // Add medicine button click
            $("#add_medicine").click(function() {
                const medicine_name = $("#medicine_name").val().trim();
                const potency = $("#potency").val().trim();
                const dosage = $("#dosage").val().trim();
                const frequency = $("#frequency").val().trim();
                const duration = $("#duration").val().trim();
                const instructions = $("#instructions").val().trim();
                const category = $('input[name="category"]:checked').val();

                if (!medicine_name) {
                    showResult("error", "Medicine name is required!");
                    return;
                }

                console.log("Adding medicine:", {
                    patient_id: PATIENT_ID,
                    case_id: CASE_ID,
                    medicine_name: medicine_name,
                    category: category
                });

                $.ajax({
                    type: "POST",
                    url: "prescription_ajax.php",
                    data: {
                        action: "add",
                        patient_id: PATIENT_ID,
                        case_id: CASE_ID,
                        medicine_name: medicine_name,
                        potency: potency,
                        dosage: dosage,
                        frequency: frequency,
                        duration: duration,
                        instructions: instructions,
                        category: category
                    },
                    dataType: "json",
                    success: function(data) {
                        console.log("Add response:", data);
                        if (data.success) {
                            // Show success modal
                            successModal.show();

                            // Auto-hide after 2.5 seconds
                            setTimeout(() => {
                                successModal.hide();
                            }, 2500);

                            // Clear form fields
                            $("#medicine_name").val("");
                            $("#potency").val("");
                            $("#dosage").val("");
                            $("#frequency").val("");
                            $("#duration").val("");
                            $("#instructions").val("");
                            $('input[name="category"]').prop('checked', false);
                            $('input[name="category"][value="other"]').prop('checked', true);

                            // Reload prescription list
                            setTimeout(() => {
                                loadPrescriptionList();
                                loadHistory(); // Reload history
                            }, 500);
                        } else {
                            showResult("error", data.message || "Failed to add medicine");
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error("Add error:", status, error);
                        showResult("error", "Network error: " + error);
                    }
                });
            });

            // Delete button click
            $(document).on("click", ".btn-delete", function() {
                const id = $(this).data("id");
                if (confirm("Delete this prescription?")) {
                    $.ajax({
                        type: "POST",
                        url: "prescription_ajax.php",
                        data: {
                            action: "delete",
                            id: id
                        },
                        dataType: "json",
                        success: function(data) {
                            if (data.success) {
                                loadPrescriptionList();
                                loadHistory(); // Reload history
                                showResult("success", "Prescription deleted");
                            }
                        },
                        error: function() {
                            showResult("error", "Delete failed");
                        }
                    });
                }
            });

            // Edit button click
            $(document).on("click", ".btn-edit", function() {
                alert("Edit feature coming soon");
            });
        });
    </script>
</body>

</html>