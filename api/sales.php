<?php
/**
 * Sales API (owner only).
 *
 *   GET api/sales.php -> {daily:[{day,orders,revenue}], top:[{product_name,qty}]}
 *
 * Only completed orders count, which matches the owner dashboard chart.
 */

require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../orders_service.php';

require_method(['GET']);
need_role(['owner']);

$summary = sales_summary($conn);

out([
    'daily' => $summary['daily'],
    'top' => $summary['top'],
]);
