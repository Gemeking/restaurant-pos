<?php
// Public — no auth required
// GET ?order_id=X  → returns order status + item statuses

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');
header('Cache-Control: no-cache');

$order_id = (int)($_GET['order_id'] ?? 0);
if (!$order_id) {
    echo json_encode(['success' => false, 'error' => 'Invalid order']);
    exit;
}

$stmt = $pdo->prepare("SELECT order_id, table_number, order_status FROM orders WHERE order_id=?");
$stmt->execute([$order_id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    echo json_encode(['success' => false, 'error' => 'Order not found']);
    exit;
}

$stmt = $pdo->prepare("
    SELECT oi.order_item_id, m.name AS item_name, oi.quantity, oi.notes, oi.item_status
    FROM order_items oi
    JOIN menu_items m ON m.menu_item_id = oi.menu_item_id
    WHERE oi.order_id = ?
    ORDER BY oi.order_item_id
");
$stmt->execute([$order_id]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    'success'      => true,
    'order_id'     => (int)$order['order_id'],
    'table_number' => (int)$order['table_number'],
    'order_status' => $order['order_status'],
    'items'        => $items,
]);
