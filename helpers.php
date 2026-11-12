<?php
/**
 * Helpers shared by the JSON API endpoints and the CLI tools.
 *
 * Mirrors the helper set from the "Introduction to APIs with Plain PHP" module
 * (out, body, me, need_role, final_price, image_url) but adapted to the project's
 * existing mysqli connection and session layout.
 */

require_once __DIR__ . '/auth.php';   // session_start(), e(), money(), layout_*
require_once __DIR__ . '/config.php'; // $conn, DISCOUNT_RATE

/**
 * Send a JSON response and stop. Sets the status code and the JSON content type.
 */
function out($data, int $code = 200): void
{
    http_response_code($code);
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * The decoded JSON request body. Returns an empty array for an empty or invalid body.
 */
function body(): array
{
    static $parsed = null;
    if ($parsed === null) {
        $raw = file_get_contents('php://input');
        $decoded = json_decode((string) $raw, true);
        $parsed = is_array($decoded) ? $decoded : [];
    }
    return $parsed;
}

function require_api_csrf(): void
{
    $provided = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!is_string($provided) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $provided)) {
        out(['message' => 'Invalid or missing CSRF token'], 419);
    }
}

/**
 * The signed-in user as a plain array, or null for a guest.
 * Never trust the session alone: the row is re-read so a deleted or disabled
 * account stops working immediately.
 */
function me(): ?array
{
    static $resolved = false;
    static $user = null;
    if ($resolved) {
        return $user;
    }
    $resolved = true;
    if (!logged_in()) {
        return null;
    }
    global $conn;
    $id = (int) $_SESSION['user_id'];
    $statement = $conn->prepare('SELECT id, username, name, role FROM users WHERE id = ? AND is_active = 1');
    $statement->bind_param('i', $id);
    $statement->execute();
    $row = $statement->get_result()->fetch_assoc();
    if (!$row) {
        unset($_SESSION['user_id'], $_SESSION['username'], $_SESSION['role']);
        return null;
    }
    $user = [
        'id' => (int) $row['id'],
        'username' => $row['username'],
        'name' => $row['name'],
        'role' => $row['role'],
    ];
    $_SESSION['username'] = $user['username'];
    $_SESSION['role'] = $user['role'];
    return $user;
}

/**
 * Require one of the given roles. Sends 401 when nobody is signed in and
 * 403 when the role is not allowed, then returns the user.
 */
function need_role(array $roles): array
{
    $user = me();
    if (!$user) {
        out(['message' => 'Please log in'], 401);
    }
    if (!in_array($user['role'], $roles, true)) {
        out(['message' => 'Forbidden'], 403);
    }
    return $user;
}

/**
 * Reject a request whose HTTP method is not in the list. Sends 405 + Allow header.
 */
function require_method(array $allowed): void
{
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if (!in_array($method, $allowed, true)) {
        if (!headers_sent()) {
            header('Allow: ' . implode(', ', $allowed));
        }
        out(['message' => 'Method not allowed'], 405);
    }
}

/**
 * The price this visitor actually pays. The 10% member discount lives here and
 * nowhere else — the browser only displays what this function returns.
 */
function final_price(int $price, ?array $user): int
{
    $member = $user && $user['role'] === 'customer';
    return $member ? (int) round($price * (1 - DISCOUNT_RATE)) : $price;
}

/**
 * Turn a stored image value into something usable as an <img src>.
 * Product images are either a full URL (imported from the public API),
 * a project path (assets/img/*.svg) or a bare upload file name.
 */
function image_url(?string $image): ?string
{
    $image = trim((string) $image);
    if ($image === '') {
        return null;
    }
    if (preg_match('#^https://[a-z0-9.-]+(?::\d+)?(?:/[^\s]*)?$#i', $image)) {
        return $image;
    }
    if (preg_match('#^(?:assets/[a-z0-9_./-]+|uploads/[a-f0-9]{16,32}\.(?:jpg|png|webp))$#i', $image)) {
        return $image;
    }
    if (preg_match('#^[a-z0-9_-]+\.(?:jpg|png|webp)$#i', $image)) {
        return 'uploads/' . $image;
    }
    return 'assets/img/coffee.svg';
}

/**
 * GET a JSON document over HTTP. Uses cURL when the extension is enabled and
 * falls back to the streams wrapper otherwise. Returns ['error' => ...] on any
 * failure instead of throwing, so callers can report a clear message.
 */
function http_get_json(string $url, int $timeout = 10): array
{
    if (function_exists('curl_init')) {
        $handle = curl_init($url);
        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
        ]);
        $response = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
        $error = curl_error($handle);
        curl_close($handle);
        if ($response === false) {
            return ['error' => $error !== '' ? $error : 'request failed'];
        }
        if ($status !== 200) {
            return ['error' => 'HTTP ' . $status];
        }
        $data = json_decode((string) $response, true);
        return is_array($data) ? $data : ['error' => 'response was not JSON'];
    }

    $context = stream_context_create(['http' => [
        'timeout' => $timeout,
        'header' => "Accept: application/json\r\n",
        'ignore_errors' => true,
    ]]);
    $response = @file_get_contents($url, false, $context);
    if ($response === false) {
        return ['error' => 'request failed (cURL is not enabled)'];
    }
    $data = json_decode($response, true);
    return is_array($data) ? $data : ['error' => 'response was not JSON'];
}
