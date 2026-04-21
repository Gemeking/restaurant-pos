<?php
// Public Customer Order API — no auth required
// POST {table_number, items: [{menu_item_id, quantity, notes}]}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$input        = json_decode(file_get_contents('php://input'), true);
$table_number = (int)($input['table_number'] ?? 0);
$items        = $input['items'] ?? [];

if ($table_number < 1) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Valid table_number required']);
    exit;
}
if (empty($items)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'No items provided']);
    exit;
}

// Check for existing active order on this table
$stmt = $pdo->prepare("
    SELECT order_id FROM orders
    WHERE table_number=? AND order_status NOT IN ('Paid','Cancelled')
    LIMIT 1
");
$stmt->execute([$table_number]);
$existing = $stmt->fetch();

if ($existing) {
    $order_id = $existing['order_id'];
} else {
    // Create new order — staff_id=NULL (customer), source=Customer
    $pdo->prepare("INSERT INTO orders (table_number, order_status, source) VALUES (?, 'Sent to Kitchen', 'Customer')")
        ->execute([$table_number]);
    $order_id = $pdo->lastInsertId();
    $pdo->prepare("UPDATE restaurant_tables SET status='Active' WHERE table_number=?")
        ->execute([$table_number]);
}

$subtotal = 0.0;
foreach ($items as $item) {
    $menu_item_id = (int)($item['menu_item_id'] ?? 0);
    $quantity     = max(1, (int)($item['quantity'] ?? 1));
    $notes        = trim($item['notes'] ?? '');

    $stmt = $pdo->prepare("SELECT base_price FROM menu_items WHERE menu_item_id=? AND is_available=1");
    $stmt->execute([$menu_item_id]);
    $mi = $stmt->fetch();
    if (!$mi) continue;

    $pdo->prepare("INSERT INTO order_items (order_id, menu_item_id, quantity, price_at_sale, notes, item_status) VALUES (?,?,?,?,?,'Pending')")
        ->execute([$order_id, $menu_item_id, $quantity, $mi['base_price'], $notes ?: null]);
    $subtotal += $quantity * (float)$mi['base_price'];
}

// Update totals
$tax   = round($subtotal * TAX_RATE, 2);
$total = round($subtotal + $tax, 2);
$pdo->prepare("UPDATE orders SET order_status='Sent to Kitchen', subtotal=?, tax_amount=?, total_amount=? WHERE order_id=?")
    ->execute([$subtotal, $tax, $total, $order_id]);

echo json_encode([
    'success'  => true,
    'order_id' => $order_id,
    'message'  => 'Your order has been sent to the kitchen!'
]);
