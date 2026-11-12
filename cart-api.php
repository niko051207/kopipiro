<?php
/**
 * JSON endpoint behind the cart drawer.
 *
 *   GET  cart-api.php                 -> current cart state
 *   POST cart-api.php {action: ...}   -> add | set | remove | clear | checkout
 *
 * The browser sends the session CSRF token in the X-CSRF-Token header. The
 * regular cart.php form still works without JavaScript.
 */

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/cart_service.php';
require_once __DIR__ . '/orders_service.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (!in_array($method, ['GET', 'POST'], true)) {
    header('Allow: GET, POST');
    out(['message' => 'Metode tidak didukung'], 405);
}

$user = me();

if ($method === 'GET') {
    out(['cart' => cart_state($conn, $user)]);
}

require_api_csrf();

$input = body();
if (!$input) {
    $input = $_POST; // allow a normal form post as well
}
$action = isset($input['action']) && is_string($input['action']) ? $input['action'] : '';
$id = (int) ($input['product_id'] ?? 0);
$quantity = (int) ($input['quantity'] ?? 1);

switch ($action) {
    case 'add':
        if (!cart_add($conn, $id, max(1, $quantity))) {
            out(['message' => 'Menu ini sedang tidak tersedia.'], 422);
        }
        $message = 'Ditambahkan ke keranjang';
        break;

    case 'set':
        if (!cart_set($conn, $id, $quantity)) {
            out(['message' => 'Menu ini sedang tidak tersedia.'], 422);
        }
        $message = 'Keranjang diperbarui';
        break;

    case 'remove':
        cart_remove($id);
        $message = 'Menu dihapus dari keranjang';
        break;

    case 'clear':
        cart_clear();
        $message = 'Keranjang dikosongkan';
        break;

    case 'checkout':
        $name = $user ? $user['name'] : trim((string) ($input['customer_name'] ?? ''));
        $phone = trim((string) ($input['phone'] ?? ''));
        if ($name === '' || $phone === '') {
            out(['message' => 'Isi nama dan nomor WhatsApp untuk melanjutkan.'], 422);
        }
        $cart = cart_state($conn, $user);
        $orderItems = [];
        foreach ($cart['items'] as $item) {
            $orderItems[] = ['product_id' => $item['id'], 'qty' => $item['quantity']];
        }
        try {
            $order = create_order($conn, $orderItems, $user, $name, $phone);
        } catch (InvalidArgumentException $exception) {
            out(['message' => checkout_error_message($exception->getMessage())], 422);
        } catch (Throwable $exception) {
            out(['message' => 'Pesanan belum dapat disimpan. Coba kembali.'], 500);
        }
        cart_clear();
        $_SESSION['last_order'] = $order['id'];
        out([
            'message' => 'Pesanan diterima',
            'order' => ['id' => $order['id'], 'total' => $order['total']],
            'cart' => cart_state($conn, $user),
        ]);

    default:
        out(['message' => 'Aksi tidak dikenal'], 422);
}

out(['message' => $message, 'cart' => cart_state($conn, $user)]);

/**
 * create_order() speaks English because it is shared with the JSON API.
 * Translate the messages a customer can actually trigger.
 */
function checkout_error_message(string $message): string
{
    $messages = [
        'Your order is empty' => 'Keranjangmu masih kosong.',
        'Some products are unavailable' => 'Ada menu yang sudah tidak tersedia. Muat ulang halaman lalu coba lagi.',
        'A valid phone number is required' => 'Nomor WhatsApp belum valid.',
        'A valid customer name is required' => 'Nama penerima wajib diisi.',
        'Order total is too large' => 'Total pesanan terlalu besar.',
    ];
    return $messages[$message] ?? 'Pesanan belum dapat diproses. Periksa kembali datamu.';
}
