<?php

// /patients/case_update.php
session_start();
include "../secure/db.php";

/* helpers */
function h($v){ return htmlspecialchars((string)$v ?? '', ENT_QUOTES, 'UTF-8'); }
function toDateInput($d){ return $d ? date('Y-m-d', strtotime($d)) : ''; }

/* require a case_id */
$case_id = isset($_GET['case_id']) ? (int)$_GET['case_id'] : 0;
if ($case_id <= 0) die("Missing case_id");

/* load case */
$cs = mysqli_prepare($conn, "SELECT * FROM cases WHERE id=?");
mysqli_stmt_bind_param($cs, "i", $case_id);
mysqli_stmt_execute($cs);
$cr = mysqli_stmt_get_result($cs);
$case = mysqli_fetch_assoc($cr);
mysqli_stmt_close($cs);
if (!$case) die("Case not found");

/* load patient */
$pid = (int)$case['patient_id'];
$ps = mysqli_prepare($conn, "SELECT * FROM patients WHERE id=?");
mysqli_stmt_bind_param($ps, "i", $pid);
mysqli_stmt_execute($ps);
$pr = mysqli_stmt_get_result($ps);
$patient = mysqli_fetch_assoc($pr);
mysqli_stmt_close($ps);

/* load consultant name */
$consultant_name = '';
$consultant_id = (int)$case['consultant_id'];
if ($consultant_id > 0) {
    $cons = mysqli_prepare($conn, "SELECT u.name FROM consultants c INNER JOIN users u ON c.user_id = u.id WHERE c.id = ?");
    mysqli_stmt_bind_param($cons, "i", $consultant_id);
    mysqli_stmt_execute($cons);
    $conres = mysqli_stmt_get_result($cons);
    if ($conrow = mysqli_fetch_assoc($conres)) {
        $consultant_name = $conrow['name'];
    }
    mysqli_stmt_close($cons);
}

/* load followups for initial render */
$fu = mysqli_prepare($conn, "SELECT * FROM case_update WHERE case_id=? ORDER BY follow_up_no ASC, id DESC");
mysqli_stmt_bind_param($fu, "i", $case_id);
mysqli_stmt_execute($fu);
$fr = mysqli_stmt_get_result($fu);
$followups = [];
while ($r = mysqli_fetch_assoc($fr)) $followups[] = $r;
mysqli_stmt_close($fu);

/* compute next followup number */
$next_follow_no = 1;
if (!empty($followups)) {
    $last = end($followups);
    $next_follow_no = ((int)$last['follow_up_no']) + 1;
}

/* enum options */
$statusOptions = [
    '' => '-- select --',
    'improved' => 'Improved',
    'no_improvement' => 'No improvement',
    'good_before' => 'Good already before'
];
$conclusionOptions = [
    '' => '-- select --',
    'no_improvement' => 'No improvement',
    'improvement_after_medicine' => 'Improvement after medicine',
    'condition_stable' => 'Condition stable'
];
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Case Update — Case #<?= h($case['id']) ?></title>
  <link href="https://cdn.jsdelivr.net/npm/@sweetalert2/theme-material-ui/material-ui.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <link rel="stylesheet" href="../css/case_update.css">
</head>
<body>
<div class="layout">

  <!-- LEFT PANEL: case list -->
  <aside class="left-panel">
    <h3>Cases</h3>
    <ul class="case-list">
      <?php
        $cs2 = mysqli_prepare($conn, "SELECT id, summary FROM cases WHERE patient_id=? ORDER BY visit_date DESC LIMIT 20");
        mysqli_stmt_bind_param($cs2, "i", $pid);
        mysqli_stmt_execute($cs2);
        $r2 = mysqli_stmt_get_result($cs2);
        while ($c = mysqli_fetch_assoc($r2)): ?>
          <li class="case-list-item <?= ($c['id'] == $case_id) ? 'active' : '' ?>">
            <a href="case_update.php?case_id=<?= (int)$c['id'] ?>">Case <?= (int)$c['id'] ?></a>
            <div class="case-summary"><?= h(substr($c['summary'], 0, 50)) ?></div>
          </li>
      <?php endwhile; mysqli_stmt_close($cs2); ?>
    </ul>
  </aside>

  <!-- RIGHT PANEL: main content -->
  <main class="right-panel">
    
    <!-- HEADER CARD: patient & consultant on left, summary+status aligned right -->
    <div class="header-card">
      <div class="header-left">
        <h4>Case #<?= (int)$case['id'] ?></h4>
        <p class="small">Patient: <strong><?= h($patient['name'] ?? 'N/A') ?></strong></p>
        <p class="small">Consultant: <strong><?= h($consultant_name) ?></strong></p>
        <p class="small">Visit Date: <?= h($case['visit_date'] ?? '') ?></p>
      </div>
      <div class="header-right">
        <div class="header-field">
          <label>Summary</label>
          <input type="text" class="input-readonly" readonly value="<?= h($case['summary'] ?? '') ?>">
        </div>
        <div class="header-field">
          <label>Status</label>
          <div class="status-badge"><?= h($case['status'] ?? 'Pending') ?></div>
        </div>
      </div>
    </div>

    <!-- FORM SECTION -->
    <section class="followups-section">
      
      <!-- NEW/EDIT FORM -->
      <form id="cu-form" class="followup-card expanded" enctype="multipart/form-data" onsubmit="return onSubmitForm(event)">
        <input type="hidden" name="case_id" value="<?= (int)$case_id ?>">
        <input type="hidden" name="patient_id" value="<?= (int)$pid ?>">
        <input type="hidden" name="consultant_id" value="<?= (int)$consultant_id ?>">
        <input id="case_update_id" type="hidden" name="case_update_id" value="0">
        <input id="follow_up_no" type="hidden" name="follow_up_no" value="<?= (int)$next_follow_no ?>">

        <div class="form-header">
          <div class="followup-badge">Follow-up: <span id="followup-display"><?= (int)$next_follow_no ?></span></div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label>Record Date *</label>
            <input id="record_date" type="date" name="record_date" value="<?= date('Y-m-d') ?>" required>
          </div>
          <div class="form-group">
            <label>&nbsp;</label>
            <!-- reserved / spacer -->
            <div style="height:1px"></div>
          </div>
          <div class="form-group">
            <label>Next Follow-up Date</label>
            <input id="next_followup_date" type="date" name="next_followup_date">
          </div>
        </div>

        <!-- SYMPTOMS GRID -->
        <div class="symptoms-section">
          <h4>Symptom Review</h4>
          <div class="symptoms-grid">
            <?php foreach (['energy', 'sleep', 'hunger', 'digestion', 'stool', 'sweat'] as $s): ?>
              <div class="symptom-card">
                <label class="symptom-label"><?= ucfirst($s) ?></label>
                <select id="<?= $s ?>_status" name="<?= $s ?>_status" class="symptom-select">
                  <?php foreach ($statusOptions as $k => $v): ?>
                    <option value="<?= h($k) ?>"><?= h($v) ?></option>
                  <?php endforeach; ?>
                </select>
                <textarea id="<?= $s ?>_notes" name="<?= $s ?>_notes" placeholder="Notes..." class="symptom-textarea"></textarea>
              </div>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label>Specific Feedback</label>
            <textarea id="specific_feedback" name="specific_feedback" placeholder="Patient feedback..."></textarea>
          </div>
          <div class="form-group">
            <label>Suggestions</label>
            <textarea id="suggestions" name="suggestions" placeholder="Medical suggestions..."></textarea>
          </div>
        </div>

        <!-- CHIEF COMPLAINT + CONCLUSION + SINGLE ATTACHMENT (moved below feedback/suggestions) -->
        <div class="form-row full-width">
          <div class="form-group full">
            <label>Chief Complaint</label>
            <textarea id="chief_complaint" name="chief_complaint" placeholder="Enter chief complaint..."></textarea>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label>Conclusion</label>
            <select id="conclusion" name="conclusion">
              <?php foreach ($conclusionOptions as $k => $v): ?>
                <option value="<?= h($k) ?>"><?= h($v) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Attachment</label>
            <input type="file" name="attachment" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" class="file-input">
            <div class="file-hint">Single document (PDF/image/doc)</div>
          </div>
          <div class="form-group">
            <label>&nbsp;</label>
            <div style="height:1px"></div>
          </div>
        </div>

        <div class="form-actions">
          <button type="submit" class="btn btn-save">Save Follow-up</button>
          <button type="button" class="btn btn-continue">Save & Continue to Prescriptions</button>
          <button type="button" class="btn btn-secondary" id="btn-clear">Clear Form</button>
        </div>
      </form>

      <!-- FOLLOWUPS LIST -->
      <div class="followups-list-section">
        <h3>Previous Follow-ups</h3>
        <div id="compact-list" class="followups-list">
          <?php foreach ($followups as $f): ?>
            <div class="followup-list-item" data-id="<?= (int)$f['id'] ?>">
              <div class="list-header">
                <div class="list-badge">FU #<?= (int)$f['follow_up_no'] ?></div>
                <div class="list-date"><?= h($f['record_date'] ?? '') ?></div>
                <div class="list-conclusion"><?= h($f['conclusion'] ?? '') ?></div>
              </div>
              <button type="button" class="btn btn-edit btn-small" data-id="<?= (int)$f['id'] ?>">Edit</button>
            </div>
          <?php endforeach; ?>
          <?php if (empty($followups)): ?>
            <p class="empty-state">No previous follow-ups recorded.</p>
          <?php endif; ?>
        </div>
      </div>

    </section>

  </main>

</div>

<script>
const $ = sel => document.querySelector(sel);
const caseId = <?= (int)$case_id ?>;
const patientId = <?= (int)$pid ?>;

document.addEventListener('DOMContentLoaded', () => {
  // Edit button handler
  document.body.addEventListener('click', e => {
    if (e.target.matches('.btn-edit')) {
      const id = e.target.getAttribute('data-id');
      if (id) loadFollowupIntoForm(parseInt(id));
    }
  });

  // Save & Continue button
  document.querySelector('.btn-continue').addEventListener('click', async () => {
    const result = await ajaxSave();
    if (result && result.success && result.saved_id) {
      window.location.href = `prescriptions.php?case_id=${caseId}&case_update_id=${result.saved_id}`;
    }
  });

  // Clear button
  document.getElementById('btn-clear').addEventListener('click', () => {
    clearFormForNew();
  });
});

/* Load and populate form with existing followup */
async function loadFollowupIntoForm(id) {
  try {
    const resp = await fetch(`case_update_ajax.php?action=load&case_update_id=${id}`);
    const json = await resp.json();
    if (!json.success) {
      Swal.fire({ icon: 'error', title: json.message || 'Failed to load', position: 'center' });
      return;
    }
    fillFormFromData(json.data, false);
    Swal.fire({ icon: 'success', title: 'Follow-up loaded', position: 'center', timer: 1000, showConfirmButton: false });
  } catch (err) {
    Swal.fire({ icon: 'error', title: 'Error loading follow-up', position: 'center' });
  }
}

/* Fill form from data */
function fillFormFromData(data, isNew = false) {
  $('#case_update_id').value = data.id || 0;
  $('#follow_up_no').value = data.follow_up_no || <?= (int)$next_follow_no ?>;
  $('#followup-display').innerText = $('#follow_up_no').value;

  $('#record_date').value = data.record_date || (new Date()).toISOString().slice(0, 10);
  $('#specific_feedback').value = data.specific_feedback || '';
  $('#suggestions').value = data.suggestions || '';
  $('#chief_complaint').value = data.chief_complaint || '';
  $('#conclusion').value = data.conclusion || '';
  $('#next_followup_date').value = data.next_followup_date || '';

  ['energy', 'sleep', 'hunger', 'digestion', 'stool', 'sweat'].forEach(s => {
    const st = document.getElementById(`${s}_status`);
    const nt = document.getElementById(`${s}_notes`);
    if (st) st.value = data[`${s}_status`] || '';
    if (nt) nt.value = data[`${s}_notes`] || '';
  });

  // clear file input (attachments not auto-filled)
  const f = document.querySelector('.file-input');
  if (f) f.value = '';
}

/* Clear form for new entry */
function clearFormForNew() {
  $('#case_update_id').value = 0;
  $('#follow_up_no').value = <?= (int)$next_follow_no ?>;
  $('#followup-display').innerText = <?= (int)$next_follow_no ?>;
  $('#record_date').value = (new Date()).toISOString().slice(0, 10);
  $('#specific_feedback').value = '';
  $('#suggestions').value = '';
  $('#chief_complaint').value = '';
  $('#conclusion').value = '';
  $('#next_followup_date').value = '';
  ['energy', 'sleep', 'hunger', 'digestion', 'stool', 'sweat'].forEach(s => {
    const st = document.getElementById(`${s}_status`);
    const nt = document.getElementById(`${s}_notes`);
    if (st) st.value = '';
    if (nt) nt.value = '';
  });
  // clear single file input
  const file = document.querySelector('.file-input');
  if (file) file.value = '';
  Swal.fire({ icon: 'info', title: 'Form cleared', position: 'center', timer: 800, showConfirmButton: false });
}

/* Form submit */
function onSubmitForm(e) {
  e.preventDefault();
  ajaxSave();
  return false;
}

/* Save via AJAX */
async function ajaxSave() {
  try {
    const form = document.getElementById('cu-form');
    const formData = new FormData(form);

    const resp = await fetch('case_update_save.php', {
      method: 'POST',
      body: formData
    });
    const json = await resp.json();
    if (!json.success) {
      Swal.fire({ icon: 'error', title: json.message || 'Save failed', position: 'center' });
      return json;
    }

    Swal.fire({ icon: 'success', title: json.message, position: 'center', timer: 1200, showConfirmButton: false });
    
    // CLEAR FORM AFTER SAVE
    setTimeout(() => {
      clearFormForNew();
      refreshCompactList();
    }, 800);

    return json;
  } catch (err) {
    Swal.fire({ icon: 'error', title: 'Save error', position: 'center' });
    return { success: false };
  }
}

/* Refresh the followups list */
async function refreshCompactList() {
  try {
    const resp = await fetch(`case_update_ajax.php?action=list&case_id=${caseId}`);
    const html = await resp.text();
    document.getElementById('compact-list').innerHTML = html;
  } catch (err) {
    console.error('List refresh error:', err);
  }
}
</script>
</body>
</html>