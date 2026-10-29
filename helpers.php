<?php
// helpers.php — small shared functions. Included by auth.php, so session is already started.

/** Escape any value before printing it into HTML. */
function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/** 25000 -> "Rp 25.000" */
function rupiah($amount): string
{
    return 'Rp ' . number_format((float) $amount, 0, ',', '.');
}

/**
 * Absolute path to an app file, so links keep working from admin/ and cashier/.
 * Works both on a vhost root (/) and under localhost/kopipiro/.
 */
function url(string $path = ''): string
{
    static $base = null;

    if ($base === null) {
        $appRoot = str_replace('\\', '/', __DIR__);
        $docRoot = str_replace('\\', '/', rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/'));

        $base = ($docRoot !== '' && str_starts_with($appRoot, $docRoot))
            ? rtrim(substr($appRoot, strlen($docRoot)), '/')
            : '';
    }

    $path = ltrim($path, '/');

    return $base . '/' . $path;
}

function redirect(string $path): never
{
    header('Location: ' . url($path));
    exit;
}

/** Queue a one-time message: flash('success', '...') */
function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

/** Read and clear queued messages. */
function take_flashes(): array
{
    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);

    return $flashes;
}

/** Render queued messages (Bootstrap alerts). Pages without the navbar call this too. */
function render_flashes(): void
{
    $flashes = take_flashes();

    if (!$flashes) {
        return;
    }

    echo '<div class="mb-3">';
    foreach ($flashes as $f) {
        $type = in_array($f['type'], ['success', 'danger', 'warning', 'info'], true) ? $f['type'] : 'info';
        echo '<div class="alert alert-' . $type . ' alert-dismissible fade show py-2" role="alert">'
            . e($f['message'])
            . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>'
            . '</div>';
    }
    echo '</div>';
}

/** Bootstrap badge for an order status. */
function status_badge(string $status): string
{
    $map = [
        'pending'    => 'secondary',
        'processing' => 'info text-dark',
        'completed'  => 'success',
        'cancelled'  => 'danger',
    ];
    $class = $map[$status] ?? 'secondary';

    return '<span class="badge bg-' . $class . '">' . e(ucfirst($status)) . '</span>';
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/** Reject a POST whose token is missing or wrong. */
function csrf_check(): void
{
    $sent = $_POST['csrf_token'] ?? '';

    if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) {
        http_response_code(419);
        die('Sesi form sudah kedaluwarsa. Muat ulang halaman lalu coba lagi.');
    }
}
