<?php
// KDS (Kitchen Display System) API
// GET  - all active kitchen orders
// PUT  - update item or order status
//        {order_item_id, item_status}  → update single item
//        {order_id, order_status}      → update whole order

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

// ── GET: kitchen orders ──────────────────────────────────────
if ($method === 'GET') {
    $stmt = $pdo->query("
        SELECT o.order_id, o.table_number, o.order_status, o.order_datetime,
               o.notes AS order_notes, o.source,
               s.first_name, s.last_name,
               oi.order_item_id, oi.menu_item_id, oi.quantity, oi.notes AS item_notes,
               oi.item_status, oi.price_at_sale,
               m.name AS item_name
        FROM orders o
        LEFT JOIN staff s ON s.staff_id = o.staff_id
        JOIN order_items oi ON oi.order_id = o.order_id
        JOIN menu_items m ON m.menu_item_id = oi.menu_item_id
        WHERE o.order_status IN ('Sent to Kitchen','In Progress','Ready')
        ORDER BY o.order_datetime ASC, oi.order_item_id ASC
    ");
    $rows = $stmt->fetchAll();

    // Group by order
    $orders = [];
    foreach ($rows as $row) {
        $oid = $row['order_id'];
        if (!isset($orders[$oid])) {
            $orders[$oid] = [
                'order_id'      => $oid,
                'table_number'  => $row['table_number'],
                'order_status'  => $row['order_status'],
                'order_datetime'=> $row['order_datetime'],
                'order_notes'   => $row['order_notes'],
                'source'        => $row['source'],
                'staff_name'    => $row['first_name'] ? $row['first_name'].' '.$row['last_name'] : 'Customer',
                'items'         => []
            ];
        }
        $orders[$oid]['items'][] = [
            'order_item_id' => $row['order_item_id'],
            'item_name'     => $row['item_name'],
            'quantity'      => $row['quantity'],
            'item_notes'    => $row['item_notes'],
            'item_status'   => $row['item_status'],
        ];
    }

    echo json_encode(['success' => true, 'data' => array_values($orders)]);
    exit;
}

// ── PUT: update status ───────────────────────────────────────
if ($method === 'PUT') {
    $input = json_decode(file_get_contents('php://input'), true);

    // Update single item
    if (!empty($input['order_item_id'])) {
        $allowed = ['Pending','In Progress','Ready'];
        $status  = $input['item_status'] ?? '';
        if (!in_array($status, $allowed)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid item_status']);
            exit;
        }
        $pdo->prepare("UPDATE order_items SET item_status=? WHERE order_item_id=?")
            ->execute([$status, (int)$input['order_item_id']]);

        // Get order_id then sync order status
        $stmt = $pdo->prepare("SELECT order_id FROM order_items WHERE order_item_id=?");
        $stmt->execute([(int)$input['order_item_id']]);
        $row = $stmt->fetch();
        if ($row) syncOrderStatus($pdo, $row['order_id']);

        echo json_encode(['success' => true]);
        exit;
    }

    // Update whole order
    if (!empty($input['order_id'])) {
        $allowed = ['In Progress','Ready'];
        $status  = $input['order_status'] ?? '';
        if (!in_array($status, $allowed)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid order_status']);
            exit;
        }
        $pdo->prepare("UPDATE orders SET order_status=? WHERE order_id=?")
            ->execute([$status, (int)$input['order_id']]);

        if ($status === 'In Progress') {
            $pdo->prepare("UPDATE order_items SET item_status='In Progress' WHERE order_id=? AND item_status='Pending'")
                ->execute([(int)$input['order_id']]);
        } elseif ($status === 'Ready') {
            $pdo->prepare("UPDATE order_items SET item_status='Ready' WHERE order_id=?")
                ->execute([(int)$input['order_id']]);
        }

        echo json_encode(['success' => true]);
        exit;
    }

    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'order_id or order_item_id required']);
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'error' => 'Method not allowed']);

function syncOrderStatus(PDO $pdo, int $order_id): void {
    $stmt = $pdo->prepare("
        SELECT
            SUM(item_status = 'Pending')     AS pending_cnt,
            SUM(item_status = 'In Progress') AS inprog_cnt,
            SUM(item_status = 'Ready')       AS ready_cnt,
            COUNT(*)                         AS total
        FROM order_items WHERE order_id=?
    ");
    $stmt->execute([$order_id]);
    $c = $stmt->fetch();
    if ($c['ready_cnt'] == $c['total']) {
        $pdo->prepare("UPDATE orders SET order_status='Ready' WHERE order_id=?")->execute([$order_id]);
    } elseif ($c['inprog_cnt'] > 0 || $c['ready_cnt'] > 0) {
        $pdo->prepare("UPDATE orders SET order_status='In Progress' WHERE order_id=?")->execute([$order_id]);
    }
}
