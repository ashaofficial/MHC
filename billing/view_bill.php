<?php
include "../auth.php";
include "../components/helpers.php";
include "../secure/db.php";

// Check if bill ID is provided
if (!isset($_GET['id'])) {
    header("Location: bill_history.php");
    exit();
}

$bill_id = (int)$_GET['id'];

// Get bill details
$bill = null;

$stmt = mysqli_prepare($conn, "
    SELECT b.bill_id, b.bill_date, b.patient_id, b.patient_name, b.consultant_id, b.consultant_name,
           b.total_amount, b.status, b.method, b.notes, b.created_at
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bill #<?= (int)$bill['bill_id'] ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <style>
        @media print {
            .no-print { display: none !important; }
            body { padding: 20px; font-size: 12px; }
            .card { border: none; }
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4 no-print">
            <h2>Bill #<?= (int)$bill['bill_id'] ?></h2>
            <div>
                <a href="bill_history.php" class="btn btn-secondary">Back to List</a>
                <button class="btn btn-success" onclick="window.print()">
                    <i class="bi bi-printer"></i> Print
                </button>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-body">
                <div class="row mb-4">
                    <div class="col-md-6">
                        <h5 class="mb-3">Bill To:</h5>
                        <p>
                            <strong><?= htmlspecialchars($bill['patient_name']) ?></strong><br>
                            <?php if (!empty($bill['consultant_name'])): ?>
                                Consultant: <?= htmlspecialchars($bill['consultant_name']) ?><br>
                            <?php endif; ?>
                        </p>
                    </div>
                    <div class="col-md-6 text-md-end">
                        <h5 class="mb-3">Bill Details</h5>
                        <p>
                            <strong>Bill #:</strong> <?= (int)$bill['bill_id'] ?><br>
                            <strong>Date:</strong> <?= date('d/m/Y', strtotime($bill['bill_date'])) ?><br>
                            <strong>Status:</strong> 
                            <span class="badge bg-<?= 
                                $bill['status'] === 'paid' ? 'success' : 
                                ($bill['status'] === 'pending' ? 'warning' : 
                                ($bill['status'] === 'unpaid' ? 'danger' : 'secondary')) 
                            ?>">
                                <?= ucfirst($bill['status']) ?>
                            </span>
                        </p>
                    </div>
                </div>

                <div class="table-responsive mb-4">
                    <table class="table">
                        <thead class="table-light">
                            <tr>
                                <th>Description</th>
                                <th class="text-end">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><strong>Service/Item</strong></td>
                                <td class="text-end"><strong>₹<?= number_format($bill['total_amount'], 2) ?></strong></td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td class="text-end"><strong>Total:</strong></td>
                                <td class="text-end"><strong>₹<?= number_format($bill['total_amount'], 2) ?></strong></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <?php if (!empty($bill['method'])): ?>
                <div class="row">
                    <div class="col-md-6">
                        <p class="mb-1"><strong>Payment Method:</strong> <?= htmlspecialchars($paymentMethods[$bill['method']] ?? ucfirst($bill['method'])) ?></p>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (!empty($bill['notes'])): ?>
                <div class="mt-4">
                    <h6>Notes</h6>
                    <p><?= nl2br(htmlspecialchars($bill['notes'])) ?></p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="text-center text-muted mb-4 no-print">
            <p>Thank you for your business!</p>
            <p>Generated on <?= date('F j, Y \a\t g:i A') ?></p>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
