<?php

// /patients/case_update_save.php
session_start();
header('Content-Type: application/json; charset=utf-8');
include "../secure/db.php";

function json_die($arr)
{
    echo json_encode($arr);
    exit;
}

$case_id = isset($_POST['case_id']) ? (int)$_POST['case_id'] : 0;
$patient_id = isset($_POST['patient_id']) ? (int)$_POST['patient_id'] : 0;
$consultant_id = isset($_POST['consultant_id']) ? (int)$_POST['consultant_id'] : 0;

if (!$case_id || !$patient_id) json_die(['success' => false, 'message' => 'Missing case or patient id']);

/* Fetch consultant_name from consultants table (via users) */
$consultant_name = null;
if ($consultant_id > 0) {
    $stmt_cons = mysqli_prepare($conn, "SELECT u.name FROM consultants c INNER JOIN users u ON c.user_id = u.id WHERE c.id = ? LIMIT 1");
    if ($stmt_cons) {
        mysqli_stmt_bind_param($stmt_cons, "i", $consultant_id);
        mysqli_stmt_execute($stmt_cons);
        $res_cons = mysqli_stmt_get_result($stmt_cons);
        if ($row_cons = mysqli_fetch_assoc($res_cons)) {
            $consultant_name = $row_cons['name'];
        }
        mysqli_stmt_close($stmt_cons);
    }
}

/* Fetch patient_name from patients table */
$patient_name = null;
$stmt_pat = mysqli_prepare($conn, "SELECT name FROM patients WHERE id = ? LIMIT 1");
if ($stmt_pat) {
    mysqli_stmt_bind_param($stmt_pat, "i", $patient_id);
    mysqli_stmt_execute($stmt_pat);
    $res_pat = mysqli_stmt_get_result($stmt_pat);
    if ($row_pat = mysqli_fetch_assoc($res_pat)) {
        $patient_name = $row_pat['name'];
    }
    mysqli_stmt_close($stmt_pat);
}

/* read inputs */
$case_update_id  = isset($_POST['case_update_id']) ? (int)$_POST['case_update_id'] : 0;
$follow_up_no    = isset($_POST['follow_up_no']) && $_POST['follow_up_no'] !== '' ? (int)$_POST['follow_up_no'] : null;
$record_date     = !empty($_POST['record_date']) ? $_POST['record_date'] : null;

$energy_status   = isset($_POST['energy_status']) && $_POST['energy_status'] !== '' ? $_POST['energy_status'] : null;
$energy_notes    = trim($_POST['energy_notes'] ?? '') ?: null;

$sleep_status    = isset($_POST['sleep_status']) && $_POST['sleep_status'] !== '' ? $_POST['sleep_status'] : null;
$sleep_notes     = trim($_POST['sleep_notes'] ?? '') ?: null;

$hunger_status   = isset($_POST['hunger_status']) && $_POST['hunger_status'] !== '' ? $_POST['hunger_status'] : null;
$hunger_notes    = trim($_POST['hunger_notes'] ?? '') ?: null;

$digestion_status = isset($_POST['digestion_status']) && $_POST['digestion_status'] !== '' ? $_POST['digestion_status'] : null;
$digestion_notes = trim($_POST['digestion_notes'] ?? '') ?: null;

$stool_status    = isset($_POST['stool_status']) && $_POST['stool_status'] !== '' ? $_POST['stool_status'] : null;
$stool_notes     = trim($_POST['stool_notes'] ?? '') ?: null;

$sweat_status    = isset($_POST['sweat_status']) && $_POST['sweat_status'] !== '' ? $_POST['sweat_status'] : null;
$sweat_notes     = trim($_POST['sweat_notes'] ?? '') ?: null;

$chief_complaint = trim($_POST['chief_complaint'] ?? '') ?: null;
$specific_feedback = trim($_POST['specific_feedback'] ?? '') ?: null;
$suggestions = trim($_POST['suggestions'] ?? '') ?: null;

$conclusion = isset($_POST['conclusion']) && $_POST['conclusion'] !== '' ? $_POST['conclusion'] : null;
$next_followup_date = !empty($_POST['next_followup_date']) ? $_POST['next_followup_date'] : null;

/* prepared statements for UPDATE or INSERT */
if ($case_update_id > 0) {
    /* UPDATE existing case_update */
    $sql = "UPDATE case_update SET
        patient_id = ?, patient_name = ?, case_id = ?, consultant_id = ?, consultant_name = ?, follow_up_no = ?, record_date = ?,
        energy_status = ?, energy_notes = ?,
        sleep_status = ?, sleep_notes = ?,
        hunger_status = ?, hunger_notes = ?,
        digestion_status = ?, digestion_notes = ?,
        stool_status = ?, stool_notes = ?,
                sweat_status = ?, sweat_notes = ?,
                chief_complaint = ?, specific_feedback = ?, suggestions = ?,
        conclusion = ?, next_followup_date = ?, updated_at = NOW()
      WHERE id = ?";

    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        json_die(['success' => false, 'message' => 'Prepare failed: ' . mysqli_error($conn)]);
    }

    $values = [
        $patient_id,
        $patient_name,
        $case_id,
        $consultant_id,
        $consultant_name,
        $follow_up_no,
        $record_date,
        $energy_status,
        $energy_notes,
        $sleep_status,
        $sleep_notes,
        $hunger_status,
        $hunger_notes,
        $digestion_status,
        $digestion_notes,
        $stool_status,
        $stool_notes,
        $sweat_status,
        $sweat_notes,
        $chief_complaint,
        $specific_feedback,
        $suggestions,
        $conclusion,
        $next_followup_date,
        $case_update_id
    ];

    // types: i (patient_id) + s (patient_name) + i (case_id) + i (consultant_id) + s (consultant_name) + i (follow_up_no)
    //        + s (record_date) + 18 strings (energy..next_followup_date) + i (case_update_id)
    $types = 'isiisi' . str_repeat('s', 18) . 'i';

    $bind = [$types];
    foreach ($values as $k => $v) {
        $bind[] = &$values[$k];
    }
    call_user_func_array([$stmt, 'bind_param'], $bind);

    mysqli_stmt_execute($stmt);
    if (mysqli_stmt_errno($stmt)) {
        $err = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        json_die(['success' => false, 'message' => "DB update error: $err"]);
    }
    mysqli_stmt_close($stmt);
    $saved_id = $case_update_id;
    $message = "Follow-up updated successfully.";
} else {
    /* INSERT new case_update */
    $sql = "INSERT INTO case_update (
        patient_id, patient_name, case_id, consultant_id, consultant_name, follow_up_no, record_date,
        energy_status, energy_notes,
        sleep_status, sleep_notes,
        hunger_status, hunger_notes,
        digestion_status, digestion_notes,
        stool_status, stool_notes,
        sweat_status, sweat_notes,
        chief_complaint, specific_feedback, suggestions,
        conclusion, next_followup_date, created_at, updated_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";

    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        json_die(['success' => false, 'message' => 'Prepare failed: ' . mysqli_error($conn)]);
    }

    $values = [
        $patient_id,
        $patient_name,
        $case_id,
        $consultant_id,
        $consultant_name,
        $follow_up_no,
        $record_date,
        $energy_status,
        $energy_notes,
        $sleep_status,
        $sleep_notes,
        $hunger_status,
        $hunger_notes,
        $digestion_status,
        $digestion_notes,
        $stool_status,
        $stool_notes,
        $sweat_status,
        $sweat_notes,
        $chief_complaint,
        $specific_feedback,
        $suggestions,
        $conclusion,
        $next_followup_date
    ];

    // types: i (patient_id) + s (patient_name) + i (case_id) + i (consultant_id) + s (consultant_name) + i (follow_up_no)
    //        + s (record_date) + 18 strings (energy..next_followup_date)
    $types = 'isiisi' . str_repeat('s', 18);

    $bind = [$types];
    foreach ($values as $k => $v) {
        $bind[] = &$values[$k];
    }
    call_user_func_array([$stmt, 'bind_param'], $bind);

    mysqli_stmt_execute($stmt);
    if (mysqli_stmt_errno($stmt)) {
        $err = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        json_die(['success' => false, 'message' => "DB insert error: $err"]);
    }
    $saved_id = mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);
    $message = "Follow-up added successfully.";
}

/* update cases.next_followup_date if provided */
if ($next_followup_date) {
    $u = mysqli_prepare($conn, "UPDATE cases SET next_followup_date=? WHERE id=?");
    if ($u) {
        mysqli_stmt_bind_param($u, "si", $next_followup_date, $case_id);
        mysqli_stmt_execute($u);
        mysqli_stmt_close($u);
    }
}

/* ---------- FILE UPLOAD (single attachment) ---------- */
$uploadDir = __DIR__ . "/uploads/followups/" . $patient_id;
if (!is_dir($uploadDir)) {
    @mkdir($uploadDir, 0777, true);
}

function save_followup_file($field, $file_type, $patient_id, $case_id, $case_update_id, $conn, $uploadDir)
{
    if (!isset($_FILES[$field]) || $_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
        return false;
    }

    $file = $_FILES[$field];
    $name = $file['name'];
    $tmp  = $file['tmp_name'];
    $size = (int)$file['size'];

    if ($size === 0) {
        return false;
    }

    // Validate file extension
    $allowed_ext = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'];
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed_ext)) {
        return false;
    }

    // Create safe filename
    $safe = uniqid("fu_", true) . "." . $ext;
    $dest = $uploadDir . "/" . $safe;

    // Move uploaded file
    if (!move_uploaded_file($tmp, $dest)) {
        return false;
    }

    // Format file size
    $sizeStr   = round($size / 1024, 2) . " KB";
    $file_path = "uploads/followups/" . $patient_id . "/" . $safe;
    $created_by = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;

    // Insert into patient_files
    $sql = "INSERT INTO patient_files (
        patient_id, case_id, case_update_id, file_type,
        file_name, file_path, file_size, created_by,
        created_at, updated_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return false;
    }

    // Bind parameters: i, i, i, s, s, s, s, i
    $stmt->bind_param(
        "iiissssi",
        $patient_id,
        $case_id,
        $case_update_id,
        $file_type,
        $safe,
        $file_path,
        $sizeStr,
        $created_by
    );

    $result = $stmt->execute();
    $stmt->close();

    return $result;
}

/* Process file upload if case_update was saved */
if ($saved_id > 0) {
    save_followup_file("attachment", "followup_report", $patient_id, $case_id, $saved_id, $conn, $uploadDir);
}

json_die(['success' => true, 'message' => $message, 'saved_id' => $saved_id]);
