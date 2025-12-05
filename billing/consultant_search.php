<?php
// consultant_search.php: returns JSON array of consultant names (users.name) matching a query (min 2 chars)
require_once __DIR__ . '/../secure/db.php';
header('Content-Type: application/json');
$q = trim($_GET['q'] ?? '');
if (mb_strlen($q) < 2) {
    echo json_encode([]);
    exit;
}
// Search users.name for active consultants, return consultants.id
$stmt = $conn->prepare("SELECT c.id, u.name FROM users u INNER JOIN consultants c ON c.user_id = u.id WHERE u.name LIKE ? ORDER BY u.name LIMIT 10");
$like = "%$q%";
$stmt->bind_param('s', $like);
$stmt->execute();
$res = $stmt->get_result();
$out = [];
while ($row = $res->fetch_assoc()) {
    $out[] = [ 'id' => $row['id'], 'name' => $row['name'] ];
}
echo json_encode($out);