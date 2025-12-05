<?php
include "../secure/db.php";

$errors = [];

// Handle form submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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

    if (empty($errors)) {
        $sql = "INSERT INTO patients (
                    visitor_date,
                    name, father_spouse_name, mobile_no, email,
                    date_of_birth, age, gender, marital_status, blood_group,
                    address, city, state, occupation,
                    referred_by, referred_person_mobile
                ) VALUES (
                    NULLIF(?, ''),
                    ?, ?, ?, ?,
                    NULLIF(?, ''), NULLIF(?, ''), ?, ?, ?,
                    ?, ?, ?, ?,
                    ?, NULLIF(?, '')
                )";

        $stmt = mysqli_prepare($conn, $sql);
        if ($stmt) {
            $types = str_repeat('s', 16);
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
                $referred_person_mobile
            );

            if (mysqli_stmt_execute($stmt)) {
                mysqli_stmt_close($stmt);
                // Redirect to list after success
                header("Location: patients_view.php");
                exit;
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

    <!-- Bootstrap (optional) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Your common layout (if any) -->
    <link rel="stylesheet" href="../css/common.css">

    <!-- Page-specific CSS -->
    <link rel="stylesheet" href="../css/patient_add.css">
</head>
<body>
<div class="app-layout">
    <main class="main-content">

        <header class="main-header">
            <div>
                <h1 class="page-title">Add Patient</h1>
                <p class="welcome-text">Enter new patient details</p>
            </div>
            <div class="current-date">
                <?= date('l, F d, Y') ?>
            </div>
        </header>

        <section class="form-card">
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger mb-3">
                    <ul class="mb-0">
                        <?php foreach ($errors as $e): ?>
                            <li><?= htmlspecialchars($e) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="post" class="row g-3" novalidate>
                <!-- Visit Date & Time | Name -->
                <div class="col-md-6">
                    <label class="form-label-custom">Visit Date &amp; Time</label>
                    <input
                        type="datetime-local"
                        name="visitor_date"
                        class="form-control-custom"
                        value="<?= htmlspecialchars($_POST['visitor_date'] ?? '') ?>"
                    >
                </div>
                <div class="col-md-6">
                    <label class="form-label-custom">Name <span class="text-danger">*</span></label>
                    <input
                        type="text"
                        name="name"
                        class="form-control-custom"
                        required
                        value="<?= htmlspecialchars($_POST['name'] ?? '') ?>"
                    >
                </div>

                <!-- Father / Guardian / Spouse | Mobile -->
                <div class="col-md-6">
                    <label class="form-label-custom">Father / Guardian / Spouse</label>
                    <input
                        type="text"
                        name="father_spouse_name"
                        class="form-control-custom"
                        value="<?= htmlspecialchars($_POST['father_spouse_name'] ?? '') ?>"
                    >
                </div>
                <div class="col-md-6">
                    <label class="form-label-custom">Mobile No</label>
                    <input
                        type="tel"
                        name="mobile_no"
                        class="form-control-custom"
                        value="<?= htmlspecialchars($_POST['mobile_no'] ?? '') ?>"
                    >
                </div>

                <!-- Email | Age -->
                <div class="col-md-6">
                    <label class="form-label-custom">Email</label>
                    <input
                        type="email"
                        name="email"
                        class="form-control-custom"
                        value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                    >
                </div>
                <div class="col-md-6">
                    <label class="form-label-custom">Age</label>
                    <input
                        type="number"
                        name="age"
                        class="form-control-custom"
                        value="<?= htmlspecialchars($_POST['age'] ?? '') ?>"
                    >
                </div>

                <!-- DOB | Gender -->
                <div class="col-md-6">
                    <label class="form-label-custom">Date of Birth</label>
                    <input
                        type="date"
                        name="date_of_birth"
                        class="form-control-custom"
                        value="<?= htmlspecialchars($_POST['date_of_birth'] ?? '') ?>"
                    >
                </div>
                <div class="col-md-6">
                    <label class="form-label-custom">Gender</label>
                    <?php $gender = $_POST['gender'] ?? ''; ?>
                    <select name="gender" class="form-control-custom">
                        <option value="">Select</option>
                        <option value="Male"   <?= $gender === 'Male' ? 'selected' : '' ?>>Male</option>
                        <option value="Female" <?= $gender === 'Female' ? 'selected' : '' ?>>Female</option>
                        <option value="Other"  <?= $gender === 'Other' ? 'selected' : '' ?>>Other</option>
                    </select>
                </div>

                <!-- Marital Status | Blood Group -->
                <div class="col-md-6">
                    <label class="form-label-custom">Marital Status</label>
                    <?php $ms = $_POST['marital_status'] ?? ''; ?>
                    <select name="marital_status" class="form-control-custom">
                        <option value="">Select</option>
                        <option value="Single"   <?= $ms === 'Single' ? 'selected' : '' ?>>Single</option>
                        <option value="Married"  <?= $ms === 'Married' ? 'selected' : '' ?>>Married</option>
                        <option value="Divorced" <?= $ms === 'Divorced' ? 'selected' : '' ?>>Divorced</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label-custom">Blood Group</label>
                    <?php $bg_val = $_POST['blood_group'] ?? ''; ?>
                    <select name="blood_group" class="form-control-custom">
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
                    <label class="form-label-custom">Address</label>
                    <textarea
                        name="address"
                        rows="2"
                        class="form-control-custom"
                    ><?= htmlspecialchars($_POST['address'] ?? '') ?></textarea>
                </div>
                <div class="col-md-6">
                    <label class="form-label-custom">City</label>
                    <input
                        type="text"
                        name="city"
                        class="form-control-custom"
                        value="<?= htmlspecialchars($_POST['city'] ?? '') ?>"
                    >
                </div>

                <!-- State | Occupation -->
                <div class="col-md-6">
                    <label class="form-label-custom">State</label>
                    <input
                        type="text"
                        name="state"
                        class="form-control-custom"
                        value="<?= htmlspecialchars($_POST['state'] ?? '') ?>"
                    >
                </div>
                <div class="col-md-6">
                    <label class="form-label-custom">Occupation</label>
                    <input
                        type="text"
                        name="occupation"
                        class="form-control-custom"
                        value="<?= htmlspecialchars($_POST['occupation'] ?? '') ?>"
                    >
                </div>

                <!-- Referred By | Referred Person Mobile -->
                <div class="col-md-6">
                    <label class="form-label-custom">Referred By</label>
                    <input
                        type="text"
                        name="referred_by"
                        class="form-control-custom"
                        value="<?= htmlspecialchars($_POST['referred_by'] ?? '') ?>"
                    >
                </div>
                <div class="col-md-6">
                    <label class="form-label-custom">Referred Person Mobile</label>
                    <input
                        type="tel"
                        name="referred_person_mobile"
                        class="form-control-custom"
                        value="<?= htmlspecialchars($_POST['referred_person_mobile'] ?? '') ?>"
                    >
                </div>

                <!-- Buttons -->
                <div class="col-12 d-flex justify-content-end gap-2 mt-3">
                    <a href="patients_view.php" class="btn secondary">Cancel</a>
                    <button type="submit" class="btn primary">Add Patient</button>
                </div>
            </form>
        </section>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
