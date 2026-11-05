<?php
require 'auth.php';
require 'config.php';
require_role('kasir');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $orderId = (int) ($_POST['order_id'] ?? 0);
    $status = $_POST['status'] ?? '';
    if (in_array($status, ['preparing', 'ready', 'completed'], true)) {
        $conn->begin_transaction();
        $claim = $conn->prepare('UPDATE orders SET cashier_id = ?, status = ? WHERE id = ? AND (cashier_id = ? OR cashier_id IS NULL) AND status IN ("pending", "preparing", "ready")');
        $claim->bind_param('isii', $_SESSION['user_id'], $status, $orderId, $_SESSION['user_id']);
        $claim->execute();
        if ($claim->affected_rows) {
            $conn->commit();
            flash('Status pesanan diperbarui.');
        } else {
            $conn->rollback();
            flash('Pesanan ini sudah dikelola kasir lain.', 'warning');
        }
    }
}
header('Location: cashier.php');
exit;
