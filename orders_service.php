<?php
/**
 * Order and sales logic shared by the checkout page and the JSON API.
 *
 * Rules that MUST stay here (never in the browser):
 *   - prices are read from the database,
 *   - the 10% member discount comes from final_price(),
 *   - order + items are written in one transaction,
 *   - new orders go to the cashier with the fewest open orders.
 */

require_once __DIR__ . '/helpers.php';

/**
 * Turn a raw items payload into a clean [product_id => quantity] map.
 * Invalid rows are dropped, duplicates are merged and each quantity is capped.
 */
function normalize_items(array $raw): array
{
    $quantities = [];
    foreach ($raw as $item) {
        if (!is_array($item)) {
            continue;
        }
        $productId = (int) ($item['product_id'] ?? 0);
        $quantity = (int) ($item['qty'] ?? 0);
        if ($productId < 1 || $quantity < 1) {
            continue;
        }
        $quantities[$productId] = min(20, ($quantities[$productId] ?? 0) + $quantity);
        if (count($quantities) > 20) {
            throw new InvalidArgumentException('A maximum of 20 different products can be ordered');
        }
    }
    return $quantities;
}

/**
 * The cashier with the fewest unfinished orders, or null when no cashier is active.
 */
function pick_cashier(mysqli $conn): ?array
{
    $row = $conn->query(
        "SELECT u.id, u.name FROM users u
         LEFT JOIN orders o ON o.cashier_id = u.id AND o.status IN ('pending','preparing','ready')
         WHERE u.role = 'kasir' AND u.is_active = 1
         GROUP BY u.id, u.name
         ORDER BY COUNT(o.id), u.id
         LIMIT 1"
    )->fetch_assoc();
    return $row ? ['id' => (int) $row['id'], 'name' => $row['name']] : null;
}

/**
 * Load the available products for a [id => qty] map.
 */
function load_cart_products(mysqli $conn, array $quantities): array
{
    if (!$quantities) {
        return [];
    }
    $ids = array_keys($quantities);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $statement = $conn->prepare(
        "SELECT id, name, price FROM products WHERE is_available = 1 AND id IN ($placeholders)"
    );
    $statement->bind_param(str_repeat('i', count($ids)), ...$ids);
    $statement->execute();
    return $statement->get_result()->fetch_all(MYSQLI_ASSOC);
}

/**
 * Create an order for a guest (null $user) or a signed-in customer.
 *
 * Money is stored gross and the discount is applied at order level, so the rows
 * always reconcile: subtotal - discount = total, and the item lines add up to
 * the subtotal.
 *
 * @return array{id:int,subtotal:int,discount:int,total:int,cashier:?array,items:array}
 * @throws InvalidArgumentException when the cart or the contact details are not usable.
 * @throws Throwable when the database rejects the transaction.
 */
function create_order(mysqli $conn, array $rawItems, ?array $user, string $customerName, string $phone): array
{
    $quantities = normalize_items($rawItems);
    if (!$quantities) {
        throw new InvalidArgumentException('Your order is empty');
    }

    $products = load_cart_products($conn, $quantities);
    if (count($products) !== count($quantities)) {
        throw new InvalidArgumentException('Some products are unavailable');
    }

    $phone = trim($phone);
    $customerName = trim($customerName);
    if ($phone === '' || strlen($phone) > 30 || !preg_match('/^[0-9+() .-]{6,30}$/', $phone)) {
        throw new InvalidArgumentException('A valid phone number is required');
    }
    if (!$user && ($customerName === '' || strlen($customerName) > 100)) {
        throw new InvalidArgumentException('A valid customer name is required');
    }

    $subtotal = 0;
    $total = 0;
    $lines = [];
    foreach ($products as $product) {
        $quantity = $quantities[$product['id']];
        $unitPrice = (int) $product['price'];
        $lineTotal = $unitPrice * $quantity;
        $netLine = final_price($unitPrice, $user) * $quantity;
        if ($lineTotal > 4294967295 - $subtotal) {
            throw new InvalidArgumentException('Order total is too large');
        }
        $subtotal += $lineTotal;
        $total += $netLine;
        $lines[] = [
            'product_id' => (int) $product['id'],
            'product_name' => $product['name'],
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'line_total' => $lineTotal,
        ];
    }
    $discount = $subtotal - $total;

    $cashier = pick_cashier($conn);
    $member = $user && $user['role'] === 'customer';
    $customerId = $member ? (int) $user['id'] : null;
    $name = $member ? $user['name'] : trim($customerName);
    if ($name === '') {
        $name = 'Guest';
    }
    $cashierId = $cashier['id'] ?? null;
    $status = 'pending';

    $conn->begin_transaction();
    try {
        $order = $conn->prepare(
            'INSERT INTO orders (customer_id, cashier_id, customer_name, phone, subtotal, discount, total, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $order->bind_param('iissiiis', $customerId, $cashierId, $name, $phone, $subtotal, $discount, $total, $status);
        $order->execute();
        $orderId = (int) $conn->insert_id;

        $line = $conn->prepare(
            'INSERT INTO order_items (order_id, product_id, product_name, quantity, unit_price, line_total)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        foreach ($lines as $item) {
            $line->bind_param(
                'iisiii',
                $orderId,
                $item['product_id'],
                $item['product_name'],
                $item['quantity'],
                $item['unit_price'],
                $item['line_total']
            );
            $line->execute();
        }

        $conn->commit();
    } catch (Throwable $exception) {
        $conn->rollback();
        throw $exception;
    }

    return [
        'id' => $orderId,
        'subtotal' => $subtotal,
        'discount' => $discount,
        'total' => $total,
        'cashier' => $cashier,
        'items' => $lines,
    ];
}

/**
 * Sales aggregates for the dashboard chart and the sales API.
 * Only completed orders count. Pass $days = 0 for all time (module shape).
 *
 * @return array{daily:array,top:array}
 */
function sales_summary(mysqli $conn, int $days = 0): array
{
    $window = $days > 0 ? ' AND o.created_at >= CURRENT_DATE - INTERVAL ' . ($days - 1) . ' DAY' : '';

    $daily = $conn->query(
        "SELECT DATE(o.created_at) AS day, COUNT(*) AS orders, SUM(o.total) AS revenue
         FROM orders o
         WHERE o.status = 'completed'" . $window . '
         GROUP BY DATE(o.created_at)
         ORDER BY day'
    )->fetch_all(MYSQLI_ASSOC);

    $top = $conn->query(
        "SELECT i.product_name, SUM(i.quantity) AS qty
         FROM order_items i
         JOIN orders o ON o.id = i.order_id
         WHERE o.status = 'completed'" . $window . '
         GROUP BY i.product_name
         ORDER BY qty DESC
         LIMIT 5'
    )->fetch_all(MYSQLI_ASSOC);

    foreach ($daily as &$row) {
        $row['orders'] = (int) $row['orders'];
        $row['revenue'] = (int) $row['revenue'];
    }
    unset($row);
    foreach ($top as &$row) {
        $row['qty'] = (int) $row['qty'];
    }
    unset($row);

    return ['daily' => $daily, 'top' => $top];
}
