<?php
session_start();
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
requireRole(['Manager']);

$page_title = 'Staff Management — ' . APP_NAME;
$page_name  = 'Staff Management';
$msg = $err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $username   = trim($_POST['username'] ?? '');
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name  = trim($_POST['last_name'] ?? '');
        $role       = $_POST['role'] ?? '';
        $password   = $_POST['password'] ?? '';

        if (!$username || !$first_name || !$last_name || !in_array($role,['Manager','Waiter','Kitchen']) || strlen($password)<6) {
            $err = 'All fields are required and password must be at least 6 characters.';
        } else {
            $exists = $pdo->prepare("SELECT staff_id FROM staff WHERE username=?");
            $exists->execute([$username]);
            if ($exists->fetch()) {
                $err = 'That username is already taken. Please choose another.';
            } else {
                $pdo->prepare("INSERT INTO staff (username,first_name,last_name,role,password_hash) VALUES (?,?,?,?,?)")
                    ->execute([$username,$first_name,$last_name,$role,password_hash($password,PASSWORD_BCRYPT)]);
                $msg = "Staff member {$first_name} {$last_name} added successfully.";
            }
        }
    }

    if ($action === 'toggle') {
        $staff_id = (int)($_POST['staff_id'] ?? 0);
        if ($staff_id === (int)$_SESSION['staff_id']) {
            $err = 'You cannot deactivate your own account.';
        } else {
            $pdo->prepare("UPDATE staff SET is_active = NOT is_active WHERE staff_id=?")->execute([$staff_id]);
            $msg = 'Staff status updated.';
        }
    }

    if ($action === 'reset_password') {
        $staff_id = (int)($_POST['staff_id'] ?? 0);
        $newpw    = $_POST['new_password'] ?? '';
        if (strlen($newpw) < 6) {
            $err = 'New password must be at least 6 characters.';
        } else {
            $pdo->prepare("UPDATE staff SET password_hash=? WHERE staff_id=?")
                ->execute([password_hash($newpw,PASSWORD_BCRYPT), $staff_id]);
            $msg = 'Password has been reset successfully.';
        }
    }
}

$staff_list = $pdo->query("SELECT * FROM staff ORDER BY role, first_name")->fetchAll();
include __DIR__ . '/includes/header.php';
?>

<!-- Page Header -->
<div class="page-header">
    <div class="page-header-text">
        <h4><i class="fa fa-users me-2 text-primary"></i>Staff Management</h4>
        <p>Add new staff accounts, change roles, reset passwords or deactivate accounts.</p>
    </div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addStaffModal">
        <i class="fa fa-user-plus me-1"></i>Add New Staff
    </button>
</div>

<!-- Tip -->
<div class="tip-box mb-3">
    <i class="fa fa-info-circle"></i>
    <span>
        There are 3 roles: <strong>Manager</strong> (full access), <strong>Waiter</strong> (POS + orders),
        <strong>Kitchen</strong> (Kitchen Display only). Staff cannot delete orders or view reports.
    </span>
</div>

<?php if ($msg): ?>
<div class="alert alert-success alert-dismissible fade show">
    <i class="fa fa-check-circle me-2"></i><?= htmlspecialchars($msg) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>
<?php if ($err): ?>
<div class="alert alert-danger alert-dismissible fade show">
    <i class="fa fa-exclamation-circle me-2"></i><?= htmlspecialchars($err) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Staff Table -->
<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-dark">
                <tr><th>Name</th><th>Username</th><th>Role</th><th>Status</th><th>Actions</th></tr>
            </thead>
            <tbody>
            <?php foreach ($staff_list as $s):
                $role_cls = match($s['role']) {
                    'Manager' => 'bg-danger',
                    'Waiter'  => 'bg-primary',
                    'Kitchen' => 'bg-success',
                    default   => 'bg-secondary'
                };
            ?>
            <tr class="<?= $s['is_active'] ? '' : 'opacity-50' ?>">
                <td>
                    <div class="d-flex align-items-center gap-2">
                        <div style="width:36px;height:36px;background:<?= $s['role']==='Manager' ? '#e74c3c' : ($s['role']==='Waiter' ? '#3498db' : '#27ae60') ?>;border-radius:50%;display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:.8rem;">
                            <?= strtoupper(substr($s['first_name'],0,1).substr($s['last_name'],0,1)) ?>
                        </div>
                        <div>
                            <div class="fw-semibold"><?= htmlspecialchars($s['first_name'].' '.$s['last_name']) ?></div>
                            <div class="text-muted small">Added <?= date('d M Y', strtotime($s['created_at'])) ?></div>
                        </div>
                    </div>
                </td>
                <td><code class="bg-light px-2 py-1 rounded"><?= htmlspecialchars($s['username']) ?></code></td>
                <td><span class="badge <?= $role_cls ?> rounded-pill"><?= $s['role'] ?></span></td>
                <td>
                    <?php if ($s['is_active']): ?>
                        <span class="badge bg-success rounded-pill"><i class="fa fa-circle me-1" style="font-size:.5rem;"></i>Active</span>
                    <?php else: ?>
                        <span class="badge bg-secondary rounded-pill"><i class="fa fa-circle me-1" style="font-size:.5rem;"></i>Inactive</span>
                    <?php endif; ?>
                </td>
                <td>
                    <form method="POST" class="d-inline">
                        <input type="hidden" name="action" value="toggle">
                        <input type="hidden" name="staff_id" value="<?= $s['staff_id'] ?>">
                        <button type="submit" class="btn btn-sm <?= $s['is_active'] ? 'btn-outline-warning' : 'btn-outline-success' ?>"
                                <?= $s['staff_id']==$_SESSION['staff_id'] ? 'disabled title="Cannot deactivate yourself"' : '' ?>>
                            <i class="fa <?= $s['is_active'] ? 'fa-user-slash' : 'fa-user-check' ?> me-1"></i>
                            <?= $s['is_active'] ? 'Deactivate' : 'Activate' ?>
                        </button>
                    </form>
                    <button class="btn btn-sm btn-outline-secondary ms-1"
                            data-bs-toggle="modal" data-bs-target="#resetPwModal"
                            data-id="<?= $s['staff_id'] ?>"
                            data-name="<?= htmlspecialchars($s['first_name'].' '.$s['last_name']) ?>">
                        <i class="fa fa-key me-1"></i>Reset PW
                    </button>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add Staff Modal -->
<div class="modal fade" id="addStaffModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST">
        <input type="hidden" name="action" value="add">
        <div class="modal-header bg-dark text-white">
          <h5 class="modal-title"><i class="fa fa-user-plus me-2"></i>Add New Staff Member</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-6">
              <label class="form-label fw-semibold">First Name</label>
              <input type="text" name="first_name" class="form-control" required placeholder="e.g. Tigist">
            </div>
            <div class="col-6">
              <label class="form-label fw-semibold">Last Name</label>
              <input type="text" name="last_name" class="form-control" required placeholder="e.g. Haile">
            </div>
            <div class="col-6">
              <label class="form-label fw-semibold">Username</label>
              <input type="text" name="username" class="form-control" required pattern="[a-zA-Z0-9_]+" placeholder="e.g. waiter3">
              <div class="form-text">Letters, numbers and underscore only.</div>
            </div>
            <div class="col-6">
              <label class="form-label fw-semibold">Role</label>
              <select name="role" class="form-select" required>
                <option value="">Select role...</option>
                <option value="Waiter">Waiter / Cashier</option>
                <option value="Kitchen">Kitchen Staff</option>
                <option value="Manager">Manager</option>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label fw-semibold">Password</label>
              <input type="password" name="password" class="form-control" required minlength="6" placeholder="Minimum 6 characters">
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary"><i class="fa fa-save me-1"></i>Add Staff</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Reset Password Modal -->
<div class="modal fade" id="resetPwModal" tabindex="-1">
  <div class="modal-dialog modal-sm">
    <div class="modal-content">
      <form method="POST">
        <input type="hidden" name="action" value="reset_password">
        <input type="hidden" name="staff_id" id="resetPwId">
        <div class="modal-header"><h5 class="modal-title"><i class="fa fa-key me-2"></i>Reset Password</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
          <p class="text-muted small mb-3">Setting new password for: <strong id="resetPwName"></strong></p>
          <input type="password" name="new_password" class="form-control" placeholder="New password (min 6 chars)" minlength="6" required>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-warning btn-sm"><i class="fa fa-key me-1"></i>Reset Password</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
document.querySelectorAll('[data-bs-target="#resetPwModal"]').forEach(btn => {
    btn.addEventListener('click', () => {
        document.getElementById('resetPwId').value = btn.dataset.id;
        document.getElementById('resetPwName').textContent = btn.dataset.name;
    });
});
</script>
<?php include __DIR__ . '/includes/footer.php'; ?>
