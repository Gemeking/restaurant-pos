/**
 * Payment Page — payment.js
 * Handles cash, card, and split-check payment flows.
 */

$(function () {
    updatePaymentAmounts();

    // Recalculate when discount changes
    $('#discountAmt').on('input', updatePaymentAmounts);

    // Cash: show change
    $('#cashReceived').on('input', function () {
        const received = parseFloat($(this).val()) || 0;
        const total    = getTotal();
        if (received >= total) {
            const change = received - total;
            $('#changeAmt').text(change.toFixed(2));
            $('#changeRow').removeClass('d-none');
        } else {
            $('#changeRow').addClass('d-none');
        }
    });

    // Quick cash buttons
    $(document).on('click', '.quick-cash', function () {
        const total = getTotal();
        const mult  = $(this).data('mult');
        const val   = $(this).data('val');
        if (mult) {
            $('#cashReceived').val(total.toFixed(2)).trigger('input');
        } else {
            $('#cashReceived').val(parseFloat(val)).trigger('input');
        }
    });

    // Card amount display
    $('#paymentTabs button').on('shown.bs.tab', function () {
        $('#cardAmount').text(getTotal().toFixed(2));
    });

    // Pay cash
    $('#btnPayCash').on('click', payCash);
    // Pay card
    $('#btnPayCard').on('click', payCard);
    // Generate split fields
    $('#btnGenSplits').on('click', generateSplitFields);
    // Pay split
    $('#btnPaySplit').on('click', paySplit);
});

function getTotal() {
    const subtotal  = parseFloat(SUBTOTAL) || 0;
    const discount  = parseFloat($('#discountAmt').val()) || 0;
    const tax       = (subtotal - discount) * TAX_RATE;
    return Math.max(0, subtotal - discount + tax);
}

function updatePaymentAmounts() {
    const subtotal = parseFloat(SUBTOTAL) || 0;
    const discount = parseFloat($('#discountAmt').val()) || 0;
    const tax      = Math.round((subtotal - discount) * TAX_RATE * 100) / 100;
    const total    = Math.round((subtotal - discount + tax) * 100) / 100;

    $('#paySubtotal').text(subtotal.toFixed(2) + ' ETB');
    $('#payDiscount').text(discount.toFixed(2) + ' ETB');
    $('#payTax').text(tax.toFixed(2) + ' ETB');
    $('#payTotal').text(total.toFixed(2) + ' ETB');
    $('#cardAmount').text(total.toFixed(2));
    $('#changeRow').addClass('d-none');
    $('#cashReceived').val('').trigger('input');
}

// ══════════════════════════════════════════
//  Cash Payment
// ══════════════════════════════════════════
function payCash() {
    const received = parseFloat($('#cashReceived').val()) || 0;
    const total    = getTotal();
    if (received < total) {
        alert('Amount received (' + received.toFixed(2) + ') is less than total (' + total.toFixed(2) + ')');
        return;
    }
    processPayment([{ method: 'Cash', amount: total }], received - total);
}

// ══════════════════════════════════════════
//  Card Payment
// ══════════════════════════════════════════
function payCard() {
    const total = getTotal();
    const ref   = $('#cardReference').val().trim();
    processPayment([{ method: 'Card', amount: total, reference_note: ref || 'Card payment' }], 0);
}

// ══════════════════════════════════════════
//  Split Check
// ══════════════════════════════════════════
function generateSplitFields() {
    const count = parseInt($('#splitCount').val()) || 2;
    const total = getTotal();
    const share = (total / count).toFixed(2);
    const $fields = $('#splitFields').empty();

    for (let i = 1; i <= count; i++) {
        $fields.append(`
          <div class="row g-2 mb-2 split-row">
            <div class="col-4">
              <select class="form-select form-select-sm split-method">
                <option value="Split-Cash">Cash</option>
                <option value="Split-Card">Card</option>
              </select>
            </div>
            <div class="col-5">
              <input type="number" class="form-control form-control-sm split-amount"
                     value="${share}" min="0" step="0.01" placeholder="Amount">
            </div>
            <div class="col-3">
              <span class="form-control-plaintext form-control-sm text-muted small">Part ${i}</span>
            </div>
          </div>
        `);
    }

    // Live validation
    $fields.on('input', '.split-amount', validateSplitTotal);
}

function validateSplitTotal() {
    const total   = getTotal();
    const entered = [...$('.split-amount')].reduce((s, el) => s + (parseFloat(el.value) || 0), 0);
    const diff    = Math.abs(entered - total);
    if (diff > 0.02) {
        $('#splitWarning').removeClass('d-none');
    } else {
        $('#splitWarning').addClass('d-none');
    }
}

function paySplit() {
    const total   = getTotal();
    const entered = [...$('.split-amount')].reduce((s, el) => s + (parseFloat(el.value) || 0), 0);
    if (Math.abs(entered - total) > 0.02) {
        alert('Split amounts must add up to the total (' + total.toFixed(2) + '). Currently: ' + entered.toFixed(2));
        return;
    }
    const payments = [];
    $('.split-row').each(function () {
        const method = $(this).find('.split-method').val();
        const amount = parseFloat($(this).find('.split-amount').val()) || 0;
        if (amount > 0) payments.push({ method: method, amount: amount });
    });
    processPayment(payments, 0);
}

// ══════════════════════════════════════════
//  Process Payment
// ══════════════════════════════════════════
function processPayment(payments, change) {
    const discount = parseFloat($('#discountAmt').val()) || 0;

    $('button').prop('disabled', true);

    $.ajax({
        url:         BASE_URL + 'api/payments.php',
        type:        'POST',
        contentType: 'application/json',
        data:        JSON.stringify({
            order_id:        ORDER_ID,
            payments:        payments,
            discount_amount: discount
        }),
        success: function (res) {
            if (res.success) {
                showReceipt(res.receipt, change);
            } else {
                alert('Payment error: ' + (res.error || 'Unknown'));
                $('button').prop('disabled', false);
            }
        },
        error: function (xhr) {
            alert('Server error: ' + (xhr.responseJSON?.error || 'Unknown'));
            $('button').prop('disabled', false);
        }
    });
}

// ══════════════════════════════════════════
//  Receipt
// ══════════════════════════════════════════
function showReceipt(receipt, change) {
    let itemRows = receipt.items.map(it =>
        `<tr><td>${escHtml(it.item_name)}</td><td class="text-center">${it.quantity}</td><td class="text-end">${(it.quantity * parseFloat(it.price_at_sale)).toFixed(2)}</td></tr>`
    ).join('');

    let payRows = receipt.payments.map(p =>
        `<tr><td>${p.method}</td><td class="text-end">${parseFloat(p.amount).toFixed(2)} ETB</td></tr>`
    ).join('');

    const html = `
      <div class="text-center mb-3">
        <h6 class="fw-bold">Receipt — Order #${receipt.order_id}</h6>
        <div class="text-muted small">Table ${receipt.table_number} · ${new Date().toLocaleString()}</div>
      </div>
      <table class="table table-sm table-bordered mb-2">
        <thead class="table-light"><tr><th>Item</th><th class="text-center">Qty</th><th class="text-end">Amount</th></tr></thead>
        <tbody>${itemRows}</tbody>
      </table>
      <table class="table table-sm mb-0">
        <tr><td>Subtotal</td><td class="text-end">${parseFloat(receipt.subtotal).toFixed(2)} ETB</td></tr>
        ${receipt.discount > 0 ? `<tr class="text-danger"><td>Discount</td><td class="text-end">-${parseFloat(receipt.discount).toFixed(2)} ETB</td></tr>` : ''}
        <tr><td>Tax (15%)</td><td class="text-end">${parseFloat(receipt.tax).toFixed(2)} ETB</td></tr>
        <tr class="fw-bold"><td>TOTAL</td><td class="text-end">${parseFloat(receipt.total).toFixed(2)} ETB</td></tr>
      </table>
      <hr>
      <p class="fw-semibold mb-1">Payment:</p>
      <table class="table table-sm mb-0">${payRows}</table>
      ${change > 0.001 ? `<div class="alert alert-info mt-2 py-1"><strong>Change: ${change.toFixed(2)} ETB</strong></div>` : ''}
      <div class="text-center mt-3 text-muted small">Thank you for your visit!</div>
    `;

    $('#receiptBody').html(html);
    new bootstrap.Modal('#receiptModal').show();
}

function escHtml(s) { return $('<div>').text(s || '').html(); }
