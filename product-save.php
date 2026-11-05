<?php
require 'auth.php';
require 'config.php';
require_role('owner');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: owner.php'); exit; }
$action = $_POST['action'] ?? '';
if ($action === 'toggle') {
    $id = (int) ($_POST['id'] ?? 0);
    $statement = $conn->prepare('UPDATE products SET is_available = NOT is_available WHERE id = ?');
    $statement->bind_param('i', $id);
    $statement->execute();
    flash('Status produk diperbarui.');
} else {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price = max(0, (int) ($_POST['price'] ?? 0));
    $categoryId = (int) ($_POST['category_id'] ?? 0);
    $featured = isset($_POST['is_featured']) ? 1 : 0;
    $imagePath = null;
    if (!empty($_FILES['image']['name']) && is_uploaded_file($_FILES['image']['tmp_name'])) {
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($_FILES['image']['tmp_name']);
        $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        if (isset($extensions[$mime]) && $_FILES['image']['size'] <= 5000000) {
            if (!is_dir('uploads')) mkdir('uploads', 0755, true);
            $filename = bin2hex(random_bytes(12)) . '.' . $extensions[$mime];
            if (move_uploaded_file($_FILES['image']['tmp_name'], 'uploads/' . $filename)) $imagePath = 'uploads/' . $filename;
        }
    }
    if ($name !== '' && $description !== '') {
        $statement = $conn->prepare('INSERT INTO products (category_id, name, slug, description, price, image, is_featured) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $name), '-')) . '-' . bin2hex(random_bytes(3));
        $statement->bind_param('isssisi', $categoryId, $name, $slug, $description, $price, $imagePath, $featured);
        $statement->execute();
        flash('Produk berhasil ditambahkan.');
    } else {
        flash('Nama dan deskripsi produk wajib diisi.', 'danger');
    }
}
header('Location: owner.php');
exit;
