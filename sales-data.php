<?php
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/orders_service.php';
require_role('owner');

header('Content-Type: application/json; charset=utf-8');

$summary = sales_summary($conn, 7);
$daily = [];
foreach ($summary['daily'] as $row) {
    $daily[$row['day']] = (int) $row['revenue'];
}
$labels = [];
$values = [];
for ($day = 6; $day >= 0; $day--) {
    $date = date('Y-m-d', strtotime("-$day days"));
    $labels[] = date('d M', strtotime($date));
    $values[] = $daily[$date] ?? 0;
}
echo json_encode(['labels' => $labels, 'values' => $values]);
