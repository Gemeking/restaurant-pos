<?php
session_start();
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
requireLogin();

$user       = getCurrentUser();
$page_title = 'Home — ' . APP_NAME;
$page_name  = 'Home';
$today      = date('Y-m-d');

// Stats
$active_tables      = $pdo->query("SELECT COUNT(*) FROM restaurant_tables WHERE status='Active'")->fetchColumn();
$open_orders        = $pdo->query("SELECT COUNT(*) FROM orders WHERE order_status NOT IN ('Paid','Cancelled') AND DATE(order_datetime)='$today'")->fetchColumn();
$kitchen_queue      = $pdo->query("SELECT COUNT(*) FROM orders WHERE order_status IN ('Sent to Kitchen','In Progress')")->fetchColumn();
$today_revenue      = (float)$pdo->query("SELECT COALESCE(SUM(total_amount),0) FROM orders WHERE order_status='Paid' AND DATE(order_datetime)='$today'")->fetchColumn();
$today_transactions = $pdo->query("SELECT COUNT(*) FROM orders WHERE order_status='Paid' AND DATE(order_datetime)='$today'")->fetchColumn();

// Recent orders
$recent = $pdo->query("
    SELECT o.order_id, o.table_number, o.order_status, o.total_amount, o.order_datetime, o.source,
           s.first_name, s.last_name
    FROM orders o LEFT JOIN staff s ON s.staff_id = o.staff_id
    ORDER BY o.order_datetime DESC LIMIT 8
")->fetchAll();

include __DIR__ . '/includes/header.php';
?>

<!-- Welcome Banner -->
<div class="welcome-banner fade-in">
    <div class="role-pill">
        <i class="fa fa-shield-halved me-1"></i><?= $user['role'] ?>
    </div>
    <h3>Welcome back, <?= htmlspecialchars($user['first_name']) ?>! 👋</h3>
    <p>
        <?php if ($user['role'] === 'Manager'): ?>
            You have full access — manage staff, menu, view reports and operate the POS.
        <?php elseif ($user['role'] === 'Waiter'): ?>
            Ready to take orders? Open the POS Terminal to get started.
        <?php else: ?>
            Head to the Kitchen Display to see incoming orders.
        <?php endif; ?>
    </p>
</div>

<!-- Stats Row -->
<div class="row g-3 mb-4 fade-in">
    <div class="col-6 col-md-3">
        <div class="stat-card" style="border-left-color:#e74c3c;">
            <i class="fa fa-chair stat-icon text-danger"></i>
            <div class="stat-val text-danger"><?= $active_tables ?></div>
            <div class="stat-label">Active Tables</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card" style="border-left-color:#3498db;">
            <i class="fa fa-receipt stat-icon text-primary"></i>
            <div class="stat-val text-primary"><?= $open_orders ?></div>
            <div class="stat-label">Open Orders Today</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card" style="border-left-color:#e67e22;">
            <i class="fa fa-fire stat-icon text-warning"></i>
            <div class="stat-val text-warning"><?= $kitchen_queue ?></div>
            <div class="stat-label">Kitchen Queue</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card" style="border-left-color:#27ae60;">
            <i class="fa fa-coins stat-icon text-success"></i>
            <div class="stat-val text-success"><?= number_format($today_revenue) ?></div>
            <div class="stat-label">Revenue Today (ETB)</div>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="mb-2 d-flex align-items-center gap-2">
    <h5 class="mb-0 fw-bold"><i class="fa fa-bolt me-2 text-warning"></i>Quick Actions</h5>
    <span class="text-muted small">— click to go directly</span>
</div>
<div class="row g-3 mb-4 fade-in">

    <?php if (in_array($user['role'], ['Manager','Waiter'])): ?>
    <div class="col-12 col-sm-6 col-md-4">
        <a href="<?= BASE_URL ?>pos.php" class="action-card ac-pos">
            <span class="ac-icon"><i class="fa fa-cash-register"></i></span>
            <div class="ac-title">POS Terminal</div>
            <div class="ac-desc">Open tables, take orders, add items and send them to the kitchen</div>
            <div class="ac-arrow"><i class="fa fa-arrow-right"></i> Open POS</div>
        </a>
    </div>
    <?php endif; ?>

    <?php if (in_array($user['role'], ['Manager','Kitchen','Waiter'])): ?>
    <div class="col-12 col-sm-6 col-md-4">
        <a href="<?= BASE_URL ?>kds.php" class="action-card ac-kds">
            <span class="ac-icon"><i class="fa fa-fire-burner"></i></span>
            <div class="ac-title">Kitchen Display</div>
            <div class="ac-desc">See all incoming orders in real-time. Mark items as in progress and ready</div>
            <div class="ac-arrow">
                <i class="fa fa-arrow-right"></i> Open Kitchen
                <?php if ($kitchen_queue > 0): ?>
                    <span class="badge bg-white text-danger ms-1"><?= $kitchen_queue ?> waiting</span>
                <?php endif; ?>
            </div>
        </a>
    </div>
    <?php endif; ?>

    <?php if ($user['role'] === 'Manager'): ?>
    <div class="col-12 col-sm-6 col-md-4">
        <a href="<?= BASE_URL ?>reports.php" class="action-card ac-reports">
            <span class="ac-icon"><i class="fa fa-chart-bar"></i></span>
            <div class="ac-title">Daily Reports</div>
            <div class="ac-desc">View today's revenue, transaction count, top items and payment methods</div>
            <div class="ac-arrow"><i class="fa fa-arrow-right"></i> View Reports</div>
        </a>
    </div>
    <div class="col-12 col-sm-6 col-md-4">
        <a href="<?= BASE_URL ?>staff.php" class="action-card ac-staff">
            <span class="ac-icon"><i class="fa fa-users"></i></span>
            <div class="ac-title">Staff Management</div>
            <div class="ac-desc">Add, edit, deactivate staff accounts and reset passwords</div>
            <div class="ac-arrow"><i class="fa fa-arrow-right"></i> Manage Staff</div>
        </a>
    </div>
    <div class="col-12 col-sm-6 col-md-4">
        <a href="<?= BASE_URL ?>menu_management.php" class="action-card ac-menu">
            <span class="ac-icon"><i class="fa fa-book-open"></i></span>
            <div class="ac-title">Menu Items</div>
            <div class="ac-desc">Add new dishes, update prices, or mark items as unavailable</div>
            <div class="ac-arrow"><i class="fa fa-arrow-right"></i> Edit Menu</div>
        </a>
    </div>
    <?php endif; ?>

    <div class="col-12 col-sm-6 col-md-4">
        <a href="<?= BASE_URL ?>customer_menu.php?table=1" target="_blank" class="action-card ac-customer">
            <span class="ac-icon"><i class="fa fa-qrcode"></i></span>
            <div class="ac-title">Customer Menu</div>
            <div class="ac-desc">Preview what customers see when they scan the QR code on a table</div>
            <div class="ac-arrow"><i class="fa fa-arrow-right"></i> Preview (opens new tab)</div>
        </a>
    </div>
</div>

<!-- How to Use Guide -->
<div class="mb-2 d-flex align-items-center gap-2">
    <h5 class="mb-0 fw-bold"><i class="fa fa-circle-question me-2 text-info"></i>How to Use</h5>
    <span class="text-muted small">— step by step guides</span>
</div>
<div class="guide-card mb-4 fade-in">
<div class="accordion" id="guideAccordion">

    <?php if (in_array($user['role'], ['Manager','Waiter'])): ?>
    <!-- Waiter Guide -->
    <div class="accordion-item border-0 border-bottom">
        <h2 class="accordion-header">
            <button class="accordion-button <?= $user['role']==='Waiter' ? '' : 'collapsed' ?>"
                    type="button" data-bs-toggle="collapse" data-bs-target="#g1">
                <i class="fa fa-cash-register me-2 text-danger"></i>
                How to Take an Order (Waiter)
            </button>
        </h2>
        <div id="g1" class="accordion-collapse collapse <?= $user['role']==='Waiter' ? 'show' : '' ?>" data-bs-parent="#guideAccordion">
            <div class="accordion-body">
                <div class="guide-step">
                    <div class="step-num">1</div>
                    <div class="step-text">Click <strong>POS Terminal</strong> from the sidebar or the Quick Action card above.</div>
                </div>
                <div class="guide-step">
                    <div class="step-num">2</div>
                    <div class="step-text">You will see a grid of all tables. <strong>Green = Available, Red = Has an active order</strong>. Click the customer's table number.</div>
                </div>
                <div class="guide-step">
                    <div class="step-num">3</div>
                    <div class="step-text">The screen splits into 3 columns: <strong>Categories → Items → Order Ticket</strong>. Select a category on the left, then tap an item in the middle to add it to the order ticket on the right.</div>
                </div>
                <div class="guide-step">
                    <div class="step-num">4</div>
                    <div class="step-text">To add a special note (e.g. "no onion"), click the <i class="fa fa-comment-alt"></i> icon next to the item in the order ticket.</div>
                </div>
                <div class="guide-step">
                    <div class="step-num">5</div>
                    <div class="step-text">When ready, click the <strong style="color:#e67e22">Send to Kitchen</strong> button. The kitchen will see it immediately on their screen.</div>
                </div>
                <div class="guide-step">
                    <div class="step-num">6</div>
                    <div class="step-text">When the kitchen marks the order <strong>Ready</strong>, deliver the food, then click <strong style="color:#27ae60">Checkout</strong> to process payment.</div>
                </div>
                <div class="guide-tip">
                    <i class="fa fa-lightbulb me-1"></i>
                    <strong>Tip:</strong> You can add more items to an order anytime before checkout. Just click the table again.
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if (in_array($user['role'], ['Manager','Kitchen'])): ?>
    <!-- Kitchen Guide -->
    <div class="accordion-item border-0 border-bottom">
        <h2 class="accordion-header">
            <button class="accordion-button <?= $user['role']==='Kitchen' ? '' : 'collapsed' ?>"
                    type="button" data-bs-toggle="collapse" data-bs-target="#g2">
                <i class="fa fa-fire me-2 text-warning"></i>
                How to Use the Kitchen Display (Kitchen Staff)
            </button>
        </h2>
        <div id="g2" class="accordion-collapse collapse <?= $user['role']==='Kitchen' ? 'show' : '' ?>" data-bs-parent="#guideAccordion">
            <div class="accordion-body">
                <div class="guide-step">
                    <div class="step-num">1</div>
                    <div class="step-text">Click <strong>Kitchen Display</strong> from the sidebar. Keep this page open on the kitchen screen at all times.</div>
                </div>
                <div class="guide-step">
                    <div class="step-num">2</div>
                    <div class="step-text">New orders appear automatically every 3 seconds — you do NOT need to refresh the page. <strong>A pulsing red border</strong> means a brand new order just arrived.</div>
                </div>
                <div class="guide-step">
                    <div class="step-num">3</div>
                    <div class="step-text">Each order card shows the <strong>Table Number</strong>, items ordered, any special notes (e.g. "no onion"), and how long ago it was ordered.</div>
                </div>
                <div class="guide-step">
                    <div class="step-num">4</div>
                    <div class="step-text">Click <strong>Start All Items</strong> when you begin cooking, or click the <i class="fa fa-fire"></i> icon next to individual items to start them one by one.</div>
                </div>
                <div class="guide-step">
                    <div class="step-num">5</div>
                    <div class="step-text">When food is done, click <strong>Mark All Ready</strong> or the <i class="fa fa-check"></i> icon per item. The waiter will be notified.</div>
                </div>
                <div class="guide-step">
                    <div class="step-num">6</div>
                    <div class="step-text">Click <strong>Order Ready — Bump</strong> to remove the ticket from your screen after the waiter picks up the food.</div>
                </div>
                <div class="guide-tip">
                    <i class="fa fa-lightbulb me-1"></i>
                    <strong>Timer:</strong> The yellow number on each ticket shows how many minutes/seconds since the order was placed. Use it to prioritize.
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Customer QR Guide -->
    <div class="accordion-item border-0 border-bottom">
        <h2 class="accordion-header">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#g3">
                <i class="fa fa-qrcode me-2 text-info"></i>
                How Customers Order Using the QR Code
            </button>
        </h2>
        <div id="g3" class="accordion-collapse collapse" data-bs-parent="#guideAccordion">
            <div class="accordion-body">
                <div class="guide-step">
                    <div class="step-num">1</div>
                    <div class="step-text">Place a printed QR code on each table. Each QR code links to:<br>
                        <code style="font-size:.8rem;"><?= BASE_URL ?>customer_menu.php?table=3</code><br>
                        (replace "3" with the actual table number)</div>
                </div>
                <div class="guide-step">
                    <div class="step-num">2</div>
                    <div class="step-text">Customer scans the QR code with their phone. The menu opens immediately — <strong>no login, no app needed</strong>.</div>
                </div>
                <div class="guide-step">
                    <div class="step-num">3</div>
                    <div class="step-text">Customer browses categories, taps items, adds notes (e.g. "extra spicy"), then taps <strong>Place Order</strong>.</div>
                </div>
                <div class="guide-step">
                    <div class="step-num">4</div>
                    <div class="step-text">The order appears <strong>instantly</strong> on the Kitchen Display screen — exactly like an order from a waiter.</div>
                </div>
                <div class="guide-tip">
                    <i class="fa fa-lightbulb me-1"></i>
                    <strong>Click "Customer Menu" in the sidebar</strong> (or the Quick Action card above) to preview exactly what customers see on their phone.
                </div>
            </div>
        </div>
    </div>

    <!-- Payment Guide -->
    <?php if (in_array($user['role'], ['Manager','Waiter'])): ?>
    <div class="accordion-item border-0 border-bottom">
        <h2 class="accordion-header">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#g4">
                <i class="fa fa-credit-card me-2 text-success"></i>
                How to Process Payment
            </button>
        </h2>
        <div id="g4" class="accordion-collapse collapse" data-bs-parent="#guideAccordion">
            <div class="accordion-body">
                <div class="guide-step">
                    <div class="step-num">1</div>
                    <div class="step-text">Go to the <strong>POS Terminal</strong> and click on the customer's table (it will be red, showing it's active).</div>
                </div>
                <div class="guide-step">
                    <div class="step-num">2</div>
                    <div class="step-text">Click the green <strong>Checkout</strong> button at the top right of the screen.</div>
                </div>
                <div class="guide-step">
                    <div class="step-num">3</div>
                    <div class="step-text">The payment page shows the full bill — all items, 15% tax automatically calculated, and the final total.</div>
                </div>
                <div class="guide-step">
                    <div class="step-num">4</div>
                    <div class="step-text">Choose the payment method:
                        <ul class="mt-1 mb-0" style="font-size:.88rem;">
                            <li><strong>Cash</strong> — Enter how much the customer gives, the system calculates the change</li>
                            <li><strong>Card</strong> — Customer taps/swipes their card, enter card reference</li>
                            <li><strong>Split</strong> — Group wants to split: enter number of people, set each person's share and method</li>
                        </ul>
                    </div>
                </div>
                <div class="guide-step">
                    <div class="step-num">5</div>
                    <div class="step-text">Click <strong>Confirm Payment</strong>. A receipt is shown. The table automatically becomes <strong>Available</strong> again.</div>
                </div>
                <div class="guide-tip">
                    <i class="fa fa-tag me-1"></i>
                    <strong>Discount:</strong> On the payment page, you can enter a discount amount before processing payment.
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($user['role'] === 'Manager'): ?>
    <!-- Reports Guide -->
    <div class="accordion-item border-0">
        <h2 class="accordion-header">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#g5">
                <i class="fa fa-chart-bar me-2 text-success"></i>
                How to View Daily Reports (Manager)
            </button>
        </h2>
        <div id="g5" class="accordion-collapse collapse" data-bs-parent="#guideAccordion">
            <div class="accordion-body">
                <div class="guide-step">
                    <div class="step-num">1</div>
                    <div class="step-text">Click <strong>Daily Reports</strong> from the sidebar or the Quick Action card above.</div>
                </div>
                <div class="guide-step">
                    <div class="step-num">2</div>
                    <div class="step-text">The report defaults to <strong>today's date</strong>. Use the date picker at the top to view any previous day.</div>
                </div>
                <div class="guide-step">
                    <div class="step-num">3</div>
                    <div class="step-text">You will see: <strong>Total Revenue, Number of Transactions, Tax Collected, Discounts Given</strong>.</div>
                </div>
                <div class="guide-step">
                    <div class="step-num">4</div>
                    <div class="step-text">Scroll down to see <strong>Payment Method Breakdown</strong> (how much was paid by Cash vs Card), <strong>Top Selling Items</strong>, and a full list of all transactions.</div>
                </div>
                <div class="guide-tip">
                    <i class="fa fa-print me-1"></i>
                    Use <strong>Ctrl+P</strong> (or right-click → Print) on the reports page to print the daily summary.
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

</div><!-- /accordion -->
</div><!-- /guide-card -->

<!-- Recent Orders -->
<div class="mb-2"><h5 class="mb-0 fw-bold"><i class="fa fa-clock me-2 text-muted"></i>Recent Orders</h5></div>
<div class="card fade-in">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <span class="fw-semibold">Latest Activity</span>
        <span class="badge bg-secondary"><?= date('D, d M Y') ?></span>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
            <thead class="table-light">
                <tr>
                    <th>Order</th><th>Table</th><th>Status</th>
                    <th>Staff</th><th class="text-end">Total</th><th>Time</th>
                    <?php if (in_array($user['role'],['Manager','Waiter'])): ?><th></th><?php endif; ?>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($recent)): ?>
                <tr><td colspan="7" class="text-center text-muted py-4">
                    <i class="fa fa-inbox fa-2x mb-2 d-block opacity-25"></i>No orders yet today
                </td></tr>
            <?php else: foreach ($recent as $o):
                $badge = match($o['order_status']) {
                    'Pending'         => 'bg-secondary',
                    'Sent to Kitchen' => 'bg-warning text-dark',
                    'In Progress'     => 'bg-info text-dark',
                    'Ready'           => 'bg-success',
                    'Paid'            => 'bg-dark',
                    default           => 'bg-light text-dark'
                };
            ?>
                <tr>
                    <td><strong class="text-muted">#<?= $o['order_id'] ?></strong></td>
                    <td><i class="fa fa-chair me-1 text-muted"></i>Table <?= $o['table_number'] ?></td>
                    <td><span class="badge <?= $badge ?> rounded-pill"><?= $o['order_status'] ?></span></td>
                    <td class="text-muted small">
                        <?= $o['source']==='Customer'
                            ? '<span class="badge bg-info text-white">Customer QR</span>'
                            : htmlspecialchars(($o['first_name']??'').($o['last_name']?' '.$o['last_name']:'')) ?>
                    </td>
                    <td class="text-end fw-semibold"><?= number_format((float)$o['total_amount'],2) ?> <span class="text-muted fw-normal small">ETB</span></td>
                    <td class="text-muted small"><?= date('H:i', strtotime($o['order_datetime'])) ?></td>
                    <?php if (in_array($user['role'],['Manager','Waiter'])): ?>
                    <td>
                        <?php if ($o['order_status'] !== 'Paid' && $o['order_status'] !== 'Cancelled'): ?>
                        <a href="<?= BASE_URL ?>pos.php?table=<?= $o['table_number'] ?>"
                           class="btn btn-sm btn-outline-primary">
                            <i class="fa fa-external-link-alt me-1"></i>Open
                        </a>
                        <?php endif; ?>
                    </td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
