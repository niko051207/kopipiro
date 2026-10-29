<?php
// Kasir: only the orders assigned to the logged-in cashier (requirement 5).
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../config.php';

require_role('kasir');

$cashierId = current_user()['id'];

$stmt = mysqli_prepare(
    $conn,
    "SELECT o.id, o.order_code, o.customer_name, o.total, o.status, o.created_at,
            COUNT(i.id) AS item_count
       FROM orders o
       JOIN order_items i ON i.order_id = o.id
      WHERE o.cashier_id = ?
      GROUP BY o.id, o.order_code, o.customer_name, o.total, o.status, o.created_at
      ORDER BY o.created_at DESC"
);
mysqli_stmt_bind_param($stmt, 'i', $cashierId);
mysqli_stmt_execute($stmt);
$orders = mysqli_stmt_get_result($stmt);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pesanan Saya — Kopipiro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <?php include __DIR__ . '/../navbar.php'; ?>

    <div class="container py-4">
        <h4 class="mb-1">Pesanan Saya</h4>
        <p class="text-muted small mb-4">
            Hanya pesanan yang ditangani oleh <strong><?= e(current_user()['username']) ?></strong>.
        </p>

        <div class="card border-0 shadow-sm">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Kode</th>
                            <th>Customer</th>
                            <th class="text-center">Item</th>
                            <th class="text-end">Total</th>
                            <th>Status</th>
                            <th>Waktu</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (mysqli_num_rows($orders) === 0): ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-5">
                                    Belum ada pesanan yang ditangani.
                                </td>
                            </tr>
                        <?php endif; ?>
                        <?php while ($row = mysqli_fetch_assoc($orders)): ?>
                            <tr>
                                <td class="font-monospace small"><?= e($row['order_code']) ?></td>
                                <td><?= e($row['customer_name']) ?></td>
                                <td class="text-center"><?= (int) $row['item_count'] ?></td>
                                <td class="text-end"><?= e(rupiah($row['total'])) ?></td>
                                <td><?= status_badge($row['status']) ?></td>
                                <td class="text-muted small"><?= e(date('d/m H:i', strtotime($row['created_at']))) ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
