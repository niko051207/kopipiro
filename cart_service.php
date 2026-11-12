<?php
/**
 * Shopping cart state shared by the checkout page, the cart drawer endpoint
 * and the no-JavaScript form fallback.
 *
 * The cart lives in the session as [product_id => quantity]. Prices are always
 * read from the database here, never from the browser.
 */

require_once __DIR__ . '/helpers.php';

/**
 * The session cart, cleaned: integer ids, quantities between 1 and 20.
 */
function cart_quantities(): array
{
    $cart = $_SESSION['cart'] ?? [];
    if (!is_array($cart)) {
        return [];
    }
    $clean = [];
    foreach ($cart as $id => $quantity) {
        $id = (int) $id;
        $quantity = (int) $quantity;
        if ($id > 0 && $quantity > 0) {
            $clean[$id] = min(20, $quantity);
        }
    }
    return $clean;
}

/**
 * Everything the drawer and the checkout page need to render the cart.
 *
 * Products that were deleted or hidden are dropped from the session so the
 * badge never counts an item the checkout cannot sell.
 *
 * @return array{items:array,subtotal:int,discount:int,total:int,count:int,member:bool,user_name:string}
 */
function cart_state(mysqli $conn, ?array $user): array
{
    $quantities = cart_quantities();
    $items = [];
    $subtotal = 0;   // gross, before the member discount
    $total = 0;      // what this visitor pays

    if ($quantities) {
        $ids = array_keys($quantities);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $statement = $conn->prepare(
            "SELECT id, name, price, image FROM products
             WHERE is_available = 1 AND id IN ($placeholders)
             ORDER BY name"
        );
        $statement->bind_param(str_repeat('i', count($ids)), ...$ids);
        $statement->execute();

        $found = [];
        foreach ($statement->get_result()->fetch_all(MYSQLI_ASSOC) as $product) {
            $id = (int) $product['id'];
            $found[$id] = true;
            $quantity = $quantities[$id];
            $unitPrice = final_price((int) $product['price'], $user);
            $lineTotal = $unitPrice * $quantity;
            $subtotal += (int) $product['price'] * $quantity;
            $total += $lineTotal;
            $items[] = [
                'id' => $id,
                'name' => $product['name'],
                'price' => (int) $product['price'],
                'unit_price' => $unitPrice,
                'quantity' => $quantity,
                'line_total' => $lineTotal,
                'image' => image_url($product['image']),
            ];
        }

        foreach ($ids as $id) {
            if (!isset($found[$id])) {
                unset($_SESSION['cart'][$id]);
            }
        }
        if (empty($_SESSION['cart'])) {
            unset($_SESSION['cart']);
        }
    }

    return [
        'items' => $items,
        'subtotal' => $subtotal,
        'discount' => $subtotal - $total,
        'total' => $total,
        'count' => (int) array_sum(array_column($items, 'quantity')),
        'member' => $user !== null && $user['role'] === 'customer',
        'user_name' => $user['name'] ?? '',
    ];
}

/**
 * Add to the cart, capped at 20 per product. Returns false when the product
 * does not exist or is not available.
 */
function cart_add(mysqli $conn, int $id, int $quantity = 1): bool
{
    if ($id < 1) {
        return false;
    }
    $statement = $conn->prepare('SELECT id FROM products WHERE id = ? AND is_available = 1');
    $statement->bind_param('i', $id);
    $statement->execute();
    if (!$statement->get_result()->num_rows) {
        return false;
    }
    $current = (int) ($_SESSION['cart'][$id] ?? 0);
    $_SESSION['cart'][$id] = min(20, $current + max(1, $quantity));
    return true;
}

/**
 * Set an absolute quantity. A quantity of 0 removes the line.
 */
function cart_set(mysqli $conn, int $id, int $quantity): bool
{
    if ($id < 1) {
        return false;
    }
    $quantity = max(0, min(20, $quantity));
    if ($quantity === 0) {
        cart_remove($id);
        return true;
    }
    $statement = $conn->prepare('SELECT id FROM products WHERE id = ? AND is_available = 1');
    $statement->bind_param('i', $id);
    $statement->execute();
    if (!$statement->get_result()->num_rows) {
        return false;
    }
    $_SESSION['cart'][$id] = $quantity;
    return true;
}

function cart_remove(int $id): void
{
    unset($_SESSION['cart'][$id]);
    if (empty($_SESSION['cart'])) {
        unset($_SESSION['cart']);
    }
}

function cart_clear(): void
{
    unset($_SESSION['cart']);
}
