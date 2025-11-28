<?php
include "../secure/db.php";

$errors = [];
$success = false;

// Load consultants (if table exists), otherwise fallback
$consultants = [];
$res = @mysqli_query($conn, "SELECT id, name FROM consultants ORDER BY name");
if ($res && mysqli_num_rows($res) > 0) {
    while ($row = mysqli_fetch_assoc($res)) {
        $consultants[] = $row;
    }
} else {
    $fallback = [
        'Dr. Sivanantham',
        'Dr. Manjula',
        'Dr. Maragatham',
        'Dr. Ramya',
        'Dr. Kural Mozhi',
        'Dr. Lathifunisha',
        'Dr. Navyashilpa'
    ];
    foreach ($fallback as $name) {
        $consultants[] = ['id' => '', 'name' => $name];
    }
}

// Handle form submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Collect and trim
    $visitor_date_raw       = trim($_POST['visitor_date'] ?? '');
    $name                   = trim($_POST['name'] ?? '');
    $father_spouse_name     = trim($_POST['father_spouse_name'] ?? '');
    $mobile_no              = trim($_POST['mobile_no'] ?? '');
    $email                  = trim($_POST['email'] ?? '');
    $date_of_birth          = trim($_POST['date_of_birth'] ?? '');
    $age                    = trim($_POST['age'] ?? '');
    $gender                 = trim($_POST['gender'] ?? '');
    $marital_status         = trim($_POST['marital_status'] ?? '');
    $blood_group            = trim($_POST['blood_group'] ?? '');
    $address                = trim($_POST['address'] ?? '');
    $city                   = trim($_POST['city'] ?? '');
    $state                  = trim($_POST['state'] ?? '');
    $occupation             = trim($_POST['occupation'] ?? '');
    $patient_type           = trim($_POST['patient_type'] ?? '');
    $referred_by            = trim($_POST['referred_by'] ?? '');
    $referred_person_mobile = trim($_POST['referred_person_mobile'] ?? '');
    $consultant_id          = trim($_POST['consultant_id'] ?? '');
    $consultant_doctor      = trim($_POST['consultant_doctor'] ?? '');

    // Convert datetime-local to MySQL DATETIME
    $visitor_date = '';
    if ($visitor_date_raw !== '') {
        $ts = strtotime($visitor_date_raw);
        if ($ts !== false) {
            $visitor_date = date('Y-m-d H:i:s', $ts);
        }
    }

    // Validation
    if ($name === '') {
        $errors[] = "Name is required.";
    }
    if ($mobile_no === '' && $email === '') {
        $errors[] = "At least one contact (mobile or email) is required.";
    }
    if ($mobile_no !== '' && !preg_match('/^[0-9 +\-]{6,20}$/', $mobile_no)) {
        $errors[] = "Mobile number looks invalid.";
    }
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Email looks invalid.";
    }

    // Validate consultant if id is selected; if valid, force consultant_doctor = that name
    if ($consultant_id !== '') {
        $valid = false;
        foreach ($consultants as $c) {
            if ((string)$c['id'] === (string)$consultant_id) {
                $valid = true;
                $consultant_doctor = $c['name'];
                break;
            }
        }
        if (!$valid) {
            $errors[] = "Selected consultant is invalid.";
        }
    } else {
        // free-text consultant_doctor allowed
        $consultant_id = '';
    }

    if (empty($errors)) {
        // Insert into patients (matches your table)
        $sql = "INSERT INTO patients (
                    visitor_date,
                    name, father_spouse_name, mobile_no, email,
                    date_of_birth, age, gender, marital_status, blood_group,
                    address, city, state, occupation,
                    patient_type,
                    referred_by, referred_person_mobile,
                    consultant_id, consultant_doctor
                ) VALUES (
                    NULLIF(?, ''),
                    ?, ?, ?, ?,
                    NULLIF(?, ''), NULLIF(?, ''), ?, ?, ?,
                    ?, ?, ?, ?,
                    ?,
                    ?, NULLIF(?, ''), NULLIF(?, ''), NULLIF(?, '')
                )";

        $stmt = mysqli_prepare($conn, $sql);
        if ($stmt) {
            $types = str_repeat('s', 19);
            mysqli_stmt_bind_param(
                $stmt,
                $types,
                $visitor_date,
                $name,
                $father_spouse_name,
                $mobile_no,
                $email,
                $date_of_birth,
                $age,
                $gender,
                $marital_status,
                $blood_group,
                $address,
                $city,
                $state,
                $occupation,
                $patient_type,
                $referred_by,
                $referred_person_mobile,
                $consultant_id,
                $consultant_doctor
            );

            if (mysqli_stmt_execute($stmt)) {
                mysqli_stmt_close($stmt);
                $success = true;
                // you can also clear form if you want:
                // $_POST = [];
            } else {
                $errors[] = "Database error: " . mysqli_stmt_error($stmt);
                mysqli_stmt_close($stmt);
            }
        } else {
            $errors[] = "Prepare failed: " . mysqli_error($conn);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add Patient</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/common.css">
    <link rel="stylesheet" href="../css/patient-pages.css">
</head>
<body class="patient-surface">

<?php if ($success): ?>
    <!-- CENTER POPUP OVERLAY -->
    <div class="success-overlay">
        <div class="success-popup">
            <div class="success-title">Patient added successfully</div>
            <div class="success-text">Redirecting to patient list...</div>
        </div>
    </div>
    <script>
        setTimeout(function () {
            window.location.href = 'patients_view.php';
        }, 2000);
    </script>
<?php endif; ?>

<div class="patient-shell">

    <header class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h1 class="page-title mb-0">Add Patient</h1>
            <p class="sub-text mb-0">Enter patient details</p>
        </div>
        <div class="text-muted fw-semibold">
            <?= date('l, F d, Y') ?>
        </div>
    </header>

    <div class="patient-form-card glass-panel">
        <div class="d-flex align-items-center mb-3">
            <h3 class="card-title mb-0 me-3">New Patient</h3>
            <small class="text-muted">Fields marked * are required</small>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <ul class="mb-0">
                    <?php foreach ($errors as $e): ?>
                        <li><?= htmlspecialchars($e) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="post" class="row g-3" novalidate>
                    <!-- Visit Date & Time | Name* -->
                    <div class="col-md-6">
                        <label class="form-label">Visit Date &amp; Time</label>
                        <input
                            type="datetime-local"
                            name="visitor_date"
                            class="form-control"
                            value="<?= htmlspecialchars($_POST['visitor_date'] ?? '') ?>"
                        >
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Name <span class="text-danger">*</span></label>
                        <input
                            type="text"
                            name="name"
                            class="form-control"
                            value="<?= htmlspecialchars($_POST['name'] ?? '') ?>"
                            required
                        >
                    </div>

                    <!-- Father / Guardian / Spouse | Mobile -->
                    <div class="col-md-6">
                        <label class="form-label">Father / Guardian / Spouse</label>
                        <input
                            type="text"
                            name="father_spouse_name"
                            class="form-control"
                            value="<?= htmlspecialchars($_POST['father_spouse_name'] ?? '') ?>"
                        >
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Mobile No</label>
                        <input
                            type="tel"
                            name="mobile_no"
                            class="form-control"
                            value="<?= htmlspecialchars($_POST['mobile_no'] ?? '') ?>"
                        >
                    </div>

                    <!-- Email | Age -->
                    <div class="col-md-6">
                        <label class="form-label">Email</label>
                        <input
                            type="email"
                            name="email"
                            class="form-control"
                            value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                        >
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Age</label>
                        <input
                            type="number"
                            name="age"
                            class="form-control"
                            value="<?= htmlspecialchars($_POST['age'] ?? '') ?>"
                        >
                    </div>

                    <!-- DOB | Gender -->
                    <div class="col-md-6">
                        <label class="form-label">Date of Birth</label>
                        <input
                            type="date"
                            name="date_of_birth"
                            class="form-control"
                            value="<?= htmlspecialchars($_POST['date_of_birth'] ?? '') ?>"
                        >
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Gender</label>
                        <?php $gender = $_POST['gender'] ?? ''; ?>
                        <select name="gender" class="form-select">
                            <option value="">Select</option>
                            <option value="Male"   <?= $gender === 'Male' ? 'selected' : '' ?>>Male</option>
                            <option value="Female" <?= $gender === 'Female' ? 'selected' : '' ?>>Female</option>
                            <option value="Other"  <?= $gender === 'Other' ? 'selected' : '' ?>>Other</option>
                        </select>
                    </div>

                    <!-- Marital Status | Blood Group -->
                    <div class="col-md-6">
                        <label class="form-label">Marital Status</label>
                        <?php $ms = $_POST['marital_status'] ?? ''; ?>
                        <select name="marital_status" class="form-select">
                            <option value="">Select</option>
                            <option value="Single"   <?= $ms === 'Single' ? 'selected' : '' ?>>Single</option>
                            <option value="Married"  <?= $ms === 'Married' ? 'selected' : '' ?>>Married</option>
                            <option value="Divorced" <?= $ms === 'Divorced' ? 'selected' : '' ?>>Divorced</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Blood Group</label>
                        <?php $bg_val = $_POST['blood_group'] ?? ''; ?>
                        <select name="blood_group" class="form-select">
                            <option value="">Select</option>
                            <?php
                            $bgs = ['A+','A-','B+','B-','AB+','AB-','O+','O-'];
                            foreach ($bgs as $bg) {
                                $sel = $bg_val === $bg ? 'selected' : '';
                                echo '<option value="' . htmlspecialchars($bg) . "\" $sel>" . htmlspecialchars($bg) . '</option>';
                            }
                            ?>
                        </select>
                    </div>

                    <!-- Address | City -->
                    <div class="col-md-6">
                        <label class="form-label">Address</label>
                        <textarea
                            name="address"
                            class="form-control"
                            rows="2"
                        ><?= htmlspecialchars($_POST['address'] ?? '') ?></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">City</label>
                        <input
                            type="text"
                            name="city"
                            class="form-control"
                            value="<?= htmlspecialchars($_POST['city'] ?? '') ?>"
                        >
                    </div>

                    <!-- State | Occupation -->
                    <div class="col-md-6">
                        <label class="form-label">State</label>
                        <input
                            type="text"
                            name="state"
                            class="form-control"
                            value="<?= htmlspecialchars($_POST['state'] ?? '') ?>"
                        >
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Occupation</label>
                        <input
                            type="text"
                            name="occupation"
                            class="form-control"
                            value="<?= htmlspecialchars($_POST['occupation'] ?? '') ?>"
                        >
                    </div>

                    <!-- Patient Type | Referred By -->
                    <div class="col-md-6">
                        <label class="form-label">Patient Type</label>
                        <?php $pt = $_POST['patient_type'] ?? ''; ?>
                        <select name="patient_type" class="form-select">
                            <option value="">Select</option>
                            <option value="new"       <?= $pt === 'new' ? 'selected' : '' ?>>New</option>
                            <option value="follow-up" <?= $pt === 'follow-up' ? 'selected' : '' ?>>Follow-up</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Referred By</label>
                        <input
                            type="text"
                            name="referred_by"
                            class="form-control"
                            value="<?= htmlspecialchars($_POST['referred_by'] ?? '') ?>"
                        >
                    </div>

                    <!-- Referred Person Mobile | Consultant -->
                    <div class="col-md-6">
                        <label class="form-label">Referred Person Mobile</label>
                        <input
                            type="tel"
                            name="referred_person_mobile"
                            class="form-control"
                            value="<?= htmlspecialchars($_POST['referred_person_mobile'] ?? '') ?>"
                        >
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Consultant</label>
                        <select name="consultant_id" class="form-select" onchange="setConsultantName(this)">
                            <option value="">Select (optional)</option>
                            <?php
                            $cid_post = $_POST['consultant_id'] ?? '';
                            foreach ($consultants as $c):
                                $cid = (string)($c['id'] ?? '');
                                $selected = ($cid_post !== '' && $cid_post === $cid) ? 'selected' : '';
                                ?>
                                <option
                                    value="<?= htmlspecialchars($cid) ?>"
                                    data-name="<?= htmlspecialchars($c['name']) ?>"
                                    <?= $selected ?>
                                >
                                    <?= htmlspecialchars($c['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <input
                            type="hidden"
                            name="consultant_doctor"
                            id="consultant_doctor"
                            value="<?= htmlspecialchars($_POST['consultant_doctor'] ?? '') ?>"
                        >
                    </div>

                    <!-- Buttons -->
                <div class="col-12">
                    <div class="d-flex justify-content-end gap-2 form-actions-stack mt-2">
                        <a href="patients_view.php" class="btn btn-cancel">Cancel</a>
                        <button type="submit" class="btn btn-add">Add Patient</button>
                    </div>
                </div>
        </form>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function setConsultantName(sel) {
        var opt = sel.options[sel.selectedIndex];
        var name = opt ? (opt.getAttribute('data-name') || '') : '';
        var hidden = document.getElementById('consultant_doctor');
        if (hidden) hidden.value = name;
    }
    (function () {
        var sel = document.querySelector('select[name="consultant_id"]');
        if (sel) setConsultantName(sel);
    })();
</script>
</body>
</html>
