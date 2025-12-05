<?php
require_once __DIR__ . '/../secure/db.php';
header('Content-Type: application/json; charset=utf-8');

$category = isset($_GET['category']) ? strtolower(trim($_GET['category'])) : 'acute';
$rows = [];

if ($category === 'constitutional') {
    $sql = "SELECT id, Constitutional_medicine_name AS name, Constitutional_medicine_date AS date FROM medicine WHERE Constitutional_medicine_name != '' AND Constitutional_medicine_name IS NOT NULL ORDER BY Constitutional_medicine_name LIMIT 300";
} elseif ($category === 'supplementary') {
    $sql = "SELECT id, Supplementary_medicine_name AS name, Supplementary_medicine_date AS date FROM medicine WHERE Supplementary_medicine_name != '' AND Supplementary_medicine_name IS NOT NULL ORDER BY Supplementary_medicine_name LIMIT 300";
} elseif ($category === 'other') {
    $sql = "(SELECT id, Constitutional_medicine_name AS name, Constitutional_medicine_date AS date FROM medicine WHERE Constitutional_medicine_name != '' AND Constitutional_medicine_name IS NOT NULL)
            UNION
            (SELECT id, Acute_medicine_name AS name, Acute_medicine_date AS date FROM medicine WHERE Acute_medicine_name != '' AND Acute_medicine_name IS NOT NULL)
            UNION
            (SELECT id, Supplementary_medicine_name AS name, Supplementary_medicine_date AS date FROM medicine WHERE Supplementary_medicine_name != '' AND Supplementary_medicine_name IS NOT NULL)
            LIMIT 500";
} else {
    $sql = "SELECT id, Acute_medicine_name AS name, Acute_medicine_date AS date FROM medicine WHERE Acute_medicine_name != '' AND Acute_medicine_name IS NOT NULL ORDER BY Acute_medicine_name LIMIT 300";
}

$stmt = $conn->prepare($sql);
if ($stmt) {
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) {
        $rows[] = ['id' => $r['id'], 'name' => $r['name'], 'date' => $r['date']];
    }
    $stmt->close();
}

echo json_encode($rows);
