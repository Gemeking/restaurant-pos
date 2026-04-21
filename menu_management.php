<?php
session_start();
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
requireRole(['Manager']);

$page_title = 'Menu Management — ' . APP_NAME;
$page_name  = 'Menu Items';
$msg = $err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_category') {
        $name = trim($_POST['cat_name'] ?? '');
        if (!$name) { $err = 'Category name is required.'; }
        else {
            $max = $pdo->query("SELECT COALESCE(MAX(display_order),0)+1 AS n FROM menu_categories")->fetch()['n'];
            $pdo->prepare("INSERT INTO menu_categories (name,display_order) VALUES (?,?)")->execute([$name,$max]);
            $msg = "Category '$name' added.";
        }
    }

    if ($action === 'add_item') {
        $cat   = (int)($_POST['category_id'] ?? 0);
        $name  = trim($_POST['item_name'] ?? '');
        $desc  = trim($_POST['description'] ?? '');
        $price = (float)($_POST['base_price'] ?? 0);
        if (!$cat || !$name || $price <= 0) {
            $err = 'Category, name and a valid price are all required.';
        } else {
            $pdo->prepare("INSERT INTO menu_items (category_id,name,description,base_price) VALUES (?,?,?,?)")
                ->execute([$cat,$name,$desc,$price]);
            $msg = "Item '$name' added to menu.";
        }
    }

    if ($action === 'edit_item') {
        $id    = (int)($_POST['menu_item_id'] ?? 0);
        $cat   = (int)($_POST['category_id'] ?? 0);
        $name  = trim($_POST['item_name'] ?? '');
        $desc  = trim($_POST['description'] ?? '');
        $price = (float)($_POST['base_price'] ?? 0);
        if (!$id || !$name || $price <= 0) { $err = 'Invalid data.'; }
        else {
            $pdo->prepare("UPDATE menu_items SET category_id=?,name=?,description=?,base_price=? WHERE menu_item_id=?")
                ->execute([$cat,$name,$desc,$price,$id]);
            $msg = "Item updated.";
        }
    }

    if ($action === 'toggle_item') {
        $id = (int)($_POST['menu_item_id'] ?? 0);
        $pdo->prepare("UPDATE menu_items SET is_available = NOT is_available WHERE menu_item_id=?")->execute([$id]);
        $msg = 'Item availability updated.';
    }
}

$categories = $pdo->query("SELECT * FROM menu_categories ORDER BY display_order")->fetchAll();
$items = $pdo->query("
    SELECT m.*, c.name AS cat_name
    FROM menu_items m LEFT JOIN menu_categories c ON c.category_id=m.category_id
    ORDER BY c.display_order, m.name
")->fetchAll();

include __DIR__ . '/includes/header.php';
?>

<!-- Page Header -->
<div class="page-header">
    <div class="page-header-text">
        <h4><i class="fa fa-book-open me-2 text-purple" style="color:#8e44ad;"></i>Menu Management</h4>
        <p>Add, edit, or hide menu items and categories. Changes take effect immediately on the POS and customer menu.</p>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addCatModal">
            <i class="fa fa-folder-plus me-1"></i>Add Category
        </button>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addItemModal">
            <i class="fa fa-plus me-1"></i>Add Item
        </button>
    </div>
</div>

<div class="tip-box mb-3">
    <i class="fa fa-eye"></i>
    <span>
        Items marked <strong>Unavailable</strong> will not appear on the POS or customer menu.
        Use this instead of deleting when an item is temporarily out of stock.
    </span>
</div>

<?php if ($msg): ?>
<div class="alert alert-success alert-dismissible fade show"><i class="fa fa-check me-2"></i><?= htmlspecialchars($msg) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>
<?php if ($err): ?>
<div class="alert alert-danger alert-dismissible fade show"><i class="fa fa-exclamation me-2"></i><?= htmlspecialchars($err) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<!-- Items by Category -->
<?php foreach ($categories as $cat):
    $catItems = array_filter($items, fn($i) => $i['category_id'] == $cat['category_id']);
    $avail    = count(array_filter($catItems, fn($i) => $i['is_available']));
?>
<div class="card shadow-sm mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span class="fw-bold"><i class="fa fa-tag me-2"></i><?= htmlspecialchars($cat['name']) ?></span>
        <div>
            <span class="badge bg-success me-1"><?= $avail ?> available</span>
            <span class="badge bg-secondary"><?= count($catItems) ?> total</span>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-sm mb-0 align-middle">
            <thead class="table-light">
                <tr><th>Name</th><th>Description</th><th class="text-end">Price (ETB)</th><th>Status</th><th>Actions</th></tr>
            </thead>
            <tbody>
            <?php if (empty($catItems)): ?>
                <tr><td colspan="5" class="text-muted text-center py-2 small">No items — click "Add Item" to add one</td></tr>
            <?php else: foreach ($catItems as $item): ?>
            <tr class="<?= $item['is_available'] ? '' : 'opacity-50' ?>">
                <td class="fw-semibold"><?= htmlspecialchars($item['name']) ?></td>
                <td class="text-muted small" style="max-width:260px;">
                    <?= htmlspecialchars(mb_substr($item['description'] ?? '', 0, 70)) ?><?= mb_strlen($item['description']??'')>70 ? '…' : '' ?>
                </td>
                <td class="text-end fw-bold" style="color:var(--primary);"><?= number_format($item['base_price'],2) ?></td>
                <td>
                    <?= $item['is_available']
                        ? '<span class="badge bg-success rounded-pill">Available</span>'
                        : '<span class="badge bg-secondary rounded-pill">Hidden</span>' ?>
                </td>
                <td>
                    <button class="btn btn-sm btn-outline-primary"
                            data-bs-toggle="modal" data-bs-target="#editItemModal"
                            data-id="<?= $item['menu_item_id'] ?>"
                            data-name="<?= htmlspecialchars($item['name']) ?>"
                            data-desc="<?= htmlspecialchars($item['description'] ?? '') ?>"
                            data-price="<?= $item['base_price'] ?>"
                            data-cat="<?= $item['category_id'] ?>">
                        <i class="fa fa-edit"></i> Edit
                    </button>
                    <form method="POST" class="d-inline">
                        <input type="hidden" name="action" value="toggle_item">
                        <input type="hidden" name="menu_item_id" value="<?= $item['menu_item_id'] ?>">
                        <button type="submit" class="btn btn-sm <?= $item['is_available'] ? 'btn-outline-warning' : 'btn-outline-success' ?> ms-1">
                            <i class="fa <?= $item['is_available'] ? 'fa-eye-slash' : 'fa-eye' ?>"></i>
                            <?= $item['is_available'] ? 'Hide' : 'Show' ?>
                        </button>
                    </form>
                </td>
            </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endforeach; ?>

<!-- Add Category Modal -->
<div class="modal fade" id="addCatModal" tabindex="-1">
  <div class="modal-dialog modal-sm">
    <div class="modal-content">
      <form method="POST">
        <input type="hidden" name="action" value="add_category">
        <div class="modal-header"><h5 class="modal-title">Add Category</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <label class="form-label fw-semibold">Category Name</label>
          <input type="text" name="cat_name" class="form-control" required placeholder="e.g. Soups, Pizza, Sides">
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary btn-sm"><i class="fa fa-plus me-1"></i>Add</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Add Item Modal -->
<div class="modal fade" id="addItemModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST">
        <input type="hidden" name="action" value="add_item">
        <div class="modal-header bg-primary text-white">
          <h5 class="modal-title"><i class="fa fa-plus me-2"></i>Add Menu Item</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label fw-semibold">Category</label>
            <select name="category_id" class="form-select" required>
              <option value="">Select a category...</option>
              <?php foreach ($categories as $c): ?>
              <option value="<?= $c['category_id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Item Name</label>
            <input type="text" name="item_name" class="form-control" required placeholder="e.g. Grilled Salmon">
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Description <span class="text-muted fw-normal">(optional)</span></label>
            <textarea name="description" class="form-control" rows="2" placeholder="Brief description shown to customers..."></textarea>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Price (ETB)</label>
            <input type="number" name="base_price" class="form-control" min="0.01" step="0.01" required placeholder="0.00">
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary"><i class="fa fa-save me-1"></i>Add Item</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Edit Item Modal -->
<div class="modal fade" id="editItemModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST">
        <input type="hidden" name="action" value="edit_item">
        <input type="hidden" name="menu_item_id" id="editItemId">
        <div class="modal-header bg-warning">
          <h5 class="modal-title"><i class="fa fa-edit me-2"></i>Edit Item</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label fw-semibold">Category</label>
            <select name="category_id" id="editItemCat" class="form-select" required>
              <?php foreach ($categories as $c): ?>
              <option value="<?= $c['category_id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Item Name</label>
            <input type="text" name="item_name" id="editItemName" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Description</label>
            <textarea name="description" id="editItemDesc" class="form-control" rows="2"></textarea>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Price (ETB)</label>
            <input type="number" name="base_price" id="editItemPrice" class="form-control" min="0.01" step="0.01" required>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-warning"><i class="fa fa-save me-1"></i>Update Item</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
document.querySelectorAll('[data-bs-target="#editItemModal"]').forEach(btn => {
    btn.addEventListener('click', () => {
        document.getElementById('editItemId').value    = btn.dataset.id;
        document.getElementById('editItemName').value  = btn.dataset.name;
        document.getElementById('editItemDesc').value  = btn.dataset.desc;
        document.getElementById('editItemPrice').value = btn.dataset.price;
        document.getElementById('editItemCat').value   = btn.dataset.cat;
    });
});
</script>
<?php include __DIR__ . '/includes/footer.php'; ?>
