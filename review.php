<?php
require 'auth.php';
require 'config.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && logged_in() && $_SESSION['role'] === 'customer') {
    $productId = (int) ($_POST['product_id'] ?? 0);
    $rating = max(1, min(5, (int) ($_POST['rating'] ?? 5)));
    $comment = trim($_POST['comment'] ?? '');
    $orderId = (int) ($_POST['order_id'] ?? 0);
    $statement = $conn->prepare('SELECT oi.product_id FROM order_items oi JOIN orders o ON o.id = oi.order_id WHERE o.id = ? AND o.customer_id = ? AND o.status = "completed" AND oi.product_id = ?');
    $statement->bind_param('iii', $orderId, $_SESSION['user_id'], $productId);
    $statement->execute();
    if ($statement->get_result()->num_rows) {
        $review = $conn->prepare('INSERT INTO reviews (customer_id, product_id, rating, comment) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE rating = VALUES(rating), comment = VALUES(comment)');
        $review->bind_param('iiis', $_SESSION['user_id'], $productId, $rating, $comment);
        $review->execute();
        flash('Terima kasih sudah berbagi ulasan.');
    }
}
header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'index.php'));
exit;
