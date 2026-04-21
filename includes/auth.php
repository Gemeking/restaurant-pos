<?php
// Authentication helpers — include after session_start()

function requireLogin() {
    if (empty($_SESSION['staff_id'])) {
        header('Location: ' . BASE_URL . 'index.php');
        exit;
    }
}

function requireRole(array $roles) {
    requireLogin();
    if (!in_array($_SESSION['role'], $roles)) {
        header('Location: ' . BASE_URL . 'dashboard.php?error=access_denied');
        exit;
    }
}

function getCurrentUser(): array {
    return [
        'staff_id'   => $_SESSION['staff_id']   ?? null,
        'username'   => $_SESSION['username']   ?? '',
        'first_name' => $_SESSION['first_name'] ?? '',
        'last_name'  => $_SESSION['last_name']  ?? '',
        'role'       => $_SESSION['role']        ?? '',
    ];
}

function isLoggedIn(): bool {
    return !empty($_SESSION['staff_id']);
}

function hasRole(string $role): bool {
    return ($_SESSION['role'] ?? '') === $role;
}
