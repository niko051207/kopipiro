<?php
require 'auth.php';
require 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}
$id = (int) ($_POST['product_id'] ?? 0);
$action = $_POST['action'] ?? '';
if ($action === 'remove') {
    unset($_SESSION['cart'][$id]);
} elseif ($action === 'set') {
    $quantity = max(0, min(20, (int) ($_POST['quantity'] ?? 0)));
    if ($quantity === 0) unset($_SESSION['cart'][$id]);
    else $_SESSION['cart'][$id] = $quantity;
} else {
    $statement = $conn->prepare('SELECT id FROM products WHERE id = ? AND is_available = 1');
    $statement->bind_param('i', $id);
    $statement->execute();
    if ($statement->get_result()->num_rows) $_SESSION['cart'][$id] = min(20, ($_SESSION['cart'][$id] ?? 0) + 1);
}
header('Location: checkout.php');
exit;
