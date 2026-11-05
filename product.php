<?php
require 'auth.php';
require 'config.php';
$id = (int) ($_GET['id'] ?? 0);
$statement = $conn->prepare('SELECT p.*, c.name AS category_name, (SELECT ROUND(AVG(r.rating), 1) FROM reviews r WHERE r.product_id = p.id) AS rating FROM products p JOIN categories c ON c.id = p.category_id WHERE p.id = ? AND p.is_available = 1');
$statement->bind_param('i', $id);
$statement->execute();
$product = $statement->get_result()->fetch_assoc();
if (!$product) { http_response_code(404); exit('Menu tidak ditemukan.'); }
$ratingStatement = $conn->prepare('SELECT r.rating, r.comment, u.name FROM reviews r JOIN users u ON u.id = r.customer_id WHERE r.product_id = ? ORDER BY r.created_at DESC LIMIT 8');
$ratingStatement->bind_param('i', $id);
$ratingStatement->execute();
$reviews = $ratingStatement->get_result()->fetch_all(MYSQLI_ASSOC);
$price = (int) $product['price'];
$discounted = logged_in() && $_SESSION['role'] === 'customer';
layout_start($product['name']);
?>
<section class="page-section"><div class="container"><a href="index.php#menu" class="back-link">← Kembali ke menu</a><div class="detail-layout"><div class="detail-photo"><img src="<?= e($product['image'] ?: 'assets/img/coffee.svg') ?>" alt="<?= e($product['name']) ?>"></div><div class="detail-info"><span class="eyebrow"><?= e($product['category_name']) ?></span><h1><?= e($product['name']) ?></h1><div class="detail-rating">★ <?= $product['rating'] ? e($product['rating']) : 'Belum ada rating' ?></div><p><?= e($product['description']) ?></p><div class="detail-price"><?php if ($discounted): ?><del><?= money($price) ?></del><?php endif; ?><strong><?= money($discounted ? round($price * .9) : $price) ?></strong></div><form method="post" action="cart.php"><input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>"><input type="hidden" name="action" value="add"><button class="btn btn-dark btn-lg rounded-pill px-5">Tambah ke keranjang</button></form></div></div><div class="review-section"><h2>Ulasan pelanggan</h2><?php if ($reviews): foreach ($reviews as $review): ?><div class="review-row"><strong><?= e($review['name']) ?></strong><span>★ <?= (int) $review['rating'] ?>/5</span><p><?= e($review['comment']) ?></p></div><?php endforeach; else: ?><p class="muted">Belum ada ulasan untuk menu ini.</p><?php endif; ?></div></div></section>
<?php layout_end(); ?>
