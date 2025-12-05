<?php
include_once __DIR__ . '/../auth.php';
include_once __DIR__ . '/../secure/db.php';

if (strtolower($USER['role'] ?? '') !== 'administrator') {
    http_response_code(403);
    die('Access denied');
}

$user_id = (int)$USER['user_id'];

/* ---------------- FETCH CURRENT USER DATA ---------------- */

$st = $conn->prepare("SELECT name, email, mobile FROM users WHERE id = ?");
$st->bind_param("i", $user_id);
$st->execute();
$result = $st->get_result();
$current = $result->fetch_assoc();

/* If user row missing */
if (!$current) {
    die("User not found");
}

/* ---------------- HANDLE FORM SUBMISSION ---------------- */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $mobile = trim($_POST['mobile'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!$name) {
        $error = "Name required";
    } else {
        // Update users table
        $ust = $conn->prepare("
            UPDATE users 
            SET name = ?, email = ?, mobile = ?, updated_at = NOW() 
            WHERE id = ?
        ");
        $ust->bind_param("sssi", $name, $email, $mobile, $user_id);
        $ust->execute();

        // Update password only if given
        if ($password) {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $c = $conn->prepare("
                UPDATE credential 
                SET password_hash = ?, updated_on = NOW() 
                WHERE user_id = ?
            ");
            $c->bind_param("si", $hash, $user_id);
            $c->execute();
        }

        $success = "Profile updated successfully.";

        // refresh current values after save
        $st->execute();
        $result = $st->get_result();
        $current = $result->fetch_assoc();
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>My Profile</title>
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
</head>
<body class="p-4">
<div class="container">
    <h2>My Profile</h2>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>

    <?php if (!empty($success)): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>

    <form method="post">

        <div class="mb-3">
            <label class="form-label">Full Name</label>
            <input name="name" class="form-control"
                   value="<?php echo htmlspecialchars($current['name']); ?>" required>
        </div>

        <div class="mb-3">
            <label class="form-label">Email</label>
            <input name="email" type="email" class="form-control"
                   value="<?php echo htmlspecialchars($current['email']); ?>">
        </div>

        <div class="mb-3">
            <label class="form-label">Mobile</label>
            <input name="mobile" class="form-control"
                   value="<?php echo htmlspecialchars($current['mobile']); ?>">
        </div>

        <div class="mb-3">
            <label class="form-label">New Password  
                <small class="text-muted">(leave blank to keep current)</small>
            </label>
            <input name="password" type="password" class="form-control">
        </div>

        <button class="btn btn-primary">Save</button>
        <a class="btn btn-secondary ms-2" href="administrator.php">Cancel</a>
    </form>
</div>
</body>
</html>
