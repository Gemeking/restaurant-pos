<?php
session_start();
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
requireRole(['Manager','Waiter']);

$page_title = 'POS Terminal — ' . APP_NAME;
$full_page  = true;

$stmt = $pdo->query("SELECT * FROM restaurant_tables ORDER BY table_number");
$tables = $stmt->fetchAll();

$preselect_table = (int)($_GET['table'] ?? 0);
$extra_js = '<script src="' . BASE_URL . 'assets/js/pos.js"></script>';
include __DIR__ . '/includes/header.php';
?>

<div class="pos-wrapper d-flex flex-column" style="height:calc(100vh - 50px);">

  <!-- ── TABLE SELECTION VIEW ── -->
  <div id="tableView" class="p-3 overflow-auto" <?= $preselect_table ? 'style="display:none"' : '' ?>>

    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-start mb-3">
      <div>
        <h4 class="fw-bold mb-1"><i class="fa fa-th me-2 text-danger"></i>Table Selection</h4>
        <p class="text-muted small mb-0">Click a table to open or start an order. Red = has active order, Green = available.</p>
      </div>
      <a href="<?= BASE_URL ?>dashboard.php" class="btn btn-sm btn-outline-secondary">
        <i class="fa fa-home me-1"></i>Home
      </a>
    </div>

    <!-- Tip -->
    <div class="tip-box mb-3">
      <i class="fa fa-info-circle"></i>
      <span><strong>Tip:</strong> Click any <span style="color:#27ae60;font-weight:600;">green table</span> to start a new order. Click a <span style="color:#e74c3c;font-weight:600;">red table</span> to continue an existing order.</span>
    </div>

    <!-- Table Grid -->
    <div class="row g-3" id="tableGrid">
      <?php foreach ($tables as $t):
        $cls = match($t['status']) {
          'Active'   => 'active',
          'Reserved' => 'reserved',
          default    => 'available',
        };
        $icon = match($t['status']) {
          'Active'   => 'fa-utensils',
          'Reserved' => 'fa-ban',
          default    => 'fa-check',
        };
      ?>
      <div class="col-6 col-sm-4 col-md-3 col-lg-2">
        <div class="table-btn card text-center py-3 px-2 shadow-sm <?= $cls ?>"
             data-table="<?= $t['table_number'] ?>"
             data-status="<?= $t['status'] ?>"
             data-capacity="<?= $t['capacity'] ?>">
          <i class="fa <?= $icon ?> fa-2x mb-2"></i>
          <div class="fw-bold fs-5">Table <?= $t['table_number'] ?></div>
          <div class="small opacity-75"><?= $t['capacity'] ?> seats</div>
          <div class="small fw-semibold mt-1"><?= $t['status'] ?></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Legend -->
    <div class="d-flex gap-3 mt-3 flex-wrap">
      <span class="d-flex align-items-center gap-1 small">
        <span style="width:14px;height:14px;background:#27ae60;border-radius:4px;display:inline-block;"></span> Available
      </span>
      <span class="d-flex align-items-center gap-1 small">
        <span style="width:14px;height:14px;background:#e74c3c;border-radius:4px;display:inline-block;"></span> Active Order
      </span>
      <span class="d-flex align-items-center gap-1 small">
        <span style="width:14px;height:14px;background:#f39c12;border-radius:4px;display:inline-block;"></span> Reserved
      </span>
    </div>
  </div>

  <!-- ── POS ORDER VIEW ── -->
  <div id="posView" style="display:none;flex:1;overflow:hidden;" class="d-flex flex-column">

    <!-- Order Top Bar -->
    <div style="background:#2c3e50;padding:.5rem 1rem;" class="d-flex align-items-center gap-2 flex-wrap">
      <button class="btn btn-sm btn-outline-light" id="btnBackTables">
        <i class="fa fa-arrow-left me-1"></i>Tables
      </button>
      <span class="text-white fw-bold" id="posTableLabel">Table —</span>
      <span class="badge bg-secondary" id="posOrderId"></span>
      <span class="badge" id="posOrderStatus"></span>
      <div class="ms-auto d-flex gap-2 flex-wrap">
        <button class="btn btn-warning btn-sm" id="btnSendKitchen" disabled>
          <i class="fa fa-fire me-1"></i>Send to Kitchen
        </button>
        <button class="btn btn-success btn-sm" id="btnCheckout" disabled>
          <i class="fa fa-credit-card me-1"></i>Checkout &amp; Pay
        </button>
      </div>
    </div>

    <!-- 3-Column Layout -->
    <div class="d-flex flex-grow-1 overflow-hidden">

      <!-- Col 1: Categories -->
      <div class="pos-col-categories overflow-auto" style="width:160px;min-width:130px;">
        <div class="px-1 pt-2 pb-1" style="font-size:.7rem;text-transform:uppercase;letter-spacing:.8px;color:#aaa;font-weight:600;">Categories</div>
        <div id="categoryList"></div>
      </div>

      <!-- Col 2: Menu Items -->
      <div class="flex-grow-1 p-2 overflow-auto" style="background:#f8f9fa;">
        <div style="font-size:.7rem;text-transform:uppercase;letter-spacing:.8px;color:#aaa;font-weight:600;margin-bottom:.5rem;" id="itemsHeader">
          ← Select a category
        </div>
        <div class="row g-2" id="menuItemGrid"></div>
      </div>

      <!-- Col 3: Order Ticket -->
      <div class="d-flex flex-column border-start bg-white" style="width:300px;min-width:260px;">
        <div style="background:#2c3e50;color:#fff;padding:.6rem 1rem;" class="d-flex justify-content-between align-items-center">
          <span class="fw-bold small"><i class="fa fa-receipt me-1"></i>Order Ticket</span>
          <button class="btn btn-outline-light btn-sm py-0 px-2" id="btnApplyDiscount" title="Apply Discount">
            <i class="fa fa-tag me-1"></i>Discount
          </button>
        </div>
        <div class="flex-grow-1 overflow-auto p-2" id="orderTicketItems">
          <p class="text-muted text-center mt-3 small">
            <i class="fa fa-arrow-left me-1"></i>Tap items from the menu to add them here
          </p>
        </div>
        <div class="border-top p-2 bg-light" style="font-size:.88rem;">
          <div class="d-flex justify-content-between mb-1 text-muted">
            <span>Subtotal</span><span id="ticketSubtotal">0.00 ETB</span>
          </div>
          <div class="d-flex justify-content-between mb-1 text-danger" id="discountRow" style="display:none!important;">
            <span>Discount</span><span id="ticketDiscount"></span>
          </div>
          <div class="d-flex justify-content-between mb-1 text-muted">
            <span>Tax (15%)</span><span id="ticketTax">0.00 ETB</span>
          </div>
          <div class="d-flex justify-content-between fw-bold pt-2 border-top" style="font-size:1rem;">
            <span>TOTAL</span><span id="ticketTotal" style="color:var(--primary);">0.00 ETB</span>
          </div>
          <div class="mt-2">
            <textarea id="orderNotes" class="form-control form-control-sm" rows="2"
                      placeholder="Order notes (e.g. birthday table, seat preference)..."></textarea>
            <button class="btn btn-sm btn-outline-secondary w-100 mt-1" id="btnSaveNotes">
              <i class="fa fa-save me-1"></i>Save Notes
            </button>
          </div>
        </div>
      </div>

    </div>
  </div>
</div>

<!-- Discount Modal -->
<div class="modal fade" id="discountModal" tabindex="-1">
  <div class="modal-dialog modal-sm">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title"><i class="fa fa-tag me-2"></i>Apply Discount</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <label class="form-label small fw-semibold">Discount Amount (ETB)</label>
        <input type="number" id="discountInput" class="form-control" min="0" step="0.01" placeholder="0.00">
        <div class="form-text">Enter the discount amount to subtract from the subtotal.</div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-primary btn-sm" id="btnApplyDiscountConfirm"><i class="fa fa-check me-1"></i>Apply</button>
      </div>
    </div>
  </div>
</div>

<!-- Item Notes Modal -->
<div class="modal fade" id="itemNotesModal" tabindex="-1">
  <div class="modal-dialog modal-sm">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="fa fa-comment-alt me-2"></i>Item Note / Modifier</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p class="fw-semibold mb-2" id="modalItemName"></p>
        <input type="text" id="modalItemNotes" class="form-control"
               placeholder="e.g. no onion, extra spicy, well done..." maxlength="255">
        <div class="form-text mt-1">This note will be shown to the kitchen staff.</div>
        <input type="hidden" id="modalOrderItemId">
      </div>
      <div class="modal-footer">
        <button class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-primary btn-sm" id="btnSaveItemNotes"><i class="fa fa-save me-1"></i>Save Note</button>
      </div>
    </div>
  </div>
</div>

<script>
const BASE_URL  = '<?= BASE_URL ?>';
const TAX_RATE  = <?= TAX_RATE ?>;
const PRESELECT = <?= $preselect_table ?: 0 ?>;
</script>
<?php include __DIR__ . '/includes/footer.php'; ?>
