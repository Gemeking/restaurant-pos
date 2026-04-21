<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title ?? APP_NAME) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>assets/css/style.css" rel="stylesheet">
    <?php if (!empty($extra_css)) echo $extra_css; ?>
</head>
<body>

<?php
$user         = getCurrentUser();
$current_page = basename($_SERVER['PHP_SELF']);
$is_full      = $full_page ?? false;

function navActive(string $pages): string {
    global $current_page;
    foreach (explode(',', $pages) as $p) {
        if (trim($p) === $current_page) return 'active';
    }
    return '';
}

$initials = strtoupper(
    substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)
);
?>

<?php if ($is_full): ?>
<!-- ── FULL-PAGE NAV (POS / KDS) ── -->
<nav class="fullpage-nav">
    <div class="nav-brand">
        <span style="background:var(--primary);width:28px;height:28px;border-radius:7px;display:flex;align-items:center;justify-content:center;">
            <i class="fa fa-utensils text-white" style="font-size:.85rem;"></i>
        </span>
        <?= APP_NAME ?>
    </div>
    <div class="nav-links">
        <a href="<?= BASE_URL ?>dashboard.php">
            <i class="fa fa-home"></i> Home
        </a>
        <?php if (in_array($user['role'], ['Manager','Waiter'])): ?>
        <a href="<?= BASE_URL ?>pos.php" class="<?= navActive('pos.php') ?>">
            <i class="fa fa-cash-register"></i> POS
        </a>
        <?php endif; ?>
        <?php if (in_array($user['role'], ['Manager','Kitchen','Waiter'])): ?>
        <a href="<?= BASE_URL ?>kds.php" class="<?= navActive('kds.php') ?>">
            <i class="fa fa-fire"></i> Kitchen
        </a>
        <?php endif; ?>
        <?php if ($user['role'] === 'Manager'): ?>
        <a href="<?= BASE_URL ?>reports.php" class="<?= navActive('reports.php') ?>">
            <i class="fa fa-chart-bar"></i> Reports
        </a>
        <?php endif; ?>
        <a href="<?= BASE_URL ?>logout.php" style="color:rgba(255,255,255,.4);">
            <i class="fa fa-sign-out-alt"></i> Logout
        </a>
    </div>
</nav>
<div>

<?php else: ?>
<!-- ── SIDEBAR LAYOUT ── -->

<!-- Overlay (mobile) -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- Sidebar -->
<aside class="sidebar" id="sidebar">
    <!-- Brand -->
    <div class="sidebar-brand">
        <div class="brand-icon"><i class="fa fa-utensils"></i></div>
        <div>
            <div><?= APP_NAME ?></div>
            <span class="brand-sub">Order &amp; Payment System</span>
        </div>
    </div>

    <!-- Navigation -->
    <nav class="sidebar-nav">
        <!-- Main -->
        <div class="sidebar-section">Main</div>
        <a href="<?= BASE_URL ?>dashboard.php" class="<?= navActive('dashboard.php') ?>">
            <i class="fa fa-home"></i> Dashboard
        </a>

        <?php if (in_array($user['role'], ['Manager','Waiter'])): ?>
        <!-- POS -->
        <div class="sidebar-section">Operations</div>
        <a href="<?= BASE_URL ?>pos.php" class="<?= navActive('pos.php') ?>">
            <i class="fa fa-cash-register"></i> POS Terminal
        </a>
        <?php endif; ?>

        <?php if (in_array($user['role'], ['Manager','Kitchen','Waiter'])): ?>
        <a href="<?= BASE_URL ?>kds.php" class="<?= navActive('kds.php') ?>">
            <i class="fa fa-fire-burner"></i> Kitchen Display
        </a>
        <?php endif; ?>

        <?php if ($user['role'] === 'Manager'): ?>
        <!-- Manager only -->
        <a href="<?= BASE_URL ?>reports.php" class="<?= navActive('reports.php') ?>">
            <i class="fa fa-chart-bar"></i> Daily Reports
        </a>

        <div class="sidebar-section">Settings</div>
        <a href="<?= BASE_URL ?>menu_management.php" class="<?= navActive('menu_management.php') ?>">
            <i class="fa fa-book-open"></i> Menu Items
        </a>
        <a href="<?= BASE_URL ?>staff.php" class="<?= navActive('staff.php') ?>">
            <i class="fa fa-users"></i> Staff
        </a>
        <?php endif; ?>

        <!-- Customer Menu preview -->
        <div class="sidebar-section">Customer</div>
        <a href="<?= BASE_URL ?>customer_menu.php?table=1" target="_blank">
            <i class="fa fa-qrcode"></i> Customer Menu
            <span class="badge bg-secondary">Preview</span>
        </a>
    </nav>

    <!-- Footer / User Info -->
    <div class="sidebar-footer">
        <div class="d-flex align-items-center gap-2 mb-1">
            <div style="width:34px;height:34px;background:var(--primary);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.8rem;font-weight:700;color:#fff;flex-shrink:0;">
                <?= $initials ?>
            </div>
            <div>
                <div class="user-name"><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></div>
                <div class="user-role"><?= $user['role'] ?></div>
            </div>
        </div>
        <a href="<?= BASE_URL ?>logout.php" class="logout-btn">
            <i class="fa fa-sign-out-alt"></i> Logout
        </a>
    </div>
</aside>

<!-- Main Content -->
<div class="main-content" id="mainContent">

    <!-- Top Bar -->
    <div class="topbar">
        <button class="topbar-toggle" id="sidebarToggle" onclick="toggleSidebar()">
            <i class="fa fa-bars"></i>
        </button>
        <div class="topbar-title">
            <span style="color:var(--muted);font-weight:400;font-size:.85rem;">
                <?= APP_NAME ?> <span class="breadcrumb-sep">/</span>
            </span>
            <?= htmlspecialchars($page_name ?? ($page_title ?? APP_NAME)) ?>
        </div>
        <div class="topbar-user d-none d-md-flex">
            <div class="topbar-avatar"><?= $initials ?></div>
            <div>
                <div style="font-size:.85rem;font-weight:600;color:var(--text);">
                    <?= htmlspecialchars($user['first_name']) ?>
                </div>
                <div style="font-size:.72rem;color:var(--muted);"><?= $user['role'] ?></div>
            </div>
        </div>
    </div>

    <!-- Page Body -->
    <div class="page-body">

<?php endif; ?>

<script>
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('sidebar-open');
    document.getElementById('sidebarOverlay').classList.toggle('show');
}
document.getElementById('sidebarOverlay')?.addEventListener('click', function() {
    document.getElementById('sidebar').classList.remove('sidebar-open');
    this.classList.remove('show');
});
</script>
