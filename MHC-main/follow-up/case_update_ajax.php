<?php

// /patients/case_update_ajax.php
session_start();
include "../secure/db.php";

$action = $_GET['action'] ?? '';

function json_die($arr){
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($arr);
    exit;
}

if ($action === 'load') {
    $id = isset($_GET['case_update_id']) ? (int)$_GET['case_update_id'] : 0;
    if (!$id) json_die(['success'=>false,'message'=>'Missing id']);

    $q = mysqli_prepare($conn, "SELECT * FROM case_update WHERE id=? LIMIT 1");
    mysqli_stmt_bind_param($q,'i',$id);
    mysqli_stmt_execute($q);
    $res = mysqli_stmt_get_result($q);
    $row = mysqli_fetch_assoc($res);
    mysqli_stmt_close($q);
    if (!$row) json_die(['success'=>false,'message'=>'Not found']);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success'=>true,'data'=>$row]);
    exit;
}

/* previous: return last followup for a case (to prefill new follow-up) */
if ($action === 'previous') {
    $case_id = isset($_GET['case_id']) ? (int)$_GET['case_id'] : 0;
    if (!$case_id) json_die(['success'=>false,'message'=>'Missing case_id']);
    $q = mysqli_prepare($conn, "SELECT * FROM case_update WHERE case_id = ? ORDER BY follow_up_no DESC, id DESC LIMIT 1");
    mysqli_stmt_bind_param($q,'i',$case_id);
    mysqli_stmt_execute($q);
    $res = mysqli_stmt_get_result($q);
    $row = mysqli_fetch_assoc($res);
    mysqli_stmt_close($q);
    if (!$row) json_die(['success'=>false,'message'=>'No previous followup','data'=>null]);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success'=>true,'data'=>$row]);
    exit;
}

/* list: return compact-list HTML snippet (not JSON) */
if ($action === 'list') {
    $case_id = isset($_GET['case_id']) ? (int)$_GET['case_id'] : 0;
    if (!$case_id) {
        header('Content-Type: text/plain');
        echo 'Missing case_id';
        exit;
    }
    $q = mysqli_prepare($conn, "SELECT * FROM case_update WHERE case_id = ? ORDER BY follow_up_no ASC, id DESC");
    mysqli_stmt_bind_param($q,'i',$case_id);
    mysqli_stmt_execute($q);
    $res = mysqli_stmt_get_result($q);
    $rows = [];
    while ($r = mysqli_fetch_assoc($res)) $rows[] = $r;
    mysqli_stmt_close($q);

    ob_start();
    foreach ($rows as $r): ?>
      <div class="compact-row" data-id="<?= (int)$r['id'] ?>">
        <div class="compact-left">
          <div class="pill">Followup: <?= (int)$r['follow_up_no'] ?></div>
          <div class="compact-meta">Date: <?= htmlspecialchars($r['record_date'] ?? $r['date'] ?? '') ?></div>
          <div class="compact-meta">Conclusion: <?= htmlspecialchars($r['conclusion'] ?? '') ?></div>
        </div>
        <div class="compact-right">
          <button class="btn small btn-edit" data-id="<?= (int)$r['id'] ?>">Edit</button>
        </div>
      </div>
    <?php endforeach;
    $html = ob_get_clean();
    header('Content-Type: text/html; charset=utf-8');
    echo $html;
    exit;
}

/* fallback */
json_die(['success'=>false,'message'=>'Unknown action']);