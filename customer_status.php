<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';

$order_id = (int)($_GET['order_id'] ?? 0);
if (!$order_id) { header('Location: ' . BASE_URL); exit; }

$stmt = $pdo->prepare("SELECT table_number, order_status FROM orders WHERE order_id=?");
$stmt->execute([$order_id]);
$order_row = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$order_row) { header('Location: ' . BASE_URL); exit; }

$table_number = (int)$order_row['table_number'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Status — <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <style>
        * { box-sizing: border-box; }
        body {
            background: #f0f4f8;
            font-family: 'Segoe UI', system-ui, sans-serif;
            min-height: 100vh;
            margin: 0;
        }

        /* ── Header ─────────────────────────────── */
        .status-header {
            background: linear-gradient(135deg, #1e272e 0%, #c0392b 100%);
            color: white;
            padding: 1.4rem 1rem 1.6rem;
            text-align: center;
        }
        .status-header .restaurant-icon {
            font-size: 2.4rem;
            margin-bottom: 0.3rem;
            display: block;
        }
        .status-header h1 {
            font-size: 1.25rem;
            font-weight: 800;
            margin: 0 0 0.25rem;
            letter-spacing: 0.5px;
        }
        .status-header .meta {
            font-size: 0.85rem;
            opacity: 0.85;
        }
        .order-chip {
            display: inline-block;
            background: rgba(255,255,255,0.18);
            border: 1px solid rgba(255,255,255,0.35);
            border-radius: 20px;
            padding: 2px 14px;
            font-weight: 700;
            font-size: 0.8rem;
            margin-top: 4px;
        }

        /* ── Stepper ─────────────────────────────── */
        .stepper-card {
            background: white;
            border-radius: 0 0 16px 16px;
            box-shadow: 0 4px 16px rgba(0,0,0,0.08);
            padding: 1.6rem 1.2rem 1.2rem;
            margin-bottom: 1rem;
        }
        .stepper {
            display: flex;
            align-items: flex-start;
            justify-content: center;
        }
        .step {
            display: flex;
            flex-direction: column;
            align-items: center;
            flex: 1;
            text-align: center;
        }
        .step-circle {
            width: 54px;
            height: 54px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            border: 3px solid #dee2e6;
            background: #f8f9fa;
            color: #adb5bd;
            transition: all 0.4s ease;
        }
        .step-label {
            font-size: 0.7rem;
            font-weight: 600;
            color: #adb5bd;
            margin-top: 0.45rem;
            line-height: 1.3;
            transition: color 0.4s ease;
        }
        .step-connector {
            flex: 1;
            height: 3px;
            background: #dee2e6;
            margin-top: 26px; /* center on circles */
            transition: background 0.5s ease;
            border-radius: 2px;
        }

        /* Done */
        .step.done .step-circle {
            background: #27ae60;
            border-color: #27ae60;
            color: white;
        }
        .step.done .step-label { color: #27ae60; }
        .step-connector.done { background: #27ae60; }

        /* Active */
        .step.active .step-circle {
            background: #e74c3c;
            border-color: #e74c3c;
            color: white;
            animation: step-pulse 1.6s infinite;
        }
        .step.active .step-label { color: #e74c3c; font-weight: 700; }
        .step-connector.half { background: linear-gradient(to right, #27ae60 50%, #dee2e6 50%); }

        @keyframes step-pulse {
            0%   { box-shadow: 0 0 0 0   rgba(231,76,60, 0.55); }
            70%  { box-shadow: 0 0 0 12px rgba(231,76,60, 0); }
            100% { box-shadow: 0 0 0 0   rgba(231,76,60, 0); }
        }

        /* ── Banners ──────────────────────────────── */
        .banner {
            border-radius: 14px;
            padding: 1.4rem 1rem;
            text-align: center;
            margin-bottom: 1rem;
            color: white;
        }
        .banner .banner-icon { font-size: 2.8rem; margin-bottom: 0.35rem; display: block; }
        .banner h3 { font-weight: 800; margin: 0 0 0.2rem; font-size: 1.3rem; }
        .banner p  { margin: 0; opacity: 0.9; font-size: 0.9rem; }

        .banner-ready {
            background: linear-gradient(135deg, #27ae60, #2ecc71);
            box-shadow: 0 6px 24px rgba(39,174,96, 0.35);
            animation: banner-pop 0.5s cubic-bezier(0.34,1.56,0.64,1);
        }
        .banner-paid {
            background: linear-gradient(135deg, #8e44ad, #9b59b6);
            box-shadow: 0 6px 24px rgba(142,68,173, 0.3);
        }

        @keyframes banner-pop {
            from { transform: scale(0.75); opacity: 0; }
            to   { transform: scale(1);   opacity: 1; }
        }

        /* ── Live indicator ──────────────────────── */
        .live-dot {
            display: inline-block;
            width: 9px;
            height: 9px;
            background: #27ae60;
            border-radius: 50%;
            animation: blink 2s infinite;
            margin-right: 5px;
            vertical-align: middle;
        }
        @keyframes blink {
            0%,100% { opacity: 1; }
            50%      { opacity: 0.2; }
        }

        /* ── Item cards ───────────────────────────── */
        .item-card {
            background: white;
            border-radius: 12px;
            padding: 0.85rem 1rem;
            margin-bottom: 0.65rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            border-left: 5px solid #dee2e6;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: border-color 0.35s ease;
        }
        .item-card.s-pending     { border-left-color: #adb5bd; }
        .item-card.s-inprogress  { border-left-color: #f39c12; }
        .item-card.s-ready       { border-left-color: #27ae60; }

        .item-name { font-weight: 700; font-size: 0.95rem; color: #1e272e; }
        .item-meta { font-size: 0.78rem; color: #888; margin-top: 2px; }

        .badge-inprogress { animation: badge-breathe 1.3s infinite alternate; }
        @keyframes badge-breathe {
            from { opacity: 0.65; }
            to   { opacity: 1; }
        }

        /* ── Misc ────────────────────────────────── */
        .section-label {
            font-size: 0.78rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: #888;
            margin-bottom: 0.5rem;
        }
    </style>
</head>
<body>

<!-- ── Header ───────────────────────── -->
<div class="status-header">
    <span class="restaurant-icon">🍽️</span>
    <h1><?= htmlspecialchars(APP_NAME) ?></h1>
    <div class="meta">
        Table <strong><?= $table_number ?></strong>
        &nbsp;·&nbsp;
        <span class="order-chip">Order #<?= $order_id ?></span>
    </div>
</div>

<div style="max-width:520px;margin:0 auto;padding:0 0.75rem 3rem;">

    <!-- ── Stepper ──────────────────── -->
    <div class="stepper-card">
        <div class="stepper">
            <div class="step" id="step1">
                <div class="step-circle"><i class="fa fa-receipt"></i></div>
                <div class="step-label">Order<br>Received</div>
            </div>
            <div class="step-connector" id="conn1"></div>
            <div class="step" id="step2">
                <div class="step-circle"><i class="fa fa-fire-flame-curved"></i></div>
                <div class="step-label">In the<br>Kitchen</div>
            </div>
            <div class="step-connector" id="conn2"></div>
            <div class="step" id="step3">
                <div class="step-circle"><i class="fa fa-bell-concierge"></i></div>
                <div class="step-label">Ready<br>to Serve!</div>
            </div>
        </div>
    </div>

    <!-- ── Ready banner (hidden until ready) ── -->
    <div class="banner banner-ready d-none" id="bannerReady">
        <span class="banner-icon">🎉</span>
        <h3>Your Food is Ready!</h3>
        <p>A waiter will bring it to your table shortly.</p>
    </div>

    <!-- ── Paid banner ──────────────────── -->
    <div class="banner banner-paid d-none" id="bannerPaid">
        <span class="banner-icon">😊</span>
        <h3>Enjoy Your Meal!</h3>
        <p>Thank you for dining with us — come back soon!</p>
    </div>

    <!-- ── Live indicator ─────────────── -->
    <div class="text-muted small mb-3">
        <span class="live-dot"></span>Live updates · checking every 3 seconds
    </div>

    <!-- ── Item list ──────────────────── -->
    <div class="section-label"><i class="fa fa-list-ul me-1"></i>Your Items</div>
    <div id="itemList">
        <div class="text-center py-4 text-muted">
            <i class="fa fa-spinner fa-spin fa-lg me-2"></i>Loading your order...
        </div>
    </div>

    <!-- ── Add more items ─────────────── -->
    <div class="text-center mt-4">
        <a id="addMoreLink"
           href="<?= BASE_URL ?>customer_menu.php?table=<?= $table_number ?>"
           class="btn btn-outline-secondary btn-sm rounded-pill px-4">
            <i class="fa fa-plus me-1"></i>Add More Items
        </a>
    </div>

</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
const BASE_URL = '<?= BASE_URL ?>';
const ORDER_ID = <?= $order_id ?>;
let pollTimer   = null;
let lastStatus  = null;

function pollStatus() {
    $.get(BASE_URL + 'api/customer_status.php?order_id=' + ORDER_ID, function (res) {
        if (!res.success) return;
        updateUI(res);
    });
}

function updateUI(data) {
    const status = data.order_status;
    const items  = data.items;

    // ── Stepper ──────────────────────────────────────
    const $s1 = $('#step1'), $s2 = $('#step2'), $s3 = $('#step3');
    const $c1 = $('#conn1'), $c2 = $('#conn2');

    $s1.add($s2).add($s3).removeClass('done active');
    $c1.add($c2).removeClass('done half');

    if (status === 'Open' || status === 'Sent to Kitchen') {
        // Step 1 done, waiting in kitchen queue
        $s1.addClass('done');
        $s2.addClass('active');
        $c1.addClass('half');

    } else if (status === 'In Progress') {
        // Actively cooking
        $s1.addClass('done'); $c1.addClass('done');
        $s2.addClass('active');

    } else if (status === 'Ready') {
        // All done
        $s1.addClass('done'); $c1.addClass('done');
        $s2.addClass('done'); $c2.addClass('done');
        $s3.addClass('done');
        if (lastStatus !== 'Ready') {
            $('#bannerReady').removeClass('d-none');
        }
        stopPolling();

    } else if (status === 'Paid') {
        $s1.addClass('done'); $c1.addClass('done');
        $s2.addClass('done'); $c2.addClass('done');
        $s3.addClass('done');
        $('#bannerReady').addClass('d-none');
        $('#bannerPaid').removeClass('d-none');
        stopPolling();
    }

    lastStatus = status;

    // ── Item cards ────────────────────────────────────
    const $list = $('#itemList').empty();

    if (!items || items.length === 0) {
        $list.html('<p class="text-muted text-center small py-2">No items found.</p>');
        return;
    }

    items.forEach(function (item) {
        const cls   = { 'Pending': 's-pending', 'In Progress': 's-inprogress', 'Ready': 's-ready' }[item.item_status] || 's-pending';
        const badge = getItemBadge(item.item_status);
        const notes = item.notes ? '<span class="fst-italic">' + escHtml(item.notes) + '</span>' : '';
        $list.append(
            '<div class="item-card ' + cls + '">' +
                '<div>' +
                    '<div class="item-name">' + escHtml(item.item_name) + '</div>' +
                    '<div class="item-meta">Qty: ' + item.quantity + (notes ? ' &nbsp;·&nbsp; ' + notes : '') + '</div>' +
                '</div>' +
                '<div>' + badge + '</div>' +
            '</div>'
        );
    });
}

function getItemBadge(status) {
    switch (status) {
        case 'Pending':
            return '<span class="badge bg-secondary px-2 py-2"><i class="fa fa-clock me-1"></i>Waiting</span>';
        case 'In Progress':
            return '<span class="badge bg-warning text-dark px-2 py-2 badge-inprogress"><i class="fa fa-fire-flame-curved me-1"></i>In Kitchen</span>';
        case 'Ready':
            return '<span class="badge bg-success px-2 py-2"><i class="fa fa-check me-1"></i>Ready ✓</span>';
        default:
            return '<span class="badge bg-secondary">' + escHtml(status) + '</span>';
    }
}

function stopPolling() {
    if (pollTimer) { clearInterval(pollTimer); pollTimer = null; }
    // Hide live dot when done
    $('.live-dot').css({ background: '#adb5bd', animation: 'none' });
    $('.text-muted.small.mb-3').html('<i class="fa fa-check-circle text-success me-1"></i>Order complete — no more updates needed.');
}

function escHtml(s) { return $('<div>').text(s || '').html(); }

// ── Start ───────────────────────────────────────────
pollStatus();
pollTimer = setInterval(pollStatus, 3000);
</script>
</body>
</html>
