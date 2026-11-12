<?php
require 'auth.php';
require 'config.php';
require_role('owner');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: owner.php'); exit; }
require_csrf();
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
    $price = (int) ($_POST['price'] ?? 0);
    $categoryId = (int) ($_POST['category_id'] ?? 0);
    $featured = isset($_POST['is_featured']) ? 1 : 0;
    $categoryCheck = $conn->prepare('SELECT id FROM categories WHERE id = ?');
    $categoryCheck->bind_param('i', $categoryId);
    $categoryCheck->execute();
    if (!$categoryCheck->get_result()->num_rows) {
        flash('Kategori produk tidak ditemukan.', 'danger');
        header('Location: owner.php'); exit;
    }
    $imagePath = null;
    $upload = $_FILES['image'] ?? null;
    if ($upload && ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || $upload['size'] > 2 * 1024 * 1024) {
            flash('Gambar gagal diunggah atau lebih besar dari 2 MB.', 'danger');
            header('Location: owner.php'); exit;
        }
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($upload['tmp_name']);
        $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        if (!isset($extensions[$mime]) || @getimagesize($upload['tmp_name']) === false) {
            flash('Format gambar harus JPG, PNG, atau WebP yang valid.', 'danger');
            header('Location: owner.php'); exit;
        }
        if (isset($extensions[$mime])) {
            if (!is_dir('uploads')) mkdir('uploads', 0755, true);
            $filename = bin2hex(random_bytes(12)) . '.' . $extensions[$mime];
            if (move_uploaded_file($upload['tmp_name'], 'uploads/' . $filename)) $imagePath = 'uploads/' . $filename;
        }
    }
    if ($name !== '' && strlen($name) <= 120 && $description !== '' && $price > 0 && $categoryId > 0) {
        $statement = $conn->prepare('INSERT INTO products (category_id, name, slug, description, price, image, is_featured) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $name), '-')) . '-' . bin2hex(random_bytes(3));
        $statement->bind_param('isssisi', $categoryId, $name, $slug, $description, $price, $imagePath, $featured);
        $statement->execute();
        flash('Produk berhasil ditambahkan.');
    } else {
        flash('Nama (maks. 120), deskripsi, kategori, dan harga di atas Rp 0 wajib diisi.', 'danger');
    }
}
header('Location: owner.php');
exit;
