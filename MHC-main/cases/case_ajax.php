<?php
session_start();
header('Content-Type: application/json');
include "../secure/db.php";

$response = ['success' => false, 'message' => 'Unknown error'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'File upload failed']);
    exit;
}

$file = $_FILES['file'];
$fieldName = isset($_POST['field_name']) ? trim($_POST['field_name']) : '';
$fileType = isset($_POST['file_type']) ? trim($_POST['file_type']) : '';
$caseId = isset($_POST['case_id']) ? (int)$_POST['case_id'] : 0;
$patientId = isset($_POST['patient_id']) ? (int)$_POST['patient_id'] : 0;
$consultantId = isset($_POST['consultant_id']) ? (int)$_POST['consultant_id'] : 0;

if ($patientId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid patient ID']);
    exit;
}

// Validate file
$originalName = basename($file['name']);
$tmp = $file['tmp_name'];
$size = (int)$file['size'];

if ($size <= 0 || $size > 5 * 1024 * 1024) { // 5MB limit
    echo json_encode(['success' => false, 'message' => 'File size invalid (max 5MB)']);
    exit;
}

$allowed_ext = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'gif'];
$ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
if (!in_array($ext, $allowed_ext, true)) {
    echo json_encode(['success' => false, 'message' => 'File type not allowed']);
    exit;
}

// If case not provided, try to create a minimal case if consultant_id is provided
$createdCaseId = $caseId;
if ($createdCaseId <= 0) {
    if ($consultantId > 0) {
        // Check if cases table has consultant_name column
        $colExists = false;
        $r = $conn->query("SHOW COLUMNS FROM cases LIKE 'consultant_name'");
        if ($r && $r->num_rows > 0) {
            $colExists = true;
            $r->close();
        }

        // Try to fetch consultant name (user.name) to store in case row
        $consultantName = null;
        $stmtC = $conn->prepare("
            SELECT u.name
            FROM consultants cons
            JOIN users u ON cons.user_id = u.id
            WHERE cons.id = ? LIMIT 1
        ");
        if ($stmtC) {
            $stmtC->bind_param("i", $consultantId);
            $stmtC->execute();
            $resC = $stmtC->get_result();
            if ($rowC = $resC->fetch_assoc()) {
                $consultantName = $rowC['name'];
            }
            $stmtC->close();
        }

        // Insert minimal case
        if ($colExists) {
            $sqlInsCase = "
                INSERT INTO cases (consultant_id, consultant_name, patient_id, visit_date, chief_complaint, status, created_at, updated_at)
                VALUES (?, ?, ?, NOW(), '', 'open', NOW(), NOW())
            ";
            $stmtInsCase = $conn->prepare($sqlInsCase);
            if ($stmtInsCase) {
                $stmtInsCase->bind_param("iss", $consultantId, $consultantName, $patientId);
                $stmtInsCase->execute();
                $createdCaseId = $stmtInsCase->insert_id;
                $stmtInsCase->close();
            }
        } else {
            $sqlInsCase = "
                INSERT INTO cases (consultant_id, patient_id, visit_date, chief_complaint, status, created_at, updated_at)
                VALUES (?, ?, NOW(), '', 'open', NOW(), NOW())
            ";
            $stmtInsCase = $conn->prepare($sqlInsCase);
            if ($stmtInsCase) {
                $stmtInsCase->bind_param("ii", $consultantId, $patientId);
                $stmtInsCase->execute();
                $createdCaseId = $stmtInsCase->insert_id;
                $stmtInsCase->close();
            }
        }

        // ----------------- NEW: ensure patient_name column (and consultant_name if missing) are set -----------------
        if ($createdCaseId) {
            // fetch patient name
            $patientName = null;
            $stmtPN = $conn->prepare("SELECT name FROM patients WHERE id = ? LIMIT 1");
            if ($stmtPN) {
                $stmtPN->bind_param("i", $patientId);
                $stmtPN->execute();
                $rPN = $stmtPN->get_result();
                if ($rowPN = $rPN->fetch_assoc()) {
                    $patientName = $rowPN['name'];
                }
                $stmtPN->close();
            }

            // if patient_name column exists update it
            $rcolP = $conn->query("SHOW COLUMNS FROM cases LIKE 'patient_name'");
            if ($rcolP && $rcolP->num_rows > 0) {
                $rcolP->close();
                $stmtUpdPN = $conn->prepare("UPDATE cases SET patient_name = ? WHERE id = ?");
                if ($stmtUpdPN) {
                    $stmtUpdPN->bind_param("si", $patientName, $createdCaseId);
                    $stmtUpdPN->execute();
                    $stmtUpdPN->close();
                }
            }

            // ensure consultant_name is set if column exists and consultantName available
            if (!empty($consultantName)) {
                $rcolC = $conn->query("SHOW COLUMNS FROM cases LIKE 'consultant_name'");
                if ($rcolC && $rcolC->num_rows > 0) {
                    $rcolC->close();
                    $stmtUpdCN = $conn->prepare("UPDATE cases SET consultant_name = ? WHERE id = ?");
                    if ($stmtUpdCN) {
                        $stmtUpdCN->bind_param("si", $consultantName, $createdCaseId);
                        $stmtUpdCN->execute();
                        $stmtUpdCN->close();
                    }
                }
            }
        }
    } else {
        // No case and no consultant -> cannot create case automatically
        // We'll allow saving file with NULL case_id (optional) or return error — choose create NULL case reference
        $createdCaseId = null;
    }
}

// Create upload directory
$uploadDir = __DIR__ . "/../medical/uploads/medical/" . $patientId;
if (!is_dir($uploadDir)) {
    if (!@mkdir($uploadDir, 0777, true)) {
        echo json_encode(['success' => false, 'message' => 'Cannot create upload directory']);
        exit;
    }
}

// Generate safe filename
$safe = uniqid("case_", true) . "." . $ext;
$dest = rtrim($uploadDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $safe;

// Move file
if (!move_uploaded_file($tmp, $dest)) {
    echo json_encode(['success' => false, 'message' => 'Failed to move uploaded file']);
    exit;
}

// Save to database
$sizeStr = round($size / 1024, 2) . " KB";
$filePath = "medical/uploads/medical/" . $patientId . "/" . $safe;
$createdBy = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;

// Map file_type: pre_case_taking, case_taking, reports
$dbFileType = $fileType;
if (in_array($fileType, ['pre_case_taking', 'pre_case'], true)) {
    $dbFileType = 'pre_case_taking';
} elseif (in_array($fileType, ['case_taking', 're_case', 're_case_taking'], true)) {
    $dbFileType = 'case_taking';
} elseif (in_array($fileType, ['reports', 'report'], true)) {
    $dbFileType = 'report';
}

// Insert into patient_files table (required table)
if ($patientId > 0) {
    $sql = "
        INSERT INTO patient_files (
            patient_id, case_id, file_type,
            file_name, file_path, file_size, created_by,
            created_at, updated_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
    ";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        @unlink($dest);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
        exit;
    }

    // If case_id is 0 or null, pass NULL to DB
    $caseIdForDb = ($caseId > 0) ? $caseId : null;

    $stmt->bind_param(
        "iisssss",
        $patientId,
        $caseIdForDb,
        $dbFileType,
        $originalName,
        $filePath,
        $sizeStr,
        $createdBy
    );

    if (!$stmt->execute()) {
        error_log("DB execute error: " . $stmt->error);
        @unlink($dest);
        echo json_encode(['success' => false, 'message' => 'Database save failed: ' . $stmt->error]);
        $stmt->close();
        exit;
    }

    $fileId = $stmt->insert_id;
    $stmt->close();

    echo json_encode([
        'success' => true,
        'message' => 'File uploaded successfully',
        'file_name' => $originalName,
        'file_path' => $filePath,
        'file_size' => $sizeStr,
        'file_id' => $fileId,
        'case_id' => $caseId,
        'file_type' => $dbFileType
    ]);
} else {
    @unlink($dest);
    echo json_encode(['success' => false, 'message' => 'Invalid patient ID for file storage']);
}
exit;
