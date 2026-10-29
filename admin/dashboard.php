<?php
// Owner dashboard — summary numbers. Chart.js version lands in Phase 6.
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../config.php';

require_role('owner');

$revenueAll = mysqli_fetch_assoc(mysqli_query(
    $conn,
    "SELECT COALESCE(SUM(total), 0) AS total FROM orders WHERE status = 'completed'"
));

$today = mysqli_fetch_assoc(mysqli_query(
    $conn,
    "SELECT COUNT(*) AS orders,
            COALESCE(SUM(CASE WHEN status = 'completed' THEN total END), 0) AS revenue
       FROM orders
      WHERE DATE(created_at) = CURDATE()"
));

$pending = mysqli_fetch_assoc(mysqli_query(
    $conn,
    "SELECT COUNT(*) AS n FROM orders WHERE status IN ('pending', 'processing')"
));

$productCount = mysqli_fetch_assoc(mysqli_query(
    $conn,
    "SELECT COUNT(*) AS n FROM products WHERE is_active = 1"
));

$recent = mysqli_query(
    $conn,
    "SELECT o.id, o.order_code, o.customer_name, o.total, o.status, o.created_at,
            u.username AS cashier
       FROM orders o
       LEFT JOIN users u ON u.id = o.cashier_id
      ORDER BY o.created_at DESC
      LIMIT 6"
);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Owner — Kopipiro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <?php include __DIR__ . '/../navbar.php'; ?>

    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h4 class="mb-0">Dashboard</h4>
                <p class="text-muted small mb-0">Ringkasan penjualan Kopipiro.</p>
            </div>
            <span class="text-muted small"><?= e(date('d M Y')) ?></span>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-6 col-lg-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="text-muted small">Pendapatan (selesai)</div>
                        <div class="fs-4 fw-semibold"><?= e(rupiah($revenueAll['total'])) ?></div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="text-muted small">Pendapatan hari ini</div>
                        <div class="fs-4 fw-semibold"><?= e(rupiah($today['revenue'])) ?></div>
                        <div class="text-muted small"><?= (int) $today['orders'] ?> pesanan</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="text-muted small">Perlu diproses</div>
                        <div class="fs-4 fw-semibold"><?= (int) $pending['n'] ?></div>
                        <div class="text-muted small">pending + processing</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="text-muted small">Produk aktif</div>
                        <div class="fs-4 fw-semibold"><?= (int) $productCount['n'] ?></div>
                        <div class="text-muted small">di katalog</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <span class="fw-semibold">Pesanan terbaru</span>
                <span class="text-muted small">6 terakhir</span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Kode</th>
                            <th>Customer</th>
                            <th>Kasir</th>
                            <th class="text-end">Total</th>
                            <th>Status</th>
                            <th>Waktu</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (mysqli_num_rows($recent) === 0): ?>
                            <tr><td colspan="6" class="text-center text-muted py-4">Belum ada pesanan.</td></tr>
                        <?php endif; ?>
                        <?php while ($row = mysqli_fetch_assoc($recent)): ?>
                            <tr>
                                <td class="font-monospace small"><?= e($row['order_code']) ?></td>
                                <td><?= e($row['customer_name']) ?></td>
                                <td class="text-muted small"><?= e($row['cashier'] ?? '—') ?></td>
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
