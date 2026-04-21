/**
 * Customer Menu — customer.js
 * Menu browsing, cart management, and order submission.
 */

let cart     = {};    // { menuItemId: { name, price, quantity } }
let menuData = [];

$(function () {
    loadMenu();
    $('#btnPlaceOrder').on('click', placeOrder);
});

// ══════════════════════════════════════════
//  Load Menu
// ══════════════════════════════════════════
function loadMenu() {
    $.get(BASE_URL + 'api/menu.php', function (res) {
        if (!res.success) {
            $('#menuContent').html('<p class="text-danger text-center">Failed to load menu.</p>');
            return;
        }
        menuData = res.data;
        renderCategoryNav();
        renderAllItems();
    }).fail(function () {
        $('#menuContent').html('<p class="text-danger text-center">Could not connect to server.</p>');
    });
}

function renderCategoryNav() {
    const $nav = $('#catNav').empty();
    const $all  = $('<button class="btn btn-sm btn-dark me-1 cat-filter" data-cat="all">All</button>')
        .on('click', () => filterCategory('all'));
    $nav.append($all);

    menuData.forEach(function (cat) {
        if (!cat.items.some(i => i.is_available)) return;
        const $btn = $(`<button class="btn btn-sm btn-outline-dark me-1 cat-filter" data-cat="${cat.category_id}">${escHtml(cat.category_name)}</button>`)
            .on('click', () => filterCategory(cat.category_id));
        $nav.append($btn);
    });
}

function renderAllItems() {
    const $content = $('#menuContent').empty();

    menuData.forEach(function (cat) {
        const avail = cat.items.filter(i => i.is_available);
        if (!avail.length) return;

        const $section = $(`<div class="cat-section mb-4" data-cat="${cat.category_id}">
            <h5 class="fw-bold mb-3" style="color:#0f3460;">${escHtml(cat.category_name)}</h5>
        </div>`);

        avail.forEach(function (item) {
            const $card = $(`
              <div class="menu-item-card">
                <div class="d-flex justify-content-between align-items-start">
                  <div class="flex-grow-1 me-3">
                    <div class="fw-bold">${escHtml(item.name)}</div>
                    <div class="text-muted small mt-1">${escHtml(item.description || '')}</div>
                  </div>
                  <div class="text-end flex-shrink-0">
                    <div class="price">${parseFloat(item.base_price).toFixed(2)} ETB</div>
                    <div class="mt-2" id="cartControl-${item.menu_item_id}">
                      <button class="btn btn-sm btn-danger add-btn px-3" onclick="addToCart(${item.menu_item_id}, '${escJs(item.name)}', ${item.base_price})">
                        <i class="fa fa-plus me-1"></i>Add
                      </button>
                    </div>
                  </div>
                </div>
              </div>
            `);
            $section.append($card);
        });

        $content.append($section);
    });
}

function filterCategory(catId) {
    $('.cat-filter').removeClass('btn-dark').addClass('btn-outline-dark');
    $(`[data-cat="${catId}"]`).removeClass('btn-outline-dark').addClass('btn-dark');

    if (catId === 'all') {
        $('.cat-section').show();
        $('html, body').animate({ scrollTop: $('#menuContent').offset().top - 130 }, 300);
    } else {
        $('.cat-section').hide();
        const $section = $(`.cat-section[data-cat="${catId}"]`);
        $section.show();
        $('html, body').animate({ scrollTop: $section.offset().top - 130 }, 300);
    }
}

// ══════════════════════════════════════════
//  Cart
// ══════════════════════════════════════════
function addToCart(id, name, price) {
    if (cart[id]) {
        cart[id].quantity += 1;
    } else {
        cart[id] = { name: name, price: price, quantity: 1, notes: '' };
    }
    updateCartControl(id);
    updateCartBar();
}

function removeFromCart(id) {
    if (!cart[id]) return;
    cart[id].quantity -= 1;
    if (cart[id].quantity <= 0) delete cart[id];
    updateCartControl(id);
    updateCartBar();
}

function updateCartControl(id) {
    const $ctrl = $('#cartControl-' + id);
    if (!cart[id]) {
        $ctrl.html(`<button class="btn btn-sm btn-danger add-btn px-3" onclick="addToCart(${id}, '${escJs(cart[id]?.name || '')}', ${cart[id]?.price || 0})"><i class="fa fa-plus me-1"></i>Add</button>`);
        // Rebuild from menuData
        menuData.forEach(cat => cat.items.forEach(item => {
            if (item.menu_item_id == id) {
                $ctrl.html(`<button class="btn btn-sm btn-danger add-btn px-3" onclick="addToCart(${item.menu_item_id}, '${escJs(item.name)}', ${item.base_price})"><i class="fa fa-plus me-1"></i>Add</button>`);
            }
        }));
    } else {
        $ctrl.html(`
          <div class="d-flex align-items-center gap-2">
            <button class="btn btn-sm btn-outline-secondary px-2" onclick="removeFromCart(${id})">−</button>
            <span class="fw-bold">${cart[id].quantity}</span>
            <button class="btn btn-sm btn-danger px-2" onclick="addToCart(${id}, '${escJs(cart[id].name)}', ${cart[id].price})">+</button>
          </div>
        `);
    }
}

function updateCartBar() {
    const total = cartTotal();
    const count = cartCount();

    if (count === 0) {
        $('#cartBar').hide();
    } else {
        $('#cartCount').text(count + ' item' + (count > 1 ? 's' : ''));
        $('#cartTotal').text(total.toFixed(2));
        $('#cartBar').show();
    }
}

function cartTotal() {
    return Object.values(cart).reduce((s, i) => s + i.price * i.quantity, 0);
}
function cartCount() {
    return Object.values(cart).reduce((s, i) => s + i.quantity, 0);
}

// Populate cart modal when opened
$('#cartModal').on('show.bs.modal', function () {
    const $body = $('#cartModalBody').empty();
    if (!Object.keys(cart).length) {
        $body.html('<p class="text-muted text-center">Your cart is empty.</p>');
        return;
    }
    Object.entries(cart).forEach(([id, item]) => {
        $body.append(`
          <div class="d-flex justify-content-between align-items-center mb-3 border-bottom pb-2">
            <div>
              <div class="fw-semibold">${escHtml(item.name)}</div>
              <div class="small text-muted">${item.price.toFixed(2)} ETB × ${item.quantity}</div>
              <input type="text" class="form-control form-control-sm mt-1 item-notes-input" style="width:200px"
                     placeholder="Note (optional)" data-id="${id}" value="${escHtml(item.notes || '')}">
            </div>
            <div class="d-flex align-items-center gap-2">
              <button class="btn btn-sm btn-outline-secondary" onclick="removeFromCart(${id}); refreshCartModal()">−</button>
              <span class="fw-bold">${item.quantity}</span>
              <button class="btn btn-sm btn-outline-danger" onclick="addToCart(${id}, '${escJs(item.name)}', ${item.price}); refreshCartModal()">+</button>
              <div class="fw-bold ms-2">${(item.price * item.quantity).toFixed(2)}</div>
            </div>
          </div>
        `);
    });

    // Save item notes
    $body.on('change', '.item-notes-input', function () {
        const id = $(this).data('id');
        if (cart[id]) cart[id].notes = $(this).val().trim();
    });

    const subtotal = cartTotal();
    const tax      = subtotal * TAX_RATE;
    const total    = subtotal + tax;
    $('#cartModalSubtotal').text(subtotal.toFixed(2) + ' ETB');
    $('#cartModalTax').text(tax.toFixed(2) + ' ETB');
    $('#cartModalTotal').text(total.toFixed(2) + ' ETB');
});

function refreshCartModal() {
    $('#cartModal').trigger('show.bs.modal');
}

// ══════════════════════════════════════════
//  Place Order
// ══════════════════════════════════════════
function placeOrder() {
    if (!Object.keys(cart).length) return;

    const items = Object.entries(cart).map(([id, item]) => ({
        menu_item_id: parseInt(id),
        quantity:     item.quantity,
        notes:        item.notes || ''
    }));

    const payload = {
        table_number: TABLE_NUMBER,
        items:        items
    };

    $('#btnPlaceOrder').prop('disabled', true).html('<i class="fa fa-spinner fa-spin me-2"></i>Sending...');

    $.ajax({
        url:         BASE_URL + 'api/customer_order.php',
        type:        'POST',
        contentType: 'application/json',
        data:        JSON.stringify(payload),
        success: function (res) {
            if (res.success) {
                bootstrap.Modal.getInstance('#cartModal')?.hide();
                $('#menuContent').hide();
                $('#catNav').hide();
                $('#cartBar').hide();
                $('#orderSuccess').show();
                cart = {};
            } else {
                alert('Error: ' + (res.error || 'Could not place order'));
                $('#btnPlaceOrder').prop('disabled', false).html('<i class="fa fa-paper-plane me-2"></i>Place Order');
            }
        },
        error: function () {
            alert('Server error. Please try again.');
            $('#btnPlaceOrder').prop('disabled', false).html('<i class="fa fa-paper-plane me-2"></i>Place Order');
        }
    });
}

function resetOrder() {
    cart = {};
    $('#orderSuccess').hide();
    $('#menuContent').show();
    $('#catNav').show();
    // Rebuild controls
    menuData.forEach(cat => cat.items.forEach(item => {
        const $ctrl = $('#cartControl-' + item.menu_item_id);
        $ctrl.html(`<button class="btn btn-sm btn-danger add-btn px-3" onclick="addToCart(${item.menu_item_id}, '${escJs(item.name)}', ${item.base_price})"><i class="fa fa-plus me-1"></i>Add</button>`);
    }));
}

// ══════════════════════════════════════════
//  Util
// ══════════════════════════════════════════
function escHtml(s) { return $('<div>').text(s || '').html(); }
function escJs(s)   { return (s || '').replace(/'/g, "\\'"); }
