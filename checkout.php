<?php
require 'auth.php';
require 'config.php';

$cart = $_SESSION['cart'] ?? [];
$items = [];
$subtotal = 0;
if ($cart) {
    $ids = array_map('intval', array_keys($cart));
    $result = $conn->query('SELECT id, name, price, image FROM products WHERE is_available = 1 AND id IN (' . implode(',', $ids) . ')');
    while ($product = $result->fetch_assoc()) {
        $product['quantity'] = (int) $cart[$product['id']];
        $product['discounted_unit'] = logged_in() && $_SESSION['role'] === 'customer' ? (int) round((int) $product['price'] * .9) : (int) $product['price'];
        $product['line_total'] = $product['discounted_unit'] * $product['quantity'];
        $subtotal += $product['line_total'];
        $items[] = $product;
    }
}
$discount = logged_in() && $_SESSION['role'] === 'customer' ? max(0, (int) array_sum(array_map(static fn($item) => (int) $item['price'] * (int) $item['quantity'] - (int) $item['line_total'], $items))) : 0;
$total = $subtotal - $discount;
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $items) {
    $name = logged_in() ? $_SESSION['username'] : trim($_POST['customer_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    if ($name === '' || $phone === '') {
        $error = 'Isi nama dan nomor telepon untuk melanjutkan.';
    } else {
        try {
            $conn->begin_transaction();
            $userId = logged_in() && $_SESSION['role'] === 'customer' ? (int) $_SESSION['user_id'] : null;
            $status = 'pending';
            $statement = $conn->prepare('INSERT INTO orders (customer_id, cashier_id, customer_name, phone, subtotal, discount, total, status) VALUES (?, NULL, ?, ?, ?, ?, ?, ?)');
            $statement->bind_param('issiiis', $userId, $name, $phone, $subtotal, $discount, $total, $status);
            $statement->execute();
            $orderId = $conn->insert_id;
            $line = $conn->prepare('INSERT INTO order_items (order_id, product_id, product_name, quantity, unit_price, line_total) VALUES (?, ?, ?, ?, ?, ?)');
            foreach ($items as $item) {
                $productId = (int) $item['id'];
                $productName = $item['name'];
                $quantity = (int) $item['quantity'];
                $unitPrice = (int) $item['discounted_unit'];
                $lineTotal = (int) $item['line_total'];
                $line->bind_param('iisiii', $orderId, $productId, $productName, $quantity, $unitPrice, $lineTotal);
                $line->execute();
            }
            $conn->commit();
            $_SESSION['cart'] = [];
            $_SESSION['last_order'] = $orderId;
            header('Location: order-success.php');
            exit;
        } catch (Throwable $exception) {
            $conn->rollback();
            $error = 'Pesanan belum dapat disimpan. Coba kembali.';
        }
    }
}
layout_start('Checkout');
?>
<section class="page-section"><div class="container"><div class="page-title"><span class="eyebrow">TINGGAL SELANGKAH LAGI</span><h1>Keranjang <em>belanjamu.</em></h1></div><?php show_flash(); ?><?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?><div class="checkout-layout"><div class="checkout-items">
<?php if (!$items): ?><div class="empty-state"><span>☕</span><h3>Keranjangmu masih kosong</h3><p>Yuk, pilih sesuatu yang enak.</p><a class="btn btn-dark rounded-pill px-4" href="index.php#menu">Lihat menu</a></div><?php else: foreach ($items as $item): ?><div class="checkout-item"><img src="<?= e($item['image'] ?: 'assets/img/coffee.svg') ?>" alt=""><div class="checkout-product"><h3><?= e($item['name']) ?></h3><strong><?= money($item['price']) ?></strong></div><form method="post" action="cart.php" class="quantity-form"><input type="hidden" name="product_id" value="<?= (int) $item['id'] ?>"><input type="hidden" name="action" value="set"><button name="quantity" value="<?= max(0, $item['quantity'] - 1) ?>">−</button><span><?= (int) $item['quantity'] ?></span><button name="quantity" value="<?= min(20, $item['quantity'] + 1) ?>">+</button></form><strong class="line-total"><?= money($item['line_total']) ?></strong></div><?php endforeach; endif; ?></div>
<?php if ($items): ?><aside class="summary-card"><h2>Ringkasan</h2><div class="summary-row"><span>Subtotal</span><span><?= money($subtotal) ?></span></div><div class="summary-row"><span>Diskon member</span><span class="discount-value">− <?= money($discount) ?></span></div><div class="summary-total"><span>Total</span><strong><?= money($total) ?></strong></div><?php if (!logged_in() || $_SESSION['role'] !== 'customer'): ?><p class="summary-hint">Masuk atau daftar untuk mendapat diskon 10%.</p><?php endif; ?><form method="post"><label class="form-label" for="customer_name">Nama penerima</label><input class="form-control mb-3" id="customer_name" name="customer_name" value="<?= e(logged_in() ? $_SESSION['username'] : ($_POST['customer_name'] ?? '')) ?>" <?= logged_in() && $_SESSION['role'] === 'customer' ? 'readonly' : 'required' ?>><label class="form-label" for="phone">Nomor WhatsApp</label><input class="form-control mb-4" id="phone" name="phone" value="<?= e($_POST['phone'] ?? '') ?>" required><button class="btn btn-dark btn-lg rounded-pill w-100">Buat pesanan · <?= money($total) ?></button></form><small class="secure-note">Pembayaran dilakukan di kasir.</small></aside><?php endif; ?></div></div></section>
<?php layout_end(); ?>
