<?php
require 'auth.php';
require 'config.php';
require_role('owner');
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="kopipiro-pesanan-' . date('Y-m-d') . '.csv"');
$output = fopen('php://output', 'w');
fwrite($output, "\xEF\xBB\xBF");
function csv_safe($value): string
{
    $value = (string) $value;
    if (preg_match('/^[\s\x00-\x1F]*[=+@\-]/u', $value)) {
        return "'" . $value;
    }
    return $value;
}
fputcsv($output, ['No. Pesanan', 'Pelanggan', 'Telepon', 'Kasir', 'Subtotal', 'Diskon', 'Total', 'Status', 'Waktu']);
$rows = $conn->query('SELECT o.*, u.username AS cashier_name FROM orders o LEFT JOIN users u ON u.id = o.cashier_id ORDER BY o.created_at DESC');
while ($row = $rows->fetch_assoc()) fputcsv($output, [
    $row['id'], csv_safe($row['customer_name']), csv_safe($row['phone']), csv_safe($row['cashier_name'] ?? ''),
    $row['subtotal'], $row['discount'], $row['total'], csv_safe($row['status']), csv_safe($row['created_at']),
]);
fclose($output);
