<?php
/**
 * Orders API.
 *
 *   POST api/orders.php        guest or customer creates an order
 *   GET  api/orders.php        owner: all, cashier: own, customer: own
 *   PUT  api/orders.php?id=5   owner or cashier changes the status
 *
 * The browser may send prices; they are ignored. create_order() always reads the
 * price from the database and applies the member discount on the server.
 */

require_once __DIR__ . '/../helpers.php';
require_once __DIR__ . '/../orders_service.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$statuses = ['pending', 'preparing', 'ready', 'completed', 'cancelled'];

if ($method === 'POST') {
    require_api_csrf();
    $user = me(); // a guest is allowed here
    $input = body();
    try {
        $order = create_order(
            $conn,
            is_array($input['items'] ?? null) ? $input['items'] : [],
            $user,
            trim($input['customer_name'] ?? ''),
            trim($input['phone'] ?? '')
        );
    } catch (InvalidArgumentException $exception) {
        out(['message' => $exception->getMessage()], 400);
    }

    out([
        'message' => 'Order created',
        'id' => $order['id'],
        'subtotal' => $order['subtotal'],
        'discount' => $order['discount'],
        'total' => $order['total'],
        'cashier' => $order['cashier'],
        'items' => $order['items'],
    ], 201);
}

if ($method === 'GET') {
    $user = need_role(['owner', 'kasir', 'customer']);

    $sql = "SELECT o.id, o.customer_name, o.phone, o.subtotal, o.discount, o.total,
                   o.status, o.created_at, u.username AS cashier,
                   (SELECT GROUP_CONCAT(CONCAT(i.quantity, 'x ', i.product_name) SEPARATOR ', ')
                    FROM order_items i WHERE i.order_id = o.id) AS items
            FROM orders o
            LEFT JOIN users u ON u.id = o.cashier_id";
    $where = [];
    $types = '';
    $values = [];

    if ($user['role'] === 'kasir') {
        $where[] = 'o.cashier_id = ?';
        $types .= 'i';
        $values[] = $user['id'];
    } elseif ($user['role'] === 'customer') {
        $where[] = 'o.customer_id = ?';
        $types .= 'i';
        $values[] = $user['id'];
    }

    $status = trim($_GET['status'] ?? '');
    if ($status !== '') {
        if (!in_array($status, $statuses, true)) {
            out(['message' => 'Unknown status filter'], 400);
        }
        $where[] = 'o.status = ?';
        $types .= 's';
        $values[] = $status;
    }

    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY o.created_at DESC, o.id DESC LIMIT 200';

    $statement = $conn->prepare($sql);
    if ($values) {
        $statement->bind_param($types, ...$values);
    }
    $statement->execute();
    $orders = $statement->get_result()->fetch_all(MYSQLI_ASSOC);

    foreach ($orders as &$order) {
        $order['id'] = (int) $order['id'];
        $order['subtotal'] = (int) $order['subtotal'];
        $order['discount'] = (int) $order['discount'];
        $order['total'] = (int) $order['total'];
    }
    unset($order);

    out($orders);
}

if ($method === 'PUT') {
    require_api_csrf();
    $user = need_role(['owner', 'kasir']);

    $id = (int) ($_GET['id'] ?? 0);
    $status = (string) (body()['status'] ?? '');
    if ($id < 1 || !in_array($status, $statuses, true)) {
        out(['message' => 'id and a valid status are required'], 400);
    }

    // A cashier may only touch an order that is assigned to them.
    $lookup = 'SELECT id, status FROM orders WHERE id = ?';
    $types = 'i';
    $values = [$id];
    if ($user['role'] === 'kasir') {
        $lookup .= ' AND cashier_id = ?';
        $types .= 'i';
        $values[] = $user['id'];
    }
    $exists = $conn->prepare($lookup);
    $exists->bind_param($types, ...$values);
    $exists->execute();
    $existingOrder = $exists->get_result()->fetch_assoc();
    if (!$existingOrder) {
        out(['message' => 'Order not found'], 404);
    }

    if ($user['role'] === 'kasir') {
        $nextStatuses = ['pending' => 'preparing', 'preparing' => 'ready', 'ready' => 'completed'];
        if (($nextStatuses[$existingOrder['status']] ?? null) !== $status) {
            out(['message' => 'Cashiers can only move an order to its next status'], 409);
        }
        $statement = $conn->prepare('UPDATE orders SET status = ? WHERE id = ? AND cashier_id = ? AND status = ?');
        $statement->bind_param('siis', $status, $id, $user['id'], $existingOrder['status']);
        $statement->execute();
        if (!$statement->affected_rows) {
            out(['message' => 'Order status changed; reload and try again'], 409);
        }
        out(['message' => 'Status updated', 'id' => $id, 'status' => $status]);
    }

    $statement = $conn->prepare('UPDATE orders SET status = ? WHERE id = ?');
    $statement->bind_param('si', $status, $id);
    $statement->execute();

    out(['message' => 'Status updated', 'id' => $id, 'status' => $status]);
}

header('Allow: GET, POST, PUT');
out(['message' => 'Method not allowed'], 405);
