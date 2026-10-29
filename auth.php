<?php
// auth.php — session + role helpers. Include this FIRST on every page.

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/helpers.php';

/** The logged-in user as stored in the session, or null for a guest. */
function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }

    return [
        'id'       => (int) $_SESSION['user_id'],
        'username' => $_SESSION['username'] ?? '',
        'role'     => $_SESSION['role'] ?? '',
    ];
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

function has_role(string ...$roles): bool
{
    $user = current_user();

    return $user !== null && in_array($user['role'], $roles, true);
}

function is_owner(): bool
{
    return has_role('owner');
}

function is_kasir(): bool
{
    return has_role('kasir');
}

/** Only this decides the 10% discount. One source of truth. */
function is_customer(): bool
{
    return has_role('customer');
}

/** Send guests to the login page, remembering where they wanted to go. */
function require_login(): void
{
    if (!is_logged_in()) {
        flash('warning', 'Silakan login terlebih dahulu.');
        redirect('login.php');
    }
}

function require_role(string ...$roles): void
{
    require_login();

    if (!has_role(...$roles)) {
        http_response_code(403);
        require __DIR__ . '/403.php';
        exit;
    }
}

/** Where a user should land after login. */
function home_for_role(string $role): string
{
    return match ($role) {
        'owner'    => 'admin/dashboard.php',
        'kasir'    => 'cashier/orders.php',
        'customer' => 'index.php',
        default    => 'index.php',
    };
}
