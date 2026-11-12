<?php
/**
 * End-to-end checks for the JSON API: roles, status codes, discount and upload rules.
 *
 * Usage (Apache must serve the project and MySQL must be running):
 *   C:\laragon\bin\php\php-8.3.33-Win32-vs16-x64\php.exe tools\api_smoke.php
 *   ...php.exe tools\api_smoke.php http://kopipiro.test
 *
 * Requires extension=curl. Exits 0 when every check passes.
 */

$GLOBALS['base'] = rtrim($argv[1] ?? 'http://localhost/kopipiro', '/');
$GLOBALS['jars'] = [];
$GLOBALS['check_count'] = 0;
$GLOBALS['check_failures'] = 0;

if (!function_exists('curl_init')) {
    fwrite(STDERR, "cURL is not enabled. Add 'extension=curl' to php.ini and run again.\n");
    exit(2);
}

function jar(string $persona): string
{
    if (!isset($GLOBALS['jars'][$persona])) {
        $GLOBALS['jars'][$persona] = tempnam(sys_get_temp_dir(), 'kp_jar_');
    }
    return $GLOBALS['jars'][$persona];
}

/**
 * One HTTP call as a persona (each persona keeps its own session cookie).
 */
function http(string $persona, string $method, string $path, ?array $json = null, ?array $form = null): array
{
    $handle = curl_init($GLOBALS['base'] . $path);
    $headers = ['Accept: application/json'];
    $options = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_COOKIEJAR => jar($persona),
        CURLOPT_COOKIEFILE => jar($persona),
    ];
    if ($json !== null) {
        $options[CURLOPT_POSTFIELDS] = json_encode($json);
        $headers[] = 'Content-Type: application/json';
    } elseif ($form !== null) {
        $options[CURLOPT_POSTFIELDS] = $form; // array -> multipart/form-data
    }
    $options[CURLOPT_HTTPHEADER] = $headers;
    curl_setopt_array($handle, $options);

    $raw = curl_exec($handle);
    $status = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
    $error = curl_error($handle);
    curl_close($handle);

    if ($raw === false) {
        return ['status' => 0, 'json' => null, 'raw' => $error];
    }
    return ['status' => $status, 'json' => json_decode($raw, true), 'raw' => $raw];
}

function check(string $label, bool $ok, string $detail = ''): void
{
    $GLOBALS['check_count']++;
    if (!$ok) {
        $GLOBALS['check_failures']++;
    }
    printf("%s  %s%s\n", $ok ? 'PASS' : 'FAIL', $label, (!$ok && $detail !== '') ? ' — ' . $detail : '');
}

// ---------------------------------------------------------------- catalogue
$products = http('guest', 'GET', '/api/products.php');
check('GET /api/products.php -> 200', $products['status'] === 200, 'status ' . $products['status']);
$product = $products['json']['products'][0] ?? null;
check('catalogue returns at least one product', $product !== null);
if (!$product) {
    fwrite(STDERR, "\nCannot continue without a product. Is the database imported?\n");
    exit(1);
}
$productId = (int) $product['id'];
$price = (int) $product['price'];
check('guest pays the normal price', (int) $product['final_price'] === $price);

$me = http('guest', 'GET', '/api/auth.php?action=me');
check('guest /me -> {role:"guest"}', $me['status'] === 200 && ($me['json']['role'] ?? '') === 'guest');

$noPhone = http('guest', 'POST', '/api/orders.php', ['items' => [['product_id' => $productId, 'qty' => 1]]]);
check('guest order without phone -> 400', $noPhone['status'] === 400, 'status ' . $noPhone['status']);

// ------------------------------------------------------------------- owner
$owner = http('owner', 'POST', '/api/auth.php?action=login', ['username' => 'owner', 'password' => 'owner123']);
check('owner login -> 200', $owner['status'] === 200 && ($owner['json']['role'] ?? '') === 'owner', 'status ' . $owner['status']);

$badLogin = http('guest', 'POST', '/api/auth.php?action=login', ['username' => 'owner', 'password' => 'wrong']);
check('wrong password -> 401', $badLogin['status'] === 401, 'status ' . $badLogin['status']);

$sales = http('owner', 'GET', '/api/sales.php');
check('owner GET /api/sales.php -> 200', $sales['status'] === 200 && isset($sales['json']['daily'], $sales['json']['top']));

// ---------------------------------------------------------------- customer
$username = 'smoke_' . bin2hex(random_bytes(3));
$register = http('customer', 'POST', '/api/auth.php?action=register', [
    'name' => 'Smoke Customer',
    'username' => $username,
    'password' => 'smokepass123',
]);
check('register customer -> 201', $register['status'] === 201, 'status ' . $register['status']);

$duplicate = http('guest', 'POST', '/api/auth.php?action=register', [
    'name' => 'Smoke Customer',
    'username' => $username,
    'password' => 'smokepass123',
]);
check('duplicate username -> 409', $duplicate['status'] === 409, 'status ' . $duplicate['status']);

$weak = http('guest', 'POST', '/api/auth.php?action=register', [
    'name' => 'Weak', 'username' => 'we', 'password' => 'short',
]);
check('weak registration data -> 400', $weak['status'] === 400, 'status ' . $weak['status']);

$login = http('customer', 'POST', '/api/auth.php?action=login', ['username' => $username, 'password' => 'smokepass123']);
check('customer login -> 200', $login['status'] === 200 && ($login['json']['role'] ?? '') === 'customer');

$memberCatalogue = http('customer', 'GET', '/api/products.php');
$memberProduct = null;
foreach (($memberCatalogue['json']['products'] ?? []) as $row) {
    if ((int) $row['id'] === $productId) {
        $memberProduct = $row;
    }
}
check('member final_price is 10% lower', $memberProduct !== null && (int) $memberProduct['final_price'] === (int) round($price * 0.9));

// ------------------------------------------------------------ order + money
$order = http('customer', 'POST', '/api/orders.php', [
    'items' => [['product_id' => $productId, 'qty' => 2, 'price' => 1, 'unit_price' => 1, 'line_total' => 2]],
    'phone' => '081200000000',
]);
check('customer order -> 201', $order['status'] === 201, 'status ' . $order['status'] . ' ' . substr((string) ($order['raw'] ?? ''), 0, 120));
$subtotal = (int) ($order['json']['subtotal'] ?? 0);
$discount = (int) ($order['json']['discount'] ?? 0);
$paid = (int) ($order['json']['total'] ?? 0);
$orderId = (int) ($order['json']['id'] ?? 0);
check('prices sent by the browser are ignored', $subtotal === $price * 2, 'subtotal ' . $subtotal);
check('discount equals subtotal - total', $discount === $subtotal - $paid, "subtotal $subtotal, discount $discount, total $paid");
check('total equals the discounted lines', $paid === (int) round($price * 0.9) * 2, 'total ' . $paid);
check('order is assigned to a cashier', ($order['json']['cashier']['id'] ?? 0) > 0);

$customerAdd = http('customer', 'POST', '/api/products.php', null, ['name' => 'Nope', 'category_id' => '1', 'price' => '1000']);
check('customer cannot add a product -> 403', $customerAdd['status'] === 403, 'status ' . $customerAdd['status']);

// ------------------------------------------------------------------ cashier
$kasir = http('kasir', 'POST', '/api/auth.php?action=login', ['username' => 'kasir1', 'password' => 'kasir123']);
check('cashier login -> 200', $kasir['status'] === 200 && ($kasir['json']['role'] ?? '') === 'kasir', 'status ' . $kasir['status']);

$kasirSales = http('kasir', 'GET', '/api/sales.php');
check('cashier on /api/sales.php -> 403', $kasirSales['status'] === 403, 'status ' . $kasirSales['status']);

$kasirOrders = http('kasir', 'GET', '/api/orders.php');
check('cashier GET orders -> 200', $kasirOrders['status'] === 200, 'status ' . $kasirOrders['status']);
$foreign = 0;
foreach (($kasirOrders['json'] ?? []) as $row) {
    if (($row['cashier'] ?? null) !== 'kasir1') {
        $foreign++;
    }
}
check('cashier sees only assigned orders', $foreign === 0, $foreign . ' foreign order(s)');

// -------------------------------------------------------------- order status
$ownerUpdate = http('owner', 'PUT', '/api/orders.php?id=' . $orderId, ['status' => 'completed']);
check('owner updates status -> 200', $ownerUpdate['status'] === 200, 'status ' . $ownerUpdate['status']);

$badStatus = http('owner', 'PUT', '/api/orders.php?id=' . $orderId, ['status' => 'shipped']);
check('invalid status -> 400', $badStatus['status'] === 400, 'status ' . $badStatus['status']);

$unknownOrder = http('owner', 'PUT', '/api/orders.php?id=999999', ['status' => 'completed']);
check('unknown order -> 404', $unknownOrder['status'] === 404, 'status ' . $unknownOrder['status']);

$kasirUpdate = http('kasir', 'PUT', '/api/orders.php?id=' . $orderId, ['status' => 'ready']);
$assignedToKasir = ($order['json']['cashier']['id'] ?? 0) === ($kasir['json']['id'] ?? -1);
check(
    'cashier may only move their own order ' . ($assignedToKasir ? '(assigned: 200)' : '(not assigned: 404)'),
    $kasirUpdate['status'] === ($assignedToKasir ? 200 : 404),
    'status ' . $kasirUpdate['status']
);

// ----------------------------------------------------------------- products
$created = http('owner', 'POST', '/api/products.php', null, [
    'name' => 'Smoke Espresso',
    'category_id' => '1',
    'price' => '25000',
    'description' => 'Created by the smoke test.',
]);
check('owner adds a product -> 201', $created['status'] === 201, 'status ' . $created['status'] . ' ' . substr((string) ($created['raw'] ?? ''), 0, 120));
$newId = (int) ($created['json']['id'] ?? 0);

$priceUpdate = http('owner', 'PUT', '/api/products.php?id=' . $newId, ['price' => 26000]);
check('owner changes a price -> 200', $priceUpdate['status'] === 200, 'status ' . $priceUpdate['status']);

$unknownProduct = http('owner', 'PUT', '/api/products.php?id=999999', ['price' => 1000]);
check('unknown product -> 404', $unknownProduct['status'] === 404, 'status ' . $unknownProduct['status']);

// Keep the catalogue clean: hide the product this run created.
$hide = http('owner', 'PUT', '/api/products.php?id=' . $newId, ['is_available' => false]);
check('owner hides the test product -> 200', $hide['status'] === 200, 'status ' . $hide['status']);
$afterHide = http('guest', 'GET', '/api/products.php');
$stillVisible = false;
foreach (($afterHide['json']['products'] ?? []) as $row) {
    if ((int) $row['id'] === $newId) {
        $stillVisible = true;
    }
}
check('hidden product leaves the catalogue', !$stillVisible);

$badUpload = http('owner', 'POST', '/api/products.php', null, [
    'name' => 'Bad Upload',
    'category_id' => '1',
    'price' => '5000',
    'image' => new CURLFile(__FILE__, 'image/png', 'evil.png'),
]);
check('a PHP file sent as .png -> 400', $badUpload['status'] === 400, 'status ' . $badUpload['status']);

$wrongMethod = http('owner', 'DELETE', '/api/products.php');
check('DELETE /api/products.php -> 405', $wrongMethod['status'] === 405, 'status ' . $wrongMethod['status']);

// ------------------------------------------------------------------- logout
$logout = http('customer', 'POST', '/api/auth.php?action=logout');
check('logout -> 200', $logout['status'] === 200, 'status ' . $logout['status']);
$afterLogout = http('customer', 'GET', '/api/auth.php?action=me');
check('session is gone after logout', ($afterLogout['json']['role'] ?? '') === 'guest');

foreach ($GLOBALS['jars'] as $file) {
    @unlink($file);
}

printf("\n%d checks, %d failure(s).\n", $GLOBALS['check_count'], $GLOBALS['check_failures']);
exit($GLOBALS['check_failures'] === 0 ? 0 : 1);
