<?php
// navbar.php — include right after <body>. Pulls in auth.php itself so the
// role check can never be "forgotten" by a page.
require_once __DIR__ . '/auth.php';

$navUser  = current_user();
$navLinks = [];

if (is_owner()) {
    $navLinks[] = ['admin/dashboard.php', 'Dashboard'];
} elseif (is_kasir()) {
    $navLinks[] = ['cashier/orders.php', 'Pesanan Saya'];
}
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container">
        <a class="navbar-brand fw-semibold" href="<?= e(url('index.php')) ?>">Kopipiro</a>

        <div class="d-flex align-items-center gap-2">
            <?php foreach ($navLinks as [$href, $label]): ?>
                <a href="<?= e(url($href)) ?>" class="btn btn-outline-light btn-sm"><?= e($label) ?></a>
            <?php endforeach; ?>

            <?php if ($navUser): ?>
                <span class="text-white small d-none d-sm-inline">
                    <?= e($navUser['username']) ?>
                    <span class="badge bg-secondary text-uppercase"><?= e($navUser['role']) ?></span>
                </span>
                <a href="<?= e(url('logout.php')) ?>" class="btn btn-danger btn-sm">Logout</a>
            <?php else: ?>
                <a href="<?= e(url('login.php')) ?>" class="btn btn-outline-light btn-sm">Login</a>
                <a href="<?= e(url('register.php')) ?>" class="btn btn-warning btn-sm">Daftar</a>
            <?php endif; ?>
        </div>
    </div>
</nav>

<?php if (!empty($_SESSION['flash'])): ?>
    <div class="container mt-3">
        <?php render_flashes(); ?>
    </div>
<?php endif; ?>
