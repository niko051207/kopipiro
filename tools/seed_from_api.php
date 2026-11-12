<?php
/**
 * Import coffee products from a public API into our own database.
 *
 * Run once, from the project folder:
 *   C:\laragon\bin\php\php-8.3.33-Win32-vs16-x64\php.exe tools\seed_from_api.php
 *
 * The public API (Sample APIs Coffee) has names, descriptions and ingredients,
 * but no prices, so prices are invented. Importing into our own database — instead
 * of calling the API on every page view — keeps the shop fast and independent of
 * a service we do not control.
 *
 * The script is idempotent: running it twice does not duplicate products.
 */

require_once __DIR__ . '/../helpers.php';

const COFFEE_ENDPOINT = 'https://api.sampleapis.com/coffee/hot';

$list = http_get_json(COFFEE_ENDPOINT, 20);
if (isset($list['error'])) {
    fwrite(STDERR, 'Public API failed (' . $list['error'] . "). Try again later.\n");
    exit(1);
}
if (!array_is_list($list) || !$list) {
    fwrite(STDERR, "Public API returned no usable coffee list.\n");
    exit(1);
}

// The catalogue already has a Kopi category in database.sql; create it if missing.
$conn->query("INSERT INTO categories (name, slug, sort_order)
              SELECT 'Kopi', 'kopi', 1 FROM DUAL
              WHERE NOT EXISTS (SELECT 1 FROM categories WHERE slug = 'kopi')");
$categoryId = (int) $conn->query("SELECT id FROM categories WHERE slug = 'kopi'")->fetch_column();

$exists = $conn->prepare('SELECT COUNT(*) FROM products WHERE name = ?');
$insert = $conn->prepare(
    'INSERT INTO products (category_id, name, slug, description, price, image, is_featured, is_available)
     VALUES (?, ?, ?, ?, ?, ?, 0, 1)'
);

$imported = 0;
$skipped = 0;
$placeholder = 0;
foreach ($list as $coffee) {
    $name = trim((string) ($coffee['title'] ?? ''));
    if ($name === '') {
        continue;
    }
    // The public API is community-edited and contains a few placeholder rows.
    if (strlen($name) < 3 || preg_match('/test|probe|coba|asdf|dummy|^xxx|^tes$/i', $name)) {
        $placeholder++;
        continue;
    }
    $exists->bind_param('s', $name);
    $exists->execute();
    if ($exists->get_result()->fetch_column()) {
        $skipped++;
        continue;
    }

    $ingredients = is_array($coffee['ingredients'] ?? null) ? implode(', ', $coffee['ingredients']) : '';
    $description = trim((string) ($coffee['description'] ?? ''));
    if ($ingredients !== '') {
        $description .= ($description === '' ? '' : ' ') . 'Ingredients: ' . $ingredients . '.';
    }
    if ($description === '') {
        $description = 'Kopi pilihan dari koleksi Kopipiro.';
    }
    // The API carries no price, so we invent a stable one from its id.
    $price = 20000 + ((int) ($coffee['id'] ?? 0) % 6) * 2500;
    $image = trim((string) ($coffee['image'] ?? ''));
    $image = $image === '' ? null : $image;
    $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $name), '-')) . '-' . bin2hex(random_bytes(3));

    $insert->bind_param('isssis', $categoryId, $name, $slug, $description, $price, $image);
    $insert->execute();
    $imported++;
}

// A few demo ratings so the catalogue shows stars. Only customers may review,
// and the schema allows one review per customer per product.
$customerIds = $conn->query("SELECT id FROM users WHERE role = 'customer' ORDER BY id LIMIT 3")->fetch_all(MYSQLI_ASSOC);
$rated = 0;
if ($customerIds) {
    $needsRating = $conn->query(
        'SELECT id FROM products WHERE is_available = 1
         AND id NOT IN (SELECT product_id FROM reviews)'
    )->fetch_all(MYSQLI_ASSOC);
    $rate = $conn->prepare('INSERT INTO reviews (customer_id, product_id, rating, comment) VALUES (?, ?, ?, ?)');
    foreach ($needsRating as $product) {
        foreach ($customerIds as $customer) {
            $productId = (int) $product['id'];
            $customerId = (int) $customer['id'];
            $score = random_int(3, 5);
            $comment = 'Racikan yang nikmat.';
            $rate->bind_param('iiis', $customerId, $productId, $score, $comment);
            $rate->execute();
            $rated++;
        }
    }
}

printf("Imported %d coffee(s) from the public API, skipped %d already present, ignored %d placeholder row(s).\n", $imported, $skipped, $placeholder);
printf("Added %d demo review(s).\n", $rated);
if (!$customerIds) {
    echo "No customer account exists yet, so no demo reviews were added.\n";
}
