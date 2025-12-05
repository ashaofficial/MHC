<?php
session_start();
header('Content-Type: application/json');
include "../secure/db.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$case_id = (int)($_POST['case_id'] ?? 0);
$patient_id = (int)($_POST['patient_id'] ?? 0);
$consultant_id = (int)($_POST['consultant_id'] ?? 0);
$reason = trim($_POST['reason'] ?? '');
$conclusion = trim($_POST['conclusion'] ?? '');
$medicine_name = trim($_POST['medicine_name'] ?? '');
$medicine_date = (!empty($_POST['medicine_date'])) ? $_POST['medicine_date'] : null;

// Fetch names if needed (optional, or pass from frontend)
$patient_name = trim($_POST['patient_name'] ?? '');
$consultant_name = trim($_POST['consultant_name'] ?? '');

// Validate required fields
if ($case_id <= 0 || $patient_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid case or patient ID']);
    exit;
}

$stmt = $conn->prepare("INSERT INTO reanalysis (case_id, patient_id, patient_name, consultant_id, consultant_name, reason, conclusion, medicine_name, medicine_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");

if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Prepare error: ' . $conn->error]);
    exit;
}

$stmt->bind_param("iisisssss", $case_id, $patient_id, $patient_name, $consultant_id, $consultant_name, $reason, $conclusion, $medicine_name, $medicine_date);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Re-analysis saved successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $stmt->error]);
}
$stmt->close();
exit;
