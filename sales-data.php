<?php
require 'auth.php';
require 'config.php';
require_role('owner');
header('Content-Type: application/json; charset=utf-8');
$rows = $conn->query('SELECT DATE(created_at) AS sales_date, SUM(total) AS revenue FROM orders WHERE status = "completed" AND created_at >= CURRENT_DATE - INTERVAL 6 DAY GROUP BY DATE(created_at)');
$daily = [];
while ($row = $rows->fetch_assoc()) $daily[$row['sales_date']] = (int) $row['revenue'];
$labels = [];
$values = [];
for ($day = 6; $day >= 0; $day--) {
    $date = date('Y-m-d', strtotime("-$day days"));
    $labels[] = date('d M', strtotime($date));
    $values[] = $daily[$date] ?? 0;
}
echo json_encode(['labels' => $labels, 'values' => $values]);
