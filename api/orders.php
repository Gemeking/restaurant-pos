<?php
// Orders API
// GET    ?table=X          - get active order for a table
// GET    ?order_id=X       - get specific order with items
// GET    (none)            - list all non-paid orders
// POST                     - create new order {table_number}
// PUT                      - update order {order_id, status} or recalculate totals

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

// ── GET ─────────────────────────────────────────────────────
if ($method === 'GET') {
    if (!empty($_GET['table'])) {
        $table = (int)$_GET['table'];
        $stmt = $pdo->prepare("
            SELECT * FROM orders
            WHERE table_number = ? AND order_status NOT IN ('Paid','Cancelled')
            ORDER BY order_datetime DESC LIMIT 1
        ");
        $stmt->execute([$table]);
        $order = $stmt->fetch();
        if ($order) {
            $order['items'] = getOrderItems($pdo, $order['order_id']);
        }
        echo json_encode(['success' => true, 'data' => $order ?: null]);

    } elseif (!empty($_GET['order_id'])) {
        $stmt = $pdo->prepare("SELECT * FROM orders WHERE order_id = ?");
        $stmt->execute([(int)$_GET['order_id']]);
        $order = $stmt->fetch();
        if ($order) {
            $order['items'] = getOrderItems($pdo, $order['order_id']);
        }
        echo json_encode(['success' => true, 'data' => $order ?: null]);

    } else {
        $stmt = $pdo->query("
            SELECT o.*, s.first_name, s.last_name
            FROM orders o
            LEFT JOIN staff s ON s.staff_id = o.staff_id
            WHERE o.order_status NOT IN ('Paid','Cancelled')
            ORDER BY o.order_datetime DESC
        ");
        echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
    }
    exit;
}

// ── POST ────────────────────────────────────────────────────
if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $table_number = (int)($input['table_number'] ?? 0);
    if ($table_number < 1) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid table number']);
        exit;
    }

    // Check for existing active order on this table
    $stmt = $pdo->prepare("
        SELECT order_id FROM orders
        WHERE table_number = ? AND order_status NOT IN ('Paid','Cancelled')
        LIMIT 1
    ");
    $stmt->execute([$table_number]);
    $existing = $stmt->fetch();
    if ($existing) {
        echo json_encode(['success' => true, 'data' => ['order_id' => $existing['order_id'], 'existing' => true]]);
        exit;
    }

    $staff_id = $_SESSION['staff_id'];
    $notes    = $input['notes'] ?? null;
    $stmt = $pdo->prepare("
        INSERT INTO orders (staff_id, table_number, notes)
        VALUES (?, ?, ?)
    ");
    $stmt->execute([$staff_id, $table_number, $notes]);
    $order_id = $pdo->lastInsertId();

    // Mark table as Active
    $pdo->prepare("UPDATE restaurant_tables SET status='Active' WHERE table_number=?")
        ->execute([$table_number]);

    echo json_encode(['success' => true, 'data' => ['order_id' => (int)$order_id, 'existing' => false]]);
    exit;
}

// ── PUT ─────────────────────────────────────────────────────
if ($method === 'PUT') {
    $input = json_decode(file_get_contents('php://input'), true);
    $order_id = (int)($input['order_id'] ?? 0);
    if (!$order_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'order_id required']);
        exit;
    }

    if (!empty($input['status'])) {
        $allowed = ['Pending','Sent to Kitchen','In Progress','Ready','Paid','Cancelled'];
        if (!in_array($input['status'], $allowed)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid status']);
            exit;
        }
        $pdo->prepare("UPDATE orders SET order_status=? WHERE order_id=?")
            ->execute([$input['status'], $order_id]);

        // If Sent to Kitchen, set all Pending items to Pending (already are)
        // If Paid, free the table
        if ($input['status'] === 'Paid') {
            $stmt = $pdo->prepare("SELECT table_number FROM orders WHERE order_id=?");
            $stmt->execute([$order_id]);
            $row = $stmt->fetch();
            if ($row) {
                $pdo->prepare("UPDATE restaurant_tables SET status='Available' WHERE table_number=?")
                    ->execute([$row['table_number']]);
            }
        }
    }

    // Update notes if provided
    if (isset($input['notes'])) {
        $pdo->prepare("UPDATE orders SET notes=? WHERE order_id=?")
            ->execute([$input['notes'], $order_id]);
    }

    if (isset($input['discount_amount'])) {
        recalcOrder($pdo, $order_id, (float)$input['discount_amount']);
    } else {
        recalcOrder($pdo, $order_id, null);
    }

    $stmt = $pdo->prepare("SELECT * FROM orders WHERE order_id=?");
    $stmt->execute([$order_id]);
    $order = $stmt->fetch();
    $order['items'] = getOrderItems($pdo, $order_id);
    echo json_encode(['success' => true, 'data' => $order]);
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'error' => 'Method not allowed']);

// ── Helpers ────────────────────────────────────────────────
function getOrderItems(PDO $pdo, int $order_id): array {
    $stmt = $pdo->prepare("
        SELECT oi.*, m.name AS item_name, m.category_id
        FROM order_items oi
        JOIN menu_items m ON m.menu_item_id = oi.menu_item_id
        WHERE oi.order_id = ?
        ORDER BY oi.order_item_id
    ");
    $stmt->execute([$order_id]);
    return $stmt->fetchAll();
}

function recalcOrder(PDO $pdo, int $order_id, ?float $discount): void {
    $stmt = $pdo->prepare("
        SELECT SUM(quantity * price_at_sale) AS subtotal FROM order_items WHERE order_id=?
    ");
    $stmt->execute([$order_id]);
    $row = $stmt->fetch();
    $subtotal  = (float)($row['subtotal'] ?? 0);
    $discount  = $discount ?? 0;
    $tax       = round(($subtotal - $discount) * TAX_RATE, 2);
    $total     = round($subtotal - $discount + $tax, 2);
    $pdo->prepare("
        UPDATE orders SET subtotal=?, tax_amount=?, discount_amount=?, total_amount=?
        WHERE order_id=?
    ")->execute([$subtotal, $tax, $discount, $total, $order_id]);
}
