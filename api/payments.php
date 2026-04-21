<?php
// Payments API
// POST - process payment
// Body: {order_id, payments: [{method, amount, reference_note?}], discount_amount?}

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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$input    = json_decode(file_get_contents('php://input'), true);
$order_id = (int)($input['order_id'] ?? 0);
$payments = $input['payments'] ?? [];
$discount = (float)($input['discount_amount'] ?? 0);

if (!$order_id || empty($payments)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'order_id and payments required']);
    exit;
}

// Fetch order
$stmt = $pdo->prepare("SELECT * FROM orders WHERE order_id=?");
$stmt->execute([$order_id]);
$order = $stmt->fetch();
if (!$order) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Order not found']);
    exit;
}
if ($order['order_status'] === 'Paid') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Order already paid']);
    exit;
}

// Apply discount and recalculate
$subtotal = (float)$order['subtotal'];
$tax      = round(($subtotal - $discount) * TAX_RATE, 2);
$total    = round($subtotal - $discount + $tax, 2);

$pdo->prepare("UPDATE orders SET discount_amount=?, tax_amount=?, total_amount=? WHERE order_id=?")
    ->execute([$discount, $tax, $total, $order_id]);

// Validate total payment
$paid = array_sum(array_column($payments, 'amount'));
if (round($paid, 2) < round($total, 2)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error'   => "Insufficient payment. Required: $total, Received: $paid"
    ]);
    exit;
}

// Insert payment records
$allowed_methods = ['Cash','Card','Split-Cash','Split-Card'];
foreach ($payments as $p) {
    $method = $p['method'] ?? 'Cash';
    if (!in_array($method, $allowed_methods)) $method = 'Cash';
    $amount = (float)($p['amount'] ?? 0);
    $note   = $p['reference_note'] ?? null;
    $pdo->prepare("INSERT INTO payments (order_id, method, amount, reference_note) VALUES (?,?,?,?)")
        ->execute([$order_id, $method, $amount, $note]);
}

// Mark order Paid and free table
$pdo->prepare("UPDATE orders SET order_status='Paid' WHERE order_id=?")->execute([$order_id]);
$pdo->prepare("UPDATE restaurant_tables SET status='Available' WHERE table_number=?")
    ->execute([$order['table_number']]);

// Build receipt
$stmt = $pdo->prepare("SELECT oi.*, m.name AS item_name FROM order_items oi JOIN menu_items m ON m.menu_item_id=oi.menu_item_id WHERE oi.order_id=?");
$stmt->execute([$order_id]);
$items = $stmt->fetchAll();

echo json_encode([
    'success' => true,
    'receipt' => [
        'order_id'     => $order_id,
        'table_number' => $order['table_number'],
        'subtotal'     => $subtotal,
        'discount'     => $discount,
        'tax'          => $tax,
        'total'        => $total,
        'paid'         => $paid,
        'change'       => round($paid - $total, 2),
        'items'        => $items,
        'payments'     => $payments,
    ]
]);
