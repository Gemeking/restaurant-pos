<?php
session_start();
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
requireRole(['Manager']);

$page_title = 'Daily Reports — ' . APP_NAME;
$page_name  = 'Daily Reports';
$date       = $_GET['date'] ?? date('Y-m-d');

$stmt = $pdo->prepare("
    SELECT COUNT(DISTINCT order_id) AS cnt,
           COALESCE(SUM(subtotal),0) AS subtotal,
           COALESCE(SUM(tax_amount),0) AS tax,
           COALESCE(SUM(discount_amount),0) AS discount,
           COALESCE(SUM(total_amount),0) AS revenue
    FROM orders WHERE order_status='Paid' AND DATE(order_datetime)=?
");
$stmt->execute([$date]);
$summary = $stmt->fetch();

$stmt = $pdo->prepare("
    SELECT p.method, COUNT(*) AS cnt, SUM(p.amount) AS total
    FROM payments p JOIN orders o ON o.order_id=p.order_id
    WHERE o.order_status='Paid' AND DATE(o.order_datetime)=?
    GROUP BY p.method
");
$stmt->execute([$date]);
$payment_methods = $stmt->fetchAll();

$stmt = $pdo->prepare("
    SELECT m.name, SUM(oi.quantity) AS qty, SUM(oi.quantity*oi.price_at_sale) AS rev
    FROM order_items oi
    JOIN menu_items m ON m.menu_item_id=oi.menu_item_id
    JOIN orders o ON o.order_id=oi.order_id
    WHERE o.order_status='Paid' AND DATE(o.order_datetime)=?
    GROUP BY m.menu_item_id ORDER BY qty DESC LIMIT 10
");
$stmt->execute([$date]);
$top_items = $stmt->fetchAll();

$stmt = $pdo->prepare("
    SELECT o.order_id, o.table_number, o.total_amount, o.order_datetime, o.source,
           s.first_name, s.last_name,
           GROUP_CONCAT(DISTINCT p.method ORDER BY p.method SEPARATOR ', ') AS methods
    FROM orders o
    LEFT JOIN staff s ON s.staff_id=o.staff_id
    LEFT JOIN payments p ON p.order_id=o.order_id
    WHERE o.order_status='Paid' AND DATE(o.order_datetime)=?
    GROUP BY o.order_id ORDER BY o.order_datetime DESC
");
$stmt->execute([$date]);
$transactions = $stmt->fetchAll();

include __DIR__ . '/includes/header.php';
?>

<!-- Page Header -->
<div class="page-header">
    <div class="page-header-text">
        <h4><i class="fa fa-chart-bar me-2 text-success"></i>Daily Sales Report</h4>
        <p>View revenue, transactions and top items for any day.</p>
    </div>
    <form class="d-flex gap-2 align-items-center" method="GET">
        <input type="date" name="date" value="<?= $date ?>" class="form-control form-control-sm" max="<?= date('Y-m-d') ?>">
        <button type="submit" class="btn btn-primary btn-sm"><i class="fa fa-search me-1"></i>View</button>
        <a href="?date=<?= date('Y-m-d') ?>" class="btn btn-outline-secondary btn-sm">Today</a>
    </form>
</div>

<?php if ($summary['cnt'] == 0): ?>
<div class="tip-box">
    <i class="fa fa-info-circle"></i>
    <span>No completed transactions found for <strong><?= date('D, d M Y', strtotime($date)) ?></strong>. Select a different date or complete some orders first.</span>
</div>
<?php endif; ?>

<!-- Summary Cards -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="report-stat card shadow-sm">
            <div class="rs-val text-primary"><?= $summary['cnt'] ?></div>
            <div class="rs-label">Transactions</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="report-stat card shadow-sm">
            <div class="rs-val text-success"><?= number_format($summary['revenue'],2) ?></div>
            <div class="rs-label">Total Revenue (ETB)</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="report-stat card shadow-sm">
            <div class="rs-val text-info"><?= number_format($summary['tax'],2) ?></div>
            <div class="rs-label">Tax Collected (ETB)</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="report-stat card shadow-sm">
            <div class="rs-val text-danger"><?= number_format($summary['discount'],2) ?></div>
            <div class="rs-label">Discounts Given (ETB)</div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <!-- Payment Methods -->
    <div class="col-md-4">
        <div class="card shadow-sm h-100">
            <div class="card-header">
                <i class="fa fa-credit-card me-2 text-primary"></i>Payment Methods
            </div>
            <div class="card-body">
                <?php if (empty($payment_methods)): ?>
                    <p class="text-muted text-center py-3 small">No data for this date</p>
                <?php else:
                    $total_paid = array_sum(array_column($payment_methods,'total'));
                    foreach ($payment_methods as $pm):
                    $pct = $total_paid > 0 ? round($pm['total']/$total_paid*100) : 0;
                ?>
                <div class="mb-3">
                    <div class="d-flex justify-content-between mb-1">
                        <span class="fw-semibold small"><?= htmlspecialchars($pm['method']) ?></span>
                        <span class="small text-muted"><?= $pct ?>%</span>
                    </div>
                    <div class="progress" style="height:8px;">
                        <div class="progress-bar bg-primary" style="width:<?= $pct ?>%"></div>
                    </div>
                    <div class="d-flex justify-content-between mt-1">
                        <span class="small text-muted"><?= $pm['cnt'] ?> transaction<?= $pm['cnt']>1?'s':'' ?></span>
                        <span class="small fw-bold"><?= number_format($pm['total'],2) ?> ETB</span>
                    </div>
                </div>
                <?php endforeach; endif; ?>
            </div>
        </div>
    </div>

    <!-- Top Items -->
    <div class="col-md-8">
        <div class="card shadow-sm h-100">
            <div class="card-header">
                <i class="fa fa-trophy me-2 text-warning"></i>Top Selling Items
            </div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <thead class="table-light">
                        <tr><th>#</th><th>Item</th><th class="text-center">Qty Sold</th><th class="text-end">Revenue</th></tr>
                    </thead>
                    <tbody>
                    <?php if (empty($top_items)): ?>
                        <tr><td colspan="4" class="text-center text-muted py-3 small">No data</td></tr>
                    <?php else: foreach ($top_items as $i => $it): ?>
                    <tr>
                        <td>
                            <?php if ($i==0): ?><i class="fa fa-trophy text-warning"></i>
                            <?php elseif ($i==1): ?><i class="fa fa-trophy text-secondary"></i>
                            <?php elseif ($i==2): ?><i class="fa fa-trophy" style="color:#cd7f32"></i>
                            <?php else: ?><span class="text-muted"><?= $i+1 ?></span><?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($it['name']) ?></td>
                        <td class="text-center"><span class="badge bg-primary"><?= $it['qty'] ?></span></td>
                        <td class="text-end fw-semibold"><?= number_format($it['rev'],2) ?></td>
                    </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Transaction List -->
<div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fa fa-list me-2"></i>All Transactions — <?= date('D, d M Y', strtotime($date)) ?></span>
        <div class="d-flex gap-2 align-items-center">
            <span class="badge bg-secondary"><?= count($transactions) ?> orders</span>
            <button class="btn btn-sm btn-outline-secondary" onclick="window.print()">
                <i class="fa fa-print me-1"></i>Print
            </button>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-hover table-sm mb-0 align-middle">
            <thead class="table-light">
                <tr><th>Order #</th><th>Table</th><th>Staff / Source</th><th>Method</th><th class="text-end">Total</th><th>Time</th></tr>
            </thead>
            <tbody>
            <?php if (empty($transactions)): ?>
                <tr><td colspan="6" class="text-center text-muted py-4">
                    <i class="fa fa-inbox fa-2x mb-2 d-block opacity-25"></i>No transactions on this date
                </td></tr>
            <?php else: foreach ($transactions as $t): ?>
            <tr>
                <td><strong>#<?= $t['order_id'] ?></strong></td>
                <td>Table <?= $t['table_number'] ?></td>
                <td>
                    <?= $t['source']==='Customer'
                        ? '<span class="badge bg-info">Customer QR</span>'
                        : htmlspecialchars(($t['first_name']??'').($t['last_name']?' '.$t['last_name']:'')) ?>
                </td>
                <td><span class="badge bg-secondary"><?= htmlspecialchars($t['methods']??'N/A') ?></span></td>
                <td class="text-end fw-bold"><?= number_format($t['total_amount'],2) ?> ETB</td>
                <td class="text-muted small"><?= date('H:i', strtotime($t['order_datetime'])) ?></td>
            </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
