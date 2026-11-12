<?php
/**
 * No-JavaScript fallback for the cart drawer. Adds, changes or removes one
 * line, then sends the visitor back to the menu with the drawer open.
 */
require 'auth.php';
require 'config.php';
require_once __DIR__ . '/cart_service.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}
require_csrf();

$id = (int) ($_POST['product_id'] ?? 0);
$action = $_POST['action'] ?? '';
if (!is_string($action)) {
    $action = '';
}

if ($action === 'remove') {
    cart_remove($id);
} elseif ($action === 'clear') {
    cart_clear();
} elseif ($action === 'set') {
    cart_set($conn, $id, (int) ($_POST['quantity'] ?? 0));
} else {
    cart_add($conn, $id);
}

header('Location: index.php?cart=open');
exit;
