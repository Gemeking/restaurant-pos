/**
 * POS Terminal — pos.js
 * Handles table selection, order management, and kitchen dispatch.
 */

let currentOrderId   = null;
let currentTable     = null;
let currentOrder     = null;   // full order object from server
let menuData         = [];     // array of categories with items
let selectedCategory = null;

// ══════════════════════════════════════════
//  Boot
// ══════════════════════════════════════════
$(function () {
    loadMenu();
    wireTableButtons();

    // Auto-open table from URL param
    if (typeof PRESELECT !== 'undefined' && PRESELECT > 0) {
        openTable(PRESELECT);
    }

    // Back to tables
    $('#btnBackTables').on('click', function () {
        showTableView();
        refreshTables();
    });

    // Send to Kitchen
    $('#btnSendKitchen').on('click', sendToKitchen);

    // Checkout
    $('#btnCheckout').on('click', function () {
        if (!currentOrderId) return;
        window.location.href = BASE_URL + 'payment.php?order_id=' + currentOrderId;
    });

    // Discount
    $('#btnApplyDiscount').on('click', function () {
        $('#discountInput').val(currentOrder ? currentOrder.discount_amount : 0);
        new bootstrap.Modal('#discountModal').show();
    });
    $('#btnApplyDiscountConfirm').on('click', applyDiscount);

    // Save notes
    $('#btnSaveNotes').on('click', saveOrderNotes);

    // Item notes save
    $('#btnSaveItemNotes').on('click', saveItemNotes);

    // Cash input listener in order notes
});

// ══════════════════════════════════════════
//  Menu Load
// ══════════════════════════════════════════
function loadMenu() {
    $.get(BASE_URL + 'api/menu.php', function (res) {
        if (res.success) {
            menuData = res.data;
            renderCategories();
        }
    });
}

function renderCategories() {
    const $list = $('#categoryList').empty();
    menuData.forEach(function (cat) {
        const $btn = $('<button>')
            .addClass('btn btn-outline-secondary w-100 mb-1 text-start cat-btn')
            .text(cat.category_name)
            .data('cat', cat)
            .on('click', function () {
                selectedCategory = cat;
                $('.cat-btn').removeClass('active btn-dark').addClass('btn-outline-secondary');
                $(this).removeClass('btn-outline-secondary').addClass('btn-dark active');
                renderMenuItems(cat);
            });
        $list.append($btn);
    });
}

function renderMenuItems(cat) {
    const $grid = $('#menuItemGrid').empty();
    $('#itemsHeader').text(cat.category_name);
    const avail = cat.items.filter(i => i.is_available);
    if (!avail.length) {
        $grid.html('<p class="text-muted col-12 text-center mt-3">No available items</p>');
        return;
    }
    avail.forEach(function (item) {
        const $card = $(`
          <div class="col-6 col-md-4 col-xl-3">
            <div class="card border menu-item-btn h-100 shadow-sm" style="cursor:pointer;" data-id="${item.menu_item_id}" data-name="${escHtml(item.name)}" data-price="${item.base_price}">
              <div class="card-body p-2 text-center d-flex flex-column justify-content-between">
                <div class="fw-semibold small">${escHtml(item.name)}</div>
                <div class="text-success fw-bold mt-1">${numFmt(item.base_price)} ETB</div>
              </div>
            </div>
          </div>
        `);
        $card.on('click', function () {
            addItemToOrder(item.menu_item_id, item.name, item.base_price);
        });
        $grid.append($card);
    });
}

// ══════════════════════════════════════════
//  Table View
// ══════════════════════════════════════════
function wireTableButtons() {
    $(document).on('click', '.table-btn', function () {
        const tableNum = parseInt($(this).data('table'));
        const status   = $(this).data('status');
        if (status === 'Reserved') {
            alert('Table ' + tableNum + ' is reserved.');
            return;
        }
        openTable(tableNum);
    });
}

function refreshTables() {
    // Simple page-level reload of table statuses via AJAX
    $.get(BASE_URL + 'api/orders.php', function (res) {
        if (!res.success) return;
        const activeTables = new Set(res.data.map(o => o.table_number));
        $('.table-btn').each(function () {
            const t  = parseInt($(this).data('table'));
            const isActive = activeTables.has(t);
            $(this).removeClass('bg-success bg-danger bg-warning text-white text-dark');
            if (isActive) {
                $(this).addClass('bg-danger text-white').data('status','Active')
                    .find('.fa').removeClass('fa-check').addClass('fa-utensils');
                $(this).find('.small').text($(this).data('capacity') + ' seats · Active');
            } else {
                $(this).addClass('bg-success text-white').data('status','Available')
                    .find('.fa').removeClass('fa-utensils').addClass('fa-check');
                $(this).find('.small').text($(this).data('capacity') + ' seats · Available');
            }
        });
    });
}

function showTableView() {
    $('#posView').hide();
    $('#tableView').show();
    currentOrderId = null;
    currentTable   = null;
    currentOrder   = null;
}

// ══════════════════════════════════════════
//  Open Table / Load Order
// ══════════════════════════════════════════
function openTable(tableNum) {
    currentTable = tableNum;
    showPOS(tableNum);

    $.get(BASE_URL + 'api/orders.php', { table: tableNum }, function (res) {
        if (res.success && res.data) {
            // Existing active order
            currentOrderId = res.data.order_id;
            currentOrder   = res.data;
            updateTicketHeader();
            renderOrderTicket(currentOrder);
        } else {
            // Create new order
            $.ajax({
                url:         BASE_URL + 'api/orders.php',
                type:        'POST',
                contentType: 'application/json',
                data:        JSON.stringify({ table_number: tableNum }),
                success: function (r) {
                    if (r.success) {
                        currentOrderId = r.data.order_id;
                        loadOrderFull(currentOrderId);
                    }
                }
            });
        }
    });
}

function showPOS(tableNum) {
    $('#tableView').hide();
    $('#posView').css('display','flex');
    $('#posTableLabel').text('Table ' + tableNum);
    // Click first category by default
    if (menuData.length) {
        setTimeout(function () {
            $('.cat-btn').first().trigger('click');
        }, 100);
    }
}

function loadOrderFull(orderId) {
    $.get(BASE_URL + 'api/orders.php', { order_id: orderId }, function (res) {
        if (res.success && res.data) {
            currentOrder   = res.data;
            currentOrderId = res.data.order_id;
            updateTicketHeader();
            renderOrderTicket(currentOrder);
        }
    });
}

// ══════════════════════════════════════════
//  Add / Remove / Update Items
// ══════════════════════════════════════════
function addItemToOrder(menuItemId, name, price) {
    if (!currentOrderId) return;
    $.ajax({
        url:         BASE_URL + 'api/order_items.php',
        type:        'POST',
        contentType: 'application/json',
        data:        JSON.stringify({ order_id: currentOrderId, menu_item_id: menuItemId }),
        success: function (res) {
            if (res.success) {
                currentOrder = res.data;
                renderOrderTicket(currentOrder);
                flashTicket();
            }
        },
        error: function (xhr) {
            alert('Error adding item: ' + (xhr.responseJSON?.error || 'Unknown'));
        }
    });
}

function removeItem(orderItemId) {
    if (!confirm('Remove this item?')) return;
    $.ajax({
        url:         BASE_URL + 'api/order_items.php',
        type:        'DELETE',
        contentType: 'application/json',
        data:        JSON.stringify({ order_item_id: orderItemId }),
        success: function (res) {
            if (res.success) {
                currentOrder = res.data;
                renderOrderTicket(currentOrder);
            }
        }
    });
}

function changeQty(orderItemId, delta) {
    const current = parseInt($('[data-oid="' + orderItemId + '"] .qty-val').text()) || 1;
    const newQty  = current + delta;
    if (newQty < 1) { removeItem(orderItemId); return; }
    $.ajax({
        url:         BASE_URL + 'api/order_items.php',
        type:        'PUT',
        contentType: 'application/json',
        data:        JSON.stringify({ order_item_id: orderItemId, quantity: newQty }),
        success: function (res) {
            if (res.success) {
                currentOrder = res.data;
                renderOrderTicket(currentOrder);
            }
        }
    });
}

function openNotesModal(orderItemId, itemName, currentNotes) {
    $('#modalItemName').text(itemName);
    $('#modalItemNotes').val(currentNotes || '');
    $('#modalOrderItemId').val(orderItemId);
    new bootstrap.Modal('#itemNotesModal').show();
}

function saveItemNotes() {
    const id    = parseInt($('#modalOrderItemId').val());
    const notes = $('#modalItemNotes').val().trim();
    $.ajax({
        url:         BASE_URL + 'api/order_items.php',
        type:        'PUT',
        contentType: 'application/json',
        data:        JSON.stringify({ order_item_id: id, notes: notes }),
        success: function (res) {
            if (res.success) {
                currentOrder = res.data;
                renderOrderTicket(currentOrder);
                bootstrap.Modal.getInstance('#itemNotesModal').hide();
            }
        }
    });
}

// ══════════════════════════════════════════
//  Render Order Ticket
// ══════════════════════════════════════════
function renderOrderTicket(order) {
    const $container = $('#orderTicketItems').empty();
    const items = order.items || [];

    if (!items.length) {
        $container.html('<p class="text-muted text-center mt-3 small">No items yet</p>');
    } else {
        items.forEach(function (it) {
            const lineTotal = (it.quantity * parseFloat(it.price_at_sale)).toFixed(2);
            const $row = $(`
              <div class="ticket-item border-bottom pb-2 mb-2" data-oid="${it.order_item_id}">
                <div class="d-flex align-items-start">
                  <div class="flex-grow-1 me-2">
                    <div class="fw-semibold small">${escHtml(it.item_name)}</div>
                    ${it.notes ? `<div class="text-primary small"><i class="fa fa-comment-alt me-1"></i>${escHtml(it.notes)}</div>` : ''}
                  </div>
                  <div class="text-end">
                    <div class="small text-muted">${numFmt(it.price_at_sale)} × </div>
                    <div class="fw-bold small">${numFmt(lineTotal)}</div>
                  </div>
                </div>
                <div class="d-flex align-items-center mt-1 gap-1">
                  <button class="btn btn-sm btn-outline-secondary px-2 py-0" onclick="changeQty(${it.order_item_id}, -1)">−</button>
                  <span class="qty-val fw-bold px-2">${it.quantity}</span>
                  <button class="btn btn-sm btn-outline-secondary px-2 py-0" onclick="changeQty(${it.order_item_id}, 1)">+</button>
                  <button class="btn btn-sm btn-outline-primary px-2 py-0 ms-1" onclick="openNotesModal(${it.order_item_id}, '${escJs(it.item_name)}', '${escJs(it.notes || '')}')">
                    <i class="fa fa-comment-alt"></i>
                  </button>
                  <button class="btn btn-sm btn-outline-danger px-2 py-0 ms-auto" onclick="removeItem(${it.order_item_id})">
                    <i class="fa fa-trash"></i>
                  </button>
                </div>
              </div>
            `);
            $container.append($row);
        });
    }

    // Totals
    const subtotal = parseFloat(order.subtotal) || 0;
    const tax      = parseFloat(order.tax_amount) || 0;
    const discount = parseFloat(order.discount_amount) || 0;
    const total    = parseFloat(order.total_amount) || 0;

    $('#ticketSubtotal').text(numFmt(subtotal) + ' ETB');
    $('#ticketTax').text(numFmt(tax) + ' ETB');
    $('#ticketTotal').text(numFmt(total) + ' ETB');
    $('#orderNotes').val(order.notes || '');

    if (discount > 0) {
        $('#ticketDiscount').text('-' + numFmt(discount) + ' ETB');
        $('#discountRow').show();
    } else {
        $('#discountRow').hide();
    }

    updateTicketHeader();
    updateActionButtons();
}

function updateTicketHeader() {
    if (!currentOrderId) return;
    $('#posOrderId').text('#' + currentOrderId);
    if (currentOrder) {
        const statusColors = {
            'Pending':         'bg-secondary',
            'Sent to Kitchen': 'bg-warning text-dark',
            'In Progress':     'bg-info text-dark',
            'Ready':           'bg-success',
        };
        const st = currentOrder.order_status;
        $('#posOrderStatus').attr('class', 'badge ms-1 ' + (statusColors[st] || 'bg-secondary')).text(st);
    }
}

function updateActionButtons() {
    if (!currentOrder) return;
    const st = currentOrder.order_status;
    const hasItems = (currentOrder.items || []).length > 0;

    if (st === 'Pending' && hasItems) {
        $('#btnSendKitchen').prop('disabled', false).show();
    } else {
        $('#btnSendKitchen').prop('disabled', true);
    }
    if (['Sent to Kitchen','In Progress','Ready'].includes(st)) {
        $('#btnCheckout').prop('disabled', false).show();
    } else {
        $('#btnCheckout').prop('disabled', !hasItems);
    }
}

// ══════════════════════════════════════════
//  Actions
// ══════════════════════════════════════════
function sendToKitchen() {
    if (!currentOrderId) return;
    if (!(currentOrder?.items?.length)) {
        alert('Add items to the order first.');
        return;
    }
    $.ajax({
        url:         BASE_URL + 'api/orders.php',
        type:        'PUT',
        contentType: 'application/json',
        data:        JSON.stringify({ order_id: currentOrderId, status: 'Sent to Kitchen' }),
        success: function (res) {
            if (res.success) {
                currentOrder = res.data;
                renderOrderTicket(currentOrder);
                showToast('Order sent to kitchen!', 'success');
            }
        }
    });
}

function applyDiscount() {
    const disc = parseFloat($('#discountInput').val()) || 0;
    $.ajax({
        url:         BASE_URL + 'api/orders.php',
        type:        'PUT',
        contentType: 'application/json',
        data:        JSON.stringify({ order_id: currentOrderId, discount_amount: disc }),
        success: function (res) {
            if (res.success) {
                currentOrder = res.data;
                renderOrderTicket(currentOrder);
                bootstrap.Modal.getInstance('#discountModal').hide();
            }
        }
    });
}

function saveOrderNotes() {
    const notes = $('#orderNotes').val().trim();
    $.ajax({
        url:         BASE_URL + 'api/orders.php',
        type:        'PUT',
        contentType: 'application/json',
        data:        JSON.stringify({ order_id: currentOrderId, notes: notes }),
        success: function (res) {
            if (res.success) showToast('Notes saved', 'info');
        }
    });
}

// ══════════════════════════════════════════
//  Utilities
// ══════════════════════════════════════════
function numFmt(n) { return parseFloat(n).toFixed(2); }
function escHtml(s) { return $('<div>').text(s || '').html(); }
function escJs(s)   { return (s || '').replace(/'/g, "\\'").replace(/\n/g, ' '); }
function flashTicket() {
    const $t = $('#orderTicketItems');
    $t.addClass('flash-green');
    setTimeout(() => $t.removeClass('flash-green'), 600);
}

function showToast(message, type = 'info') {
    const colors = { success: '#198754', info: '#0dcaf0', warning: '#ffc107', danger: '#dc3545' };
    const $toast = $(`
      <div style="position:fixed;top:70px;right:20px;z-index:9999;background:${colors[type]};color:${type==='warning'?'#000':'#fff'};padding:10px 18px;border-radius:8px;box-shadow:0 4px 12px rgba(0,0,0,.2);font-weight:600;">
        ${escHtml(message)}
      </div>
    `).appendTo('body');
    setTimeout(() => $toast.fadeOut(400, () => $toast.remove()), 2500);
}
