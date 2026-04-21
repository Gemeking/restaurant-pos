/**
 * Kitchen Display System — kds.js
 * Polls the server every 3 seconds for updated kitchen orders.
 */

let kdsOrders    = {};   // {order_id: order_obj} — local cache
let pollInterval = null;

$(function () {
    loadKitchenOrders();
    pollInterval = setInterval(loadKitchenOrders, 3000);
});

// ══════════════════════════════════════════
//  Poll: Load Kitchen Orders
// ══════════════════════════════════════════
function loadKitchenOrders() {
    $.get(BASE_URL + 'api/kds.php', function (res) {
        if (!res.success) return;

        const orders = res.data;
        $('#kdsOrderCount').text(orders.length + ' order' + (orders.length !== 1 ? 's' : ''));
        $('#kdsLastUpdate').text('Updated ' + new Date().toLocaleTimeString());

        if (!orders.length) {
            if (!$('#kdsEmpty').is(':visible')) {
                $('#kdsGrid').html('<div class="col-12 text-center text-muted py-5" id="kdsEmpty"><i class="fa fa-coffee fa-3x mb-3 opacity-25"></i><p>No orders in queue</p></div>');
            }
            kdsOrders = {};
            return;
        }

        // Track which order IDs are still active
        const activeIds = new Set(orders.map(o => o.order_id));

        // Remove tickets for orders no longer in queue
        Object.keys(kdsOrders).forEach(function (oid) {
            if (!activeIds.has(parseInt(oid))) {
                $('#kdsTicket-' + oid).fadeOut(400, function () { $(this).remove(); });
                delete kdsOrders[oid];
            }
        });

        orders.forEach(function (order) {
            const oid = order.order_id;
            const existing = $('#kdsTicket-' + oid);

            if (existing.length) {
                // Update existing ticket (refresh content)
                updateTicket(order);
            } else {
                // New ticket — add with animation
                renderTicket(order);
                $('#kdsEmpty').remove();
            }
            kdsOrders[oid] = order;
        });

        // Update elapsed times
        updateElapsed();
    });
}

// ══════════════════════════════════════════
//  Render a New Ticket
// ══════════════════════════════════════════
function renderTicket(order) {
    const html = buildTicketHtml(order);
    const $col = $('<div class="col-12 col-sm-6 col-md-4 col-xl-3 kds-col"></div>').attr('id', 'kdsCol-' + order.order_id);
    $col.html(html).hide().appendTo('#kdsGrid').fadeIn(400);
}

function updateTicket(order) {
    const $col = $('#kdsCol-' + order.order_id);
    if (!$col.length) { renderTicket(order); return; }
    $col.html(buildTicketHtml(order));
}

function buildTicketHtml(order) {
    const statusClass = {
        'Sent to Kitchen': 'kds-ticket-new',
        'In Progress':     'kds-ticket-inprog',
        'Ready':           'kds-ticket-ready',
    }[order.order_status] || 'kds-ticket-new';

    const elapsed = getElapsed(order.order_datetime);
    const allReady = order.items.every(i => i.item_status === 'Ready');
    const hasInProg = order.items.some(i => i.item_status === 'In Progress');

    let itemsHtml = order.items.map(function (it) {
        const itCls = it.item_status === 'Ready'      ? 'kds-item-ready' :
                      it.item_status === 'In Progress' ? 'kds-item-inprog' : '';
        const itBtn = it.item_status !== 'Ready'
            ? `<button class="btn btn-xs" onclick="markItemStatus(${it.order_item_id}, '${it.item_status === 'Pending' ? 'In Progress' : 'Ready'}')">
                 ${it.item_status === 'Pending' ? '<i class="fa fa-fire"></i>' : '<i class="fa fa-check"></i>'}
               </button>`
            : '<span class="text-success small"><i class="fa fa-check-circle"></i></span>';
        return `
          <div class="kds-item-row ${itCls} d-flex justify-content-between align-items-start mb-1">
            <div>
              <span class="fw-bold">${it.quantity}×</span> ${escHtml(it.item_name)}
              ${it.item_notes ? `<div class="kds-item-note"><i class="fa fa-comment-alt me-1"></i>${escHtml(it.item_notes)}</div>` : ''}
            </div>
            ${itBtn}
          </div>`;
    }).join('');

    const actionBtns = allReady
        ? `<button class="btn btn-success w-100 btn-sm mt-2" onclick="bumpOrder(${order.order_id})">
             <i class="fa fa-check-double me-1"></i>Order Ready — Bump
           </button>`
        : !hasInProg
        ? `<button class="btn btn-warning w-100 btn-sm mt-2" onclick="markOrderInProgress(${order.order_id})">
             <i class="fa fa-fire me-1"></i>Start All Items
           </button>`
        : `<button class="btn btn-info w-100 btn-sm mt-2" onclick="markOrderReady(${order.order_id})">
             <i class="fa fa-check me-1"></i>Mark All Ready
           </button>`;

    const source = order.source === 'Customer' ? '<span class="badge bg-info ms-1">Customer</span>' : '';

    return `
      <div class="kds-ticket ${statusClass}" id="kdsTicket-${order.order_id}">
        <div class="kds-ticket-header d-flex justify-content-between align-items-center">
          <div>
            <span class="fw-bold fs-5">Table ${order.table_number}</span>${source}
            <div class="small opacity-75">Order #${order.order_id}</div>
          </div>
          <div class="text-end">
            <div class="kds-timer" data-time="${order.order_datetime}">${elapsed}</div>
            <div class="small opacity-75">${order.staff_name || 'Customer'}</div>
          </div>
        </div>
        <div class="kds-ticket-body">
          ${itemsHtml}
          ${order.order_notes ? `<div class="kds-order-note mt-2"><i class="fa fa-sticky-note me-1"></i>${escHtml(order.order_notes)}</div>` : ''}
        </div>
        <div class="kds-ticket-footer">
          ${actionBtns}
        </div>
      </div>`;
}

// ══════════════════════════════════════════
//  Status Updates
// ══════════════════════════════════════════
function markItemStatus(orderItemId, newStatus) {
    $.ajax({
        url:         BASE_URL + 'api/kds.php',
        type:        'PUT',
        contentType: 'application/json',
        data:        JSON.stringify({ order_item_id: orderItemId, item_status: newStatus }),
        success: loadKitchenOrders
    });
}

function markOrderInProgress(orderId) {
    $.ajax({
        url:         BASE_URL + 'api/kds.php',
        type:        'PUT',
        contentType: 'application/json',
        data:        JSON.stringify({ order_id: orderId, order_status: 'In Progress' }),
        success: loadKitchenOrders
    });
}

function markOrderReady(orderId) {
    $.ajax({
        url:         BASE_URL + 'api/kds.php',
        type:        'PUT',
        contentType: 'application/json',
        data:        JSON.stringify({ order_id: orderId, order_status: 'Ready' }),
        success: loadKitchenOrders
    });
}

function bumpOrder(orderId) {
    // Mark as Ready then remove from KDS view
    $.ajax({
        url:         BASE_URL + 'api/kds.php',
        type:        'PUT',
        contentType: 'application/json',
        data:        JSON.stringify({ order_id: orderId, order_status: 'Ready' }),
        success: function () {
            $('#kdsCol-' + orderId).fadeOut(600, function () {
                $(this).remove();
                delete kdsOrders[orderId];
                if (!Object.keys(kdsOrders).length) {
                    $('#kdsGrid').html('<div class="col-12 text-center text-muted py-5" id="kdsEmpty"><i class="fa fa-coffee fa-3x mb-3 opacity-25"></i><p>No orders in queue</p></div>');
                    $('#kdsOrderCount').text('0 orders');
                }
            });
        }
    });
}

// ══════════════════════════════════════════
//  Elapsed Time
// ══════════════════════════════════════════
function getElapsed(datetimeStr) {
    const orderTime = new Date(datetimeStr.replace(' ', 'T'));
    const now       = new Date();
    const diffSec   = Math.floor((now - orderTime) / 1000);
    if (diffSec < 60)  return diffSec + 's';
    if (diffSec < 3600) return Math.floor(diffSec / 60) + 'm ' + (diffSec % 60) + 's';
    return Math.floor(diffSec / 3600) + 'h ' + Math.floor((diffSec % 3600) / 60) + 'm';
}

function updateElapsed() {
    $('.kds-timer').each(function () {
        $(this).text(getElapsed($(this).data('time')));
    });
}

// ══════════════════════════════════════════
//  Util
// ══════════════════════════════════════════
function escHtml(s) { return $('<div>').text(s || '').html(); }
