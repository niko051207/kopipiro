<?php
require 'auth.php';
require 'config.php';
require_role('kasir');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $orderId = (int) ($_POST['order_id'] ?? 0);
    $status = $_POST['status'] ?? '';
    $nextStatuses = [
        'pending' => 'preparing',
        'preparing' => 'ready',
        'ready' => 'completed',
    ];
    if (isset($nextStatuses[$_POST['current_status'] ?? '']) && $nextStatuses[$_POST['current_status']] === $status) {
        $conn->begin_transaction();
        $currentStatus = (string) $_POST['current_status'];
        $claim = $conn->prepare('UPDATE orders SET cashier_id = ?, status = ? WHERE id = ? AND (cashier_id = ? OR cashier_id IS NULL) AND status = ?');
        $claim->bind_param('isiis', $_SESSION['user_id'], $status, $orderId, $_SESSION['user_id'], $currentStatus);
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
