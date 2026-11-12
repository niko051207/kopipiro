<?php
require_once __DIR__ . '/helpers.php';

$category = trim($_GET['category'] ?? '');
$search = trim($_GET['q'] ?? '');
$sql = 'SELECT p.*, c.name AS category_name, (SELECT ROUND(AVG(r.rating), 1) FROM reviews r WHERE r.product_id = p.id) AS rating FROM products p JOIN categories c ON c.id = p.category_id WHERE p.is_available = 1';
$types = '';
$values = [];
if ($category !== '') {
    $sql .= ' AND c.slug = ?';
    $types .= 's';
    $values[] = $category;
}
if ($search !== '') {
    $sql .= ' AND (p.name LIKE ? OR p.description LIKE ?)';
    $types .= 'ss';
    $values[] = '%' . $search . '%';
    $values[] = '%' . $search . '%';
}
$sql .= ' ORDER BY p.is_featured DESC, p.name';
$statement = $conn->prepare($sql);
if ($values) $statement->bind_param($types, ...$values);
$statement->execute();
$products = $statement->get_result()->fetch_all(MYSQLI_ASSOC);
$categories = $conn->query('SELECT name, slug FROM categories ORDER BY sort_order, name')->fetch_all(MYSQLI_ASSOC);
$member = me();
$discount = $member !== null && $member['role'] === 'customer';

layout_start('Menu', 'menu');
?>
<section class="menu-section" id="menu">
    <div class="container">
        <div class="section-heading"><div><span class="eyebrow">DIBUAT DENGAN HATI</span><h2>Menu favoritmu<span class="brand-dot">.</span></h2></div><form class="search-form" method="get"><input name="q" type="search" value="<?= e($search) ?>" placeholder="Cari menu..."><button aria-label="Cari">⌕</button></form></div>
        <?php if ($discount): ?><div class="discount-banner"><span>Member Kopipiro</span><strong>Nikmati diskon 10% untuk semua pesananmu.</strong></div><?php endif; ?>
        <div class="category-row"><a class="category-chip <?= $category === '' ? 'selected' : '' ?>" href="index.php<?= $search ? '?q=' . urlencode($search) : '' ?>">Semua menu</a><?php foreach ($categories as $item): ?><a class="category-chip <?= $category === $item['slug'] ? 'selected' : '' ?>" href="?category=<?= e($item['slug']) ?><?= $search ? '&q=' . urlencode($search) : '' ?>"><?= e($item['name']) ?></a><?php endforeach; ?></div>
        <div class="product-grid">
            <?php foreach ($products as $product): $price = (int) $product['price']; $finalPrice = final_price($price, $member); ?>
                <article class="product-card"><a href="product.php?id=<?= (int) $product['id'] ?>" class="product-photo-wrap"><img class="product-photo" src="<?= e(image_url($product['image'])) ?>" alt="<?= e($product['name']) ?>" loading="lazy"><?php if ($product['is_featured']): ?><span class="photo-tag">PILIHAN KAMI</span><?php endif; ?></a><div class="product-info"><div class="product-meta"><span><?= e($product['category_name']) ?></span><span>★ <?= $product['rating'] ? e($product['rating']) : 'Baru' ?></span></div><h3><a href="product.php?id=<?= (int) $product['id'] ?>"><?= e($product['name']) ?></a></h3><p><?= e($product['description']) ?></p><div class="product-bottom"><div><?php if ($discount): ?><del><?= money($price) ?></del><?php endif; ?><strong><?= money($finalPrice) ?></strong></div><form method="post" action="cart.php" class="js-add-to-cart"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>"><input type="hidden" name="action" value="add"><button class="add-button" aria-label="Tambah <?= e($product['name']) ?>">+</button></form></div></div></article>
            <?php endforeach; ?>
            <?php if (!$products): ?><div class="empty-state"><span>☕</span><h3>Menu belum ditemukan</h3><p>Coba kata kunci atau kategori yang lain.</p></div><?php endif; ?>
        </div>
    </div>
</section>
<?php layout_end(); ?>
