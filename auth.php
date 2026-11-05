<?php
session_start();

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
    if (!logged_in() || $_SESSION['role'] !== $role) {
        header('Location: login.php');
        exit;
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
        <link href="assets/style.css" rel="stylesheet">
    </head>
    <body>
    <nav class="navbar navbar-expand-lg site-nav">
        <div class="container">
            <a class="navbar-brand" href="index.php"><span class="brand-mark">K</span> kopipiro<span class="brand-dot">.</span></a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#siteNav"><span class="navbar-toggler-icon"></span></button>
            <div class="collapse navbar-collapse" id="siteNav">
                <div class="navbar-nav ms-auto align-items-lg-center gap-lg-2">
                    <a class="nav-link <?= $active === 'menu' ? 'active' : '' ?>" href="index.php">Menu</a>
                    <a class="nav-link" href="index.php#about">Tentang</a>
                    <?php if ($role === 'owner'): ?><a class="nav-link <?= $active === 'owner' ? 'active' : '' ?>" href="owner.php">Dashboard</a><?php endif; ?>
                    <?php if ($role === 'kasir'): ?><a class="nav-link <?= $active === 'cashier' ? 'active' : '' ?>" href="cashier.php">Pesanan</a><?php endif; ?>
                    <?php if ($user): ?>
                        <span class="nav-user"><?= e($user) ?></span><a class="btn btn-outline-dark btn-sm rounded-pill px-3" href="logout.php">Keluar</a>
                    <?php else: ?>
                        <a class="nav-link" href="login.php">Masuk</a><a class="btn btn-dark btn-sm rounded-pill px-3" href="register.php">Daftar</a>
                    <?php endif; ?>
                    <a class="cart-link" href="checkout.php" aria-label="Keranjang">Bag <span><?= array_sum($_SESSION['cart'] ?? []) ?></span></a>
                </div>
            </div>
        </div>
    </nav>
    <main>
    <?php
}

function layout_end()
{
    ?>
    </main>
    <footer class="site-footer"><div class="container d-flex justify-content-between flex-wrap gap-2"><span>kopipiro<span class="brand-dot">.</span></span><span>Kopi yang bikin harimu berarti.</span></div></footer>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/app.js"></script>
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
