<?php
require 'auth.php';
require 'config.php';

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
$discount = logged_in() && $_SESSION['role'] === 'customer';

layout_start('Menu', 'menu');
?>
<section class="hero-section">
    <div class="container hero-grid">
        <div class="hero-copy"><span class="eyebrow">KOPI LOKAL, CERITA PERSONAL</span><h1>Mulai hari<br> dengan <em>rasa.</em></h1><p>Secangkir kopi yang diracik dengan hati. Temukan favoritmu, nikmati harimu.</p><a class="btn btn-dark rounded-pill px-4 py-3" href="#menu">Jelajahi menu <span class="ms-2">↘</span></a><div class="hero-note"><span>★★★★★</span><small>Diracik segar setiap hari</small></div></div>
        <div class="hero-visual"><div class="hero-image"></div><div class="hero-stamp">GOOD<br>MOOD<br>BREWED</div><div class="hero-caption">01 / YOUR DAILY PAUSE</div></div>
    </div>
</section>
<section class="menu-section" id="menu">
    <div class="container">
        <div class="section-heading"><div><span class="eyebrow">DIBUAT DENGAN HATI</span><h2>Menu favoritmu<span class="brand-dot">.</span></h2></div><form class="search-form" method="get"><input name="q" type="search" value="<?= e($search) ?>" placeholder="Cari menu..."><button aria-label="Cari">⌕</button></form></div>
        <?php if ($discount): ?><div class="discount-banner"><span>Member Kopipiro</span><strong>Nikmati diskon 10% untuk semua pesananmu.</strong></div><?php endif; ?>
        <div class="category-row"><a class="category-chip <?= $category === '' ? 'selected' : '' ?>" href="index.php<?= $search ? '?q=' . urlencode($search) : '' ?>">Semua menu</a><?php foreach ($categories as $item): ?><a class="category-chip <?= $category === $item['slug'] ? 'selected' : '' ?>" href="?category=<?= e($item['slug']) ?><?= $search ? '&q=' . urlencode($search) : '' ?>"><?= e($item['name']) ?></a><?php endforeach; ?></div>
        <div class="product-grid">
            <?php foreach ($products as $product): $price = (int) $product['price']; $finalPrice = $discount ? (int) round($price * .9) : $price; ?>
                <article class="product-card"><a href="product.php?id=<?= (int) $product['id'] ?>" class="product-photo-wrap"><img class="product-photo" src="<?= e($product['image'] ?: 'assets/img/coffee.svg') ?>" alt="<?= e($product['name']) ?>" loading="lazy"><?php if ($product['is_featured']): ?><span class="photo-tag">PILIHAN KAMI</span><?php endif; ?></a><div class="product-info"><div class="product-meta"><span><?= e($product['category_name']) ?></span><span>★ <?= $product['rating'] ? e($product['rating']) : 'Baru' ?></span></div><h3><a href="product.php?id=<?= (int) $product['id'] ?>"><?= e($product['name']) ?></a></h3><p><?= e($product['description']) ?></p><div class="product-bottom"><div><?php if ($discount): ?><del><?= money($price) ?></del><?php endif; ?><strong><?= money($finalPrice) ?></strong></div><form method="post" action="cart.php"><input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>"><input type="hidden" name="action" value="add"><button class="add-button" aria-label="Tambah <?= e($product['name']) ?>">+</button></form></div></div></article>
            <?php endforeach; ?>
            <?php if (!$products): ?><div class="empty-state"><span>☕</span><h3>Menu belum ditemukan</h3><p>Coba kata kunci atau kategori yang lain.</p></div><?php endif; ?>
        </div>
    </div>
</section>
<section class="about-section" id="about"><div class="container about-inner"><span class="eyebrow">SEDIKIT TENTANG KAMI</span><h2>Ruang kecil untuk<br>jeda yang <em>berarti.</em></h2><p>Kopipiro percaya hal baik dimulai dari hal sederhana: biji pilihan, racikan yang pas, dan waktu untuk menikmatinya.</p></div></section>
<?php layout_end(); ?>
