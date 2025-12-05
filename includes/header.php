<?php
// This is a partial file, should be included in other PHP files
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medical Health Center - Billing System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/public/css/style.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="/">MHC Billing</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link <?= $current_page === 'index.php' ? 'active' : '' ?>" href="/">Dashboard</a>
                    </li>
                    <?php if (in_array($_SESSION['role_name'] ?? '', ['admin', 'receptionist'])): ?>
                    <li class="nav-item">
                        <a class="nav-link <?= $current_page === 'create_bill.php' ? 'active' : '' ?>" href="/billing/create_bill.php">Create Bill</a>
                    </li>
                    <?php endif; ?>
                    <li class="nav-item">
                        <a class="nav-link <?= $current_page === 'bill_history.php' ? 'active' : '' ?>" href="/billing/bill_history.php">Bill History</a>
                    </li>
                    <?php if (($_SESSION['role_name'] ?? '') === 'admin'): ?>
                    <li class="nav-item">
                        <a class="nav-link <?= $current_page === 'items.php' ? 'active' : '' ?>" href="/billing/items.php">Billing Items</a>
                    </li>
                    <?php endif; ?>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle"></i> <?= htmlspecialchars($_SESSION['user_name'] ?? 'User') ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="/profile.php">Profile</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="/auth/logout.php">Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?= $_SESSION['success'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?= $_SESSION['error'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>
