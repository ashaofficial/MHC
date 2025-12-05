<?php
include "../auth.php";
include "../components/helpers.php";
include "../secure/db.php";

// Check if bill ID is provided
if (!isset($_GET['id'])) {
    die("Bill ID is required.");
}

$bill_id = (int)$_GET['id'];

// Get bill details
$bill = null;

$stmt = mysqli_prepare($conn, "
    SELECT b.bill_id, b.bill_date, b.patient_id, b.patient_name, b.consultant_id, b.consultant_name,
           b.total_amount, b.status, b.method, b.notes, b.items_json, b.created_at
    FROM billings b
    WHERE b.bill_id = ?
");
if ($stmt) {
    mysqli_stmt_bind_param($stmt, 'i', $bill_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $bill = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
}

// Fetch patient age/gender for print (if available)
$patientAge = '';
$patientGender = '';
if (!empty($bill['patient_id'])) {
    $pstmt = $conn->prepare("SELECT COALESCE(age, '') AS age, COALESCE(gender, '') AS gender FROM patients WHERE id = ? LIMIT 1");
    if ($pstmt) {
        $pstmt->bind_param('i', $bill['patient_id']);
        $pstmt->execute();
        $pres = $pstmt->get_result();
        if ($prow = $pres->fetch_assoc()) {
            $patientAge = $prow['age'];
            $patientGender = $prow['gender'];
        }
        $pstmt->close();
    }
}

// Get bill items
$billItems = [];
$itemStmt = mysqli_prepare($conn, "
    SELECT bi.id, bi.description, bi.quantity, bi.rate, bi.amount
    FROM billing_items bi
    WHERE bi.bill_id = ?
    ORDER BY bi.id ASC
");
if ($itemStmt) {
    mysqli_stmt_bind_param($itemStmt, 'i', $bill_id);
    mysqli_stmt_execute($itemStmt);
    $itemResult = mysqli_stmt_get_result($itemStmt);
    while ($row = mysqli_fetch_assoc($itemResult)) {
        $billItems[] = $row;
    }
    mysqli_stmt_close($itemStmt);
}

// If no line items found in billing_items per-bill table, try to parse items from items_json column or notes
if (empty($billItems)) {
    // First try to parse items_json column
    if (!empty($bill['items_json'])) {
        $decoded = json_decode($bill['items_json'], true);
        if (is_array($decoded)) {
            foreach ($decoded as $li) {
                $itemDesc = $li['description'] ?? '';
                $itemQty = isset($li['quantity']) ? (int)$li['quantity'] : 1;
                $itemRate = isset($li['rate']) ? (float)$li['rate'] : 0.0;
                $itemAmount = isset($li['amount']) ? (float)$li['amount'] : ($itemRate ?: 0.0);

                // If a billing_item_id was stored, prefer fetching current details from billing_items table
                if (!empty($li['billing_item_id'])) {
                    $biId = (int)$li['billing_item_id'];
                    $bistmt = $conn->prepare("SELECT item_name, price FROM billing_items WHERE id = ? LIMIT 1");
                    if ($bistmt) {
                        $bistmt->bind_param('i', $biId);
                        $bistmt->execute();
                        $bires = $bistmt->get_result();
                        if ($birow = $bires->fetch_assoc()) {
                            $itemDesc = $birow['item_name'];
                            $itemRate = (float)$birow['price'];
                            $itemAmount = $itemRate * $itemQty;
                        }
                        $bistmt->close();
                    }
                }

                $billItems[] = [
                    'description' => $itemDesc,
                    'quantity' => $itemQty,
                    'rate' => $itemRate,
                    'amount' => $itemAmount
                ];
            }
        }
    }
    
    // Fallback: try to parse items from notes (backward compatibility with old format)
    if (empty($billItems) && !empty($bill['notes']) && strpos($bill['notes'], '__ITEMS__:') === 0) {
        $rest = substr($bill['notes'], strlen('__ITEMS__:'));
        // rest may contain JSON then newline and other notes
        $jsonPart = $rest;
        if (strpos($rest, "\n") !== false) {
            $parts = explode("\n", $rest, 2);
            $jsonPart = $parts[0];
        }
        $decoded = json_decode($jsonPart, true);
        if (is_array($decoded)) {
            foreach ($decoded as $li) {
                $itemDesc = $li['description'] ?? '';
                $itemQty = isset($li['quantity']) ? (int)$li['quantity'] : 1;
                $itemRate = isset($li['rate']) ? (float)$li['rate'] : 0.0;
                $itemAmount = isset($li['amount']) ? (float)$li['amount'] : ($itemRate ?: 0.0);

                // If a billing_item_id was stored, prefer fetching current details from billing_items table
                if (!empty($li['billing_item_id'])) {
                    $biId = (int)$li['billing_item_id'];
                    $bistmt = $conn->prepare("SELECT item_name, price FROM billing_items WHERE id = ? LIMIT 1");
                    if ($bistmt) {
                        $bistmt->bind_param('i', $biId);
                        $bistmt->execute();
                        $bires = $bistmt->get_result();
                        if ($birow = $bires->fetch_assoc()) {
                            $itemDesc = $birow['item_name'];
                            $itemRate = (float)$birow['price'];
                            $itemAmount = $itemRate * $itemQty;
                        }
                        $bistmt->close();
                    }
                }

                $billItems[] = [
                    'description' => $itemDesc,
                    'quantity' => $itemQty,
                    'rate' => $itemRate,
                    'amount' => $itemAmount
                ];
            }
        }
    }
}

if (!$bill) {
    die("Bill not found.");
}

$paymentMethods = [
    'cash'          => 'Cash',
    'card'          => 'Card / POS',
    'upi'           => 'UPI',
    'bank_transfer' => 'Bank Transfer',
    'other'         => 'Other'
];

// Clinic information
$clinicName = "MANITHAM HOMEO HEALING CENTER";
$clinicAddress = "No.55/4 Ashtalakshmi Nagar, Agaram village, Kattur, Trichy - 620018";
$clinicPhone = "+90479 85143";
$clinicEmail = "33ABGFM0266PLZO";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bill #<?= (int)$bill['bill_id'] ?> - <?= htmlspecialchars($clinicName) ?></title>
    <style>
        @page {
            size: A4;
            margin: 10mm;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Courier New', monospace;
            font-size: 10pt;
            line-height: 1.4;
            color: #000;
            background: #fff;
            padding: 10px;
        }
        
        .bill-container {
            max-width: 210mm;
            margin: 0 auto;
            background: #fff;
        }
        
        .bill-header {
            text-align: center;
            margin-bottom: 10px;
        }
        
        .header-top {
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 8px;
            gap: 15px;
        }
        
        .logo-container {
            flex-shrink: 0;
        }
        
        .logo-container img {
            max-height: 50px;
            max-width: 80px;
        }
        
        .clinic-info {
            flex: 1;
        }
        
        .clinic-name {
            font-size: 13pt;
            font-weight: bold;
            letter-spacing: 1px;
        }
        
        .clinic-address {
            font-size: 9pt;
            line-height: 1.3;
            margin-top: 2px;
        }
        
        .bill-type {
            text-align: center;
            font-size: 10pt;
            font-weight: bold;
            margin: 5px 0;
            letter-spacing: 1px;
        }
        
        .duplicate-copy {
            position: absolute;
            right: 20px;
            top: 120px;
            font-size: 9pt;
            color: #666;
        }
        
        .header-line {
            border-bottom: 1px dashed #000;
            margin: 8px 0;
        }
        
        .bill-meta {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 10px;
            font-size: 9pt;
        }
        
        .meta-item {
            display: flex;
            justify-content: space-between;
        }
        
        .meta-label {
            font-weight: bold;
        }
        
        .patient-consultant-section {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 10px;
            font-size: 9pt;
        }
        
        .section-block {
            display: flex;
            justify-content: space-between;
        }
        
        .section-label {
            font-weight: bold;
            min-width: 90px;
        }
        
        .section-value {
            flex: 1;
            text-align: left;
            padding-left: 10px;
        }
        
        .items-table {
            width: 100%;
            margin: 12px 0;
            border-collapse: collapse;
            font-size: 9pt;
        }
        
        .items-table thead {
            border-top: 1px solid #000;
            border-bottom: 1px solid #000;
        }
        
        .items-table th {
            padding: 4px 6px;
            text-align: left;
            font-weight: bold;
        }
        
        .items-table td {
            padding: 6px;
            border-bottom: 1px dotted #ccc;
        }
        
        .items-table tbody tr:last-child td {
            border-bottom: 1px solid #000;
        }
        
        .col-sno {
            width: 5%;
            text-align: center;
        }
        
        .col-desc {
            width: 50%;
        }
        
        .col-qty {
            width: 12%;
            text-align: center;
        }
        
        .col-rate {
            width: 15%;
            text-align: right;
        }
        
        .col-amount {
            width: 18%;
            text-align: right;
        }
        
        .amount-words {
            font-family: Arial, sans-serif;
            font-size: 9pt;
            margin-top: 10px;
        }
        
        .totals-section {
            margin-top: 10px;
            text-align: right;
            font-size: 9pt;
        }
        
        .total-row {
            display: flex;
            justify-content: flex-end;
            gap: 30px;
            margin-top: 5px;
            padding: 4px 0;
        }
        
        .total-label {
            min-width: 100px;
            text-align: left;
        }
        
        .total-amount {
            min-width: 80px;
            text-align: right;
            border-bottom: 1px solid #000;
        }
        
        .footer {
            margin-top: 15px;
            text-align: center;
            font-size: 8pt;
            color: #000;
            border-top: 1px dashed #000;
            padding-top: 8px;
            line-height: 1.3;
        }
        
        .no-print {
            display: none;
        }
        
        @media print {
            body {
                padding: 0;
            }
            
            .no-print {
                display: none !important;
            }
            
            .bill-container {
                max-width: 100%;
                padding: 10mm;
            }
        }
        
        @media screen {
            .print-button {
                position: fixed;
                top: 20px;
                right: 20px;
                z-index: 1000;
                padding: 10px 20px;
                background: #007bff;
                color: white;
                border: none;
                border-radius: 5px;
                cursor: pointer;
                font-size: 14pt;
            }
            
            .print-button:hover {
                background: #0056b3;
            }
        }
    </style>
</head>
<body>
    <button class="print-button no-print" onclick="window.print()">🖨️ Print Bill</button>
    
    <div class="bill-container">
        <!-- Header -->
        <div class="bill-header">
            <div class="header-top">
                <div class="logo-container">
                    <img src="../images/logo_MHC.jpeg" alt="Logo" onerror="this.style.display='none'">
                </div>
                <div class="clinic-info">
                    <div class="clinic-name"><?= htmlspecialchars($clinicName) ?></div>
                    <div class="clinic-address">
                        <?= htmlspecialchars($clinicAddress) ?><br>
                        <?= htmlspecialchars($clinicPhone) ?> | <?= htmlspecialchars($clinicEmail) ?>
                    </div>
                </div>
            </div>
            <div class="bill-type">PATIENT BILL / RECEIPT</div>
            <div class="header-line"></div>
        </div>
        
        <div class="duplicate-copy">Duplicate copy</div>
        
        <!-- Bill Metadata -->
        <div class="bill-meta">
            <div class="meta-item">
                <span class="meta-label">Casesheet No. :</span>
                <span><?= (int)$bill['bill_id'] ?></span>
            </div>
            <div class="meta-item">
                <span class="meta-label">Bill No.:</span>
                <span><?= (int)$bill['bill_id'] ?></span>
            </div>
        </div>
        
        <!-- Patient & Consultant Info -->
        <div class="patient-consultant-section">
            <div>
                <div class="section-block">
                    <span class="section-label">Name</span>
                    <span class="section-value">: <?= htmlspecialchars($bill['patient_name'] ?: '—') ?></span>
                </div>
                <div class="section-block">
                    <span class="section-label">Consultant</span>
                    <span class="section-value">: <?= htmlspecialchars($bill['consultant_name'] ?: '—') ?></span>
                </div>
            </div>
            <div>
                <div class="section-block">
                    <span class="section-label">Age/Sex :</span>
                    <span><?= htmlspecialchars($patientAge ?: '—') ?> / <?= htmlspecialchars($patientGender ?: '—') ?></span>
                </div>
                <div class="section-block">
                    <span class="section-label">Date</span>
                    <span>: <?= date('d-m-Y H:i A', strtotime($bill['bill_date'])) ?></span>
                </div>
            </div>
        </div>
        
        <div class="header-line"></div>
        
        <!-- Items Table -->
        <table class="items-table">
            <thead>
                <tr>
                    <th class="col-sno">S.No.</th>
                    <th class="col-desc">Descriptions</th>
                    <th class="col-qty">Qty</th>
                    <th class="col-rate">Rate</th>
                    <th class="col-amount">Amount</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($billItems)): ?>
                    <?php foreach ($billItems as $index => $item): ?>
                    <tr>
                        <td class="col-sno"><?= $index + 1 ?></td>
                        <td class="col-desc"><?= htmlspecialchars($item['description']) ?></td>
                        <td class="col-qty"><?= (int)$item['quantity'] ?></td>
                        <td class="col-rate"><?= number_format((float)$item['rate'], 2) ?></td>
                        <td class="col-amount"><?= number_format((float)$item['amount'], 2) ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" style="text-align: center; padding: 20px;">No items found</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
        
        <!-- Amount Words -->
        <div class="amount-words">
            <strong>Amount in Words:</strong> <?= amountInWords((float)$bill['total_amount']) ?>
        </div>
        
        <!-- Totals -->
        <div class="totals-section">
            <div class="total-row">
                <span class="total-label">Gross Amt.</span>
                <span class="total-amount"><?= number_format((float)$bill['total_amount'], 2) ?></span>
            </div>
            <div class="total-row">
                <span class="total-label">Net Amount</span>
                <span class="total-amount"><?= number_format((float)$bill['total_amount'], 2) ?></span>
            </div>
        </div>
        
        <!-- Footer -->
        <div class="footer">
            <p>This is a computer generated bill which does not require signature</p>
            <p>Health Care Service exempted from GST by the notification No. 9/2017-dt-28.06.2017</p>
        </div>
    </div>
    
    <script>
        // Auto-print when opened in new window
        if (window.location.search.includes('autoprint=1')) {
            window.onload = function() {
                window.print();
            };
        }
    </script>
</body>
</html>

