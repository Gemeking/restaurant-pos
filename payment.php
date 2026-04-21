<?php
session_start();
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
requireRole(['Manager','Waiter']);

$order_id = (int)($_GET['order_id'] ?? 0);
if (!$order_id) { header('Location: ' . BASE_URL . 'pos.php'); exit; }

$stmt = $pdo->prepare("SELECT * FROM orders WHERE order_id=?");
$stmt->execute([$order_id]);
$order = $stmt->fetch();
if (!$order || $order['order_status'] === 'Paid') {
    header('Location: ' . BASE_URL . 'pos.php'); exit;
}

$stmt = $pdo->prepare("
    SELECT oi.*, m.name AS item_name
    FROM order_items oi JOIN menu_items m ON m.menu_item_id=oi.menu_item_id
    WHERE oi.order_id=? ORDER BY oi.order_item_id
");
$stmt->execute([$order_id]);
$items = $stmt->fetchAll();

$page_title = 'Payment — Order #' . $order_id;
$page_name  = 'Payment';
$extra_js   = '<script src="' . BASE_URL . 'assets/js/payment.js"></script>';
include __DIR__ . '/includes/header.php';
?>

<!-- Page Header -->
<div class="page-header">
    <div class="page-header-text">
        <h4><i class="fa fa-credit-card me-2 text-success"></i>Process Payment</h4>
        <p>Order <strong>#<?= $order_id ?></strong> — Table <?= $order['table_number'] ?> — <?= $order['order_status'] ?></p>
    </div>
    <a href="<?= BASE_URL ?>pos.php?table=<?= $order['table_number'] ?>" class="btn btn-outline-secondary btn-sm">
        <i class="fa fa-arrow-left me-1"></i>Back to Order
    </a>
</div>

<div class="tip-box mb-3">
    <i class="fa fa-info-circle"></i>
    <span>Review the order below, apply any discount if needed, then select a payment method and confirm.</span>
</div>

<div class="row g-4">

    <!-- Order Summary -->
    <div class="col-lg-6">
        <div class="card shadow-sm">
            <div class="card-header"><i class="fa fa-receipt me-2"></i>Order Summary — Table <?= $order['table_number'] ?></div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead class="table-light">
                        <tr><th>Item</th><th class="text-muted small">Notes</th><th class="text-center">Qty</th><th class="text-end">Amount</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($items as $it): ?>
                    <tr>
                        <td class="fw-semibold"><?= htmlspecialchars($it['item_name']) ?></td>
                        <td class="text-muted small"><?= htmlspecialchars($it['notes'] ?? '') ?></td>
                        <td class="text-center"><?= $it['quantity'] ?></td>
                        <td class="text-end"><?= number_format($it['quantity'] * $it['price_at_sale'], 2) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="card-footer bg-light">
                <div class="row align-items-end">
                    <div class="col-6">
                        <label class="form-label small fw-semibold">Discount (ETB)</label>
                        <input type="number" id="discountAmt" class="form-control form-control-sm"
                               value="<?= $order['discount_amount'] ?>" min="0" step="0.01">
                        <div class="form-text small">Optional discount to subtract</div>
                    </div>
                    <div class="col-6 text-end">
                        <div class="small text-muted">Subtotal</div>
                        <div id="paySubtotal" class="fw-semibold"><?= number_format($order['subtotal'],2) ?> ETB</div>
                        <div class="small text-muted mt-1">Discount</div>
                        <div id="payDiscount" class="fw-semibold text-danger">0.00 ETB</div>
                        <div class="small text-muted mt-1">Tax (15%)</div>
                        <div id="payTax" class="fw-semibold"><?= number_format($order['tax_amount'],2) ?> ETB</div>
                        <hr class="my-1">
                        <div class="text-muted small">TOTAL DUE</div>
                        <div id="payTotal" class="fw-bold" style="font-size:1.4rem;color:var(--primary);"><?= number_format($order['total_amount'],2) ?> ETB</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Payment Methods -->
    <div class="col-lg-6">
        <div class="card shadow-sm">
            <div class="card-header bg-success text-white"><i class="fa fa-cash-register me-2"></i>Choose Payment Method</div>
            <div class="card-body">

                <ul class="nav nav-tabs mb-3">
                    <li class="nav-item">
                        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#cashTab">
                            <i class="fa fa-money-bill me-1"></i>Cash
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#cardTab">
                            <i class="fa fa-credit-card me-1"></i>Card
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#splitTab">
                            <i class="fa fa-users me-1"></i>Split Bill
                        </button>
                    </li>
                </ul>

                <div class="tab-content">

                    <!-- Cash Tab -->
                    <div class="tab-pane fade show active" id="cashTab">
                        <div class="tip-box mb-3">
                            <i class="fa fa-money-bill"></i>
                            <span>Enter the amount the customer gives you. The system will show the change.</span>
                        </div>
                        <label class="form-label fw-semibold">Amount Received (ETB)</label>
                        <input type="number" id="cashReceived" class="form-control form-control-lg mb-2" min="0" step="0.01" placeholder="0.00">
                        <div class="alert alert-success d-none py-2" id="changeRow">
                            <i class="fa fa-coins me-2"></i>Give customer change: <strong id="changeAmt">0.00</strong> ETB
                        </div>
                        <div class="d-flex gap-2 flex-wrap mb-3">
                            <span class="text-muted small">Quick amounts:</span>
                            <button class="btn btn-outline-secondary btn-sm quick-cash" data-mult="1">Exact</button>
                            <button class="btn btn-outline-secondary btn-sm quick-cash" data-val="100">100</button>
                            <button class="btn btn-outline-secondary btn-sm quick-cash" data-val="200">200</button>
                            <button class="btn btn-outline-secondary btn-sm quick-cash" data-val="500">500</button>
                            <button class="btn btn-outline-secondary btn-sm quick-cash" data-val="1000">1000</button>
                        </div>
                        <button class="btn btn-success btn-lg w-100 fw-bold" id="btnPayCash">
                            <i class="fa fa-check me-2"></i>Confirm Cash Payment
                        </button>
                    </div>

                    <!-- Card Tab -->
                    <div class="tab-pane fade" id="cardTab">
                        <div class="tip-box mb-3">
                            <i class="fa fa-credit-card"></i>
                            <span>Ask the customer to tap or swipe their card on the terminal, then confirm below.</span>
                        </div>
                        <div class="text-center py-3">
                            <i class="fa fa-credit-card fa-4x text-primary mb-3 d-block"></i>
                            <h5>Total to charge</h5>
                            <div class="fw-bold mb-3" style="font-size:2rem;color:var(--primary);">
                                <span id="cardAmount">0.00</span> ETB
                            </div>
                        </div>
                        <input type="text" id="cardReference" class="form-control mb-3"
                               placeholder="Card reference or last 4 digits (optional)">
                        <button class="btn btn-primary btn-lg w-100 fw-bold" id="btnPayCard">
                            <i class="fa fa-check me-2"></i>Confirm Card Payment
                        </button>
                    </div>

                    <!-- Split Tab -->
                    <div class="tab-pane fade" id="splitTab">
                        <div class="tip-box mb-3">
                            <i class="fa fa-users"></i>
                            <span>For groups splitting the bill. Enter the number of people, then set each person's share.</span>
                        </div>
                        <div class="d-flex gap-2 align-items-end mb-3">
                            <div class="flex-grow-1">
                                <label class="form-label fw-semibold small">Number of people</label>
                                <input type="number" id="splitCount" class="form-control" value="2" min="2" max="10">
                            </div>
                            <button class="btn btn-outline-primary" id="btnGenSplits">
                                <i class="fa fa-calculator me-1"></i>Split
                            </button>
                        </div>
                        <div id="splitFields"></div>
                        <div class="alert alert-warning d-none small mt-2" id="splitWarning">
                            <i class="fa fa-exclamation-triangle me-1"></i>Split amounts must add up to the total.
                        </div>
                        <button class="btn btn-warning btn-lg w-100 fw-bold mt-2" id="btnPaySplit">
                            <i class="fa fa-check me-2"></i>Confirm Split Payment
                        </button>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

<!-- Receipt Modal -->
<div class="modal fade" id="receiptModal" tabindex="-1" data-bs-backdrop="static">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header bg-success text-white">
        <h5 class="modal-title"><i class="fa fa-check-circle me-2"></i>Payment Successful!</h5>
      </div>
      <div class="modal-body" id="receiptBody"></div>
      <div class="modal-footer">
        <button class="btn btn-outline-secondary" onclick="window.print()"><i class="fa fa-print me-1"></i>Print Receipt</button>
        <a href="<?= BASE_URL ?>pos.php" class="btn btn-primary"><i class="fa fa-th me-1"></i>Back to Tables</a>
      </div>
    </div>
  </div>
</div>

<script>
const BASE_URL = '<?= BASE_URL ?>';
const ORDER_ID = <?= $order_id ?>;
const TAX_RATE = <?= TAX_RATE ?>;
const SUBTOTAL = <?= $order['subtotal'] ?>;
</script>
<?php include __DIR__ . '/includes/footer.php'; ?>
