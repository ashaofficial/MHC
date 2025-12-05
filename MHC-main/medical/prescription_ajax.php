<?php
// prescription_ajax.php
// AJAX endpoint for prescriptions: add | list | get | update | delete
// Safe with unknown consultant name column. Uses prepared statements.

// --- START PATCH: ensure fatal errors return JSON and start session ---
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Buffer output to prevent accidental HTML (PHP warnings) being sent to client
ob_start();

// Don't display errors to client, log them
ini_set('display_errors', '0');
ini_set('log_errors', '1');
// Ensure path exists or change to a writable path on your system
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');
error_reporting(E_ALL);

// If a fatal error happens, return JSON (so front-end JSON.parse won't fail)
register_shutdown_function(function () {
    $err = error_get_last();
    if ($err) {
        // Clean any buffered output (strip HTML stacktrace)
        if (ob_get_length()) {
            @ob_clean();
        }
        // Send JSON error response
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
            header('X-Content-Type-Options: nosniff');
        }
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Server error',
            'error'   => $err['message'] // remove in production to avoid leaking details
        ]);
        flush();
    }
});

// Convert non-fatal PHP errors to exceptions so they are caught by shutdown handler
set_error_handler(function ($severity, $message, $file, $line) {
    // Respect @ operator
    if (!(error_reporting() & $severity)) {
        return;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});
// --- END PATCH ---

mysqli_report(MYSQLI_REPORT_OFF);
include "../secure/db.php";

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

function h($v)
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function detect_consultant_name_column($conn)
{
    $prefer = ['name', 'consultant_name', 'full_name', 'first_name', 'firstname', 'display_name', 'title'];
    $cols = [];
    $sql = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'consultants'";
    $res = mysqli_query($conn, $sql);
    if ($res) {
        while ($r = mysqli_fetch_assoc($res)) $cols[] = $r['COLUMN_NAME'];
        mysqli_free_result($res);
    }
    foreach ($prefer as $c) {
        if (in_array($c, $cols)) return $c;
    }
    return null;
}

function get_consultant_for_case($conn, $case_id)
{
    $out = ['consultant_id' => null, 'consultant_name' => null];
    if (!$case_id) return $out;

    $name_col = detect_consultant_name_column($conn);
    $select = "con.id AS consultant_id";
    if ($name_col) {
        $select .= ", con.`$name_col` AS consultant_name";
    } else {
        $select .= ", NULL AS consultant_name";
    }

    $sql = "SELECT $select FROM cases c LEFT JOIN consultants con ON con.id = c.consultant_id WHERE c.id = ? LIMIT 1";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) return $out;
    mysqli_stmt_bind_param($stmt, "i", $case_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    if ($res && $row = mysqli_fetch_assoc($res)) {
        $out['consultant_id'] = $row['consultant_id'] ?? null;
        $out['consultant_name'] = $row['consultant_name'] ?? null;
    }
    mysqli_stmt_close($stmt);
    return $out;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

/* --------- ADD MEDICINE --------- */
if ($action === 'add') {
    $patient_id = isset($_POST['patient_id']) ? (int)$_POST['patient_id'] : 0;
    $case_id = isset($_POST['case_id']) ? (int)$_POST['case_id'] : null;
    $category = $_POST['category'] ?? 'other';
    $medicine_name = trim($_POST['medicine_name'] ?? '');
    $potency = trim($_POST['potency'] ?? '');
    $dosage = trim($_POST['dosage'] ?? '');
    $frequency = trim($_POST['frequency'] ?? '');
    $duration = trim($_POST['duration'] ?? '');
    $instructions = trim($_POST['instructions'] ?? '');
    $prescribed_by = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;

    if ($patient_id <= 0 || $medicine_name === '') {
        http_response_code(400);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        exit;
    }

    // Get consultant info from case
    $consult = get_consultant_for_case($conn, $case_id);
    $consultant_id = isset($consult['consultant_id']) ? (int)$consult['consultant_id'] : null;
    $consultant_name = $consult['consultant_name'] ?? null;

    // Ensure medicine exists
    $medicine_id = null;
    $med_stmt = mysqli_prepare($conn, "SELECT id FROM medicine WHERE medicine_name = ?");
    if ($med_stmt) {
        mysqli_stmt_bind_param($med_stmt, "s", $medicine_name);
        mysqli_stmt_execute($med_stmt);
        $med_res = mysqli_stmt_get_result($med_stmt);
        if ($med_res && $med_row = mysqli_fetch_assoc($med_res)) {
            $medicine_id = (int)$med_row['id'];
        }
        mysqli_stmt_close($med_stmt);
    }

    if (!$medicine_id) {
        $insert_med = "INSERT INTO medicine (medicine_name, medicine_category) VALUES (?, ?)";
        $med_insert_stmt = mysqli_prepare($conn, $insert_med);
        if ($med_insert_stmt) {
            mysqli_stmt_bind_param($med_insert_stmt, "ss", $medicine_name, $category);
            if (mysqli_stmt_execute($med_insert_stmt)) {
                $medicine_id = mysqli_insert_id($conn);
            }
            mysqli_stmt_close($med_insert_stmt);
        }
    }

    // Insert prescription - bind parameters dynamically to avoid mismatched type errors
    $sql = "INSERT INTO prescriptions (
        patient_id, case_id, consultant_id, consultant_name, 
        medicine_id, medicine_name, medicine_category, 
        dosage, potency, duration, frequency, instructions, prescribed_by
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'Database prepare failed: ' . mysqli_error($conn)]);
        exit;
    }

    // Prepare values array in same order as placeholders
    $values = [
        $patient_id,
        $case_id !== null ? $case_id : null,
        $consultant_id !== null ? $consultant_id : null,
        $consultant_name !== null ? $consultant_name : null,
        $medicine_id !== null ? $medicine_id : null,
        $medicine_name,
        $category,
        $dosage,
        $potency,
        $duration,
        $frequency,
        $instructions,
        $prescribed_by !== null ? $prescribed_by : null
    ];

    // Build types string dynamically: integers => 'i', everything else => 's'
    $types = '';
    foreach ($values as $v) {
        $types .= (is_int($v) ? 'i' : 's');
    }

    // Bind params via call_user_func_array (requires references)
    $bind_params = [];
    $bind_params[] = &$types;
    for ($i = 0; $i < count($values); $i++) {
        $bind_params[] = &$values[$i];
    }
    call_user_func_array([$stmt, 'bind_param'], $bind_params);

    if (mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => true, 'message' => 'Medicine added successfully']);
    } else {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'Failed to add medicine: ' . mysqli_error($conn)]);
    }
    exit;
}

/* --------- LIST PRESCRIPTIONS --------- */
if ($action === 'list') {
    $patient_id = isset($_POST['patient_id']) ? (int)$_POST['patient_id'] : 0;
    $case_id = isset($_POST['case_id']) ? (int)$_POST['case_id'] : null;

    header('Content-Type: text/html; charset=utf-8'); // return HTML table rows

    if (!$patient_id) {
        echo "<tr><td colspan='8' class='text-center text-muted'>No patient ID</td></tr>";
        exit;
    }

    if ($case_id > 0) {
        $sql = "SELECT * FROM prescriptions WHERE patient_id = ? AND case_id = ? ORDER BY id DESC";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "ii", $patient_id, $case_id);
    } else {
        $sql = "SELECT * FROM prescriptions WHERE patient_id = ? ORDER BY created_at DESC";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "i", $patient_id);
    }

    if (!$stmt) {
        echo "<tr><td colspan='8' class='text-center text-danger'>Database error</td></tr>";
        exit;
    }

    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);

    $sn = 1;
    $out = "";

    if ($res && mysqli_num_rows($res) > 0) {
        while ($row = mysqli_fetch_assoc($res)) {
            $out .= "<tr>";
            $out .= "<td>" . $sn . "</td>";
            $out .= "<td><span class='badge bg-secondary'>" . h($row['medicine_category']) . "</span></td>";
            $out .= "<td>" . h($row['medicine_name']) . "</td>";
            $out .= "<td>" . h($row['potency'] ?: '--') . "</td>";
            $out .= "<td>" . h($row['frequency'] ?: '--') . "</td>";
            $out .= "<td>" . h($row['duration'] ?: '--') . "</td>";
            $out .= "<td><button class='btn btn-sm btn-warning btn-edit' data-id='" . (int)$row['id'] . "' title='Edit'><i class='fas fa-edit'></i></button></td>";
            $out .= "<td><button class='btn btn-sm btn-danger btn-delete' data-id='" . (int)$row['id'] . "' title='Delete'><i class='fas fa-trash'></i></button></td>";
            $out .= "</tr>";
            $sn++;
        }
    } else {
        $out = "<tr><td colspan='8' class='text-center text-muted'>No prescriptions</td></tr>";
    }

    mysqli_stmt_close($stmt);
    echo $out;
    exit;
}

/* --------- GET SINGLE PRESCRIPTION --------- */
if ($action === 'get') {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    if (!$id) {
        echo json_encode([]);
        exit;
    }

    $stmt = mysqli_prepare($conn, "SELECT * FROM prescriptions WHERE id = ? LIMIT 1");
    if (!$stmt) {
        echo json_encode([]);
        exit;
    }
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);

    echo json_encode($row ?: []);
    exit;
}

/* --------- UPDATE PRESCRIPTION --------- */
if ($action === 'update') {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    if (!$id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing id']);
        exit;
    }

    $medicine_name = trim($_POST['medicine_name'] ?? '');
    $potency = trim($_POST['potency'] ?? '');
    $dosage = trim($_POST['dosage'] ?? '');
    $frequency = trim($_POST['frequency'] ?? '');
    $duration = trim($_POST['duration'] ?? '');
    $instructions = trim($_POST['instructions'] ?? '');
    $category = $_POST['medicine_category'] ?? 'other';

    $sql = "UPDATE prescriptions SET 
        medicine_name=?, potency=?, dosage=?, frequency=?, 
        duration=?, instructions=?, medicine_category=?, updated_at=NOW() 
        WHERE id = ?";

    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'DB prepare failed']);
        exit;
    }

    mysqli_stmt_bind_param($stmt, "sssssssi", $medicine_name, $potency, $dosage, $frequency, $duration, $instructions, $category, $id);

    if (mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        echo json_encode(['success' => true, 'message' => 'Updated successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Update failed']);
    }
    exit;
}

/* --------- DELETE PRESCRIPTION --------- */
if ($action === 'delete') {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    if (!$id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing id']);
        exit;
    }

    $stmt = mysqli_prepare($conn, "DELETE FROM prescriptions WHERE id = ?");
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'DB prepare failed']);
        exit;
    }

    mysqli_stmt_bind_param($stmt, "i", $id);
    if (mysqli_stmt_execute($stmt)) {
        mysqli_stmt_close($stmt);
        echo json_encode(['success' => true, 'message' => 'Deleted successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Delete failed']);
    }
    exit;
}

/* --------- LIST HISTORY (HTML) --------- */
if ($action === 'history') {
    $patient_id = isset($_POST['patient_id']) ? (int)$_POST['patient_id'] : 0;
    header('Content-Type: text/html; charset=utf-8');
    if (!$patient_id) {
        echo "<div class='history-block'><strong>Constitutional:</strong><ul class='history-bullet'><li class='text-muted'>No history</li></ul><strong>Acute:</strong><ul class='history-bullet'><li class='text-muted'>No history</li></ul><strong>Supplementary:</strong><ul class='history-bullet'><li class='text-muted'>No history</li></ul></div>";
        exit;
    }

    $sql = "SELECT medicine_name, medicine_category, created_at FROM prescriptions WHERE patient_id = ? ORDER BY created_at DESC LIMIT 200";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        echo "<div class='history-block'><p class='text-muted'>Unable to load history</p></div>";
        exit;
    }
    mysqli_stmt_bind_param($stmt, "i", $patient_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);

    $lists = [
        'constitutional' => [],
        'acute' => [],
        'supplementary' => []
    ];

    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            $cat = $row['medicine_category'] ?: 'other';
            $date = isset($row['created_at']) ? date('d M Y', strtotime($row['created_at'])) : '';
            $item = "<li>" . htmlspecialchars($row['medicine_name'], ENT_QUOTES, 'UTF-8') . ($date ? " <span class='text-muted small'>($date)</span>" : "") . "</li>";
            if (isset($lists[$cat])) {
                $lists[$cat][] = $item;
            }
        }
    }
    mysqli_stmt_close($stmt);

    // Build HTML
    $out = "<div class='history-block'>";
    foreach (['constitutional', 'acute', 'supplementary'] as $c) {
        $out .= "<strong>" . ucfirst($c) . ":</strong><ul class='history-bullet'>";
        if (!empty($lists[$c])) {
            $out .= implode("", $lists[$c]);
        } else {
            $out .= "<li class='text-muted'>—</li>";
        }
        $out .= "</ul>";
    }
    $out .= "</div>";

    echo $out;
    exit;
}

http_response_code(400);
echo json_encode(['success' => false, 'message' => 'Unknown action']);
exit;
