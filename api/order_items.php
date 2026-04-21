<?php
// Order Items API
// POST   - add item   {order_id, menu_item_id, quantity, notes}
// PUT    - update     {order_item_id, quantity?, notes?}
// DELETE - remove     {order_item_id}

session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

// ── POST: add item ───────────────────────────────────────────
if ($method === 'POST') {
    $input        = json_decode(file_get_contents('php://input'), true);
    $order_id     = (int)($input['order_id'] ?? 0);
    $menu_item_id = (int)($input['menu_item_id'] ?? 0);
    $quantity     = max(1, (int)($input['quantity'] ?? 1));
    $notes        = trim($input['notes'] ?? '');

    if (!$order_id || !$menu_item_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'order_id and menu_item_id required']);
        exit;
    }

    // Get current price
    $stmt = $pdo->prepare("SELECT base_price, name FROM menu_items WHERE menu_item_id=? AND is_available=1");
    $stmt->execute([$menu_item_id]);
    $item = $stmt->fetch();
    if (!$item) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Menu item not found or unavailable']);
        exit;
    }

    // Check if same item+notes combo already in order (merge qty)
    $stmt = $pdo->prepare("
        SELECT order_item_id, quantity FROM order_items
        WHERE order_id=? AND menu_item_id=? AND COALESCE(notes,'')=?
    ");
    $stmt->execute([$order_id, $menu_item_id, $notes]);
    $existing = $stmt->fetch();

    if ($existing) {
        $new_qty = $existing['quantity'] + $quantity;
        $pdo->prepare("UPDATE order_items SET quantity=? WHERE order_item_id=?")
            ->execute([$new_qty, $existing['order_item_id']]);
        $order_item_id = $existing['order_item_id'];
    } else {
        $pdo->prepare("
            INSERT INTO order_items (order_id, menu_item_id, quantity, price_at_sale, notes)
            VALUES (?, ?, ?, ?, ?)
        ")->execute([$order_id, $menu_item_id, $quantity, $item['base_price'], $notes ?: null]);
        $order_item_id = $pdo->lastInsertId();
    }

    recalcOrder($pdo, $order_id);
    $order = getOrderWithItems($pdo, $order_id);
    echo json_encode(['success' => true, 'data' => $order]);
    exit;
}

// ── PUT: update item ─────────────────────────────────────────
if ($method === 'PUT') {
    $input         = json_decode(file_get_contents('php://input'), true);
    $order_item_id = (int)($input['order_item_id'] ?? 0);
    if (!$order_item_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'order_item_id required']);
        exit;
    }

    $stmt = $pdo->prepare("SELECT * FROM order_items WHERE order_item_id=?");
    $stmt->execute([$order_item_id]);
    $row = $stmt->fetch();
    if (!$row) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Item not found']);
        exit;
    }

    $quantity = isset($input['quantity']) ? max(1, (int)$input['quantity']) : $row['quantity'];
    $notes    = isset($input['notes'])    ? trim($input['notes'])            : $row['notes'];

    $pdo->prepare("UPDATE order_items SET quantity=?, notes=? WHERE order_item_id=?")
        ->execute([$quantity, $notes ?: null, $order_item_id]);

    recalcOrder($pdo, $row['order_id']);
    $order = getOrderWithItems($pdo, $row['order_id']);
    echo json_encode(['success' => true, 'data' => $order]);
    exit;
}

// ── DELETE: remove item ──────────────────────────────────────
if ($method === 'DELETE') {
    $input         = json_decode(file_get_contents('php://input'), true);
    $order_item_id = (int)($input['order_item_id'] ?? 0);
    if (!$order_item_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'order_item_id required']);
        exit;
    }

    $stmt = $pdo->prepare("SELECT order_id FROM order_items WHERE order_item_id=?");
    $stmt->execute([$order_item_id]);
    $row = $stmt->fetch();
    if (!$row) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Item not found']);
        exit;
    }
    $order_id = $row['order_id'];

    $pdo->prepare("DELETE FROM order_items WHERE order_item_id=?")->execute([$order_item_id]);
    recalcOrder($pdo, $order_id);
    $order = getOrderWithItems($pdo, $order_id);
    echo json_encode(['success' => true, 'data' => $order]);
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'error' => 'Method not allowed']);

// ── Helpers ──────────────────────────────────────────────────
function getOrderWithItems(PDO $pdo, int $order_id): array {
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE order_id=?");
    $stmt->execute([$order_id]);
    $order = $stmt->fetch();
    $stmt = $pdo->prepare("
        SELECT oi.*, m.name AS item_name
        FROM order_items oi
        JOIN menu_items m ON m.menu_item_id = oi.menu_item_id
        WHERE oi.order_id=?
        ORDER BY oi.order_item_id
    ");
    $stmt->execute([$order_id]);
    $order['items'] = $stmt->fetchAll();
    return $order;
}

function recalcOrder(PDO $pdo, int $order_id): void {
    require_once __DIR__ . '/../config/config.php';
    $stmt = $pdo->prepare("SELECT SUM(quantity * price_at_sale) AS subtotal FROM order_items WHERE order_id=?");
    $stmt->execute([$order_id]);
    $row      = $stmt->fetch();
    $subtotal = (float)($row['subtotal'] ?? 0);

    $stmt = $pdo->prepare("SELECT discount_amount FROM orders WHERE order_id=?");
    $stmt->execute([$order_id]);
    $orow     = $stmt->fetch();
    $discount = (float)($orow['discount_amount'] ?? 0);

    $tax   = round(($subtotal - $discount) * TAX_RATE, 2);
    $total = round($subtotal - $discount + $tax, 2);
    $pdo->prepare("UPDATE orders SET subtotal=?, tax_amount=?, total_amount=? WHERE order_id=?")
        ->execute([$subtotal, $tax, $total, $order_id]);
}
