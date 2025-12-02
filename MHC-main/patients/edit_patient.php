<?php
include "../secure/db.php";

// ------------ helpers ------------
function h($v) {
    return htmlspecialchars((string)$v ?? '', ENT_QUOTES, 'UTF-8');
}

// ------------ load patient ------------
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    die("Invalid patient ID.");
}

$sql = "SELECT * FROM patients WHERE id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$patient = mysqli_fetch_assoc($res);
mysqli_stmt_close($stmt);

if (!$patient) {
    die("Patient not found.");
}

// ------------ defaults from DB ------------
$visitor_date_raw       = $patient['visitor_date'] ? date('Y-m-d\TH:i', strtotime($patient['visitor_date'])) : '';
$name                   = $patient['name'] ?? '';
$father_spouse_name     = $patient['father_spouse_name'] ?? '';
$mobile_no              = $patient['mobile_no'] ?? '';
$email                  = $patient['email'] ?? '';
$date_of_birth          = $patient['date_of_birth'] ?? '';
$age                    = $patient['age'] ?? '';
$gender                 = $patient['gender'] ?? '';
$marital_status         = $patient['marital_status'] ?? '';
$blood_group            = $patient['blood_group'] ?? '';
$address                = $patient['address'] ?? '';
$city                   = $patient['city'] ?? '';
$state                  = $patient['state'] ?? '';
$occupation             = $patient['occupation'] ?? '';
$referred_by            = $patient['referred_by'] ?? '';
$referred_person_mobile = $patient['referred_person_mobile'] ?? '';

$errors  = [];
$success = false;

// ------------ handle POST ------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // overwrite with POST values
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
    $referred_by            = trim($_POST['referred_by'] ?? '');
    $referred_person_mobile = trim($_POST['referred_person_mobile'] ?? '');

    // convert datetime-local to MySQL DATETIME
    $visitor_date = '';
    if ($visitor_date_raw !== '') {
        $ts = strtotime($visitor_date_raw);
        if ($ts !== false) {
            $visitor_date = date('Y-m-d H:i:s', $ts);
        }
    }

    // basic validation
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

    if (empty($errors)) {
        $sql = "UPDATE patients SET
                    visitor_date           = NULLIF(?, ''),
                    name                   = ?,
                    father_spouse_name     = ?,
                    mobile_no              = ?,
                    email                  = ?,
                    date_of_birth          = NULLIF(?, ''),
                    age                    = NULLIF(?, ''),
                    gender                 = ?,
                    marital_status         = ?,
                    blood_group            = ?,
                    address                = ?,
                    city                   = ?,
                    state                  = ?,
                    occupation             = ?,
                    referred_by            = ?,
                    referred_person_mobile = NULLIF(?, '')
                WHERE id = ?";

        $stmt = mysqli_prepare($conn, $sql);
        if ($stmt) {
            // 16 's' params + 1 'i'
            $types = str_repeat('s', 16) . 'i';

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
                $referred_by,
                $referred_person_mobile,
                $id
            );

            if (mysqli_stmt_execute($stmt)) {
                $success = true;
            } else {
                $errors[] = "Update failed: " . mysqli_stmt_error($stmt);
            }
            mysqli_stmt_close($stmt);
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
    <title>Edit Patient</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/common.css">
    <link rel="stylesheet" href="../css/patient-pages.css">
</head>
<body class="patient-surface">

<div class="patient-shell">
    <header class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h1 class="page-title mb-0">Edit Patient</h1>
            <p class="sub-text mb-0">PAT<?= $id ?> – <?= h($patient['name']) ?></p>
        </div>
        <div class="d-flex gap-2">
            <a class="btn btn-cancel" href="patients_view.php">← Back to List</a>
            <a class="btn btn-secondary" href="patient.php?id=<?= $id ?>">View Profile</a>
        </div>
    </header>

    <div class="patient-form-card glass-panel">

        <?php if ($success && empty($errors)): ?>
            <div class="alert alert-success py-2 mb-3">
                Patient updated successfully.
            </div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger py-2 mb-3">
                <ul class="mb-0">
                    <?php foreach ($errors as $e): ?>
                        <li><?= h($e) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="post" class="row g-3" novalidate>

            <!-- Visit Date & Time | Name -->
            <div class="col-md-6">
                <label class="form-label">Visit Date &amp; Time</label>
                <input
                    type="datetime-local"
                    name="visitor_date"
                    class="form-control"
                    value="<?= h($visitor_date_raw) ?>"
                >
            </div>
            <div class="col-md-6">
                <label class="form-label">Name <span class="text-danger">*</span></label>
                <input
                    type="text"
                    name="name"
                    class="form-control"
                    value="<?= h($name) ?>"
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
                    value="<?= h($father_spouse_name) ?>"
                >
            </div>
            <div class="col-md-6">
                <label class="form-label">Mobile No</label>
                <input
                    type="tel"
                    name="mobile_no"
                    class="form-control"
                    value="<?= h($mobile_no) ?>"
                >
            </div>

            <!-- Email | Age -->
            <div class="col-md-6">
                <label class="form-label">Email</label>
                <input
                    type="email"
                    name="email"
                    class="form-control"
                    value="<?= h($email) ?>"
                >
            </div>
            <div class="col-md-6">
                <label class="form-label">Age</label>
                <input
                    type="number"
                    name="age"
                    class="form-control"
                    value="<?= h($age) ?>"
                >
            </div>

            <!-- DOB | Gender -->
            <div class="col-md-6">
                <label class="form-label">Date of Birth</label>
                <input
                    type="date"
                    name="date_of_birth"
                    class="form-control"
                    value="<?= h($date_of_birth) ?>"
                >
            </div>
            <div class="col-md-6">
                <label class="form-label">Gender</label>
                <select name="gender" class="form-select">
                    <?php $g = $gender; ?>
                    <option value="">Select</option>
                    <option value="Male"   <?= $g === 'Male' ? 'selected' : '' ?>>Male</option>
                    <option value="Female" <?= $g === 'Female' ? 'selected' : '' ?>>Female</option>
                    <option value="Other"  <?= $g === 'Other' ? 'selected' : '' ?>>Other</option>
                </select>
            </div>

            <!-- Marital Status | Blood Group -->
            <div class="col-md-6">
                <label class="form-label">Marital Status</label>
                <?php $ms = $marital_status; ?>
                <select name="marital_status" class="form-select">
                    <option value="">Select</option>
                    <option value="Single"   <?= $ms === 'Single' ? 'selected' : '' ?>>Single</option>
                    <option value="Married"  <?= $ms === 'Married' ? 'selected' : '' ?>>Married</option>
                    <option value="Divorced" <?= $ms === 'Divorced' ? 'selected' : '' ?>>Divorced</option>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">Blood Group</label>
                <?php $bg_val = $blood_group; ?>
                <select name="blood_group" class="form-select">
                    <option value="">Select</option>
                    <?php
                    $bgs = ['A+','A-','B+','B-','AB+','AB-','O+','O-'];
                    foreach ($bgs as $bg) {
                        $sel = $bg_val === $bg ? 'selected' : '';
                        echo '<option value="'.h($bg).'" '.$sel.'>'.h($bg).'</option>';
                    }
                    ?>
                </select>
            </div>

            <!-- Address | City -->
            <div class="col-md-6">
                <label class="form-label">Address</label>
                <textarea
                    name="address"
                    rows="2"
                    class="form-control"
                ><?= h($address) ?></textarea>
            </div>
            <div class="col-md-6">
                <label class="form-label">City</label>
                <input
                    type="text"
                    name="city"
                    class="form-control"
                    value="<?= h($city) ?>"
                >
            </div>

            <!-- State | Occupation -->
            <div class="col-md-6">
                <label class="form-label">State</label>
                <input
                    type="text"
                    name="state"
                    class="form-control"
                    value="<?= h($state) ?>"
                >
            </div>
            <div class="col-md-6">
                <label class="form-label">Occupation</label>
                <input
                    type="text"
                    name="occupation"
                    class="form-control"
                    value="<?= h($occupation) ?>"
                >
            </div>

            <!-- Referred By | Referred Person Mobile -->
            <div class="col-md-6">
                <label class="form-label">Referred By</label>
                <input
                    type="text"
                    name="referred_by"
                    class="form-control"
                    value="<?= h($referred_by) ?>"
                >
            </div>
            <div class="col-md-6">
                <label class="form-label">Referred Person Mobile</label>
                <input
                    type="tel"
                    name="referred_person_mobile"
                    class="form-control"
                    value="<?= h($referred_person_mobile) ?>"
                >
            </div>

            <!-- Buttons -->
            <div class="col-12 d-flex justify-content-end gap-2 form-actions-stack mt-2">
                <a href="patient.php?id=<?= $id ?>" class="btn btn-cancel">Cancel</a>
                <button type="submit" class="btn btn-add">Save Changes</button>
            </div>

        </form>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
