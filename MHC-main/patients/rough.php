<?php
include "../secure/db.php";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Case Sheet</title>

    <!-- simple layout styles, you can move this to case.css -->
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 16px;
            font-family: Arial, Helvetica, sans-serif;
            background:#f5f5f5;
        }
        .case-wrapper {
            max-width: 1100px;
            margin: 0 auto;
            background:#ffffff;
            border:1px solid #ddd;
            border-radius:10px;
            padding:16px 20px 20px;
        }
        .case-title {
            font-size: 20px;
            font-weight: 600;
            text-align:left;
            margin-bottom:10px;
        }
        .case-header {
            display:grid;
            grid-template-columns:1fr 1fr 1fr;
            gap:16px;
            margin-bottom:16px;
        }
        .field {
            display:flex;
            flex-direction:column;
            gap:4px;
        }
        .field label {
            font-size:13px;
            font-weight:600;
        }
        .field input[type="text"],
        .field input[type="date"],
        .field textarea {
            width:100%;
            padding:6px 8px;
            border:1px solid #ccc;
            border-radius:6px;
            font-size:13px;
        }
        textarea {
            resize:vertical;
            min-height:90px;
        }
        .case-body {
            display:grid;
            grid-template-columns:2fr 1.6fr;
            gap:20px;
        }
        .oc-row,
        .file-row {
            display:grid;
            grid-template-columns:auto 1fr;
            align-items:center;
            gap:6px;
            margin-bottom:6px;
            font-size:13px;
        }
        .oc-row span,
        .file-row span.label-text {
            white-space:nowrap;
        }
        .file-row input[type="text"] {
            width:100%;
        }
        .case-actions {
            display:flex;
            justify-content:space-between;
            gap:10px;
            margin-top:20px;
        }
        .case-actions button {
            flex:1;
            padding:10px 12px;
            border-radius:8px;
            border:1px solid #0d6efd;
            background:#0d6efd;
            color:#fff;
            font-size:14px;
            font-weight:600;
            cursor:pointer;
        }
        .case-actions button:nth-child(1),
        .case-actions button:nth-child(2) {
            background:#e5e7eb;
            color:#111827;
            border-color:#9ca3af;
        }
        .case-actions button:nth-child(4) {
            background:#10b981;
            border-color:#059669;
        }
        .case-actions button:hover {
            opacity:.9;
        }
    </style>
</head>
<body>

<div class="case-wrapper">
    <div class="case-title">Case Sheet</div>

    <form method="post" action="case_save.php" id="caseForm">
        <!-- top row: consultant / case status / next follow up -->
        <div class="case-header">
            <div class="field">
                <label for="consultant">Consultant</label>
                <input type="text" name="consultant" id="consultant">
            </div>

            <div class="field">
                <label for="case_status">Case status</label>
                <input type="text" name="case_status" id="case_status">
            </div>

            <div class="field">
                <label for="next_followup">Next follow up</label>
                <input type="date" name="next_followup" id="next_followup">
            </div>
        </div>

        <!-- middle: left = chief complaint & summary, right = other complaint & case files -->
        <div class="case-body">
            <!-- left column -->
            <div>
                <div class="field" style="margin-bottom:14px;">
                    <label for="chief_complaint">Chief Complaint</label>
                    <textarea name="chief_complaint" id="chief_complaint"></textarea>
                </div>

                <div class="field">
                    <label for="summary">Summary / Impression</label>
                    <textarea name="summary" id="summary"></textarea>
                </div>
            </div>

            <!-- right column -->
            <div>
                <!-- Other complaint -->
                <div class="field" style="margin-bottom:16px;">
                    <label>Other Complaint</label>

                    <div class="oc-row">
                        <span>1)</span>
                        <input type="text" name="other_complaint_1">
                    </div>
                    <div class="oc-row">
                        <span>2)</span>
                        <input type="text" name="other_complaint_2">
                    </div>
                    <div class="oc-row">
                        <span>3)</span>
                        <input type="text" name="other_complaint_3">
                    </div>
                </div>

                <!-- Case files details -->
                <div class="field">
                    <label>Case files Details</label>

                    <div class="file-row">
                        <span class="label-text">1) Pre case</span>
                        <input type="text" name="file_pre_case">
                    </div>
                    <div class="file-row">
                        <span class="label-text">2) Re case</span>
                        <input type="text" name="file_re_case">
                    </div>
                    <div class="file-row">
                        <span class="label-text">3) Report</span>
                        <input type="text" name="file_report">
                    </div>
                </div>
            </div>
        </div>

        <!-- bottom buttons -->
        <div class="case-actions">
            <button type="button" name="reanalyze_btn">Re-analysis</button>
            <button type="button" name="recasetak_btn">Re case taking</button>
            <button type="submit" name="save_case">Save Case</button>
            <button type="reset" name="new_form">New form</button>
        </div>
    </form>
</div>

</body>
</html>
