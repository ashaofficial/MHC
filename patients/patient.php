<?php
include "../auth.php";
include "../components/helpers.php";
include "../secure/db.php";

$role = $USER['role'] ?? '';
$isAdminRole = isAdmin($role);
$isReceptionistRole = hasRole($role, 'receptionist');
$isConsultantRole = hasRole($role, 'consultant');
$canManageBills = $isAdminRole || $isReceptionistRole;

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
            
            <div class="d-flex gap-3 flex-wrap mt-4 mb-4">
                <?php if ($canManageBills): ?>
                    <button type="button" 
                            class="btn btn-primary btn-lg billing-action-btn" 
                            data-action="create"
                            data-url="../billing/create_bill.php?patient_id=<?= $patient['id'] ?>">
                        <i class="bi bi-plus-circle"></i> Create Bill
                    </button>
                    <button type="button" 
                            class="btn btn-warning btn-lg billing-action-btn" 
                            data-action="update"
                            data-url="../billing/update_bill.php?patient_id=<?= $patient['id'] ?>">
                        <i class="bi bi-pencil-square"></i> Update Bill
                    </button>
                <?php endif; ?>
                
                <button type="button" 
                        class="btn btn-info btn-lg billing-action-btn" 
                        data-action="history"
                        data-url="../billing/bill_history.php?patient_id=<?= $patient['id'] ?>">
                    <i class="bi bi-clock-history"></i> Bill History
                </button>
            </div>
            
            <!-- Billing Content Area -->
            <div id="billingContentArea" class="billing-content-area" style="display: none;">
                <iframe id="billingFrame" 
                        class="billing-frame" 
                        style="width:100%; min-height:600px; border:0; border-radius:8px;"
                        title="Billing Content">
                </iframe>
            </div>
            
            <?php if ($canManageBills): ?>
                <p class="text-muted mt-3">
                    <small>Click any button above to manage billing for this patient. The patient details will be automatically populated when creating a bill.</small>
                </p>
            <?php else: ?>
                <p class="text-muted mt-3">
                    <small>View the billing history for this patient.</small>
                </p>
            <?php endif; ?>
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
    
    // Billing action buttons
    document.addEventListener('DOMContentLoaded', function() {
        const billingButtons = document.querySelectorAll('.billing-action-btn');
        const billingContentArea = document.getElementById('billingContentArea');
        const billingFrame = document.getElementById('billingFrame');
        
        // Function to load billing content
        function loadBillingContent(url, action) {
            // Remove active class from all buttons
            billingButtons.forEach(function(b) {
                b.classList.remove('active');
            });
            
            // Add active class to clicked button
            const activeBtn = document.querySelector('.billing-action-btn[data-action="' + action + '"]');
            if (activeBtn) {
                activeBtn.classList.add('active');
            }
            
            // Show content area and load iframe
            if (billingContentArea && billingFrame) {
                billingContentArea.style.display = 'block';
                billingFrame.src = url;
                
                // Scroll to content area
                billingContentArea.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        }
        
        billingButtons.forEach(function(btn) {
            btn.addEventListener('click', function() {
                const url = this.getAttribute('data-url');
                const action = this.getAttribute('data-action');
                loadBillingContent(url, action);
            });
        });
        
        // Function to switch to update bill tab and load specific bill
        window.switchToUpdateBill = function(billId) {
            // Switch to billing tab first
            const billingTab = document.getElementById('billing-tab');
            if (billingTab) {
                const tabTrigger = new bootstrap.Tab(billingTab);
                tabTrigger.show();
                
                // Wait for tab to show, then load update bill
                billingTab.addEventListener('shown.bs.tab', function loadUpdateBill() {
                    const updateUrl = '../billing/update_bill.php?patient_id=<?= $patient['id'] ?>&bill_id=' + billId;
                    loadBillingContent(updateUrl, 'update');
                    billingTab.removeEventListener('shown.bs.tab', loadUpdateBill);
                }, { once: true });
            } else {
                // If already on billing tab, just load the update bill
                const updateUrl = '../billing/update_bill.php?patient_id=<?= $patient['id'] ?>&bill_id=' + billId;
                loadBillingContent(updateUrl, 'update');
            }
        };
        
        // Listen for postMessage from iframe
        window.addEventListener('message', function(event) {
            if (event.data && event.data.type === 'editBill') {
                window.switchToUpdateBill(event.data.billId);
            }
        });
        
        // Auto-load bill history when billing tab is first opened
        const billingTab = document.getElementById('billing-tab');
        if (billingTab) {
            billingTab.addEventListener('shown.bs.tab', function() {
                // If no content is loaded yet, load bill history by default
                if (billingContentArea && billingContentArea.style.display === 'none') {
                    const historyBtn = document.querySelector('.billing-action-btn[data-action="history"]');
                    if (historyBtn) {
                        historyBtn.click();
                    }
                }
            });
        }
    });
</script>
<style>
    .billing-action-btn.active {
        box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.25);
        font-weight: bold;
    }
    
    .billing-content-area {
        margin-top: 20px;
        background: #f8f9fa;
        padding: 15px;
        border-radius: 8px;
    }
    
    .billing-frame {
        background: white;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
</style>
</body>
</html>
