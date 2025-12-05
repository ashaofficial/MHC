<?php
include "../auth.php";
include "../components/helpers.php";
include "../secure/db.php";
include "../components/modal-dialogs.php";
include "../components/notification_js.php";

$role = $USER['role'] ?? '';
$isAdminRole = isAdmin($role);

if (!$isAdminRole) {
    die("Access denied! Only administrators can manage billing items.");
}

$errors = [];
$success = false;
$successMessage = '';
$items = [];

// Fetch all billing items
function fetchBillingItems(mysqli $conn): array {
    $list = [];
    $res = @mysqli_query($conn, "SELECT id, item_name, price, created_at FROM billing_items ORDER BY created_at DESC");
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            $list[] = $row;
        }
    }
    return $list;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = trim($_POST['action'] ?? '');
    
    if ($action === 'add') {
        $item_name = trim($_POST['item_name'] ?? '');
        $price = trim($_POST['price'] ?? '');

        if ($item_name === '') {
            $errors[] = "Item name is required.";
        }
        if ($price === '' || !is_numeric($price) || (float)$price < 0) {
            $errors[] = "Price must be a valid non-negative number.";
        }

        if (empty($errors)) {
            $price_val = (float)$price;
            $stmt = mysqli_prepare($conn, "INSERT INTO billing_items (item_name, price, created_at, updated_at) VALUES (?, ?, NOW(), NOW())");
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'sd', $item_name, $price_val);
                if (mysqli_stmt_execute($stmt)) {
                    $success = true;
                    $successMessage = "Billing item created successfully.";
                } else {
                    $errors[] = "Failed to create item: " . mysqli_error($conn);
                }
                mysqli_stmt_close($stmt);
            } else {
                $errors[] = "Failed to prepare statement: " . mysqli_error($conn);
            }
        }
    } elseif ($action === 'update') {
        $item_id = (int)($_POST['item_id'] ?? 0);
        $item_name = trim($_POST['item_name'] ?? '');
        $price = trim($_POST['price'] ?? '');

        if ($item_id <= 0) {
            $errors[] = "Invalid item ID.";
        }
        if ($item_name === '') {
            $errors[] = "Item name is required.";
        }
        if ($price === '' || !is_numeric($price) || (float)$price < 0) {
            $errors[] = "Price must be a valid non-negative number.";
        }

        if (empty($errors)) {
            $price_val = (float)$price;
            $stmt = mysqli_prepare($conn, "UPDATE billing_items SET item_name = ?, price = ?, updated_at = NOW() WHERE id = ?");
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'sdi', $item_name, $price_val, $item_id);
                if (mysqli_stmt_execute($stmt)) {
                    $success = true;
                    $successMessage = "Billing item updated successfully.";
                } else {
                    $errors[] = "Failed to update item: " . mysqli_error($conn);
                }
                mysqli_stmt_close($stmt);
            } else {
                $errors[] = "Failed to prepare statement: " . mysqli_error($conn);
            }
        }
    } elseif ($action === 'delete') {
        $item_id = (int)($_POST['item_id'] ?? 0);

        if ($item_id <= 0) {
            $errors[] = "Invalid item ID.";
        } else {
            $stmt = mysqli_prepare($conn, "DELETE FROM billing_items WHERE id = ?");
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'i', $item_id);
                if (mysqli_stmt_execute($stmt)) {
                    $success = true;
                    $successMessage = "Billing item deleted successfully.";
                } else {
                    $errors[] = "Failed to delete item: " . mysqli_error($conn);
                }
                mysqli_stmt_close($stmt);
            } else {
                $errors[] = "Failed to prepare statement: " . mysqli_error($conn);
            }
        }
    }
}

$items = fetchBillingItems($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Billing Items</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../css/common.css">
    <link rel="stylesheet" href="../css/patient-pages.css">
</head>
<body class="patient-surface">
<div class="patient-shell">
    <header class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h1 class="page-title mb-0">Manage Billing Items</h1>
            <p class="sub-text mb-0">Add, update, or delete billing items</p>
        </div>
    </header>

    <div class="patient-form-card glass-panel">
        <h5 class="mb-3">Add New Billing Item</h5>
        <form method="post" class="row g-3 mb-5 pb-5 border-bottom" id="addItemForm">
            <input type="hidden" name="action" value="add">
            
            <div class="col-md-6">
                <label class="form-label">Item Name <span class="text-danger">*</span></label>
                <input type="text" name="item_name" class="form-control" placeholder="e.g., Consultation Fee" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Price (₹) <span class="text-danger">*</span></label>
                <input type="number" step="0.01" min="0" name="price" class="form-control" placeholder="0.00" required>
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-add">Add Item</button>
            </div>
        </form>

        <h5 class="mb-3">Existing Billing Items</h5>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Item Name</th>
                        <th>Price (₹)</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($items)): ?>
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">No billing items found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($items as $index => $item): ?>
                            <tr>
                                <td><?= $index + 1 ?></td>
                                <td><?= htmlspecialchars($item['item_name']) ?></td>
                                <td><?= number_format($item['price'], 2) ?></td>
                                <td><?= date('M d, Y', strtotime($item['created_at'])) ?></td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editModal" onclick="loadEditForm(<?= (int)$item['id'] ?>, '<?= htmlspecialchars($item['item_name'], ENT_QUOTES) ?>', <?= $item['price'] ?>)">
                                        <i class="bi bi-pencil"></i> Edit
                                    </button>
                                    <form method="post" style="display:inline;" class="delete-item-form">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="item_id" value="<?= (int)$item['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                            <i class="bi bi-trash"></i> Delete
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Billing Item</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="post" id="editItemForm">
                <div class="modal-body">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="item_id" id="editItemId">
                    
                    <div class="mb-3">
                        <label class="form-label">Item Name <span class="text-danger">*</span></label>
                        <input type="text" name="item_name" id="editItemName" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Price (₹) <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" min="0" name="price" id="editItemPrice" class="form-control" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Item</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function loadEditForm(id, name, price) {
    document.getElementById('editItemId').value = id;
    document.getElementById('editItemName').value = name;
    document.getElementById('editItemPrice').value = price;
}

function attachConfirmHandler(form, message) {
    if (!form) return;
    form.addEventListener('submit', function (e) {
        if (form.dataset.confirmed === '1') {
            form.dataset.confirmed = '';
            return;
        }
        e.preventDefault();
        if (typeof showConfirmModal === 'function') {
            showConfirmModal(message, function () {
                form.dataset.confirmed = '1';
                form.submit();
            });
        } else if (confirm(message)) {
            form.submit();
        }
    });
}

document.addEventListener('DOMContentLoaded', function () {
    attachConfirmHandler(document.getElementById('addItemForm'), 'Are you sure you want to add this billing item?');
    attachConfirmHandler(document.getElementById('editItemForm'), 'Are you sure you want to update this billing item?');

    document.querySelectorAll('.delete-item-form').forEach(function (form) {
        attachConfirmHandler(form, 'Are you sure you want to delete this billing item?');
    });
});
</script>
<?php if ($success): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof showSuccessModal === 'function') {
        showSuccessModal(<?= json_encode($successMessage ?: 'Operation completed successfully.') ?>);
    }
});
</script>
<?php endif; ?>
<?php if (!empty($errors)): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof showErrorModal === 'function') {
        showErrorModal(<?= json_encode(implode("\n", $errors)) ?>);
    }
});
</script>
<?php endif; ?>
</body>
</html>
