<?php
// patient_search.php: returns JSON array of patient names matching a query (min 2 chars)
require_once __DIR__ . '/../secure/db.php';
header('Content-Type: application/json');
$q = trim($_GET['q'] ?? '');
if (mb_strlen($q) < 2) {
    echo json_encode(['status' => 'success', 'items' => []]);
    exit;
}
// Return id, name, age and gender so callers can populate form fields
$stmt = $conn->prepare("SELECT id, name, COALESCE(age, '') AS age, COALESCE(gender, '') AS gender FROM patients WHERE name LIKE ? ORDER BY name LIMIT 10");
$like = "%$q%";
$stmt->bind_param('s', $like);
$stmt->execute();
$res = $stmt->get_result();
$out = [];
while ($row = $res->fetch_assoc()) {
    $out[] = [ 'id' => $row['id'], 'name' => $row['name'], 'age' => $row['age'], 'gender' => $row['gender'] ];
}
echo json_encode(['status' => 'success', 'items' => $out]);