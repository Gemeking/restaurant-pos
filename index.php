<?php
session_start();
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/db.php';

if (!empty($_SESSION['staff_id'])) {
    header('Location: ' . BASE_URL . 'dashboard.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    if ($username && $password) {
        $stmt = $pdo->prepare("SELECT * FROM staff WHERE username=? AND is_active=1");
        $stmt->execute([$username]);
        $staff = $stmt->fetch();
        if ($staff && password_verify($password, $staff['password_hash'])) {
            $_SESSION['staff_id']   = $staff['staff_id'];
            $_SESSION['username']   = $staff['username'];
            $_SESSION['first_name'] = $staff['first_name'];
            $_SESSION['last_name']  = $staff['last_name'];
            $_SESSION['role']       = $staff['role'];
            header('Location: ' . BASE_URL . ($staff['role']==='Kitchen' ? 'kds.php' : 'dashboard.php'));
            exit;
        }
        $error = 'Incorrect username or password. Please try again.';
    } else {
        $error = 'Please enter both username and password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link href="<?= BASE_URL ?>assets/css/style.css" rel="stylesheet">
</head>
<body class="login-page">

<div class="login-card">
    <!-- Header -->
    <div class="login-header">
        <div style="width:70px;height:70px;background:rgba(255,255,255,.2);border-radius:20px;display:flex;align-items:center;justify-content:center;margin:0 auto 1rem;" >
            <i class="fa fa-utensils fa-2x"></i>
        </div>
        <h3 class="fw-bold mb-1"><?= APP_NAME ?></h3>
        <p class="mb-0" style="opacity:.8;font-size:.9rem;">Order Management &amp; Payment System</p>
    </div>

    <!-- Form -->
    <div class="bg-white p-4">
        <?php if ($error): ?>
        <div class="alert alert-danger py-2 small">
            <i class="fa fa-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>

        <form method="POST">
            <div class="mb-3">
                <label class="form-label fw-semibold">Username</label>
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0"><i class="fa fa-user text-muted"></i></span>
                    <input type="text" name="username" class="form-control border-start-0"
                           placeholder="Enter your username"
                           value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                           required autofocus>
                </div>
            </div>
            <div class="mb-4">
                <label class="form-label fw-semibold">Password</label>
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0"><i class="fa fa-lock text-muted"></i></span>
                    <input type="password" name="password" class="form-control border-start-0"
                           placeholder="Enter your password" required>
                </div>
            </div>
            <button type="submit" class="btn btn-danger btn-lg w-100 fw-bold" style="background:var(--primary);border-color:var(--primary);">
                <i class="fa fa-sign-in-alt me-2"></i>Login
            </button>
        </form>

        <hr class="my-3">

        <!-- Demo Accounts -->
        <p class="text-center text-muted small mb-2"><strong>Demo Accounts</strong> — password is <code>password</code> for all</p>
        <div class="row g-2">
            <div class="col-4">
                <div class="text-center p-2 rounded" style="background:#fff5f5;border:1px solid #fecaca;">
                    <i class="fa fa-crown text-danger d-block mb-1"></i>
                    <div style="font-size:.75rem;font-weight:700;">manager1</div>
                    <div style="font-size:.68rem;color:#888;">Full access</div>
                </div>
            </div>
            <div class="col-4">
                <div class="text-center p-2 rounded" style="background:#f0f9ff;border:1px solid #bae6fd;">
                    <i class="fa fa-utensils text-primary d-block mb-1"></i>
                    <div style="font-size:.75rem;font-weight:700;">waiter1</div>
                    <div style="font-size:.68rem;color:#888;">POS + Orders</div>
                </div>
            </div>
            <div class="col-4">
                <div class="text-center p-2 rounded" style="background:#f0fdf4;border:1px solid #bbf7d0;">
                    <i class="fa fa-fire text-success d-block mb-1"></i>
                    <div style="font-size:.75rem;font-weight:700;">kitchen1</div>
                    <div style="font-size:.68rem;color:#888;">Kitchen only</div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
