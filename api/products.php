<?php
/**
 * Products API.
 *
 *   GET  api/products.php[?category=<slug>][&q=<search>]  public catalogue, JSON
 *   POST api/products.php                                  owner adds a product (multipart)
 *   PUT  api/products.php?id=3                             owner changes a product (JSON)
 *
 * GET returns both the normal price and final_price, which is what the current
 * visitor would pay: 10% below the normal price for a signed-in customer.
 */

require_once __DIR__ . '/../helpers.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$user = me();

if ($method === 'GET') {
    // Public read-only endpoint: other origins may read the catalogue.
    header('Access-Control-Allow-Origin: *');

    $sql = "SELECT p.id, p.name, p.slug, p.description, p.price, p.image, p.is_featured,
                   c.name AS category, c.slug AS category_slug,
                   (SELECT ROUND(AVG(r.rating), 1) FROM reviews r WHERE r.product_id = p.id) AS rating,
                   (SELECT COUNT(*) FROM reviews r WHERE r.product_id = p.id) AS reviews
            FROM products p
            JOIN categories c ON c.id = p.category_id
            WHERE p.is_available = 1";
    $types = '';
    $values = [];

    $category = trim($_GET['category'] ?? '');
    if ($category !== '') {
        $sql .= ' AND c.slug = ?';
        $types .= 's';
        $values[] = $category;
    }
    $search = trim($_GET['q'] ?? '');
    if ($search !== '') {
        $sql .= ' AND (p.name LIKE ? OR p.description LIKE ?)';
        $types .= 'ss';
        $values[] = '%' . $search . '%';
        $values[] = '%' . $search . '%';
    }
    $sql .= ' ORDER BY p.is_featured DESC, c.id, p.name';

    $statement = $conn->prepare($sql);
    if ($values) {
        $statement->bind_param($types, ...$values);
    }
    $statement->execute();
    $products = $statement->get_result()->fetch_all(MYSQLI_ASSOC);

    foreach ($products as &$product) {
        $price = (int) $product['price'];
        $product['id'] = (int) $product['id'];
        $product['price'] = $price;
        $product['final_price'] = final_price($price, $user);
        $product['image'] = image_url($product['image']);
        $product['is_featured'] = (bool) $product['is_featured'];
        $product['rating'] = $product['rating'] === null ? null : (float) $product['rating'];
        $product['reviews'] = (int) $product['reviews'];
    }
    unset($product);

    $categories = $conn->query('SELECT id, name, slug FROM categories ORDER BY sort_order, name')->fetch_all(MYSQLI_ASSOC);
    foreach ($categories as &$row) {
        $row['id'] = (int) $row['id'];
    }
    unset($row);

    out(['categories' => $categories, 'products' => $products]);
}

if ($method === 'POST') {
    need_role(['owner']);
    require_api_csrf();

    $name = trim($_POST['name'] ?? '');
    $categoryId = (int) ($_POST['category_id'] ?? 0);
    $price = (int) ($_POST['price'] ?? 0);
    $description = trim($_POST['description'] ?? '');
    if ($name === '' || strlen($name) > 120 || $categoryId < 1 || $price < 1) {
        out(['message' => 'name, category_id and price are required'], 400);
    }

    $category = $conn->prepare('SELECT id FROM categories WHERE id = ?');
    $category->bind_param('i', $categoryId);
    $category->execute();
    if (!$category->get_result()->num_rows) {
        out(['message' => 'Unknown category'], 400);
    }

    $image = null;
    if (!empty($_FILES['image']['name'])) {
        $file = $_FILES['image'];
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || $file['size'] > 2 * 1024 * 1024) {
            out(['message' => 'Image upload failed or larger than 2 MB'], 400);
        }
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        $extension = $allowed[$mime] ?? null;
        if (!$extension || @getimagesize($file['tmp_name']) === false) {
            out(['message' => 'Only JPG, PNG or WebP images are allowed'], 400);
        }
        if (!is_dir(__DIR__ . '/../uploads')) {
            mkdir(__DIR__ . '/../uploads', 0755, true);
        }
        // Never trust the original file name.
        $image = bin2hex(random_bytes(8)) . '.' . $extension;
        if (!move_uploaded_file($file['tmp_name'], __DIR__ . '/../uploads/' . $image)) {
            out(['message' => 'The image could not be stored'], 500);
        }
    }

    $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $name), '-')) . '-' . bin2hex(random_bytes(3));
    $statement = $conn->prepare(
        'INSERT INTO products (category_id, name, slug, description, price, image, is_featured, is_available)
         VALUES (?, ?, ?, ?, ?, ?, 0, 1)'
    );
    $statement->bind_param('isssis', $categoryId, $name, $slug, $description, $price, $image);
    $statement->execute();

    out(['message' => 'Product created', 'id' => (int) $conn->insert_id], 201);
}

if ($method === 'PUT') {
    need_role(['owner']);
    require_api_csrf();

    $id = (int) ($_GET['id'] ?? 0);
    if ($id < 1) {
        out(['message' => 'id parameter is required'], 400);
    }
    $input = body();
    $exists = $conn->prepare('SELECT id FROM products WHERE id = ?');
    $exists->bind_param('i', $id);
    $exists->execute();
    if (!$exists->get_result()->num_rows) {
        out(['message' => 'Product not found'], 404);
    }

    // Only the fields that were sent are changed; price is not mandatory.
    $fields = [];
    $types = '';
    $values = [];
    if (array_key_exists('price', $input)) {
        $price = (int) $input['price'];
        if ($price < 1) {
            out(['message' => 'price must be a positive number'], 400);
        }
        $fields[] = 'price = ?';
        $types .= 'i';
        $values[] = $price;
    }
    if (array_key_exists('name', $input) && trim((string) $input['name']) !== '') {
        if (strlen(trim((string) $input['name'])) > 120) {
            out(['message' => 'name must be at most 120 characters'], 400);
        }
        $fields[] = 'name = ?';
        $types .= 's';
        $values[] = trim((string) $input['name']);
    }
    if (array_key_exists('description', $input)) {
        $fields[] = 'description = ?';
        $types .= 's';
        $values[] = trim((string) $input['description']);
    }
    if (array_key_exists('is_available', $input)) {
        $fields[] = 'is_available = ?';
        $types .= 'i';
        $values[] = $input['is_available'] ? 1 : 0;
    }
    if (!$fields) {
        out(['message' => 'Nothing to update'], 400);
    }
    $types .= 'i';
    $values[] = $id;

    $statement = $conn->prepare('UPDATE products SET ' . implode(', ', $fields) . ' WHERE id = ?');
    $statement->bind_param($types, ...$values);
    $statement->execute();

    out(['message' => 'Product updated']);
}

header('Allow: GET, POST, PUT');
out(['message' => 'Method not allowed'], 405);
