<?php
session_start();
header('Content-Type: application/json');
include "../secure/db.php";

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

// Collect recase taking data
$case_id = (int)($_POST['case_id'] ?? 0);
$patient_id = (int)($_POST['patient_id'] ?? 0);
$consultant_id = (int)($_POST['consultant_id'] ?? 0);
$patient_name = trim($_POST['patient_name'] ?? '');
$consultant_name = trim($_POST['consultant_name'] ?? '');
$reason = trim($_POST['reason'] ?? '');
$medicine_name = trim($_POST['medicine_name'] ?? '');
$medicine_date = (!empty($_POST['medicine_date'])) ? $_POST['medicine_date'] : null;

// Validate required fields
if ($case_id <= 0 || $patient_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid case or patient ID']);
    exit;
}

// Insert into recasetaking table
$stmt = $conn->prepare("INSERT INTO recasetaking (case_id, patient_id, patient_name, consultant_id, consultant_name, reason, medicine_name, medicine_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Prepare error: ' . $conn->error]);
    exit;
}

$stmt->bind_param("iisissss", $case_id, $patient_id, $patient_name, $consultant_id, $consultant_name, $reason, $medicine_name, $medicine_date);

if (!$stmt->execute()) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $stmt->error]);
    $stmt->close();
    exit;
}

$stmt->close();

echo json_encode(['success' => true, 'message' => 'Re-case taking saved successfully']);
exit;

// Handle file upload (optional)
if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
    $file = $_FILES['file'];
    $originalName = basename($file['name']);
    $tmp = $file['tmp_name'];
    $size = (int)$file['size'];

    // Validate file size (max 5MB)
    if ($size <= 0 || $size > 5 * 1024 * 1024) {
        echo json_encode(['success' => false, 'message' => 'File size invalid (max 5MB)']);
        exit;
    }

    // Allowed extensions
    $allowed_ext = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'gif'];
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed_ext, true)) {
        echo json_encode(['success' => false, 'message' => 'File type not allowed']);
        exit;
    }

    // Create upload directory
    $uploadDir = __DIR__ . "/../medical/uploads/medical/" . $patient_id;
    if (!is_dir($uploadDir)) {
        if (!@mkdir($uploadDir, 0777, true)) {
            echo json_encode(['success' => false, 'message' => 'Cannot create upload directory']);
            exit;
        }
    }

    // Generate safe filename
    $safe = uniqid("recase_", true) . "." . $ext;
    $dest = rtrim($uploadDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $safe;

    // Move file
    if (!move_uploaded_file($tmp, $dest)) {
        echo json_encode(['success' => false, 'message' => 'Failed to move uploaded file']);
        exit;
    }

    // Save to patient_files table
    $sizeStr = round($size / 1024, 2) . " KB";
    $filePath = "medical/uploads/medical/" . $patient_id . "/" . $safe;
    $createdBy = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
    $fileType = 'case_taking'; // or 'recase_taking' if you want to distinguish

    $sql = "INSERT INTO patient_files (patient_id, case_id, file_type, file_name, file_path, file_size, created_by, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
    $stmt2 = $conn->prepare($sql);
    if (!$stmt2) {
        @unlink($dest);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
        exit;
    }
    $caseIdForDb = ($case_id > 0) ? $case_id : null;
    $stmt2->bind_param(
        "iissssi",
        $patient_id,
        $caseIdForDb,
        $fileType,
        $originalName,
        $filePath,
        $sizeStr,
        $createdBy
    );
    if (!$stmt2->execute()) {
        @unlink($dest);
        echo json_encode(['success' => false, 'message' => 'File DB save failed: ' . $stmt2->error]);
        $stmt2->close();
        exit;
    }
    $stmt2->close();
}

echo json_encode(['success' => true, 'message' => 'Re-case taking saved successfully']);
exit;
