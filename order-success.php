<?php
require 'auth.php';
require 'config.php';

$id = (int) ($_SESSION['last_order'] ?? 0);
if (!$id) {
    header('Location: index.php');
    exit;
}
$statement = $conn->prepare('SELECT id, total FROM orders WHERE id = ?');
$statement->bind_param('i', $id);
$statement->execute();
$order = $statement->get_result()->fetch_assoc();
unset($_SESSION['last_order']);
layout_start('Pesanan diterima');
?>
<section class="success-section"><div class="success-icon">✓</div><span class="eyebrow">PESANAN DITERIMA</span><h1>Terima kasih,<br><em>pesananmu tercatat.</em></h1><p>Sampaikan nomor pesanan <strong>#<?= (int) $order['id'] ?></strong> kepada kasir untuk konfirmasi.</p><div class="success-total"><span>Total pembayaran</span><strong><?= money($order['total']) ?></strong></div><a class="btn btn-dark rounded-pill px-4 py-3" href="index.php">Kembali ke menu</a></section>
<?php layout_end(); ?>
