<?php
session_start();
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
requireRole(['Manager','Kitchen','Waiter']);

$page_title = 'Kitchen Display — ' . APP_NAME;
$full_page  = true;
$extra_js   = '<script src="' . BASE_URL . 'assets/js/kds.js"></script>';
include __DIR__ . '/includes/header.php';
?>

<div class="kds-page">

  <!-- KDS Header Bar -->
  <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <div>
      <h4 class="text-white fw-bold mb-0">
        <i class="fa fa-fire text-danger me-2"></i>Kitchen Display System
      </h4>
      <div class="text-muted small mt-1">
        Orders update automatically every 3 seconds — no need to refresh
        <span class="kds-live-dot ms-1"></span>
      </div>
    </div>
    <div class="d-flex align-items-center gap-3">
      <span id="kdsOrderCount" class="badge bg-secondary fs-6 px-3 py-2">0 orders</span>
      <span class="text-muted small" id="kdsLastUpdate"></span>
    </div>
  </div>

  <!-- Legend / Guide -->
  <div class="d-flex gap-2 mb-3 flex-wrap">
    <span class="badge py-2 px-3" style="background:#e74c3c;">
      <i class="fa fa-bell me-1"></i>New Order — tap "Start All Items"
    </span>
    <span class="badge py-2 px-3" style="background:#27ae60;">
      <i class="fa fa-fire me-1"></i>In Progress — cooking now
    </span>
    <span class="badge py-2 px-3" style="background:#2ecc71;">
      <i class="fa fa-check me-1"></i>Ready — tap "Bump" to remove
    </span>
    <span class="badge bg-secondary py-2 px-3">
      <i class="fa fa-clock me-1"></i>Timer shows time since order
    </span>
  </div>

  <!-- Order Tickets -->
  <div class="row g-3" id="kdsGrid">
    <div class="col-12 text-center py-5" id="kdsEmpty">
      <i class="fa fa-mug-hot text-muted" style="font-size:4rem;opacity:.15;"></i>
      <p class="text-muted mt-3">No orders in queue right now</p>
      <p class="text-muted small">New orders will appear here automatically</p>
    </div>
  </div>

</div>

<script>const BASE_URL = '<?= BASE_URL ?>';</script>
<?php include __DIR__ . '/includes/footer.php'; ?>
