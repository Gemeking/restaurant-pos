<?php
// GET /api/menu.php - Public endpoint: all available menu items grouped by category
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');

$stmt = $pdo->query("
    SELECT c.category_id, c.name AS category_name, c.display_order,
           m.menu_item_id, m.name AS item_name, m.description, m.base_price, m.is_available
    FROM menu_categories c
    LEFT JOIN menu_items m ON m.category_id = c.category_id
    ORDER BY c.display_order, m.name
");
$rows = $stmt->fetchAll();

$categories = [];
foreach ($rows as $row) {
    $cid = $row['category_id'];
    if (!isset($categories[$cid])) {
        $categories[$cid] = [
            'category_id'   => $cid,
            'category_name' => $row['category_name'],
            'items'         => []
        ];
    }
    if ($row['menu_item_id']) {
        $categories[$cid]['items'][] = [
            'menu_item_id' => $row['menu_item_id'],
            'name'         => $row['item_name'],
            'description'  => $row['description'],
            'base_price'   => (float)$row['base_price'],
            'is_available' => (bool)$row['is_available'],
        ];
    }
}

echo json_encode(['success' => true, 'data' => array_values($categories)]);
