<?php
require_once __DIR__ . '/config/config.php';
// Public page - no auth required
$table_number = (int)($_GET['table'] ?? 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Menu — <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>assets/css/style.css" rel="stylesheet">
    <style>
        body { background:#f5f5f5; }
        .customer-header { background:linear-gradient(135deg,#e94560,#0f3460); color:#fff; padding:1.2rem; text-align:center; position:sticky; top:0; z-index:100; }
        .cat-nav { background:#fff; border-bottom:1px solid #eee; padding:.5rem 1rem; overflow-x:auto; white-space:nowrap; position:sticky; top:72px; z-index:99; }
        .cat-nav .btn { margin-right:.4rem; }
        .menu-item-card { background:#fff; border-radius:12px; padding:1rem; margin-bottom:.8rem; box-shadow:0 2px 8px rgba(0,0,0,.07); }
        .menu-item-card .price { color:#e94560; font-weight:700; font-size:1.1rem; }
        .cart-bar { position:fixed; bottom:0; left:0; right:0; background:#0f3460; color:#fff; padding:.8rem 1.2rem; z-index:200; display:none; }
        #orderSuccess { display:none; }
    </style>
</head>
<body>

<div class="customer-header">
    <h4 class="mb-0 fw-bold"><i class="fa fa-utensils me-2"></i><?= APP_NAME ?></h4>
    <div class="small opacity-75">
        <?php if ($table_number): ?>
            Table <?= $table_number ?>
        <?php else: ?>
            Please scan your table QR code
        <?php endif; ?>
    </div>
</div>

<?php if (!$table_number): ?>
<div class="container py-5 text-center">
    <i class="fa fa-qrcode fa-5x text-muted mb-3"></i>
    <h5 class="text-muted">No table selected</h5>
    <p class="text-muted">Please scan the QR code on your table to view the menu.</p>
    <div class="mt-3">
        <label class="form-label">Or enter table number manually:</label>
        <div class="d-flex gap-2 justify-content-center">
            <input type="number" id="manualTable" class="form-control w-auto" min="1" max="99" placeholder="Table #">
            <button class="btn btn-primary" onclick="goTable()">Go</button>
        </div>
    </div>
</div>
<script>
function goTable() {
    const t = document.getElementById('manualTable').value;
    if (t) window.location.href = '?table=' + t;
}
</script>
<?php else: ?>

<!-- Category Nav -->
<div class="cat-nav" id="catNav">
    <div class="spinner-border spinner-border-sm text-secondary" role="status"></div>
</div>

<!-- Menu Content -->
<div class="container py-3 pb-5" id="menuContent">
    <div class="text-center py-5"><div class="spinner-border text-primary"></div></div>
</div>

<!-- Order Success -->
<div class="container py-5" id="orderSuccess">
    <div class="text-center">
        <i class="fa fa-check-circle fa-5x text-success mb-3"></i>
        <h4>Order Sent!</h4>
        <p class="text-muted">Your order has been sent to the kitchen. A staff member will serve you shortly.</p>
        <button class="btn btn-primary mt-2" onclick="resetOrder()">Order More</button>
    </div>
</div>

<!-- Cart Bar -->
<div class="cart-bar" id="cartBar">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <span id="cartCount" class="badge bg-warning text-dark me-2"></span>
            <span id="cartTotal" class="fw-bold fs-5"></span>
            <span class="small opacity-75 ms-1">ETB</span>
        </div>
        <button class="btn btn-warning fw-bold" id="btnViewCart" data-bs-toggle="modal" data-bs-target="#cartModal">
            <i class="fa fa-shopping-cart me-1"></i>Review Order
        </button>
    </div>
</div>

<!-- Cart Modal -->
<div class="modal fade" id="cartModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header bg-dark text-white">
        <h5 class="modal-title"><i class="fa fa-receipt me-2"></i>Your Order — Table <?= $table_number ?></h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="cartModalBody"></div>
      <div class="modal-footer flex-column">
        <div class="d-flex justify-content-between w-100 mb-2">
          <span>Subtotal</span><span id="cartModalSubtotal" class="fw-bold"></span>
        </div>
        <div class="d-flex justify-content-between w-100 mb-3">
          <span>Tax (15%)</span><span id="cartModalTax"></span>
        </div>
        <div class="d-flex justify-content-between w-100 mb-3 fw-bold fs-6 border-top pt-2">
          <span>TOTAL</span><span id="cartModalTotal" class="text-danger"></span>
        </div>
        <div class="mb-3 w-100">
          <label class="form-label small">Special Instructions (optional)</label>
          <textarea id="customerNotes" class="form-control form-control-sm" rows="2" placeholder="Allergies, preferences..."></textarea>
        </div>
        <button class="btn btn-danger btn-lg w-100 fw-bold" id="btnPlaceOrder">
          <i class="fa fa-paper-plane me-2"></i>Place Order
        </button>
      </div>
    </div>
  </div>
</div>

<script>
const BASE_URL     = '<?= BASE_URL ?>';
const TABLE_NUMBER = <?= $table_number ?>;
const TAX_RATE     = <?= TAX_RATE ?>;
</script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= BASE_URL ?>assets/js/customer.js"></script>
<?php endif; ?>
</body>
</html>
