<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    $isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function money($amount)
{
    return 'Rp ' . number_format((float) $amount, 0, ',', '.');
}

function logged_in()
{
    return isset($_SESSION['user_id']);
}

function require_role($role)
{
    if (!logged_in()) {
        header('Location: login.php');
        exit;
    }
    require_once __DIR__ . '/config.php';
    global $conn;
    $statement = $conn->prepare('SELECT username, role, is_active FROM users WHERE id = ?');
    $userId = (int) $_SESSION['user_id'];
    $statement->bind_param('i', $userId);
    $statement->execute();
    $user = $statement->get_result()->fetch_assoc();
    if (!$user || !(int) $user['is_active']) {
        $_SESSION = [];
        session_destroy();
        header('Location: login.php');
        exit;
    }
    $_SESSION['username'] = $user['username'];
    $_SESSION['role'] = $user['role'];
    if ($user['role'] !== $role) {
        http_response_code(403);
        exit('Akses ditolak.');
    }
}

function csrf_token()
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function require_csrf()
{
    $provided = $_POST['csrf_token'] ?? '';
    if (!is_string($provided) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $provided)) {
        http_response_code(419);
        exit('Formulir kedaluwarsa atau tidak valid. Muat ulang halaman lalu coba lagi.');
    }
}

function layout_start($title, $active = '')
{
    $user = $_SESSION['username'] ?? null;
    $role = $_SESSION['role'] ?? null;
    ?>
    <!doctype html>
    <html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title><?= e($title) ?> · Kopipiro</title>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="assets/style.css?v=<?= (int) @filemtime(__DIR__ . '/assets/style.css') ?>" rel="stylesheet">
        <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    </head>
    <body>
    <nav class="navbar navbar-expand-lg site-nav">
        <div class="container">
            <a class="navbar-brand" href="index.php"><span class="brand-mark">K</span> kopipiro<span class="brand-dot">.</span></a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#siteNav"><span class="navbar-toggler-icon"></span></button>
            <div class="collapse navbar-collapse" id="siteNav">
                <div class="navbar-nav ms-auto align-items-lg-center gap-lg-2">
                    <a class="nav-link <?= $active === 'menu' ? 'active' : '' ?>" href="index.php">Menu</a>
                    <?php if ($role === 'owner'): ?><a class="nav-link <?= $active === 'owner' ? 'active' : '' ?>" href="owner.php">Dashboard</a><?php endif; ?>
                    <?php if ($role === 'kasir'): ?><a class="nav-link <?= $active === 'cashier' ? 'active' : '' ?>" href="cashier.php">Pesanan</a><?php endif; ?>
                    <?php if ($user): ?>
                        <span class="nav-user"><?= e($user) ?></span><form method="post" action="logout.php" class="d-inline"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><button class="btn btn-outline-dark btn-sm rounded-pill px-3">Keluar</button></form>
                    <?php else: ?>
                        <a class="nav-link" href="login.php">Masuk</a><a class="btn btn-dark btn-sm rounded-pill px-3" href="register.php">Daftar</a>
                    <?php endif; ?>
                    <a class="cart-link" href="index.php?cart=open" data-cart-open aria-label="Buka keranjang">Bag <span data-cart-count><?= (int) array_sum($_SESSION['cart'] ?? []) ?></span></a>
                </div>
            </div>
        </div>
    </nav>
    <div class="cart-overlay" data-cart-overlay hidden></div>
    <aside class="cart-drawer" data-cart-drawer aria-hidden="true" aria-label="Keranjang" hidden>
        <div class="cart-drawer-head">
            <div><span class="eyebrow">KERANJANG</span><h2>Pesananmu<span class="brand-dot">.</span></h2></div>
            <button type="button" class="cart-close" data-cart-close aria-label="Tutup keranjang">×</button>
        </div>
        <div class="cart-drawer-body" data-cart-items></div>
        <div class="cart-empty" data-cart-empty>
            <span>☕</span><h3>Keranjangmu masih kosong</h3><p>Pilih menu favoritmu dulu, ya.</p>
        </div>
        <div class="cart-drawer-foot" data-cart-foot hidden>
            <div class="cart-summary-row"><span>Subtotal</span><span data-cart-subtotal></span></div>
            <div class="cart-summary-row" data-cart-discount-row hidden><span>Diskon member</span><span class="discount-value" data-cart-discount></span></div>
            <div class="cart-summary-total"><span>Total</span><strong data-cart-total></strong></div>
            <form class="cart-checkout-form" data-cart-checkout>
                <label for="cart-name">Nama penerima</label>
                <input id="cart-name" name="customer_name" autocomplete="name" required>
                <label for="cart-phone">Nomor WhatsApp</label>
                <input id="cart-phone" name="phone" type="tel" autocomplete="tel" required>
                <p class="cart-error" data-cart-error hidden></p>
                <button class="btn btn-dark rounded-pill w-100" type="submit">Pesan sekarang</button>
            </form>
        </div>
        <div class="cart-success" data-cart-success hidden>
            <div class="success-icon">✓</div>
            <h3>Pesanan diterima</h3>
            <p>Nomor pesanan <strong data-cart-order-id></strong> sudah tercatat. Sampaikan ke kasir untuk konfirmasi.</p>
            <div class="cart-summary-total"><span>Total</span><strong data-cart-order-total></strong></div>
            <a class="btn btn-dark rounded-pill w-100" href="order-success.php">Lihat detail pesanan</a>
            <button type="button" class="cart-checkout-link" data-cart-close>Kembali ke menu</button>
        </div>
    </aside>
    <main>
    <?php
}

function layout_end()
{
    ?>
    </main>
    <footer class="site-footer"><div class="container d-flex justify-content-between flex-wrap gap-2"><span>kopipiro<span class="brand-dot">.</span></span><span>Kopi yang bikin harimu berarti.</span></div></footer>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/app.js?v=<?= (int) @filemtime(__DIR__ . '/assets/app.js') ?>"></script>
    </body>
    </html>
    <?php
}

function flash($message, $type = 'success')
{
    $_SESSION['flash'] = ['message' => $message, 'type' => $type];
}

function show_flash()
{
    if (!empty($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        echo '<div class="alert alert-' . e($flash['type']) . ' alert-dismissible fade show" role="alert">' . e($flash['message']) . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
    }
}
