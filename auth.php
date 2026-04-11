<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function rbac_permissions_by_role(): array {
    return [
        'admin' => [
            'dashboard.admin.view',
            'reservation.manage',
            'product.manage',
            'customer.manage',
            'pickup.manage',
            'reports.view',
            'availability.view'
        ],
        'customer' => [
            'dashboard.customer.view',
            'reservation.create',
            'reservation.cancel',
            'reservation.view_own',
            'cart.manage_own',
            'pickup.view_own',
            'availability.view'
        ]
    ];
}

function current_user_role(): ?string {
    return $_SESSION['role'] ?? null;
}

function is_authenticated(): bool {
    return isset($_SESSION['user_id']) && isset($_SESSION['role']);
}

function has_role(string $role): bool {
    return is_authenticated() && current_user_role() === $role;
}

function has_permission(string $permission): bool {
    if (!is_authenticated()) {
        return false;
    }

    $role = current_user_role();
    $matrix = rbac_permissions_by_role();
    return isset($matrix[$role]) && in_array($permission, $matrix[$role], true);
}

function require_role(string $role, string $redirect = '../login.php'): void {
    if (!has_role($role)) {
        header("Location: $redirect");
        exit();
    }
}

function require_permission(string $permission, string $redirect = '../login.php'): void {
    if (!has_permission($permission)) {
        header("Location: $redirect");
        exit();
    }
}


